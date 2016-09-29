<?php

namespace Besepa\WCPlugin\Gateway;


use Besepa\Entity\BankAccount;
use Besepa\Exception\DebitCreationException;
use Besepa\WCPlugin\BesepaWooCommerce;
use Besepa\WCPlugin\Entity\CheckoutData;
use Besepa\WCPlugin\Entity\UnauthenticatedUser;
use Besepa\WCPlugin\Exception\BankAccountCreationException;
use Besepa\WCPlugin\Exception\PaymentProcessException;
use Besepa\WCPlugin\Extension\NifExtension;
use Besepa\WCPlugin\Repository\BesepaWCRepository;

class BesepaGateway extends \WC_Payment_Gateway
{

    const FILTER_ON_INSTANTIATED = 'besepa_wc_gateway.instantiated';

    public $id;

    public $icon;

    public $has_fields;

    public $method_title;

    public $method_description;


	public $subscription_support=false;


    /**
     * @var BesepaWCRepository
     */
    private $repository;

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
    	\add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'onSubscriptionPayment'), 10, 2);

	    \add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'onSubscriptionCancelled'), 10, 2);

	    if( is_admin() )
	    {
		    \add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
			              array( $this, 'process_admin_options' ) );

	    }

    }

    /**
     * When a scheduled payment for a subscription needs to be processed
     * @param $amount
     * @param \WC_Order $subscription
     */
    public function onSubscriptionPayment( $amount, \WC_Order $subscription )
    {


        $checkoutData = new CheckoutData();
        $checkoutData->order  = $subscription;
        $checkoutData->amount = $amount;
        $checkoutData->selectedBankAccountId = get_post_meta($checkoutData->order->id, "_besepa_bank_account_id", true);


        if($this->repository->process($checkoutData, true))
        {

            $subscription->add_order_note('Pago de suscripción ok');
            $subscription->update_status('completed');

            \WC_Subscriptions_Manager::process_subscription_payments_on_order( $subscription );
            $subscription->payment_complete();

        }else{

            $subscription->update_status( 'failed', sprintf( __( 'Besepa Transaction Failed', 'besepa' ) ) );
            \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $subscription );
        }

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
     * @param BesepaWCRepository $repository
     */
    public function setRepository(BesepaWCRepository $repository)
    {
        $this->repository = $repository;

        $this->repository->configure(
        	$this->get_option("api_key"),
	        $this->get_option("api_account_id")
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

        $besepaUser = $this->repository->getUserManager()->getUser();

        include BesepaWooCommerce::getViewsDir() . 'Gateway/bank_accounts_fields.php';
    }

    /**
     * Validation at checkout page
     * @return bool
     */
    public function validate_fields(){

        if(isset($_POST["payment_method"]) &&
           $_POST["payment_method"] == $this->id){


            if(isset($_POST["besepa_selected_bank_account_id"]) && trim($_POST["besepa_selected_bank_account_id"])){

                if(isset($_POST["besepa_current_customer_id"]) && trim($_POST["besepa_current_customer_id"])){

                    return true;

                }else{
                    wc_add_notice( apply_filters( 'woocommerce_checkout_required_field_notice', __("Debes seleccionar una cuenta bancaria de Besepa", "besepa") ) , 'error' );
                }

            }else{
                wc_add_notice( apply_filters( 'woocommerce_checkout_required_field_notice', __("Debes seleccionar una cuenta bancaria de Besepa", "besepa") ) , 'error' );
            }



        }
        return false;

    }


    /**
     * Payment processed at checkout page (for normal or subscriptions payments)
     * @param $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
	    $order = new \WC_Order( $order_id );

	    try{

	        $bank_account_id = $_POST["besepa_selected_bank_account_id"];
            $customer_id     = $_POST["besepa_current_customer_id"];

	        $checkoutData = new CheckoutData();
            $checkoutData->order                 = $order;
            $checkoutData->selectedBankAccountId = $bank_account_id;
            $checkoutData->selectedCustomerId    = $customer_id;


		    if($this->repository->process($checkoutData)){

                $order->reduce_order_stock();
			    $order->payment_complete();

			    if( $this->subscription_support &&
			        \WC_Subscriptions_Order::order_contains_subscription( $order_id ) )
			    {

				    \WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
				    $order->update_status('completed', __( 'Pedido de suscripción completado correctamente', 'besepa' ));

			    }

			    \WC()->cart->empty_cart();

			    return array(
				    'result' => 'success',
				    'redirect' => $this->get_return_url( $order )
			    );

		    }


	    }catch (PaymentProcessException $e)
        {
		    wc_add_notice( __('Payment error:', 'woothemes') . " ". __("error intentando realizar el cobro en la cuenta indicada", "besepa"), 'error' );
		    return;
	    }catch (DebitCreationException $e)
        {
            if(isset($e->field_messages->debtor_bank_account))
            {
                wc_add_notice( __('Payment error:', 'woothemes') . " ". __("La cuenta bancaria indicada no está activada", "besepa"), 'error' );
                return;
            }


        }

	    wc_add_notice( __('Payment error:', 'woothemes') . " ". __("error intentando realizar el cargo en la cuenta indicada", "besepa"), 'error' );
	    return;

    }


}