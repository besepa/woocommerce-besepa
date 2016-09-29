<?php

namespace Besepa\WCPlugin\Repository;


use Besepa\Client;
use Besepa\Entity\BankAccount;
use Besepa\Entity\Customer;
use Besepa\Entity\Debit;
use Besepa\Exception\DebitCreationException;
use Besepa\WCPlugin\Entity\CheckoutData;
use Besepa\WCPlugin\Entity\UserInterface;
use Besepa\WCPlugin\Exception\ResourceAlreadyExistsException;
use Besepa\WCPlugin\WordPress\UserManager;

class BesepaWCRepository
{

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

    function getBankAccount($bank_account_id, $customer_id)
    {
        return $this->client->getRepository("BankAccount", $customer_id)->find($bank_account_id, true);
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


        $order       = $checkoutData->order;
        $bankAccount = $checkoutData->bankAccount;
        $amount      = (round($order->get_total(), 2) * 100);;


        $debit = new Debit();
        $debit->debtor_bank_account_id = $bankAccount->id;
        $debit->reference   = $order->id;
        $debit->description = "WooCommerce order:{$order->id}, for customer {$customer->id} with email: {$order->billing_email}";
        $debit->amount      = $amount;


        if($debit = $this->client->getRepository("Debit", $customer->id)->create($debit, true)){

            do_action("besepa.debit_created", $debit);

            return $debit;
        }

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




            if ($debit = $this->createDebit($checkoutData, $customer)) {

                add_post_meta($checkoutData->order->id, "besepa_bank_account_id", $checkoutData->bankAccount->id, true);
                add_post_meta($checkoutData->order->id, "besepa_customer_id", $customer->id, true);
                add_post_meta($checkoutData->order->id, "besepa_debit_id", $debit->id, true);

                do_action("besepa.order_processed", $checkoutData->order, $debit, $customer);

                return true;
            }



        }

		return false;
	}
}