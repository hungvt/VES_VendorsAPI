<?php

class VES_VendorsAPI_Model_Api2_Vendor_Product_Category_Rest_Customer_V1 extends VES_VendorsAPI_Model_Api2_Vendor_Product_Category_Rest
{
    /**
     * Product category assign
     *
     * @param array $data
     * @return string
     */
    protected function _create(array $data)
    {
        return parent::_create($data);
    }

    /**
     * Product category unassign
     *
     * @return bool
     */
    protected function _delete()
    {
        return parent::_delete();
    }

    /**
     * Return all assigned categories
     *
     * @return array
     */
    protected function _getCategoryIds()
    {
        return $this->_getProduct()->getCategoryIds();
    }
}
