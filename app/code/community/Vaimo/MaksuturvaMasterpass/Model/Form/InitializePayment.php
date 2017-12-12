<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Model_Form_InitializePayment extends Vaimo_MaksuturvaMasterpass_Model_Form_Abstract
{
    public function __construct($args)
    {
        parent::__construct($args);

        $this->_formData['pmt_version'] = '5104';

        /**
         * Address information must be included even in initialization where the information
         * is still unknown, use dummy values
         */
        $this->_formData["pmt_buyeremail"] = "masterpass@maksuturva.fi";
        $this->_formData["pmt_buyername"] = "Masterpass";
        $this->_formData["pmt_buyeraddress"] = "none";
        $this->_formData["pmt_buyerpostalcode"]= "00000";
        $this->_formData["pmt_buyercity"] = "none";
        $this->_formData["pmt_buyercountry"] = "FI";
        $this->_formData["pmt_deliveryname"] = "Masterpass";
        $this->_formData["pmt_deliveryaddress"] = "none";
        $this->_formData["pmt_deliverypostalcode"] = "00000";
        $this->_formData["pmt_deliverycity"] = "none";
        $this->_formData["pmt_deliverycountry"] = "FI";
    }
}