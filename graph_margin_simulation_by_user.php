<?php

require('config.php');
dol_include_once('/clibip/class/clibip.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/core/class/html.form.class.php');

if (!$user->rights->mandarin->graph->margin_simulation_by_user) accessforbidden();
$langs->load('mandarin@mandarin');

$userid = GETPOST('userid');
// Begin of page
llxHeader('', $langs->trans('linkMenuMarginSimulationByUserReportShort'), '');

print dol_get_fiche_head('linkMenuMarginSimulationByUserReportShort');
print_fiche_titre($langs->trans('linkMenuMarginSimulationByUserReportShort'));

print_form_filter($userid);

$TData = get_data_tab($user->id);
$TGraph = draw_table($TData);
print '<br />';
exit;
draw_graphique($TGraph);

llxFooter();

function print_form_filter($userid) {
	
	global $db, $langs;
	
	$langs->load('users');
	
	$form = new Form($db);
	
	print '<form name="filter" methode="GET" action="'.$_SERVER['PHP_SELF'].'">';
	
	print $langs->trans('User');
	
	print $form->select_dolusers($userid, 'userid', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 1);
	
	print '<br /><br />';
	
	print '<input type="SUBMIT" class="butAction" value="Filtrer" />';
	
	print '</form>';
	
	print '<br />';
	
}

function get_data_tab($userid=0) {
	
	global $db;
	
	$TData = array();
	
	$sql = getSqlForData($userid);
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)) $TData[] = $res;
	
	return $TData;
	
}

function getSqlForData($userid=0)
{
	$sql = 'SELECT DISTINCT s.fk_propal,p.fk_soc, soc.nom, p.fk_user_author, u.lastname,p.fk_statut 
			FROM `'.MAIN_DB_PREFIX.'simulation_clibip` s 
			LEFT JOIN  `'.MAIN_DB_PREFIX.'propal` p ON (s.fk_propal = p.rowid)
			LEFT JOIN  `'.MAIN_DB_PREFIX.'user` u ON (p.fk_user_author = u.rowid)
			LEFT JOIN  `'.MAIN_DB_PREFIX.'societe` soc ON (p.fk_soc = soc.rowid)';

	//if($userid > 0) $sql.= ' WHERE p.fk_user_author = '.$userid;
	
	return $sql;
}



function draw_table(&$TData) {
	
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
	print '<td>';	
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
			print '<td>';
			print price(round($clibip->getMargeAvecAchat($prop),2), 0, $langs, 1, 'MT', -1);
			print '</td>';
			$nb_total_margin += $clibip->getMargeAvecAchat($prop);
			
			
			switch($tda->fk_statut){
				case 0:
					$statut = $langs->trans("Draft");
					break;
				case 1:
					$statut = $langs->trans("Validated");
					break;
				case 2:
					$statut = $langs->trans("Signed");
					break;
				case 3:
					$statut = $langs->trans("Not Signed");
					break;
					
			}
			
			//Tableau pour graphe pour les brouillons
			if($tda->fk_statut == 0){
				$i=0;
				$exist = false;
				while($i<count($tabDraft)){
					if($u->lastname == $tabDraft[$i][0]){
						$tabDraft[$i][1] +=  $clibip->getMargeAvecAchat($prop);
						$exist = true;
						
					}
					$i++;
				}
				if(!$exist){
					$tabDraft[$i][0] = $u->lastname;
					$tabDraft[$i][1] = $clibip->getMargeAvecAchat($prop);
					
				}
			}else if($tda->fk_statut == 1){//Tableau pour graphe pour les validés
				$i=0;
				$exist = false;
				while($i<count($tabValidated)){
					if($u->lastname == $tabValidated[$i][0]){
						$tabValidated[$i][1] +=  $clibip->getMargeAvecAchat($prop);
						$exist = true;
						
					}
					$i++;
				}
				if(!$exist){
					$tabValidated[$i][0] = $u->lastname;
					$tabValidated[$i][1] = $clibip->getMargeAvecAchat($prop);
					
				}
			}
			else if($tda->fk_statut == 2){//Tableau pour graphe pour les signés
				$i=0;
				$exist = false;
				while($i<count($tabSigned)){
					if($u->lastname == $tabSigned[$i][0]){
						$tabSigned[$i][1] +=  $clibip->getMargeAvecAchat($prop);
						$exist = true;
						
					}
					$i++;
				}
				if(!$exist){
					$tabSigned[$i][0] = $u->lastname;
					$tabSigned[$i][1] = $clibip->getMargeAvecAchat($prop);
					
				}
			}
			
			print '<td>';
			print $statut;
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
		print '<td>';
		print price(round($nb_total_margin,2), 0, $langs, 1, 'MT', -1);
		print '</td>';
		print '<td>';
		
		print '</td>';
		
		print '</tr>';
		
		print '</table>';
		
		array_push($tabDraft,$tabValidated,$tabSigned);
		
		return $tabDraft;
		
	}

function draw_graphique(&$TData) {
	
	global $langs;
	
	$PDOdb = new TPDOdb;
	
	$TSum = array();

	$i = 0;
	while($i<){
		
	}
	
	$listeview = new TListviewTBS('graphProjectByType');
	
	print $listeview->renderArray($PDOdb, $TSum
		,array(
			'type' => 'chart'
			,'chartType' => 'PieChart'
			,'liste'=>array(
				'titre'=>$langs->transnoentitiesnoconv('titleGraphMarginByUserByStateProject')
			)
		)
	);
	
}
