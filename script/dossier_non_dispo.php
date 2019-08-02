<?php
$a = microtime(true);

require '../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/lib/financement.lib.php');

$sql = "
select
       (case d.nature_financement
            when 'INTERNE' then dfcli.reference
            when 'EXTERNE' then dflea.reference
           end) as ref_contrat,
       e.label  as partenaire,
       lea.nom  as leaser,
       (case d.nature_financement
            when d.display_solde = 0 then 1
            when d.montant >= (select coalesce(c.value, 50000) from ".MAIN_DB_PREFIX."const c where c.name = 'FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE' and c.entity in (0, 1, d.entity) order by c.entity desc limit 1) then 2
            when d.soldepersodispo = 2 then 3
            when 'EXTERNE'
                then
                case
                    when dflea.incident_paiement = 'OUI'
                        then 4
                    when dflea.taux < (select c2.value from ".MAIN_DB_PREFIX."const c2 where name = 'FINANCEMENT_MIN_TAUX_TO_SHOW_SOLDE' and c2.entity in (0, 1, d.entity) order by c2.entity desc limit 1)
                        then 5
                    when ((dflea.numero_prochaine_echeance - 1) * (case dflea.periodicite when 'TRIMESTRE' then 3 when 'SEMESTRE' then 6 when 'ANNEE' then 12 else 1 end))
                        <= (select c3.value from ".MAIN_DB_PREFIX."const c3 where name = 'FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH' and c3.entity in (0, 1, d.entity) order by c3.entity desc limit 1)
                        then 6
                    end
            when 'INTERNE'
                then
                case
                    when dfcli.incident_paiement = 'OUI'
                        then 4
                    when dfcli.taux < (select c2.value from ".MAIN_DB_PREFIX."const c2 where name = 'FINANCEMENT_MIN_TAUX_TO_SHOW_SOLDE' and c2.entity in (0, 1, d.entity) order by c2.entity desc limit 1)
                        then 5
                    when ((dfcli.numero_prochaine_echeance-1) * (case dfcli.periodicite when 'TRIMESTRE' then 3 when 'SEMESTRE' then 6 when 'ANNEE' then 12 else 1 end))
                        <= (select c3.value from ".MAIN_DB_PREFIX."const c3 where name = 'FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH' and c3.entity in (0, 1, d.entity) order by c3.entity desc limit 1)
                        then 6
                    end
            when (select count(ee.fk_source)
                  from ".MAIN_DB_PREFIX."element_element ee
                  left join ".MAIN_DB_PREFIX."facture f on (ee.targettype = 'facture' and ee.fk_target = f.rowid and ee.sourcetype = 'dossier')
                  where ee.fk_source = d.rowid
                    and f.paye = 0) > (select c4.value from ".MAIN_DB_PREFIX."const c4 where name = 'FINANCEMENT_NB_INVOICE_UNPAID' and c4.entity in (0, 1, d.entity) order by c4.entity desc limit 1)
                then 7
           end) as ruleNumber
from ".MAIN_DB_PREFIX."fin_dossier d
left join ".MAIN_DB_PREFIX."fin_dossier_financement dfcli on (dfcli.fk_fin_dossier = d.rowid and dfcli.type = 'CLIENT')
left join ".MAIN_DB_PREFIX."fin_dossier_financement dflea on (dflea.fk_fin_dossier = d.rowid and dflea.type = 'LEASER')
left join ".MAIN_DB_PREFIX."societe lea on (dflea.fk_soc = lea.rowid)
inner join ".MAIN_DB_PREFIX."entity e on (d.entity = e.rowid)
where dflea.date_solde < '1970-01-01 00:00:00'
  and (d.display_solde = 0 -- Règle 1
    or d.montant >= (select coalesce(c.value, 50000) from ".MAIN_DB_PREFIX."const c where c.name = 'FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE' and c.entity in (0, 1, d.entity) order by c.entity desc limit 1) -- Règle 2
    or d.soldepersodispo = 2 -- Règle 3
    or (case d.nature_financement
            when 'EXTERNE' then dflea.incident_paiement = 'OUI'
            when 'INTERNE' then dfcli.incident_paiement = 'OUI'
        end) -- Règle 4
    or (case d.nature_financement
            when 'EXTERNE' then dflea.taux < (select c2.value from ".MAIN_DB_PREFIX."const c2 where name = 'FINANCEMENT_MIN_TAUX_TO_SHOW_SOLDE' and c2.entity in (0, 1, d.entity) order by c2.entity desc limit 1)
            when 'INTERNE' then dfcli.taux < (select c2.value from ".MAIN_DB_PREFIX."const c2 where name = 'FINANCEMENT_MIN_TAUX_TO_SHOW_SOLDE' and c2.entity in (0, 1, d.entity) order by c2.entity desc limit 1)
        end) -- Règle 5
    or (case d.nature_financement
            when 'EXTERNE'
                then ((dflea.numero_prochaine_echeance-1) * (case dflea.periodicite when 'TRIMESTRE' then 3 when 'SEMESTRE' then 6 when 'ANNEE' then 12 else 1 end))
                <= (select c3.value from ".MAIN_DB_PREFIX."const c3 where name = 'FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH' and c3.entity in (0, 1, d.entity) order by c3.entity desc limit 1)
            when 'INTERNE'
                then ((dfcli.numero_prochaine_echeance-1) * (case dfcli.periodicite when 'TRIMESTRE' then 3 when 'SEMESTRE' then 6 when 'ANNEE' then 12 else 1 end))
                <= (select c3.value from ".MAIN_DB_PREFIX."const c3 where name = 'FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH' and c3.entity in (0, 1, d.entity) order by c3.entity desc limit 1)
        end) -- Règle 6
    or (select count(ee.fk_source)
        from ".MAIN_DB_PREFIX."element_element ee
        left join ".MAIN_DB_PREFIX."facture f on (ee.targettype = 'facture' and ee.fk_target = f.rowid and ee.sourcetype = 'dossier')
        where ee.fk_source = d.rowid
          and f.paye = 0) > (select c4.value from ".MAIN_DB_PREFIX."const c4 where name = 'FINANCEMENT_NB_INVOICE_UNPAID' and c4.entity in (0, 1, d.entity) order by c4.entity desc limit 1)) -- Règle 7
order by ruleNumber desc;";

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$THead = array(
    'ref_contrat',
    'partenaire',
    'leaser',
    'numero_regle'
);

if($conf->entity > 1) $path = DOL_DATA_ROOT.'/'.$conf->entity.'/financement/export/dossier_non_dispo';
else $path = DOL_DATA_ROOT.'/financement/export/dossier_non_dispo';

if(! file_exists($path)) dol_mkdir($path);

$filename = 'extract_dossier_non_dispo_'.date('Ymd-His').'.csv';
$f = fopen($path.'/'.$filename, 'w');
$res = fputcsv($f, $THead, ';');

$TRes = array();
while($obj = $db->fetch_object($resql)) {
    $TData = array(
        $obj->ref_contrat,
        $obj->partenaire,
        $obj->leaser,
        $obj->ruleNumber
    );

    fputcsv($f, $TData, ';');
}

fclose($f);
$db->free($resql);

$b = microtime(true);
print 'Execution time: '.($b-$a).' sec';

// Pour download le fichier
print '<script language="javascript">';
print 'document.location.href = "'.dol_buildpath('/document.php?modulepart=financement&entity='.$conf->entity.'&file=export/dossier_non_dispo/'.$filename, 2).'";';
print '</script>';