/**
 * Created by asier on 25/9/16.
 */
jQuery(document).ready(function ($) {

    var bank_row_tpl =  '<tr id="besepa_ba_[id]" class="bank_account">' +
        '<td>[iban]' +
        '<td class="select center"><input type="radio" name="besepa_bank_account_id" value="[id]"> </td>' +
        '</tr>';

    var form_container       = $('#besepa_add_new_form_container');
    var iban_input           = $("#besepa_iban");
    var customer_id_input    = $("#besepa_customer_id");
    var besepa_error         = $("#besepa_error");
    var bank_accounts_list   = $("#bank_accounts");


    $('#besepa_add_new').click(function (e) {
        e.preventDefault();
        if(form_container.is(":visible"))
        {
            form_container.slideUp("fast");
        }else{
            form_container.slideDown("fast");
            iban_input.focus();
        }
    });

    $("#besepa_add_account").click(function (e) {

        e.preventDefault();

        besepa_error.hide();
        if(iban_input.val())
        {

            var self = this;

            var work_label = $(self).attr("data-working-text");
            var rest_label = $(self).text();

            $(self).html(work_label);
            $(self).attr("disabled", "disabled");

            $.getJSON(window.ajax_url,
                {
                    besepa_ajax_action: 'create_bank_account',
                    besepa_iban: iban_input.val(),
                    besepa_customer_id: customer_id_input.val()
                },
                function (json) {

                    if(json.error)
                    {

                        besepa_error.html(json.error).fadeIn();

                    }else{


                        iban_input.val("");
                        form_container.slideUp("fast", function(){ });

                        $("#besepa_no_bank_accounts").remove();

                        var html = bank_row_tpl;
                        html = html.replace('[iban]', json.bank_account.iban);
                        html = html.replace('[id]', json.bank_account.id);
                        html = html.replace('[id]', json.bank_account.id);

                        var _item = $(html);
                        bank_accounts_list.append(_item);

                        _item.fadeOut().fadeIn().find('input').attr("checked", "checked");

                        $("#besepa_make_payment").removeAttr("disabled");

                        if(json.needs_mandate)
                        {
                            tb_show('', json.bank_account.mandate_url + '#TB_iframe=true', false);
                        }
                    }
                    $(self).html(rest_label);
                    $(self).removeAttr("disabled");




            });


        }else{

            iban_input.focus();

        }

    });





});
