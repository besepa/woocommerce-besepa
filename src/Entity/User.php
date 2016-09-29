<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 30/8/16
 * Time: 17:42
 */

namespace Besepa\WCPlugin\Entity;


use Besepa\Entity\BankAccount;

class User implements UserInterface
{

    /**
     * @var \WP_User
     */
    private $user;

    function __construct(\WP_User $user)
    {
        $this->user = $user;
    }

    function getID()
    {
        return $this->user->ID;
    }

    function getCustomerId()
    {
        return get_user_meta($this->user->ID, UserInterface::CUSTOMERID_META, true);
    }

    function setCustomerId($besepaCustomerId)
    {
        return update_user_meta($this->user->ID, UserInterface::CUSTOMERID_META, $besepaCustomerId);
    }

    function getTaxID()
    {
        return update_user_meta($this->user->ID, UserInterface::TAXID_META);
    }

    function getRef()
    {
       return "wc_user_" . $this->user->ID;
    }

    function getEmail()
    {
        return $this->user->user_email;
    }

    function getName()
    {
        return $this->user->display_name;
    }

    function addBankAccount(BankAccount $bankAccount)
    {
        $bank_accounts = $this->getBankAccounts();
        $bank_accounts[$bankAccount->id] = $bankAccount;

        update_user_meta($this->user->ID, UserInterface::BANKACCOUNTS_META, json_encode($bank_accounts));
    }

    function getBankAccounts()
    {
        $bank_accounts = json_decode(get_user_meta($this->user->ID, UserInterface::BANKACCOUNTS_META, true), true);
        return $bank_accounts?:array();
    }
}