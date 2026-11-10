# Rapport de test de bout en bout – étape 6

- Date : 2026-11-07 ; Dolibarr 24.0.2, MariaDB 11.8.
- Méthode : script `scripts/setup/03_scenario_test.php`, exécuté dans le conteneur avec les classes métier de Dolibarr (les mêmes que celles de l'interface web). Les montants et le stock sont relus en base après chaque étape. L'étape 7 (inventaire) reproduit à l'identique l'action « Enregistrer » de `product/inventory/inventory.php`.
- Données : uniquement des tiers et articles fictifs « TEST ». Le PDF de la facture a été ouvert et relu (`pdftotext`).

**Bilan : 10 tests sur 10 conformes sur le fond.** Au premier passage, 3 tests étaient en ÉCHEC à cause d'une **erreur dans mes assertions**, pas de Dolibarr : voir la note ci-dessous. Ils ont été revérifiés en lecture seule par `04_reverif.php`.

| # | Étape | Résultat attendu | Résultat obtenu | Statut |
|---|---|---|---|---|
| 1 | Devis 3 lignes dont 1 remisée | 3 lignes, 1 remise 10 % ; HT 1 133,25 ; TVA 226,65 ; TTC 1 359,90 | PR2611-0001 : 3 lignes (10 × TEST-P01 à 45 ; 5 × TEST-P03 à 18,50 −10 % ; 2 h TEST-S01 à 300) ; HT 1 133,25 ; TVA 226,65 ; TTC 1 359,90 | OK |
| 2 | Validation puis transformation en commande | Devis signé ; commande validée, 3 lignes, même total, liée au devis | PR2611-0001 « Signée » → **SO2611-0001** « Validée », 3 lignes, HT 1 133,25, lien devis→commande présent | OK (*) |
| 3 | Expédition, le stock diminue | TEST-P01 Magasin 100 → 90 ; TEST-P03 12 → 7 ; le service n'est pas expédié | SH2611-0001 validée ; TEST-P01 100 → 90 ; TEST-P03 12 → 7 | OK |
| 4 | Facture depuis la commande, validation, PDF | Brouillon (PROV) → numéro définitif ; TTC 1 359,90 ; PDF avec les données de l'entreprise | (PROV1) → **IN2611-0001**, TTC 1 359,90, liée à la commande ; PDF généré (modèle *sponge*) : raison sociale, adresse, tél., IF et ICE émetteur, ICE client, pied de page RC/Patente/IF/CNSS/ICE, « Montants exprimés en Dirham » | OK (*) |
| 5a | Paiement partiel | 500 MAD en espèces → « Règlement commencé », reste 859,90 | « Règlement commencé », reste 859,90 | OK |
| 5b | Paiement du solde | 859,90 par chèque → « Payée », reste 0 | « Payée », reste 0, `paye=1` | OK |
| 6 | Réception fournisseur, le stock augmente | Commande fournisseur 5 × TEST-P05 chez Gamma, réceptionnée : Magasin 10 → 15 | PO2611-0001 « Tous les produits reçus » ; TEST-P05 10 → 15 | OK |
| 7 | Inventaire avec écart | TEST-P02 : théorique 30, compté 28 → 1 mouvement de −2, stock 28 | INV-TEST-261107122542 « Clôturé » ; 1 mouvement de −2 ; stock 30 → 28 | OK |
| 8 | Avoir et numérotation sans trou | Avoir lié à la facture ; séries continues | **IC2611-0001** (retour de 2 TEST-P01, TTC −108,00), source IN2611-0001 ; une 2e facture validée ensuite = IN2611-0002. Séries IN = 0001, 0002 et IC = 0001, sans trou ; aucun brouillon en attente | OK (*) |
| 9 | Produit sous le seuil dans les alertes | TEST-P03 (stock 7 < seuil 10) en alerte | Liste des alertes : TEST-P03 (7 < 10), et lui seul | OK |

(*) **Note sur les 3 échecs du premier passage.** Mon script attendait les préfixes français `CO` / `FA` / `AV`. Or Dolibarr ≥ 16 utilise volontairement les préfixes internationaux pour tout pays autre que la France : `SO` (commande), `IN` (facture), `IC` (avoir). Ce comportement est codé dans `mod_facture_terre` et `mod_commande_marbre`. Il est **normal**, et j'ai gardé ces préfixes. Les autres conditions de ces tests (statuts, montants, liens, PDF, continuité de la numérotation) étaient remplies. Au moment du test 2, la commande était bien « Validée ». Elle est passée ensuite à « Envoi en cours » à cause de l'expédition du test 3, ce qui est attendu.
Si vous préférez `FA`, la constante `INVOICE_NUMBERING_TERRE_FORCE_PREFIX` existe. Décidez-le **avant la première vraie facture**, voir `TODO_VERIFICATION.md`.

## Constats annexes
- Le PDF affiche l'avertissement « Un mode de paiement a été défini de type VIR mais la configuration du module Facture n'a pas été complétée… ». Il faut renseigner le RIB à afficher, ce qui nécessite le module *Banques et caisses* (non activé, à votre demande). Autre solution : mettre « Espèces » ou « Chèque » comme mode par défaut des tiers.
- Les paiements ne sont rattachés à aucun compte de trésorerie, car le module Banques n'est pas actif. Pas de rapprochement de caisse possible pour l'instant.
- Pas de catégories ni de codes-barres sur les produits : les modules *Catégories* et *Codes-barres* ne sont pas activés (non validés). Activez-les si vous le souhaitez, et les 3 catégories seront ajoutées en quelques minutes.

## Données de test créées (à supprimer avant la mise en production, sur votre demande)
PR2611-0001, SO2611-0001, SH2611-0001, IN2611-0001, IN2611-0002, IC2611-0001, PO2611-0001, INV-TEST-…, 7 articles `TEST-*`, 4 tiers `TEST …`.
⚠️ Les factures **validées** ne doivent pas être supprimées en production (numérotation continue). Pour repartir de zéro proprement, le plus simple est de réinstaller une base vierge avant la mise en service réelle. À décider avec vous.
