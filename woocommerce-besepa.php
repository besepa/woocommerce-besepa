<?php

/*
Plugin Name: Besepa for WooCommerce
Plugin URI:  https://besepa.com
Description: Besepa gateway for WooCommerce and WooCommerce Subscriptions
Version:     1.0
Author:      Besepa
Author URI:  https://besepa.com
License:     MIT
License URI: https://opensource.org/licenses/MIT
Domain Path: /languages
Text Domain: besepa
*/
if(!class_exists('Composer\Autoload\ClassLoader') ||
   is_dir(__DIR__ . "/vendor"))
{
	include __DIR__ . '/bootstrap_include.php';
}




use Besepa\WCPlugin\BesepaWooCommerce;


add_action("plugins_loaded", function() {

    $plugin = new BesepaWooCommerce(__FILE__);

    if( BesepaWooCommerce::PREREQUISITES_FAIL !== $plugin->checkPrerequisites() )
    {
        $plugin->init();

    } else if( \is_admin() )
    {

        \add_action( 'admin_notices', function ()
        {
            $message = __( 'El plugin de WooCommerce debe estar instalado para que funcione Besepa for WooCommerce', 'besepa' );

            printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
        });

    }


});

