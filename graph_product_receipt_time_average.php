<?php

require('config.php');
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

if (!$user->rights->mandarin->graph->products_receipt_average) accessforbidden();
$langs->load('mandarin@mandarin');

$action = GETPOST('action');
$fk_soc = GETPOST('fk_soc');
$date_deb = GETPOST('date_deb');
$date_fin = GETPOST('date_fin');

switch($action) {
	
	case 'print_data':
		llxHeader('', $langs->trans('RapportTempsMoyenReceptionProduits'), '');
		print dol_get_fiche_head('RapportTempsMoyenReceptionProduits');
		print_fiche_titre($langs->trans('RapportTempsMoyenReceptionProduits'));
		print_form_filter($date_deb, $date_fin, $fk_soc);
		$TData = get_data_tab($date_deb, $date_fin, $fk_soc);
		if(!empty($TData)) draw_table($TData);
		break;
		
	case 'download_file':
		$TData = get_data_tab($date_deb, $date_fin, $fk_soc);
		download_file($TData);
		break;
	
	default:
		llxHeader('', $langs->trans('RapportTempsMoyenReceptionProduits'), '');
		print dol_get_fiche_head('RapportTempsMoyenReceptionProduits');
		print_fiche_titre($langs->trans('RapportTempsMoyenReceptionProduits'));
		print_form_filter($date_deb, $date_fin, $fk_soc);
	
}

llxFooter();

function print_form_filter($date_deb, $date_fin, $fk_soc) {
	
	global $db, $langs;
	
	$langs->load('users');
	
	$form = new Form($db);
	
	print '<form name="filter" methode="GET" action="'.$_SERVER['PHP_SELF'].'">';
	
	print '<input type="hidden" name="action" value="print_data" />';
	
	print $langs->trans('Supplier').'&nbsp;&nbsp;';
	print $form->select_company($fk_soc, 'fk_soc', ' fournisseur = 1', 1);
	
	print '<br /><br />';
	
	$date_deb_t = dol_mktime(0, 0, 0, GETPOST('date_debmonth'), GETPOST('date_debday'), GETPOST('date_debyear'));
	$date_fin_t = dol_mktime(0, 0, 0, GETPOST('date_finmonth'), GETPOST('date_finday'), GETPOST('date_finyear'));
	
	print 'Du&nbsp;&nbsp;';
	$form->select_date(empty($date_deb) ? false : $date_deb_t, 'date_deb', 0, 0, 0, '', 1, 1);
	print '<br />Au&nbsp;&nbsp;';
	$form->select_date(empty($date_fin) ? false : $date_fin_t, 'date_fin', 0, 0, 0, '', 1, 1);
	print '<br /><br />';
	
	print '<input type="SUBMIT" class="butAction" value="Filtrer" />';
	
	print '</form>';
	
	print '<br />';
	
}

function get_data_tab($date_deb, $date_fin, $fk_soc) {
	
	global $db;
	
	if(empty($date_deb) || empty($date_fin)){
		setEventMessage('Renseignez une date de début et de fin', 'warnings');
		return 0;
	}
	
	$TData = array();
	
	$sql = 'SELECT cfd.fk_product as id_prod, p.label as label_prod, DATEDIFF(cf.date_commande, rec.datec) as nb_days_diff
			FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet cfd
			INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseur cf ON(cf.rowid = cfd.fk_commande)
			LEFT JOIN '.MAIN_DB_PREFIX.'product p ON(p.rowid = cfd.fk_product)
			INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseur_dispatch rec ON (rec.fk_commandefourndet = cfd.rowid)
			WHERE cf.fk_statut >= 3';
	
	if($fk_soc > 0) $sql.= ' AND cf.fk_soc = '.$fk_soc;
	
	$sql.= ' AND cf.date_commande >= "'.$_REQUEST['date_debyear'].'-'.$_REQUEST['date_debmonth'].'-'.$_REQUEST['date_debday'].' 00:00:00"';
	$sql.= ' AND cf.date_commande <= "'.$_REQUEST['date_finyear'].'-'.$_REQUEST['date_finmonth'].'-'.$_REQUEST['date_finday'].' 23:59:59"';
	//echo $sql;exit;
	
	$resql = $db->query($sql);
	
	$TResql = array();
	while($res = $db->fetch_object($resql)) $TResql[$res->id_prod][] = abs($res->nb_days_diff);
	
	$TProdAverage = array();
	foreach($TResql as $id_prod=>$Tab) {
		$p = new Product($db);
		$p->fetch($id_prod);
		$TProdAverage[$id_prod] = array(
											'diff'=>(array_sum($Tab) / count($Tab))
											,'ref_prod'=>$p->ref
											,'get_nom_url'=>$p->getNomUrl(1)
											,'label_prod'=>$p->label
									    );
	}
	
	//var_dump($TProdAverage);exit;
	
	return $TProdAverage;
	
}

function draw_table(&$TData) {
	
	global $db, $langs;
	
	// Je ne fais pas de liste TBS parce que je vais devoir afficher le tableau en base 64 dans la dom pour le poster vers un fichier download.php pour télécharger un csv
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>Produit</td>';
	print '<td>Libellé</td>';
	print '<td>Temps moyen de réception (en jours)</td>';
	print '</tr>';
	
	foreach($TData as $Tab) {
		print '<tr>';
		print '<td>'.$Tab['get_nom_url'].'</td>';
		print '<td>'.$Tab['label_prod'].'</td>';
		print '<td>'.$Tab['diff'].'</td>';
		print '</tr>';
		
	}

	print '</table>';
	
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=download_file&type='.GETPOST('type');
	print '&fk_soc='.GETPOST('fk_soc');
	print '&date_deb='.GETPOST('date_deb').'&date_fin='.GETPOST('date_fin');
	print '&date_debyear='.GETPOST('date_debyear').'&date_debmonth='.GETPOST('date_debmonth').'&date_debday='.GETPOST('date_debday');
	print '&date_finyear='.GETPOST('date_finyear').'&date_finmonth='.GETPOST('date_finmonth').'&date_finday='.GETPOST('date_finday').'">';
	print 'Télécharger CSV';
	print '</a>';
	print '</div>';
	
}

function download_file(&$TData) {
	
	$name = 'receipt_time_'.$_REQUEST['date_debyear'].$_REQUEST['date_debmonth'].$_REQUEST['date_debday'].'_'
					 .$_REQUEST['date_finyear'].$_REQUEST['date_finmonth'].$_REQUEST['date_finday'].'.csv';
	$fname = sys_get_temp_dir().'/'.$name;
	$f = fopen($fname, 'w+');
	fputcsv($f, array('Produit', 'Libellé', 'Temps moyen de réception (en jours)'), ';');
	
	foreach($TData as $Tab) {
		
		fputcsv($f, array(
							  $Tab['ref_prod']
							  , $Tab['label_prod']
							  , $Tab['diff']
						  )
				, ';');
		
	}
	
	fclose($f);
	
	header('Content-Description: File Transfer');
    header('Content-Type: application/CSV');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fname));
    readfile($fname);
	exit;
	
}
