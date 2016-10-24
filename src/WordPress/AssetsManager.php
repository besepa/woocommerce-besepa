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
    /**
     * @var UserManager
     */
    private $userManager;

    function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    function registerCheckoutScripts()
    {
        if(!\is_checkout())
            return;


        ob_start();


        $userManager = $this->userManager;
        include BesepaWooCommerce::getViewsDir() . 'Checkout/inline-javascript.php';

        $jsContent = ob_get_clean();

        wp_enqueue_script('besepa_checkout', BesepaWooCommerce::getAssetsUrl() . "js/checkout.bank_accounts.js", array('jquery', 'thickbox'), 1, true);
        wp_add_inline_script( 'besepa_checkout', $jsContent, 'before' );



    }

    function registerCheckoutStyles()
    {
        if(!\is_checkout())
            return;


	    wp_enqueue_style('thickbox');
        wp_enqueue_style('besepa_checkout', BesepaWooCommerce::getAssetsUrl() . "css/checkout.css" );


    }

}