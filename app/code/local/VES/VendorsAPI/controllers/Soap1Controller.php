<?php

class VES_VendorsAPI_Soap1Controller extends Mage_Core_Controller_Front_Action
{
    public function indexAction() {
        $client = new SoapClient('http://localhost/marketplace/api/v2_soap/?wsdl=1');

        // If somestuff requires api authentification,
        // then get a session token
                $session = $client->login('test', 'admin123');

               // $result = $client->call($session, 'vendor.info','cd257f23a80d0665d82a164b5b40ccd7');

        // get attribute set
       // $attributeSets = $client->call($session, 'product_attribute_set.list');
       // $attributeSet = current($attributeSets);


        //v2
        $result = $client->vendorVendorInfo($session,'cd257f23a80d0665d82a164b5b40ccd7');

        /*$result = $client->call($session, 'vendor.createProduct', array('cd257f23a80d0665d82a164b5b40ccd7','simple', $attributeSet['set_id'], 'product_sku1', array(
            'categories' => array(2),
            'websites' => array(1),
            'name' => 'Product name',
            'description' => 'Product description',
            'short_description' => 'Product short description',
            'weight' => '10',
            'status' => '1',
            'url_key' => 'product-url-key',
            'url_path' => 'product-url-path',
            'visibility' => '4',
            'price' => '100',
            'tax_class_id' => 1,
            'meta_title' => 'Product meta title',
            'meta_keyword' => 'Product meta keyword',
            'meta_description' => 'Product meta description'
        )));*/
                var_dump($result);

        // If you don't need the session anymore
        //$client->endSession($session);
    }
}