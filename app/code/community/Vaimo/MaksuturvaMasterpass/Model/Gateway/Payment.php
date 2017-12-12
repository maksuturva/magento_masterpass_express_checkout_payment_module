<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Model_Gateway_Payment extends Vaimo_MaksuturvaMasterpass_Model_Gateway_Abstract
{
    private $formType = 'maksuturvamasterpass/form_finalizePayment';

    public function getForm()
    {
        if (! $this->form) {
            $fieldBuilder = $this->getOrderFormFieldBuilder();
            $this->form = $this->_getForm($fieldBuilder, $this->formType);
        }

        return $this->form;
    }
}