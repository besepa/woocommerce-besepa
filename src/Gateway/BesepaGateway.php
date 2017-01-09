<?php

namespace Besepa\WCPlugin\Gateway;


use Besepa\Entity\BankAccount;
use Besepa\Entity\Customer;
use Besepa\WCPlugin\BesepaService;
use Besepa\WCPlugin\BesepaWooCommerce;
use Besepa\WCPlugin\Entity\User;
use Besepa\WCPlugin\Exception\CustomerCreationException;

class BesepaGateway extends \WC_Payment_Gateway
{

    const FILTER_ON_INSTANTIATED = 'besepa_wc_gateway.instantiated';

    const META_DEBIT_ID        = "besepa_debit_id";
    const META_CUSTOMER_ID     = "besepa_customer_id";
    const META_BANK_ACCOUNT_ID = "besepa_bank_account_id";

    const META_ORDER_PAYMENT_ON_MANDATE = "besepa_payment_pending_mandate";

    public $id;

    public $icon;

    public $has_fields;

    public $method_title;

    public $method_description;


	public $subscription_support=false;


    private $payment_error=false;


    /**
     * @var BesepaService
     */
    private $service;

    function __construct()
    {
        $this->id                 = "besepa";
        $this->icon               = "http://www.besepa.com/img/logo-white.png";
        $this->has_fields         = true;
        $this->method_title       = __("Pagar mediante cuenta bancaria con Besepa", "besepa");
        $this->method_description = __("Mediante esta opción, realizaremos un cargo en la cuenta que nos facilites por el valor del importe de tu compra", "besepa");
	    $this->supports           = array(
	    	'subscriptions',
		    'subscription_cancellation',
		    'subscription_suspension',
		    'subscription_reactivation',
		    'subscription_amount_changes',
		    'subscription_date_changes',
		    'products' );


        $this->init_settings();

        $this->init_form_fields();



        $this->enabled            = $this->get_option("enabled");
        $this->title              = $this->get_option("title");
        $this->description        = $this->get_option("description");


		$this->initSubscribers();


        \apply_filters(static::FILTER_ON_INSTANTIATED, $this);
    }

    /**
     * Hooks configuration
     */
    public function initSubscribers()
    {

    	add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'onSubscriptionPayment'), 10, 2);
	    add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'onSubscriptionCancelled'), 10, 2);

	    if( is_admin() )
	    {
		    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	    }

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

    }



    /**
     * When a scheduled payment for a subscription needs to be processed
     * @param $amount
     * @param \WC_Order $subscription
     */
    public function onSubscriptionPayment( $amount, \WC_Order $order )
    {


        $bank_account_id = get_post_meta($order->id, static::META_BANK_ACCOUNT_ID, true);
        $customer_id     = get_post_meta($order->id, static::META_CUSTOMER_ID, true);

        $customer = $this->service->getBesepaCustomerById($customer_id);
        $account  = $this->service->getCustomerBankAccount($bank_account_id, $customer);


        if($account)
        {
            if($account->status == BankAccount::STATUS_ACTIVE)
            {
                try{

                    if($debit = $this->service->createDebit($order, $account, $customer, $amount))
                    {

                        $order->add_order_note( __('BESEPA: Adeudo periódico creado correctamente', BesepaWooCommerce::LANG_DOMAIN) );

                        \WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
                        $order->update_status('completed');
                        exit();
                    }




                }  catch (\Exception $e)
                {

                }

            }else{
                $order->add_order_note( __('BESEPA: No se puede cargar el adeudo periódico, la tarjeta no está activa', BesepaWooCommerce::LANG_DOMAIN) );

            }


        }
        $order->update_status( 'failed', sprintf( __( 'Besepa Transaction Failed', 'besepa' ) ) );
        \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
        $order->add_order_note( __('BESEPA: Error creado el adeudo bancario', BesepaWooCommerce::LANG_DOMAIN) );


        exit();

    }

    /**
     * Internal WooCommerce subscription cancellation
     * @param \WC_Subscription $subscription
     */
	public function onSubscriptionCancelled( \WC_Subscription $subscription )
	{
		\WC_Subscriptions_Manager::cancel_subscriptions_for_order( $subscription );
	}

    /**
     * @param bool $is_supported
     */
    public function setSubscriptionSupport($is_supported=false){
    	$this->subscription_support = $is_supported;
    }


    /**
     * @param BesepaService $service
     */
    public function setBesepaService(BesepaService $service)
    {
        $this->service = $service;

        $this->service->configure(
        	$this->get_option("api_key"),
	        $this->get_option("api_account_id"),
            $this->get_option("sign_mode")
        );
    }

    /**
     * Inputs for admin configuration form
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Activar / Desactivar', 'besepa' ),
                'label'		=> __( 'Activar esta pasarela de pago', 'besepa' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __( 'Título', 'besepa' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'El título que el usuario verá durante el proceso de compra', 'besepa' ),
                'default'	=> __( 'Cargo en cuenta bancaria', 'besepa' ),
            ),
            'description' => array(
                'title'		=> __( 'Descripción', 'besepa' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Descripción que el usuario verá durante el proceso de compra.', 'besepa' ),
                'default'	=> __( 'Pagar mediante un cargo en tu cuenta bancaria.', 'besepa' ),
                'css'		=> 'max-width:350px;'
            ),
            'api_key' => array(
                'title'		=> __( 'Api de Besepa', 'besepa' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Api de tu cuenta en Besepa.', 'besepa' ),
            ),
            'api_account_id' => array(
	            'title'		=> __( 'ID Account de Besepa', 'besepa' ),
	            'type'		=> 'text',
	            'desc_tip'	=> __( 'EL identificador de tu cuenta en Besepa.', 'besepa' ),
            ),
            'sign_mode' => array(
                'title'		  => __( 'Modo de firmado de cuentas', 'besepa' ),
                'label'		  => __( 'Firmado de cuentas', 'besepa' ),
                'type'		  => 'select',
                'desc_tip' => __( 'Al añadir una cuenta bancaria, qué criterio establecer para firmar sus mandatos.', 'besepa' ),
                'default'	  => 'force',
                'options'     => array(
                    BesepaService::SIGNATURE_MODE_FORCE => __('Forzar firmado', 'besepa'),
                    BesepaService::SIGNATURE_MODE_FORM => __('Firmado por formulario', 'besepa'),
                    BesepaService::SIGNATURE_MODE_SMS => __('Firmado por SMS', 'besepa')
                )
            ),
            'environment' => array(
                'title'		  => __( 'Modo test', 'besepa' ),
                'label'		  => __( 'Activar el modo de pruebas', 'besepa' ),
                'type'		  => 'checkbox',
                'description' => __( 'Utilizar el API en modo de pruebas.', 'besepa' ),
                'default'	  => 'no',
            )
        );

    }

    /**
     * Html fields for besepa option in checkout page
     */
    public function payment_fields()
    {

        _e("Se realizará el pago mediante un adeudo en cuenta bancaria", BesepaWooCommerce::LANG_DOMAIN);
    }



    /**
     * Payment processed at checkout page (for normal or subscriptions payments)
     * @param $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {

        $order = new \WC_Order( $order_id );

        return array(
            'result' 	=> 'success',
            'redirect'	=> $order->get_checkout_payment_url( true )
        );


    }

    function receipt_page( $order_id )
    {

        $this->processBesepaDirectPayment();



        $payment_error = $this->payment_error;

        $error  = false;
        $order  = new \WC_Order( $order_id );
        $user   = new User(get_current_user_id());
        $besepa = $this->service;

        $bank_accounts = array();


        if(!$user->getTaxId() && !$user->getCustomerId())
        {
            if(!$this->processCollectTaxId($user))
            {
                include BesepaWooCommerce::getViewsDir() . '/taxid-page.php';
                return;
            }

        }

        if(!$user->getCustomerId())
        {

            $customer = new Customer();
            $customer->name               = $order->billing_company;
            $customer->taxid              = $user->getTaxId();
            $customer->contact_name       = $order->billing_first_name . " " . $order->billing_last_name;
            $customer->contact_email      = $order->billing_email;
            $customer->address_postalcode = $order->billing_postcode;
            $customer->address_street     = $order->billing_address_1 . " \n" . $order->billing_address_2;
            $customer->address_city       = $order->billing_city;
            $customer->address_state      = $order->billing_state;
            $customer->address_country    = $order->billing_country;

            try{
                $this->service->createCustomerToUser($customer, $user);
            }catch (CustomerCreationException $e)
            {
                $error = true;
                if(WP_DEBUG)
                    wp_die("BESEPA CUSTOMER CREATING ERROR:" . $e->getMessage());
            }
        }

        if(!$error)
        {

            $bank_accounts = $this->service->getUserBankAccounts($user);
            $url_success   = $this->get_return_url($order);
            $url_cancel    = $order->get_cancel_order_url(true);
        }

        include BesepaWooCommerce::getViewsDir() . '/payment-page.php';

    }

    private function processBesepaDirectPayment()
    {

        if(isset($_POST["besepa_action"]) && $_POST["besepa_action"]=="make_debit")
        {

            if(!isset($_POST["besepa_order_id"]) ||
                !isset($_POST["besepa_url_cancel"]) ||
                !isset($_POST["besepa_url_success"]) ||
                !isset($_POST["besepa_bank_account_id"]))
            {
                wp_die(__("Faltan parámetros", BesepaWooCommerce::LANG_DOMAIN));
            }

            if($order = new \WC_Order($_POST["besepa_order_id"]))
            {


                $user     = new User(get_current_user_id());
                $account  = $this->service->getUserBankAccount($_POST["besepa_bank_account_id"], $user);
                $customer = $this->service->getBesepaCustomer($user);

                if($account)
                {
                    if($account->status == BankAccount::STATUS_ACTIVE)
                    {
                        try{

                            if($debit = $this->service->createDebit($order, $account, $customer))
                            {
                                add_post_meta($order->id, static::META_BANK_ACCOUNT_ID, $account->id, true);
                                add_post_meta($order->id, static::META_CUSTOMER_ID, $customer->id, true);
                                add_post_meta($order->id, static::META_DEBIT_ID, $debit->id, true);

                                $order->add_order_note( __('BESEPA: Adeudo creado correctamente', BesepaWooCommerce::LANG_DOMAIN) );
                                $order->payment_complete();
                                $order->update_status('completed');

                                do_action("besepa.order_processed", $order, $debit, $customer);

                                wp_redirect($_POST["besepa_url_success"]);
                                exit();
                            }



                        }  catch (\Exception $e)
                        {

                        }

                    }else if($account->status == BankAccount::STATUS_PENDING_MANDATE){

                        $order->add_order_note( __('BESEPA: El pedido queda en espera de firma de mandato', BesepaWooCommerce::LANG_DOMAIN) );
                        update_post_meta($order->post->ID, static::META_ORDER_PAYMENT_ON_MANDATE, $account->id);

                        wp_redirect($_POST["besepa_url_success"]);
                        exit();

                    }


                }
                $order->add_order_note( __('BESEPA: Error creado el adeudo bancario', BesepaWooCommerce::LANG_DOMAIN) );
                $this->payment_error = true;

            }

        }

    }

    private function processCollectTaxId(User $user)
    {

        if(isset($_POST["besepa_action"]) && $_POST["besepa_action"]=="collect_taxid")
        {

            if(!empty($_POST["besepa_tax_id"])){
                $user->setTaxId($_POST["besepa_tax_id"]);
                return true;
            }

        }

        return false;

    }

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 * @return bool was anything saved?
	 */
    public function process_admin_options()
    {
    	$result = parent::process_admin_options();

	    //register webhook required for besepa bankaccount activations
	    $this->service->registerWebhook();

	    return $result;
    }



}