<?php
require __DIR__.'/_bootstrap.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$u = new User($db); $u->fetch(0, 'caissier');
foreach (array('unvalidate', 'reopen') as $sp) {
  $o = $db->fetch_object($db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='facture' AND perms='invoice_advance' AND subperms='$sp' AND entity=".$conf->entity));
  ($u->delrights($o->id) >= 0) ? ok("Droit retiré : facture->invoice_advance->$sp") : ko("delrights $sp", $u);
}
