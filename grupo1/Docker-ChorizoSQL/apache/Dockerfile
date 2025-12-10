# Usa Debian como base
FROM debian:stable-slim

ENV DEBIAN_FRONTEND=noninteractive

# Instala Apache, PHP y extensiones necesarias
RUN apt-get update && \
    apt-get install -y apache2 openssl \
    php php-cli libapache2-mod-php php-ldap php-mysqli php-zip php-curl php-xml php-mbstring php-gd && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# Configuración PHP (límites de subida)
# ------------------------------------------------------------
RUN set -eux; \
    PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" ; \
    mkdir -p "/etc/php/${PHP_VER}/apache2/conf.d" ; \
    printf '%s\n' \
        'file_uploads = On' \
        'upload_max_filesize = 100M' \
        'post_max_size = 120M' \
        'max_file_uploads = 20' \
    > "/etc/php/${PHP_VER}/apache2/conf.d/zz-upload-limit.ini"

# Seguridad de sesión PHP (PHPSESSID secure + httponly + samesite)
RUN set -eux; \
    PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" ; \
    printf '%s\n' \
        'session.cookie_secure = 1' \
        'session.cookie_httponly = 1' \
        'session.use_strict_mode = 1' \
        'session.cookie_samesite = "Lax"' \
    > "/etc/php/${PHP_VER}/apache2/conf.d/zz-session-security.ini"

# Habilitar módulos Apache
RUN a2enmod ssl && \
    a2enmod rewrite && \
    a2enmod ldap && \
    a2enmod authnz_ldap && \
    a2enmod headers

# Crear carpeta SSL
RUN mkdir -p /etc/apache2/ssl

# Certificado autofirmado
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/apache2/ssl/apache.key \
    -out /etc/apache2/ssl/apache.crt \
    -subj "/C=ES/ST=Madrid/L=Madrid/O=MiOrg/OU=IT/CN=localhost"

# Crear DocumentRoot
RUN mkdir -p /var/www/html

# ------------------------------------------------------------
# VirtualHost HTTP → redirección a HTTPS
# ------------------------------------------------------------
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    ServerAdmin admin@10.11.0.15' \
    '    ServerName 10.11.0.15' \
    '    Redirect permanent / https://10.11.0.15/' \
    '    ErrorLog ${APACHE_LOG_DIR}/error_80.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access_80.log combined' \
    '</VirtualHost>' \
> /etc/apache2/sites-available/000-default.conf

# ------------------------------------------------------------
# VirtualHost HTTPS con HSTS
# ------------------------------------------------------------
RUN printf '%s\n' \
    '<VirtualHost _default_:443>' \
    '    ServerAdmin admin@10.11.0.15' \
    '    DocumentRoot /var/www/html' \
    '    SSLEngine on' \
    '    SSLCertificateFile /etc/apache2/ssl/apache.crt' \
    '    SSLCertificateKeyFile /etc/apache2/ssl/apache.key' \
    '    # Seguridad HSTS (HTTPS obligatorio)' \
    '    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"' \
    '    <Directory /var/www/html>' \
    '        Options Indexes FollowSymLinks' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '    ErrorLog ${APACHE_LOG_DIR}/error_443.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access_443.log combined' \
    '</VirtualHost>' \
> /etc/apache2/sites-available/default-ssl.conf

RUN a2ensite default-ssl

# ------------------------------------------------------------
# ENTRYPOINT para arreglar permisos en tiempo real
# ------------------------------------------------------------
RUN printf '%s\n' \
    '#!/bin/bash' \
    'set -e' \
    'echo "--- Fixing /var/www/html/img permissions ---"' \
    'mkdir -p /var/www/html/img' \
    'chown -R www-data:www-data /var/www/html/img' \
    'chmod -R 775 /var/www/html/img' \
    'echo "--- Starting Apache ---"' \
    'exec apachectl -D FOREGROUND' \
> /usr/local/bin/start-apache.sh && \
    chmod +x /usr/local/bin/start-apache.sh

EXPOSE 80 443

CMD ["/usr/local/bin/start-apache.sh"]
