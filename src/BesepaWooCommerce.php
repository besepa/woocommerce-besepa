<?php

namespace Besepa\WCPlugin;


use Besepa\Client;
use Besepa\WCPlugin\Extension\CheckoutRequiredFieldsExtension;
use Besepa\WCPlugin\Gateway\BesepaGateway;
use Besepa\WCPlugin\WordPress\AssetsManager;
use Besepa\WCPlugin\WordPress\BesepaControllers;

class BesepaWooCommerce
{

    const LANG_DOMAIN = "besepa";

    const PREREQUISITES_FAIL = 0;
    const PREREQUISITES_WOOCOMMERCE = 1;
    const PREREQUISITES_ALL  = 2;

    static $PLUGIN_FILE_PATH = '';


    function __construct($plugin_file)
    {
        static::$PLUGIN_FILE_PATH = $plugin_file;
    }

    static function getViewsDir()
    {
        return \plugin_dir_path(self::$PLUGIN_FILE_PATH) . 'views/';
    }

    static function getAssetsUrl()
    {
        return \plugin_dir_url(self::$PLUGIN_FILE_PATH) . 'assets/';
    }


    function init()
    {
        if($this->checkPrerequisites() === static::PREREQUISITES_FAIL) return;


        $service = new BesepaService(new Client());

        \add_action( 'woocommerce_payment_gateways', array($this, "registerGateway") );

        \add_filter(BesepaGateway::FILTER_ON_INSTANTIATED,
                            function (BesepaGateway $gateway) use ($service)
        {

        	$gateway->setBesepaService($service);

	        if($this->checkPrerequisites() === static::PREREQUISITES_ALL){
	        	$gateway->setSubscriptionSupport(true);
	        }

	        do_action("besepa.gateway_instantiated");
        });

        $this->registerWooCommerceExtensions();
        $this->registerAssets();

        //Register ajax and ipn controllers
        $this->registerControllers($service);
    }



    function registerAssets()
    {
        $assetManager = new AssetsManager();

        \add_action( 'wp_enqueue_scripts', array($assetManager, 'registerCheckoutScripts') );
        \add_action( 'wp_enqueue_scripts', array($assetManager, 'registerCheckoutStyles') );

    }

    function registerControllers(BesepaService $service)
    {
        $ajaxControllers = new BesepaControllers($service);
        $ajaxControllers->registerAjaxControllers();
        $ajaxControllers->registerWebHookControllers();

    }

    function registerWooCommerceExtensions()
    {
	    $required_fields = new CheckoutRequiredFieldsExtension();
	    $required_fields->init();
    }

    function registerGateway( $methods )
    {
        $methods[] = "\\Besepa\\WCPlugin\\Gateway\\BesepaGateway";
        return $methods;
    }

    function checkPrerequisites()
    {

        if ( !class_exists( 'WC_Payment_Gateway' ) )
        {
            return static::PREREQUISITES_FAIL;
        }

        if ( !class_exists( 'WC_Subscriptions' ) )
        {
            return static::PREREQUISITES_WOOCOMMERCE;
        }

        return static::PREREQUISITES_ALL;

    }


}