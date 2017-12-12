<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */
class Vaimo_MaksuturvaMasterpass_Block_Address_Display extends Mage_Checkout_Block_Onepage_Abstract
{
    public function getShippingAddress()
    {
        return $this->getQuote()->getShippingAddress();
    }

    public function getBillingAddress()
    {
        return $this->getQuote()->getBillingAddress();
    }
}