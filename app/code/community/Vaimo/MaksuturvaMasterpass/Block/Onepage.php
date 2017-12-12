<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Block_Onepage extends Mage_Checkout_Block_Onepage
{
    public function getSteps()
    {
        $steps = parent::getSteps();

        unset($steps['login']);
        unset($steps['billing']);
        unset($steps['shipping']);
        unset($steps['payment']);

        return $steps;
    }

    public function getActiveStep()
    {
        return 'shipping_method';
    }
}
