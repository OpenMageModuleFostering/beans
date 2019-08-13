<?php
class Beans_Beans_Model_Source_Style3
{
  public function toOptionArray()
  {
    return array(
      array('value' => 'normal',    'label' => Mage::helper('beans')->__('normal')),
      array('value' => 'light',     'label' => Mage::helper('beans')->__('light')),
      array('value' => '',          'label' => Mage::helper('beans')->__('none')),
    );
  }
}