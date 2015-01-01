<?php

class VES_VendorsAPI_Model_Api2_Vendor_Order extends VES_VendorsAPI_Model_Api2_Vendor
{
    /**#@+
     * Parameters' names in config with special ACL meaning
     */
    const PARAM_GIFT_MESSAGE   = '_gift_message';
    const PARAM_ORDER_COMMENTS = '_order_comments';
    const PARAM_PAYMENT_METHOD = '_payment_method';
    const PARAM_TAX_NAME       = '_tax_name';
    const PARAM_TAX_RATE       = '_tax_rate';
    /**#@-*/

    /**
     * Add gift message info to select
     *
     * @param Mage_Sales_Model_Resource_Order_Collection $collection
     * @return Mage_Sales_Model_Api2_Order
     */
    protected function _addGiftMessageInfo(Mage_Sales_Model_Resource_Order_Collection $collection)
    {
        $collection->getSelect()->joinLeft(
            array('gift_message' => $collection->getTable('giftmessage/message')),
            'main_table.gift_message_id = gift_message.gift_message_id',
            array(
                'gift_message_from' => 'gift_message.sender',
                'gift_message_to'   => 'gift_message.recipient',
                'gift_message_body' => 'gift_message.message'
            )
        );

        return $this;
    }

    /**
     * Add order payment method field to select
     *
     * @param Mage_Sales_Model_Resource_Order_Collection $collection
     * @return Mage_Sales_Model_Api2_Order
     */
    protected function _addPaymentMethodInfo(Mage_Sales_Model_Resource_Order_Collection $collection)
    {
        $collection->getSelect()->joinLeft(
            array('payment_method' => $collection->getTable('sales/order_payment')),
            'main_table.entity_id = payment_method.parent_id',
            array('payment_method' => 'payment_method.method')
        );

        return $this;
    }

    /**
     * Add order tax information to select
     *
     * @param Mage_Sales_Model_Resource_Order_Collection $collection
     * @return Mage_Sales_Model_Api2_Order
     */
    protected function _addTaxInfo(Mage_Sales_Model_Resource_Order_Collection $collection)
    {
        $taxInfoFields = array();

        if ($this->_isTaxNameAllowed()) {
            $taxInfoFields['tax_name'] = 'order_tax.title';
        }
        if ($this->_isTaxRateAllowed()) {
            $taxInfoFields['tax_rate'] = 'order_tax.percent';
        }
        if ($taxInfoFields) {
            $collection->getSelect()->joinLeft(
                array('order_tax' => $collection->getTable('sales/order_tax')),
                'main_table.entity_id = order_tax.order_id',
                $taxInfoFields
            );
        }
        return $this;
    }

    /**
     * Retrieve a list or orders' addresses in a form of [order ID => array of addresses, ...]
     *
     * @param array $orderIds Orders identifiers
     * @return array
     */
    protected function _getAddresses(array $orderIds)
    {
        $addresses = array();

        if ($this->_isSubCallAllowed('vendor_sales_order_address')) {
            /** @var $addressesFilter Mage_Api2_Model_Acl_Filter */
            $addressesFilter = $this->_getSubModel('vendor_sales_order_address', array())->getFilter();
            // do addresses request if at least one attribute allowed
            if ($addressesFilter->getAllowedAttributes()) {
                /* @var $collection Mage_Sales_Model_Resource_Order_Address_Collection */
                $collection = Mage::getResourceModel('sales/order_address_collection');

                $collection->addAttributeToFilter('parent_id', $orderIds);

                foreach ($collection->getItems() as $item) {
                    $addresses[$item->getParentId()][] = $addressesFilter->out($item->toArray());
                }
            }
        }
        return $addresses;
    }

    /**
     * Retrieve collection instance for orders list by vendor api key
     *
     * @return Mage_Sales_Model_Resource_Order_Collection
     */
    protected function _getCollectionForRetrieve()
    {
        /** @var $customer VES_Vendors_Model_Vendor */
        $vendor = $this->_getVendorByApiKey($this->getRequest()->getParam('api'));
        if(!$vendor->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

        /** @var $collection Mage_Sales_Model_Resource_Order_Collection */
        if($this->_isAdvancedMode())
        {
            $collection = Mage::getResourceModel('sales/order_collection');
            $collection->addFieldToFilter('vendor_id',$vendor->getId());
        } else {
            $collection = Mage::getResourceModel('sales/order_item_collection');
            $collection->removeFieldFromSelect('item_id');

            $collection->getSelect()->columns(array(
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
                ->join(array('order_table'=>$collection->getTable('sales/order_grid')),'order_id=entity_id',array('entity_id','increment_id','status','billing_name','shipping_name','order_currency_code','base_currency_code'))
                ->where('main_table.vendor_id=?',$vendor->getId());
                ;
        }

       // Mage::log($collection->count());

        $this->_applyCollectionModifiers($collection);

        return $collection;
    }

    /**
     * Retrieve collection instance for single order
     *
     * @param int $orderId Order identifier
     * @return Mage_Sales_Model_Resource_Order_Collection
     */
    protected function _getCollectionForSingleRetrieve($orderId)
    {
        /** @var $collection Mage_Sales_Model_Resource_Order_Collection */
        $collection = $this->_getCollectionForRetrieve();
        //$collection->addFieldToFilter($collection->getResource()->getIdFieldName(), $orderId);

        if($this->_isAdvancedMode()) return $collection->addFieldToFilter($collection->getResource()->getIdFieldName(), $orderId);
        else {
            $collection->getSelect()->where('order_table.entity_id=?',$orderId);

        }
        Mage::log(get_class($collection));
        return $collection;
    }

    /**
     * Retrieve a list or orders' comments in a form of [order ID => array of comments, ...]
     *
     * @param array $orderIds Orders' identifiers
     * @return array
     */
    protected function _getComments(array $orderIds)
    {
        /** @var $customer VES_Vendors_Model_Vendor */
        $vendor = $this->_getVendorByApiKey($this->getRequest()->getParam('api'));
        if(!$vendor->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        $comments = array();

        if ($this->_isOrderCommentsAllowed() && $this->_isSubCallAllowed('vendor_sales_order_comment')) {
            /** @var $commentsFilter Mage_Api2_Model_Acl_Filter */
            $commentsFilter = $this->_getSubModel('vendor_sales_order_comment', array())->getFilter();
            // do comments request if at least one attribute allowed
            if ($commentsFilter->getAllowedAttributes()) {
                foreach ($this->_getCommentsCollection($orderIds)->getItems() as $item) {
                    if($item->getVendorId() != $vendor->getId()) continue;
                    $comments[$item->getParentId()][] = $commentsFilter->out($item->toArray());
                }
            }
        }
        return $comments;
    }

    /**
     * Prepare and return order comments collection
     *
     * @param array $orderIds Orders' identifiers
     * @return Mage_Sales_Model_Resource_Order_Status_History_Collection|Object
     */
    protected function _getCommentsCollection(array $orderIds)
    {
        /* @var $collection Mage_Sales_Model_Resource_Order_Status_History_Collection */
        $collection = Mage::getResourceModel('sales/order_status_history_collection');
        $collection->setOrderFilter($orderIds);

        return $collection;
    }

    /**
     * Retrieve a list or orders' items in a form of [order ID => array of items, ...]
     *
     * @param array $orderIds Orders identifiers
     * @return array
     */
    protected function _getItems(array $orderIds)
    {
        Mage::log('get items');
        /** @var $customer VES_Vendors_Model_Vendor */
        $vendor = $this->_getVendorByApiKey($this->getRequest()->getParam('api'));
        if(!$vendor->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        $items = array();

        if ($this->_isSubCallAllowed('vendor_sales_order_item')) {
            /** @var $itemsFilter Mage_Api2_Model_Acl_Filter */
            $itemsFilter = $this->_getSubModel('vendor_sales_order_item', array())->getFilter();
            // do items request if at least one attribute allowed
            if ($itemsFilter->getAllowedAttributes()) {
               // Mage::log($itemsFilter);
                /* @var $collection Mage_Sales_Model_Resource_Order_Item_Collection */
                $collection = Mage::getResourceModel('sales/order_item_collection');
                $collection->addAttributeToFilter('order_id', $orderIds)->addFieldToFilter('vendor_id',$vendor->getId());
                //Mage::log($collection->getSelectSql(true));
                foreach ($collection->getItems() as $item) {
                    $items[$item->getOrderId()][] = $itemsFilter->out($item->toArray());
                }
            }
        }
        return $items;
    }

    /**
     * Check gift messages information is allowed
     *
     * @return bool
     */
    public function _isGiftMessageAllowed()
    {
        return in_array(self::PARAM_GIFT_MESSAGE, $this->getFilter()->getAllowedAttributes());
    }

    /**
     * Check order comments information is allowed
     *
     * @return bool
     */
    public function _isOrderCommentsAllowed()
    {
        return in_array(self::PARAM_ORDER_COMMENTS, $this->getFilter()->getAllowedAttributes());
    }

    /**
     * Check payment method information is allowed
     *
     * @return bool
     */
    public function _isPaymentMethodAllowed()
    {
        return in_array(self::PARAM_PAYMENT_METHOD, $this->getFilter()->getAllowedAttributes());
    }

    /**
     * Check tax name information is allowed
     *
     * @return bool
     */
    public function _isTaxNameAllowed()
    {
        return in_array(self::PARAM_TAX_NAME, $this->getFilter()->getAllowedAttributes());
    }

    /**
     * Check tax rate information is allowed
     *
     * @return bool
     */
    public function _isTaxRateAllowed()
    {
        return in_array(self::PARAM_TAX_RATE, $this->getFilter()->getAllowedAttributes());
    }

    /**
     * Get orders list
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $collection = $this->_getCollectionForRetrieve();

        //Mage::log($collection->count());

        if ($this->_isPaymentMethodAllowed() and $this->_isAdvancedMode()) {
            $this->_addPaymentMethodInfo($collection);
        }
        if ($this->_isGiftMessageAllowed() and $this->_isAdvancedMode()) {
            $this->_addGiftMessageInfo($collection);
        }
        if($this->_isAdvancedMode()) $this->_addTaxInfo($collection);

        $ordersData = array();

        foreach ($collection->getItems() as $order) {
            if($this->_isAdvancedMode())$ordersData[$order->getId()] = $order->toArray();
            else $ordersData[$order->getData('entity_id')] = $order->toArray();
        }
        if ($ordersData) {
            foreach ($this->_getAddresses(array_keys($ordersData)) as $orderId => $addresses) {
                $ordersData[$orderId]['addresses'] = $addresses;
            }
            foreach ($this->_getItems(array_keys($ordersData)) as $orderId => $items) {
                $ordersData[$orderId]['order_items'] = $items;
            }
            foreach ($this->_getComments(array_keys($ordersData)) as $orderId => $comments) {
                $ordersData[$orderId]['order_comments'] = $comments;
            }
        }
        return $ordersData;
    }

    /**
     * check order object is owned of vendor (advanced mode only)
     * @param object $vendor
     * @param string $orderId
     * @param string|null $type
     */
    protected function _isOrderOwner($vendor, $orderId, $type='order') {
        switch($type) {
            case 'order': $obj = Mage::getModel('sales/order')->load($orderId);break;
            case 'order_invoice': $obj = Mage::getModel('sales/order_invoice')->load($orderId);break;
            case 'order_shipment': $obj = Mage::getModel('sales/order_shipment')->load($orderId);break;
            case 'order_creditmemo': $obj = Mage::getModel('sales/order_creditmemo')->load($orderId);break;
        }

        if(!$obj->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        if($obj->getVendorId() != $vendor->getId()) {
            switch($type) {
                case 'order':$this->_critical('This vendor not is owner of order.');break;
                case 'order_invoice':$this->_critical('This vendor not is owner of invoice.');break;
                case 'order_shipment':$this->_critical('This vendor not is owner of shipment.');break;
                case 'order_creditmemo':$this->_critical('This vendor not is owner of creditmemo.');break;
            }
        }
    }

    public function getVendorApiKey() {
        return $this->getRequest()->getParam('api');
    }

    /**
     * return true if system in advanced mode(Advanced and Advanced X),false if other
     * @return boolean
     */
    protected function _isAdvancedMode() {
        return Mage::helper('vendors')->isAdvancedMode();
    }

    /**
     * check order (or invoice...) owned by vendor
     * in advanced mode check vendor id of order(invoice...)
     * in general mode check order item(invoice ... same advanced mode)
     * @param $vendor
     * @param $orderId
     * @param string $type
     */
    protected function _isOrderOwnerAdvanced($vendor,$orderId, $type='order') {
        if($this->_isAdvancedMode()) return $this->_isOrderOwner($vendor,$orderId,$type);
        else {
            if(in_array($type,array('order_invoice','order_shipment','order_creditmemo'))) {
                return $this->_isOrderOwner($vendor,$orderId,$type);
            } else {
                $obj = Mage::getModel('sales/order')->load($orderId);
                $items = $obj->getAllItems();
                $_is_owned = false;
                foreach($items as $item) {
                    Mage::log('item'.$item->getId());
                    if($item->getVendorId() == $vendor->getId()) {$_is_owned = true;break;}
                }
               // Mage::log($_is_owned);
                if($_is_owned === false) {
                    $this->_critical('Order not is owned by vendor.');
                }
            }
        }
    }
}
