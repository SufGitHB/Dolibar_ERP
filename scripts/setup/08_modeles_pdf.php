<?php
// Active le module « Modèles Maroc », enregistre les 4 modèles PDF, les met par défaut
// et régénère les PDF des documents de TEST pour contrôle visuel.
// Les modèles standards (cyan, eratosthene, espadon, sponge) restent disponibles.
require '/tmp/_bootstrap.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$db->begin();

$res = activateModule('modModelesMaroc');
if (!empty($res['errors'])) { ko('Activation modModelesMaroc : '.implode(' | ', $res['errors'])); $db->rollback(); exit(1); }
ok('Module « Modèles Maroc » activé');

$modeles = array(
	// type document_model => array(nom du modèle, constante du modèle par défaut)
	'propal'   => array('maroc_devis', 'PROPALE_ADDON_PDF'),
	'order'    => array('maroc_commande', 'COMMANDE_ADDON_PDF'),
	'shipping' => array('maroc_bl', 'EXPEDITION_ADDON_PDF'),
	'invoice'  => array('maroc_facture', 'FACTURE_ADDON_PDF'),
);
foreach ($modeles as $type => list($nom, $constante)) {
	$sql = "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."document_model WHERE nom = '".$db->escape($nom)."' AND type = '".$db->escape($type)."' AND entity = ".((int) $conf->entity);
	$obj = $db->fetch_object($db->query($sql));
	if (!$obj->nb && addDocumentModel($nom, $type) <= 0) { ko("Enregistrement du modèle $nom"); $db->rollback(); exit(1); }
	if (setc($constante, $nom) <= 0) { ko("Constante $constante"); $db->rollback(); exit(1); }
	ok("Modèle $nom enregistré et mis par défaut ($constante)");
}
$db->commit();

// Recharge la configuration (module_parts['models']) avant de générer.
$conf->setValues($db);

// Régénération des PDF des documents de test
$docs = array(
	array('Propal', 'PR2611-0001', 'maroc_devis'),
	array('Commande', 'SO2611-0001', 'maroc_commande'),
	array('Expedition', 'SH2611-0001', 'maroc_bl'),
	array('Facture', 'IN2611-0001', 'maroc_facture'),
	array('Facture', 'IC2611-0001', 'maroc_facture'),
);
foreach ($docs as list($classe, $ref, $modele)) {
	$o = new $classe($db);
	if ($o->fetch(0, $ref) <= 0) { ko("$ref introuvable"); continue; }
	$o->fetch_thirdparty();
	$r = $o->generateDocument($modele, $langs, 0, 0, 0);
	if ($r <= 0) { ko("PDF $ref ($modele)", $o); continue; }
	$dir = $o->element == 'propal' ? $conf->propal->multidir_output[$o->entity]
		: ($o->element == 'commande' ? $conf->commande->multidir_output[$o->entity]
		: ($o->element == 'shipping' ? $conf->expedition->dir_output.'/sending'
		: $conf->facture->multidir_output[$o->entity]));
	$f = $dir.'/'.dol_sanitizeFileName($ref).'/'.dol_sanitizeFileName($ref).'.pdf';
	file_exists($f) ? ok("PDF $ref généré avec $modele : $f") : ko("PDF $ref absent ($f)");
}
exit($GLOBALS['NB_KO'] ? 1 : 0);
