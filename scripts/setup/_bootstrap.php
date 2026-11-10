<?php
// Amorçage CLI : charge Dolibarr sans modifier ses sources. Exécuté DANS le conteneur, en www-data.
if (php_sapi_name() !== 'cli') { exit(1); }
define('NOSESSION', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
global $db, $conf, $langs, $user, $mysoc;
$login = getenv('DOLI_SETUP_LOGIN') ?: 'admin';
if ($user->fetch(0, $login) <= 0) { fwrite(STDERR, "Utilisateur $login introuvable\n"); exit(2); }
$user->loadRights();
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'companies', 'bills', 'products', 'orders', 'propal', 'sendings', 'stocks'));
$GLOBALS['NB_KO'] = 0;
function ok($m) { echo "[OK]    $m\n"; }
function ko($m, $o = null) { $GLOBALS['NB_KO']++; echo "[ECHEC] $m".($o ? ' :: '.$o->error.' '.implode(' | ', (array) $o->errors) : '')."\n"; }
function info($m) { echo "        $m\n"; }
function setc($name, $val) { global $db, $conf; return dolibarr_set_const($db, $name, $val, 'chaine', 0, '', $conf->entity); }
