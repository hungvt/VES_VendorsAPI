<?php
class VES_VendorsAPI_Model_Api2_Vendor_Rest_Admin_V1 extends VES_VendorsAPI_Model_Api2_Vendor_Rest
{
    /**
     * Retrieve information about vendor
     *
     * @throws Mage_Api2_Exception
     * @return array
     */
    protected function _retrieve()
    {
        return parent::_retrieve();
    }

    protected function _retrieveCollection() {
        return parent::_retrieveCollection();
    }

    /**
     * Vendor create only available for admin
     *
     * @param array $data
     */
    protected function _create(array $data)
    {
        $this->_critical(self::RESOURCE_NOT_FOUND);
    }

    /**
     * Vendor update only available for admin
     *
     * @param array $data
     */
    protected function _update(array $data)
    {
        $this->_critical(self::RESOURCE_NOT_FOUND);
    }

    /**
     * Vendor delete only available for admin
     */
    protected function _delete()
    {
        $this->_critical(self::RESOURCE_NOT_FOUND);
    }
}
