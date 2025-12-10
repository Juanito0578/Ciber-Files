# 🛡️ Sistema de Escaneo y Gestión · Infraestructura ChorizoSQL

Este proyecto implementa un sistema completo distribuido entre tres servidores: un servidor Docker con Apache + LDAP + panel web en PHP, un servidor MariaDB + WordPress y una Raspberry Pi que realiza escaneos Nmap. Aquí se documenta toda la infraestructura real.

---

# 📡 Infraestructura General

| Servidor | IP | Función | Ruta Principal |
|---------|----|---------|----------------|
| **Docker** | **10.11.0.15** | Apache, PHP, OpenLDAP, panel web, SSO | `/proyectos/ChorizoSQL-main/Docker-ChorizoSQL/` |
| **MariaDB + WordPress** | **10.11.0.16** | Base de datos & WordPress | `/var/www/html/wordpress/` |
| **Raspberry Pi (Escáner)** | **10.11.0.152** | Escaneo Nmap + Python | `/opt/scan_vulns/` |

---

# 🐳 1. Servidor Docker (10.11.0.15)

Ruta del Scripts:

```
/home/chorizo/scripts/
```

Ruta del proyecto:

```
/proyectos/ChorizoSQL-main/Docker-ChorizoSQL/
```

## 📂 Estructura completa:

```
Docker-ChorizoSQL/
├── apache/
│   └── Dockerfile
├── ldap/
│   ├── Dockerfile
│   ├── entrypoint.sh
│   └── slapd.conf.template
├── volumes/
│   ├── apache-html/
│   │   ├── Conf/config.php
│   │   ├── css/style.css
│   │   ├── img/
│   │   ├── paginas/
│   │   │   ├── administration/
│   │   │   ├── inc/
│   │   │   ├── projects/
│   │   │   ├── perfil.php
│   │   │   └── index.php
│   │   ├── sso/
│   │   │   ├── index.php
│   │   │   ├── logout.php
│   │   │   └── sso-check.php
│   │   ├── .htaccess
│   │   └── index.php
│   ├── apache-settins/
│   │   └── html/
│   ├── ldap-config/
│   │   ├── cn=config
│   │   ├── cn=config.ldif
│   │   ├── docker-openldap-was-admin-reset
│   │   └── docker-openldap-was-started
│   └── ldap-data/
│       ├── data.mdb
│       └── lock.mdb
├── .env
└── docker-compose.yml
```

---

# 🗄️ 2. Servidor MariaDB + WordPress (10.11.0.16)
Ruta del Scripts:

```
/home/chorizopi/scripts/
```

WordPress se encuentra en:

```
/var/www/html/wordpress/
```

## 📦 Base de datos del proyecto: `dbchorizosql`

Tablas:

```
accesos
gastos
ldap_users
scans
services
```

## 📦 Base de datos WordPress (`wordpress_db`)
Incluye tablas estándar:

```
wp_posts
wp_users
wp_options
wp_comments
...
```

---

# 🐍 3. Raspberry Pi – Escáner de vulnerabilidades (10.11.0.152)

Ruta del Scripts:

```
/home/chorizopi/scripts/
```

Ruta del escáner:

```
/opt/scan_vulns/
```

## 📂 Archivos reales:

```
/opt/scan_vulns/
├── venv/
├── run/
├── log/
├── scan.py
├── api.py
├── scan_status.json
├── start_api.sh
├── stop_api.sh
└── .env
```

## ✔ Funciones del escáner

- Escaneo completo con Nmap  
- Detección de puertos y versiones  
- Inferencia básica de vulnerabilidades  
- Inserción en MariaDB  
- Logs por ejecución  
- Gestión del estado de los escaneos  

---

# 🧱 Arquitectura completa

```
Docker (10.11.0.15)
   Apache + PHP + LDAP + SSO
           │
           ▼
MariaDB (10.11.0.16)
   dbchorizosql + wordpress_db
           ▲
           │
           ▼
Raspberry Pi (10.11.0.152)
   scan.py → Inserta resultados
```

---
