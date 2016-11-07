<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 7/11/16
 * Time: 17:35
 */

namespace Besepa\WCPlugin\Exception;


use Besepa\WCPlugin\Entity\CheckoutData;

class BankAccountUnsignedException extends \Exception
{

    /**
     * @var CheckoutData
     */
    public $checkoutData;

    function __construct(CheckoutData $checkoutData)
    {
        $this->checkoutData = $checkoutData;
        parent::__construct("Unsigned Bank Account");
    }

}