<?php
/* Modèle de commande client « Maroc » : modèle standard Eratosthene, titre « Bon de commande » + montant en lettres. */
require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/doc/pdf_eratosthene.modules.php';
dol_include_once('/modelesmaroc/lib/modelesmaroc.lib.php');

class pdf_maroc_commande extends pdf_eratosthene
{
	public function __construct($db)
	{
		parent::__construct($db);
		$this->name = 'maroc_commande';
		$this->description = 'Bon de commande Maroc (montant en lettres)';
	}

	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $langs;
		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		return modelesmaroc_avec_reglages($outputlangs, array('PdfOrderTitle' => 'Bon de commande'), 12, function () use ($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref) {
			return parent::write_file($object, $outputlangs, $srctemplatepath, $hidedetails, $hidedesc, $hideref);
		});
	}

	protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis = null)
	{
		$posy = parent::drawTotalTable($pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis);
		return modelesmaroc_ecrire_arrete($pdf, $this, $object, $posy, $outputlangs, 'Arrêtée la présente commande à la somme de');
	}
}
