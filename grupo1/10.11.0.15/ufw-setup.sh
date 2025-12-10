#!/bin/bash

echo "==> Configurando UFW para 10.11.0.15 (Servidor Docker ChorizoSQL)..."

# Reset por seguridad
ufw --force reset

# Política restrictiva por defecto
ufw default deny incoming
ufw default allow outgoing

echo "==> Permitimos SSH"
ufw allow 22/tcp

echo "==> Permitimos LDAP"
ufw allow 389/tcp
ufw allow 636/tcp

echo "==> Permitimos HTTPS (Apache con SSL)"
ufw allow 443/tcp

# (Opcional) Si quieres poder acceder desde red interna solamente:
# ufw allow from 10.11.0.0/24 to any port 22 proto tcp
# ufw allow from 10.11.0.0/24 to any port 389 proto tcp
# ufw allow from 10.11.0.0/24 to any port 636 proto tcp
# ufw allow from 10.11.0.0/24 to any port 443 proto tcp

echo "==> Denegamos todo lo demás"
# Ya lo hace la política por defecto

echo "==> Activando UFW..."
ufw --force enable

echo "==> Configuración final:"
ufw status verbose
