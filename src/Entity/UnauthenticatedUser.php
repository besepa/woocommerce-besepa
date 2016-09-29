<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 30/8/16
 * Time: 17:49
 */

namespace Besepa\WCPlugin\Entity;


use Besepa\Entity\BankAccount;

class UnauthenticatedUser implements UserInterface
{

    public $ref;

    public $name;

    public $taxId;

    public $email;

    public $customerId;

    /**
     * @var \WC_Session_Handler
     */
    private $session;

    function __construct(\WC_Session_Handler $session)
    {
        $this->ref = 'wc_anon_' . $session->get_customer_id();
        $this->session = $session;
    }

    function setCustomerId($besepaCustomerId)
    {
        $this->session->set(UserInterface::CUSTOMERID_META, $besepaCustomerId);
    }


    function getID()
    {
        return null;
    }

    function getCustomerId()
    {
        if(!$this->customerId){
            $this->customerId = $this->session->get(UserInterface::CUSTOMERID_META);
        }
        return $this->customerId;
    }

    function getTaxID()
    {
        return $this->taxId;
    }

    function getRef()
    {
        return $this->ref;
    }

    function getEmail()
    {
        return $this->email;
    }

    function getName()
    {
        return $this->getName();
    }

    function addBankAccount(BankAccount $bankAccount)
    {
        $bank_accounts = $this->getBankAccounts();
        $bankAccounts[$bankAccount->id] = $bankAccount->iban;

        return $this->session->set(UserInterface::BANKACCOUNTS_META, json_encode($bank_accounts));
    }

    function getBankAccounts()
    {
        $bank_accounts = json_decode($this->session->get(UserInterface::BANKACCOUNTS_META), true);
        return $bank_accounts?:array();
    }
}