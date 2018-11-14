<?php

require 'config.php';
// spl_autoload_register(function ($class_name) { dol_include_once('/financement/class/'.$class_name.'.class.php'); });
dol_include_once('/financement/class/dossier.class.php');   // à voir si on en a besoin
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/financement/class/simulation.class.php');
//dol_include_once('/financement/class/score.class.php');
//dol_include_once('/financement/class/affaire.class.php');
//dol_include_once('/financement/class/grille.class.php');

$code_artis = GETPOST('code_artis');
if(empty($code_artis)) exit('empty code_artis!');

$fk_simu = GETPOST('fk_simu');
$entity = GETPOST('fk_entity');
if(empty($entity)) $entity = $conf->entity;

$PDOdb = new TPDOdb;
$simu = new TSimulation;
$soc = new Societe($db);

if(! empty($fk_simu)) {
    $simu->load($PDOdb, $db, $fk_simu, false);
    if($simu->rowid > 0) {
        // Fetch reussi : On va sur la fiche en mode edit
        // TODO: Trouver un moyen de pré-remplir le formulaire
        $url_to_go = dol_buildpath('/financement/simulation.php', 2);
        $url_to_go.= '?id='.$fk_simu;
        $url_to_go.= '&action=edit';
        header('Location: '.$url_to_go);
        exit;
    }
    else {
        // Simulation non présente sur LeaseBoard
        // On regarde si le Tiers a d'autres simulations
        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
        $sql.= " WHERE code_client='".$db->escape($code_artis)."'";
        $sql.= ' AND entity='.$db->escape($entity);

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        if($obj = $db->fetch_object($resql)) {
            $TSimu = $simu->load_by_soc($PDOdb, $db, $obj->rowid);
            $nb_simu = count($TSimu);

            $url = dol_buildpath('/financement/simulation.php', 2);
            if(empty($nb_simu)) {
                // NEW
                $url.= '?action=new';
            }
            else if($nb_simu == 1) {
                // EDIT
                $simulation = array_shift($TSimu);
                $url.= '?action=edit';
                $url.= '&id='.$simulation->rowid;
                
                ?>
                <form id="to_submit">
                    <input type="hidden" name="id" value="<?php echo $simulation->rowid; ?>" />
                    <input type="hidden" name="action" value="edit" />
                    
                    <!-- TOOD: Définir les champs à envoyer (Penser à aussi les remplir dans la fonction set_values_from_cristal()) -->
                </form>
                <?php
            }
            else {
                // LIST
                $url = '?socid='.$obj->rowid;
            }
            header('Location: '.$url);
            exit;
        }
    }
}
else {
//    header('Location: '.dol_buildpath('/comm/list.php', 2).'?search_code='.$db->escape($code_artis));
//    exit;
}

function _has_valid_simulations(&$PDOdb, $socid){
    global $db;

    $simu = new TSimulation;
    $TSimulations = $simu->load_by_soc($PDOdb, $db, $socid);
    
    foreach ($TSimulations as $simulation){
        if($simulation->date_validite > dol_now()) {
            return true;
        }
    }
        return false;
    
}