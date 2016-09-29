<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 26/9/16
 * Time: 2:15
 */

namespace Besepa\WCPlugin\WordPress;


use Besepa\Entity\BankAccount;
use Besepa\WCPlugin\Entity\UnauthenticatedUser;
use Besepa\WCPlugin\Entity\UserInterface;
use Besepa\WCPlugin\Entity\User;

class UserManager
{

    const PRE_BESEPA_CUSTOMER_CREATED_DATA_KEY = 'besepa.pre_customer_data';
    /**
     * @var UserInterface
     */
    private $user;

    /**
     * @var \WC_Session_Handler
     */
    private $session;



    function onInit(\WC_Session_Handler $session_Handler)
    {
        $this->session = $session_Handler;

        $this->setUpUser();

    }

    function setUpUser()
    {


        if($this->isAuthenticated())
        {
            $this->user = new User(get_userdata(get_current_user_id()));
        }else{
            $this->user = new UnauthenticatedUser($this->session);
        }
    }

    /**
     * @return UserInterface
     */
    function getUser()
    {
        return $this->user;
    }




    function isAuthenticated()
    {
        return (bool) get_current_user_id();
    }





}