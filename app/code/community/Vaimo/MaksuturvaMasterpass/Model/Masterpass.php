<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Model_Masterpass extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'masterpassbp';

    private $config;

    const MAKSUTURVA_MASTERPASS_METHOD_CODE = 'FI55';

    const ERROR_MAKSUTURVA_RETURN = 'error_maksuturva_return';
    const ERROR_COMMUNICATION_FAILED = 'error_communication_failed';

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('masterpass/checkout/placeOrder', array('_secure' => true));
    }

    public function canUseCheckout()
    {
        /** Prevent Masterpass from showing up in normal checkout */
        return false;
    }

    public function getMethodFormBlock()
    {
        return '';
    }

    public function getInitializeFormFields()
    {
        return $this->getInitializationGateway()->getFormFields();
    }

    public function getCheckoutFormFields()
    {
        return $this->getPaymentGateway()->getFormFields();
    }

    /**
     * @return Vaimo_MaksuturvaMasterpass_Model_Gateway_Initialization
     */
    public function getInitializationGateway()
    {
        return Mage::getModel('maksuturvamasterpass/gateway_initialization', $this->getConfigs());
    }

    /**
     * @return Vaimo_MaksuturvaMasterpass_Model_Gateway_Payment
     */
    public function getPaymentGateway()
    {
        return Mage::getModel('maksuturvamasterpass/gateway_payment', $this->getConfigs());
    }

    public function getPaymentRequestUrl()
    {
        return $this->getPaymentGateway()->getPaymentRequestUrl();
    }

    public function reserveOrderId()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quote->reserveOrderId();
    }

    /**
     * Set billing and shipping address from XML returned by Maksuturva
     *
     * @param $xmlElement SimpleXMLElement
     *
     * @return bool Returns true if all fields were valid
     */
    public function setAddressesFromXml($xmlElement)
    {
        /* @var $quote Mage_Sales_Model_Quote */
        $quote = $this->getQuote();

        /** @var Vaimo_MaksuturvaMasterpass_Helper_Data $helper */
        $helper = Mage::helper('maksuturvamasterpass');

        $quote->setCustomerEmail($xmlElement->pmtq_buyeremail);

        /* @var $buyerAddress Mage_Sales_Model_Quote_Address */
        $buyerAddress = $quote->getBillingAddress();
        $deliveryAddress = $quote->getShippingAddress();

        if (! $buyerAddress) {
            $buyerAddress = Mage::getModel('sales/quote_address');
        }

        $helper->parseName((string)$xmlElement->pmtq_buyername, $buyerFirstname, $buyerLastname);

        $buyerAddress->setFirstname($buyerFirstname);
        $buyerAddress->setLastname($buyerLastname);
        $buyerAddress->setStreet([(string)$xmlElement->pmtq_buyeraddress1, (string)$xmlElement->pmtq_buyeraddress2]);
        $buyerAddress->setPostcode((string)$xmlElement->pmtq_buyerpostalcode);
        $buyerAddress->setCity((string)$xmlElement->pmtq_buyercity);
        $buyerAddress->setCountryId($xmlElement->pmtq_buyercountry);
        $buyerAddress->setTelephone($xmlElement->pmtq_buyerphone);
        $buyerAddress->setEmail($xmlElement->pmtq_buyeremail);
        $buyerAddress->setQuoteId($quote->getEntityId());
        $buyerAddress->setAddressType('billing');
        $buyerAddress->setPaymentMethod($this->_code);

        if (! $deliveryAddress) {
            $deliveryAddress = Mage::getModel('sales/quote_address');
        }

        $helper->parseName((string)$xmlElement->pmtq_deliveryname, $deliveryFirstname, $deliveryLastname);

        $deliveryAddress->setFirstname($deliveryFirstname);
        $deliveryAddress->setLastname($deliveryLastname);
        $deliveryAddress->setStreet([(string)$xmlElement->pmtq_deliveryaddress1, (string)$xmlElement->pmtq_deliveryaddress2]);
        $deliveryAddress->setPostcode((string)$xmlElement->pmtq_deliverypostalcode);
        $deliveryAddress->setCity((string)$xmlElement->pmtq_deliverycity);
        $deliveryAddress->setCountryId($xmlElement->pmtq_buyercountry);
        $deliveryAddress->setTelephone($xmlElement->pmtq_buyerphone);
        $deliveryAddress->setEmail($xmlElement->pmtq_buyeremail);
        $deliveryAddress->setQuoteId($quote->getEntityId());
        $deliveryAddress->setCollectShippingRates(true);
        $deliveryAddress->setAddressType('shipping');
        $deliveryAddress->setPaymentMethod($this->_code);

        $serializedBuyerData = $helper->serializeAddress($buyerAddress);
        $serializeDeliveryData = $helper->serializeAddress($deliveryAddress);
        $isAddressSame = strcmp($serializedBuyerData, $serializeDeliveryData) == 0;

        $deliveryAddress->setSameAsBilling($isAddressSame);

        $quote->setBillingAddress($buyerAddress);
        $quote->setShippingAddress($deliveryAddress);

        $quote->collectTotals()->save();
        $buyerAddress->save();
        $deliveryAddress->save();
        return true;
    }

    protected function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    protected function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getConfigs()
    {
        if ($this->config == null) {
            $this->config = array(
                'sandbox' => intval($this->getConfigData('sandboxmode')),
                'keyversion' => $this->getConfigData('keyversion'),
                'paymentdue' => $this->getConfigData('paymentdue'),
                'active' => intval($this->getConfigData('active'))
            );

            $this->config['commencoding'] = 'UTF-8';

            if ($this->config['sandbox']) {
                $this->config['commurl'] = $this->_getConfigData('test_commurl');
                $this->config['sellerId'] = $this->_getConfigData('test_sellerid');
                $this->config['secretKey'] = $this->_getConfigData('test_secretkey');
                $this->config['callbackUrlWorkaround'] = $this->_getConfigData('callback_url_workaround');
            } else {
                $this->config['commurl'] = $this->_getConfigData(('commurl'));
                $this->config['sellerId'] = $this->_getConfigData('sellerid');
                $this->config['secretKey'] = $this->_getConfigData('secretkey');
            }
        }
        return $this->config;
    }

    /**
     * @param $key
     *
     * @return bool|mixed
     */
    public function getConfigValue($key)
    {
        $config = $this->getConfigs();

        if (isset($config[$key])) {
            return $config[$key];
        }
        return false;
    }

    private function _getConfigData($field, $storeId = null)
    {
        if (null === $storeId) {
            $storeId = $this->getStore();
        }

        $code = $this->getCode();
        $path = 'payment/' . $code . '/' . $field;
        return Mage::getStoreConfig($path, $storeId);
    }

    public function getCode()
    {
        return $this->_code;
    }
}
