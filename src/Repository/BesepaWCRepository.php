<?php

namespace Besepa\WCPlugin\Repository;


use Besepa\Client;
use Besepa\Entity\BankAccount;
use Besepa\Entity\Customer;
use Besepa\Entity\Debit;
use Besepa\Entity\Webhook;
use Besepa\WCPlugin\Exception\DebitCreationException;
use Besepa\WCPlugin\Entity\CheckoutData;
use Besepa\WCPlugin\Entity\UserInterface;
use Besepa\WCPlugin\Exception\PaymentProcessException;
use Besepa\WCPlugin\Exception\ResourceAlreadyExistsException;
use Besepa\WCPlugin\WordPress\UserManager;

class BesepaWCRepository
{

    const WEBHOOK_PARAM = '';
    const META_CUSTOMER_ID = 'besepa.customer_id';

	/**
	 * @var Client
	 */
	private $client;

    /**
     * @var UserManager
     */
    private $userManager;

	function __construct(Client $client, UserManager $userManager) {
		$this->client = $client;
        $this->userManager = $userManager;
	}

	function configure( $api_key, $account_id )
	{
		$this->client->init( $api_key, $account_id );
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

        if($items = $repo->query($customer->taxid))
        {
            // if there is another customer and its email is equal to the requested email
            $entityInstance = $items[0];
            foreach ($items as $item){
                if($customer->contact_email == $item->contact_email)
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


    function createBankAccount(BankAccount $bankAccount, $customer_id)
    {
        $bankAccountRepo = $this->client->getRepository("BankAccount", $customer_id);

        if($items = $bankAccountRepo->query($bankAccount->iban))
        {
            throw new ResourceAlreadyExistsException($items[0]);
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


	function process( CheckoutData $checkoutData, $subscription_payment = false )
	{

	    $customer = null;

	    if($subscription_payment)
        {
            if($checkoutData->order && $customer_id = get_post_meta($checkoutData->order->id, "besepa_customer_id", true))
            {
                $customer = $this->getCustomer($customer_id);
            }

        }else{

            $customer = $this->getCustomer($checkoutData->selectedCustomerId);
        }

        $checkoutData->bankAccount = $this->getBankAccount($checkoutData->selectedBankAccountId, $customer->id);


        if( $customer && $checkoutData->bankAccount )
        {

            if($checkoutData->bankAccount->status = BankAccount::STATUS_ACTIVE)
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
                add_post_meta($checkoutData->order->id, "besepa_customer_id", $customer->id, true);
                add_post_meta($checkoutData->order->id, "besepa_debit_id", null, true);
                add_post_meta($checkoutData->order->id, "besepa_bank_account_unsigned", 1, true);

                return false;
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

            add_post_meta($checkoutData->order->id, "besepa_bank_account_id", $checkoutData->bankAccount->id, true);
            add_post_meta($checkoutData->order->id, "besepa_customer_id", $customer->id, true);
            add_post_meta($checkoutData->order->id, "besepa_debit_id", $debit->id, true);

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
            $webhook->url = (get_site_url() . '/?besepa_webhook_listener=true');

            if($webhook = $repo->create($webhook))
            {

                update_option("besepa_webhook_id", $webhook->id);
            }
        }



    }
}