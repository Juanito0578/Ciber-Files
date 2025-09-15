#!/bin/bash
# 1. Borrar reglas anteriores
ufw --force reset
# 2. Política RESTRICTIVA por defecto
ufw default deny incoming
ufw default deny outgoing
# Puertos SFTP/SSH (22), SMB (445), SNMP (161/udp)
ufw allow from 10.11.0.89 to any port 22 proto tcp
ufw allow from 10.11.0.89 to any port 445 proto tcp
ufw allow from 10.11.0.89 to any port 161 proto udp
# Rango 31010-31110 (solo TCP)
ufw allow from 10.11.0.89 to any proto tcp port 31010:31110
# Rango 50505-50509 (TCP y UDP)
ufw allow from 10.11.0.89 to any proto tcp port 50505:50509
ufw allow from 10.11.0.89 to any proto udp port 50505:50509
# 4. Permitir desde 172.16.2.201
ufw allow from 172.16.2.201 to any port 4444 proto udp
ufw allow from 172.16.2.201 to any port 139 proto tcp
ufw allow from 172.16.2.201 to any port 445 proto tcp
ufw allow from 172.16.2.201 to any port 3389 proto tcp
# 5. Puertos públicos SMTP (25), IMAP (143), POP3 (110)
ufw allow 25/tcp
ufw allow 110/tcp
ufw allow 143/tcp
# 6. Permitir MySQL/MariaDB (3306) desde la subred
ufw allow from 10.11.0.0 to any port 3306 proto tcp
# 7. Habilitar UFW y mostrar reglas
ufw --force enable
ufw status verbose