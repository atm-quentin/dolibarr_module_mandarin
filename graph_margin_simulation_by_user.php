<?php

require('config.php');
dol_include_once('/clibip/class/clibip.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/core/class/html.formpropal.class.php');
dol_include_once('/core/class/html.formother.class.php');

if (!$user->rights->mandarin->graph->margin_simulation_by_user) accessforbidden();
$langs->load('mandarin@mandarin');

$userid = GETPOST('userid');
$object_statut=GETPOST('propal_statut');
$monthBeginning=GETPOST('monthBeginning');
$yearBeginning=GETPOST('yearBeginning');
$monthEnding=GETPOST('monthEnding');
$yearEnding=GETPOST('yearEnding');
// Begin of page
llxHeader('', $langs->trans('linkMenuMarginSimulationByUserReportShort'), '');

print dol_get_fiche_head('linkMenuMarginSimulationByUserReportShort');
print_fiche_titre($langs->trans('linkMenuMarginSimulationByUserReportShort'));

print_form_filter($userid,$object_statut);

$TData = get_data_tab($userid,$object_statut);
$mesTabs = new stdClass;
draw_table($TData, $mesTabs);
print '<br />';

draw_graphique($mesTabs);

llxFooter();

function print_form_filter($userid,$object_statut) {
	
	global $db, $langs;
	
	$langs->load('users');
	$langs->load('agenda');
	
	$form = new Form($db);
	$formPropal = new FormPropal($db);
	$formother = new FormOther($db);
	print '<form name="filter" methode="GET" action="'.$_SERVER['PHP_SELF'].'">';
	
	print $langs->trans('User');
	
	print $form->select_dolusers($userid, 'userid', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 1);
	print "\t";
	print '<br />';
	print '<br />';
	print $langs->trans('State');
	print "\t";
	print $formPropal->selectProposalStatus($object_statut,1);
	print "\t";
	print '<br />';
	print '<br />';
	
	
	
	
	$date_deb = explode('/', $_REQUEST['date_deb']);
	$date_deb = implode('/', array_reverse($date_deb));
	$date_fin = explode('/', $_REQUEST['date_fin']);
	$date_fin = implode('/', array_reverse($date_fin));
	
	print 'Du ';
	$form->select_date(strtotime($date_deb), 'date_deb');
	print 'Au ';
	$form->select_date(strtotime($date_fin), 'date_fin');
	print '<br />';	
	print '<br />';
	print '<input type="SUBMIT" class="butAction" value="Filtrer" />';
	
	print '</form>';
	
	print '<br />';
	
}

function get_data_tab($userid=0,$object_statut='') {
	
	global $db;
	
	$TData = array();
	
	$sql = getSqlForData($userid,$object_statut);
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)) $TData[] = $res;
	
	return $TData;
	
}

function getSqlForData($userid=0,$object_statut='')
{
	$sql = 'SELECT DISTINCT s.fk_propal,p.fk_soc, soc.nom, p.fk_user_author, u.lastname,p.fk_statut ,p.datec,p.date_valid,p.date_cloture
			FROM `'.MAIN_DB_PREFIX.'simulation_clibip` s 
			LEFT JOIN  `'.MAIN_DB_PREFIX.'propal` p ON (s.fk_propal = p.rowid)
			LEFT JOIN  `'.MAIN_DB_PREFIX.'user` u ON (p.fk_user_author = u.rowid)
			LEFT JOIN  `'.MAIN_DB_PREFIX.'societe` soc ON (p.fk_soc = soc.rowid)
			WHERE 1';
	
	if($userid > 0) $sql.= ' AND p.fk_user_author = '.$userid;
	if(($object_statut)!='') $sql.=' AND p.fk_statut='.$object_statut;

	if(!empty($_REQUEST['date_deb'])){
		
		 $dateBeginning= $_REQUEST['date_debyear'].''.$_REQUEST['date_debmonth'].''.$_REQUEST['date_debday'];
		$sql.= ' AND ((p.fk_statut=0 AND DATE_FORMAT(p.datec,\'%Y%m%d\')>='.$dateBeginning.' )';
		$sql.= ' OR (p.fk_statut=1 AND DATE_FORMAT(p.date_valid,\'%Y%m%d\')>='.$dateBeginning.')';
		$sql.= ' OR (p.fk_statut=2 AND DATE_FORMAT(p.date_cloture,\'%Y%m%d\')>='.$dateBeginning.'))';
	}
	if(!empty($_REQUEST['date_fin'])){
		$dateEnding = $_REQUEST['date_finyear'].''.$_REQUEST['date_finmonth'].''.$_REQUEST['date_finday'];
		$sql.= ' AND ((p.fk_statut=0 AND DATE_FORMAT(p.datec,\'%Y%m%d\')<='.$dateEnding.')';
		$sql.= ' OR (p.fk_statut=1 AND DATE_FORMAT(p.date_valid,\'%Y%m%d\')<='.$dateEnding.')';
		$sql.= ' OR (p.fk_statut=2 AND DATE_FORMAT(p.date_cloture,\'%Y%m%d\')<='.$dateEnding.'))';
		
	}
	return $sql;
}



function draw_table(&$TData, &$mesTabs) {
	
	global $db, $langs;
	
	$langs->load('agenda');

	print '<table class="noborder" width="100%">';
	
	$TFkStatutOpportunite = array_keys($TData);
	asort($TFkStatutOpportunite);
	
	print '<tr class="liste_titre">';
	print '<td>';
	print $langs->trans('User');
	print '</td>';
	
	// Rangement par pourcentage croissant
	
	print '<td>';	
	print $langs->trans('Customer');		
	print '</td>';
	print '<td >';	
	print $langs->trans('Simulation');		
	print '</td>';
	print '<td>';	
	print $langs->trans('Margin');		
	print '</td>';
	print '<td>';	
	print $langs->trans('Status');		
	print '</td>';
	print '</tr>';
	
	$u = new User($db);
	$soc = new Societe($db);
	
	
	
	$class = array('pair', 'impair');
	$var = true;
	$nb_total_sim = 0; //nb total de simulation
	$nb_total_margin = 0;
	
	$tabDraft = array();
	$tabValidated = array();
	$tabSigned = array();
	
	
	foreach($TData as $tda) {
		$clibip = new CliBip();
		if(!empty($tda->fk_user_author)){
			print '<tr class="'.$class[$var].'">';
			print '<td>';
			$u->fetch($tda->fk_user_author);
			print $u->getNomUrl(1);
			print '</td>';
			
			
			$soc->fetch($tda->fk_soc);
			
			print '<td>';
			print $soc->getNomUrl(1);
			print '</td>';
			$prop = new Propal($db);
			$prop->fetch($tda->fk_propal);
			$prop->fetch_optionals();
			print '<td>';
			print $prop->getNomUrl(1);
			print '</td>';
			$PDOdb = new TPDOdb;
			
			$clibip->loadByPropalAndNumAffaire($PDOdb, $prop, 1);
			$clibip->montant_solde_integre = 0;
			print '<td style="text-align: right;">';
			print price(round($clibip->getMargeAvecAchat($prop),2), 0, $langs, 1, 'MT', -1).' €';
			print '</td>';
			$nb_total_margin += round($clibip->getMargeAvecAchat($prop),2);

			//Tableau pour graphe pour les brouillons
			if($tda->fk_statut == 0){
				$i=0;
				$exist = false;
				while($i<count($tabDraft)){
					if($u->lastname.' '.$u->firstname == $tabDraft[$i][0]){
						$tabDraft[$i][1] +=  round($clibip->getMargeAvecAchat($prop),2);
						$exist = true;
						
					}
					$i++;
				}
				if(!$exist){
					$tabDraft[$i][0] = $u->lastname.' '.$u->firstname;
					$tabDraft[$i][1] =round( $clibip->getMargeAvecAchat($prop),2);
					
				}
			}else if($tda->fk_statut == 1){//Tableau pour graphe pour les validés
				$i=0;
				$exist = false;
				while($i<count($tabValidated)){
					if($u->lastname.' '.$u->firstname == $tabValidated[$i][0]){
						$tabValidated[$i][1] += round($clibip->getMargeAvecAchat($prop),2);
						$exist = true;
						
					}
					$i++;
				}
				if(!$exist){
					$tabValidated[$i][0] = $u->lastname.' '.$u->firstname;
					$tabValidated[$i][1] = round($clibip->getMargeAvecAchat($prop),2);
					
				}
			}
			else if($tda->fk_statut == 2){//Tableau pour graphe pour les signés
				$i=0;
				$exist = false;
				while($i<count($tabSigned)){
					if($u->lastname.' '.$u->firstname == $tabSigned[$i][0]){
						$tabSigned[$i][1] +=  round($clibip->getMargeAvecAchat($prop),2);
						$exist = true;
						
					}
					$i++;
				}
				if(!$exist){
					$tabSigned[$i][0] = $u->lastname.' '.$u->firstname;
					$tabSigned[$i][1] = round($clibip->getMargeAvecAchat($prop),2);
					
				}
			}
			
			print '<td align="right">';
			print $prop->LibStatut($tda->fk_statut,5);
			print '</td>';
			print '</tr>';
			$nb_total_sim++;
			$var = !$var;
		}
			
	}
		
		print '<tr class="liste_total">';
		print '<td>';
		print 'Total';
		print '</td>';
		print '<td>';
		print '</td>';
		print '<td>';
		print $nb_total_sim;
		print '</td>';
		print '<td align="right">';
		print price(round($nb_total_margin,2), 0, $langs, 1, 'MT', -1).'€';
		print '</td>';
		print '<td>';
		
		print '</td>';
		
		print '</tr>';
		
		print '</table>';
		
		$mesTabs->draft=$tabDraft;
		$mesTabs->validated=$tabValidated;
		$mesTabs->signed = $tabSigned;
		
		
	}

function draw_graphique(&$TData) {
	
	global $langs;
	
	$PDOdb = new TPDOdb;
	$i = 0;

	print '<table class="noborder" width="100%">';
	$listeview = new TListviewTBS('graphMarginByDraft');
	print '<tr >';
	print '<td width=width=33%>';
	print $listeview->renderArray($PDOdb, $TData->draft
		,array(
			'type' => 'chart'
			,'chartType' => 'PieChart'
			,'liste'=>array(
				'titre'=>$langs->transnoentitiesnoconv('titleGraphMarginByUserByDraftSimulation')
			)
		)
	);
	print '</td>';
	print '<td width=33%>';
	$listeview2 = new TListviewTBS('graphMarginByValidated');
	print $listeview2->renderArray($PDOdb, $TData->validated
		,array(
			'type' => 'chart'
			,'chartType' => 'PieChart'
			,'liste'=>array(
				'titre'=>$langs->transnoentitiesnoconv('titleGraphMarginByUserByValidatedSimulation')
			)
		)
	);
	print '</td>';
	print '<td width=33%>';
	$listeview3 = new TListviewTBS('graphMarginBySigned');
	print $listeview3->renderArray($PDOdb, $TData->signed
		,array(
			'type' => 'chart'
			,'chartType' => 'PieChart'
			,'liste'=>array(
				'titre'=>$langs->transnoentitiesnoconv('titleGraphMarginByUserBySignedSimulation')
			)
		)
	);
	print '</td>';
	print '</tr>';
	print "</table>";
}
