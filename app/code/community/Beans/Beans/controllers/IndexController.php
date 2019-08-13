<?php

require_once(Mage::getBaseDir('lib') . '/Beans/Beans.php');

class Beans_Beans_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        $this->loadLayout();
        $theme = 'adzuki_bean';
        $this->getLayout()->getBlock('head')->addCss("css/beans/$theme.css");
        $this->renderLayout();
    }

    public function cancelRedeemAction() {

        $helper = Mage::helper('beans');
        $account = Mage::getSingleton('core/session')->getBeansAccount();

        $helper->flushDiscount($account['id']);

        Mage::getSingleton('core/session')->setBeansDebit(null);
        $helper->updateSession();

        return $this->_redirect('checkout/cart');
    }

    public function applyRedeemAction() {

        if(!$this->getRequest()->isPost())
            return $this->_redirect('checkout/cart');

        $account = Mage::getSingleton('core/session')->getBeansAccount();
        if(!isset($account['id']))
            return $this->_redirect('checkout/cart');

        $helper = Mage::helper('beans');
        $helper->flushDiscount($account['id']);

        $cart = Mage::getModel('checkout/cart')->getQuote();

        $amount = min($cart->getBaseSubtotalWithDiscount(), $account['beans_value']);
        $amount = sprintf('%0.2f', $amount);

        $generator = Mage::getModel('salesrule/coupon_codegenerator')->setLength(3);
        $code      = $cart->getID() . $generator->generateCode();

        try {
            $debit = $helper->API()->post('debit', array(
                'account'     => $account['id'],
                'quantity'    => $amount,
                'rule'        => $cart->getBaseCurrencyCode(),
                'uid'         => 'mage_' . $code,
                'description' => 'Debit for a ' . Mage::helper('core')->currency($amount, true, false) . ' discount',
                'commit'      => false
            ));
        } catch(\Beans\Error\BaseError $e) {
            Mage::getSingleton('core/session')->addWarning('Debiting failed with message: '.$e->getMessage());
            Mage::log('Debiting failed: '.$e->getMessage(), Zend_Log::ERR, 'beans.log');
            return $this->_redirect('checkout/cart');
        }

        $helper->updateSession();

        try {
            $helper->applyDiscount($debit, $cart);
        } catch(Exception $e) {
            $helper->API()->post('debit/' . $debit['id'] . '/cancel');
            Mage::getSingleton('core/session')->addWarning('Unable to apply discount: '. $e->getMessage());
            Mage::log('Unable to apply discount: '.$e->getMessage(), Zend_Log::ERR, 'beans.log');
            return $this->_redirect('checkout/cart');
        }

        Mage::getSingleton('core/session')->setBeansDebit($debit);

        return $this->_redirect('checkout/cart');
    }

}