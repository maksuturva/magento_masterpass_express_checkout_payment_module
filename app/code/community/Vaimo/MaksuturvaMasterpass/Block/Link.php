<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Block_Link extends Mage_Core_Block_Template
{
    protected $inCartPage;

    public function getCheckoutUrl()
    {
        return Mage::getUrl('masterpass/authorize/index');
    }

    public function isDisabled()
    {
        return !Mage::helper('maksuturvamasterpass')->canMasterpassCheckout();
    }

    public function isInCartPage()
    {
        return $this->inCartPage;
    }

    public function setIsInCartPage($value) {
        $this->inCartPage = $value;
    }
}