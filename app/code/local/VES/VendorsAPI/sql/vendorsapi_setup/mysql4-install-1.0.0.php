<?php

$installer = $this;

$installer->startSetup();

$vendorsEavSetup = new VES_Vendors_Model_Resource_Setup();

$this->addAttribute('ves_vendor', 'ves_api_key', array(
    'type' => 'text',
    'input' => 'hidden',
    'frontend_input' => 'hidden',
    'class' => '',
    'backend' => '',
    'frontend' => '',
    'required' => false,
    'user_defined' => false,
    'unique' => true,
));

$installer->endSetup(); 