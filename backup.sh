#!/usr/bin/env bash
# Sauvegarde Dolibarr : base MariaDB + dossier documents -> ~/erp-backups/dolibarr_AAAAMMJJ_HHMMSS/
# Aucun mot de passe n'apparaît en ligne de commande : le dump utilise la variable déjà présente DANS le conteneur.
set -euo pipefail
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKUP_ROOT="${BACKUP_ROOT:-$HOME/erp-backups}"
cd "$PROJECT_DIR"

TS="$(date +%Y%m%d_%H%M%S)"
DEST="$BACKUP_ROOT/dolibarr_$TS"
umask 077
mkdir -p "$DEST"

echo "[1/3] Export de la base -> $DEST/db.sql.gz"
docker compose exec -T mariadb sh -c \
  'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-dump -uroot --single-transaction --routines --triggers --events --default-character-set=utf8mb4 "$MARIADB_DATABASE"' \
  | gzip -9 > "$DEST/db.sql.gz"
# Contrôle : le dump doit se terminer par la ligne "Dump completed"
gzip -dc "$DEST/db.sql.gz" | tail -1 | grep -q "Dump completed" || { echo "ERREUR : dump incomplet" >&2; exit 1; }

echo "[2/3] Archive des documents -> $DEST/documents.tar.gz"
docker compose exec -T dolibarr tar -czf - -C /var/www documents > "$DEST/documents.tar.gz"
tar -tzf "$DEST/documents.tar.gz" >/dev/null || { echo "ERREUR : archive documents illisible" >&2; exit 1; }

echo "[3/3] Empreintes et métadonnées"
{
  echo "date=$TS"
  docker compose ps --format '{{.Service}}_image={{.Image}}'
} > "$DEST/INFO.txt"
( cd "$DEST" && sha256sum db.sql.gz documents.tar.gz > SHA256SUMS )

echo "OK : sauvegarde terminée dans $DEST ($(du -sh "$DEST" | cut -f1))"
echo "Rappel : copiez régulièrement ~/erp-backups sur un support externe (disque USB, NAS)."
