<?php
// Re-vérification (lecture seule) des tests 2, 4, 8 avec les préfixes réels de Dolibarr pour un pays ≠ FR.
require __DIR__.'/_bootstrap.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
function check($s, $a, $o, $c) { echo str_pad('['.($c ? 'OK' : 'ECHEC').']', 9)."$s\n         attendu : $a\n         obtenu  : $o\n"; }
$pr = new Propal($db); $pr->fetch(0, 'PR2611-0001');
$co = new Commande($db); $co->fetch(0, 'SO2611-0001');
check('2. (re-vérif) Devis validé → commande', 'Devis signé ; commande SO… validée liée au devis, 3 lignes, même HT',
  "Devis {$pr->ref} « ".$pr->getLibStatut(0)." » ; commande {$co->ref} « ".$co->getLibStatut(0).' », '.count($co->lines).' lignes, HT '.price2num($co->total_ht).', lien devis→commande : '.($co->fetchObjectLinked() !== null && !empty($co->linkedObjectsIds['propal']) ? 'oui' : 'non'),
  $pr->statut == Propal::STATUS_SIGNED && $co->statut == Commande::STATUS_VALIDATED && count($co->lines) == 3 && abs($co->total_ht - $pr->total_ht) < 0.01 && !empty($co->linkedObjectsIds['propal']));
$fa = new Facture($db); $fa->fetch(0, 'IN2611-0001');
$pdf = $conf->facture->multidir_output[$conf->entity].'/IN2611-0001/IN2611-0001.pdf';
check('4. (re-vérif) Facture depuis commande + PDF', 'IN2611-0001 validée liée à la commande, TTC 1359.90, PDF présent',
  "{$fa->ref} TTC ".price2num($fa->total_ttc).', PDF '.(is_file($pdf) ? filesize($pdf).' o' : 'ABSENT').', lien commande : '.($fa->fetchObjectLinked() !== null && !empty($fa->linkedObjectsIds['commande']) ? 'oui' : 'non'),
  is_file($pdf) && abs($fa->total_ttc - 1359.90) < 0.01 && !empty($fa->linkedObjectsIds['commande']));
$seq = function ($p) use ($db) { $o = array(); $r = $db->query("SELECT ref, type FROM ".MAIN_DB_PREFIX."facture WHERE ref LIKE '".$p."____-%' ORDER BY ref"); while ($x = $db->fetch_object($r)) $o[] = $x->ref; return $o; };
$contig = function ($refs) { foreach ($refs as $i => $r) if ((int) substr($r, -4) != $i + 1) return false; return count($refs) > 0; };
$ins = $seq('IN'); $ics = $seq('IC'); $prov = $db->fetch_object($db->query("SELECT COUNT(*) n FROM ".MAIN_DB_PREFIX."facture WHERE ref LIKE '(PROV%'"))->n;
$av = new Facture($db); $av->fetch(0, 'IC2611-0001');
check('8. (re-vérif) Avoir + numérotation sans trou', 'Avoir IC…-0001 lié à IN2611-0001, TTC -108 ; IN = 0001, 0002 sans trou ; IC = 0001 ; aucun brouillon restant',
  "Avoir {$av->ref} source=".($av->fk_facture_source == $fa->id ? $fa->ref : $av->fk_facture_source).' TTC '.price2num($av->total_ttc).' ; IN = '.implode(', ', $ins).' ; IC = '.implode(', ', $ics)." ; brouillons : $prov",
  $av->fk_facture_source == $fa->id && abs($av->total_ttc + 108) < 0.01 && $contig($ins) && count($ins) == 2 && $contig($ics) && $prov == 0);
