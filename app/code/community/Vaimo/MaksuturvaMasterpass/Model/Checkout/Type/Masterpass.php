<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Model_Checkout_Type_Masterpass extends Mage_Checkout_Model_Type_Onepage
{

    public function saveOrder()
    {
        $this->getQuote()->collectTotals();

        $this->validate();
        switch ($this->getCheckoutMethod()) {
            case self::METHOD_GUEST:
                $this->_prepareGuestQuote();
                break;
            case self::METHOD_REGISTER:
                Mage::throwException(Mage::helper('checkout')->__('Checkout method not allowed.'));
                break;
            case self::METHOD_CUSTOMER:
                $this->_prepareCustomerQuote();
                break;
        }

        $service = Mage::getModel('sales/service_quote', $this->getQuote());
        $service->submitAll();

        $this->_checkoutSession->setLastQuoteId($this->getQuote()->getId())
            ->setLastSuccessQuoteId($this->getQuote()->getId())
            ->clearHelperData();

        $order = $service->getOrder();
        if ($order) {
            Mage::dispatchEvent('checkout_type_onepage_save_order_after',
                array('order'=>$order, 'quote'=>$this->getQuote()));

            /**
             * a flag to set that there will be redirect to third party after confirmation
             * eg: paypal standard ipn
             */
            $redirectUrl = $this->getQuote()->getPayment()->getOrderPlaceRedirectUrl();
            /**
             * we only want to send to customer about new order when there is no redirect to third party
             */
            if (!$redirectUrl && $order->getCanSendNewEmailFlag()) {
                try {
                    $order->queueNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            // add order information to the session
            $this->_checkoutSession->setLastOrderId($order->getId())
                ->setRedirectUrl($redirectUrl)
                ->setLastRealOrderId($order->getIncrementId());

            // as well a billing agreement can be created
            $agreement = $order->getPayment()->getBillingAgreement();
            if ($agreement) {
                $this->_checkoutSession->setLastBillingAgreementId($agreement->getId());
            }
        }

        // add recurring profiles information to the session
        $profiles = $service->getRecurringPaymentProfiles();
        if ($profiles) {
            $ids = array();
            foreach ($profiles as $profile) {
                $ids[] = $profile->getId();
            }
            $this->_checkoutSession->setLastRecurringProfileIds($ids);
            // TODO: send recurring profile emails
        }

        Mage::dispatchEvent(
            'checkout_submit_all_after',
            array('order' => $order, 'quote' => $this->getQuote(), 'recurring_profiles' => $profiles)
        );

        return $this;
    }

    public function saveShippingMethod($shippingMethod)
    {
        if (empty($shippingMethod)) {
            return array('error' => -1, 'message' => Mage::helper('checkout')->__('Invalid shipping method.'));
        }
        $rate = $this->getQuote()->getShippingAddress()->getShippingRateByCode($shippingMethod);
        if (!$rate) {
            return array('error' => -1, 'message' => Mage::helper('checkout')->__('Invalid shipping method.'));
        }
        $this->getQuote()->getShippingAddress()
            ->setShippingMethod($shippingMethod);

        $this->getCheckout()
            ->setStepData('shipping_method', 'complete', true)
            ->setStepData('review', 'allow', true);

        return array();
    }

    public function getCheckoutMethod()
    {
        if ($this->getCustomerSession()->isLoggedIn()) {
            return Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
        }
        return Mage_Checkout_Model_Type_Onepage::METHOD_GUEST;
    }

    /**
     * Validate quote state to be integrated with one page checkout process
     */
    public function validate()
    {
        $quote  = $this->getQuote();
        if ($quote->getIsMultiShipping()) {
            Mage::throwException(Mage::helper('checkout')->__('Invalid checkout type.'));
        }

        if ($quote->getCheckoutMethod() == Mage_Sales_Model_Quote::CHECKOUT_METHOD_GUEST && !$quote->isAllowedGuestCheckout()) {
            Mage::throwException(Mage::helper('checkout')->__('Sorry, guest checkout is not enabled. Please try again or contact store owner.'));
        }
    }
}