<?php

class VES_VendorsAPI_Model_Api_Resource extends Mage_Api_Model_Resource_Abstract
{
    const _TYPE_PRODUCT = 1;
    const _TYPE_VENDOR = 2;

    /**
     * Field name in session for saving store id
     * @var string
     */
    protected $_storeIdSessionField   = 'store_id';

    protected $_vendorApiAttribute = 'ves_api_key';
    /**
     * Default ignored attribute codes
     *
     * @var array
     */
    protected $_productIgnoredAttributeCodes = array('entity_id', 'attribute_set_id', 'entity_type_id');

    /**
     * Default ignored attribute types
     *
     * @var array
     */
    protected $_productIgnoredAttributeTypes = array();

    /**
     * Default ignored attribute codes vendor
     */
    protected $_vendorIgnoredAttributeCodes = array('entity_id', 'attribute_set_id', 'entity_type_id', 'logo'
        ,'password_hash','updated_at','ves_api_key','rp_token','rp_token_created_at','confirmation');

    /**
     * Default ignored attribute types
     *
     * @var array
     */
    protected $_vendorIgnoredAttributeTypes = array();

    /**
     * Decode encoded text
     * @param string $encoded
     * @param string $key
     * @return string
     */
    protected function _decode($encoded, $key)
    {
        $code = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($encoded), MCRYPT_MODE_CBC, md5(md5($key))), "\0");
        return $code;
    }

    /**
     * Encode text
     * @param string $code
     * @param string $key
     * @return string
     */
    protected function _encode($code, $key)
    {
        $code = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $code, MCRYPT_MODE_CBC, md5(md5($key))));
        return $code;
    }

    /**
     * get Vendor ID from API Key
     * @param string $key vendor api key
     * @return object|null
     */
    protected function _getVendorFromApiKey($key)
    {
        $key = trim($key);
        $collection = Mage::getModel('vendors/vendor')->getCollection()->addAttributeToSelect('*')->addAttributeToFilter($this->_vendorApiAttribute, $key);
        if ($collection->count()) {
            return $collection->getFirstItem();
        }
        $this->_fault('vendor_not_exists');
    }

    /**
     * check vendor update,delete product
     * @param string $vendorApiKey Vendor API Key
     * @param int $productId Product ID
     * @param  int|string $store
     * @param  string $identifierType
     * @return bool
     */
    protected function _isAccessChangeProduct($vendorApiKey, $productId, $identifierType = null)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        if ($vendor->getEntityId()) {
            $product = $this->_getProduct($productId, $this->_getStoreId($vendor->getStoreId()), $identifierType);
            if ($product->getId() != null) {
                if ($product->getVendorId() != $vendor->getVendorId()) $this->_fault('vendor_not_change');
            }
        }
    }

    /**
     * Return loaded product instance
     *
     * @param  int|string $productId (SKU or ID)
     * @param  int|string $store
     * @param  string $identifierType
     * @return Mage_Catalog_Model_Product
     */
    protected function _getProduct($productId, $store = null, $identifierType = null)
    {

        $product = Mage::helper('catalog/product')->getProduct($productId, $this->_getStoreId($store), $identifierType);
        if (is_null($product->getId())) {
            $this->_fault('product_not_exists');
        }
        return $product;
    }

    /**
     * Check if product type exists
     *
     * @param  $productType
     * @throw Mage_Api_Exception
     * @return void
     */
    protected function _checkProductTypeExists($productType)
    {
        if (!in_array($productType, array_keys(Mage::getModel('catalog/product_type')->getOptionArray()))) {
            $this->_fault('product_type_not_exists');
        }
    }

    /**
     * Check if attributeSet is exits and in catalog_product entity group type
     *
     * @param  $attributeSetId
     * @throw Mage_Api_Exception
     * @return void
     */
    protected function _checkProductAttributeSet($attributeSetId)
    {
        $attributeSet = Mage::getModel('eav/entity_attribute_set')->load($attributeSetId);
        if (is_null($attributeSet->getId())) {
            $this->_fault('product_attribute_set_not_exists');
        }
        if (Mage::getModel('catalog/product')->getResource()->getTypeId() != $attributeSet->getEntityTypeId()) {
            $this->_fault('product_attribute_set_not_valid');
        }
    }

    /**
     * Check is attribute allowed
     *
     * @param string $type
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param array $attributes
     * @return boolean
     */
    protected function _isAllowedAttribute($type,$attribute, $attributes = null)
    {
        if($type == self::_TYPE_PRODUCT) {
            if (Mage::getSingleton('api/server')->getApiName() == 'rest') {
                if (!$this->_checkAttributeAcl($attribute)) {
                    return false;
                }
            }
        }

        if (is_array($attributes)
            && !(in_array($attribute->getAttributeCode(), $attributes)
                || in_array($attribute->getAttributeId(), $attributes))
        ) {
            return false;
        }

        if($type == self::_TYPE_PRODUCT) {
            return !in_array($attribute->getFrontendInput(), $this->_productIgnoredAttributeTypes)
            && !in_array($attribute->getAttributeCode(), $this->_productIgnoredAttributeCodes);
        } else if($type == self::_TYPE_VENDOR) {
            return !in_array($attribute->getFrontendInput(), $this->_vendorIgnoredAttributeTypes)
            && !in_array($attribute->getAttributeCode(), $this->_vendorIgnoredAttributeCodes);
        }

    }

    /**
     * Return list of allowed attributes
     *
     * @param string $type
     * @param Mage_Eav_Model_Entity_Abstract $entity
     * @param array $filter
     * @return array
     */
    public function getAllowedAttributes($type,$entity, array $filter = null)
    {
        $attributes = $entity->getResource()
            ->loadAllAttributes($entity)
            ->getAttributesByCode();
        $result = array();
        foreach ($attributes as $attribute) {
            if ($this->_isAllowedAttribute($type, $attribute, $filter)) {
                $result[$attribute->getAttributeCode()] = $attribute;
            }
        }

        return $result;
    }

    /**
     * Retrives store id from store code, if no store id specified,
     * it use seted session or admin store
     *
     * @param string $vendorApiKey
     * @param string|int $store
     * @return int
     */
    protected function _getStoreId($store = null)
    {
        if (is_null($store)) {
            $store = ($this->_getSession()->hasData($this->_storeIdSessionField)
                ? $this->_getSession()->getData($this->_storeIdSessionField) : 0);
        }

        try {
            $storeId = Mage::app()->getStore($store)->getId();
        } catch (Mage_Core_Model_Store_Exception $e) {
            $this->_fault('store_not_exists');
        }

        return $storeId;
    }


}
