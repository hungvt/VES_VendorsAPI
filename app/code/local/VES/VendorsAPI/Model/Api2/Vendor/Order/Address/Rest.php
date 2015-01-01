<?php

abstract class VES_VendorsAPI_Model_Api2_Vendor_Order_Address_Rest extends VES_VendorsAPI_Model_Api2_Vendor_Order_Address
{
    /**#@+
     * Parameters in request used in model (usually specified in route mask)
     */
    const PARAM_ORDER_ID     = 'order_id';
    const PARAM_ADDRESS_TYPE = 'address_type';
    /**#@-*/

    /**
     * Retrieve order address
     *
     * @return array
     */
    protected function _retrieve()
    {
        /** @var $address Mage_Sales_Model_Order_Address */
        $address = $this->_getCollectionForRetrieve()
            ->addAttributeToFilter('address_type', $this->getRequest()->getParam(self::PARAM_ADDRESS_TYPE))
            ->getFirstItem();
        if (!$address->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $address->getData();
    }

    /**
     * Retrieve order addresses
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $collection = $this->_getCollectionForRetrieve();

        $this->_applyCollectionModifiers($collection);
        $data = $collection->load()->toArray();

        if (0 == count($data['items'])) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

        return $data['items'];
    }

    /**
     * Retrieve collection instances
     *
     * @return Mage_Sales_Model_Resource_Order_Address_Collection
     */
    protected function _getCollectionForRetrieve()
    {
        $order = $this->_loadOrderById($this->getRequest()->getParam(self::PARAM_ORDER_ID));

        /* @var $collection Mage_Sales_Model_Resource_Order_Address_Collection */
        $collection = Mage::getResourceModel('sales/order_address_collection');
        $collection->addAttributeToFilter('parent_id', $order->getId());

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
