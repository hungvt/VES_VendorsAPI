<?php

abstract class VES_VendorsAPI_Model_Api2_Vendor_Order_Comment_Rest extends VES_VendorsAPI_Model_Api2_Vendor_Order_Comment
{
    /**#@+
     * Parameters in request used in model (usually specified in route mask)
     */
    const PARAM_ORDER_ID = 'id';
    /**#@-*/

    /**
     * Get sales order comments
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $collection = $this->_getCollectionForRetrieve();
        $collection->addFieldToSelect($this->getForcedAttributes());

        $this->_applyCollectionModifiers($collection);

        $data = $collection->load()->toArray();
        return isset($data['items']) ? $data['items'] : $data;
    }

    /**
     * Retrieve collection instances
     *
     * @return Mage_Sales_Model_Resource_Order_Status_History_Collection
     */
    protected function _getCollectionForRetrieve()
    {
        $vendor = $this->_getVendorByApiKey($this->getVendorApiKey());
        if(!$vendor->getId()) {
            $this->_critical('Vendor not exists');
        }

        /* @var $collection Mage_Sales_Model_Resource_Order_Status_History_Collection */
        $collection = Mage::getResourceModel('sales/order_status_history_collection');
        $collection->setOrderFilter($this->_loadOrderById($this->getRequest()->getParam(self::PARAM_ORDER_ID)));

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
        if (!$order->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

        if($order->getVendorId() != $vendor->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $order;
    }
}
