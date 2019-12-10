$(document).ready(function () {
    $('select[name="opt_periodicite"]').bind('change', get_periode);
    $('select[name="fk_type_contrat"]').bind('change', get_periode);
    $('select[name="entity_partenaire"]').bind('change', function () {
        let entity = $('input#entity');
        if (entity.val() !== $(this).val()) {
            entity.val($(this).val());
        }

        get_periode();
    });
    $('select[name="fk_type_contrat"]').bind('change', restrict_grand_compte);
    $('input[name^="opt_"]').bind('click', get_grille);
    $('select[name^="opt_"]').bind('change', get_grille);

    if ($('select[name="fk_type_contrat"]').length > 0) {
        get_periode();
        restrict_grand_compte();
    }

    $('input[name^="dossiers_rachetes"]').bind('click', calcul_montant_rachat);
    $('input[name^="dossiers_rachetes"]').bind('click', prevent_dbl_select);
    $('select[name="fk_type_contrat"]').bind('change', calcul_montant_rachat);
    $('#montant_rachete_concurrence').bind('change', calcul_montant_rachat);

    init_selected_dossier();

    // Calage
    /*$('select[name="opt_calage"]').bind('change', select_calage);
    if($('select[name="opt_calage"]').val() == '') {
        $('input[name="date_demarrage"]').attr('disabled', true);
        $('input[name="date_demarrage"]').val('');
    } else {
        $('input[name="date_demarrage"]').attr('disabled', false);
    }*/
    let entity = $('#entity_partenaire').val();
    if (entity === '18' || entity === '25') $('#date_demarrage').bind('change', getCalageForEsusAndAbs);
    else $('#date_demarrage').bind('change', getCalageForOthers);

    function getCalageForOthers() {
        console.log('function getCalageForOthers');

        let date_d = $('#date_demarrage').val();
        date_d = date_d.split('/');
        date_d.reverse();
        date_d = date_d.join('/');

        var date_demarrage = new Date(date_d);
        var today = new Date();
        var diff_time = date_demarrage.getTime() - today.getTime();

        var diff_jours = Math.ceil(diff_time / (1000 * 60 * 60 * 24));
        if (diff_jours >= 0 && diff_jours < 210) {
            $('#opt_calage').val(Math.floor(diff_jours / 31) + 'M');
            $('#opt_calage_label').val(Math.floor(diff_jours / 31) + 'M');
        }
        else if (diff_jours >= 210) {
            $('#opt_calage').val('6M');
            $('#opt_calage_label').val('6M');
        }
        else {
            $('#opt_calage').val('');
            $('#opt_calage_label').val('');
        }
    }

    function getCalageForEsusAndAbs() {
        console.log('function getCalageForEsusAndAbs');
        let date_livraison = $('#date_demarrage').val();

        date_livraison = date_livraison.split('/');
        date_livraison.reverse();
        date_livraison = date_livraison.join('/');
        var dateL = new Date(date_livraison);

        let nextQuarter = getQuarter(dateL);
        let diffInMS = nextQuarter.getTime() - dateL.getTime();
        let diffInDays = diffInMS / (86400 * 1000);

        let calageRule = null;
        if (diffInDays > 0 && diffInDays <= 45) calageRule = 1;
        else if (diffInDays > 45 && diffInDays <= 75) calageRule = 2;
        else if (diffInDays > 75) calageRule = 3;

        $('#opt_calage').val(calageRule + 'M');
        $('#opt_calage_label').val(calageRule + 'M');
    }

    function getQuarter(date, id = 'next') {
        let d = date;
        let quarter = Math.floor((d.getMonth() / 3));
        var firstDate = null;
        let endDate = null;

        switch (id) {
            case 'prev':
                firstDate = new Date(d.getFullYear(), quarter * 3 - 3, 1);
                endDate = new Date(firstDate.getFullYear(), firstDate.getMonth() + 3, 0);
                break;
            case 'curr':
                firstDate = new Date(d.getFullYear(), quarter * 3, 1);
                endDate = new Date(firstDate.getFullYear(), firstDate.getMonth() + 3, 0);
                break;
            case 'next':
                firstDate = new Date(d.getFullYear(), quarter * 3 + 3, 1);
                endDate = new Date(firstDate.getFullYear(), firstDate.getMonth() + 3, 0);
                break;
        }
        return firstDate;
    }

    $('select[name="opt_calage_label"]').bind('change', function () {
        $('#opt_calage').val($(this).val());
    });

    // Rachat dossier ou case aucun
    $('#opt_no_case_to_settle').bind('click', function () {
        if ($(this).attr('checked') == 'checked') {
            $('input[name^="dossiers_rachetes"]').attr('checked', false);
            $('input[name^="dossiers_rachetes"]').parent('td.solde').css('background-color', '');
            calcul_montant_rachat();
        }
    });

    $('input[name="validate_simul"]').click(function () {
        $(this).hide();
    });

    // Adjonction
    if ($('input[name="opt_adjonction"]:checked').length == 0) {
        $('select[name="fk_fin_dossier_adjonction"]').hide();
    }

    $('input[name="opt_adjonction"]').bind('click', function () {
        if ($(this).attr('checked') == 'checked') {
            $('select[name="fk_fin_dossier_adjonction"]').show();
        } else {
            $('select[name="fk_fin_dossier_adjonction"]').hide();
            $('select[name="fk_fin_dossier_adjonction"]').val(0);
        }
    });

    $('select[name="fk_fin_dossier_adjonction"]').bind('change', function () {
        var contrat = $(this).find(':selected').attr('type_contrat');
        if (contrat !== undefined) {
            $('#fk_type_contrat').val(contrat);
            $('#fk_type_contrat option:selected').attr('disabled', false);
            $('#fk_type_contrat option:not(:selected)').attr('disabled', true);
            $('#fk_type_contrat').change();
        } else {
            $('#fk_type_contrat').val(0);
            $('#fk_type_contrat option').attr('disabled', false);
        }
    });
});

var get_grille = function () {
    var fin_options = {
        'opt_periodicite' : $('select[name="opt_periodicite"]').val()
        , 'opt_mode_reglement' : $('select[name="opt_mode_reglement"]').val()
        , 'opt_terme' : $('select[name="opt_terme"]').val()
        , 'opt_administration' : $('input[name="opt_administration"]:checked').length > 0 ? $('input[name="opt_administration"]:checked').val() : 0
        , 'opt_creditbail' : $('input[name="opt_creditbail"]:checked').length > 0 ? $('input[name="opt_creditbail"]:checked').val() : 0
        , 'opt_calage' : $('select[name="opt_calage"]').val()
    };

    var data = {
        mode : 'grille',
        outjson : 1,
        idLeaser : $('input[name="idLeaser"]').val(),
        fk_type_contrat : $('select[name="fk_type_contrat"]').val(),
        opt_periodicite : $('select[name="opt_periodicite"]').val(),
        options : fin_options,
        entity : $('#entity_partenaire').val()
    };

    if (data.fk_type_contrat != 0) {
        $.post(
            'ajaxsimulateur.php',
            data,
            function (resHTML) {
                $('#grille').remove();
                var grille = $('<div />').append(resHTML);
                grille.attr('id', 'grille');
                $('div.fiche').append(grille);
            },
            'json'
        );
    }
};

var get_periode = function () {
    var data = {
        mode : 'duree',
        outjson : 1,
        opt_periodicite : $('select[name="opt_periodicite"]').val(),
        fk_type_contrat : $('select[name="fk_type_contrat"]').val(),
        entity : $('#entity_partenaire').val()
    };
    $.post(
        'ajaxsimulateur.php',
        data,
        function (resHTML) {
            var valeur = $('select[name="duree"]').val();
            //alert(valeur);
            $('select[name="duree"]').replaceWith(resHTML);

            $('select[name="duree"]').val(valeur);

            get_grille();
        },
        'json'
    );
};

var calcul_montant_rachat = function () {
    var montant_rachat = montant_decompte_copies = montant_rachat_final = 0;
    var type_contrat = $('select[name="fk_type_contrat"]').val();
    var type_solde = 'solde_nr';
    $('input[name^="dossiers_rachetes"]:checked').each(function () {

        montant_rachat += parseFloat($(this).attr('solde'));
        montant_decompte_copies += parseFloat($('input[name="dossiers_rachetes_perso[' + $(this).val() + ']"]').attr('solde'));
        montant_rachat_concurrence = parseFloat($('#montant_rachete_concurrence').val());

        if ((montant_rachat - montant_decompte_copies) < 0)
            montant_decompte_copies = montant_rachat;

        montant_rachat_final = montant_rachat - montant_decompte_copies + montant_rachat_concurrence;
    });

    montant_rachat = Math.round(montant_rachat * 100) / 100;
    montant_decompte_copies = Math.round(montant_decompte_copies * 100) / 100;
    montant_rachat_final = Math.round(montant_rachat_final * 100) / 100;
    $('input[name="montant_rachete"]').val(montant_rachat);
    $('input[name="montant_decompte_copies_sup"]').val(montant_decompte_copies);
    $('input[name="montant_rachat_final"]').val(montant_rachat_final);
};

var prevent_dbl_select = function () {
    var val = $(this).val();
    if ($(this).attr('checked') == 'checked') {
        $('input[name^="dossiers_rachetes"][name$="\\[' + val + '\\]"]').attr('disabled', true);
        $(this).attr('disabled', false);
    } else {
        $('input[name^="dossiers_rachetes"][name$="\\[' + val + '\\]"]').attr('disabled', false);
    }
    /*if($('#dossiers_rachetes\\['+val+'\\]:checked').length > 0) {
        $('#dossiers_rachetes_p1\\['+val+'\\]').attr('disabled', true);
    } else {
        $('#dossiers_rachetes_p1\\['+val+'\\]').attr('disabled', false);
    }
    if($('#dossiers_rachetes_p1\\['+val+'\\]:checked').length > 0) {
        $('#dossiers_rachetes\\['+val+'\\]').attr('disabled', true);
    } else {
        $('#dossiers_rachetes\\['+val+'\\]').attr('disabled', false);
    }*/
};

var select_solde = function () {
    var cb = $(this).find('input[type="checkbox"]');
    if (cb.attr('checked') == 'checked') {
        cb.attr('checked', false);
        $(this).css('background-color', '');
    } else {
        $(this).parent('tr').find('input[type="checkbox"]').attr('checked', false);
        $(this).parent('tr').find('td.solde').css('background-color', '');
        cb.attr('checked', true);
        $(this).css('background-color', '#00FF00');
        $('#opt_no_case_to_settle').attr('checked', false);
    }

    calcul_montant_rachat();
};

var init_selected_dossier = function () {
    // Mise en couleur des dossiers rachetés dans la simulation
    $('input[type="checkbox"]:checked').parent('td').css('background-color', '#00FF00');
    $('span.check').parent('td').css('background-color', '#00FF00');

    // Possibilité de sélectionner un solde
    $('input[type="checkbox"]').not(':disabled').parent('td.solde').bind('click', select_solde).css('cursor', 'pointer');

    // Dossier déjà sélectionné dans une autre simu
    $('input[type="checkbox"]:disabled').parent('td.solde').each(function () {
        $(this).attr('title', $(this).find('input[type="checkbox"]').attr('title'));
    });
};

var select_calage = function () {
    if ($(this).val() == '') {
        $('input[name="date_demarrage"]').attr('disabled', true);
        $('input[name="date_demarrage"]').attr('value', '');
    } else {
        $('input[name="date_demarrage"]').attr('disabled', false);
    }
};

// Modifications spécifique au type GRAND COMPTE
var restrict_grand_compte = function () {
    if ($('select[name="fk_type_contrat"]').val() == 'GRANDCOMPTE') {
        // On décoche la case administration au cas ou, et on désactive le champ
        $('input[name="opt_administration"]').attr('checked', false);
        $('input[name="opt_administration"]').attr('disabled', true);
        // On positionne sur Mandat et on désactive le choix
        $('select[name="opt_mode_reglement"]').val('MDT');
        $('select[name="opt_mode_reglement"] option').attr('disabled', true);
        $('select[name="opt_mode_reglement"] option:selected').attr('disabled', false);
    } else {
        $('input[name="opt_administration"]').attr('disabled', false);
        $('select[name="opt_mode_reglement"] option').attr('disabled', false);
    }
};
