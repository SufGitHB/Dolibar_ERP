#!/usr/bin/env bash
# Contrôles de sécurité locaux (lecture seule). Les mots de passe sont lus dans .env et envoyés à curl par stdin (jamais en argument).
set -uo pipefail
cd "$(dirname "$0")/.."; set -a; . ./.env; set +a
BASE="http://127.0.0.1:${HTTP_PORT:-8080}"; TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
pass=0; fail=0
res() { if [ "$1" = OK ]; then pass=$((pass+1)); else fail=$((fail+1)); fi; printf '[%-5s] %s\n' "$1" "$2"; }
login() { # $1=login $2=password $3=cookiejar -> 0 si connecté
  local tok; tok=$(curl -s -c "$3" -b "$3" "$BASE/index.php" | grep -oP 'name="token" value="\K[^"]+' | head -1)
  printf 'actionlogin=login&loginfunction=loginfunction&token=%s&username=%s&password=%s' "$tok" "$1" "$(python3 -c 'import sys,urllib.parse;print(urllib.parse.quote(sys.stdin.read().strip()))' <<<"$2")" \
    | curl -s -c "$3" -b "$3" -o "$TMP/login.html" -L --data @- "$BASE/index.php"
  ! grep -q 'name="actionlogin"' "$TMP/login.html"; }
get() { curl -s -b "$1" -o "$TMP/page.html" -w '%{http_code}' "$BASE$2"; }
# Dolibarr encode le message en entités HTML (Acc&egrave;s refus&eacute;) : on teste les deux formes.
denied() { grep -qiE 'Acc(è|&egrave;)s refus(é|&eacute;)|Access denied' "$TMP/page.html"; }

echo "== 1. Exposition réseau =="
L=$(ss -ltnH | awk '{print $4}' | grep -E ':(8080|3306)$' | tr '\n' ' ')
[ "$L" = "127.0.0.1:${HTTP_PORT:-8080} " ] && res OK "Seul 127.0.0.1:${HTTP_PORT:-8080} écoute (MariaDB non publié) : $L" || res ECHEC "Ports en écoute : $L"
IP=$(hostname -I | awk '{print $1}')
curl -s -m 3 -o /dev/null "http://$IP:${HTTP_PORT:-8080}/" && res ECHEC "Dolibarr joignable via l'IP LAN $IP" || res OK "Dolibarr non joignable via l'IP LAN $IP"

echo "== 2. Mot de passe admin =="
if login "$DOLI_ADMIN_LOGIN" "$DOLI_ADMIN_PASSWORD" "$TMP/admin.jar"; then
  res À_FAIRE "Le mot de passe admin initial (.env) fonctionne encore → changez-le (voir README)"
else res OK "Le mot de passe admin initial ne fonctionne plus (changé)"; fi

echo "== 3. Comptes =="
CNT=$(docker compose exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N "$MARIADB_DATABASE" -e "SELECT CONCAT(login,\":admin=\",admin,\":actif=\",statut) FROM llx_user ORDER BY rowid"' | tr '\n' ' ')
echo "$CNT" | grep -qiE '(^| )(demo|test|user)[^ ]*:.*actif=1' && res ECHEC "Compte de démo actif : $CNT" || res OK "Aucun compte de démonstration : $CNT"

echo "== 4. Permissions du caissier =="
login "$CAISSIER_LOGIN" "$CAISSIER_PASSWORD" "$TMP/c.jar" && res OK "Connexion caissier" || { res ECHEC "Connexion caissier impossible"; echo "BILAN $pass OK / $fail ECHEC"; exit 1; }
for u in /admin/company.php /admin/modules.php /admin/dict.php /user/card.php?action=create /product/card.php?action=create; do
  c=$(get "$TMP/c.jar" "$u"); denied && res OK "Refusé au caissier : $u (HTTP $c)" || res ECHEC "Accessible au caissier : $u (HTTP $c)"
done
NOM_AVANT=$(docker compose exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N "$MARIADB_DATABASE" -e "SELECT value FROM llx_const WHERE name=\"MAIN_INFO_SOCIETE_NOM\""')
get "$TMP/c.jar" /index.php >/dev/null; TOK=$(grep -oP 'name="token" value="\K[^"]+' "$TMP/page.html" | head -1); [ -z "$TOK" ] && TOK=$(grep -oP 'token=\K[0-9a-f]{20,}' "$TMP/page.html" | head -1)
curl -s -b "$TMP/c.jar" -o "$TMP/page.html" --data "token=$TOK&action=update&nom=PIRATE_TEST" "$BASE/admin/company.php"
NOM_APRES=$(docker compose exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N "$MARIADB_DATABASE" -e "SELECT value FROM llx_const WHERE name=\"MAIN_INFO_SOCIETE_NOM\""')
[ "$NOM_AVANT" = "$NOM_APRES" ] && res OK "Tentative POST de modification de la config refusée (nom inchangé : $NOM_APRES)" || res ECHEC "Configuration modifiée par le caissier !"
c=$(get "$TMP/c.jar" /compta/facture/list.php); grep -q 'IN2611-0001' "$TMP/page.html" && res OK "Le caissier voit la liste des factures (HTTP $c)" || res ECHEC "Le caissier ne voit pas les factures (HTTP $c)"
echo "BILAN $pass OK / $fail ECHEC/À FAIRE"
