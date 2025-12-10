

#!/bin/bash

# =============================================================================

# Script de copia de seguridad organizado por carpetas diarias

# =============================================================================
 
# -----------------------

# Datos de conexión MySQL

# -----------------------

USER="cadmin"

PASS="My_Chorizo"

DB="dbchorizosql"

DB_HOST="127.0.0.1"
 
# -----------------------

# Directorio raíz de backups

# -----------------------

MAIN_BACKUP_DIR="/home/chorizo/BACKUP_DIR"
 
# -----------------------

# Rutas absolutas (CRON friendly)

# -----------------------

MYSQLDUMP="/usr/bin/mysqldump"

GZIP="/usr/bin/gzip"

FIND="/usr/bin/find"

DATE_CMD="/usr/bin/date"

TEE="/usr/bin/tee"
 
# -----------------------

# Preparar nombres y carpetas SÓLO CON FECHA

# -----------------------

DATE_FOLDER=$($DATE_CMD +"%Y-%m-%d")   # sin horas

DATE=$DATE_FOLDER                      # variable general también sin horas
 
# Carpeta del backup de hoy

TODAY_DIR="$MAIN_BACKUP_DIR/backup_$DATE_FOLDER"
 
# Crear directorio si no existe

mkdir -p "$TODAY_DIR"
 
# Archivos dentro de la carpeta del día

SQL_FILE="$TODAY_DIR/${DB}_backup_$DATE.sql"

GZ_FILE="${SQL_FILE}.gz"

LOG_FILE="$TODAY_DIR/backup_$DATE.log"
 
# -----------------------

# Inicia log

# -----------------------

echo "===== BACKUP INICIADO: $DATE =====" | $TEE -a "$LOG_FILE"
 
# -----------------------

# Crear backup SQL

# -----------------------

$MYSQLDUMP -h "$DB_HOST" -u "$USER" -p"$PASS" "$DB" > "$SQL_FILE" 2>> "$LOG_FILE"
 
if [ $? -eq 0 ]; then

    echo "[OK] Backup SQL creado: $SQL_FILE" | $TEE -a "$LOG_FILE"

else

    echo "[ERROR] Fallo al crear el backup SQL." | $TEE -a "$LOG_FILE"

    exit 1

fi
 
# -----------------------

# Compresión del backup

# -----------------------

$GZIP "$SQL_FILE"
 
if [ $? -eq 0 ]; then

    echo "[OK] Backup comprimido: $GZ_FILE" | $TEE -a "$LOG_FILE"

else

    echo "[ERROR] Fallo al comprimir $SQL_FILE" | $TEE -a "$LOG_FILE"

fi
 
# -----------------------

# Eliminar carpetas con más de 7 días

# -----------------------

$FIND "$MAIN_BACKUP_DIR" -maxdepth 1 -type d -name "backup_*" -mtime +7 -exec rm -rf {} \;
 
echo "[OK] Carpetas antiguas (>7 días) eliminadas" | $TEE -a "$LOG_FILE"
 
# -----------------------

# Cierre log

# -----------------------

echo "===== BACKUP COMPLETADO: $DATE =====" | $TEE -a "$LOG_FILE"

echo "" | $TEE -a "$LOG_FILE"

 