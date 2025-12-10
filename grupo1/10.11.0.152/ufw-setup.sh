#!/bin/bash

echo "==> Configurando UFW para 10.11.0.152 (Raspberry Pi - Escáner)..."

# Reset total
ufw --force reset

# Política segura
ufw default deny incoming
ufw default allow outgoing

echo "==> Permitimos SSH"
ufw allow 22/tcp

echo "==> Permitimos API en puerto 5000"
ufw allow 5000/tcp

echo "==> Activando UFW..."
ufw --force enable

echo "==> Reglas aplicadas:"
ufw status verbose
