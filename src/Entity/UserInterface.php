<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 30/8/16
 * Time: 17:43
 */

namespace Besepa\WCPlugin\Entity;


use Besepa\Entity\BankAccount;

interface UserInterface
{

    const TAXID_META        = "besepa.tax_id";
    const BANKACCOUNTS_META = "besepa.bank_accounts";
    const CUSTOMERID_META   = "besepa.customer_id";

    function getTaxID();

    function getRef();

    function getName();

    function getEmail();

    function getID();

    function getCustomerId();

    function setCustomerId($besepaCustomerId);


    function addBankAccount(BankAccount $bankAccount);

    function getBankAccounts();


}