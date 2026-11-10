#!/usr/bin/env bash
# Usage : scripts/setup/run.sh <script.php>  — copie le script et le bootstrap dans le conteneur et l'exécute en www-data.
set -euo pipefail
cd "$(dirname "$0")/../.."
docker compose cp scripts/setup/_bootstrap.php dolibarr:/tmp/_bootstrap.php >/dev/null
docker compose cp "scripts/setup/$1" "dolibarr:/tmp/$1" >/dev/null
docker compose exec -T -u www-data -w /tmp dolibarr php "/tmp/$1"
