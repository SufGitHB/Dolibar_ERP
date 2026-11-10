#!/usr/bin/env bash
# Restauration Dolibarr.
#
#   ./restore.sh <dossier_sauvegarde>                 -> MODE TEST (par défaut, sans risque) :
#        restaure la base dans "dolibarr_restore_test" et les documents dans ~/erp-backups/restore_test_docs/,
#        puis compare avec la production. La base et les documents de production ne sont PAS touchés.
#
#   ./restore.sh <dossier_sauvegarde> --production    -> MODE PRODUCTION (écrase les données actuelles) :
#        demande de taper RESTAURER, fait d'abord une sauvegarde de sécurité, arrête Dolibarr,
#        réimporte la base et les documents, puis redémarre.
set -euo pipefail
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT_DIR"

SRC="${1:-}"; MODE="${2:-test}"
[ -n "$SRC" ] && [ -f "$SRC/db.sql.gz" ] && [ -f "$SRC/documents.tar.gz" ] || {
  echo "Usage : $0 <dossier_sauvegarde> [--production]" >&2; echo "Sauvegardes disponibles :" >&2; ls -1d "$HOME"/erp-backups/dolibarr_* 2>/dev/null >&2 || true; exit 1; }
SRC="$(cd "$SRC" && pwd)"

echo "Vérification des empreintes SHA-256…"
( cd "$SRC" && sha256sum -c --quiet SHA256SUMS ) || { echo "ERREUR : sauvegarde corrompue" >&2; exit 1; }

# Exécute du SQL en root dans le conteneur, sans exposer le mot de passe sur l'hôte.
sql_root() { docker compose exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -uroot "$@"' -- "$@"; }

if [ "$MODE" != "--production" ]; then
  TESTDB="dolibarr_restore_test"
  TESTDOCS="$HOME/erp-backups/restore_test_docs"
  echo "=== MODE TEST : base '$TESTDB', documents dans $TESTDOCS (production intacte) ==="
  sql_root -e "CREATE DATABASE IF NOT EXISTS \`$TESTDB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  gzip -dc "$SRC/db.sql.gz" | sql_root "$TESTDB"
  mkdir -p "$TESTDOCS"; tar -xzf "$SRC/documents.tar.gz" -C "$TESTDOCS"

  echo "--- Comparaison production / restauration ---"
  PROD="$(docker compose exec -T mariadb sh -c 'echo "$MARIADB_DATABASE"' | tr -d '\r')"
  Q="SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='%s') tables_,
            (SELECT COUNT(*) FROM \`%s\`.llx_facture) factures, (SELECT COUNT(*) FROM \`%s\`.llx_product) produits,
            (SELECT COUNT(*) FROM \`%s\`.llx_societe) tiers, (SELECT COUNT(*) FROM \`%s\`.llx_stock_mouvement) mouvements,
            (SELECT COALESCE(SUM(total_ttc),0) FROM \`%s\`.llx_facture) somme_ttc"
  P=$(sql_root -N -e "$(printf "$Q" "$PROD" "$PROD" "$PROD" "$PROD" "$PROD" "$PROD")")
  T=$(sql_root -N -e "$(printf "$Q" "$TESTDB" "$TESTDB" "$TESTDB" "$TESTDB" "$TESTDB" "$TESTDB")")
  echo "             tables factures produits tiers mouvements somme_ttc"
  echo "production : $P"; echo "restaurée  : $T"
  NDOC_SRC=$(tar -tzf "$SRC/documents.tar.gz" | grep -vc '/$'); NDOC_DST=$(find "$TESTDOCS/documents" -type f | wc -l)
  echo "documents : $NDOC_SRC fichiers dans l'archive, $NDOC_DST restaurés"
  if [ "$NDOC_SRC" = "$NDOC_DST" ] && [ "$(echo "$P" | awk '{print $1}')" = "$(echo "$T" | awk '{print $1}')" ]; then
    echo "OK : restauration de test réussie (les écarts de lignes viennent uniquement de saisies faites APRÈS la sauvegarde)."
  else echo "ATTENTION : écart détecté" >&2; exit 1; fi
  echo "Nettoyage manuel possible quand vous le souhaitez : base '$TESTDB' et dossier $TESTDOCS."
  exit 0
fi

echo "=== MODE PRODUCTION : la base et les documents ACTUELS seront remplacés par $SRC ==="
read -r -p "Tapez RESTAURER pour confirmer : " ANSWER
[ "$ANSWER" = "RESTAURER" ] || { echo "Annulé."; exit 1; }
echo "Sauvegarde de sécurité de l'état actuel…"; "$PROJECT_DIR/backup.sh"
docker compose stop dolibarr
docker compose exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -uroot "$MARIADB_DATABASE"' < <(gzip -dc "$SRC/db.sql.gz")
docker compose run --rm --no-deps -T --entrypoint sh dolibarr -c 'tar -xzf - -C /var/www && chown -R www-data:www-data /var/www/documents' < "$SRC/documents.tar.gz"
docker compose start dolibarr
echo "OK : restauration production terminée. Vérifiez http://127.0.0.1:${HTTP_PORT:-8080}"
