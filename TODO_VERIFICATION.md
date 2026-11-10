# Points fiscaux et réglementaires à faire valider

> Rien de ce qui suit n'est un conseil fiscal. Chaque point doit être confirmé par un **expert-comptable** ou par la **DGI** (https://www.tax.gov.ma), selon les textes en vigueur (Code Général des Impôts, Loi de Finances de l'année).
> Légende : ✅ vérifié dans Dolibarr (aspect technique seulement) ; ❓ règle non vérifiée officiellement.

## 1. Taux de TVA
Dictionnaire Dolibarr pour le Maroc, présent d'origine et **non modifié** : 0 %, 7 %, 10 %, 14 %, 20 %.

| Taux | Présent dans Dolibarr | Vérifié officiellement | À faire |
|---|---|---|---|
| 20 % (normal) | ✅ | ❓ | Confirmer |
| 14 % | ✅ | ❓ | Confirmer s'il est encore applicable à vos activités |
| 10 % | ✅ | ❓ | Confirmer |
| 7 % | ✅ | ❓ | Confirmer s'il est encore applicable à vos activités |
| 0 % / exonéré | ✅ | ❓ | Définir les cas (exonération avec ou sans droit à déduction) et la mention à imprimer |

- ❓ À ma connaissance, la Loi de Finances 2024 a lancé une réforme progressive des taux de TVA (rapprochement vers 10 % et 20 % sur plusieurs années). Je n'ai **pas vérifié** le calendrier exact ni les produits concernés. **À confirmer** avant de désactiver ou de garder 7 % et 14 %.
- ❓ Régime de TVA de l'entreprise (encaissements ou débits) et périodicité de déclaration. Dans Dolibarr, j'ai mis `FACTURE_TVAOPTION=1` (assujetti à la TVA), **à confirmer**.
- Tous les produits de test sont à 20 %. Le taux réel de chaque article est à valider.

## 2. Identifiants de l'entreprise
Champs Dolibarr pour le Maroc (✅ vérifié dans les fichiers de langue v24) :
ProfId1 = **R.C.**, ProfId2 = **Patente** (taxe professionnelle), ProfId3 = **I.F.**, ProfId4 = **C.N.S.S.**, ProfId5 = **ICE**.

- ❗ Les valeurs saisies sont **FICTIVES** (« MA SOCIETE TEST SARL », ICE 000000000000000…). Remplacez-les dans *Configuration → Société/Organisation* avant toute vraie facture, et ajoutez le logo.
- ❓ L'ICE compte 15 chiffres. Dolibarr ne contrôle pas son format pour le Maroc : à vérifier à la saisie.
- ❓ La « Patente » a été remplacée par la *taxe professionnelle*. Vérifiez l'identifiant exact à imprimer (n° TP).
- ❓ Mentions obligatoires sur les factures (CGI, art. 145 et textes associés) : ICE du vendeur **et du client**, IF, RC, TP, n° de facture, date, détail HT, taux et montant de TVA, TTC, mode de paiement, etc. Le PDF actuel affiche ICE vendeur et client, IF, RC, Patente et CNSS. **La liste complète est à faire valider**, ainsi que le capital social et la forme juridique, que je n'ai pas renseignés.

## 3. Numérotation des documents
- ✅ Numérotation continue vérifiée : factures `IN2611-0001`, `IN2611-0002` ; avoirs `IC2611-0001` ; numéro attribué seulement à la validation, donc pas de trou à cause des brouillons.
- ❓ Le format est `PRÉFIXE + AAMM + compteur`, avec un compteur **qui ne se remet pas à zéro chaque mois** par défaut (modèle *Terre*). Faut-il une remise à zéro annuelle, ou un préfixe `FA` plutôt que `IN` ? **Décision à prendre avant la première vraie facture.**
- ❓ Les avoirs ont leur propre série (`IC`). Vérifier que c'est accepté par votre comptable.
- ❓ Règles d'annulation : une facture validée ne doit pas être supprimée, on la corrige par un avoir. Le droit « repasser en brouillon » a été retiré au caissier ; l'admin l'a encore. Faut-il le retirer aussi à l'admin ?

## 4. Facturation électronique
- ❓ La DGI a annoncé une généralisation progressive de la facture électronique, avec un calendrier par taille d'entreprise. **Statut, calendrier et format exigés à vérifier.** Dolibarr ne propose pas en standard de connecteur DGI marocain : il faudra peut-être un module tiers, qui ne sera installé qu'avec votre accord.
- ❓ Exigences d'archivage : durée de conservation des factures et des pièces (souvent 10 ans), et inaltérabilité. Le module Dolibarr « Journaux inaltérables » (*blockedlog*) est à évaluer avec le comptable. Il n'est pas activé.

## 5. Plan comptable et comptabilité
- Module Comptabilité **non activé**, comme demandé.
- ❓ Le plan comptable marocain (CGNC/PCM) doit être chargé et validé avant l'activation. Vérifiez si Dolibarr fournit un plan « MA » adapté, ou s'il faut l'importer.
- ❓ Codes comptables clients et fournisseurs : modèle actuel *panicum* (automatique), à aligner sur votre plan de comptes.

## 6. Autres
- ❓ Monnaie MAD et arrondis : affichage à 2 décimales vérifié ✅ ; règles d'arrondi de la TVA (par ligne ou total) à valider.
- ❓ Paiements en espèces : il existe des plafonds légaux et des droits de timbre possibles sur les paiements en espèces. **À vérifier** ; ce n'est pas géré dans Dolibarr à ce stade.
- ❓ CNSS : identifiant seulement. La **paie n'est pas activée**.
- RIB à afficher sur les factures (paiement par virement) : nécessite le module Banques et caisses, à activer après votre accord.
- Données personnelles (loi 09-08, CNDP) : à vérifier si vous stockez des données de clients personnes physiques.
