<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Block_Onepage_Review_Info extends Mage_Checkout_Block_Onepage_Review_Info
{
    public function isShow()
    {
        return true;
    }
}