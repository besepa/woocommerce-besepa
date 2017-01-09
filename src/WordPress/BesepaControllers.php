<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 26/9/16
 * Time: 1:57
 */

namespace Besepa\WCPlugin\WordPress;


use Besepa\Entity\BankAccount;
use Besepa\Entity\Mandate;
use Besepa\WCPlugin\BesepaService;
use Besepa\WCPlugin\BesepaWooCommerce;
use Besepa\WCPlugin\Entity\User;
use Besepa\WCPlugin\Exception\ResourceAlreadyExistsException;
use Besepa\WCPlugin\Gateway\BesepaGateway;

class BesepaControllers
{


    /**
     * @var BesepaService
     */
    private $service;

    function __construct(BesepaService $service)
    {

        $this->service  = $service;
    }

    function registerAjaxControllers()
    {
        // we can´t use the WordPress standard ajax calls because the need of having the Gateway instantiated
        add_action( 'besepa.gateway_instantiated', array($this, 'registerCheckoutAjaxControllers') );
    }

    function registerWebHookControllers()
    {

        add_action('init', array($this, 'listenBesepaWebhook'));
    }

    function registerCheckoutAjaxControllers()
    {

        if(is_checkout())
        {
            $this->createNewBankAccountAction();
        }

    }



    function createNewBankAccountAction()
    {
        $return = array("error" => __("Error creando cuenta bancaria", BesepaWooCommerce::LANG_DOMAIN));


        if(!get_current_user_id())
            return;

        $user = new User(get_current_user_id());


        if(!(isset($_GET["besepa_ajax_action"]) &&
            $_GET["besepa_ajax_action"]=="create_bank_account"))
            return;

        if( isset($_GET["besepa_iban"]) &&
            isset($_GET["besepa_customer_id"]))
        {

            if($_GET["besepa_customer_id"] != $user->getCustomerId())
                return;

            $sign_mode = $this->service->getSignatureMode();

            $bank_account       = new BankAccount();
            $bank_account->iban = $_GET["besepa_iban"];

            if($sign_mode == BesepaService::SIGNATURE_MODE_FORCE)
            {
                //firmado manual
                $bank_account->mandate = new Mandate();
                $bank_account->mandate->signed_at = date("Y/m/d");
            }

            if($sign_mode == BesepaService::SIGNATURE_MODE_SMS)
            {
                $bank_account->mandate = new Mandate();
                $bank_account->mandate->scheme = "B2B";
                $bank_account->mandate->signature_type = "sms";
            }


            try{


                /**
                 * @var $bank_account BankAccount
                 */
                if($bank_account = $this->service->createBankAccount($bank_account, $user)){

                    do_action("besepa.bank_account_created", $bank_account);

                    $signature_url_pending_mandate = isset($bank_account->mandate->signature_url) ?
                                                            $bank_account->mandate->signature_url : $bank_account->mandate->url;
                    $mandate_url                   = ($sign_mode == BesepaService::SIGNATURE_MODE_FORCE) ?
                                                            $bank_account->mandate->url : $signature_url_pending_mandate;

                    $return = array(
                        "error"       => false,
                        "bank_account" => array(
                            'id'            => $bank_account->id,
                            "iban"          => $bank_account->iban,
                            "status"        => $bank_account->status,
                            "mandate_url"   => $mandate_url,
                        ),
                        "needs_mandate" => ($bank_account->status == BankAccount::STATUS_PENDING_MANDATE && $signature_url_pending_mandate)

                    );
                }

            }catch (ResourceAlreadyExistsException $e) {

                $return = array(
                    "error"       => false,
                    "bank_account" => array(
                        'id'            => $e->entityInstance->id,
                        "iban"          => $e->entityInstance->iban,
                        "status"        => $e->entityInstance->status,
                        "mandate_url"   => isset($e->entityInstance->mandate->url) ? $e->entityInstance->mandate->url : null,
                    ),
                    "needs_mandate" => $sign_mode == BesepaService::SIGNATURE_MODE_FORCE || $e->entityInstance->status == BankAccount::STATUS_PENDING_MANDATE

                );

            }

        }

        wp_send_json($return);
    }



    function listenBesepaWebhook()
    {

        $result = array('error'=>false);


        if(isset($_GET[ BesepaService::WEBHOOK_PARAM ]))
        {


            $json = file_get_contents('php://input');
            $notification = json_decode($json, true);

            if(isset($notification["event"]))
            {



                switch ($notification["event"])
                {
                    case 'mandate.signed';

                        $processed_count=0;

                        $bank_account_data = $notification["data"];
                        if(isset($bank_account_data["id"]))
                        {


                            if($customer = $this->service->getBesepaCustomerById($bank_account_data["customer_id"]))
                            {

                                if($bank_account = $this->service->getCustomerBankAccount($bank_account_data["id"], $customer))
                                {
                                    $pending_orders = get_posts( array(
                                        'numberposts' => -1,
                                        'meta_key'    => BesepaGateway::META_ORDER_PAYMENT_ON_MANDATE,
                                        'meta_value'  => $bank_account->id,
                                        'post_type'   => 'shop_order',
                                        'post_status' => 'any',
                                    ));

                                    if(is_array($pending_orders))
                                    {
                                        foreach($pending_orders as $order_post)
                                        {
                                            $order = new \WC_Order($order_post->ID);
                                            if($order->get_status() == "pending")
                                            {

                                                try{
                                                    if($debit = $this->service->createDebit($order, $bank_account, $customer))
                                                    {
                                                        add_post_meta($order->id, BesepaGateway::META_BANK_ACCOUNT_ID, $bank_account->id, true);
                                                        add_post_meta($order->id, BesepaGateway::META_CUSTOMER_ID, $customer->id, true);
                                                        add_post_meta($order->id, BesepaGateway::META_DEBIT_ID, $debit->id, true);

                                                        $order->add_order_note( __('BESEPA: Adeudo creado correctamente', BesepaWooCommerce::LANG_DOMAIN) );
                                                        $order->payment_complete();

                                                        do_action("besepa.order_processed", $order, $debit, $customer);

                                                        $processed_count++;
                                                    }else{
                                                        $order->add_order_note( __('BESEPA: Error al crear adeudo al activarse la tarjeta', BesepaWooCommerce::LANG_DOMAIN) );
                                                    }
                                                }catch (\Exception $e)
                                                {
                                                    $order->add_order_note( __('BESEPA: Error al crear adeudo al activarse la tarjeta', BesepaWooCommerce::LANG_DOMAIN) );
                                                    $result["error"] = true;
                                                }

                                            }
                                        }
                                    }
                                    $result = array(
                                        "processed_count" => $processed_count
                                    );

                                }

                            }


}

                        break;
                }

            }

            if(isset($result["error"]) && $result["error"])
            {
                http_response_code(400);
            }else{
                http_response_code(200);
            }

            \wp_send_json($result);
        }


    }


}