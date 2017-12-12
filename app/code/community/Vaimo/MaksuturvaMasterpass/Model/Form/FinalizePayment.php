<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Model_Form_FinalizePayment extends Vaimo_MaksuturvaMasterpass_Model_Form_Abstract
{
    public function __construct($args)
    {
        parent::__construct($args);

        $this->_formData['pmt_version'] = '5204';
    }
}