<?php
require __DIR__.'/_bootstrap.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$u = new User($db); $u->fetch(0, 'caissier');
$o = $db->fetch_object($db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE module='societe' AND perms='client' AND subperms='voir' AND entity=".$conf->entity));
($o && $u->addrights($o->id) >= 0) ? ok("Droit ajouté : societe->client->voir (voir tous les clients)") : ko('addrights societe->client->voir', $u);
