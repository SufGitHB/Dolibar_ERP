<?php
/* Module « Modèles Maroc » : modèles PDF devis / commande / bon de livraison / facture
 * adaptés à l'usage marocain. Aucun fichier source de Dolibarr n'est modifié :
 * les modèles héritent des modèles standards (cyan, eratosthene, espadon, sponge).
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modModelesMaroc extends DolibarrModules
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->numero = 500100;                 // plage 500000+ réservée aux modules privés
		$this->rights_class = 'modelesmaroc';
		$this->family = 'other';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Modèles PDF Maroc : devis, bon de commande, bon de livraison, facture (montant en lettres, signatures)';
		$this->editor_name = 'Local';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'pdf';
		$this->module_parts = array('models' => 1);   // Dolibarr cherchera core/modules/<type>/doc/ dans ce module
		$this->dirs = array();
		$this->config_page_url = array();
		$this->depends = array('modPropale', 'modCommande', 'modExpedition', 'modFacture');
		$this->requiredby = array();
		$this->langfiles = array();
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, 0);
		$this->const = array();
		$this->rights = array();
		$this->menu = array();
	}

	public function init($options = '')
	{
		return $this->_init(array(), $options);
	}

	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
