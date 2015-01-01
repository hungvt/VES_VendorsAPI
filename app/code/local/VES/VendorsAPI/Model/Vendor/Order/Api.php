<?php

class VES_VendorsAPI_Model_Vendor_Order_Api extends VES_VendorsAPI_Model_Api_Resource_Order
{
    protected $_orderGeneralAttributes = array(

    );


    /**
     * Initialize attributes map
     */
    public function __construct()
    {
        $this->_attributesMap = array(
            'order' => array('order_id' => 'entity_id'),
            'order_address' => array('address_id' => 'entity_id'),
            'order_payment' => array('payment_id' => 'entity_id')
        );
    }

    /**
     * Initialize basic order model
     *
     * @param mixed $orderIncrementId
     * @return Mage_Sales_Model_Order
     */
    protected function _initOrder($orderIncrementId)
    {
        $order = Mage::getModel('sales/order');

        /* @var $order Mage_Sales_Model_Order */

        $order->loadByIncrementId($orderIncrementId); //Mage::log($order->getData());

        if (!$order->getId()) {
            $this->_fault('not_exists');
        }

        return $order;
    }

    /**
     * Retrieve list of orders. Filtration could be applied
     *
     * @param string $vendorApiKey
     * @param null|object|array $filters
     * @return array
     */
    public function items($vendorApiKey, $filters = null)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $orders = array();

        //TODO: add full name logic
        $billingAliasName = 'billing_o_a';
        $shippingAliasName = 'shipping_o_a';

        /** @var $orderCollection Mage_Sales_Model_Mysql4_Order_Collection */
        if($this->_isAdvancedMode()) {
            $orderCollection = Mage::getModel("sales/order")->getCollection();
            $billingFirstnameField = "$billingAliasName.firstname";
            $billingLastnameField = "$billingAliasName.lastname";
            $shippingFirstnameField = "$shippingAliasName.firstname";
            $shippingLastnameField = "$shippingAliasName.lastname";
            $orderCollection->addAttributeToSelect('*')
                ->addAddressFields()
                ->addExpressionFieldToSelect('billing_firstname', "{{billing_firstname}}",
                    array('billing_firstname' => $billingFirstnameField))
                ->addExpressionFieldToSelect('billing_lastname', "{{billing_lastname}}",
                    array('billing_lastname' => $billingLastnameField))
                ->addExpressionFieldToSelect('shipping_firstname', "{{shipping_firstname}}",
                    array('shipping_firstname' => $shippingFirstnameField))
                ->addExpressionFieldToSelect('shipping_lastname', "{{shipping_lastname}}",
                    array('shipping_lastname' => $shippingLastnameField))
                ->addExpressionFieldToSelect('billing_name', "CONCAT({{billing_firstname}}, ' ', {{billing_lastname}})",
                    array('billing_firstname' => $billingFirstnameField, 'billing_lastname' => $billingLastnameField))
                ->addExpressionFieldToSelect('shipping_name', 'CONCAT({{shipping_firstname}}, " ", {{shipping_lastname}})',
                    array('shipping_firstname' => $shippingFirstnameField, 'shipping_lastname' => $shippingLastnameField)
            );

            //set filter vendor id
            $orderCollection->getSelect()->where('main_table.vendor_id=?',$vendor->getId());
        } else {
            $orderCollection = Mage::getResourceModel('sales/order_item_collection');

            /**
             * may be not,because must add filter
             */
            /*$orderCollection->addFieldToSelect(array('vendor_id','order_id','store_id','created_at','updated_at',
                                                    ));*/
            $orderCollection->removeFieldFromSelect('item_id');

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
                ->where('main_table.vendor_id=?',$vendor->getId());
        }
        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_attributesMap['order']);
        try {
            foreach ($filters as $field => $value) {
                $orderCollection->addFieldToFilter($field, $value);
            }
            $orderCollection->load(true, true);
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        foreach ($orderCollection as $order) {
            $orders[] = $this->_getAttributes($order, 'order');
        }
        return $orders;
    }

    /**
     * Retrieve full order information
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @return array
     */
    public function info($vendorApiKey, $orderIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }


        $order = $this->_initOrder($orderIncrementId);

        //check vendor is owner of order
        $this->_isOrderOwner($vendor,$orderIncrementId);

        if ($order->getGiftMessageId() > 0) {
            $order->setGiftMessage(
                Mage::getSingleton('giftmessage/message')->load($order->getGiftMessageId())->getMessage()
            );
        }

        if ($this->_isAdvancedMode()) {
            $result = $this->_getAttributes($order, 'order');

            $result['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
            $result['billing_address'] = $this->_getAttributes($order->getBillingAddress(), 'order_address');
            $result['items'] = array();

            foreach ($order->getAllItems() as $item) {
                if (!$item->getId()) {
                    continue;
                }
                if ($item->getVendorId() != $vendor->getId()) {
                    continue;
                }

                if ($item->getGiftMessageId() > 0) {
                    $item->setGiftMessage(
                        Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
                    );
                }

                $result['items'][] = $this->_getAttributes($item, 'order_item');
            }

            $result['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');

        }
        else {
            $result = $this->items($vendorApiKey, array('increment_id'=>$orderIncrementId));
            $result = $result[0];
            foreach($order->getAllItems() as $item) {
                if (!$item->getId()) {
                    continue;
                }
                if ($item->getVendorId() != $vendor->getId()) {
                    continue;
                }

                if ($item->getGiftMessageId() > 0) {
                    $item->setGiftMessage(
                        Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
                    );
                }

                $result['items'][] = $this->_getAttributes($item, 'order_item');
            }
        }

        $result['status_history'] = array();

        foreach ($order->getAllStatusHistory() as $history) {
            $result['status_history'][] = $this->_getAttributes($history, 'order_status_history');
        }
        //Mage::log($result);

        return $result;
    }

    /**
     * Add comment to order
     * METHOD SUPPORT ONLY ADVANCED MODE
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @param string $status
     * @param string $comment
     * @param boolean $notify
     * @return boolean
     */
    public function addComment($vendorApiKey,$orderIncrementId, $status, $comment = '', $notify = false)
    {
/*        if(!$this->_isAdvancedMode()) {
            $this->_fault('method_not_support');
        }*/

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = $this->_initOrder($orderIncrementId);

        //check vendor is owner of order
        $this->_isOrderOwner($vendor,$orderIncrementId);

        $historyItem = $order->addStatusHistoryComment($comment, $status);
        $historyItem->setIsCustomerNotified($notify)->save();


        try {
            if ($notify && $comment) {
                $oldStore = Mage::getDesign()->getStore();
                $oldArea = Mage::getDesign()->getArea();
                Mage::getDesign()->setStore($order->getStoreId());
                Mage::getDesign()->setArea('frontend');
            }

            $order->save();
            $order->sendOrderUpdateEmail($notify, $comment);
            if ($notify && $comment) {
                Mage::getDesign()->setStore($oldStore);
                Mage::getDesign()->setArea($oldArea);
            }

        } catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        }

        return true;
    }

    /**
     * Hold order
     *METHOD SUPPORT ONLY ADVANCED MODE
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @return boolean
     */
    public function hold($vendorApiKey, $orderIncrementId)
    {
/*        if(!$this->_isAdvancedMode()) {
            $this->_fault('method_not_support');
        }*/

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = $this->_initOrder($orderIncrementId);

        //check vendor is owner of order
        $this->_isOrderOwner($vendor,$orderIncrementId);

        try {
            $order->hold();
            $order->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        }

        return true;
    }

    /**
     * Unhold order
     *METHOD SUPPORT ONLY ADVANCED MODE
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @return boolean
     */
    public function unhold($vendorApiKey, $orderIncrementId)
    {
/*        if(!$this->_isAdvancedMode()) {
            $this->_fault('method_not_support');
        }*/

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = $this->_initOrder($orderIncrementId);

        //check vendor is owner of order
        $this->_isOrderOwner($vendor,$orderIncrementId);

        try {
            $order->unhold();
            $order->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        }

        return true;
    }

    /**
     * Cancel order
     *METHOD SUPPORT ONLY ADVANCED MODE
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @return boolean
     */
    public function cancel($vendorApiKey,$orderIncrementId)
    {
        /*if(!$this->_isAdvancedMode()) {
            $this->_fault('method_not_support');
        }*/

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = $this->_initOrder($orderIncrementId);

        //check vendor is owner of order
        $this->_isOrderOwner($vendor,$orderIncrementId);

        if (Mage_Sales_Model_Order::STATE_CANCELED == $order->getState()) {
            $this->_fault('status_not_changed');
        }
        try {
            $order->cancel();
            $order->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        }
        if (Mage_Sales_Model_Order::STATE_CANCELED != $order->getState()) {
            $this->_fault('status_not_changed');
        }
        return true;
    }

    /**
     * check vendor is owner of order
     * @param object $vendor
     * @param $orderIncrementId/*
     * @return bool
     */
    protected function _isOrderOwner($vendor,$orderIncrementId) {
        return parent::_isOrderOwnerAdvanced($vendor, $orderIncrementId, 'order');
    }

} // Class Mage_Sales_Model_Order_Api End
