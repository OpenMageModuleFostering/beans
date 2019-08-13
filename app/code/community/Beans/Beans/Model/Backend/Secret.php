<?php


class Beans_Beans_Model_Backend_Secret extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {

        return parent::_beforeSave();

        $public = trim ($this->getFieldsetDataValue('public'));
        $secret = trim ($this->getFieldsetDataValue('secret'));
       
        $helper = Mage::helper('beans');
        
        if (!$public || !$secret)
            Mage::throwException($helper->__('Beans Public and Secret keys are mandatory.'));

        // Check public and secret keys only if they have been modified
        if($public!=Mage::getStoreConfig('loyalty/api/public') || $secret!=Mage::getStoreConfig('loyalty/api/secret')){

            $rewards_public = '';
            try{
                $rewards_public = BeansAPI::call('reward/get_all/', array('public'=>$public), false);
            }catch(BeansException $e){
                Mage::throwException($helper->__($e->getMessage()));
            }

            $rewards_secret = '';
            try{
                $rewards_secret = BeansAPI::call('reward/get_all/', array('secret'=>$secret), false);
            }catch(BeansException $e){
                Mage::throwException($helper->__($e->getMessage()));
            }

            if($rewards_public!=$rewards_secret)
                Mage::throwException($helper->__('Public key does not match Secret key.'));
        }
        
        BeansAPI::init($secret);
        $card = BeansAPI::call('card/get/');
        Mage::app()->getStore($this->getStoreCode())->setConfig('loyalty/api/cardname', $card['name']);
        
        return parent::_beforeSave();
    }

}