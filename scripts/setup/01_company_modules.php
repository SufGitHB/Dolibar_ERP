<?php
require __DIR__.'/_bootstrap.php';
$db->begin();
// --- Étape 3 : fiche entreprise (valeurs FICTIVES à remplacer) ---
$vals = array(
  'MAIN_INFO_SOCIETE_NOM'     => 'MA SOCIETE TEST SARL',
  'MAIN_INFO_SOCIETE_ADDRESS' => "12, Rue Exemple (TEST)",
  'MAIN_INFO_SOCIETE_ZIP'     => '20000',
  'MAIN_INFO_SOCIETE_TOWN'    => 'Casablanca',
  'MAIN_INFO_SOCIETE_COUNTRY' => '12:MA:Maroc',
  'MAIN_INFO_SOCIETE_TEL'     => '+212 5 00 00 00 00',
  'MAIN_INFO_SOCIETE_MAIL'    => 'contact@example.invalid',
  'MAIN_INFO_SIREN'           => 'RC-TEST-000000',   // ProfId1 MA = R.C.
  'MAIN_INFO_SIRET'           => 'PAT-TEST-000000',  // ProfId2 MA = Patente
  'MAIN_INFO_APE'             => 'IF-TEST-0000000',  // ProfId3 MA = I.F.
  'MAIN_INFO_RCS'             => 'CNSS-TEST-00000',  // ProfId4 MA = C.N.S.S.
  'MAIN_INFO_PROFID5'         => '000000000000000',  // ProfId5 MA = ICE (15 chiffres)
  'MAIN_MONNAIE'              => 'MAD',
  'MAIN_LANG_DEFAULT'         => 'fr_FR',
  'FACTURE_TVAOPTION'         => '1',                 // assujetti à la TVA (à confirmer)
);
foreach ($vals as $k => $v) { if (setc($k, $v) > 0) ok("$k"); else ko("const $k"); }
// --- Étape 4 : modules (liste validée par l'utilisateur) ---
$mods = array('modSociete','modProduct','modService','modStock','modPropale','modCommande','modExpedition','modFacture','modFournisseur');
foreach ($mods as $m) {
  $r = activateModule($m, 1);
  if (empty($r['errors'])) ok("Module $m activé"); else ko("Module $m : ".implode(' ', $r['errors']));
}
if ($GLOBALS['NB_KO']) { $db->rollback(); echo "ROLLBACK\n"; exit(1); }
$db->commit(); echo "COMMIT\n";
