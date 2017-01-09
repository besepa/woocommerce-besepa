<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 25/9/16
 * Time: 19:54
 */

namespace Besepa\WCPlugin\WordPress;


use Besepa\WCPlugin\BesepaWooCommerce;

class AssetsManager
{


    function registerCheckoutScripts()
    {
        if(!\is_checkout())
            return;

        wp_enqueue_script('besepa_checkout', BesepaWooCommerce::getAssetsUrl() . "js/checkout.bank_accounts.js", array('jquery', 'thickbox'), 1, true);


    }

    function registerCheckoutStyles()
    {

        if(!\is_checkout())
            return;


	    wp_enqueue_style('thickbox');
        wp_enqueue_style('besepa_checkout', BesepaWooCommerce::getAssetsUrl() . "css/checkout.css" );


    }

}