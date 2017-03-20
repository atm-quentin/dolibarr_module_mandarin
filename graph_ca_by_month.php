<?php

	require 'config.php';

	if (!$user->rights->mandarin->graph->ca_client_month) accessforbidden();

	llxHeader('', $langs->trans('CAClientMonth'), '');

	dol_fiche_head();

	$PDOdb=new TPDOdb;
	//$PDOdb->debug=true;

	$year = (int)GETPOST('year');
	if(empty($year))$year=(int)date('Y');

	$payed=GETPOST('payed');
	$invoiced=GETPOST('invoiced');

	$TYear = $TMonth = $ColFormat= $ColTotal =array();
	$y = (int)date('Y');
	for($i = $y - 10 ; $i<$y+3; $i++){ $TYear[$i] = $i; }
	for($i = 1 ; $i<=12; $i++){
		$TMonth[$i] = $langs->trans('month'. date('M', strtotime(date('Y-'.$i.'-01'))) ).'-'.$year;
		$ColFormat[$TMonth[$i]]='number';
		$ColTotal[$TMonth[$i]]='sum';

	}
	$ColFormat['total'] = 'number';
	$ColTotal['total'] = 'sum';

	$mode = GETPOST('mode');

	$TData=array();

	if($mode == 'order') {
		//commande non brouillon par date de livraison
		$sql = "SELECT commande.fk_soc, SUM(commande.total_ht) as total, MONTH(commande.date_livraison) as 'month' FROM ".MAIN_DB_PREFIX."commande as commande
				INNER JOIN ".MAIN_DB_PREFIX."societe as soc ON soc.rowid=commande.fk_soc WHERE commande.fk_statut>0 AND YEAR(commande.date_livraison)=".$year."
						".(empty($invoiced)?'AND facture=0':"AND facture=1")."
				GROUP BY commande.fk_soc,soc.nom, MONTH(commande.date_livraison)
                ORDER BY soc.nom,MONTH(commande.date_livraison)";
	}
	else{
		//facture payée sur date de facturation
		$sql = "SELECT fact.fk_soc, SUM(fact.total) as total, MONTH(fact.datef) as 'month' FROM ".MAIN_DB_PREFIX."facture as fact
				INNER JOIN ".MAIN_DB_PREFIX."societe as soc ON soc.rowid=fact.fk_soc
						WHERE fk_statut>0 ".(empty($payed)?'':"AND paye=1")." AND YEAR(datef)=".$year."
								GROUP BY fact.fk_soc,soc.nom, MONTH(fact.datef)
								ORDER BY soc.nom,MONTH(fact.datef)";
	}

	$Tab = $PDOdb->ExecuteAsArray($sql);

	foreach($Tab as &$row) {

		if(!isset($TData[$row->fk_soc])) $TData[$row->fk_soc] = _init_line();

		$TData[$row->fk_soc][$TMonth[(int)$row->month]] = (float)$row->total;
		$TData[$row->fk_soc]['total'] += (float)$row->total;
	}

	_get_company_object($TData);
	//usort($TData, '_sort_company');

	?>
	<style type="text/css">
		*[field=total],tr.liste_total td {
			font-weight: bold;
		}
	</style>
	<?php
	$formCore=new TFormCore('auto','form2', 'get');

	$headsearch = $formCore->hidden('mode', $mode );
	$headsearch.= $formCore->combo($langs->trans('Year'), 'year', $TYear, $year);
	if (empty($mode)) {
		$headsearch.= $formCore->checkbox1($langs->trans('Paid'), 'payed', 1, $payed);
	} elseif ($mode=='order') {
		$headsearch.= $formCore->checkbox1($langs->trans('PropalStatusBilled'), 'invoiced', 1, $invoiced);
	}
	$headsearch.= $formCore->btsubmit($langs->trans('Ok'),'bt_ok');

	$listeview = new TListviewTBS('CAClientMonth');
	print $listeview->renderArray($PDOdb, $TData
		,array(

			'liste'=>array(
				'titre'=>$langs->transnoentitiesnoconv('CAClientMonth')
				,'head_search'=>$headsearch
			)
			,'type'=>$ColFormat
			,'title'=>array(
				'client' => $langs->transnoentitiesnoconv('Company')
				,'total' => $langs->transnoentitiesnoconv('Total')
				,'year' => $langs->transnoentitiesnoconv('Year')
			)
			,'math'=>$ColTotal
			,'export'=>array('CSV')

		)
	);

	$formCore->end();

	dol_fiche_end();

	llxFooter();

function _sort_company(&$a, &$b) {

	$r = strcasecmp($a->name, $b->name);

	return empty($r) ? 0 : $r / abs($r);
}

function _get_company_object(&$TRender){
	global $db,$conf,$langs,$user;
	dol_include_once('/societe/class/societe.class.php');

	foreach($TRender as $fk_soc=>&$line) {

		$s=new Societe($db);
		$s->fetch($fk_soc);

		$line['client'] = $s->getNomUrl();

	}

}

function _init_line() {
	global $TMonth;
	$Tab=array('client'=>'');

	for($i = 1; $i<=12;$i++) {

		$Tab[$TMonth[$i]] = 0;

	}

	$Tab['total'] = 0;

	return $Tab;


}
