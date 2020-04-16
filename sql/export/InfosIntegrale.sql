select 'rowid',
       'nom',
       'reference',
       'date_debut',
       'ref',
       'datef',
       'total_ht_engage',
       'total_ht_facture',
       'ecart',
       'vol_noir_engage',
       'vol_noir_realise',
       'vol_noir_facture',
       'cout_unit_noir',
       'vol_coul_engage',
       'vol_coul_realise',
       'vol_coul_facture',
       'cout_unit_coul',
       'fas',
       'fass',
       'frais_dossier',
       'frais_bris_machine',
       'frais_facturation',
       'login',
       'type_activite_cpro',
       'duree',
       'duree_restante',
       'montant_finance',
       'loyer_financier',
       'periode_facture',
       'date_fin',
       'label'
union all
select
    fi.rowid,
    s.nom,
    df.reference,
    df.date_debut,
    f.ref,
    f.datef,
    fi.total_ht_engage,
    fi.total_ht_facture,
    fi.ecart,
    fi.vol_noir_engage,
    fi.vol_noir_realise,
    fi.vol_noir_facture,
    fi.cout_unit_noir,
    fi.vol_coul_engage,
    fi.vol_coul_realise,
    fi.vol_coul_facture,
    fi.cout_unit_coul,
    fi.fas,
    fi.fass,
    fi.frais_dossier,
    fi.frais_bris_machine,
    fi.frais_facturation,
    u.login,
    sc.type_activite_cpro,
    df.duree,
    (df.duree - df.numero_prochaine_echeance + 1) as duree_restante,
    df.montant                                    as montant_finance,
    df.echeance                                   as loyer_financier,
    f.ref_client                                  as periode_facture,
    df.date_fin,
    ent.label
from llx_facture f
left join llx_societe s on s.rowid = f.fk_soc
left join llx_societe_commerciaux sc on sc.fk_soc = s.rowid and sc.type_activite_cpro in ('Copieur', 'Traceur', 'Solution')
left join llx_user u on u.rowid = sc.fk_user
left join llx_element_element ee on (ee.fk_target = f.rowid and ee.targettype = 'facture')
left join llx_fin_dossier d on (d.rowid = ee.fk_source and ee.sourcetype = 'dossier')
left join llx_entity ent on (ent.rowid = d.entity)
left join llx_fin_dossier_financement df on (df.fk_fin_dossier = d.rowid)
inner join llx_fin_facture_integrale fi on (fi.rowid is not null and fi.facnumber = f.ref)
where df.type = 'CLIENT'
  and f.rowid not in (select fa.fk_facture_source from llx_facture as fa where fa.type = 2 and fa.fk_facture_source = f.rowid and ABS(f.total) = ABS(fa.total))
  and f.rowid not in (select fa.rowid from llx_facture as fa where fa.type = 2 and ABS(f.total) = ABS(fa.total))
