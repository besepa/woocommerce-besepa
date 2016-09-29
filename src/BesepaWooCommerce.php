<?php

namespace Besepa\WCPlugin;


use Besepa\Client;
use Besepa\WCPlugin\Extension\NifExtension;
use Besepa\WCPlugin\Gateway\BesepaGateway;
use Besepa\WCPlugin\Repository\BesepaWCRepository;
use Besepa\WCPlugin\WordPress\AjaxControllers;
use Besepa\WCPlugin\WordPress\AssetsManager;
use Besepa\WCPlugin\WordPress\UserManager;

class BesepaWooCommerce
{

    const PREREQUISITES_FAIL = 0;
    const PREREQUISITES_WOOCOMMERCE = 1;
    const PREREQUISITES_ALL  = 2;

    static $PLUGIN_FILE_PATH = '';

    private $userManager;

    function __construct($plugin_file)
    {
        static::$PLUGIN_FILE_PATH = $plugin_file;
        $this->userManager = new UserManager();
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

        $this->initUserManager();

        $besepaRepository = new BesepaWCRepository(new Client(), $this->userManager);

        \add_action( 'woocommerce_payment_gateways', array($this, "registerGateway") );

        $plugin = $this;

        \add_filter(BesepaGateway::FILTER_ON_INSTANTIATED, function (BesepaGateway $gateway)
                                                                use ($besepaRepository)
        {

        	$gateway->setRepository($besepaRepository);

	        if($this->checkPrerequisites() === static::PREREQUISITES_ALL){
	        	$gateway->setSubscriptionSupport(true);
	        }

	        do_action("besepa.gateway_instantiated");
        });

        $this->registerWooCommerceExtensions();
        $this->registerAssets();
        $this->registerAjaxControllers($besepaRepository);


    }

    function initUserManager()
    {

        if(\is_admin())
            return;

        $userManager = $this->userManager;

        \add_action('woocommerce_init', function () use($userManager){
            $userManager->onInit(\WooCommerce::instance()->session);
        });

    }

    function registerAssets()
    {
        $assetManager = new AssetsManager($this->userManager);

        \add_action( 'wp_enqueue_scripts', array($assetManager, 'registerCheckoutScripts') );
        \add_action( 'wp_enqueue_scripts', array($assetManager, 'registerCheckoutStyles') );

    }

    function registerAjaxControllers(BesepaWCRepository $besepaRepository)
    {
        $ajaxControllers = new AjaxControllers($besepaRepository);
        $ajaxControllers->registerControllers();
    }

    function registerWooCommerceExtensions()
    {
        $nif_extension = new NifExtension();
        $nif_extension->init();
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