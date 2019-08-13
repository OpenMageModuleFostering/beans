<?php

require_once(Mage::getBaseDir('lib') . '/Beans/Beans.php');

use Beans\Beans;

class Beans_Beans_Helper_Data extends Mage_Core_Helper_Abstract {
    private static $card = null;
    private static $key = null;

    private static function setKey(){
        if (!self::$key)
            self::$key = 'sk_0w9P2ssAn1u7LDPjxbnvC7wpffs5';
    }

    public static function API() {
        self::setKey();
        $beans           = new Beans(self::$key);
        $beans->endpoint = 'http://api.trybeans.com/v1.1/';
        return $beans;
    }


    public static function getCard() {
        if (!self::$card && self::isInstalled()) {
            try {
                self::$card = self::API()->get('card/current');
            } catch (\Beans\Error\BaseError $e) {
                Mage::log('Unable to get card: '.$e->getMessage(), Zend_Log::ERR, 'beans.log');
            }
        }

        return self::$card;
    }

    public static function isInstalled() {
        self::setKey();
        return (bool) self::$key;
    }

    public static function isActive() {
        if (!self::getCard())
            return false;

        return self::$card['is_active'];
    }

    public static function updateSession() {
        $account = Mage::getSingleton('core/session')->getBeansAccount();
        if (!$account)
            return;
        try {
            $account = self::API()->get('account/'.$account['id']);
            Mage::getSingleton('core/session')->setBeansAccount($account);
        } catch (\Beans\Error\BaseError $e) {
            Mage::log('Unable to get account: '.$e->getMessage(), Zend_Log::ERR, 'beans.log');
        }
    }

    public static function applyDiscount($debit, $cart) {

        $website_id = Mage::app()->getWebsite()->getId();
        $role_id    = Mage::getSingleton('customer/session')->getCustomerGroupId();

        // Use debit ID as $code so we can link debit to
        // coupon and commit/cancel easily at a later time

        $code = $debit['id'];

        // Use account ID as $description so we can link rule to
        // account and flush easily unused coupon at a later time

        // create rule
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = Mage::getModel('salesrule/rule');
        $rule->setName('Redemption')
            ->setDescription($debit['account'])
            ->setFromDate(date('Y-m-d'))
            ->setToDate(date('Y-m-d', strtotime('+1 day', time())))
            ->setIsActive(1)
            ->setUsesPerCustomer(1)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::CART_FIXED_ACTION)
            ->setDiscountAmount($debit['quantity'])
            ->setDiscountQty(1)
            ->setUsesPerCoupon(1)
            ->setStopRulesProcessing(0)
            ->setApplyToShipping(0)
            ->setUseAutoGeneration(0)
            ->setIsRss(0)
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setCouponCode($code)
            ->setWebsiteIds(array($website_id))
            ->setCustomerGroupIds($role_id)
            ->save();

        $cart->setCouponCode($code)->collectTotals()->save();

        return true;
    }

    public static function flushDiscount($account_id) {
        if (!$account_id)
            return;

        $rules = Mage::getModel('salesrule/rule')->getCollection();
        $rules->addFieldToFilter('description', array('like' => '%'.$account_id.'%'));

        foreach ($rules as $rule) {
            if(strpos($rule->getCode(), 'dr_') !== 0)
                continue;

            if($rule->getTimesUsed() == 0){
                try{
                    self::API()->post('debit/'.$rule->getCode().'/cancel');
                }catch (\Beans\Error\BaseError $e) {
                    Mage::log('Unable to cancel debit after flush: '.$e->getMessage(), Zend_Log::ERR, 'beans.log');
                    continue;
                }
            }
            $rule->delete();
        }
    }

}
