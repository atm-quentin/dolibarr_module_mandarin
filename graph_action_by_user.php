<?php

require('config.php');
dol_include_once('/core/class/html.form.class.php');

if (!$user->rights->mandarin->graph->action_by_user) accessforbidden();
$langs->load('mandarin@mandarin');

$userid = GETPOST('userid');
if($userid != 0) $userdefault = $userid;
elseif(user_est_responsable_hierarchique()) $userid = $user->id;

// Begin of page
llxHeader('', $langs->trans('mandarinTitleGraphEventByUser'), '');

print dol_get_fiche_head('RapportEvenementsParCommerciaux');
print_fiche_titre($langs->trans('RapportEvenementsParCommerciaux'));

print_form_filter($userid);

$TData = get_data_tab($userid);
draw_table($TData, get_list_id_user($TData), get_tab_label_action_comm());

print '<br />';
draw_graphique($TData, get_tab_label_action_comm());

llxFooter();

function print_form_filter($userid) {
	
	global $db, $langs;
	
	$langs->load('users');
	
	$form = new Form($db);
	
	print '<form name="filter" methode="GET" action="'.$_SERVER['PHP_SELF'].'">';
	
	print $langs->trans('HierarchicalResponsible');
	
	print $form->select_dolusers($userid, 'userid', 1, '', 0, '', '', 0, 0, 0, '', 0, '', '', 1);
	
	print '<br /><br />';
	
	$date_deb = explode('/', $_REQUEST['date_deb']);
	$date_deb = implode('/', array_reverse($date_deb));
	$date_fin = explode('/', $_REQUEST['date_fin']);
	$date_fin = implode('/', array_reverse($date_fin));
	
	print 'Du ';
	$form->select_date(strtotime($date_deb), 'date_deb');
	print 'Au ';
	$form->select_date(strtotime($date_fin), 'date_fin');
	
	print '<input type="SUBMIT" class="butAction" value="Filtrer" />';
	
	print '</form>';
	
	print '<br />';
	
}

function user_est_responsable_hierarchique() {
	
	global $db, $user;
	
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE fk_user = '.$user->id;
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);
	
	return $res->rowid > 0;
	
}

function get_data_tab($userid) {
	
	global $db;
	
	$TData = array();
	
	$sql = 'SELECT u.rowid, c.code, COUNT(*) as nb_events
			FROM llx_user u
			LEFT JOIN llx_actioncomm a ON (a.fk_user_action = u.rowid)
			LEFT JOIN llx_c_actioncomm c ON (c.id = a.fk_action)
			WHERE u.rowid > 1
			AND u.statut = 1';

	if(!empty($_REQUEST['date_deb'])) $sql.= ' AND a.datep >= "'.$_REQUEST['date_debyear'].'-'.$_REQUEST['date_debmonth'].'-'.$_REQUEST['date_debday'].' 00:00:00"';
	if(!empty($_REQUEST['date_fin'])) $sql.= ' AND a.datep <= "'.$_REQUEST['date_finyear'].'-'.$_REQUEST['date_finmonth'].'-'.$_REQUEST['date_finday'].' 23:59:59"';
	if($userid > 0) $sql.= ' AND u.fk_user = '.$userid;
	$sql.= ' AND a.code NOT IN ("AC_OTH_AUTO")
			GROUP BY u.rowid, c.code';
	
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)){
		$TData[$res->code][$res->rowid] = $res->nb_events;
	}
	return $TData;
	
}

function get_tab_label_action_comm() {
	
	global $db, $langs;
	
	$TLabel = array();
	
	$sql = 'SELECT code, libelle FROM '.MAIN_DB_PREFIX.'c_actioncomm';
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)) {
		$key=$langs->trans("Action".strtoupper($res->code));
        $label=($res->code && $key != "Action".strtoupper($res->code)?$key:$res->libelle);
		$TLabel[$res->code] = $label;
	}
	
	return $TLabel;
	
}

function get_list_id_user(&$TData) {
	
	global $db;
	
	$TIDUser = array();
	
	foreach($TData as $type_event=>$TDonnes) {
		$TIDUser = array_merge(array_keys($TDonnes), $TIDUser);
	}
	$TIDUser = array_unique($TIDUser);
	
	ksort($TIDUser);
	
	return $TIDUser;
	
}

function draw_table(&$TData, &$TIDUser, &$TLabelActionComm) {
	
	global $db, $langs;
	
	/*pre($TData,true);
	pre($TIDUser,true);
	pre($TLabelActionComm,true);*/
	
	$langs->load('agenda');
	
	print '<table class="noborder" width="100%">';
	
	$TTypeEvents = array_keys($TData);
	asort($TTypeEvents);
	
	print '<tr class="liste_titre">';
	print '<td>';
	print $langs->trans('Users'). ' / '.$langs->trans('EventType');
	print '</td>';
	
	foreach($TTypeEvents as $type) print '<td>'.$TLabelActionComm[$type].'</td>';
	
	print '<td>';
	print 'Total';
	print '</td>';
	print '</tr>';
	
	$u = new User($db);
	
	$class = array('pair', 'impair');
	$var = true;
	
	$TNBTotalType = array(); // Contient le nombre d'occurences pour chaque type d'événement, tout user confondu
	foreach($TIDUser as $id_user) {
		
		print '<tr class="'.$class[$var].'">';
		print '<td>';
		$u->fetch($id_user);
		print $u->getNomUrl(1);
		print '</td>';
		
		$nb_total_user = 0; // Contient le nombre d'occurences pour chaque user, tout type d'événement confondu
		foreach($TTypeEvents as $type) {
			print '<td>';
			print '<a href="'.dol_buildpath('/comm/action/listactions.php?actioncode='.$type.'&usertodo='.$id_user, 1).'">'.$TData[$type][$id_user].'</a>';
			print '</td>';
			$nb_total_user+=$TData[$type][$id_user];
			$TNBTotalType[$type]+=$TData[$type][$id_user];
		}
		
		print '<td>';
		print '<a href="'.dol_buildpath('/comm/action/listactions.php?&usertodo='.$id_user, 1).'">'.$nb_total_user.'</a>';
		print '</td>';
		print '</tr>';
		
		$var = !$var;
		
	}
	
	print '<tr class="liste_total">';
	print '<td>';
	print 'Total';
	print '</td>';
	foreach($TTypeEvents as $type) print '<td><a href="'.dol_buildpath('/comm/action/listactions.php?actioncode='.$type.'&usertodo=-1', 1).'">'.$TNBTotalType[$type].'</a></td>';
	print '<td>'.array_sum($TNBTotalType).'</td>';
	print '</tr>';
	
	print '</table>';
	
}

function draw_graphique(&$TData, &$TabTrad) {
	
	global $langs;
	
	$PDOdb = new TPDOdb;
	
	$TSum = array();

	foreach($TData as $code=>$Tab) $TSum[] = array($TabTrad[$code], array_sum($Tab));

	$listeview = new TListviewTBS('graphProjectByType');
	
	print $listeview->renderArray($PDOdb, $TSum
		,array(
			'type' => 'chart'
			,'chartType' => 'PieChart'
			,'liste'=>array(
				'titre'=>$langs->transnoentitiesnoconv('titleGraphEventByUser')
			)
		)
	);
	
}
