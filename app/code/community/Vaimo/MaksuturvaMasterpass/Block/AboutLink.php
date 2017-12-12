<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Block_AboutLink extends Mage_Core_Block_Template
{
    public function getIframeUrl()
    {
        $locale = Mage::app()->getLocale()->getLocaleCode();
        $url = '';

        switch ($locale) {
            case 'fi_FI':
               $url = 'https://www.mastercard.com/mc_us/wallet/learnmore/fi/FI/';
               break;
            case 'en_FI':
                $url = 'https://www.mastercard.com/mc_us/wallet/learnmore/en/FI/';
                break;
            case 'sv_SE':
               $url = 'https://www.mastercard.com/mc_us/wallet/learnmore/se/';
               break;
            default:
               $url = 'https://www.mastercard.com/mc_us/wallet/learnmore/en/US/';
        }
        return $url;
    }
}