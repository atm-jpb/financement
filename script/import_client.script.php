<?php
/* Copyright (C) 2012	  Maxime Kohlhaas		<maxime.kohlhaas@atm-consulting.fr>
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
 *      \file       script/import_client.script.php
 *		\ingroup    financement
 *      \brief      This file is an example for a command line script
 *					Initialy built by build_class_from_table on 2012-12-20 10:36
 */


dol_include_once("/societe/class/societe.class.php");

foreach ($currentFile as $i => $line) {
	$societe=new Societe($db);
	
	$dataline = str_getcsv($line, $delimiter, $enclosure);
	$data = array_combine($mapping, $dataline);
	
	foreach ($data as $key => $value) {
		$societe->{$key} = $value;
	}
	echo '<pre>';
	print_r($data);
	echo '</pre>';
	echo $eol;
	
	$id=$societe->create($user);
	if ($id < 0) { $error++; dol_print_error($db,$societe->error); }
	else print "Object created with id=".$id.$eol;
	
	// Example for inserting creating object in database
	/*
	dol_syslog($script_file." CREATE", LOG_DEBUG);
	$myobject->prop1='value_prop1';
	$myobject->prop2='value_prop2';
	$id=$myobject->create($user);
	if ($id < 0) { $error++; dol_print_error($db,$myobject->error); }
	else print "Object created with id=".$id."\n";
	*/
	
	// Example for reading object from database
	/*
	dol_syslog($script_file." FETCH", LOG_DEBUG);
	$result=$myobject->fetch($id);
	if ($result < 0) { $error; dol_print_error($db,$myobject->error); }
	else print "Object with id=".$id." loaded\n";
	*/
	
	// Example for updating object in database ($myobject must have been loaded by a fetch before)
	/*
	dol_syslog($script_file." UPDATE", LOG_DEBUG);
	$myobject->prop1='newvalue_prop1';
	$myobject->prop2='newvalue_prop2';
	$result=$myobject->update($user);
	if ($result < 0) { $error++; dol_print_error($db,$myobject->error); }
	else print "Object with id ".$myobject->id." updated\n";
	*/
	
	// Example for deleting object in database ($myobject must have been loaded by a fetch before)
	/*
	dol_syslog($script_file." DELETE", LOG_DEBUG);
	$result=$myobject->delete($user);
	if ($result < 0) { $error++; dol_print_error($db,$myobject->error); }
	else print "Object with id ".$myobject->id." deleted\n";
	*/
	
	
	// An example of a direct SQL read without using the fetch method
	/*
	$sql = "SELECT field1, field2";
	$sql.= " FROM ".MAIN_DB_PREFIX."c_pays";
	$sql.= " WHERE field3 = 'xxx'";
	$sql.= " ORDER BY field1 ASC";
	
	dol_syslog($script_file." sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;
		if ($num)
		{
			while ($i < $num)
			{
				$obj = $db->fetch_object($resql);
				if ($obj)
				{
					// You can use here results
					print $obj->field1;
					print $obj->field2;
				}
				$i++;
			}
		}
	}
	else
	{
		$error++;
		dol_print_error($db);
	}
	*/
	
	
	// -------------------- END OF YOUR CODE --------------------
	
	if (! $error)
	{
		$db->commit();
		print date('Y-m-d H:i:s').' : end ok'.$eol;
	}
	else
	{
		print date('Y-m-d H:i:s').' : error code='.$error.$eol;
		$db->rollback();
	}

}
?>
