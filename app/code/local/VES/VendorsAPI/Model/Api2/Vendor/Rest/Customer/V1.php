<?php
class VES_VendorsAPI_Model_Api2_Vendor_Rest_Customer_V1 extends VES_VendorsAPI_Model_Api2_Vendor_Rest
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

    /**
     * Get vendors list
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        return parent::_retrieveCollection();
    }

    /**
     * Vendor create only available for admin
     *
     * @param array $data
     */
    protected function _create(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Vendor update only available for admin
     *
     * @param array $data
     */
    protected function _update(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Vendor delete only available for admin
     */
    protected function _delete()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

}
