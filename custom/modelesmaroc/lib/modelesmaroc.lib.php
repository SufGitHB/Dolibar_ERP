<?php
/* Fonctions communes aux modèles PDF « Maroc ». */
require_once DOL_DOCUMENT_ROOT.'/core/lib/functionsnumtoword.lib.php';

/**
 * Montant en toutes lettres, en dirhams : « Mille trois cent cinquante-neuf dirhams et quatre-vingt-dix centimes ».
 */
function modelesmaroc_montant_lettres($montant, $outputlangs)
{
	$montant = round(abs((float) $montant), 2);
	$dh = (int) floor($montant + 0.0000001);
	$cts = (int) round(($montant - $dh) * 100);

	$txt = dol_convertToWord($dh, $outputlangs, '', false);
	if ($dh == 0) {
		$txt = 'zéro';
	}
	// « deux millions de dirhams », « un milliard de dirhams »
	$txt .= (preg_match('/(million|milliard)s?$/u', $txt) ? ' de' : '').($dh > 1 ? ' dirhams' : ' dirham');
	if ($cts > 0) {
		$txt .= ' et '.dol_convertToWord($cts, $outputlangs, '', false).($cts > 1 ? ' centimes' : ' centime');
	}
	return dol_ucfirst(trim($txt));
}

/**
 * Écrit « Arrêté(e) … à la somme de : <montant> TTC » sous le tableau des totaux (colonne de droite).
 * Retourne la nouvelle position verticale.
 */
function modelesmaroc_ecrire_arrete(&$pdf, $model, $object, $posy, $outputlangs, $phrase)
{
	$default_font_size = pdf_getPDFFontSize($outputlangs);
	$posx = 120;
	$largeur = $model->page_largeur - $model->marge_droite - $posx;
	$html = dol_htmlentities($phrase, ENT_QUOTES).' :<br><b>'
		.dol_htmlentities(modelesmaroc_montant_lettres($object->total_ttc, $outputlangs), ENT_QUOTES).' TTC</b>';

	$pdf->SetTextColor(0, 0, 0);
	$pdf->SetFont('', '', $default_font_size - 2);
	$pdf->writeHTMLCell($largeur, 3, $posx, $posy + 2, $html, 0, 1, false, true, 'L');
	return $pdf->GetY();
}

/**
 * Exécute $callback avec : des titres de documents remplacés (uniquement pour ce PDF)
 * et une hauteur réservée en bas de page augmentée de $hauteur_sup mm.
 */
function modelesmaroc_avec_reglages($outputlangs, array $titres, $hauteur_sup, callable $callback)
{
	global $conf;

	$outputlangs->loadLangs(array('main', 'bills', 'propal', 'orders', 'sendings', 'companies'));
	$anciens_titres = array();
	foreach ($titres as $cle => $valeur) {
		$anciens_titres[$cle] = $outputlangs->tab_translate[$cle] ?? null;
		$outputlangs->tab_translate[$cle] = $valeur;
	}
	$avait_hauteur = isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT);
	$ancienne_hauteur = $avait_hauteur ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : null;
	$conf->global->MAIN_PDF_FREETEXT_HEIGHT = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5) + $hauteur_sup;

	try {
		return $callback();
	} finally {
		foreach ($anciens_titres as $cle => $valeur) {
			if ($valeur === null) {
				unset($outputlangs->tab_translate[$cle]);
			} else {
				$outputlangs->tab_translate[$cle] = $valeur;
			}
		}
		if ($avait_hauteur) {
			$conf->global->MAIN_PDF_FREETEXT_HEIGHT = $ancienne_hauteur;
		} else {
			unset($conf->global->MAIN_PDF_FREETEXT_HEIGHT);
		}
	}
}
