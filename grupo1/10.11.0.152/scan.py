#!/usr/bin/env python3
import os
import subprocess
from datetime import datetime
from pathlib import Path
import sys
import re
import tempfile
import argparse
import json
import signal

import mysql.connector
from mysql.connector import Error

# ============================================================
# CONFIGURACIÓN RUTAS
# ============================================================

BASE_DIR = "/opt/scan_vulns"
STATUS_FILE = f"{BASE_DIR}/scan_status.json"
PID_FILE = f"{BASE_DIR}/scan.pid"
LOG_ROOT = f"{BASE_DIR}/log"

# ============================================================
# CARGA .env (python-dotenv)
# ============================================================

try:
    from dotenv import load_dotenv
except ImportError:
    load_dotenv = None

if load_dotenv is not None:
    env_path = os.path.join(BASE_DIR, ".env")
    if os.path.exists(env_path):
        load_dotenv(env_path)
    else:
        # Si no hay .env, se pueden usar variables de entorno del sistema
        pass
else:
    # Si no está instalado python-dotenv, seguimos pero sólo con variables
    # de entorno del sistema.
    pass

# ============================================================
# CONFIGURACIÓN BBDD (desde .env / entorno)
# ============================================================

DB_HOST = os.getenv("DB_HOST", "10.11.0.16")
DB_PORT = int(os.getenv("DB_PORT", "3306"))
DB_USER = os.getenv("DB_USER", "chorizosql")
DB_PASS = os.getenv("DB_PASS", "My_Chorizo")
DB_NAME = os.getenv("DB_NAME", "dbchorizosql")


# ============================================================
# LOGGING
# ============================================================

def init_logger():
    """
    Crea una carpeta única para este escaneo con la fecha.
    Devuelve la ruta del archivo log para escribir en él.
    """
    ts = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
    folder = f"{LOG_ROOT}/{ts}"
    os.makedirs(folder, exist_ok=True)
    logfile = f"{folder}/scan.log"
    return logfile


LOG_FILE = init_logger()


def log(msg: str):
    """Escribe mensajes en el logfile sin hacer print()."""
    try:
        with open(LOG_FILE, "a") as f:
            f.write(f"{datetime.now()} - {msg}\n")
    except Exception:
        pass


# ============================================================
# SISTEMA PROGRESO
# ============================================================

def write_status(state: str, total_hosts: int = 0, scanned_hosts: int = 0, current_host: str | None = None):
    """
    Guarda el estado del escaneo en JSON para que la API/PHP lo lean.

    state:
      - discovering | running | finished | no_hosts | stopped | error
    """
    data = {
        "state": state,
        "total_hosts": total_hosts,
        "scanned_hosts": scanned_hosts,
        "current_host": current_host,
        "log_file": LOG_FILE,
    }
    try:
        with open(STATUS_FILE, "w") as f:
            json.dump(data, f)
    except Exception:
        pass


# ============================================================
# AUXILIARES BBDD
# ============================================================

def cvss_to_severity(score: float):
    if score is None:
        return None
    if score >= 9.0:
        return "CRITICAL"
    if score >= 7.0:
        return "HIGH"
    if score >= 4.0:
        return "MEDIUM"
    if score > 0.0:
        return "LOW"
    return None


def get_db_conn():
    conn = mysql.connector.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
    )
    return conn


def reset_db(conn):
    """
    ANTES limpiaba las tablas scans y services.

    AHORA NO SE USA para mantener historial.
    La dejamos por si algún día quieres hacer un script de limpieza manual.
    """
    cur = conn.cursor()
    log("[DB] Limpiando tablas scans y services...")
    cur.execute("DELETE FROM services")
    cur.execute("DELETE FROM scans")
    cur.execute("ALTER TABLE services AUTO_INCREMENT = 1")
    cur.execute("ALTER TABLE scans AUTO_INCREMENT = 1")
    conn.commit()
    cur.close()
    log("[DB] Limpieza completada.")


def insert_scan(conn, started_at, finished_at, network, notes=""):
    """
    Inserta un registro en la tabla scans.

    - network: aquí vamos a guardar el TARGET original (ej: '10.11.0.0/24').
    """
    sql = """
        INSERT INTO scans (started_at, finished_at, network, notes)
        VALUES (%s, %s, %s, %s)
    """
    cur = conn.cursor()
    cur.execute(sql, (started_at, finished_at, network, notes))
    conn.commit()
    scan_id = cur.lastrowid
    cur.close()
    log(f"[DB] Nuevo scan guardado id={scan_id} (network={network})")
    return scan_id


def update_scan_finished_at(conn, scan_id, finished_at):
    """
    Actualiza la columna finished_at de un scan concreto.
    """
    sql = "UPDATE scans SET finished_at = %s WHERE id = %s"
    cur = conn.cursor()
    cur.execute(sql, (finished_at, scan_id))
    conn.commit()
    cur.close()
    log(f"[DB] Scan id={scan_id} actualizado finished_at={finished_at}")


def insert_service(conn, scan_id, ip, port, proto, state, service_name,
                   product, version, cve_id, cve_title, severity):
    now = datetime.now()
    sql = """
        INSERT INTO services (
            scan_id, ip, port, protocol, state,
            service_name, product, version,
            cve_id, cve_title, severity, last_seen
        )
        VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    cur = conn.cursor()
    cur.execute(sql, (
        scan_id,
        ip,
        port,
        proto,
        state,
        service_name,
        product,
        version,
        cve_id,
        cve_title,
        severity,
        now
    ))
    conn.commit()
    sid = cur.lastrowid
    cur.close()
    log(f"[DB] Servicio guardado {ip}:{port}/{proto} id={sid} CVE={cve_id}")


# ============================================================
# DISCOVERY HOSTS
# ============================================================

def discover_hosts(network: str):
    """
    Hace un nmap -sn sobre la red o target pasado y devuelve
    una lista de IPs vivas.

    OJO: en el código original sólo se tienen en cuenta IPs 10.11.0.x.
    Si quieres generalizar, hay que adaptar el filtro.
    """
    log(f"[DISCOVERY] Escaneando hosts en: {network}")
    try:
        result = subprocess.run(
            ["nmap", "-sn", network],
            capture_output=True,
            text=True,
            check=False
        )
    except FileNotFoundError:
        log("[ERROR] Nmap no está instalado.")
        return []

    hosts = set()
    for line in result.stdout.splitlines():
        line = line.strip()
        if line.startswith("Nmap scan report for"):
            parts = line.split()
            ip = parts[-1]

            # Posible formato "hostname (IP)"
            if ip.startswith("(") and ip.endswith(")"):
                ip = ip[1:-1]

            # Filtro original (sólo 10.11.0.x que no terminen en .0)
            if ip.startswith("10.11.0.") and not ip.endswith(".0"):
                hosts.add(ip)

    hosts = sorted(hosts, key=lambda x: int(x.split(".")[-1]))
    if not hosts:
        log("[DISCOVERY] No se encontraron hosts vivos.")
    else:
        log(f"[DISCOVERY] Hosts vivos encontrados: {', '.join(hosts)}")
    return list(hosts)


# ============================================================
# RANGOS IP (para agrupar hosts internamente)
# ============================================================

def get_range_label_for_ip(ip: str) -> str:
    """
    Agrupa IPs por bloques de 30 en el último octeto:
    1-30, 31-60, etc. Ejemplo: 10.11.0.5 -> '10.11.0.1-30'
    """
    octets = ip.split(".")
    last = int(octets[-1])
    base = ".".join(octets[:3]) + "."
    start = ((last - 1) // 30) * 30 + 1
    end = min(start + 29, 255)
    return f"{base}{start}-{end}"


def group_hosts_by_range(hosts):
    ranges = {}
    for ip in hosts:
        label = get_range_label_for_ip(ip)
        ranges.setdefault(label, []).append(ip)
    return ranges


# ============================================================
# NMAP
# ============================================================

def build_nmap_args(intensity: str, ports: str):
    args = []

    # Intensidad → timing template
    if intensity == "low":
        args.append("-T2")
    elif intensity == "high":
        args.append("-T4")
    else:
        args.append("-T3")

    # Tipo de scan
    args += ["-sS", "-sV"]

    # Puertos
    if ports == "all":
        args += ["-p-"]
    else:
        args += ["-p", ports]

    # Script de vulners
    args += ["--script", "vulners"]

    return args


def run_nmap_scan(host: str, output_file: Path, nmap_args):
    cmd = ["nmap"] + nmap_args + ["-oN", str(output_file), host]
    log(f"[NMAP] Escaneando host {host} → {output_file}")
    subprocess.run(cmd, check=False)


# ============================================================
# PARSER NMAP
# ============================================================

def parse_nmap_output(nmap_file: Path, min_cvss: float):
    """
    Parsea la salida normal (-oN) de nmap con script vulners.
    Devuelve una lista de puertos con su lista de CVEs asociadas.

    Estructura de retorno:
    [
      {
        "port": 22,
        "protocol": "tcp",
        "service": "ssh",
        "product": "OpenSSH",
        "version": "8.2p1",
        "details": "...",
        "cves": [
          {"id": "CVE-XXXX-YYYY", "score": 9.8, "url": "https://..."},
          ...
        ]
      },
      ...
    ]
    """
    ports = []
    port_index = {}
    current_port_key = None
    in_vulners = False

    if not nmap_file.exists():
        return []

    port_line_re = re.compile(r'^(\d+)\/(\w+)\s+open\s+(\S+)\s*(.*)$')
    cve_line_re = re.compile(r'(CVE-\d{4}-\d+)\s+([\d\.]+)\s+(\S+)')

    with nmap_file.open("r", encoding="utf-8", errors="ignore") as f:
        for raw_line in f:
            line = raw_line.rstrip("\n")

            # Línea de puerto abierto
            m_port = port_line_re.match(line.strip())
            if m_port:
                port_str, proto, service, extra = m_port.groups()
                port_num = int(port_str)
                extra = extra.strip()

                if extra:
                    parts = extra.split()
                    product = parts[0]
                    version = " ".join(parts[1:]) if len(parts) > 1 else ""
                else:
                    product = ""
                    version = ""

                ports.append({
                    "port": port_num,
                    "protocol": proto,
                    "service": service,
                    "product": product,
                    "version": version,
                    "details": extra,
                    "cves": []
                })
                key = (port_num, proto)
                port_index[key] = len(ports) - 1
                current_port_key = key
                in_vulners = False
                continue

            # Inicio de bloque vulners:
            if "vulners:" in line and current_port_key is not None:
                in_vulners = True
                continue

            if in_vulners:
                # Salimos del bloque si deja de empezar por '|'
                if not line.strip().startswith("|"):
                    in_vulners = False
                    continue

                m_cve = cve_line_re.search(line)
                if m_cve:
                    cve_id, score_str, url = m_cve.groups()
                    try:
                        score = float(score_str)
                    except ValueError:
                        continue

                    if score >= min_cvss:
                        idx = port_index.get(current_port_key)
                        if idx is not None:
                            # Evitar duplicados, quedándonos con el score más alto
                            existing = next(
                                (c for c in ports[idx]["cves"] if c["id"] == cve_id),
                                None
                            )
                            if existing:
                                if existing["score"] < score:
                                    existing["score"] = score
                                    existing["url"] = url
                            else:
                                ports[idx]["cves"].append({
                                    "id": cve_id,
                                    "score": score,
                                    "url": url
                                })

    # Ordenamos CVEs por score desc
    for p in ports:
        p["cves"].sort(key=lambda c: (-c["score"], c["id"]))

    # Ordenamos puertos
    ports.sort(key=lambda p: p["port"])

    return ports


# ============================================================
# MANEJO DE SEÑALES (STOP)
# ============================================================

def handle_stop_signal(signum, frame):
    log(f"[SIGNAL] Señal {signum} recibida. Marcando estado como 'stopped' y saliendo.")
    write_status("stopped")
    try:
        if os.path.exists(PID_FILE):
            os.remove(PID_FILE)
    except Exception:
        pass
    sys.exit(0)


# ============================================================
# MAIN
# ============================================================

def main(target: str, ports: str, intensity: str, min_cvss: float):
    write_status("discovering")
    log("==========================================")
    log("ESCANEO INICIADO")
    log(f"Target: {target}")
    log(f"Ports: {ports}")
    log(f"Intensity: {intensity}")
    log("==========================================")

    # Descubrimos hosts vivos
    targets = discover_hosts(target)
    if not targets:
        write_status("no_hosts", 0, 0, None)
        log("[FIN] No hay hosts. Terminando.")
        try:
            if os.path.exists(PID_FILE):
                os.remove(PID_FILE)
        except Exception:
            pass
        sys.exit(0)

    total_hosts = len(targets)
    scanned_hosts = 0
    write_status("running", total_hosts, scanned_hosts, None)

    # Agrupamos por rangos internos para ordenar escaneo, pero
    # BBDD sólo tendrá UN registro en scans por ejecución.
    grouped = group_hosts_by_range(targets)
    rango_list = sorted(
        grouped.keys(),
        key=lambda r: int(r.split(".")[-1].split("-")[0])
    )

    conn = get_db_conn()

    # YA NO LIMPIAMOS BBDD AQUÍ → historial completo
    # reset_db(conn)

    # Preparamos args de nmap
    nmap_args = build_nmap_args(intensity, ports)
    log(f"[NMAP] Args finales NMAP: {' '.join(nmap_args)}")

    # Creamos un único registro en scans por ejecución,
    # con network = target original.
    started_at = datetime.now()
    notes = f"nmap {' '.join(nmap_args)}"
    # finished_at se pone provisionalmente igual que started_at;
    # luego lo actualizamos al final del escaneo.
    scan_id = insert_scan(conn, started_at, started_at, target, notes)

    for rango in rango_list:
        hosts = grouped[rango]
        log(f"[RANGE] Procesando rango interno {rango} con hosts: {', '.join(hosts)}")

        for host in hosts:
            # Actualizamos estado con el host actual
            write_status("running", total_hosts, scanned_hosts, host)
            log(f"[SCAN] Host: {host}")

            with tempfile.NamedTemporaryFile(mode="w+", delete=False, suffix=".txt") as tmp:
                tmp_path = Path(tmp.name)

            run_nmap_scan(host, tmp_path, nmap_args)
            ports_info = parse_nmap_output(tmp_path, min_cvss)

            try:
                os.remove(tmp_path)
            except Exception:
                pass

            # Guardamos servicios en BBDD
            for p in ports_info:
                best = p["cves"][0] if p["cves"] else None
                best_id = best["id"] if best else None
                best_title = best["url"] if best else None
                best_score = best["score"] if best else None
                severity = cvss_to_severity(best_score) if best_score else None

                insert_service(
                    conn,
                    scan_id,
                    host,
                    p["port"],
                    p["protocol"],
                    "open",
                    p["service"],
                    p["product"],
                    p["version"],
                    best_id,
                    best_title,
                    severity
                )

            # Hemos terminado este host → incrementamos contador
            scanned_hosts += 1
            write_status("running", total_hosts, scanned_hosts, host)

    # Terminamos: actualizamos finished_at del scan
    finished_at = datetime.now()
    update_scan_finished_at(conn, scan_id, finished_at)

    conn.close()
    write_status("finished", total_hosts, scanned_hosts, None)
    log("[FIN] Escaneo completado correctamente.")

    try:
        if os.path.exists(PID_FILE):
            os.remove(PID_FILE)
    except Exception:
        pass


# ============================================================
# ENTRYPOINT
# ============================================================

if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Escáner de vulnerabilidades con nmap + vulners."
    )
    parser.add_argument(
        "--target",
        required=True,
        help="IP, rango (10.11.0.1-30) o subred (10.11.0.0/24) a escanear."
    )
    parser.add_argument(
        "--ports",
        default="all",
        help='Rango de puertos: "all" o "min-max" o lista "22,80-100,443".'
    )
    parser.add_argument(
        "--intensity",
        choices=["low", "normal", "high"],
        default="normal",
        help="Agresividad del escaneo (timing template)."
    )
    parser.add_argument(
        "--min-cvss",
        type=float,
        default=7.0,
        help="Umbral mínimo CVSS para guardar CVEs."
    )

    args = parser.parse_args()

    # Guardamos PID para poder hacer STOP (modo standalone)
    try:
        with open(PID_FILE, "w") as f:
            f.write(str(os.getpid()))
    except Exception:
        # si no podemos escribir el PID, lo registramos pero seguimos
        log("[WARN] No se pudo escribir PID_FILE.")

    # Registramos manejadores de señal para STOP
    signal.signal(signal.SIGTERM, handle_stop_signal)
    signal.signal(signal.SIGINT, handle_stop_signal)

    try:
        main(args.target, args.ports, args.intensity, args.min_cvss)
    except Exception as e:
        log(f"[ERROR] Excepción no controlada: {e}")
        write_status("error")
        try:
            if os.path.exists(PID_FILE):
                os.remove(PID_FILE)
        except Exception:
            pass
        sys.exit(1)
