<?php

class VES_VendorsAPI_Model_Api2_Vendor_Order_Rest_Customer_V1 extends VES_VendorsAPI_Model_Api2_Vendor_Order_Rest
{

    /**
     * Prepare and return order comments collection
     *
     * @param array $orderIds Orders' identifiers
     * @return Mage_Sales_Model_Resource_Order_Status_History_Collection|Object
     */
/*    protected function _getCommentsCollection(array $orderIds)
    {
        return parent::_getCommentsCollection($orderIds)->addFieldToFilter('is_visible_on_front', 1);
    }*/
}
