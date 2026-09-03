#!/bin/sh
set -eu

# ==============================================================================
# Backup diario de Postgres (pg_dump -Fc) + rotación de dumps antiguos.
# Ejecutado por crond dentro del contenedor "postgres-backup" (ver
# docker/backup-crontab y el servicio en docker-compose.yml). Alcance mínimo
# de #98: sin subida externa, sin cifrado, sin restore automatizado.
# ==============================================================================

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="/backups/savepoint_${TIMESTAMP}.dump"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"

echo "[backup] Iniciando pg_dump -> ${DUMP_FILE}"
pg_dump -h "${PGHOST:-postgres}" -U "${PGUSER:-savepoint}" -d "${PGDATABASE:-savepoint}" -Fc -f "${DUMP_FILE}"
echo "[backup] Completado ($(du -h "${DUMP_FILE}" | cut -f1))"

echo "[backup] Rotando dumps de más de ${RETENTION_DAYS} días"
find /backups -name 'savepoint_*.dump' -mtime "+${RETENTION_DAYS}" -print -delete
