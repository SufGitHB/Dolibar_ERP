<?php
/* Bon de livraison « Maroc » : modèle standard Espadon (sans prix), titre « Bon de livraison »
 * + cadres de signature société / client en bas de la dernière page. */
require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/doc/pdf_espadon.modules.php';
dol_include_once('/modelesmaroc/lib/modelesmaroc.lib.php');

class pdf_maroc_bl extends pdf_espadon
{
	/** Hauteur (mm) réservée aux cadres de signature */
	const HAUTEUR_SIGNATURES = 32;

	public function __construct(DoliDB $db)
	{
		parent::__construct($db);
		$this->name = 'maroc_bl';
		$this->description = 'Bon de livraison Maroc (sans prix, cadres de signature et cachet)';
	}

	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs;
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		return modelesmaroc_avec_reglages($outputlangs, array('SendingSheet' => 'Bon de livraison'), self::HAUTEUR_SIGNATURES, function () use ($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref) {
			return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
		});
	}

	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		// $hidefreetext = 0 uniquement sur la dernière page : c'est là que l'on place les signatures,
		// dans l'espace réservé au-dessus du pied de page.
		if (empty($hidefreetext)) {
			$this->dessinerSignatures($pdf, $outputlangs);
		}
		return parent::_pagefoot($pdf, $object, $outputlangs, $hidefreetext);
	}

	private function dessinerSignatures(&$pdf, $outputlangs)
	{
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$hauteur_pied = $this->marge_basse + 8;
		$y = $this->page_hauteur - $hauteur_pied - getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5) + 4;
		$h = self::HAUTEUR_SIGNATURES - 10;
		$espace = 6;
		$largeur = ($this->page_largeur - $this->marge_gauche - $this->marge_droite - $espace) / 2;
		$x2 = $this->marge_gauche + $largeur + $espace;

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', 'B', $default_font_size - 2);
		$pdf->SetXY($this->marge_gauche, $y);
		$pdf->MultiCell($largeur, 4, 'Livré par (cachet et signature de la société)', 0, 'L');
		$pdf->SetXY($x2, $y);
		$pdf->MultiCell($largeur, 4, 'Reçu conforme par le client', 0, 'L');
		$pdf->SetFont('', '', $default_font_size - 3);
		$pdf->SetXY($x2, $y + 4);
		$pdf->MultiCell($largeur, 3, 'Nom, date, cachet et signature', 0, 'L');

		$pdf->RoundedRect($this->marge_gauche, $y + 8, $largeur, $h - 4, $this->corner_radius, '1234', 'D');
		$pdf->RoundedRect($x2, $y + 8, $largeur, $h - 4, $this->corner_radius, '1234', 'D');
	}
}
