<?php

namespace Besepa\WCPlugin\Repository;


use Besepa\Client;
use Besepa\Entity\BankAccount;
use Besepa\Entity\Customer;
use Besepa\Entity\Debit;
use Besepa\Entity\Webhook;
use Besepa\WCPlugin\Exception\BankAccountUnsignedException;
use Besepa\WCPlugin\Exception\DebitCreationException;
use Besepa\WCPlugin\Entity\CheckoutData;
use Besepa\WCPlugin\Exception\PaymentProcessException;
use Besepa\WCPlugin\Exception\ResourceAlreadyExistsException;
use Besepa\WCPlugin\WordPress\UserManager;

class BesepaWCRepository
{

    const WEBHOOK_PARAM = 'besepa_ipn';
    const META_CUSTOMER_ID = 'besepa.customer_id';

    const SIGNATURE_MODE_FORCE        = "force";
    const SIGNATURE_MODE_FORM         = "form";
    const SIGNATURE_MODE_SMS          = "sms";

	/**
	 * @var Client
	 */
	private $client;

    private $signMode;

    /**
     * @var UserManager
     */
    private $userManager;

	function __construct(Client $client, UserManager $userManager) {
		$this->client = $client;
        $this->userManager = $userManager;
	}

	function configure( $api_key, $account_id, $sign_mode )
	{
		$this->client->init( $api_key, $account_id );
        $this->signMode = $sign_mode;
	}

	function getSignMode()
    {
        return $this->signMode?:static::SIGNATURE_MODE_FORCE;
    }


    /**
     * @return UserManager
     */
	function getUserManager()
    {
        return $this->userManager;
    }

	function getCurrentCustomer()
    {
        if($customer_id = $this->userManager->getUser()->getCustomerId())
        {
            return $this->client->getRepository("Customer")->find($customer_id);
        }
    }

	function getCustomer($customer_id)
    {
        return $this->client->getRepository("Customer")->find($customer_id);
    }

    function createCustomer(Customer $customer)
    {

        $repo = $this->client->getRepository("Customer");

        if($customer->id && $items = $repo->query($customer->id))
        {
            // if there is another customer and its email is equal to the requested email
            $entityInstance = $items[0];
            foreach ($items as $item){
                if($customer->id == $item->id)
                {
                    $entityInstance = $item;
                }
            }

            throw new ResourceAlreadyExistsException($entityInstance);
        }

        return $repo->create($customer);
    }

    /**
     * @param $bank_account_id
     * @param $customer_id
     *
     * @return BankAccount|null
     */
    function getBankAccount($bank_account_id, $customer_id)
    {
        return $this->client->getRepository("BankAccount", $customer_id)->find($bank_account_id);
    }

    function getBankAccounts($customer_id, $page=1)
    {
        return $this->client->getRepository("BankAccount", $customer_id)->findAll($page);
    }


    function createBankAccount(BankAccount $bankAccount, $customer_id)
    {
        $bankAccountRepo = $this->client->getRepository("BankAccount", $customer_id);


        if($items = $bankAccountRepo->query($bankAccount->iban))
        {
            foreach($items as $item)
            {
                if($item->iban == $bankAccount->iban)
                {
                    throw new ResourceAlreadyExistsException($item);
                }
            }
        }

        return $bankAccountRepo->create($bankAccount);
    }

    /**
     * @param CheckoutData $checkoutData
     * @return Debit|mixed
     */
    function createDebit(CheckoutData $checkoutData, Customer $customer)
    {


        /**
         * @var $order \WC_Order
         */
        $order       = $checkoutData->order;
        $bankAccount = $checkoutData->bankAccount;
        $amount      = (round($order->get_total(), 2) * 100);;


        $debit = new Debit();
        $debit->debtor_bank_account_id = $bankAccount->id;
        $debit->reference              = $order->id;
        $debit->description            = "WooCommerce order:{$order->id}, for customer {$customer->id} with email: {$order->billing_email}";
        $debit->amount                 = $amount;


        if($debit = $this->client->getRepository("Debit", $customer->id)->create($debit)){

            do_action("besepa.debit_created", $debit);

            return $debit;
        }

        throw new DebitCreationException();

    }

    function manualSignMandateInBankAccount(BankAccount $account)
    {

    }


	function process( CheckoutData $checkoutData, $subscription_payment = false )
	{



	    $customer = null;

	    if($subscription_payment)
        {

            if($checkoutData->order)
            {

                if(is_array($subscription_payment)){
                    $_subscription_payment = array_shift($subscription_payment);
                    $subscription_payment  = $_subscription_payment;
                }

                $customer_id = get_post_meta($subscription_payment->order->id, "besepa_customer_id", true);

                if($customer_id)
                {
                    $customer = $this->getCustomer($customer_id);
                }
            }



        }else{

            $customer = $this->getCustomer($checkoutData->selectedCustomerId);
        }

        if(!$customer)
        {
            throw new PaymentProcessException();
        }

        $checkoutData->bankAccount = $this->getBankAccount($checkoutData->selectedBankAccountId, $customer->id);


        if( $customer && $checkoutData->bankAccount )
        {

            if($checkoutData->bankAccount->status == BankAccount::STATUS_ACTIVE)
            {

                if ($debit = $this->createDebit($checkoutData, $customer)) {

                    add_post_meta($checkoutData->order->id, "besepa_bank_account_id", $checkoutData->bankAccount->id, true);
                    add_post_meta($checkoutData->order->id, "besepa_customer_id", $customer->id, true);
                    add_post_meta($checkoutData->order->id, "besepa_debit_id", $debit->id, true);

                    do_action("besepa.order_processed", $checkoutData->order, $debit, $customer);

                    return $debit;
                }

            }else{


                add_post_meta($checkoutData->order->id, "besepa_bank_account_id", $checkoutData->bankAccount->id, true);
                add_post_meta($checkoutData->order->id, "besepa_unsigned_bank_account_id", $checkoutData->bankAccount->id, true);

                add_post_meta($checkoutData->order->id, "besepa_customer_id", $customer->id, true);
                add_post_meta($checkoutData->order->id, "besepa_debit_id", null, true);
                add_post_meta($checkoutData->order->id, "besepa_bank_account_unsigned", 1, true);

                throw new BankAccountUnsignedException($checkoutData);
            }


        }

		throw new PaymentProcessException();
	}

	function processPendingOrder(\WC_Order $order, BankAccount $account)
    {


        $customer = $this->getCustomer(get_post_meta("besepa_customer_id", $order->post->ID, true));

        $checkoutData = new CheckoutData();
        $checkoutData->order = $order;
        $checkoutData->selectedCustomerId = $customer->id;
        $checkoutData->bankAccount = $account;

        if ($debit = $this->createDebit($checkoutData, $customer)) {

            update_post_meta($checkoutData->order->id, "besepa_bank_account_id", $checkoutData->bankAccount->id);
            update_post_meta($checkoutData->order->id, "besepa_customer_id", $customer->id);
            update_post_meta($checkoutData->order->id, "besepa_debit_id", $debit->id);
            delete_post_meta($checkoutData->order->id, "besepa_bank_account_unsigned");
            delete_post_meta($checkoutData->order->id, "besepa_unsigned_bank_account_id");

            do_action("besepa.order_processed", $checkoutData->order, $debit, $customer);

            return $debit;
        }

        return false;

    }


	function registerWebhook()
    {
        $create = true;
        $repo   = $this->client->getRepository('Webhook');
        if($webhook_id = get_option("besepa_webhook_id"))
        {
            $items = $repo->findAll();
            foreach($items as $hook)
            {
                if($hook->id == $webhook_id)
                {
                    $create = false;
                    break;
                }
            }

        }

        if($create)
        {
            $webhook = new Webhook();
            $webhook->url = (get_site_url() . '/?' . static::WEBHOOK_PARAM .'=true');

            if($webhook = $repo->create($webhook))
            {

                update_option("besepa_webhook_id", $webhook->id);
            }
        }



    }


}