/**
 * Created by asier on 25/9/16.
 */
jQuery(document).ready(function ($) {

    var besepa_user = window.besepa_user;
    var besepa_messages  = window.besepa_messages;
    var woocommerce_place_order_button_id = "#place_order";

    var bank_row_tpl =  '<tr id="besepa_ba_[id]" class="bank_account">' +
                        '<td>[iban] <span class="status">[status]</span></td>' +
                        '<td class="select"><a href="#" data-id="[id]" data-status="[status]" class="select_besepa_bank_account">' +
                        besepa_messages.select_bank_account + '</a> </td></tr>';


    var bank_accounts_list;
    var bank_account_selected_input;
    var current_customer_input;
    var new_bank_account_iban_input;
    var payment_method_checkbox_besepa;

    var billing_company_input;
    var billing_email_input;
    var billing_taxid_input;
    var billing_first_name_input;
    var add_account_field_error;

    var address1_input;
    var address2_input;
    var postcode_input;
    var country_input;
    var bank_account_selected_status_input;


    //hack to select woocommerce fields
    function refreshDomSelection()
    {
        bank_accounts_list             = $('#besepa_bank_accounts');
        bank_account_selected_input    = $('#besepa_bank_account_id');
        bank_account_selected_status_input = $('#besepa_bank_account_status');
        current_customer_input         = $('#besepa_current_customer_id');
        new_bank_account_iban_input    = $('#besepa_new_iban');
        payment_method_checkbox_besepa = $("#payment_method_besepa");

        billing_company_input = $("#billing_company");
        billing_email_input   = $("#billing_email");
        billing_first_name_input = $("#billing_first_name");
        billing_taxid_input   = $("#" + window.tax_id_field_id);
        add_account_field_error = $("#add_account_field_error");

        address1_input = $("#billing_address_1");
        address2_input = $("#billing_address_2");
        postcode_input = $("#billing_postcode");
        country_input = $("#billing_country");

    }


    function selectBankAccount(id, status) {


        $('.bank_account').removeClass('selected');
        $('#besepa_ba_'+id).addClass('selected');

        bank_account_selected_input.val(id);
        bank_account_selected_status_input.val(status);

    }


    function createNewCustomer(name, taxId, email, firstName, postcode, address, country) {

        $.getJSON(window.ajax_url,
                  {   besepa_ajax_action: 'create_customer',
                      besepa_company_name: name,
                      besepa_tax_id: taxId,
                      besepa_first_name: firstName,
                      besepa_email: email,
                      billing_postcode: postcode,
                      billing_address: address,
                      billing_country: country
                  },
                  function (json) {

                        if(json.error || !json)
                        {

                            add_account_field_error.html(besepa_messages.error_creating_customer_in_besepa).fadeIn();
                            json = {};
                            json.error = true;

                        }

                        $(document).trigger('besepa_customer_created', [{
                            error:       json.error,
                            customer_id: json.customer_id
                        }]);

        });

    }

    function createNewBankAccount(iban, customer_id) {

        $.getJSON(window.ajax_url,
            {besepa_ajax_action: 'create_bank_account', besepa_iban: iban, besepa_customer_id: customer_id},
            function (json) {

                if(json.error || !json)
                {
                    add_account_field_error.html(besepa_messages.error_creating_bank_account_in_besepa).fadeIn();
                    json = {};
                    json.error = true;
                }

                $(document).trigger('besepa_bank_account_created', [{
                    error:        json.error,
                    bank_account: json.bank_account,
                    needs_mandate: json.needs_mandate
                }]);

            });
    }


    $(document).on('besepa_customer_created', function (e, result)
    {

        if(result.error){

            add_account_field_error.html(besepa_messages.error_creating_customer_in_besepa).fadeIn();
            $("#besepa_register_bc").removeAttr('disabled').html(besepa_messages.add_bank_account);

        }else{

            besepa_user.customer_id = result.customer_id;
            current_customer_input.val(besepa_user.customer_id);

            createNewBankAccount(new_bank_account_iban_input.val(), besepa_user.customer_id)
        }

    });

    $(document).on('besepa_bank_account_created', function (e, result)
    {

        $("#besepa_register_bc").removeAttr('disabled').html(besepa_messages.add_bank_account);
        new_bank_account_iban_input.val("");
        add_account_field_error.html("").fadeOut();

        if(result.error){

            add_account_field_error.html(besepa_messages.error_creating_bank_account_in_besepa).fadeIn();

        }else{

            if($("#besepa_ba_" + result.bank_account.id).attr("id"))
            {
                $("#besepa_ba_"+result.bank_account.id).fadeOut().fadeIn();

            }else{

                $("#besepa_no_bank_accounts").remove();
                var html = bank_row_tpl;
                html = html.replace('[iban]', result.bank_account.iban);
                html = html.replace('[id]', result.bank_account.id);
                html = html.replace('[id]', result.bank_account.id);
                html = html.replace('[status]', result.bank_account.status);
                html = html.replace('[status]', result.bank_account.status);
                bank_accounts_list.append($(html));

            }
            selectBankAccount(result.bank_account.id);

            if(result.needs_mandate)
            {
                tb_show('', result.bank_account.mandate_url + '#TB_iframe=true', false);
            }

        }



    });

    $(document).on('click', '.select_besepa_bank_account',function (e)
    {
        refreshDomSelection();
        e.preventDefault();

        selectBankAccount($(this).attr("data-id"), $(this).attr("data-status"));
    });

    $(document).on('click', '#besepa_register_bc', function (e)
    {
        refreshDomSelection();
        e.preventDefault();

        add_account_field_error.html("").fadeOut();


        if(!new_bank_account_iban_input.val())
            return add_account_field_error.html(besepa_messages.required_iban_number).fadeIn();

        if(!billing_company_input.val())
            return add_account_field_error.html(besepa_messages.required_company_name).fadeIn();

        if(!billing_taxid_input.val())
            return add_account_field_error.html(besepa_messages.required_tax_id).fadeIn();

        if(!billing_email_input.val())
            return add_account_field_error.html(besepa_messages.required_email).fadeIn();

        if(!billing_first_name_input.val())
            return add_account_field_error.html(besepa_messages.required_first_name).fadeIn();



        $(this).attr('disabled', 'disabled').html(besepa_messages.adding_bank_account);




        if(besepa_user.customer_id)
        {

            $.getJSON(window.ajax_url,
                {besepa_ajax_action: 'check_customer_id', besepa_customer_id: besepa_user.customer_id},
                function (json) {


                if(json)
                {
                    createNewBankAccount(new_bank_account_iban_input.val(), besepa_user.customer_id);

                }else{

                    createNewCustomer(
                        billing_company_input.val(),
                        billing_taxid_input.val(),
                        billing_email_input.val(),
                        billing_first_name_input.val());
                }


            });


        }else{

            var address = address1_input.val();
            if(address2_input.val())
            {
                address = address + " " + address2_input.val();
            }

            createNewCustomer(
                billing_company_input.val(),
                billing_taxid_input.val(),
                billing_email_input.val(),
                billing_first_name_input.val(),
                postcode_input.val(),
                address,
                country_input.val()
            );
        }


    });


    $(document).on('click', woocommerce_place_order_button_id,function (e)
    {

        refreshDomSelection();

        if(payment_method_checkbox_besepa.is(":checked")){
            if(!bank_account_selected_input.val()){
                add_account_field_error.html(besepa_messages.select_a_bank_account_required).fadeIn();
                e.preventDefault();
            }

        }

    });


});
