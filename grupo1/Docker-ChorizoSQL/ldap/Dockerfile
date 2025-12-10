FROM debian:bullseye
ENV DEBIAN_FRONTEND=noninteractive

# Instalar paquetes incluyendo openssl
RUN apt-get update \
  && apt-get install -y debconf-utils gettext-base slapd ldap-utils openssl \
  && echo "slapd slapd/no_configuration boolean true" | debconf-set-selections \
  && rm -rf /var/lib/apt/lists/*

# Crear carpetas para certificados
RUN mkdir -p /etc/ssl/private /etc/ssl/certs

# Generar certificado y clave autofirmados para LDAPS
RUN openssl req -x509 -nodes -days 365 \
  -newkey rsa:2048 \
  -keyout /etc/ssl/private/ldap-server.key \
  -out /etc/ssl/certs/ldap-server.crt \
  -subj "/C=ES/ST=Example/L=Example/O=Example/CN=ldap-server"

# Copiar archivos de plantilla para slapd y entrypoint
COPY slapd.conf.template /etc/ldap/slapd.conf.template
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Cambiar permisos para que slapd pueda leer la clave
RUN chown openldap:openldap /etc/ssl/private/ldap-server.key \
 && chmod 600 /etc/ssl/private/ldap-server.key

# Crear directorios para ldap y asignar permisos
RUN mkdir -p /var/lib/ldap /var/run/slapd \
 && chown -R openldap:openldap /var/lib/ldap /var/run/slapd


# Exponer puertos LDAP (389) y LDAPS (636)
EXPOSE 389 636

ENTRYPOINT ["/entrypoint.sh"]
