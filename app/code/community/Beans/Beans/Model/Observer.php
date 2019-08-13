<?php

require_once(Mage::getBaseDir('lib') . '/Beans/Beans.php');

class Beans_Beans_Model_Observer {

    private static function createBeansAccount($email, $firstname, $lastname) {
        $helper = Mage::helper('beans');
        try {
            return $helper->API()->post('account', array(
                'email'      => $email,
                'first_name' => $firstname,
                'last_name'  => $lastname,
            ));
        } catch (\Beans\Error\BaseError $e) {
            Mage::log('Unable to create account: '.$e->getMessage(), Zend_Log::ERR, 'beans.log');
        }

        return null;
    }

    public function customerLogin($observer) {
        $helper = Mage::helper('beans');

        if (!$helper->isActive())
            return;

        $customer = $observer->getCustomer();
        $account  = $this->createBeansAccount($customer->email, $customer->firstname, $customer->lastname);
        Mage::getSingleton('core/session')->setBeansAccount($account);

    }

    public function orderPlaced($observer) {

        $helper = Mage::helper('beans');

        if (!$helper->isActive())
            return;

        $order         = $observer->getOrder();
        $applied_rules = $order->getAppliedRuleIds();


        if (!$applied_rules)
            return;

        foreach (explode(",", $applied_rules) as $rule_id) {

            $rule = Mage::getModel('salesrule/rule')->load($rule_id);
            if (strpos($rule->getCouponCode(), 'dr_') === 0) {
                try {
                    $helper->API()->post('debit/' . $rule->getCouponCode() . '/commit', array());
                } catch (\Beans\Error\BaseError $e) {
                    Mage::log('Unable to commit debit after order: ' . $e->getMessage(), Zend_Log::ERR, 'beans.log');
                }
                $helper->flushDiscount($rule->getDescription());
            }
        }
    }

    public function orderPaid($observer) {

        $helper = Mage::helper('beans');

        if (!$helper->isActive())
            return;

        $invoice = $observer->getInvoice();

        $order = $invoice->getOrder();

        $amount = $invoice->getBaseSubtotal() - $invoice->getBaseDiscountAmount();

        $account = null;

        try {
            $account = $helper->API()->get('account/' . $order->getCustomerEmail());
        } catch (\Beans\Error\ValidationError $e) {
            if ($e->getCode() == 404 && !$order->getCustomerIsGuest) {
                $account = $this->createBeansAccount();
            }
            else {
                Mage::log('Looking for Beans account for crediting failed with message: ' . $e->getMessage(), Zend_Log::ERR, 'beans.log');
            }
        } catch (\Beans\Error\BaseError $e) {
            Mage::log('Looking for Beans account for crediting failed with message: ' . $e->getMessage(), Zend_Log::ERR, 'beans.log');
        }

        if (!$account)
            return;

        try {
            $credit = $helper->API()->post('credit', array(
                'account'     => $account['id'],
                'quantity'    => $amount,
                'rule'        => 'beans:currency_spent',
                'uid'         => 'mage_inv_' . $invoice->getID(),
                'description' => 'Customer loyalty rewarded for order #' . $order->getIncrementId(),
                'commit'      => true
            ));
        } catch (\Beans\Error\BaseError $e) {
            if($e->getCode() != 409)
                Mage::log('Crediting failed with message: ' . $e->getMessage(), Zend_Log::ERR, 'beans.log');
        }
    }

}
