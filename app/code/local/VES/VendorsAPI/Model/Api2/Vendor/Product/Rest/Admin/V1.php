<?php
class VES_VendorsAPI_Model_Api2_Vendor_Product_Rest_Admin_V1 extends VES_VendorsAPI_Model_Api2_Vendor_Product_Rest
{
    /**
     * create vendor product.
     * The vendor user can create product only for themselves.
     * @param array $data
     * @return string
     */
    protected function _create(array $data)
    {
        return parent::_create($data);
    }

    /**
     * delete vendor  product
     * The vendor user can delete product only for themselves.
     */
    protected function _delete()
    {
        return parent::_delete();
    }

    /**
     * update vendor product
     * The vendor user can update product only for themselves.
     * @param array $data
     */
    protected function _update(array $data)
    {
        return parent::_update($data);
    }

    protected function _retrieve() {
        return parent::_retrieve();
    }

    protected function _retrieveCollection() {
        return parent::_retrieveCollection();
    }
}
