<?php

$installer = $this;

$installer->startSetup();


$collection = Mage::getModel('vendors/vendor')->getCollection();
foreach ($collection as $vendor) {
    $vendor->setData('ves_api_key', md5($vendor->getVendorId() . md5(strtotime(now()) . rand(1000, 9999))))->save();
}

$installer->endSetup(); 