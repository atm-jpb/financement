<?php
/* Copyright (C) 2012      Maxime Kohlhaas        <maxime@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */


/**
 *	\file       financement/simulateur.php
 *	\ingroup    financement
 *	\brief      Outil de calculateur et de simulateur
 */


require('config.php');

dol_include_once('/financement/class/score.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

if (!($user->rights->financement->score->read))
{
	accessforbidden();
}

$langs->load('financement@financement');
$score=new TScore;
$ATMdb = new Tdb;
$tbs = new TTemplateTBS;

$mesg = '';
$error=false;

$action = GETPOST('action');
if(!empty($_REQUEST['cancel'])) {
	if(!empty($_REQUEST['fk_soc'])) { header('Location: ?socid='.$_REQUEST['fk_soc']); exit; } // Retour sur client sinon
	header('Location: '.$_SERVER['PHP_SELF']); exit;
}

if(!empty($action)) {
	switch($action) {
		case 'list':
			_liste($ATMdb, $score);
			break;
		case 'add':
		case 'new':
			
			$score->set_values($_REQUEST);
			_fiche($ATMdb, $score,'edit');
			
			break;
		case 'edit'	:
		
			$score->load($ATMdb, $_REQUEST['id']);
			
			_fiche($ATMdb, $score,'edit');
			break;
			
		case 'save':
			$score->load($ATMdb, $_REQUEST['id']);
			$score->set_values($_REQUEST);
			
			$score->save($ATMdb);
			
			_liste($ATMdb, $score);
			
			break;
		
			
		case 'delete':
			$score->load($ATMdb, $_REQUEST['id']);
			$score->delete($ATMdb);
			
			?>
			<script language="javascript">
				document.location.href="?socid=<?= $_REQUEST['socid'] ?>&delete_ok=1";
			</script>
			<?
			
			break;
	}
	
}
elseif(isset($_REQUEST['id'])) {
	$score->load($ATMdb, $_REQUEST['id']);
	
	_fiche($ATMdb, $score, 'view');global $mesg, $error;
}
else {
	 _liste($ATMdb, $score);
}

llxFooter();

/*
 * View
 */

function _liste(&$ATMdb, &$score) {
	global $langs, $db, $conf, $user;
	
	llxHeader('','Scores');
	
	$societe = new Societe($db);
	$societe->fetch($_REQUEST['socid']);
	
	$r = new TSSRenderControler($score);
	
	$sql = "SELECT s.rowid as 'ID', s.score as 'Score', s.encours_conseille as 'Encours conseillé', s.date_score as 'Date du score', u.login as 'Utilisateur',";
	$sql.= " '' as 'Actions'";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_score as s";
	$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON s.fk_user_author = u.rowid';
	$sql.= " WHERE fk_soc = ".$_REQUEST['socid'];
	
	// Affichage résumé client
	$formDoli = new Form($db);
	
	$TBS=new TTemplateTBS();

	print $TBS->render('./tpl/client_entete.tpl.php'
		,array(
			
		)
		,array(
			'client'=>array(
				'dolibarr_societe_head'=>dol_get_fiche_head(societe_prepare_head($societe), 'scores', $langs->trans("ThirdParty"),0,'company')
				,'showrefnav'=>$formDoli->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom')
				,'idprof1'=>$societe->idprof1
				,'adresse'=>$societe->address
				,'cpville'=>$societe->zip.($societe->zip && $societe->town ? " / ":"").$societe->town
				,'pays'=>picto_from_langcode($societe->country_code).' '.$societe->country
			)
			,'view'=>array(
				'mode'=>'view'
			)
		)
	);
	
	$THide = array('ID');
	
	$TOrder = array('Date du score'=>'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
	
	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>'10'
		)
		,'link'=>array(
			'ID'=>'<a href="?id=@ID@">@val@</a>'
			,'Utilisateur'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id=@fk_user_author@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
			,'Actions'=>'<a href="'.$_SERVER["PHP_SELF"].'?action=edit&socid='.$societe->id.'&id=@ID@">'.img_edit().'</a> <a href="'.$_SERVER["PHP_SELF"].'?action=delete&socid='.$societe->id.'&id=@ID@">'.img_delete().'</a>'
		)
		,'hide'=>$THide
		,'type'=>array('Date du score'=>'date', 'Encours conseillé'=>'money')
		,'liste'=>array(
			'titre'=>'Liste des scores'
			,'image'=>img_picto('','simul32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=>"Il n'y a aucun score à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
		)
		,'orderBy'=>$TOrder
	));
	
	if(isset($_REQUEST['socid'])) {
		?><div class="tabsAction"><a href="?action=new&fk_soc=<?=$_REQUEST['socid'] ?>" class="butAction">Nouveau score</a></div><?
	}
	
	llxFooter();
}

function _fiche(&$ATMdb, &$score, $mode) {
	global $db, $langs, $user, $conf;
	
	$societe = new Societe($db);
	$societe->fetch($score->fk_soc);
	
	llxHeader('',$langs->trans("Score"),'','','','',$extrajs);
	
	$formDoli = new Form($db);
	$form=new TFormCore($_SERVER['PHP_SELF'],'formscore','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $score->getId());
	echo $form->hidden('action', 'save');
	echo $form->hidden('fk_soc', $score->fk_soc);
	echo $form->hidden('socid', $score->fk_soc);
	echo $form->hidden('fk_user_author', $user->id);

	$TBS=new TTemplateTBS();
	
	print $TBS->render('./tpl/score.tpl.php'
		,array(
			
		)
		,array(
			'score'=>array(
				'titre'=>load_fiche_titre($score->getId() > 0 ? $langs->trans("EditScore") : $langs->trans("NewScore"),'','')
				,'id'=>$score->rowid
				,'fk_soc'=>$score->fk_soc
				,'score'=>$form->texte('', 'score', $score->score, 5)
				,'encours_conseille'=>$form->texte('', 'encours_conseille', $score->encours_conseille, 10)
				,'date_score'=>$form->calendrier('', 'date_score', $score->get_date('date_score'), 10)
				
				,'user'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id='.$score->fk_user_author.'">'.img_picto('','object_user.png', '', 0).' '.$score->user->login.'</a>'
				,'bt_cancel'=>$form->btsubmit('Annuler', 'cancel')
				,'bt_save'=>$form->btsubmit('Valider', 'save')
			)
			,'client'=>array(
				'dolibarr_societe_head'=>dol_get_fiche_head(societe_prepare_head($societe), 'scores', $langs->trans("ThirdParty"),0,'company')
				,'showrefnav'=>$formDoli->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom')
				,'idprof1'=>$societe->idprof1
				,'adresse'=>$societe->address
				,'cpville'=>$societe->zip.($societe->zip && $societe->town ? " / ":"").$societe->town
				,'pays'=>picto_from_langcode($societe->country_code).' '.$societe->country
			)
			,'view'=>array(
				'mode'=>$mode
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}

?>