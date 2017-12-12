<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

abstract class Vaimo_MaksuturvaMasterpass_Model_Gateway_Abstract extends Vaimo_Maksuturva_Model_Gateway_Abstract
{
    const PAYMENT_SERVICE_URN = 'NewPaymentExtended.pmt';
    const PAYMENT_STATUS_QUERY_URN = 'PaymentStatusQuery.pmt';

    const STATUS_RESPONSE_NOT_PAID = '00';
    const STATUS_RESPONSE_FAILED = '01';
    const STATUS_RESPONSE_PAID_WAIT_DELIVERY = '20';
    const STATUS_RESPONSE_PAID = '30';
    const STATUS_RESPONSE_SETTLED = '40';
    const STATUS_RESPONSE_CANCEL = '91';
    const STATUS_RESPONSE_PARTIAL_REFUND = '92';
    const STATUS_RESPONSE_RECLAMATION = '95';
    const STATUS_RESPONSE_CANCELLED = '99';

    private $sellerId = "";
    private $secretKey = "";
    protected $form = null;
    private $_order;

    /** @var Vaimo_MaksuturvaMasterpass_Helper_Data */
    protected $helper;

    public function __construct($config)
    {
        $this->sellerId = $config['sellerId'];
        $this->secretKey = $config['secretKey'];
        $this->commUrl = $config['commurl'];
        $this->commEncoding = $config['commencoding'];
        $this->paymentDue = $config['paymentdue'];
        $this->keyVersion = $config['keyversion'];
        $this->callbackUrlWorkaround = $config['callbackUrlWorkaround'];

        $this->helper = Mage::helper('maksuturvamasterpass');

        parent::__construct();
    }

    protected function _getForm($fieldBuilder, $type)
    {
        if (! $this->form) {
            $fields = $fieldBuilder->build();

            Mage::log(var_export($fields, true), null, 'maksuturva.log', true);

            $this->form = Mage::getModel($type, array(
                'secretkey' => $this->secretKey,
                'options' => $fields,
                'encoding' => $this->commEncoding,
                'url' => $this->commUrl
            ));
        }

        return $this->form;
    }

    protected function getSellerId()
    {
        return $this->sellerId;
    }

    protected function getOrderFormFieldBuilder()
    {
        $order = $this->getOrder();

        if (! $order instanceof Mage_Sales_Model_Order) {
            throw new \Exception('order not found');
        }

        $builder = $this->getFormFieldBuilder($order);

        $builder->setBillingAddress($order->getBillingAddress());
        $builder->setShippingAddress($order->getShippingAddress());

        // Adding the shipping cost as a row
        $shippingDescription = ($order->getShippingDescription() ? $order->getShippingDescription() : 'Free Shipping');
        $shippingCost = $order->getShippingAmount();
        $shippingTax = $order->getShippingTaxAmount();
        $builder->addShippingCost($shippingCost, $shippingTax, $shippingDescription);

        return $builder;
    }

    protected function getQuoteFormFieldBuilder()
    {
        $quote = $this->getQuote();

        if (! $quote instanceof Mage_Sales_Model_Quote) {
            throw new \Exception('quote not found');
        }

        return $this->getFormFieldBuilder($quote);
    }

    /**
     * @param Mage_Sales_Model_Order|Mage_Sales_Model_Quote $order
     * @return Vaimo_MaksuturvaMasterpass_Model_Form_FieldBuilder $formFieldBuilder
     */
    private function getFormFieldBuilder($order)
    {
        /* @var $formFieldBuilder Vaimo_MaksuturvaMasterpass_Model_Form_FieldBuilder */
        $formFieldBuilder = Mage::getModel('maksuturvamasterpass/form_fieldBuilder');

        $dueDate = date("d.m.Y", strtotime("+" . $this->paymentDue . " day"));

        $formFieldBuilder->addItems($order->getAllItems());
        $orderData = $order->getData();

        if ($order instanceof Mage_Sales_Model_Order) {
            $discountAmount = $orderData["discount_amount"];
            $discountDescription = $orderData["discount_description"];
        } else {
            $discountAmount = $order->getShippingAddress()->getDiscountAmount();
            $discountDescription = $order->getShippingAddress()->getDiscountDescription();
        }
        if ($discountAmount != 0) {
            $formFieldBuilder->addDiscountItem($discountAmount, $discountDescription);
        }

        // Payment Fee (Vaimo)
        if ($fee = $order->getBaseVaimoPaymentFee()) {
            $formFieldBuilder->addVaimoPaymentFee($fee, $order->getBaseVaimoPaymentFeeTax());
        }

        // Gift Card payment (Vaimo)
        if ($order->getGiftCardsAmount() > 0) {
            $formFieldBuilder->addGiftCards($order->getGiftCards(), $order->getGiftCardsAmount());
        }

        // Store credit (Vaimo)
        if ($order->getCustomerBalanceAmount() > 0) {
            $formFieldBuilder->addStoreCredit($order->getCustomerBalanceAmount());
        }

        // store unique transaction id on payment object for later retrieval
        $payment = $order->getPayment();
        $additional_data = unserialize($payment->getAdditionalData());
        if (isset($additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_TRANSACTION_ID])) {
            $pmt_id = $additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_TRANSACTION_ID];
        } else {
            $pmt_id = Mage::helper('maksuturva')->generatePaymentId();
            $additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_TRANSACTION_ID] = $pmt_id;
            $payment->setAdditionalData(serialize($additional_data));
            $payment->setMaksuturvaPmtId($pmt_id);
            $payment->save();
        }

        if ($order instanceof Mage_Sales_Model_Quote) {
            $orderId = $order->getReservedOrderId();
        } else {
            $orderId = $order->getIncrementId();

            if (! $payment->getMaksuturvaPmtId()) {
                $payment->setMaksuturvaPmtId($pmt_id);
                $payment->save();
            }
        }

        $formFieldBuilder->addOrderId($orderId);
        $formFieldBuilder->addOrderReference((string)($orderId + 100));
        $formFieldBuilder->addPaymentId($pmt_id);
        $formFieldBuilder->addSellerId($this->sellerId);
        $formFieldBuilder->addDueDate($dueDate);

        $formFieldBuilder->addCustomerEmail($order->getCustomerEmail() ? $order->getCustomerEmail() : 'empty@email.com');
        $formFieldBuilder->addKeyVersion($this->keyVersion);

        if ($this->callbackUrlWorkaround) {
            /* Workaround for passing url validation since devbox urls are considered malformed by API */
            $formFieldBuilder->addCallbackUrls(
                'http://localhost/masterpass/authorize/success',
                'http://localhost/masterpass/authorize/error',
                'http://localhost/masterpass/authorize/cancel',
                'http://localhost/masterpass/authorize/delayed'
            );
        } else {
            $formFieldBuilder->addCallbackUrls(
                Mage::getUrl('masterpass/authorize/success'),
                Mage::getUrl('masterpass/authorize/error'),
                Mage::getUrl('masterpass/authorize/cancel'),
                Mage::getUrl('masterpass/authorize/delayed')
            );
        }

        return $formFieldBuilder;
    }

    public function getFormFields()
    {
        return $this->getForm()->getFieldArray();
    }

    protected function _getCustomerTaxClass()
    {
        $customerGroup = $this->getQuote()->getCustomerGroupId();
        if (!$customerGroup) {
            $customerGroup = Mage::getStoreConfig('customer/create_account/default_group', $this->getQuote()->getStoreId());
        }
        return Mage::getModel('customer/group')->load($customerGroup)->getTaxClassId();
    }

    public function getPaymentRequestUrl()
    {
        return $this->commUrl . self::PAYMENT_SERVICE_URN;
    }

    protected function getStatusQueryUrl()
    {
        return $this->commUrl . self::PAYMENT_STATUS_QUERY_URN;
    }

    public function getQuote()
    {
        return Mage::getSingleton('checkout/session')->getQuote();
    }

    public function getOrder()
    {
        return $this->_order;
    }

    public function setOrder($order)
    {
        $this->_order = $order;
    }

    /**
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function statusQuery()
    {
        $pmt_id = $this->helper->getSerializedMaksuturvaPaymentId($this->getQuote()->getPayment());

        $requestFields = [
            "pmtq_action" => "PAYMENT_STATUS_QUERY",
            "pmtq_version" => "0005",
            "pmtq_sellerid" => $this->sellerId,
            "pmtq_id" => $pmt_id,
            "pmtq_resptype" => "XML",
            "pmtq_hashversion" => "",
            "pmtq_hash" => "",
            "pmtq_keygeneration" => "001"
        ];

        $responseXml = $this->basicAuthPost($this->getStatusQueryUrl(), $requestFields, true);

        if (! $this->validateStatusQueryResponse($requestFields, $responseXml, $invalidField)) {
            throw new MaksuturvaGatewayException('Missing field in status query response: {$invalidField}');
        }

        return $responseXml;
    }

    /**
     * Validate fields which should equal same as in request and check that all expected fields exist
     *
     * @param $requestFields
     * @param $xml
     * @param $invalidField
     *
     * @return bool
     */
    protected function validateStatusQueryResponse($requestFields, $xml, &$invalidField)
    {
        $checkExists = array(
            "pmtq_buyeraddress1", "pmtq_buyercity", "pmtq_buyercountry", "pmtq_buyername",
            "pmtq_buyerpostalcode", "pmtq_certification", "pmtq_deliveryaddress1", "pmtq_deliverycity",
            "pmtq_deliverycountry", "pmtq_deliveryname", "pmtq_deliverypostalcode", "pmtq_escrow",
            "pmtq_externalcode1", "pmtq_externaltext", /*"pmtq_paymentdate",*/ "pmtq_paymentstarttimestamp",
            "pmtq_returncode", "pmtq_returntext"
        );

        foreach ($checkExists as $field) {
            if (! $xml->{$field}) {
                $invalidField = $field;
                return false;
            }
        }

        $checkEquals = array(
            'pmtq_action', 'pmtq_id', 'pmtq_sellerid', 'pmtq_version'
        );

        foreach ($checkEquals as $field) {
            if ($requestFields[$field] != $xml->{$field}) {
                $invalidField = $field;
                return false;
            }
        }
        return true;
    }

    /**
     * @param $data
     * @param bool $parseXml
     *
     * @return SimpleXMLElement|Zend_Http_Response
     */
    public function paymentPost($data, $parseXml = false)
    {
        return $this->basicAuthPost($this->getPaymentRequestUrl(), $data, $parseXml);
    }

    /**
     * @param string $url
     * @param array $data
     * @param bool $parseXml
     *
     * @return SimpleXMLElement|Zend_Http_Response
     * @throws MaksuturvaGatewayException
     */
    protected function basicAuthPost($url, $data, $parseXml = false)
    {
        $client = new Zend_Http_Client($url);

        $client->setHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'accept-encoding' => 'gzip, deflate',
            'accept-language' => 'en-US,en;q=0.8'
        ]);

        $client->setAuth($this->sellerId, $this->secretKey);
        $client->setParameterPost($data);
        $client->setMethod(Zend_Http_Client::POST);

        $response = $client->request();

        if (! $response) {
            throw new MasterpassGatewayException();
        }

        if (true === $parseXml) {
            $xmlString = $response->getBody();
            if (! ($response = simplexml_load_string($xmlString))) {
                throw new MasterpassGatewayException();
            }
        }
        return $response;
    }
}

class MasterpassGatewayException extends \Exception {}
