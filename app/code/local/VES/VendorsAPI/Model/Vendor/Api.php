<?php
class VES_VendorsAPI_Model_Vendor_Api extends VES_VendorsAPI_Model_Api_Resource
{

    /**
     * Basic vendor data if get info(if attributes is null)
     * @var array
     */
    protected $_basicVendorData = array(
        'entity_id', 'vendor_id', 'group_id', 'email', 'store_id', 'entity_type_id',
        'credit', 'firstname', 'lastname', 'title', 'company', 'telephone', 'address', 'city'
    );

    protected $_filtersMap = array(
        'id' => 'entity_id',
       // 'set' => 'attribute_set_id',
      //  'type' => 'entity_type_id',
        'website' => 'website_id',
        'vendor' => 'vendor_id',
        'group' => 'group_id',
        'country' => 'country_id',
    );

    /************************************
     *
     *
     * Protected method
     *
     *
     *************************************/

    /**
     *  Set additional data before product saved
     *
     * @param    Mage_Catalog_Model_Product $product
     * @param    array $productData
     * @return   object
     */
    protected function _prepareDataForSave($product, $productData)
    {
        if (isset($productData['website_ids']) && is_array($productData['website_ids'])) {
            $product->setWebsiteIds($productData['website_ids']);
        }

        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            //Unset data if object attribute has no value in current store
            if (Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID !== (int)$product->getStoreId()
                && !$product->getExistsStoreValueFlag($attribute->getAttributeCode())
                && !$attribute->isScopeGlobal()
            ) {
                $product->setData($attribute->getAttributeCode(), false);
            }

            if ($this->_isAllowedAttribute(1,$attribute)) {
                if (isset($productData[$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData[$attribute->getAttributeCode()]
                    );
                } elseif (isset($productData['additional_attributes']['single_data'][$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData['additional_attributes']['single_data'][$attribute->getAttributeCode()]
                    );
                } elseif (isset($productData['additional_attributes']['multi_data'][$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData['additional_attributes']['multi_data'][$attribute->getAttributeCode()]
                    );
                }
            }
        }

        if (isset($productData['categories']) && is_array($productData['categories'])) {
            $product->setCategoryIds($productData['categories']);
        }

        if (isset($productData['websites']) && is_array($productData['websites'])) {
            foreach ($productData['websites'] as &$website) {
                if (is_string($website)) {
                    try {
                        $website = Mage::app()->getWebsite($website)->getId();
                    } catch (Exception $e) {
                    }
                }
            }
            $product->setWebsiteIds($productData['websites']);
        }

        if (Mage::app()->isSingleStoreMode()) {
            $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));
        }

        if (isset($productData['stock_data']) && is_array($productData['stock_data'])) {
            $product->setStockData($productData['stock_data']);
        }

        if (isset($productData['tier_price']) && is_array($productData['tier_price'])) {
            $tierPrices = Mage::getModel('catalog/product_attribute_tierprice_api')
                ->prepareTierPrices($product, $productData['tier_price']);
            $product->setData(Mage_Catalog_Model_Product_Attribute_Tierprice_Api::ATTRIBUTE_CODE, $tierPrices);
        }
    }


    /************************************
     *
     *
     * Public method
     *
     *
     *************************************/

    /**
     * Retrieve list of vendor info
     *
     * @param null|object|array $filters
     * @return array
     */
    public function items($filters = null)
    {
        $collection = Mage::getModel('vendors/vendor')->getCollection()
            ->addAttributeToSelect('*');

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_filtersMap);
        try {
            foreach ($filters as $field => $value) {
                $collection->addAttributeToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        $result = array();
        foreach ($collection as $vendor) {
            $data = $vendor->toArray();
            $row  = array();
            foreach ($this->_filtersMap as $attributeAlias => $attributeCode) {
                $row[$attributeAlias] = (isset($data[$attributeCode]) ? $data[$attributeCode] : null);
            }
            foreach ($this->getAllowedAttributes(2,$vendor) as $attributeCode => $attribute) {
                if (isset($data[$attributeCode])) {
                    $row[$attributeCode] = $data[$attributeCode];
                }
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Retrieve vendor info
     *
     * @param string $vendorApiKey
     * @param array @attributes
     * @return array
     */
    public function info($vendorApiKey, $attributes = null)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);

        if (!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        if (!is_null($attributes) && !is_array($attributes)) {
            $attributes = array($attributes);
        }

        $result = array();

        foreach ($this->_filtersMap as $attributeAlias=>$attributeCode) {
            $result[$attributeAlias] = $vendor->getData($attributeCode);
        }

        foreach ($this->getAllowedAttributes(2,$vendor, $attributes) as $attributeCode=>$attribute) {
            $result[$attributeCode] = $vendor->getData($attributeCode);
        }

        return $result;
    }

    /**
     * Create new product.
     *
     * @param string $vendorApiKey
     * @param string $type
     * @param int $set
     * @param string $sku
     * @param array $productData
     * @return int
     */
    public function createProduct($vendorApiKey, $type, $set, $sku, $productData)
    {
        if (!$type || !$set || !$sku) {
            $this->_fault('data_invalid');
        }

        $vendor = $this->_getVendorFromApiKey($vendorApiKey); //get vendor object

        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $this->_checkProductTypeExists($type);
        $this->_checkProductAttributeSet($set);

        /** @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($this->_getStoreId($vendor->getStoreId()))
            ->setAttributeSetId($set)
            ->setTypeId($type)
            ->setSku($sku);

        if (!isset($productData['stock_data']) || !is_array($productData['stock_data'])) {
            //Set default stock_data if not exist in product data
            $product->setStockData(array('use_config_manage_stock' => 0));
        }

        foreach ($product->getMediaAttributes() as $mediaAttribute) {
            $mediaAttrCode = $mediaAttribute->getAttributeCode();
            $product->setData($mediaAttrCode, 'no_selection');
        }

        /*
         *
         * set vendor id and approved
         *
         */
        if (!$product->getVendorId()) {
            $vendorId = $vendor->getId();
            $product->setData('vendor_id', $vendorId);
        }
        if (!$product->getId()) {
            if (Mage::helper('vendorsproduct')->isProductApproval()) {
                $product->setData('approval', VES_VendorsProduct_Model_Source_Approval::STATUS_NOT_SUBMITED);
            } else {
                $product->setData('approval', VES_VendorsProduct_Model_Source_Approval::STATUS_APPROVED);
            }
        }

        $this->_prepareDataForSave($product, $productData);

        try {
            /**
             * @todo implement full validation process with errors returning which are ignoring now
             * @todo see Mage_Catalog_Model_Product::validate()
             */
            if (is_array($errors = $product->validate())) {
                $strErrors = array();
                foreach ($errors as $code => $error) {
                    if ($error === true) {
                        $error = Mage::helper('catalog')->__('Attribute "%s" is invalid.', $code);
                    }
                    $strErrors[] = $error;
                }
                $this->_fault('data_invalid', implode("\n", $strErrors));
            }

            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $product->getId();
    }

    /**
     * Update product data
     *
     * @param string $vendorApiKey
     * @param int|string $productId
     * @param array $productData
     * @param string $identifierType
     * @return boolean
     */
    public function updateProduct($vendorApiKey, $productId, $productData, $identifierType = null)
    {
        $this->_isAccessChangeProduct($vendorApiKey, $productId, $identifierType);

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);

        Mage::log('store'.$this->_getStoreId($vendor->getStoreId()));
        $product = $this->_getProduct($productId, $this->_getStoreId($vendor->getStoreId()), $identifierType);

        $this->_prepareDataForSave($product, $productData);

        try {
            /**
             * @todo implement full validation process with errors returning which are ignoring now
             * @todo see Mage_Catalog_Model_Product::validate()
             */
            if (is_array($errors = $product->validate())) {
                $strErrors = array();
                foreach ($errors as $code => $error) {
                    if ($error === true) {
                        $error = Mage::helper('catalog')->__('Value for "%s" is invalid.', $code);
                    } else {
                        $error = Mage::helper('catalog')->__('Value for "%s" is invalid: %s', $code, $error);
                    }
                    $strErrors[] = $error;
                }
                $this->_fault('data_invalid', implode("\n", $strErrors));
            }

            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }

    /**
     * Delete product
     *
     * @param string $vendorApiKey
     * @param int|string $productId
     * @param string $identifierType
     * @return boolean
     */
    public function deleteProduct($vendorApiKey, $productId, $identifierType = null)
    {
        $this->_isAccessChangeProduct($vendorApiKey, $productId, $identifierType);

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        $product = $this->_getProduct($productId, $this->_getStoreId($vendor->getStoreId()), $identifierType);

        try {
            $product->delete();
            return true;
        } catch (Mage_Core_Exception $e) {
            $this->_fault('product_not_deleted', $e->getMessage());
            return false;
        }

        return false;
    }
}