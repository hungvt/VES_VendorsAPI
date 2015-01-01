<?php

class VES_VendorsAPI_Model_Api2_Vendor_Order_Address_Rest_Customer_V1 extends VES_VendorsAPI_Model_Api2_Vendor_Order_Address_Rest
{
    /**
     * Retrieve collection instances
     *
     * @return Mage_Sales_Model_Resource_Order_Address_Collection
     */
/*    protected function _getCollectionForRetrieve()
    {
        $collection = parent::_getCollectionForRetrieve();
        $collection->addAttributeToFilter('customer_id', $this->getApiUser()->getUserId());

        return $collection;
    }*/
}
