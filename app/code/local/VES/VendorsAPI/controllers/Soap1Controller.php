<?php

class VES_VendorsAPI_Soap1Controller extends Mage_Core_Controller_Front_Action
{
    public function indexAction() {
        $client = new SoapClient('http://localhost/marketplace/api/v2_soap?wsdl=1');

        // If somestuff requires api authentification,
        // then get a session token
                $session = $client->login('test', 'admin123');

                //$result = $client->call($session, 'vendor.info',array('cd257f23a80d0665d82a164b5b40ccd7'));

        // get attribute set
       // $attributeSets = $client->call($session, 'product_attribute_set.list');
       // $attributeSet = current($attributeSets);


        //v2
        //$result = $client->vendorVendorInfo($session,'cd257f23a80d0665d82a164b5b40ccd7');
        //$result = $client->vendorVendorInfo($session,'cd257f23a80d0665d82a164b5b40ccd7',array('name','email'));
        /*$result = $client->call($session, 'vendor.deleteProduct', array('cd257f23a80d0665d82a164b5b40ccd7', '254', array(
            'categories' => array(2),
            'websites' => array(1),
            'name' => 'Product name2121',
            'description' => 'Product description213123',
            'short_description' => 'Product short description4141',
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

        $productData = array(
            'type_id' => 'simple',
            'approval'=>'2',
            'attribute_set_id' => 9,
            'sku' => 'hiep_customer_'.rand(1000,9999),
            'weight' => 199,
            'status' => 1,
            'visibility' => 4,
            'name' => 'Simple Product 1new',
            'description' => 'Simple Description',
            'short_description' => 'Simple Short Description',
            'price' => 99.95,
            'tax_class_id' => 0,
            'meta_title'    => 'meta title',
            'meta_description'    => 'meta_description title',
            'url_key' => 'url_hiep_test',
            'country_of_manufacture'    => 'AD',
            'price' => '222',
            'special_price'=>'199',
            'special_from_date'=>'2012-03-15 00:00:00',
            'stock_data'=>array(
                'qty'=>'888',
                'min_qty'=>'5',
                'is_in_stock'   => '1',
                'manage_stock'=>'1',
            ),
        );
        //100000095
        //$result = $client->call($session, 'vendorSalesOrderInfo',array('cd257f23a80d0665d82a164b5b40ccd7','100000096'));
        //$result = $client->vendorVendorCreateProduct($session,'cd257f23a80d0665d82a164b5b40ccd7','simple','9','test_soap2_1',$productData);
        $result = $client->vendorSalesOrderInvoiceList($session,'cd257f23a80d0665d82a164b5b40ccd7');
        var_dump($result);

        // If you don't need the session anymore
        //$client->endSession($session);
    }

    public function testAction() {
        $client = new SoapClient('http://localhost/marketplace/api/soap?wsdl');
        $session = $client->login('test', 'admin123');
        $result = $client->call($session, 'vendor_order.list',array('cd257f23a80d0665d82a164b5b40ccd7'));

        var_dump($result);
    }

    public function test1Action() {
        $orderCollection = Mage::getResourceModel('sales/order_item_collection');
        $orderCollection->getSelect()->columns(array(
            'base_grand_total'=>'sum(base_row_total)',
            'grand_total'=>'sum(row_total)',
            'subtotal_incl_tax'=>'sum(row_total_incl_tax)',
            'base_subtotal_incl_tax'=>'sum(base_row_total_incl_tax)',
            'weight'=>'sum(row_weight)',
            'total_qty_ordered'=>'sum(qty_ordered)',
            'base_total_invoiced'=>'sum(base_row_invoiced)',
            'total_invoiced'=>'sum(row_invoiced)',

        ))
            ->group('order_id')
            ->join(array('order_table'=>$orderCollection->getTable('sales/order_grid')),'order_id=entity_id',array('increment_id','status','billing_name','shipping_name','order_currency_code','base_currency_code'))
            ->where('main_table.vendor_id=?',2);

        $orderCollection->load(true,true);
    }
}