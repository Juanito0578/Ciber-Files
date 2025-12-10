#!/usr/bin/env python3
from flask import Flask, request, jsonify
import subprocess
import re
import ipaddress
import os
import signal
import json
from pathlib import Path

# Ruta al script de escaneo
SCRIPT_PATH = "/opt/scan_vulns/scan.py"

# Donde vamos a guardar el PID del scan actual
RUN_DIR  = Path("/opt/scan_vulns/run")
PID_FILE = RUN_DIR / "scan.pid"

# Fichero de estado que escribe scan.py
STATUS_FILE = Path("/opt/scan_vulns/scan_status.json")

RUN_DIR.mkdir(parents=True, exist_ok=True)

app = Flask(__name__)


# =========================================================
# PARSEO DE TARGETS
# =========================================================
def parse_target_expression(expr: str):
    """
    Acepta cosas como:
      - '10.11.0.15'
      - '10.11.0.15,10.11.0.16,10.11.0.152'
      - '10.11.0.10-20'      (rango de último octeto)
      - '10.11.0.0/24'       (subred)
      - mezcla: '10.11.0.15,10.11.0.10-20,10.11.0.0/24'

    Devuelve una lista de IPs únicas como strings.
    Lanza ValueError si algo no cuadra.
    """
    expr = expr.strip()
    if not expr:
        return []

    targets = set()
    tokens = [t.strip() for t in expr.split(',') if t.strip()]

    for tok in tokens:
        # Subred tipo 10.11.0.0/24
        if '/' in tok:
            try:
                net = ipaddress.ip_network(tok, strict=False)
                for ip in net.hosts():
                    targets.add(str(ip))
            except ValueError:
                raise ValueError(f"Invalid subnet: {tok}")
            continue

        # Rango tipo 10.11.0.10-20  (solo último octeto)
        if '-' in tok:
            try:
                base, last_range = tok.rsplit('.', 1)
                start_str, end_str = last_range.split('-', 2)
                start = int(start_str)
                end = int(end_str)
            except Exception:
                raise ValueError(f"Invalid IP range: {tok}")

            if start < 0 or end > 255 or start > end:
                raise ValueError(f"IP range out of bounds or inverted: {tok}")

            for n in range(start, end + 1):
                ip_str = f"{base}.{n}"
                try:
                    ipaddress.ip_address(ip_str)
                except ValueError:
                    raise ValueError(f"Invalid IP generated in range: {ip_str}")
                targets.add(ip_str)
            continue

        # IP simple
        try:
            ip = ipaddress.ip_address(tok)
        except ValueError:
            raise ValueError(f"Invalid IP: {tok}")
        targets.add(str(ip))

    # Ordenamos por octetos
    return sorted(targets, key=lambda x: tuple(map(int, x.split("."))))


# =========================================================
# PARSEO DE PUERTOS
# =========================================================
def normalize_ports_expression(expr: str) -> str:
    """
    Normaliza y valida la expresión de puertos.

    Acepta:
      - "all"   -> todos los puertos
      - "80"
      - "22,80,443"
      - "20-30"
      - mezcla: "22,80-100,443"

    Devuelve:
      - "all" o
      - string normalizada "22,80-100,443"

    Lanza ValueError si algo no es válido.
    """
    expr = (expr or "").strip()
    if expr == "" or expr.lower() == "all":
        return "all"

    # Solo dígitos, comas, guiones y espacios
    if not re.match(r'^[0-9,\-\s]+$', expr):
        raise ValueError(
            "Invalid ports format. Use 'all' or digits, commas and dashes "
            "(e.g. 22,80-100,443)."
        )

    tokens = [t.strip() for t in expr.split(",") if t.strip()]
    if not tokens:
        raise ValueError("No valid ports specified.")

    for tok in tokens:
        if "-" in tok:
            parts = tok.split("-", 1)
            if len(parts) != 2 or not parts[0].isdigit() or not parts[1].isdigit():
                raise ValueError(f"Invalid port range: {tok}")
            start = int(parts[0])
            end = int(parts[1])
            if start < 1 or end > 65535 or start > end:
                raise ValueError(f"Port range out of bounds or inverted: {tok}")
        else:
            if not tok.isdigit():
                raise ValueError(f"Invalid port: {tok}")
            p = int(tok)
            if p < 1 or p > 65535:
                raise ValueError(f"Port out of range (1-65535): {tok}")

    # Devolvemos la expresión sin espacios extra
    return ",".join(tokens)


# =========================================================
# HELPERS PARA PROCESO
# =========================================================
def _get_running_pid():
    """Devuelve el PID guardado en el PID_FILE o None."""
    if not PID_FILE.exists():
        return None
    try:
        txt = PID_FILE.read_text().strip()
        if not txt:
            return None
        return int(txt)
    except Exception:
        return None


def _is_process_running(pid: int) -> bool:
    """Comprueba si el proceso con PID existe."""
    try:
        os.kill(pid, 0)
    except ProcessLookupError:
        return False
    except PermissionError:
        # existe pero no tenemos permisos (raro en este caso)
        return True
    else:
        return True


# =========================================================
# ENDPOINT: LANZAR SCAN
# =========================================================
@app.post("/api/scan")
def api_scan():
    """
    Endpoint que recibe:
      {
        "target": "10.11.0.15,10.11.0.16,10.11.0.152",
        "ports": "all" | "22,80,443" | "20-30" | "22,80-100,443",
        "intensity": "low" | "normal" | "high"
      }
    y lanza el script scan.py en segundo plano.
    """
    data = request.get_json(silent=True) or {}

    target_raw = (data.get("target") or "").strip()
    ports_raw  = (data.get("ports") or "").strip()
    intensity  = (data.get("intensity") or "normal").strip()

    # --- Bloqueamos si ya hay un scan en marcha ---
    existing_pid = _get_running_pid()
    if existing_pid and _is_process_running(existing_pid):
        return jsonify({
            "status": "error",
            "message": f"A scan is already running (PID {existing_pid}). Stop it before starting a new one."
        }), 409

    # --- Validación de destino ---
    if not target_raw:
        return jsonify({"status": "error", "message": "Missing 'target'."}), 400

    # Comprobación superficial de caracteres permitidos
    if not re.match(r'^[0-9\.,/\-\s]+$', target_raw):
        return jsonify({
            "status": "error",
            "message": "Invalid target format. Use only digits, '.', ',', '-' and '/'."
        }), 400

    # Parseamos para comprobar que la expresión es válida
    try:
        targets_list = parse_target_expression(target_raw)
    except ValueError as e:
        return jsonify({"status": "error", "message": str(e)}), 400

    if not targets_list:
        return jsonify({
            "status": "error",
            "message": "No valid IP addresses were obtained from the target expression."
        }), 400

    # --- Validación / normalización de puertos ---
    try:
        ports = normalize_ports_expression(ports_raw)
    except ValueError as e:
        return jsonify({"status": "error", "message": str(e)}), 400

    # --- Intensidad ---
    if intensity not in ("low", "normal", "high"):
        intensity = "normal"

    # Construimos el comando para lanzar el escaneo
    cmd = [
        "python3",
        SCRIPT_PATH,
        "--target", target_raw,   # pasamos la expresión original (ya validada)
        "--ports", ports,
        "--intensity", intensity,
        "--min-cvss", "7.0",
    ]

    # Lanzamos el script en segundo plano (no bloqueamos la API)
    try:
        proc = subprocess.Popen(
            cmd,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL
        )
        # Guardamos el PID
        PID_FILE.write_text(str(proc.pid))
    except Exception as e:
        return jsonify({"status": "error", "message": f"Error launching scan: {e}"}), 500

    return jsonify({
        "status": "started",
        "message": (
            f"Scan started on {target_raw} "
            f"(ports={ports}, intensity={intensity}), PID={proc.pid}."
        )
    }), 202


# =========================================================
# ENDPOINT: PARAR SCAN
# =========================================================
@app.post("/api/scan/stop")
def api_scan_stop():
    pid = _get_running_pid()
    if not pid:
        return jsonify({
            "status": "error",
            "message": "No running scan found (no PID stored)."
        }), 404

    # Comprobamos si sigue vivo
    if not _is_process_running(pid):
        # El proceso ya no existe → limpiamos PID y devolvemos info
        try:
            PID_FILE.unlink(missing_ok=True)
        except Exception:
            pass
        return jsonify({
            "status": "error",
            "message": f"Scan process with PID {pid} is not running anymore."
        }), 404

    # Intentamos enviar SIGTERM
    try:
        os.kill(pid, signal.SIGTERM)
    except ProcessLookupError:
        try:
            PID_FILE.unlink(missing_ok=True)
        except Exception:
            pass
        return jsonify({
            "status": "error",
            "message": f"Scan process with PID {pid} not found."
        }), 404
    except Exception as e:
        return jsonify({
            "status": "error",
            "message": f"Error stopping scan (PID {pid}): {e}"
        }), 500

    # Borramos el PID file (aunque aún esté terminando)
    try:
        PID_FILE.unlink(missing_ok=True)
    except Exception:
        pass

    return jsonify({
        "status": "ok",
        "message": f"Stop signal sent to scan process (PID {pid})."
    }), 200


# =========================================================
# ENDPOINT: STATUS (detallado con total_hosts / scanned_hosts)
# =========================================================
@app.get("/api/scan/status")
def api_scan_status():
    """
    Devuelve el estado del escaneo leyendo:
      - PID_FILE  -> si hay proceso en marcha
      - STATUS_FILE (JSON) -> state, total_hosts, scanned_hosts, current_host, log_file
    """
    status_data = None
    if STATUS_FILE.exists():
        try:
            status_data = json.loads(STATUS_FILE.read_text())
        except Exception:
            status_data = None

    pid = _get_running_pid()
    running = bool(pid and _is_process_running(pid))

    # Estado base
    state = "running" if running else "idle"
    if status_data and status_data.get("state"):
        # usa el state que escribe scan.py: discovering, running, finished, no_hosts, stopped, error
        state = status_data["state"]

    resp = {
        "status": state,         # para compatibilidad
        "state": state,          # nombre explícito
        "pid": pid if running else None,
    }

    if status_data:
        # Merge: total_hosts, scanned_hosts, current_host, log_file, etc.
        resp.update(status_data)

    return jsonify(resp), 200


if __name__ == "__main__":
    # Escuchamos en todas las interfaces en el puerto 5000
    # (restringe con firewall para que solo 10.11.0.15 pueda entrar)
    app.run(host="0.0.0.0", port=5000)
