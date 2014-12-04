<?php
class VES_VendorsAPI_Model_Api2_Vendor extends Mage_Api2_Model_Resource
{
    /**
     * get Vendor ID from API Key
     * @param string $key vendor api key
     * @return VES_Vendors_Model_Vendor
     */
    protected function _getVendorByApiKey($key)
    {
        $key = trim($key);
        $collection = Mage::getModel('vendors/vendor')->getCollection()->addAttributeToSelect('*')->addAttributeToFilter('ves_api_key',$key);
        if($collection->count()){
            $vendor = $collection->getFirstItem();
            return $vendor;
        }
        $this->_critical(self::RESOURCE_NOT_FOUND);

    }

    /**
     * get Vendor ID By ID
     * @param string $id vendor ID
     * @return VES_Vendors_Model_Vendor
     */
    protected function _getVendorById($id)
    {
        $vendor = Mage::getModel('vendors/vendor')->load($id);
        if ($vendor->getId()) {
            return $vendor;
        }
        $this->_critical(self::RESOURCE_NOT_FOUND);
    }

    /**
     * Retrieve current store according to request and API user type
     * @return Mage_Core_Model_Store
     */
    protected function _getStore()
    {
        $vendorApiKey = $this->getRequest()->getParam('api');
        $vendor = $this->_getVendorByApiKey($vendorApiKey);
        $store = $vendor->getStoreId();

        try {
            if ($this->getUserType() != Mage_Api2_Model_Auth_User_Admin::USER_TYPE) {
                // customer or guest role
                if (!$store) {
                    $store = Mage::app()->getDefaultStoreView();
                } else {
                    $store = Mage::app()->getStore($store);
                }
            } else {
                // admin role
                if (is_null($store)) {
                    $store = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
                }
                $store = Mage::app()->getStore($store);
            }
        } catch (Mage_Core_Model_Store_Exception $e) {
            // store does not exist
            $this->_critical('Requested store is invalid', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        return $store;
    }
}