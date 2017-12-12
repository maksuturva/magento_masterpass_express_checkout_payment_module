<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Model_Gateway_Initialization extends Vaimo_MaksuturvaMasterpass_Model_Gateway_Abstract
{
    private $formType = 'maksuturvamasterpass/form_initializePayment';

    public function getForm()
    {
        if (!$this->form) {
            $builder = $this->getQuoteFormFieldBuilder();
            $this->form = $this->_getForm($builder, $this->formType);
        }

        return $this->form;
    }
}