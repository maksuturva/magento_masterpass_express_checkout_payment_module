<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Controller_Front_Abstract extends Mage_Core_Controller_Front_Action
{
    /**
     * @return Mage_Sales_Model_Quote
     */
    protected function getQuote()
    {
        return $this->getMasterpassCheckout()->getQuote();
    }

    /**
     * @return Vaimo_MaksuturvaMasterpass_Model_Checkout_Type_Masterpass
     */
    protected function getMasterpassCheckout()
    {
        return Mage::getModel('maksuturvamasterpass/checkout_type_masterpass');
    }

    /**
     * @return Mage_Customer_Model_Customer
     */
    protected function getCustomer()
    {
        return Mage::singleton('customer/session')->getCustomer();
    }

    /**
     * @return Vaimo_MaksuturvaMasterpass_Model_Masterpass
     */
    protected function getPaymentMethod()
    {
        return Mage::getModel('maksuturvamasterpass/masterpass');
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function getCheckout()
    {
        return $this->getMasterpassCheckout()->getCheckout();
    }

    /**
     * @return Vaimo_MaksuturvaMasterpass_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('maksuturvamasterpass');
    }
}