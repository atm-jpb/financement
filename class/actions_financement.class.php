<?php

class ActionsFinancement
{
    protected $db;
    public $dao;
    public $error;
    public $errors = array();
    public $resprints = '';

    /**
     * Constructor
     *
     * @param DoliDB $db
     */
    public function __construct($db) {
        $this->db = $db;
        $this->error = 0;
        $this->errors = array();
    }

    function doActions($parameters, &$object, &$action, $hookmanager) {
        global $user;

        if(in_array('propalcard', explode(':', $parameters['context']))) {
            // Nouvelle regle, uniquement accessible aux admin
            // 2017.07.27 MKO : activation de l'accès aux autres
            //if(empty($user->rights->financement->admin->write)) accessforbidden();

            if($object->fin_validite < strtotime(date('Y-m-d')) && empty($user->rights->financement->integrale->see_past_propal)) {
                dol_include_once('/core/lib/security.lib.php');
                $mess = 'Vous ne pouvez consulter une proposition dont la date de fin de validité est dépassée.';
                accessforbidden($mess, 1);
            }
        }

        return 0;
    }

    public function addSearchEntry($parameters, &$object, &$action, $hookmanager) {
        $TRes = array(
            'searchintofinancementdossier' => array(
                'position' => 12,
                'text' => img_picto('', 'object_financementico@financement').' Dossiers',
                'url' => dol_buildpath('/financement/dossier_list.php', 1)
            ),
            'searchintofinancementsimulation' => array(
                'position' => 13,
                'text' => img_picto('', 'object_financementico@financement').' Simulations',
                'url' => dol_buildpath('/financement/simulation/list.php', 1)
            ),
            'searchintofinancementmatricule' => array(
                'position' => 14,
                'text' => img_object('','resource').' Matricules',
                'url' => dol_buildpath('/financement/simulation/simulation.php', 1).'?mode_search=search_matricule'
            )
        );

        $this->results = $TRes;
    }

//	function printSearchForm($parameters, &$object, &$action, $hookmanager) {
//		global $langs, $hookmanager;
//
//		 $res = printSearchForm(DOL_URL_ROOT.'/custom/financement/dossier_list.php', DOL_URL_ROOT.'/custom/financement/dossier_list.php', img_picto('',dol_buildpath('/financement/img/object_financeico.png', 1), '', true).' '.$langs->trans("Dossiers"), 'searchdossier', 'searchdossier');
//		 $res .= printSearchForm(DOL_URL_ROOT.'/compta/facture/list.php', DOL_URL_ROOT.'/compta/facture/list.php', img_object('','invoice').' '.$langs->trans("Factures Clients"), 'products', 'search_ref');
//		 $res .= printSearchForm(DOL_URL_ROOT.'/fourn/facture/list.php', DOL_URL_ROOT.'/fourn/facture/list.php', img_object('','invoice').' '.$langs->trans("Factures Leasers"), 'products', 'search_ref');
//		 $res .= printSearchForm(DOL_URL_ROOT.'/custom/financement/simulation/list.php', DOL_URL_ROOT.'/custom/financement/simulation/list.php', img_object('','invoice').' Simulation', 'searchnumetude', 'searchnumetude');
//		 $res .= printSearchForm(DOL_URL_ROOT.'/custom/financement/simulation/simulation.php', DOL_URL_ROOT.'/custom/financement/simulation/simulation.php', img_object('','resource').' Matricule', 'search_matricule', 'search_matricule');
//		 $hookmanager->resPrint.= $res;
//
//		 return 0;
//	}

    function formObjectOptions($parameters, &$object, &$action, $hookmanager) {
        global $user, $db;

        if(in_array('thirdpartycard', explode(':', $parameters['context'])) && $action !== 'create') {
            /** @var Societe $object */
            $listsalesrepresentatives = $object->getSalesRepresentatives($user);

            foreach($listsalesrepresentatives as $commercial) {
                $sql = "SELECT type_activite_cpro FROM ".MAIN_DB_PREFIX."societe_commerciaux WHERE fk_soc=".$object->id." AND fk_user=".$commercial['id'];
                if($resql = $db->query($sql)) {
                    $obj = $db->fetch_object($resql);

                    if($obj->type_activite_cpro != '') {
                        ?>
                        <script type="text/javascript">
                            $(document).ready(function () {
                                $('a').each(function () {
                                    if ($(this).html() == "<?php echo $commercial['firstname'].' '.$commercial['lastname'] ?>") {
                                        $(this).append(" [<?php echo $obj->type_activite_cpro ?>]");
                                    }
                                });
                            });
                        </script>
                        <?php
                    }
                }
            }
        }
        else if(in_array('salesrepresentativescard', explode(':', $parameters['context']))) {
            $id = isset($object->rowid) ? $object->rowid : $object->id;

            $sql = "SELECT type_activite_cpro FROM ".MAIN_DB_PREFIX."societe_commerciaux WHERE fk_soc=".$parameters['socid']." AND fk_user=".$id." AND rowid = ".$object->id_link;

            if($resql = $db->query($sql)) {
                $obj = $db->fetch_object($resql);
                if($obj->type_activite_cpro != '') {
                    $object->lastname .= ' ['.$obj->type_activite_cpro.']';
                    if(isset($object->name)) $object->name .= ' ['.$obj->type_activite_cpro.']';
                }
            }
        }
        else if(in_array('invoicecard', explode(':', $parameters['context']))) { // Affichage du dossier de financement relatif à la facture de location ou de l'affaire relative à la facture de matériel
            $sql = "SELECT sourcetype, fk_source FROM ".MAIN_DB_PREFIX."element_element WHERE fk_target=".$object->id." AND targettype='facture'";
            if($resql = $db->query($sql)) {
                $obj = $db->fetch_object($resql);

                if($obj->sourcetype == 'affaire') {
                    $link = '<a href="'.dol_buildpath('/financement/affaire.php?id='.$obj->fk_source, 1).'">Voir l\'affaire</a>';
                    echo '<tr><td >Facture de matériel</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';
                }
                else if($obj->sourcetype == 'dossier') {
                    $link = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$obj->fk_source, 1).'">Voir le dossier de financement</a>';
                    echo '<tr><td >Facture de location</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';
                }
            }
        }
        else if(in_array('invoicesuppliercard', explode(':', $parameters['context']))) {
            // Affichage du dossier de financement relatif à la facture fournisseur
            $sql = "SELECT sourcetype, fk_source FROM ".MAIN_DB_PREFIX."element_element WHERE fk_target=".$object->id." AND targettype='invoice_supplier'";
            if($resql = $db->query($sql)) {
                $obj = $db->fetch_object($resql);

                if($obj->sourcetype == 'dossier') {
                    $link = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$obj->fk_source, 1).'">Voir le dossier de financement</a>';
                    echo '<tr><td >Facture de loyer leaser</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';

                    // Affichage bouton permettant de créer un avoir directement
                    if($object->type != 2) {
                        $url = dol_buildpath('/financement/dossier.php?action=create_avoir&id_facture_fournisseur='.$object->id.'&id_dossier='.$obj->fk_source, 1);
                        ?>
                        <script type="text/javascript">
                            $(document).ready(function () {
                                $('div.tabsAction').append('<a class="butAction" href="<?php echo $url ?>">Créer un avoir</a>');
                            });
                        </script>
                        <?php
                    }
                }
            }
        }
        else if(in_array('propalcard', explode(':', $parameters['context']))) {
            /** @var Propal $object */
            $object->fetchObjectLinked();

            if(! empty($object->linkedObjects['facture'])) {
                define('INC_FROM_DOLIBARR', true);
                dol_include_once('/financement/config.php');
                dol_include_once('/financement/class/dossier_integrale.class.php');

                $fac = array_shift($object->linkedObjects['facture']);

                $sql = 'SELECT fk_source FROM '.MAIN_DB_PREFIX.'element_element WHERE sourcetype="dossier" AND targettype="facture" AND fk_target='.$fac->id.' LIMIT 1';

                $resql = $db->query($sql);
                if($resql) {
                    $res = $db->fetch_object($resql);

                    if($res->fk_source > 0) {
                        print '<tr>';
                        print '<td>';
                        print 'Suivi intégrale';
                        print '</td>';
                        print '<td>';
                        print '<a href="'.dol_buildpath('/financement/dossier_integrale.php?id='.$res->fk_source, 1).'">Voir le suivi intégrale associé</a>';
                        print '</td>';
                        print '</tr>';
                    }
                }

                // Détail du nouveau coût unitaire (uniquement si le user a les droits)
                if(! empty($user->rights->financement->integrale->detail_couts)) {
                    $PDOdb = new TPDOdb;
                    $integrale = new TIntegrale;
                    $integrale->loadBy($PDOdb, $fac->ref, 'facnumber');

                    if($integrale->rowid > 0) {
                        $line_engagement_noir = TIntegrale::get_line_from_propal($object, 'E_NOIR');
                        $line_engagement_coul = TIntegrale::get_line_from_propal($object, 'E_COUL');

                        $TDataCalculNoir = $integrale->calcul_detail_cout($line_engagement_noir->qty, $line_engagement_noir->subprice);
                        $TDataCalculCouleur = $integrale->calcul_detail_cout($line_engagement_coul->qty, $line_engagement_coul->subprice, 'coul');

                        print '<tr>'.'<td>';
                        print '<STRONG>Détail nouvel engagement noir</STRONG>';
                        print '</td>'.'<td>';
                        print '';
                        print '</td>'.'</tr>';

                        print '<tr>'.'<td>';
                        print '- Tech';
                        print '</td>'.'<td>';
                        print $TDataCalculNoir['nouveau_cout_unitaire_tech'];
                        print '</td>'.'</tr>'.'<tr>'.'<td>';
                        print '- Mach';
                        print '</td>'.'<td>';
                        print $TDataCalculNoir['nouveau_cout_unitaire_mach'];
                        print '</td>'.'</tr>'.'<tr>'.'<td>';
                        print '- Loyer';
                        print '</td>'.'<td>';
                        print $TDataCalculNoir['nouveau_cout_unitaire_loyer'];
                        print '</td>'.'</tr>';

                        print '<tr>'.'<td>';
                        print '<STRONG>Détail nouvel engagement couleur</STRONG>';
                        print '</td>'.'<td>';
                        print '';
                        print '</td>'.'</tr>';

                        print '<tr>'.'<td>';
                        print '- Tech';
                        print '</td>'.'<td>';
                        print $TDataCalculCouleur['nouveau_cout_unitaire_tech'];
                        print '</td>'.'</tr>'.'<tr>'.'<td>';
                        print '- Mach';
                        print '</td>'.'<td>';
                        print $TDataCalculCouleur['nouveau_cout_unitaire_mach'];
                        print '</td>'.'</tr>'.'<tr>'.'<td>';
                        print '- Loyer';
                        print '</td>'.'<td>';
                        print $TDataCalculCouleur['nouveau_cout_unitaire_loyer'];
                        print '</td>'.'</tr>';
                    }
                }
            }
        }
    }

    // Affichage valeur spéciale dans dictionnaire
    function createDictionaryFieldlist($parameters, &$object, &$action, $hookmanager) {
        global $form, $langs;

        define('INC_FROM_DOLIBARR', true);
        dol_include_once('/financement/config.php');
        dol_include_once('/financement/class/affaire.class.php');
        $aff = new TFin_affaire();

        foreach($parameters['fieldlist'] as $field => $value) {
            if($value == 'fk_type_contrat') {
                print '<td>';
                print $form->selectarray($value, $aff->TContrat, $object->$value);
                print '</td>';
            }
            else if($value == 'fk_nature') {
                print '<td>';
                print $form->selectarray($value, $aff->TNatureFinancement, $object->$value);
                print '</td>';
            }
            else if($value == 'base_solde') {
                print '<td>';
                print $form->selectarray($value, $aff->TBaseSolde, $object->$value);
                print '</td>';
            }
            else if($value == 'date_application') {
                print '<td>';
                print '<input type="date" class="flat" name="date_application" />';
                print '</td>';
            }
            else if($value == 'cape_lrd') {
                $TCapeLRD = array(
                    0 => $langs->trans('No'),
                    1 => $langs->trans('Yes')
                );

                print '<td>';
                print '<select name="cape_lrd" class="flat">';
                foreach($TCapeLRD as $k => $v) {
                    print '<option value="'.$k.'"';
                    if($k == 0) print ' selected="selected"';
                    print '>'.$v.'</options>';
                }
                print '</select>';
                print '</td>';
            }
            else if($value == 'amount') {
                print '<td>';
                print '<input type="number" class="flat" name="amount" min="0" />';
                print '</td>';
            }
            else {
                print '<td>';
                $size = '';
                if($value == 'periode') $size = 'size="10" ';
                if($value == 'percent') $size = 'size="10" ';
                print '<input type="text" '.$size.' class="flat" value="'.(isset($object->$value) ? $object->$value : '').'" name="'.$value.'">';
                print '</td>';
            }
        }

        $hookmanager->resPrint = '1';
        return 1;
    }

    function editDictionaryFieldlist($parameters, &$object, &$action, $hookmanager) {
        global $form, $langs;

        define('INC_FROM_DOLIBARR', true);
        dol_include_once('/financement/config.php');
        dol_include_once('/financement/class/affaire.class.php');
        $aff = new TFin_affaire();

        foreach($parameters['fieldlist'] as $field => $value) {
            if($value == 'fk_type_contrat') {
                print '<td>';
                print $form->selectarray($value, $aff->TContrat, $object->$value);
                print '</td>';
            }
            else if($value == 'fk_nature') {
                print '<td>';
                print $form->selectarray($value, $aff->TNatureFinancement, $object->$value);
                print '</td>';
            }
            else if($value == 'base_solde') {
                print '<td>';
                print $form->selectarray($value, $aff->TBaseSolde, $object->$value);
                print '</td>';
            }
            else if($value == 'date_application') {
                print '<td>';
                print '<input type="date" class="flat" name="date_application" value="'.$object->$value.'" />';
                print '</td>';
            }
            else if($value == 'cape_lrd') {
                $TCapeLRD = array(
                    0 => $langs->trans('No'),
                    1 => $langs->trans('Yes')
                );

                print '<td>';
                print '<select name="cape_lrd" class="flat">';
                foreach($TCapeLRD as $k => $v) {
                    print '<option value="'.$k.'"';
                    if($object->$value == $k) print ' selected="selected"';
                    print '>'.$v.'</options>';
                }
                print '</select>';
                print '</td>';
            }
            else if($value == 'amount') {
                print '<td>';
                print '<input type="number" class="flat" name="amount" min="0" value="'.$object->$value.'" />';
                print '</td>';
            }
            else {
                print '<td>';
                $size = '';
                if($value == 'periode') $size = 'size="10" ';
                if($value == 'percent') $size = 'size="10" ';
                print '<input type="text" '.$size.' class="flat" value="'.(isset($object->$value) ? $object->$value : '').'" name="'.$value.'">';
                print '</td>';
            }
        }

        $hookmanager->resPrint = '1';
        return 1;
    }

    function viewDictionaryFieldlist($parameters, &$object, &$action, $hookmanager) {
        global $langs;

        define('INC_FROM_DOLIBARR', true);
        dol_include_once('/financement/config.php');
        dol_include_once('/financement/class/affaire.class.php');
        $aff = new TFin_affaire();

        foreach($parameters['fieldlist'] as $field => $value) {
            if($value == 'fk_type_contrat') {
                print '<td>';
                print $aff->TContrat[$object->$value];
                print '</td>';
            }
            else if($value == 'fk_nature') {
                print '<td>';
                print $aff->TNatureFinancement[$object->$value];
                print '</td>';
            }
            else if($value == 'base_solde') {
                print '<td>';
                print $aff->TBaseSolde[$object->$value];
                print '</td>';
            }
            else if($value == 'date_application') {
                print '<td>';

                if(is_null($object->$value)) print '';
                else print date('d/m/Y', strtotime($object->$value));

                print '</td>';
            }
            else if($value == 'cape_lrd') {
                print '<td>';

                if(! empty($object->$value)) print $langs->trans('Yes');
                else print $langs->trans('No');

                print '</td>';
            }
            else {
                print '<td>';
                print $object->$value;
                print '</td>';
            }
        }

        $hookmanager->resPrint = '1';
        return 1;
    }

    function replaceThirdparty($parameters, &$object, &$action, $hookmanager) {
        global $db;

        dol_include_once('/financement/lib/financement.lib.php');
        dol_include_once('/financement/class/simulation.class.php');
        dol_include_once('/financement/class/affaire.class.php');

        $fk_soc_source = $parameters['soc_origin'];
        $fk_soc_target = $parameters['soc_dest'];

        $socSource = new Societe($db);
        $socSource->fetch($fk_soc_source);
        $socTarget = new Societe($db);
        $socTarget->fetch($fk_soc_target);

        // TODO: Check if standard replacement is possible
        $TEntityGroup = getOneEntityGroup($socTarget->entity, 'fin_simulation', array(4, 17));
        // Si les 2 sociétés ne sont pas dans la même groupe, on évite de merge
        if(! in_array($socSource->entity, $TEntityGroup)) return -1;


        TSimulation::replaceThirdparty($fk_soc_source, $fk_soc_target, $TEntityGroup);
        TFin_affaire::replaceThirdparty($fk_soc_source, $fk_soc_target, $TEntityGroup);

        return 0;
    }
}
