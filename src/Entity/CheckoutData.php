<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 30/8/16
 * Time: 18:34
 */

namespace Besepa\WCPlugin\Entity;


use Besepa\Entity\BankAccount;

class CheckoutData
{

    /**
     * @var \WC_Order
     */
    public $order;

    public $amount;

    /**
     * @var BankAccount
     */
    public $bankAccount;

    public $newUnsignedIban;

    public $selectedBankAccountId;

    public $selectedCustomerId;

    /**
     * @var UnauthenticatedUser
     */
    public $unauthenticatedUser;

}