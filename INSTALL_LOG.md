# Journal d'installation – Dolibarr local

> Les mots de passe ne figurent jamais dans ce journal (voir `.env`, permissions 600).

## 2026-11-07 – Étape 0 : diagnostic
- OS : Fedora Linux 44 (KDE), noyau 7.2.10, x86_64, 8 cœurs, 15 Go RAM, 455 Go libres sur /home.
- Docker 29.9.0 + Docker Compose v5.6.0 (service actif, utilisateur dans le groupe `docker`), Git 2.56, Firefox/Chrome, VS Code/KWrite/nano/vim.
- Ports 8080 et 3306 libres. SELinux en mode Enforcing → choix de **volumes Docker nommés** (pas de bind-mount, pas de désactivation de SELinux).
- Décision : aucune installation logicielle nécessaire.

## 2026-11-07 – Étape 1 : environnement
- Documentation de l'image `dolibarr/dolibarr` consultée (Docker Hub + README GitHub `Dolibarr/dolibarr-docker`) :
  port interne 80, variables `DOLI_*`, auto-install au 1er démarrage (`DOLI_INSTALL_AUTO=1`), volumes `/var/www/documents` et `/var/www/html/custom`.
- Versions retenues (épinglées) : `dolibarr/dolibarr:24.0.2` (dernière stable, = `latest` au 2026-11-07) et `mariadb:11.8` (LTS).
- Création de `~/Documents/Project/` : `docker-compose.yml`, `.env` (secrets aléatoires 28 caractères, chmod 600, jamais affichés), `.gitignore`.
- Sécurité réseau : Dolibarr publié sur `127.0.0.1:8080` uniquement ; **MariaDB n'a aucun port publié** (réseau Docker interne seulement).
- Fuseau horaire : `Africa/Casablanca` (PHP et MariaDB). Encodage base : utf8mb4.
- `docker compose up -d` → mariadb healthy, dolibarr démarré ; `http://127.0.0.1:8080` → HTTP 200 (page « Login @ 24.0.2 »).
- Test depuis l'IP LAN (192.168.1.158:8080) → connexion refusée ✅. `ss -ltn` : seul `127.0.0.1:8080` écoute.

## 2026-11-07 – Étape 2 : installation Dolibarr
- Installation automatique via variables d'environnement (pas d'assistant web nécessaire).
- Vérifications : 410 tables créées, `MAIN_VERSION_LAST_INSTALL=24.0.2`, `install.lock` présent (lecture seule), un seul utilisateur `admin` (aucune donnée de démo : `DOLI_INIT_DEMO=0`).
- Base : MariaDB du conteneur, identifiants issus de `.env`. Compte admin : identifiants issus de `.env`.
- ⚠️ Action utilisateur : changer le mot de passe admin à la première connexion (voir README).

## Constats Maroc (lecture seule, rien modifié)
- Identifiants professionnels Dolibarr pour le pays MA : ProfId1 = R.C., ProfId2 = Patente, ProfId3 = I.F., ProfId4 = C.N.S.S., ProfId5 = ICE.
- Dictionnaire TVA Maroc présent d'origine : 0 %, 7 %, 10 %, 14 %, 20 % (actifs). **Non vérifiés officiellement** → voir `TODO_VERIFICATION.md`.
- Devise MAD présente et active.

## 2026-11-07 – Méthode de paramétrage
- Paramétrage fait par des scripts PHP (`scripts/setup/*.php`) lancés **dans** le conteneur, en `www-data`, avec les classes et fonctions de Dolibarr (`activateModule`, `dolibarr_set_const`, `Product::create`…). Aucun fichier source Dolibarr n'est modifié. Chaque script tourne dans une transaction (annulée en cas d'erreur).

## 2026-11-07 – Étape 3 : entreprise
- Pas de réponse sur les données de l'entreprise → **valeurs fictives** : « MA SOCIETE TEST SARL », Casablanca, ICE 000000000000000, IF/RC/Patente/CNSS « …-TEST-… ». **À remplacer.** Pas de logo fourni.
- Pays Maroc (`12:MA`), devise **MAD**, langue fr_FR, `FACTURE_TVAOPTION=1` (assujetti, à confirmer). Format de date : celui de la langue fr (JJ/MM/AAAA).
- Numérotation : modèles par défaut (devis *marbre* PR, commandes *marbre* SO, factures *terre* IN/IC, expéditions *safor* SH, commandes fournisseurs *muguet* PO). Préfixes internationaux car pays ≠ FR (comportement standard v16+).
- Taux de TVA : 0/7/10/14/20 % présents d'origine, **aucun ajouté ni modifié**.

## 2026-11-07 – Étape 4 : modules
- Activés : Tiers, Produits, Services, Stock (inventaire inclus), Propositions commerciales, Commandes, Expéditions, Factures (paiements inclus), **Fournisseurs** (ajout validé par l'utilisateur pour la réception, test 6.6).
- Refusés / non validés (non activés) : Codes-barres, Banques et caisses, Catégories. Comptabilité et paie : **non activées**. Aucun module tiers ou payant.

## 2026-11-07 – Étape 5 : paramétrage métier
- Stock : décrément à la validation de l'expédition (`STOCK_CALCULATE_ON_SHIPMENT=1`), incrément à la réception d'une commande fournisseur (`STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER=1`).
- Entrepôts : « Magasin principal » (#1), « Réserve » (#2).
- 5 produits TEST-P01…P05 (prix HT en MAD, TVA 20 %, seuil d'alerte, stock initial) et 2 services TEST-S01/S02. Prix d'achat chez « TEST Fournisseur Gamma » pour P01, P03, P05.
- **Catégories et codes-barres non créés** (modules non validés).
- Tiers : TEST Client Alpha SARL, TEST Client Beta SA, TEST Fournisseur Gamma, TEST Fournisseur Delta (ICE fictifs, conditions 30 j, virement).
- Modes de règlement actifs : Espèces (LIQ), Virement (VIR), Chèque (CHQ), Carte (CB) ; les autres sont désactivés (réversible dans Dictionnaires).
- Conditions de paiement actives : à réception, 30 j, 30 j fin de mois, 60 j, 50/50 ; les autres sont désactivées (réversible).
- PDF : (remplacés depuis par les modèles « Maroc », voir plus bas) modèles par défaut *cyan* (devis) et *sponge* (facture) qui reprennent la fiche entreprise ; ajout de l'ICE client dans l'adresse, de l'ICE et de l'IF émetteur (`MAIN_PROFID5_IN_ADDRESS`, `MAIN_PROFID5_IN_SOURCE_ADDRESS`, `MAIN_PROFID3_IN_SOURCE_ADDRESS`). Contenu du PDF vérifié.

## 2026-11-07 – Étape 6 : tests
- Voir `TEST_REPORT.md`. Premier passage : 7/10. Les 3 échecs venaient de mes assertions (préfixes CO/FA/AV attendus au lieu de SO/IN/IC). Revérification en lecture seule : 10/10 conformes.

## 2026-11-07 – Étape 7 : sauvegarde / restauration
- `backup.sh` : dump MariaDB (single-transaction, contrôle de fin de dump) + archive des documents + SHA-256 → `~/erp-backups/dolibarr_<horodatage>/` (chmod 700/600). Mot de passe pris dans l'environnement du conteneur, jamais en argument sur l'hôte.
- `restore.sh` : mode test par défaut (base `dolibarr_restore_test` + `~/erp-backups/restore_test_docs/`) ; mode `--production` avec confirmation « RESTAURER » et sauvegarde de sécurité automatique.
- Test : restauration de test réussie (410 tables, 3 factures, 7 produits, 4 tiers, 12 mouvements, somme TTC identique ; 13/13 fichiers). Production non touchée.
- Correctif : INFO.txt n'indiquait pas les versions des images → corrigé (`docker compose ps --format`).
- Tâche cron : **non créée**, en attente de confirmation.
- Pas de `backup.ps1` : le PC est sous Linux.

## 2026-11-07 – Étape 8 : sécurité
- Utilisateur `caissier` (admin=0) créé. Droits : lire tiers, **voir tous les clients**, produits, services, stock, commandes, expéditions ; factures : lire, créer, valider, envoyer, encaisser.
- Correctifs pendant le test : (1) mon script avait accordé tous les sous-droits `invoice_advance` → retrait de « repasser en brouillon » et « rouvrir » ; (2) sans `societe->client->voir`, le caissier ne voyait aucune facture → droit ajouté ; (3) le test « accès refusé » ne reconnaissait pas le message encodé en entités HTML → expression corrigée.
- `scripts/security_check.sh` : 11 OK. Seule exposition `127.0.0.1:8080`, non joignable depuis le LAN ; aucun compte de démo ; caissier refusé sur configuration, modules, dictionnaires, création d'utilisateur et création de produit ; tentative POST de modification de la configuration sans effet ; le caissier voit bien les factures.
- **À FAIRE (utilisateur)** : changer le mot de passe admin (encore le mot de passe initial de `.env`).
- Variables de `.env` : MARIADB_ROOT_PASSWORD, DB_NAME, DB_USER, DB_PASSWORD, DOLI_ADMIN_LOGIN, DOLI_ADMIN_PASSWORD, DOLI_INSTANCE_UNIQUE_ID, DOLI_CRON_KEY, HTTP_PORT, CAISSIER_LOGIN, CAISSIER_PASSWORD.

## 2026-11-07 – Déplacement du projet
- Projet déplacé de `~/erp-dolibarr` vers `~/Documents/Project` (sauvegarde préalable `~/erp-backups/dolibarr_20261107_124909`). Les données restent dans les volumes nommés `erp-dolibarr_*` (`name: erp-dolibarr` dans le compose) : aucune perte, 2 utilisateurs et 4 tiers vérifiés après redémarrage. `security_check.sh` : 11 OK, mot de passe admin toujours à changer.

## 2026-11-07 – Modèles PDF « Maroc »
- Module local `custom/modelesmaroc` (n° 500100), déployé par `scripts/deploy_modeles.sh` dans le volume `custom`, activé par `scripts/setup/08_modeles_pdf.php`. **Aucun fichier source Dolibarr modifié** : les modèles héritent des modèles standards.
- `maroc_devis` (hérite de cyan) : titre « Devis », « Arrêté le présent devis à la somme de : … TTC », zone « Bon pour accord ».
- `maroc_commande` (eratosthene) : titre « Bon de commande », montant en lettres.
- `maroc_bl` (espadon, sans prix) : titre « Bon de livraison », cadres « Livré par » / « Reçu conforme par le client ».
- `maroc_facture` (sponge) : « Arrêtée la présente facture / Arrêté le présent avoir à la somme de : … TTC ».
- Les 4 modèles sont par défaut (`PROPALE_ADDON_PDF`, `COMMANDE_ADDON_PDF`, `EXPEDITION_ADDON_PDF`, `FACTURE_ADDON_PDF`). Les modèles standards restent actifs et sélectionnables.
- Tests : PDF de PR2611-0001, SO2611-0001, SH2611-0001, IN2611-0001, IC2611-0001 régénérés et contrôlés visuellement ; test multipage (copies en mémoire, 63 et 42 lignes) : montant en lettres et signatures sur la dernière page seulement, sans chevauchement. Fichiers de test supprimés.
