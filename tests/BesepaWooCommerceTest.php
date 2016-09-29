<?php

namespace Besepa\WCPlugin\Test;


use Besepa\WCPlugin\BesepaWooCommerce;

class BesepaWooCommerceTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var BesepaWooCommerce
     */
    private $plugin;

    function setUp(){
        $this->plugin = new BesepaWooCommerce(null);
    }


    function testInitCalledWithoutExceptions()
    {
        $this->plugin->init(\WC()->session);
    }


    function testPrerequisites()
    {
        $this->assertEquals(BesepaWooCommerce::PREREQUISITES_FAIL, $this->plugin->checkPrerequisites());

        $this->getMockBuilder('WC_Payment_Gateway')->getMock();
        $this->assertEquals(BesepaWooCommerce::PREREQUISITES_WOOCOMMERCE, $this->plugin->checkPrerequisites());

        $this->getMockBuilder('WC_Subscriptions')->getMock();
        $this->assertEquals(BesepaWooCommerce::PREREQUISITES_ALL, $this->plugin->checkPrerequisites());

    }

    function testRegisterGateway(){
        $methods = $this->plugin->registerGateway(array());
        foreach($methods as $method){
            $this->assertTrue(class_exists($method));
        }
    }

}
