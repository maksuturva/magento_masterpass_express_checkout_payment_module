<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

/**
 * Handles authorization part of payment before user enters checkout page
 */
class Vaimo_MaksuturvaMasterpass_AuthorizeController extends Vaimo_MaksuturvaMasterpass_Controller_Front_Abstract
{
    protected $authMandatoryFields = array(
        "pmt_version",
        "pmt_id",
        "pmt_reference",
        "pmt_amount",
        "pmt_currency",
        "pmt_paymenturl"
    );

    public function indexAction()
    {
        if (! $this->getHelper()->canMasterpassCheckout()) {
            Mage::getSingleton('checkout/session')->addError($this->__('Masterpass Best Practice checkout is disabled.'));
            $this->_redirect('checkout/cart');
            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $this->getQuote();

        if (! $quote->validateMinimumAmount()) {
            $error = Mage::getStoreConfig('sales/minimum_order/error_message') ?
                Mage::getStoreConfig('sales/minimum_order/error_message') :
                Mage::helper('checkout')->__('Subtotal must exceed minimum order amount');

            Mage::getSingleton('checkout/session')->addError($error);
            $this->_redirect('checkout/cart');
            return;
        }

        if (! $quote->hasItems() || $quote->getHasError()) {
            $this->_redirect('checkout/cart');
            return;
        }

        $responseXml = null;
        try {
            $responseXml = $this->initializePayment();
        } catch (MasterpassGatewayException $e) {
            $this->_redirect('masterpass/authorize/error', array(
                'type' => Vaimo_MaksuturvaMasterpass_Model_Masterpass::ERROR_COMMUNICATION_FAILED
            ));
            $this->getCheckout()->addError($this->__('Communication with Maksuturva failed.'));
        }

        if ($responseXml instanceof SimpleXMLElement) {
            $paymentUrl = (string)$responseXml->{'pmt_paymenturl'};
            $this->_redirectUrl($paymentUrl);
        }
    }

    /**
     * @return bool|SimpleXMLElement
     */
    public function initializePayment()
    {
        $quote = $this->getQuote();

        if (empty($quote)) {
            $this->_redirect('checkout/cart');
            return false;
        }

        /* @var $method Vaimo_MaksuturvaMasterpass_Model_Masterpass */
        $method = $this->getPaymentMethod();

        /* @var $gateway Vaimo_MaksuturvaMasterpass_Model_Gateway_Initialization */
        $gateway = $method->getPaymentGateway();

        // Order id has to be same in initialization request as in final payment request
        $method->reserveOrderId();

        // Set payment method and apply possible discount
        $payment = $quote->getPayment();
        $additional_data = unserialize($payment->getAdditionalData());
        $additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_PRESELECTED_PAYMENT_METHOD] = 'FI55';
        $additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_PRESELECTED_PAYMENT_METHOD_DESCRIPTION] = 'Masterpass';
        $payment->setAdditionalData(serialize($additional_data));
        $payment->setMethod($method->getCode())->save();
        $quote->collectTotals()->save();

        $requestFields = $method->getInitializeFormFields();

        try {
            $responseXml = $gateway->paymentPost($requestFields, true);
        } catch (MasterpassGatewayException $e) {
            $this->_redirect('masterpass/authorize/error', array(
                'type' => Vaimo_MaksuturvaMasterpass_Model_Masterpass::ERROR_COMMUNICATION_FAILED
            ));
            return false;
        }

        if ($responseXml->error) {
            $this->_redirect('masterpass/authorize/error', array(
                'type' => Vaimo_MaksuturvaMasterpass_Model_Masterpass::ERROR_MAKSUTURVA_RETURN
            ));
            return false;
        }

        if (! $this->validateAuthResponse($responseXml, $requestFields, $invalidField)) {
            $this->_redirect('masterpass/authorize/error', array(
                'type' => Vaimo_Maksuturva_Model_Maksuturva::ERROR_EMPTY_FIELD,
                'field' => $invalidField
            ));
            return false;
        }

        return $responseXml;
    }

    public function successAction()
    {
        $pmt_id = $this->getRequest()->getParam('pmt_id');
        $pmt_paymentmethod = $this->getRequest()->getParam('pmt_paymentmethod');

        if ($pmt_paymentmethod != Vaimo_MaksuturvaMasterpass_Model_Masterpass::MAKSUTURVA_MASTERPASS_METHOD_CODE) {
            $this->_redirect('masterpass/authorize/error');
            return;
        }

        $quote = $this->getQuote();
        $quotePmtId = $this->getHelper()->getSerializedMaksuturvaPaymentId($quote->getPayment());

        if ($quotePmtId != $pmt_id) {
            $this->_redirect('masterpass/authorize/error');
            return;
        }

        $method = $this->getPaymentMethod();
        $gateway = $method->getInitializationGateway();

        try {
            $responseXml = $gateway->statusQuery(false);
        } catch (MasterpassGatewayException $e) {
            $this->_redirect('masterpass/authorize/error', array(
                'type' => Vaimo_MaksuturvaMasterpass_Model_Masterpass::ERROR_COMMUNICATION_FAILED
            ));
        }

        if ($responseXml->pmtq_externalcode1 != 'OK') {
            $this->_redirect('masterpass/authorize/error');
        }

        if ($responseXml->pmtq_paymentmethod != Vaimo_MaksuturvaMasterpass_Model_Masterpass::MAKSUTURVA_MASTERPASS_METHOD_CODE) {
            $this->_redirect('masterpass/authorize/error');
            return false;
        }

        switch ($responseXml->pmtq_returncode) {
            case Vaimo_MaksuturvaMasterpass_Model_Gateway_Abstract::STATUS_QUERY_NOT_FOUND:
                break; // OK
            case Vaimo_MaksuturvaMasterpass_Model_Gateway_Abstract::STATUS_QUERY_PAID:
            case Vaimo_MaksuturvaMasterpass_Model_Gateway_Abstract::STATUS_QUERY_PAID_DELIVERY:
                Mage::getSingleton('core/session')->addSuccess($this->__('Your order is already paid'));
                break;
            default:
                $this->_redirect('masterpass/authorize/error');
        }

        $method->setAddressesFromXml($responseXml);
        $this->redirectExpressCheckout();
    }

    public function errorAction()
    {
        $errorType = $this->getRequest()->getParam('type');

        switch ($errorType) {
            case Vaimo_MaksuturvaMasterpass_Model_Masterpass::ERROR_MAKSUTURVA_RETURN:
                $errorMsg = 'Maksuturva returned an error on your payment.';
                break;
            case Vaimo_Maksuturva_Model_Maksuturva::ERROR_EMPTY_FIELD:
                $errorMsg = 'Error: missing field';
                break;
            case Vaimo_MaksuturvaMasterpass_Model_Masterpass::ERROR_COMMUNICATION_FAILED:
                $errorMsg = 'Communication with Maksuturva failed. Please try again later.';
                break;
            default:
                $errorMsg = 'Unknown error';
        }

        if ($errorMsg) {
            $this->getCheckout()->addError($this->__($errorMsg));
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Customer canceled order on authorization page, order has not been created
     */
    public function cancelAction()
    {
        $this->_redirect('checkout/cart');
    }

    /**
     * Delayed capture is not used with Masterpass Best Practice
     */
    public function delayedAction()
    {
        $this->_redirect('checkout/cart');
    }

    /**
     * Redirect to custom checkout page to fill in rest of information and place order
     */
    protected function redirectExpressCheckout()
    {
        $this->_redirect('masterpass/checkout/index');
    }

    /**
     * @param SimpleXMLElement $xmlObject
     * @param string[] $requestFields
     * @param string $invalidField Outputs invalid field if return==false
     *
     * @return bool
     */
    private function validateAuthResponse($xmlObject, $requestFields, &$invalidField)
    {
        foreach ($this->authMandatoryFields as $field) {
            if ($field == 'pmt_paymenturl') {
                if (! $xmlObject->{$field}) {
                    return false;
                }
                continue;
            }

            if ($xmlObject->{$field} != $requestFields[$field]) {
                $invalidField = $field;
                return false;
            }
        }
        return true;
    }
}
