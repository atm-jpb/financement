<?php

ini_set('display_errors', true);

// Include Dolibarr environment
require_once '../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// R&eacute;cup&eacute;ration des paramètres
$entitySource = GETPOST('entity_source');
$entityTarget = GETPOST('entity_target');

if(empty($entitySource) || empty($entityTarget)) {
	echo 'Ce script n&eacute;cessite 2 paramètres : "entity_source" et "entity_target"';
	exit();
}

$PDOdb = new TPDOdb();

// Chargement des entit&eacute;s
$e1 = new DaoMulticompany($db);
$e1->fetch($entitySource);
//pre($e1,true);
$e2 = new DaoMulticompany($db);
$e2->fetch($entityTarget);

// Get all tables
$TTable = get_tables($PDOdb);

$TRes = array();
foreach ($TTable as $table) {
	// Deal only with table with entity column in it
	if(has_entity($PDOdb, $table)) {
		$sql = "SELECT count(*) as nb FROM ".$table." WHERE entity = ".$entitySource;
		$PDOdb->Execute($sql);
		$PDOdb->Get_line();
		$nb = $PDOdb->Get_field('nb');
		$TRes[] = array('table' => $table, 'records' => $nb);
	}
}

/**
 * Action
 */
$TabAction = GETPOST('TTable', 'array');
$TReq = array();
if(!empty($TabAction)) {
	foreach ($TabAction as $table => $action) {
        if($table == 'documents') {
		    updateDocuments($action, $entitySource, $entityTarget);
        }
        else if($table == 'confMulticompany') {
            updateMulticompanyConf($action, $entitySource, $entityTarget);
        }
		else if($action == 'delete') {
			$TReq[] = delete_record_with_entity($PDOdb, $table, $entitySource);
		}
		else if ($action == 'move') {
            $TReq[] = update_record_with_entity($PDOdb, $table, $entitySource, $entityTarget);
		}
	}
}

/**
 * View
 */
llxHeader();

dol_fiche_head(array(), '0', '', -2);

?>
Tous les &eacute;l&eacute;ments de l'entit&eacute; <strong><?php echo $entitySource . ' - ' .$e1->label ?></strong>
vont être supprim&eacute;s ou transf&eacute;r&eacute;s sur l'entit&eacute; <strong><?php echo $entityTarget . ' - ' .$e2->label ?></strong>.
<br><br>

<form method="POST">
<input type="hidden" name="entity_source" value="<?php echo $entitySource ?>" />
<input type="hidden" name="entity_target" value="<?php echo $entityTarget ?>" />

<?php
if(!empty($TReq)) {
	echo 'Requêtes à exécuter :<hr>';
	echo implode('<br>', $TReq);
	echo '<hr>';
}
?>
<table class="liste">
    <tr class="liste_titre">
        <th>Elements</th>
        <th>Actions</th>
    </tr>
    <tr>
        <td>Documents</td>
        <td>
            <label>Move <input type="radio" name="TTable[documents]" value="move" /></label>
            <label>Delete <input type="radio" name="TTable[documents]" value="delete" /></label>
            <label>None <input type="radio" name="TTable[documents]" value="none" checked="checked" /></label>
        </td>
    </tr>
    <tr>
        <td>Conf MULTICOMPANY_USER_GROUP_ENTITY</td>
        <td>
            <label>Move <input type="radio" name="TTable[confMulticompany]" value="move" /></label>
            <label>Delete <input type="radio" name="TTable[confMulticompany]" value="delete" /></label>
            <label>None <input type="radio" name="TTable[confMulticompany]" value="none" checked="checked" /></label>
        </td>
    </tr>
</table>
<br/><br/>

<table class="liste">
	<tr class="liste_titre">
		<th>Table</th>
		<th>Nb records</th>
		<th>Action</th>
	</tr>
<?php
$var = true;
foreach($TRes as $data) {
	if(empty($data['records'])) continue;
?>
	<tr <?php echo $bc[$var] ?>>
		<td><?php echo $data['table'] ?></td>
		<td><?php echo $data['records'] ?></td>
		<td>
			Move <input type="radio" name="TTable[<?php echo $data['table'] ?>]" value="move" />
			Delete <input type="radio" name="TTable[<?php echo $data['table'] ?>]" value="delete" />
			None <input type="radio" name="TTable[<?php echo $data['table'] ?>]" value="none" checked="checked" />
		</td>
	</tr>
<?php
	$var = !$var;
}
?>
</table>

<div class="tabsAction">
	<input type="submit" value="Fusionner" class="butAction" />
</div>

</form>
<?php

dol_fiche_end();

llxFooter();


// Get list of all tables in database
function get_tables(&$PDOdb) {
	$Tab=array();
	
	$PDOdb->Execute("SHOW TABLES");
	
	while($row = $PDOdb->Get_line()){
		$nom = current($row);
		$Tab[] = $nom;
	}
	
	return $Tab;
}

// Check if table has an "entity" column
function has_entity(&$PDOdb, $table) {
	$PDOdb->Execute("SHOW COLUMNS FROM ".$table);
	
	while($row = $PDOdb->Get_line()){
		if($PDOdb->Get_field('Field') == 'entity') return true;
	}
	
	return false;
}

// Delete all records in a table with an entity
function delete_record_with_entity(&$PDOdb, $table, $entitySource) {
	$sql = "DELETE FROM $table WHERE entity = $entitySource;";
	//$PDOdb->Execute($sql);
	return $sql;
}

// Update all records in a table with an entity
function update_record_with_entity(&$PDOdb, $table, $entitySource, $entityTarget) {
    $sql = '';
    $TUpdateRef = array(
        MAIN_DB_PREFIX.'facture',
        MAIN_DB_PREFIX.'facture_fourn',
        MAIN_DB_PREFIX.'product'
    );
    if(in_array($table, $TUpdateRef)) {
        $sql .= 'UPDATE '.$table." SET ref = concat(entity, '-', ref) WHERE entity = ".$entitySource.';<br/>';
    }
	$sql .= "UPDATE $table SET entity = $entityTarget WHERE entity = $entitySource;";
	//$PDOdb->Execute($sql);
	return $sql;
}

function updateMulticompanyConf($action, $entitySource, $entityTarget) {
    global $conf;

    $Tab = unserialize($conf->global->MULTICOMPANY_USER_GROUP_ENTITY);

    foreach($Tab as $k => $TData) {
        if($TData['entity_id'] == $entitySource) {
            if($action == 'move') $TData['entity_id'] = $entityTarget;
            else if($action == 'delete') unset($Tab[$k]);
        }
    }

//    $conf->global->MULTICOMPANY_USER_GROUP_ENTITY = serialize($Tab);
}

function updateDocuments($action, $entitySource, $entityTarget) {
    if($action == 'delete') {
//        if($entitySource != 1) dol_delete_dir_recursive(DOL_DATA_ROOT.'/'.$entitySource.'/');
    }
    else if($action == 'move') {
        $sourcePath = $targetPath = DOL_DATA_ROOT.'/';
        if($entitySource != 1) $sourcePath .= $entitySource.'/';
        if($entityTarget != 1) $targetPath .= $entityTarget.'/';

        $TDir = dol_dir_list($sourcePath, 'directories');
        foreach($TDir as $TData) {
            $cmd = 'cp -r '.$TData['fullname'].' '.$targetPath.';rm -r '.$TData['fullname'];
//            exec($cmd);
        }
    }
}
