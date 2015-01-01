<?php

abstract class VES_VendorsAPI_Model_Api2_Vendor_Order_Item_Rest extends VES_VendorsAPI_Model_Api2_Vendor_Order_Item
{
    /**#@+
     * Parameters in request used in model (usually specified in route)
     */
    const PARAM_ORDER_ID = 'id';
    /**#@-*/

    /**
     * Get order items list
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $data = array();
        /* @var $item Mage_Sales_Model_Order_Item */
        foreach ($this->_getCollectionForRetrieve() as $item) {
            $itemData = $item->getData();
            $itemData['status'] = $item->getStatus();
            $data[] = $itemData;
        }
        return $data;
    }

    /**
     * Retrieve order items collection
     *
     * @return Mage_Sales_Model_Resource_Order_Item_Collection
     */
    protected function _getCollectionForRetrieve()
    {
        $vendor = $this->_getVendorByApiKey($this->getVendorApiKey());
        if(!$vendor->getId()) {
            $this->_critical('Vendor not exists');
        }

        /* @var $order Mage_Sales_Model_Order */
        $order = $this->_loadOrderById(
            $this->getRequest()->getParam(self::PARAM_ORDER_ID)
        );

        /* @var $collection Mage_Sales_Model_Resource_Order_Item_Collection */
        $collection = Mage::getResourceModel('sales/order_item_collection');
        $collection->setOrderFilter($order->getId());
        $collection->addAttributeToFilter('vendor_id',$vendor->getId());
        $this->_applyCollectionModifiers($collection);
        return $collection;
    }

    /**
     * Load order by id
     *
     * @param int $id
     * @throws Mage_Api2_Exception
     * @return Mage_Sales_Model_Order
     */
    protected function _loadOrderById($id)
    {
        $vendor = $this->_getVendorByApiKey($this->getVendorApiKey());
        if(!$vendor->getId()) {
            $this->_critical('Vendor not exists');
        }

        /* @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->load($id);
        Mage::log($order->getData());
        if (!$order->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

        //check owner order
        $this->_isOrderOwnerAdvanced($vendor,$id,'order');
        return $order;
    }
}
