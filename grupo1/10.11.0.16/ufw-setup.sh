#!/bin/bash

echo "==> Configurando UFW para 10.11.0.16 (MariaDB + WordPress)..."

# Reset
ufw --force reset

# Política por defecto
ufw default deny incoming
ufw default allow outgoing

echo "==> Permitimos SSH"
ufw allow 22/tcp

echo "==> Permitimos WordPress HTTPS"
ufw allow 443/tcp

echo "==> Permitimos MariaDB (3306)"
ufw allow 3306/tcp

echo "==> Activando UFW..."
ufw --force enable

echo "==> Reglas aplicadas:"
ufw status verbose
