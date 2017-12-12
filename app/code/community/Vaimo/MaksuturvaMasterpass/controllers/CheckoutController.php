<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

/**
 * Handles routing in checkout and finalization of the payment
 */
class Vaimo_MaksuturvaMasterpass_CheckoutController extends Vaimo_MaksuturvaMasterpass_Controller_Front_Abstract
{
    protected $paymentMandatoryFields = array(
        "pmt_action",
        "pmt_version",
        "pmt_id",
        "pmt_reference",
        "pmt_amount",
        "pmt_currency",
        "pmt_sellercosts",
        "pmt_paymentmethod"
    );

    public function indexAction()
    {
        if (! $this->validatePaymentAuthorized()) {
            $this->_redirect('checkout/cart');
            return;
        }
        $this->loadLayout();
        $this->getLayout()->getBlock('head')->setTitle($this->__('Masterpass checkout'));
        $this->renderLayout();
    }

    /**
     * Check that quote has gone through authorization
     *
     * @return bool
     */
    protected function validatePaymentAuthorized()
    {
        $quote = $this->getQuote();
        $helper = $this->getHelper();

        if (! $quote->getCustomerEmail()) {
            return false;
        }

        if (! $helper->getSerializedMaksuturvaPaymentId($quote->getPayment())) {
            return false;
        }
        return true;
    }

    public function progressAction()
    {
        // previous step should never be null. We always start with billing and go forward
        $prevStep = $this->getRequest()->getParam('prevStep', false);

        if ($this->_expireAjax() || !$prevStep) {
            return null;
        }

        $layout = $this->getLayout();
        $update = $layout->getUpdate();
        /* Load the block belonging to the current step*/
        $update->load('checkout_onepage_progress_' . $prevStep);
        $layout->generateXml();
        $layout->generateBlocks();
        $output = $layout->getOutput();
        $this->getResponse()->setBody($output);
        return $output;
    }

    public function saveShippingMethodAction()
    {
        if ($this->_expireAjax()) {
            return;
        }

        if ($this->isFormkeyValidationOnCheckoutEnabled() && !$this->_validateFormKey()) {
            return;
        }

        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost('shipping_method', '');
            $result = $this->getMasterpassCheckout()->saveShippingMethod($data);
            // $result will contain error data if shipping method is empty
            if (!$result) {
                Mage::dispatchEvent(
                    'checkout_controller_onepage_save_shipping_method',
                    array(
                        'request' => $this->getRequest(),
                        'quote'   => $this->getQuote()));
                $this->getQuote()->collectTotals();
                $this->_prepareDataJSON($result);

                $this->loadLayout('maksuturvamasterpass_checkout_review');
                //$this->loadLayout('checkout_onepage_review');
                $result['goto_section'] = 'review';
                $result['update_section'] = array(
                    'name' => 'review',
                    'html' => $this->_getReviewHtml()
                );
            }
            $this->getQuote()->setTotalsCollectedFlag(true)->collectTotals()->save();
            $this->_prepareDataJSON($result);
        }
    }

    /**
     * @param SimpleXMLElement $xmlObject
     * @param array $requestFields
     * @param $invalidField Output field name if an invalid field is found
     *
     * @return bool
     */
    private function validatePaymentResponse($xmlObject, $requestFields, &$invalidField)
    {
        foreach ($this->paymentMandatoryFields as $field) {
            if ($xmlObject->{$field} != $requestFields[$field]) {
                $invalidField = $field;
                return false;
            }
        }
        return true;
    }

    public function redirectAction()
    {
        $response = $this->getLayout()->createBlock('masterpass/redirect')->toHtml();
        $this->getResponse()->setBody($response);
    }

    public function cancelAction()
    {
        $this->_redirect('checkout/cart');
    }

    /**
     * Get order review step html
     *
     * @return string
     */
    protected function _getReviewHtml()
    {
        return $this->getLayout()->getBlock('root')->toHtml();
    }

    public function getQuote()
    {
        return $this->getMasterpassCheckout()->getQuote();
    }

    /**
     * Create order action
     */
    public function saveOrderAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_redirect('*/*');
            return;
        }

        if ($this->_expireAjax()) {
            return;
        }

        $result = array();
        try {
            $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds();
            if ($requiredAgreements) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                $diff = array_diff($requiredAgreements, $postedAgreements);
                if ($diff) {
                    $result['success'] = false;
                    $result['error'] = true;
                    $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->_prepareDataJSON($result);
                    return;
                }
            }

            $this->getMasterpassCheckout()->saveOrder();

            $redirectUrl = $this->getPaymentMethod()->getOrderPlaceRedirectUrl();
            $result['success'] = true;
            $result['error']   = false;
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $result['error_messages'] = $message;
            }
            $result['goto_section'] = 'payment';
            $result['update_section'] = array(
                'name' => 'payment-method',
                'html' => $this->_getPaymentMethodsHtml()
            );
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getQuote(), $e->getMessage());
            $result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $e->getMessage();

            $gotoSection = $this->getCheckout()->getGotoSection();
            if ($gotoSection) {
                $result['goto_section'] = $gotoSection;
                $this->getCheckout()->setGotoSection(null);
            }
            $updateSection = $this->getCheckout()->getUpdateSection();
            if ($updateSection) {
                if (isset($this->_sectionUpdateFunctions[$updateSection])) {
                    $updateSectionFunction = $this->_sectionUpdateFunctions[$updateSection];
                    $result['update_section'] = array(
                        'name' => $updateSection,
                        'html' => $this->$updateSectionFunction()
                    );
                }
                $this->getCheckout()->setUpdateSection(null);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getQuote(), $e->getMessage());
            $result['success']  = false;
            $result['error']    = true;
            $result['error_messages'] = $this->__('There was an error processing your order. Please contact us or try again later.');
        }
        $this->getQuote()->save();
        /**
         * when there is redirect to third party, we don't want to save order yet.
         * we will save the order in return action.
         */
        if (isset($redirectUrl)) {
            $result['redirect'] = $redirectUrl;
        }

        $this->_prepareDataJSON($result);
    }

    /**
     * Send payment finalization call to Maksuturva
     */
    public function placeOrderAction()
    {
        if ($this->isFormkeyValidationOnCheckoutEnabled() && !$this->_validateFormKey()) {
            return;
        }

        /* @var $method Vaimo_MaksuturvaMasterpass_Model_Masterpass */
        $method = $this->getPaymentMethod();

        /* @var $gateway Vaimo_MaksuturvaMasterpass_Model_Gateway_Payment */
        $gateway = $method->getPaymentGateway();

        $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        $order = Mage::getModel('sales/order')->load($lastOrderId);

        if (! $order->getId()) {
            Mage::getSingleton('core/session')->addError($this->__('Your order is not valid.'));
            $this->_redirect('checkout/cart');
            return;
        }
        if (! $order->canInvoice()) {
            Mage::getSingleton('core/session')->addSuccess($this->__('Your order is already paid.'));
        }
        if ($order->getState() != Mage_Sales_Model_Order::STATE_NEW) {
            Mage::getSingleton('core/session')->addSuccess($this->__('Your order is already authorized'));
        }

        $gateway->setOrder($order);

        $requestFields = $gateway->getFormFields();

        $responseXml = $gateway->paymentPost($requestFields, true);

        if ($responseXml->error) {
            $this->_redirect('masterpass/checkout/error', array('pmt_id' => $order->getPayment()->getMaksuturvaPmtId()));
            return false;
        }

        if (! $this->validatePaymentResponse($responseXml, $requestFields, $invalidField)) {
            $this->_redirect('masterpass/checkout/error', array('type' => Vaimo_Maksuturva_Model_Maksuturva::ERROR_EMPTY_FIELD, 'field' => $invalidField));
            return false;
        }

        if ($responseXml->{'pmt_sellercosts'} != $requestFields['pmt_sellercosts']) {
            if ($responseXml->{'pmt_sellercosts'} < $requestFields['pmt_sellercosts']) {
                $this->_redirect('masterpass/index/error', array('type' => Vaimo_Maksuturva_Model_Maksuturva::ERROR_SELLERCOSTS_VALUES_MISMATCH));
                return;
            } else {
                $sellercosts_change = $requestFields['pmt_sellercosts'] - $responseXml->{'pmt_sellercosts'};
                $msg = $this->__("Payment captured by Maksuturva. NOTE: Change in the sellercosts + {$sellercosts_change} EUR.");
            }
        } else {
            $msg = $this->__("Payment captured by Maksuturva");
        }

        try {
            $this->_createInvoice($order);

            if (! $order->getEmailSent()) {
                try {
                    $order->sendNewOrderEmail();
                    $order->setEmailSet(true);
                } catch (\Exception $e) {
                    Mage::logException($e);
                }
            }

            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $msg, false);
            $order->save();

            Mage::dispatchEvent("ic_order_success", array("order" => $order));

            $quote = $this->getQuote();
            if ($quote->getId()) {
                $quote->setIsActive(false)->save();
            }

            $this->_redirect('masterpass/checkout/success', array('_secure' => true));
        } catch (\Exception $e) {
            $this->_redirect('masterpass/checkout/error');
        }
    }

    public function successAction()
    {
        $session = $this->getCheckout();
        if (! $session->getLastSuccessQuoteId()) {
            $this->_redirect('checkout/cart');
            return;
        }

        $lastQuoteId = $session->getLastQuoteId();
        $lastOrderId = $session->getLastOrderId();
        $lastRecurringProfiles = $session->getLastRecurringProfileIds();
        if (! $lastQuoteId || (! $lastOrderId && empty($lastRecurringProfiles))) {
            $this->_redirect('checkout/cart');
            return;
        }

        $session->clear();
        $this->loadLayout();
        $this->_initLayoutMessages('checkout/session');
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($lastOrderId)));
        $this->renderLayout();
    }

    public function errorAction()
    {
        $pmt_id = $this->getRequest()->getParam('pmt_id');
        $session = Mage::getSingleton('checkout/session');

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');

        $incrementId = $session->getLastRealOrderId();

        if (empty($incrementId) || empty($pmt_id)) {
            $session->addError($this->__('Unknown error on maksuturva payment module.'));
            $this->_redirect('checkout/cart');
            return;
        }

        $order->loadByIncrementId($incrementId);
        $payment = $order->getPayment();
        $additional_data = unserialize($payment->getAdditionalData());

        $paramsArray = $this->getRequest()->getParams();

        if (array_key_exists('pmt_id', $paramsArray)) {
            $session->addError($this->__('Maksuturva returned an error on your payment.'));
        } else {
            switch ($paramsArray['type']) {
                case Vaimo_Maksuturva_Model_Maksuturva::ERROR_INVALID_HASH:
                    $session->addError($this->__('Invalid hash returned'));
                    break;

                case Vaimo_Maksuturva_Model_Maksuturva::ERROR_EMPTY_FIELD:
                    $session->addError($this->__('Gateway returned and empty field') . ' ' . $paramsArray['field']);
                    break;

                case Vaimo_Maksuturva_Model_Maksuturva::ERROR_VALUES_MISMATCH:
                    $session->addError($this->__('Value returned from Maksuturva does not match:') . ' ' . $paramsArray['message']);
                    break;

                case Vaimo_Maksuturva_Model_Maksuturva::ERROR_SELLERCOSTS_VALUES_MISMATCH:
                    $session->addError($this->__('Shipping and payment costs returned from Maksuturva do not match.') . ' ' . $paramsArray['message']);
                    break;

                default:
                    $session->addError($this->__('Unknown error on maksuturva payment module.'));
                    break;
            }
        }

        // received pmt_id must always match to pmt_id on payment
        if ($additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_TRANSACTION_ID] !== $pmt_id) {
            $this->_redirect('checkout/cart');
            return;
        }

        if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
            if (isset($paramsArray['type']) && $paramsArray['type'] == Vaimo_Maksuturva_Model_Maksuturva::ERROR_SELLERCOSTS_VALUES_MISMATCH) {
                $order->addStatusHistoryComment($this->__('Mismatch in seller costs returned from Maksuturva. New sellercosts: ' . $paramsArray["new_sellercosts"] . ' EUR,' . ' was ' . $paramsArray["old_sellercosts"] . ' EUR.'));
            } else {
                $order->addStatusHistoryComment($this->__('Error on Maksuturva return'));
            }
            if ($order->canCancel()) {
                $order->cancel();
            }
            $order->save();
            $this->_redirect('checkout/onepage/failure');
            return;
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * @param Mage_Sales_Model_Order $order
     *
     * @return bool|Mage_Sales_Model_Order_Invoice
     */
    protected function _createInvoice($order)
    {
        if (!$order->canInvoice()) {
            return false;
        }

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $order->getPayment();
        $payment->setTransactionId($order->getPayment()->getMaksuturvaPmtId())
            ->setTransactionClosed(0);
        $order->save();

        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = $order->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
        $invoice->register();

        $payment->setCreatedInvoice($invoice);
        $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, $invoice, true);

        if ($invoice->canCapture()) {
            $invoice->capture();
        }
        $invoice->save();
        $order->addRelatedObject($invoice);
        return $invoice;
    }

    protected function _expireAjax()
    {
        if (!$this->getQuote()->hasItems()
            || $this->getQuote()->getHasError()
        ) {
            $this->_ajaxRedirectResponse();
            return true;
        }
        $action = strtolower($this->getRequest()->getActionName());
        if (Mage::getSingleton('checkout/session')->getCartWasUpdated(true)
            && !in_array($action, array('index', 'progress'))
        ) {
            $this->_ajaxRedirectResponse();
            return true;
        }
        return false;
    }

    /**
     * Send Ajax redirect response
     *
     * @return Mage_Checkout_OnepageController
     */
    protected function _ajaxRedirectResponse()
    {
        $this->getResponse()
            ->setHeader('HTTP/1.1', '403 Session Expired')
            ->setHeader('Login-Required', 'true')
            ->sendResponse();
        return $this;
    }

    /**
     * Prepare JSON formatted data for response to client
     *
     * @param $response
     * @return Zend_Controller_Response_Abstract
     */
    protected function _prepareDataJSON($response)
    {
        $this->getResponse()->setHeader('Content-type', 'application/json', true);
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }
}
