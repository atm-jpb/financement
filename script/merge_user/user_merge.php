<?php

ini_set('display_errors', true);

// Include Dolibarr environment
require_once '../../config.php';

$debug = array_key_exists('debug', $_GET);

$PDOdb = new TPDOdb;

// This will be replaced by all users concerned
$TUser = array();
$f = fopen(__DIR__.'/mapping_logins.csv', 'r');

$i = 0;
while($line = fgetcsv($f,2048,';','"')) {
    $i++;
    if($i === 1) continue;  // Ignore header

    $TUser[] = array(
            'fk_user_used' => $line[0],
            'fk_user_trigramme' => $line[1]
    );
}

fclose($f);

// Get all tables
$TTable = get_tables($PDOdb);
$TRes = array();

foreach($TUser as $k => $TUserContent) {
    foreach ($TTable as $table) {
        // Deal only with table with entity column in it
        $column_name = has_user($PDOdb, $table);
        if(! empty($column_name)) {
            $sql = 'SELECT count(*) as nb FROM '.$table.' WHERE '.$column_name.' = '.$TUserContent['fk_user_used'];
            $PDOdb->Execute($sql);
            $PDOdb->Get_line();

            $nb = $PDOdb->Get_field('nb');
            $TRes[$k][] = array('table' => $table, 'records' => $nb);
        }
    }
}

/**
 * Action
 */
$TabAction = GETPOST('TTable', 'array');

$TReq = array();
if(!empty($TabAction)) {
	foreach ($TabAction as $k => $TAction) {
        foreach($TAction as $table => $action) {
            if($action == 'delete') {
                $TReq[$k][] = delete_record_with_user($PDOdb, $table, $TUser[$k]['fk_user_used']);
            }
            else if ($action == 'update') {
                $TReq[$k][] = update_record_with_user($PDOdb, $table, $TUser[$k]['fk_user_used'], $TUser[$k]['fk_user_trigramme']);
            }
        }
	}
}

/**
 * View
 */
llxHeader();

dol_fiche_head();

print '<form method="POST">';
print '<span>'.count($TRes[$k]).' Table(s) avec une colonne correspondant à "fk_user%"</span>';
print '<br />';

foreach($TUser as $k => $TUserContent) {
?>
    <br />
    <span>
        Utilisateur source : <strong><?php echo $TUserContent['fk_user_used']; ?></strong><br />
        Utilisateur destination : <strong><?php echo $TUserContent['fk_user_trigramme']; ?></strong>.
    </span>
    <br><br>

    <?php
    if(!empty($TReq[$k])) {
        echo 'Requêtes à exécuter :<hr>';
        echo '<span style="font-weight: bold;">';
        echo implode('<br>', $TReq[$k]);
        echo '</span>';
        echo '<hr>';
    }
    ?>

    <table class="liste">
        <tr class="liste_titre">
            <th style="width: 35%;">Table</th>
            <th style="width: 20%;">Nb records</th>
            <th style="width: 45%;">Action</th>
        </tr>
    <?php
    $var = true;
    foreach($TRes[$k] as $data) {
        if(empty($data['records'])) continue;
    ?>
        <tr <?php echo $bc[$var]; ?>>
            <td><?php echo $data['table']; ?></td>
            <td><?php echo $data['records']; ?></td>
            <td>
                <label for="Update[<?php echo $k; ?>][<?php echo $data['table']; ?>]">Update </label>
                <input type="radio" 
                       id="Update[<?php echo $k; ?>][<?php echo $data['table']; ?>]"
                       name="TTable[<?php echo $k; ?>][<?php echo $data['table']; ?>]"
                       value="update"
                       data-table="<?php echo $data['table']; ?>" />

                <label for="Delete[<?php echo $k; ?>][<?php echo $data['table']; ?>]">Delete </label>
                <input type="radio"
                       id="Delete[<?php echo $k; ?>][<?php echo $data['table']; ?>]"
                       name="TTable[<?php echo $k; ?>][<?php echo $data['table']; ?>]"
                       value="delete"
                       data-table="<?php echo $data['table']; ?>" />

                <label for="None[<?php echo $k; ?>][<?php echo $data['table']; ?>]">None </label>
                <input type="radio"
                       id="None[<?php echo $k; ?>][<?php echo $data['table']; ?>]"
                       name="TTable[<?php echo $k; ?>][<?php echo $data['table']; ?>]"
                       value="none"
                       checked="checked"
                       data-table="<?php echo $data['table']; ?>" />
            </td>
        </tr>
    <?php
        $var = !$var;
    }
    ?>
    </table>

<?php
}   // Fin du foreach sur le tableau des Users
?>
<div class="tabsAction">
    <input type="submit" value="Afficher les Requêtes" class="butAction" />
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

// Check if table has a "fk_user%" column
function has_user(&$PDOdb, $table) {
	$PDOdb->Execute("SHOW COLUMNS FROM ".$table);
	
	while($row = $PDOdb->Get_line()){
		if(preg_match('/fk_user[\_a-z]*/', $PDOdb->Get_field('Field'))) return $PDOdb->Get_field('Field');
	}
	
	return '';
}

// Delete all records in a table with a fk_user%
function delete_record_with_user(&$PDOdb, $table, $fk_user) {
    $column_name = has_user($PDOdb, $table);
	$sql = "DELETE FROM $table WHERE $column_name = $fk_user;";
	//$PDOdb->Execute($sql);
	return $sql;
}

// Update all records in a table with a fk_user%
// Use this JS cmd "$('input[type=radio][value=update][data-table!=llx_usergroup_user]').prop('checked', 'checked')" to check all update radio button
// except for 'llx_usergroup_user' table
function update_record_with_user(&$PDOdb, $table, $fk_user_source, $fk_user_target) {
    global $db;

    $column_name = has_user($PDOdb, $table);
	$sql = "UPDATE $table SET $column_name = $fk_user_target WHERE $column_name = $fk_user_source;";
	$PDOdb->Execute($sql);

	// Deactivate user
	$u = new User($db);
	$u->fetch($fk_user_source);
	$u->setstatus(0);

	return $sql;
}
