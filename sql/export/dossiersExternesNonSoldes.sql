select 'Affaire ref',
       'Affaire nature',
       'Affaire type',
       'Affaire contrat',
       'Affaire date',
       'Affaire client',
       'Affaire SIREN',
       'Affaire code artis',
       'Affaire montant',
       'Leaser',
       'Leaser ref',
       'Leaser date debut',
       'Leaser date fin',
       'Leaser montant',
       'Leaser loyer intercalaire',
       'Leaser echeance',
       'Leaser assurance',
       'Leaser VR',
       'Leaser duree',
       'Leaser periodicite',
       'Leaser terme',
       'Leaser date solde',
       'Leaser montant solde',
       'Statut dossier',
       'N° série',
       'Partenaire'
union all
select
    a.reference                     as 'Affaire ref',
    a.nature_financement            as 'Affaire nature',
    a.type_financement              as 'Affaire type',
    a.contrat                       as 'Affaire contrat',
    a.date_affaire                  as 'Affaire date',
    s.nom                           as 'Affaire client',
    s.siren                         as 'Affaire SIREN',
    s.code_client                   as 'Affaire code artis',
    a.montant                       as 'Affaire montant',
    lea.nom                         as 'Leaser',
    dflea.reference                 as 'Leaser ref',
    dflea.date_debut                as 'Leaser date debut',
    dflea.date_fin                  as 'Leaser date fin',
    dflea.montant                   as 'Leaser montant',
    dflea.loyer_intercalaire        as 'Leaser loyer intercalaire',
    dflea.echeance                  as 'Leaser echeance',
    dflea.assurance                 as 'Leaser assurance',
    dflea.reste                     as 'Leaser VR',
    dflea.duree                     as 'Leaser duree',
    dflea.periodicite               as 'Leaser periodicite',
    dflea.terme                     as 'Leaser terme',
    dflea.date_solde                as 'Leaser date solde',
    dflea.montant_solde             as 'Leaser montant solde',
    dsta.label                      as 'Statut dossier',
    GROUP_CONCAT(ass.serial_number) as 'N° série',
    e.label                         as 'Partenaire'
from llx_fin_dossier d
left join llx_fin_dossier_affaire da on (da.fk_fin_dossier = d.rowid)
left join llx_fin_affaire a on (a.rowid = da.fk_fin_affaire)
left join llx_societe s on (s.rowid = a.fk_soc)
left join llx_fin_dossier_financement dflea on (dflea.fk_fin_dossier = d.rowid)
left join llx_societe lea on (lea.rowid = dflea.fk_soc)
left join llx_c_financement_statut_dossier dsta on (dsta.rowid = d.fk_statut_dossier)
left join llx_assetatm_link al on (al.fk_document = a.rowid and al.type_document = 'affaire')
left join llx_assetatm ass on (ass.rowid = al.fk_asset)
inner join llx_entity e on (d.entity = e.rowid)
where dflea.type = 'LEASER'
  and (dflea.date_solde < '1970-01-01' or dflea.date_solde is null)
  and a.nature_financement = 'EXTERNE'
group by a.rowid, d.rowid
