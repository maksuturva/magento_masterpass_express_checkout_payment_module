<?php
/**
 * Copyright (c) 2009-2017 Vaimo AB
 *
 * Vaimo reserves all rights in the Program as delivered. The Program
 * or any portion thereof may not be reproduced in any form whatsoever without
 * the written consent of Vaimo, except as provided by licence. A licence
 * under Vaimo's rights in the Program may be available directly from
 * Vaimo.
 *
 * Disclaimer:
 * THIS NOTICE MAY NOT BE REMOVED FROM THE PROGRAM BY ANY USER THEREOF.
 * THE PROGRAM IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE PROGRAM OR THE USE OR OTHER DEALINGS
 * IN THE PROGRAM.
 *
 * @category    Vaimo
 * @package     Vaimo_Module
 * @copyright   Copyright (c) 2009-2017 Vaimo AB
 */

class Vaimo_MaksuturvaMasterpass_Model_Checkout_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Remove Masterpass Best Practice payment method from quote when entering cart or normal checkout
     *
     * @param $observer
     */
    public function checkPaymentMethod($observer)
    {
        /** @var Mage_Sales_Model_Quote $checkoutSessionQuote */
        $checkoutSessionQuote = Mage::getSingleton('checkout/session')->getQuote();
        $masterpassMethod = Mage::getModel('maksuturvamasterpass/masterpass');

        /** @var Mage_Sales_Model_Quote_Payment $payment */
        if ($payment = $checkoutSessionQuote->getPayment()) {
            if ($payment->getMethod() == $masterpassMethod->getCode()) {
                $checkoutSessionQuote->removePayment();
                $checkoutSessionQuote->collectTotals();
                $checkoutSessionQuote->save();
            }
        }
    }
}