<div id="besepa_page_content">

    <?php if($error){ ?>

        <h1><?php _e("No se puede utilizar BESEPA en estos momentos", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></h1>


    <?php }else{ ?>


        <?php if($payment_error){ ?>
            <div class="error"><?php _e("Error procesando el pago", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></div>
        <?php } ?>

        <h1><?php _e("Paga con BESEPA para completar tu pedido", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></h1>

        <h4><?php _e("Selecciona una cuenta bancaria", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></h4>

        <div class="right">
            <button id="besepa_add_new"><?php _e("+ Añadir una nueva cuenta", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></button>
        </div>

        <div class="form_container" id="besepa_add_new_form_container" style="display: none">

               <div id="besepa_error" class="error" style="display:none;"></div>

                <div class="field">
                    <label for="besepa_iban"><?php _e("IBAN de la cuenta bancaria", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></label>
                    <input name="iban" type="text" id="besepa_iban">
                    <input type="hidden" name="besepa_customer_id" id="besepa_customer_id" value="<?php echo $user->getCustomerId() ?>">
                </div>
                <div class="action">
                    <button class="cta"
                            data-working-text="<?php _e("Creando cuenta en besepa...", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?>"
                            id="besepa_add_account"><?php _e("Añadir", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></button>
                </div>

        </div>

        <form action="" class="besepa_payment" method="POST">
            <table class="besepa_table">
                <thead>
                <tr>
                    <th>
                        <?php _e("Número de cuenta", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></th>
                    <th class="center">
                        <?php _e("Seleccionar", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?>
                    </th>
                </tr>
                </thead>
                <tbody id="bank_accounts">
                    <?php if(!count($bank_accounts)){ ?>
                    <tr>
                        <td colspan="2" id="besepa_no_bank_accounts"><?php _e("No tienes número de cuentas asociadas", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></td>
                    </tr>
                    <?php }else{ ?>

                        <?php foreach ($bank_accounts as $i=>$bank_account){ ?>
                            <tr id="besepa_ba_<?php echo $bank_account->id ?>"
                                class="bank_account <?php echo 0==$_count++ ? "selected": "" ?>">

                                <td><?php echo $bank_account->iban ?></td>
                                <td class="select center">
                                    <label>
                                        <input type="radio"
                                               name="besepa_bank_account_id"
                                               value="<?php echo $bank_account->id ?>"
                                               <?php echo !$i ? 'checked="checked"' : ''?>
                                        >
                                    </label>
                                </td>



                            </tr>
                        <?php } ?>

                    <?php } ?>
                </tbody>
            </table>


            <input type="hidden" name="besepa_url_success" value="<?php echo $url_success ?>">
            <input type="hidden" name="besepa_url_cancel" value="<?php echo $url_cancel ?>">
            <input type="hidden" name="besepa_order_id" value="<?php echo $order->id ?>">
            <input type="hidden" name="besepa_action" value="make_debit">
            <div>
                <button id="besepa_make_payment" <?php echo !count($bank_accounts) ? "disabled":"" ?> class="cta"><?php _e("Realizar el pago", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></button>
            </div>
        </form>

    <?php } ?>

</div>

<script type="text/javascript">
    var ajax_url = "<?php echo get_permalink() ?>";
    var select_bank_account = "<?php _e("Seleccionar") ?>";
</script>
