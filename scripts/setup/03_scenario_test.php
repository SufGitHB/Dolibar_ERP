<?php
// Étape 6 : scénario de bout en bout. Chaque étape affiche ATTENDU / OBTENU / OK|ECHEC.
require __DIR__.'/_bootstrap.php';
foreach (array('/comm/propal/class/propal.class.php','/commande/class/commande.class.php','/expedition/class/expedition.class.php',
  '/compta/facture/class/facture.class.php','/compta/paiement/class/paiement.class.php','/fourn/class/fournisseur.commande.class.php',
  '/product/class/product.class.php','/product/stock/class/entrepot.class.php','/product/stock/class/mouvementstock.class.php',
  '/product/inventory/class/inventory.class.php','/societe/class/societe.class.php') as $f) require_once DOL_DOCUMENT_ROOT.$f;

$R = array();
function check($step, $attendu, $obtenu, $cond) { global $R; $s = $cond ? 'OK' : 'ECHEC'; $R[] = array($step, $attendu, $obtenu, $s);
  echo str_pad("[$s]", 9)."$step\n         attendu : $attendu\n         obtenu  : $obtenu\n"; if (!$cond) $GLOBALS['NB_KO']++; }
function die_on($r, $o, $what) { if ($r <= 0) { ko($what, $o); exit(1); } return $r; }
function pid($ref) { $p = new Product($GLOBALS['db']); $p->fetch(0, $ref); return $p->id; }
function wid($lab) { $e = new Entrepot($GLOBALS['db']); $e->fetch(0, $lab); return $e->id; }
function sid($nom) { $r = $GLOBALS['db']->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom='".$GLOBALS['db']->escape($nom)."'"); return $GLOBALS['db']->fetch_object($r)->rowid; }
function stock($pid, $wid) { $r = $GLOBALS['db']->query("SELECT reel FROM ".MAIN_DB_PREFIX."product_stock WHERE fk_product=".((int)$pid)." AND fk_entrepot=".((int)$wid)); $o = $GLOBALS['db']->fetch_object($r); return $o ? (float) $o->reel : 0.0; }
function idFromCode($table, $code, $col = 'rowid') { return dol_getIdFromCode($GLOBALS['db'], $code, $table, 'code', $col); }

$MAG = wid('Magasin principal');
$P1 = pid('TEST-P01'); $P3 = pid('TEST-P03'); $P2 = pid('TEST-P02'); $P5 = pid('TEST-P05'); $S1 = pid('TEST-S01');
$ALPHA = sid('TEST Client Alpha SARL'); $BETA = sid('TEST Client Beta SA'); $GAMMA = sid('TEST Fournisseur Gamma');
$now = dol_now();

// ---------- 1. Devis 3 lignes dont une remisée ----------
$pr = new Propal($db); $pr->socid = $ALPHA; $pr->datep = $now; $pr->duree_validite = 30;
$pr->cond_reglement_id = idFromCode('c_payment_term', '30D'); $pr->mode_reglement_id = idFromCode('c_paiement', 'VIR', 'id');
die_on($pr->create($user), $pr, 'création devis');
$pr->addline('', 45.00, 10, 20, 0, 0, $P1, 0);      // 10 x 45 = 450
$pr->addline('', 18.50, 5, 20, 0, 0, $P3, 10);      // 5 x 18.5 -10 % = 83.25
$pr->addline('', 300.00, 2, 20, 0, 0, $S1, 0);      // 2 x 300 = 600
$pr->fetch($pr->id);
$remLine = 0; foreach ($pr->lines as $l) if ($l->remise_percent > 0) $remLine++;
check('1. Devis 3 lignes dont 1 remisée', '3 lignes, 1 avec remise 10 %, total HT 1133.25, TVA 226.65, TTC 1359.90',
  count($pr->lines).' lignes, '.$remLine.' remisée(s), HT '.price2num($pr->total_ht).', TVA '.price2num($pr->total_tva).', TTC '.price2num($pr->total_ttc),
  count($pr->lines) == 3 && $remLine == 1 && abs($pr->total_ht - 1133.25) < 0.01 && abs($pr->total_ttc - 1359.90) < 0.01);

// ---------- 2. Validation + signature + transformation en commande ----------
die_on($pr->valid($user), $pr, 'validation devis'); $pr->fetch($pr->id);
$pr->generateDocument('cyan', $langs);
die_on($pr->closeProposal($user, Propal::STATUS_SIGNED, 'Signé (test)'), $pr, 'signature devis'); $pr->fetch($pr->id);
$co = new Commande($db); $coid = die_on($co->createFromProposal($pr, $user), $co, 'commande depuis devis');
$co->fetch($coid); die_on($co->valid($user), $co, 'validation commande'); $co->fetch($coid);
check('2. Devis validé puis transformé en commande', 'Devis PR… statut Signé ; commande CO… validée, 3 lignes, même total HT',
  "Devis {$pr->ref} statut ".$pr->getLibStatut(0)." ; commande {$co->ref} statut ".$co->getLibStatut(0).', '.count($co->lines).' lignes, HT '.price2num($co->total_ht),
  $pr->statut == Propal::STATUS_SIGNED && $co->statut == Commande::STATUS_VALIDATED && count($co->lines) == 3 && abs($co->total_ht - $pr->total_ht) < 0.01 && strpos($co->ref, 'CO') === 0);

// ---------- 3. Expédition : le stock diminue ----------
$b1 = stock($P1, $MAG); $b3 = stock($P3, $MAG);
$ex = new Expedition($db); $ex->origin = 'commande'; $ex->origin_type = 'commande'; $ex->origin_id = $co->id; $ex->socid = $co->socid;
$ex->fk_project = 0; $ex->date_delivery = $now; $ex->date_shipping = $now; $ex->entrepot_id = $MAG; $ex->ref_customer = $co->ref_client;
foreach ($co->lines as $l) if ($l->product_type == 0) $ex->addline($MAG, $l->id, $l->qty);
die_on($ex->create($user), $ex, 'création expédition'); die_on($ex->valid($user), $ex, 'validation expédition'); $ex->fetch($ex->id);
$a1 = stock($P1, $MAG); $a3 = stock($P3, $MAG);
check('3. Expédition depuis la commande, stock diminue', "TEST-P01 Magasin $b1 → ".($b1-10)." ; TEST-P03 $b3 → ".($b3-5)." (service non expédié)",
  "Expédition {$ex->ref} ".$ex->getLibStatut(0)." ; TEST-P01 $b1 → $a1 ; TEST-P03 $b3 → $a3",
  $a1 == $b1 - 10 && $a3 == $b3 - 5 && $ex->statut == Expedition::STATUS_VALIDATED);

// ---------- 4. Facture depuis la commande, validation, PDF ----------
$fa = new Facture($db); $faid = die_on($fa->createFromOrder($co, $user), $fa, 'facture depuis commande');
$fa->fetch($faid); $provref = $fa->ref;
die_on($fa->validate($user), $fa, 'validation facture'); $fa->fetch($faid);
$fa->generateDocument('sponge', $langs);
$pdf = $conf->facture->multidir_output[$conf->entity].'/'.dol_sanitizeFileName($fa->ref).'/'.dol_sanitizeFileName($fa->ref).'.pdf';
check('4. Facture depuis la commande, validée, PDF généré', 'Brouillon (PROV…) → FA'.dol_print_date($now, '%y%m').'-0001, TTC 1359.90, PDF présent',
  "$provref → {$fa->ref}, TTC ".price2num($fa->total_ttc).', PDF '.(is_file($pdf) ? 'présent ('.filesize($pdf).' o)' : 'ABSENT'),
  preg_match('/^FA\d{4}-\d{4}$/', $fa->ref) && $fa->statut == Facture::STATUS_VALIDATED && is_file($pdf) && abs($fa->total_ttc - 1359.90) < 0.01);
echo "PDF_FACTURE=$pdf\n";

// ---------- 5. Paiement partiel puis solde ----------
$pay = function ($amount, $code, $num) use ($db, $user, $fa, $now) {
  $p = new Paiement($db); $p->datepaye = $now; $p->amounts = array($fa->id => $amount); $p->multicurrency_amounts = array();
  $p->paiementid = dol_getIdFromCode($db, $code, 'c_paiement', 'code', 'id'); $p->paiementcode = $code; $p->num_payment = $num; $p->note_private = 'Paiement TEST';
  return array($p->create($user, 1), $p); };
list($r, $p) = $pay(500.00, 'LIQ', ''); die_on($r, $p, 'paiement partiel'); $fa->fetch($fa->id);
$reste1 = $fa->getRemainToPay(); $st1 = $fa->getLibStatut(0);
check('5a. Paiement partiel 500 MAD en espèces', 'Statut « Commencée/Paiement partiel », reste 859.90', "Statut « $st1 », reste ".price2num($reste1),
  $fa->statut == Facture::STATUS_VALIDATED && $fa->paye == 0 && abs($reste1 - 859.90) < 0.01);
list($r, $p) = $pay($reste1, 'CHQ', 'CHQ-TEST-0001'); die_on($r, $p, 'paiement solde'); $fa->fetch($fa->id);
check('5b. Paiement du solde par chèque', 'Statut « Payée », reste 0', 'Statut « '.$fa->getLibStatut(0).' », reste '.price2num($fa->getRemainToPay()).', paye='.$fa->paye,
  $fa->statut == Facture::STATUS_CLOSED && $fa->paye == 1 && abs($fa->getRemainToPay()) < 0.01);

// ---------- 6. Réception fournisseur : le stock augmente ----------
$b5 = stock($P5, $MAG);
$cf = new CommandeFournisseur($db); $cf->socid = $GAMMA; $cf->ref_supplier = 'GAM-TEST-1';
$cf->cond_reglement_id = idFromCode('c_payment_term', '30D'); $cf->mode_reglement_id = idFromCode('c_paiement', 'VIR', 'id');
die_on($cf->create($user), $cf, 'création commande fournisseur');
$pfp = $db->fetch_object($db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product_fournisseur_price WHERE fk_product=$P5 AND fk_soc=$GAMMA"))->rowid;
die_on($cf->addline('', 130.00, 5, 20, 0, 0, $P5, $pfp, 'GAM-TEST-P05'), $cf, 'ligne commande fournisseur');
$cf->fetch($cf->id);
die_on($cf->valid($user), $cf, 'validation CF'); die_on($cf->approve($user), $cf, 'approbation CF');
die_on($cf->commande($user, $now, dol_getIdFromCode($db, 'OrderByEMail', 'c_input_method', 'code', 'rowid') ?: 1), $cf, 'envoi CF');
$cf->fetch($cf->id);
die_on($cf->dispatchProduct($user, $P5, 5, $MAG, 130.00, 'Réception TEST', '', '', '', $cf->lines[0]->id), $cf, 'réception');
if (method_exists($cf, 'calcAndSetStatusDispatch')) $cf->calcAndSetStatusDispatch($user);
$cf->fetch($cf->id); $a5 = stock($P5, $MAG);
check('6. Réception fournisseur, stock augmente', "TEST-P05 Magasin $b5 → ".($b5+5), "Commande fournisseur {$cf->ref} « ".$cf->getLibStatut(0)." » ; TEST-P05 $b5 → $a5", $a5 == $b5 + 5);

// ---------- 7. Inventaire avec écart ----------
$b2 = stock($P2, $MAG); $compte = $b2 - 2;
$inv = new Inventory($db); $inv->ref = 'INV-TEST-'.dol_print_date($now, '%y%m%d%H%M%S'); $inv->title = 'Inventaire TEST TEST-P02';
$inv->fk_warehouse = $MAG; $inv->fk_product = $P2;
die_on($inv->create($user), $inv, 'création inventaire'); die_on($inv->validate($user), $inv, 'démarrage inventaire');
$db->query("UPDATE ".MAIN_DB_PREFIX."inventorydet SET qty_view = ".((float) $compte)." WHERE fk_inventory = ".((int) $inv->id));
// Enregistrement : reproduit exactement l'action « update » de product/inventory/inventory.php (v24)
$db->begin(); $mv = new MouvementStock($db); $mv->setOrigin($inv->element, $inv->id);
$res = $db->query("SELECT rowid, fk_warehouse, fk_product, batch, qty_stock, qty_view FROM ".MAIN_DB_PREFIX."inventorydet WHERE fk_inventory = ".((int) $inv->id));
while ($l = $db->fetch_object($res)) {
  $ps = new Product($db); $ps->fetch($l->fk_product, '', '', '', 1, 1, 1); $ps->load_stock(',novirtual');
  $real = $ps->stock_warehouse[$l->fk_warehouse]->real; $delta = price2num($l->qty_view - $real, 'MS');
  if ($delta != 0) {
    $idm = $mv->_create($user, $l->fk_product, $l->fk_warehouse, (float) $delta, $delta < 0 ? 1 : 0, 0, $langs->trans('LabelOfInventoryMovemement', $inv->ref), 'INV-'.$inv->ref, '', '', '', $l->batch);
    if ($idm < 0) { $db->rollback(); ko('mouvement inventaire', $mv); exit(1); }
    $db->query("UPDATE ".MAIN_DB_PREFIX."inventorydet SET fk_movement = ".((int) $idm)." WHERE rowid = ".((int) $l->rowid));
  }
}
$inv->setRecorded($user); $db->commit(); $inv->fetch($inv->id); $a2 = stock($P2, $MAG);
$nbmv = $db->fetch_object($db->query("SELECT COUNT(*) n, SUM(value) q FROM ".MAIN_DB_PREFIX."stock_mouvement WHERE inventorycode = 'INV-".$db->escape($inv->ref)."'"));
check('7. Inventaire TEST-P02 avec écart -2', "Stock théorique $b2, compté $compte → stock $compte, 1 mouvement de -2, inventaire « Enregistré »",
  "Stock $b2 → $a2, {$nbmv->n} mouvement(s) de {$nbmv->q}, inventaire « ".$inv->getLibStatut(0).' »',
  $a2 == $compte && $nbmv->n == 1 && $nbmv->q == -2 && $inv->status == Inventory::STATUS_RECORDED);

// ---------- 8. Avoir + numérotation séquentielle ----------
$av = new Facture($db); $av->socid = $fa->socid; $av->type = Facture::TYPE_CREDIT_NOTE; $av->fk_facture_source = $fa->id;
$av->date = $now; $av->cond_reglement_id = $fa->cond_reglement_id; $av->mode_reglement_id = $fa->mode_reglement_id; $av->note_public = 'Retour de 2 ramettes (TEST)';
die_on($av->create($user), $av, 'création avoir');
$av->addline('Retour TEST-P01', 45.00, 2, 20, 0, 0, $P1, 0);
$av->fetch($av->id); die_on($av->validate($user), $av, 'validation avoir'); $av->fetch($av->id);
$av->generateDocument('sponge', $langs);
// Une seconde facture validée après l'avoir, pour vérifier la continuité de la série FA
$fb = new Facture($db); $fb->socid = $BETA; $fb->type = Facture::TYPE_STANDARD; $fb->date = $now; $fb->cond_reglement_id = idFromCode('c_payment_term', 'RECEP'); $fb->mode_reglement_id = idFromCode('c_paiement', 'LIQ', 'id');
die_on($fb->create($user), $fb, 'création facture 2'); $fb->addline('', 800.00, 1, 20, 0, 0, pid('TEST-S02'), 0); $fb->fetch($fb->id);
die_on($fb->validate($user), $fb, 'validation facture 2'); $fb->fetch($fb->id);
$seq = function ($prefix) use ($db) { $out = array(); $r = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."facture WHERE ref LIKE '".$prefix."____-%' ORDER BY ref");
  while ($o = $db->fetch_object($r)) $out[] = $o->ref; return $out; };
$fas = $seq('FA'); $avs = $seq('AV');
$contig = function ($refs) { foreach ($refs as $i => $ref) if ((int) substr($ref, -4) != $i + 1) return false; return count($refs) > 0; };
check('8. Avoir sur la facture, numérotation sans trou', 'Avoir AV…-0001 lié à '.$fa->ref.', TTC -108.00 ; série FA continue (0001, 0002) ; série AV continue',
  "Avoir {$av->ref} (source #{$av->fk_facture_source}) TTC ".price2num($av->total_ttc).' ; FA = '.implode(', ', $fas).' ; AV = '.implode(', ', $avs),
  $av->type == 2 && $av->fk_facture_source == $fa->id && abs($av->total_ttc + 108) < 0.01 && $contig($fas) && $contig($avs) && count($fas) == 2);

// ---------- 9. Alerte de stock ----------
$r = $db->query("SELECT p.ref, p.seuil_stock_alerte s, COALESCE(SUM(ps.reel),0) st FROM ".MAIN_DB_PREFIX."product p LEFT JOIN ".MAIN_DB_PREFIX."product_stock ps ON ps.fk_product=p.rowid
  WHERE p.fk_product_type=0 AND p.seuil_stock_alerte IS NOT NULL AND p.entity IN (".getEntity('product').") GROUP BY p.rowid HAVING st < s ORDER BY p.ref");
$al = array(); while ($o = $db->fetch_object($r)) $al[] = "$o->ref (stock ".(float)$o->st." < seuil ".(float)$o->s.")";
$p3 = new Product($db); $p3->fetch($P3); $p3->load_stock();
check('9. Produit sous le seuil dans les alertes', 'TEST-P03 (stock 7 < seuil 10) présent dans la liste des alertes',
  (count($al) ? implode(' ; ', $al) : 'aucune alerte').' ; Product::load_stock TEST-P03 = '.$p3->stock_reel,
  count(preg_grep('/^TEST-P03 /', $al)) == 1 && $p3->stock_reel < $p3->seuil_stock_alerte);

echo "\nREFS devis={$pr->ref} commande={$co->ref} expedition={$ex->ref} facture={$fa->ref} avoir={$av->ref} facture2={$fb->ref} cmdfourn={$cf->ref} inventaire={$inv->ref}\n";
echo "BILAN ".(count($R) - $GLOBALS['NB_KO'])."/".count($R)." OK\n";
