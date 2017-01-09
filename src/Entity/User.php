<?php
/**
 *
 *
 * @author Asier Marqués <asiermarques@gmail.com>
 */

namespace Besepa\WCPlugin\Entity;


class User
{

    const META_CUSTOMER_ID = 'besepa_customer_id';

    const META_TAX_ID  = "besepa_taxid";

    private $user_id;

    function __construct($wp_user_id)
    {
        $this->user_id = $wp_user_id;
    }


    function getTaxId()
    {
        return get_user_meta($this->user_id, static::META_TAX_ID, true);
    }

    function setTaxId($taxId)
    {
        return update_user_meta($this->user_id, static::META_TAX_ID, $taxId);
    }

    /**
     * @return string
     */
    function getCustomerId()
    {
        return get_user_meta($this->user_id, static::META_CUSTOMER_ID, true);
    }

    /**
     * @param $besepa_customer_id
     * @return void
     */
    function setCustomerId($besepa_customer_id)
    {
         update_user_meta($this->user_id, static::META_CUSTOMER_ID, $besepa_customer_id);
    }

    /**
     * @return false|\WP_User
     */
    function getWordPressUser()
    {
        return get_userdata($this->user_id);
    }


}