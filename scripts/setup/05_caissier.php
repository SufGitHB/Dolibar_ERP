<?php
// Crée l'utilisateur "caissier" sans droits d'administration. Mot de passe lu depuis l'environnement (issu de .env).
require __DIR__.'/_bootstrap.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$login = getenv('CAISSIER_LOGIN') ?: 'caissier'; $pass = getenv('CAISSIER_PASSWORD');
if (!$pass) { ko('CAISSIER_PASSWORD absent'); exit(1); }
$u = new User($db);
if ($u->fetch(0, $login) > 0) { info("Utilisateur $login existe déjà (#$u->id)"); }
else {
  $u->login = $login; $u->lastname = 'Caissier'; $u->firstname = 'TEST'; $u->admin = 0; $u->employee = 1; $u->status = 1; $u->entity = $conf->entity;
  $id = $u->create($user); if ($id <= 0) { ko('création caissier', $u); exit(1); }
  $u->fetch($id); ok("Utilisateur $login créé (#$id), admin=0");
}
if ($u->setPassword($user, $pass) < 0) { ko('mot de passe', $u); exit(1); } else ok('Mot de passe caissier défini (depuis .env)');
// Droits minimaux d'un caissier : consulter tiers/produits/stock, créer factures et encaisser.
$rights = array(array('societe','lire'), array('societe','client','voir'), array('produit','lire'), array('service','lire'), array('stock','lire'),
  array('commande','lire'), array('expedition','lire'), array('facture','lire'), array('facture','creer'), array('facture','paiement'), array('facture','invoice_advance','validate'));
foreach ($rights as $r) {
  if (!isset($r[2])) { $res = $u->addrights(0, $r[0], $r[1]); }
  else { $sql = "SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='".$r[0]."' AND perms='".$r[1]."' AND subperms='".$r[2]."' AND entity=".$conf->entity;
    $o = $db->fetch_object($db->query($sql)); $res = $o ? $u->addrights($o->id) : -1; }
  ($res >= 0) ? ok('Droit '.implode('->', $r)) : ko('Droit '.implode('->', $r), $u);
}
$u->clearrights(); $u->loadRights();
info('Contrôle : admin='.$u->admin.' ; config modifiable (user->admin requis) : '.($u->admin ? 'OUI' : 'NON').' ; facture créer : '.($u->hasRight('facture','creer') ? 'oui' : 'non').' ; produit créer : '.($u->hasRight('produit','creer') ? 'oui' : 'non'));
$r = $db->query("SELECT login, admin, statut FROM ".MAIN_DB_PREFIX."user ORDER BY rowid"); while ($o = $db->fetch_object($r)) info("Compte : $o->login admin=$o->admin actif=$o->statut");
