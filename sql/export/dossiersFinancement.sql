select 'Affaire_reference',
       'Affaire_NatureFinancement',
       'Affaire_TypeFinancement',
       'Affaire_ContratFinancement',
       'Affaire_Date',
       'Affaire_Client',
       'Affaire_ClientSIREN',
       'Affaire_ClientCodeArtis',
       'Affaire_Montant',
       'Affaire_Solde',
       'Dossier_RentaPrevisionnelle',
       'Dossier_RentaAttendue',
       'Dossier_RentaReelle',
       'Financement_Type',
       'Financement_Reference',
       'Leaser',
       'Financement_DateDebut',
       'Financement_DateFin',
       'Financement_Montant',
       'Loyer_Intercalaire',
       'Financement_Echeance',
       'Financement_Taux',
       'Financement_Reste',
       'Financement_Periodicite',
       'Financement_Terme',
       'Financement_MontantSolde',
       'Financement_DateSolde',
       'Financement_Duree',
       'Financement_ModeReglement',
       'Financement_MontantPrestation',
       'Dossier_Entite',
       'Dossier_RNStatutDossier',
       'Dossier_RNStatutRentaNeg',
       'Client_Commercial',
       'Entité'
union all
select
    a.reference                as 'Affaire_reference',
    a.nature_financement       as 'Affaire_NatureFinancement',
    a.type_financement         as 'Affaire_TypeFinancement',
    a.contrat                  as 'Affaire_ContratFinancement',
    a.date_affaire             as 'Affaire_Date',
    s.nom                      as 'Affaire_Client',
    s.siren                    as 'Affaire_ClientSIREN',
    s.code_client              as 'Affaire_ClientCodeArtis',
    a.montant                  as 'Affaire_Montant',
    a.solde                    as 'Affaire_Solde',
    d.renta_previsionnelle     as 'Dossier_RentaPrevisionnelle',
    d.renta_attendue           as 'Dossier_RentaAttendue',
    d.renta_reelle             as 'Dossier_RentaReelle',
    df.type                    as 'Financement_Type',
    df.reference               as 'Financement_Reference',
    l.nom                      as 'Leaser',
    df.date_debut              as 'Financement_DateDebut',
    df.date_fin                as 'Financement_DateFin',
    df.montant                 as 'Financement_Montant',
    df.loyer_intercalaire      as 'Loyer_Intercalaire',
    df.echeance                as 'Financement_Echeance',
    df.taux                    as 'Financement_Taux',
    df.reste                   as 'Financement_Reste',
    df.periodicite             as 'Financement_Periodicite',
    df.terme                   as 'Financement_Terme',
    df.montant_solde           as 'Financement_MontantSolde',
    df.date_solde              as 'Financement_DateSolde',
    df.duree                   as 'Financement_Duree',
    df.reglement               as 'Financement_ModeReglement',
    df.montant_prestation      as 'Financement_MontantPrestation',
    ent.label                  as 'Dossier_Entite',
    dsd.label                  as 'Dossier_RNStatutDossier',
    drn.label                  as 'Dossier_RNStatutRentaNeg',
    GROUP_CONCAT(comm.login)   as 'Client_Commercial',
    ent.label                  as 'Entité'
from llx_fin_affaire as a
left join llx_societe as s on (a.fk_soc = s.rowid)
left join llx_societe_commerciaux as sc on (sc.fk_soc = s.rowid)
left join llx_user as comm on (sc.fk_user = comm.rowid)
left join llx_assetatm as e on (e.fk_affaire = a.rowid)
left join llx_product as p on (p.rowid = e.fk_product)
left join llx_fin_dossier_affaire as da on (da.fk_fin_affaire = a.rowid)
left join llx_fin_dossier as d on (d.rowid = da.fk_fin_dossier)
left join llx_entity as ent on (ent.rowid = d.entity)
left join llx_fin_dossier_financement as df on (df.fk_fin_dossier = d.rowid)
left join llx_societe as l on (df.fk_soc = l.rowid)
left join llx_c_financement_statut_dossier as dsd on (d.fk_statut_dossier = dsd.rowid)
left join llx_c_financement_statut_renta_neg_ano as drn on (d.fk_statut_renta_neg_ano = drn.rowid)
where l.rowid not in (204904, 204905, 204906)
group by df.rowid
