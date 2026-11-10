<?php
/* Modèle de devis « Maroc » : modèle standard Cyan, titre « Devis » + montant TTC en toutes lettres. */
require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/doc/pdf_cyan.modules.php';
dol_include_once('/modelesmaroc/lib/modelesmaroc.lib.php');

class pdf_maroc_devis extends pdf_cyan
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'maroc_devis';
		$this->description = 'Devis Maroc (titre « Devis », montant en lettres, zone « Bon pour accord »)';
	}

	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs;
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		return modelesmaroc_avec_reglages($outputlangs, array('PdfCommercialProposalTitle' => 'Devis'), 12, function () use ($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref) {
			return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
		});
	}

	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis = null)
	{
		$posy = parent::drawTotalTable($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
		return modelesmaroc_ecrire_arrete($pdf, $this, $object, $posy, $outputlangs, 'Arrêté le présent devis à la somme de');
	}
}
