# Dolibarr ERP/CRM – installation locale

Gestion de stock, commerce et facturation pour une petite entreprise marocaine.
Dolibarr **24.0.2** et MariaDB **11.8 LTS** tournent dans Docker et sont accessibles **uniquement depuis ce PC**.

- Adresse : **http://127.0.0.1:8080**
- Comptes : `admin` (administrateur) et `caissier` (sans droits d'administration). Les mots de passe sont dans `.env`.
- Fichiers : `docker-compose.yml`, `.env` (secrets, **jamais versionné**, chmod 600), `backup.sh`, `restore.sh`,
  `scripts/security_check.sh`, `scripts/setup/*.php` (scripts de paramétrage déjà exécutés, conservés pour traçabilité).

## 1. Première connexion : changer le mot de passe admin (obligatoire)
1. Lire le mot de passe initial : `grep DOLI_ADMIN_PASSWORD ~/Documents/Project/.env` (dans votre terminal uniquement).
2. Se connecter sur http://127.0.0.1:8080 avec `admin`.
3. En haut à droite : *Mon profil* → *Modifier le mot de passe*. Choisir un mot de passe fort et le garder dans un gestionnaire de mots de passe.
4. Vérifier : `./scripts/security_check.sh` doit afficher `[OK] Le mot de passe admin initial ne fonctionne plus`.
5. (Recommandé) Remplacer aussi la valeur `DOLI_ADMIN_PASSWORD` dans `.env` par une valeur quelconque : elle ne sert qu'au tout premier démarrage.

Le mot de passe du caissier (`CAISSIER_PASSWORD` dans `.env`) est un mot de passe de test. Changez-le de la même façon avant un usage réel.

## 2. Installation (sur un nouveau PC)
Prérequis : Docker Engine + Docker Compose v2 (Fedora : `sudo dnf install docker-ce docker-compose-plugin`, ou Docker Desktop sous Windows/macOS).
```bash
cd ~/Documents/Project
# Créer .env (une seule fois). Voir la liste des variables dans INSTALL_LOG.md ; générer chaque secret avec :
#   LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 28
docker compose up -d
```
Au premier démarrage, l'image installe Dolibarr toute seule (`DOLI_INSTALL_AUTO=1`). Comptez environ 1 minute.

## 3. Démarrer / arrêter / état
```bash
cd ~/Documents/Project
docker compose up -d            # démarrer (redémarre aussi automatiquement au boot si Docker est actif)
docker compose stop             # arrêter (les données sont conservées)
docker compose ps               # état des services
docker compose logs -f dolibarr # journaux
```
⚠️ N'utilisez **pas** `docker compose down -v` : l'option `-v` **supprime les volumes**, c'est-à-dire toutes les données.

## 4. Mise à jour de Dolibarr
1. **Sauvegarder** : `./backup.sh`.
2. Lire les notes de version sur https://www.dolibarr.org/ et les tags sur https://hub.docker.com/r/dolibarr/dolibarr/tags.
3. Montez **une version majeure à la fois** (24 → 25 → 26…). Modifiez `image: dolibarr/dolibarr:<version>` dans `docker-compose.yml`.
4. Autoriser la migration : `docker compose exec dolibarr rm -f /var/www/documents/install.lock`
5. `docker compose pull && docker compose up -d && docker compose logs -f dolibarr`. Avec `DOLI_INSTALL_AUTO=1`, la base est migrée au démarrage.
6. Vérifier que `install.lock` a bien été recréé (`docker compose exec dolibarr ls -l /var/www/documents/install.lock`), puis faire un test rapide (connexion, une facture brouillon).

MariaDB : restez sur la branche LTS `11.8`. Faites `docker compose pull mariadb && docker compose up -d` après une sauvegarde.

## 5. Sauvegarde
```bash
./backup.sh
```
Le script crée `~/erp-backups/dolibarr_AAAAMMJJ_HHMMSS/`, qui contient :
- `db.sql.gz` : dump complet de la base, avec contrôle « Dump completed » ;
- `documents.tar.gz` : PDF, pièces jointes, logo ;
- `SHA256SUMS` et `INFO.txt` : empreintes et versions des images.

Aucune sauvegarde n'est supprimée automatiquement. Copiez régulièrement `~/erp-backups/` sur un **support externe**.

**Planification (optionnelle, non activée)** : par exemple tous les jours à 19 h avec `crontab -e` :
```
0 19 * * * /home/soufiane/Documents/Project/backup.sh >> /home/soufiane/erp-backups/backup.log 2>&1
```

## 6. Restauration
```bash
./restore.sh ~/erp-backups/dolibarr_AAAAMMJJ_HHMMSS               # MODE TEST (sans risque)
./restore.sh ~/erp-backups/dolibarr_AAAAMMJJ_HHMMSS --production  # remplace les données actuelles
```
- **Mode test** (par défaut) : la base est restaurée dans `dolibarr_restore_test` et les documents dans `~/erp-backups/restore_test_docs/`. Le script compare ensuite avec la production. La production n'est pas touchée.
- **Mode production** : il faut taper `RESTAURER` pour confirmer. Le script fait d'abord une **sauvegarde de sécurité automatique**, arrête Dolibarr, réimporte la base et les documents, puis redémarre.

## 7. Sécurité
- Dolibarr n'écoute que sur `127.0.0.1:8080`. MariaDB n'a **aucun port publié**.
- Contrôle complet : `./scripts/security_check.sh` (exposition réseau, mot de passe admin, comptes, permissions du caissier).
- SELinux reste en mode *Enforcing*. Les données sont dans des volumes Docker nommés : `erp-dolibarr_mariadb_data`, `erp-dolibarr_dolibarr_documents`, `erp-dolibarr_dolibarr_custom`.

## 8. Où se trouve quoi dans Dolibarr
| Besoin | Menu |
|---|---|
| Fiche entreprise, ICE/IF/RC/Patente/CNSS, logo | Accueil → Configuration → Société/Organisation |
| Modules | Accueil → Configuration → Modules/Applications |
| Taux de TVA, modes et conditions de règlement | Accueil → Configuration → Dictionnaires |
| Numérotation et modèles PDF (devis, factures) | Configuration → Modules → (icône réglages du module) |
| Alertes de stock | Produits → Stocks → Réapprovisionnement, ou liste produits filtre « Alerte stock » |
| Utilisateurs et droits | Accueil → Utilisateurs & Groupes |

## Modèles PDF « Maroc »
Module local dans `custom/modelesmaroc/` : devis, bon de commande, bon de livraison et facture avec montant TTC en toutes lettres (« Arrêtée la présente facture à la somme de… ») et cadres de signature sur le bon de livraison. Ce sont les modèles par défaut. Les modèles standards restent disponibles dans la liste « Modèle » de chaque document.

- Après une modification des fichiers : `scripts/deploy_modeles.sh`, puis régénérer le PDF depuis la fiche du document.
- Réinstallation complète : `scripts/deploy_modeles.sh && scripts/setup/run.sh 08_modeles_pdf.php`.
- Les modèles se trouvent dans le volume `erp-dolibarr_dolibarr_custom` : ils survivent aux mises à jour de l'image. Après une mise à jour majeure de Dolibarr, régénérez un PDF de chaque type pour vérifier.
- Pour revenir aux modèles standards : *Configuration → Modules → Factures (ou Devis, Commandes, Expéditions) → Modèles de documents*, puis choisir sponge / cyan / eratosthene / espadon par défaut.
