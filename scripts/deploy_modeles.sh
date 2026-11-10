#!/usr/bin/env bash
# Copie le module « Modèles Maroc » (custom/modelesmaroc) dans le volume custom du conteneur Dolibarr.
# À relancer après chaque modification des modèles. Ne touche pas aux sources de Dolibarr.
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose cp custom/modelesmaroc dolibarr:/var/www/html/custom/ >/dev/null
docker compose exec -T dolibarr sh -c 'chown -R root:www-data /var/www/html/custom/modelesmaroc && find /var/www/html/custom/modelesmaroc -type d -exec chmod 755 {} + && find /var/www/html/custom/modelesmaroc -type f -exec chmod 644 {} +'
docker compose exec -T dolibarr sh -c 'for f in $(find /var/www/html/custom/modelesmaroc -name "*.php"); do php -l "$f" >/dev/null || exit 1; done'
echo "OK : module modelesmaroc déployé dans /var/www/html/custom/"
