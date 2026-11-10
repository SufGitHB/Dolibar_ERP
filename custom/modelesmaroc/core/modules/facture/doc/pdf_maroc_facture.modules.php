<?php
/* Modèle de facture « Maroc » : modèle standard Sponge + montant TTC en toutes lettres. */
require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/doc/pdf_sponge.modules.php';
dol_include_once('/modelesmaroc/lib/modelesmaroc.lib.php');

class pdf_maroc_facture extends pdf_sponge
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'maroc_facture';
		$this->description = 'Facture Maroc (ICE/IF/RC, montant en lettres)';
	}

	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs;
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		return modelesmaroc_avec_reglages($outputlangs, array(), 12, function () use ($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref) {
			return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
		});
	}

	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis)
	{
		$posy = parent::drawTotalTable($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
		if ($object->type == Facture::TYPE_CREDIT_NOTE) {
			$phrase = 'Arrêté le présent avoir à la somme de';
		} else {
			$phrase = 'Arrêtée la présente facture à la somme de';
		}
		return modelesmaroc_ecrire_arrete($pdf, $this, $object, $posy, $outputlangs, $phrase);
	}
}
