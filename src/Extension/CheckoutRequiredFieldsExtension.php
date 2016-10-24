<?php
/**
 * Created by Asier Marqués <asiermarques@gmail.com>
 * Date: 23/10/16
 * Time: 20:24
 */

namespace Besepa\WCPlugin\Extension;


class CheckoutRequiredFieldsExtension {

	function init()
	{
		add_filter( 'woocommerce_checkout_fields' , array($this, 'setFieldsRequired') );
	}

	function setFieldsRequired($fields=array())
	{

		//https://docs.woocommerce.com/document/tutorial-customising-checkout-fields-using-actions-and-filters/
		$fields["billing"]["billing_company"]["required"] = true;

		return $fields;

	}

}