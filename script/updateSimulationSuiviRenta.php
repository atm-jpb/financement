<?php

require '../config.php';
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/score.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

$fk_simu = GETPOST('fk_simu', 'int');

if(empty($fk_simu) || ! TSimulation::isExistingObject($fk_simu)) exit('No simulation found !');

$PDOdb = new TPDOdb;
$s = new TSimulation;
$s->load($PDOdb, $fk_simu);

$s->calculAiguillageSuivi($PDOdb);