<div id="besepa_page_content">

    <h1><?php _e("Paga con BESEPA para completar tu pedido", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></h1>

    <h4><?php _e("Por favor, introduzca su DNI/CIF o NIF", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></h4>


    <div class="form_container">
        <form action="" class="besepa_payment" method="post">

            <div class="field">
                <label for="besepa_tax_id"><?php _e("DNI/CIF/NIF", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></label>
                <input type="text" name="besepa_tax_id" id="besepa_tax_id">

            </div>

            <input type="hidden" name="besepa_action" value="collect_taxid">
            <div>
                <button class="cta"><?php _e("Siguiente", \Besepa\WCPlugin\BesepaWooCommerce::LANG_DOMAIN) ?></button>
            </div>
        </form>
    </div>


</div>