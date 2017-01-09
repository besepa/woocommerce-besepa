<?php
/**
 *
 *
 * @author Asier Marqués <asiermarques@gmail.com>
 */

namespace Besepa\WCPlugin;


use Besepa\Client;
use Besepa\Entity\BankAccount;
use Besepa\Entity\Customer;
use Besepa\Entity\Debit;
use Besepa\Entity\Webhook;
use Besepa\WCPlugin\Entity\User;
use Besepa\WCPlugin\Exception\CustomerCreationException;
use Besepa\WCPlugin\Exception\DebitCreationException;
use Besepa\WCPlugin\Exception\ResourceAlreadyExistsException;

class BesepaService
{

    const SIGNATURE_MODE_FORCE        = "force";
    const SIGNATURE_MODE_FORM         = "form";
    const SIGNATURE_MODE_SMS          = "sms";

    const WEBHOOK_PARAM               = 'besepa_ipn';

    /**
     * @var Client
     */
    private $client;

    private $signatureMode;

    function __construct(Client $client)
    {
        $this->client = $client;
    }

    function configure( $api_key, $account_id, $signature_mode )
    {
        $this->client->init( $api_key, $account_id );
        $this->signatureMode = $signature_mode;
    }

    function getSignatureMode()
    {
        return $this->signatureMode?:static::SIGNATURE_MODE_FORCE;
    }


    /**
     * @param $customer_id
     * @return Customer|null
     */
    function getBesepaCustomerById($customer_id)
    {
        return $this->client->getRepository("Customer")->find($customer_id);
    }

    /**
     * @param User $user
     * @return Customer|null
     */
    public function getBesepaCustomer(User $user)
    {
        if(!$user->getCustomerId())
            return null;

        return $this->getBesepaCustomerById($user->getCustomerId());
    }


    function createCustomerToUser(Customer $customer, User $user)
    {

        $repo = $this->client->getRepository("Customer");

        /**
         * @var $customer Customer
         */
        if($customer = $repo->create($customer))
        {
            $user->setCustomerId($customer->id);
            return;
        }

        throw new CustomerCreationException();
    }

    /**
     * @param BankAccount $bankAccount
     * @param User $user
     * @return mixed|null
     * @throws ResourceAlreadyExistsException
     */
    function createBankAccount(BankAccount $bankAccount, User $user)
    {
        if(!$user->getCustomerId())
            return null;

        $bankAccountRepo = $this->client->getRepository("BankAccount", $user->getCustomerId());


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
     * @param User $user
     * @param int $page
     * @return array
     */
    public function getUserBankAccounts(User $user, $page=1)
    {
        if(!$user->getCustomerId())
            return array();

        return $this->client->getRepository("BankAccount", $user->getCustomerId())->findAll($page);
    }


    /**
     * @param $bank_account_id
     * @param User $user
     * @return BankAccount|null
     */
    function getUserBankAccount($bank_account_id, User $user)
    {
        if(!$user->getCustomerId())
            return null;

        return $this->client->getRepository("BankAccount", $user->getCustomerId())->find($bank_account_id);
    }


    /**
     * @param $bank_account_id
     * @param Customer $customer
     * @return mixed
     */
    function getCustomerBankAccount($bank_account_id, Customer $customer)
    {
        return $this->client->getRepository("BankAccount", $customer->id)->find($bank_account_id);
    }


    function createDebit(\WC_Order $order, BankAccount $bankAccount, Customer $customer, $force_amount=null)
    {

        if($force_amount!==null)
        {
            $amount = $force_amount;

        }else{

            $amount      = (round($order->get_total(), 2) * 100);;
        }


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