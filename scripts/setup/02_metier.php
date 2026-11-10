<?php
require __DIR__.'/_bootstrap.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
$db->begin();

// --- Paramètres stock / PDF ---
foreach (array(
  'STOCK_CALCULATE_ON_SHIPMENT' => '1',                  // stock diminue à la validation de l'expédition
  'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER' => '1',   // stock augmente à la réception d'une commande fournisseur
  'MAIN_PROFID5_IN_ADDRESS' => '1',                      // ICE du client dans le bloc adresse PDF
  'MAIN_PROFID5_IN_SOURCE_ADDRESS' => '1',               // ICE de l'entreprise dans le bloc émetteur PDF
  'MAIN_PROFID3_IN_SOURCE_ADDRESS' => '1',               // IF de l'entreprise dans le bloc émetteur PDF
  'PRODUIT_DESC_IN_FORM' => '1',
) as $k => $v) { if (setc($k, $v) > 0) ok("const $k=$v"); else ko("const $k"); }

// --- Modes de règlement : espèces, virement, chèque, carte uniquement ---
$db->query("UPDATE ".MAIN_DB_PREFIX."c_paiement SET active = CASE WHEN code IN ('LIQ','VIR','CHQ','CB') THEN 1 ELSE 0 END WHERE entity = ".((int) $conf->entity)) ? ok("Modes de règlement : LIQ, VIR, CHQ, CB actifs ; autres désactivés") : ko("modes de règlement");
// --- Conditions de paiement : à réception, 30 j, 30 j fin de mois, 60 j, 50/50 ---
$db->query("UPDATE ".MAIN_DB_PREFIX."c_payment_term SET active = CASE WHEN code IN ('RECEP','30D','30DENDMONTH','60D','PT_5050') THEN 1 ELSE 0 END WHERE entity = ".((int) $conf->entity)) ? ok("Conditions de paiement : RECEP, 30D, 30DENDMONTH, 60D, PT_5050 actives") : ko("conditions");

// --- Entrepôts ---
$wh = array();
foreach (array('Magasin principal' => 'Point de vente', 'Réserve' => 'Stock de réserve') as $lab => $desc) {
  $e = new Entrepot($db);
  if ($e->fetch(0, $lab) > 0) { $wh[$lab] = $e->id; info("Entrepôt '$lab' existe déjà (#$e->id)"); continue; }
  $e->label = $lab; $e->description = $desc; $e->statut = 1; $e->town = 'Casablanca'; $e->country_id = 12;
  $id = $e->create($user); if ($id > 0) { $wh[$lab] = $id; ok("Entrepôt '$lab' #$id"); } else ko("Entrepôt $lab", $e);
}

// --- Produits et services ---  [ref, libellé, type 0=produit 1=service, PU HT MAD, TVA, seuil alerte, stock initial Magasin, stock initial Réserve]
$items = array(
  array('TEST-P01', 'TEST Ramette papier A4 80g',      0,   45.00, 20, 20, 100, 50),
  array('TEST-P02', 'TEST Stylo bille bleu (boîte 50)',0,  120.00, 20,  5,  30, 10),
  array('TEST-P03', 'TEST Classeur à levier',          0,   18.50, 20, 10,  12,  0),
  array('TEST-P04', 'TEST Cartouche encre noire',      0,  250.00, 20,  3,  15,  5),
  array('TEST-P05', 'TEST Calculatrice de bureau',     0,  199.00, 20,  2,  10,  0),
  array('TEST-S01', 'TEST Installation sur site (heure)', 1, 300.00, 20, 0, 0, 0),
  array('TEST-S02', 'TEST Maintenance mensuelle',      1,  800.00, 20,  0,   0,  0),
);
$pids = array();
foreach ($items as $it) {
  list($ref, $lab, $type, $pu, $tva, $alert, $s1, $s2) = $it;
  $p = new Product($db);
  if ($p->fetch(0, $ref) > 0) { $pids[$ref] = $p->id; info("$ref existe déjà"); continue; }
  $p->ref = $ref; $p->label = $lab; $p->type = $type; $p->price = $pu; $p->price_base_type = 'HT'; $p->tva_tx = $tva;
  $p->status = 1; $p->status_buy = 1; $p->description = 'Article fictif de test';
  if ($type == 0) { $p->seuil_stock_alerte = $alert; $p->desiredstock = $alert * 3; }
  $id = $p->create($user);
  if ($id <= 0) { ko("Produit $ref", $p); continue; }
  $pids[$ref] = $id; ok("$ref '$lab' PU HT $pu MAD, TVA $tva %".($type == 0 ? ", seuil $alert" : " (service)"));
  if ($type == 0) {
    $p->fetch($id);
    if ($s1 > 0 && $p->correct_stock($user, $wh['Magasin principal'], $s1, 0, 'Stock initial TEST') <= 0) ko("stock $ref", $p);
    if ($s2 > 0 && $p->correct_stock($user, $wh['Réserve'], $s2, 0, 'Stock initial TEST') <= 0) ko("stock $ref", $p);
    info("stock initial : Magasin $s1, Réserve $s2");
  }
}

// --- Tiers TEST ---  [nom, client(1)/fournisseur, ICE fictif, ville]
$tiers = array(
  array('TEST Client Alpha SARL', 1, 0, '111111111111111', 'Rabat'),
  array('TEST Client Beta SA',    1, 0, '222222222222222', 'Marrakech'),
  array('TEST Fournisseur Gamma', 0, 1, '333333333333333', 'Tanger'),
  array('TEST Fournisseur Delta', 0, 1, '444444444444444', 'Fès'),
);
$sids = array();
foreach ($tiers as $t) {
  list($nom, $cli, $fou, $ice, $ville) = $t;
  $s = new Societe($db);
  $r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom='".$db->escape($nom)."'");
  if ($o = $db->fetch_object($r)) { $sids[$nom] = $o->rowid; info("$nom existe déjà"); continue; }
  $s->name = $nom; $s->client = $cli; $s->fournisseur = $fou; $s->code_client = $cli ? -1 : ''; $s->code_fournisseur = $fou ? -1 : '';
  $s->country_id = 12; $s->town = $ville; $s->address = 'Adresse fictive TEST'; $s->email = 'test@example.invalid';
  $s->idprof5 = $ice; $s->tva_assuj = 1; $s->status = 1;
  $s->cond_reglement_id = dol_getIdFromCode($db, '30D', 'c_payment_term', 'code', 'rowid');
  $s->mode_reglement_id = dol_getIdFromCode($db, 'VIR', 'c_paiement', 'code', 'id');
  $id = $s->create($user);
  if ($id > 0) { $sids[$nom] = $id; ok("Tiers '$nom' #$id (".($cli ? 'client' : 'fournisseur').", ICE fictif $ice)"); } else ko("Tiers $nom", $s);
}

// --- Prix d'achat fournisseur (nécessaires aux commandes fournisseurs) ---
foreach (array('TEST-P01' => 30.00, 'TEST-P03' => 11.00, 'TEST-P05' => 130.00) as $ref => $buy) {
  $pf = new ProductFournisseur($db); $pf->fetch($pids[$ref]);
  $f = new Fournisseur($db); $f->fetch($sids['TEST Fournisseur Gamma']);
  $r = $pf->update_buyprice(1, $buy, $user, 'HT', $f, 0, 'GAM-'.$ref, 20);
  if ($r >= 0) ok("Prix d'achat $ref chez Gamma : $buy MAD HT"); else ko("prix achat $ref", $pf);
}

if ($GLOBALS['NB_KO']) { $db->rollback(); echo "ROLLBACK ({$GLOBALS['NB_KO']} erreurs)\n"; exit(1); }
$db->commit(); echo "COMMIT\n";
