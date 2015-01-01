<?php

abstract class VES_VendorsAPI_Model_Api2_Vendor_Rest extends VES_VendorsAPI_Model_Api2_Vendor
{
    protected $_attributes_not_allowed_collection = array(
        'ves_api_key','credit'
    );
    /**
     *
     * Vendor create not support
     * @param array $data
     * @return not return
     */
    protected function _create(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Vendor update not support
     *
     * @param array $data
     */
    protected function _update(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Vendor delete not support
     */
    protected function _delete()
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Retrieve collection instances
     *
     * @return VES_Vendors_Model_Resource_Vendor_Collection
     */
    protected function _getCollectionForRetrieve()
    {
        $attributes_available = array_keys(
            $this->getAvailableAttributes($this->getUserType(), Mage_Api2_Model_Resource::OPERATION_ATTRIBUTE_READ)
        );
        foreach($this->_attributes_not_allowed_collection as $att) {
            //Mage::log('...'.in_array('credit1',$attributes_available));
            if(in_array($att,$attributes_available)) {
                $key = array_search($att,$attributes_available);
                unset($attributes_available[$key]);
            }
        }
        Mage::log($attributes_available);
        /** @var $collection Mage_Customer_Model_Resource_Customer_Collection */
        $collection = Mage::getResourceModel('vendors/vendor_collection');
        $collection->addAttributeToSelect($attributes_available);

        $this->_applyCollectionModifiers($collection);
        return $collection;
    }

    /**
     * Get vendors list
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $data = $this->_getCollectionForRetrieve()->load()->toArray();
        return isset($data['items']) ? $data['items'] : $data;
    }

    /**
     * Retrieve information about vendor
     *
     * @throws Mage_Api2_Exception
     * @return array
     */
    protected function _retrieve()
    {
        /** @var $customer VES_Vendors_Model_Vendor */
        $vendor = $this->_getVendorByApiKey($this->getRequest()->getParam('api'));
        if(!$vendor->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $vendor->getData();
    }
}
