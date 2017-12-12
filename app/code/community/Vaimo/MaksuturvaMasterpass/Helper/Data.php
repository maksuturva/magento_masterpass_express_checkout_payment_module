<?php
/**
 * Copyright (c) 2017 Vaimo Finland Oy, Suomen Maksuturva Oy
 * See LICENSE.txt for license details.
 */

class Vaimo_MaksuturvaMasterpass_Helper_Data extends Vaimo_Maksuturva_Helper_Data
{
    /**
     * @param $fullname
     * @param $firstname
     * @param $lastname
     *
     * @return bool
     */
    public function parseName($fullname, &$firstname, &$lastname)
    {
        if (empty($fullname)) {
            return false;
        }
        $names = explode(' ', $fullname);
        $nameCount = count($names);

        if ($nameCount == 1) {
            $firstname = $names[0];
            $lastname = '';
        } else if ($nameCount == 2) {
            $firstname = $names[0];
            $lastname = $names[1];
        } else {
            $firstname = implode(' ', array_splice($names, $nameCount - 1));
            $lastname = $names[$nameCount - 1];
        }
        return true;
    }

    /**
     * @return bool
     */
    public function canMasterpassCheckout()
    {
        /** @var Vaimo_MaksuturvaMasterpass_Model_Masterpass $masterpass */
        $masterpass = Mage::getModel('maksuturvamasterpass/masterpass');
        return $masterpass->getConfigValue('active');
    }

    public function getSerializedMaksuturvaPaymentId($payment)
    {
        $additional_data = unserialize($payment->getAdditionalData());
        if (isset($additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_TRANSACTION_ID])) {
            return $additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_TRANSACTION_ID];
        }
        return false;
    }

    public function setPaymentMaksuturvaPmtId($payment, $pmt_id)
    {
        $additional_data = unserialize($payment->getAdditionalData());
        $additional_data[Vaimo_Maksuturva_Model_Maksuturva::MAKSUTURVA_TRANSACTION_ID] = $pmt_id;
        $payment->setAdditionalData(serialize($additional_data));
        $payment->setMaksuturvaPmtId($pmt_id);
        $payment->save();
    }

    /**
     * @param $address Mage_Sales_Model_Quote_Address
     */
    public function serializeAddress($address)
    {
        return serialize(
            array(
                'firstname' => $address->getFirstname(),
                'lastname' => $address->getLastname(),
                'street' => $address->getStreet(),
                'city' => $address->getCity(),
                'postcode' => $address->getPostcode()
            )
        );
    }
}