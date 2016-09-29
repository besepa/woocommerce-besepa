<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 29/8/16
 * Time: 18:24
 */

namespace Besepa\WCPlugin\Extension;


class NifExtension
{

    const FIELD_NAME     = "besepa_taxid";
    const FIELD_META_KEY = "besepa_taxid";

    function init(){


        add_action('woocommerce_before_checkout_billing_form', array($this, 'addField'));
        add_action('woocommerce_checkout_process', array($this, 'checkField'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'updateOrderWithFieldData') );
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'showInAdminOrder') , 10, 1 );
        add_filter('woocommerce_email_order_meta_keys', array($this, 'showInOrderEmail'));
    }

    function addField($checkout)
    {


        woocommerce_form_field( static::FIELD_NAME , array(
            'type'          => 'text',
            'class'         => array('my-field-class form-row-wide'),
            'label'         => __('Tu NIF-DNI/CIF', 'besepa'),
            'required'      => true,
            'placeholder'   => __('Introduzca el Nº NIF-DNI o CIF', 'besepa'),
        ), $checkout->get_value( static::FIELD_NAME ));

    }

    function checkField()
    {
        // Comprueba si se ha introducido un valor y si está vacío se muestra un error.
        if ( !isset($_POST[static::FIELD_NAME]) || ! $_POST[static::FIELD_NAME] )
            wc_add_notice( __( 'NIF-DNI/CIF, es un campo requerido. Debe de introducir su NIF DNI o CIF para finalizar la compra.', 'besepa' ), 'error' );

    }


    function updateOrderWithFieldData($order_id)
    {
        if ( isset($_POST[static::FIELD_NAME]) && ! empty( $_POST[static::FIELD_NAME] ) ) {
            update_post_meta( $order_id, self::FIELD_META_KEY, sanitize_text_field( $_POST[static::FIELD_NAME] ) );
        }
    }



    function showInAdminOrder($order)
    {
        echo '<p><strong>'.__('NIF/CIF', "besepa").':</strong> ' . get_post_meta( $order->id, self::FIELD_META_KEY, true ) . '</p>';
    }



    function showInOrderEmail( $keys ) {
        $keys[] = self::FIELD_META_KEY;
        return $keys;
    }

}