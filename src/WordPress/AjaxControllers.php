<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 26/9/16
 * Time: 1:57
 */

namespace Besepa\WCPlugin\WordPress;


use Besepa\Entity\BankAccount;
use Besepa\Entity\Customer;
use Besepa\Entity\Mandate;
use Besepa\WCPlugin\Exception\ResourceAlreadyExistsException;
use Besepa\WCPlugin\Repository\BesepaWCRepository;

class AjaxControllers
{

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var BesepaWCRepository
     */
    private $repository;

    function __construct(BesepaWCRepository $repository)
    {

        $this->repository  = $repository;
        $this->userManager = $repository->getUserManager();
    }

    function registerControllers()
    {
        // we can´t use the WordPress standard ajax calls because the need of having the Gateway instantiated
        add_action( 'besepa.gateway_instantiated', array($this, 'registerCheckoutAjaxControllers') );
    }

    function registerCheckoutAjaxControllers()
    {

        if(is_checkout())
        {
            $this->createNewCustomerAction();
            $this->createNewBankAccountAction();
            $this->checkIfCustomerExistsAction();
        }

    }

    function createNewCustomerAction()
    {

        if(!(isset($_GET["besepa_ajax_action"]) && $_GET["besepa_ajax_action"]=="create_customer"))
            return;


        $return = array("error" => true);


        if( isset($_GET["besepa_company_name"]) &&
            isset($_GET["besepa_tax_id"]) &&
            isset($_GET["besepa_first_name"]) &&
            isset($_GET["besepa_email"]))
        {

            $customer = new Customer();


            $customer->name           = $_GET["besepa_company_name"];
            $customer->taxid          = $_GET["besepa_tax_id"];
            $customer->contact_name   = $_GET["besepa_first_name"];
            $customer->contact_email  = $_GET["besepa_email"];
            $customer->reference      = $this->userManager->getUser()->getRef();


            try{

                if($customer = $this->repository->createCustomer($customer)){

                    $this->userManager->getUser()->setCustomerId($customer->id);

                    do_action("besepa.customer_created", $customer);

                    $return = array(
                        "error"       => false,
                        "customer_id" => $customer->id
                    );
                }

            }
            catch (ResourceAlreadyExistsException $e)
            {
                $this->userManager->getUser()->setCustomerId($e->entityInstance->id);
                $return = array(
                    "error"       => false,
                    "customer_id" => $e->entityInstance->id
                );
            }



        }



        wp_send_json($return);

    }



    function createNewBankAccountAction()
    {
        $return = array("error" => true);

        $sign_mode = BesepaWCRepository::SIGNATURE_MODE_FORCE;

        if(!(isset($_GET["besepa_ajax_action"]) && $_GET["besepa_ajax_action"]=="create_bank_account"))
            return;

        if( isset($_GET["besepa_iban"]) &&
            isset($_GET["besepa_customer_id"]))
        {

            $bank_account = new BankAccount();
            $bank_account->iban = $_GET["besepa_iban"];

            if($sign_mode == BesepaWCRepository::SIGNATURE_MODE_FORCE)
            {
                //firmado manual
                $bank_account->mandate = new Mandate();
                $bank_account->mandate->signed_at = date("Y/m/d");
            }


            try{


                /**
                 * @var $bank_account BankAccount
                 */
                if($bank_account = $this->repository->createBankAccount($bank_account, $_GET["besepa_customer_id"])){

                    $this->userManager->getUser()->addBankAccount($bank_account);

                    do_action("besepa.bank_account_created", $bank_account);

                    $signature_url_pending_mandate = isset($bank_account->mandate->signature_url) ? $bank_account->mandate->signature_url : null;
                    $mandate_url = ($sign_mode == BesepaWCRepository::SIGNATURE_MODE_FORCE) ? $bank_account->mandate->url : $signature_url_pending_mandate;

                    $return = array(
                        "error"       => false,
                        "bank_account" => array(
                            'id'            => $bank_account->id,
                            "iban"          => $bank_account->iban,
                            "status"        => $bank_account->status,
                            "mandate_url"   => $mandate_url,
                        ),
                        "needs_mandate" => true //$bank_account->status == BankAccount::STATUS_PENDING_MANDATE

                    );
                }

            }catch (ResourceAlreadyExistsException $e)
            {



                    $this->userManager->getUser()->addBankAccount($e->entityInstance);

                    $return = array(
                        "error"       => false,
                        "bank_account" => array(
                            'id'            => $e->entityInstance->id,
                            "iban"          => $e->entityInstance->iban,
                            "status"        => $e->entityInstance->status,
                            "mandate_url"   => isset($bank_account->mandate->signature_url) ? $bank_account->mandate->signature_url : null,
                        ),
                        "needs_mandate" => $sign_mode == BesepaWCRepository::SIGNATURE_MODE_FORCE || $bank_account->status == BankAccount::STATUS_PENDING_MANDATE

                    );

            }

        }

        wp_send_json($return);
    }

    function checkIfCustomerExistsAction()
    {

        if(!(isset($_GET["besepa_ajax_action"]) && $_GET["besepa_ajax_action"]=="check_customer_id"))
            return;

        if( isset($_GET["besepa_customer_id"]))
        {

            if($customer = $this->repository->getCustomer($_GET["besepa_customer_id"]))
            {

                wp_send_json(true);
            }

        }

        wp_send_json(false);
    }


}