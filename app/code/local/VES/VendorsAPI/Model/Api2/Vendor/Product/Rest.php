<?php

/**
 * Abstract API2 class for product instance
 *
 * @module: Vendors API
 * @author: vnecoms
 */

abstract class VES_VendorsAPI_Model_Api2_Vendor_Product_Rest extends VES_VendorsAPI_Model_Api2_Vendor_Product
{
    /**
     * The greatest decimal value which could be stored. Corresponds to DECIMAL (12,4) SQL type
     */
    const MAX_DECIMAL_VALUE = 99999999.9999;


    /**
     * Current loaded product
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_product;

    /**
     * Retrieve product data
     *
     * @return array
     */
    protected function _retrieve()
    {
        $this->_critical('Method not support.');
    }

    /**
     * Retrieve list of products
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $this->_critical('Method not support.');
    }

    /**
     * Load product by its SKU or ID provided in request
     *
     * @return Mage_Catalog_Model_Product
     */
    protected function _getProduct()
    {
        if (is_null($this->_product)) {
            $vendorApiKey = $this->getRequest()->getParam('api');

            $vendor = $this->_getVendorByApiKey($vendorApiKey);
            if(!($vendor->getId())) {
                $this->_critical(self::RESOURCE_NOT_FOUND);
            }
            $productId = $this->getRequest()->getParam('id');
            /** @var $productHelper Mage_Catalog_Helper_Product */
            $productHelper = Mage::helper('catalog/product');
            $product = $productHelper->getProduct($productId, $this->_getStore()->getId());
            if (!($product->getId())) {
                $this->_critical(self::RESOURCE_NOT_FOUND);
            }

            //check owner vendor product
            if($product->getVendorId() != $vendor->getId()) {
                $this->_critical('Product not allowed to change.');
            }
            // check if product belongs to website current
            if ($this->_getStore()->getId()) {
                $isValidWebsite = in_array($this->_getStore()->getWebsiteId(), $product->getWebsiteIds());
                if (!$isValidWebsite) {
                    $this->_critical(self::RESOURCE_NOT_FOUND);
                }
            }
            // Check display settings for customers & guests
            if ($this->getApiUser()->getType() != Mage_Api2_Model_Auth_User_Admin::USER_TYPE) {
                // check if product assigned to any website and can be shown
                if ((!Mage::app()->isSingleStoreMode() && !count($product->getWebsiteIds()))
                    || !$productHelper->canShow($product)
                ) {
                    $this->_critical(self::RESOURCE_NOT_FOUND);
                }
            }
            $this->_product = $product;
        }
        return $this->_product;
    }

    /**
     * Set product
     *
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _setProduct(Mage_Catalog_Model_Product $product)
    {
        $this->_product = $product;
    }

    /**
     * Load category by id
     *
     * @param int $categoryId
     * @return Mage_Catalog_Model_Category
     */
    protected function _getCategoryById($categoryId)
    {
        return Mage::getModel('catalog/category')->load($categoryId);
    }

    /**
     * Get product price with all tax settings processing
     *
     * @param float $price inputed product price
     * @param bool $includingTax return price include tax flag
     * @param null|Mage_Customer_Model_Address $shippingAddress
     * @param null|Mage_Customer_Model_Address $billingAddress
     * @param null|int $ctc customer tax class
     * @param bool $priceIncludesTax flag that price parameter contain tax
     * @return float
     * @see Mage_Tax_Helper_Data::getPrice()
     */
    protected function _getPrice($price, $includingTax = null, $shippingAddress = null,
                                 $billingAddress = null, $ctc = null, $priceIncludesTax = null
    ) {
        $product = $this->_getProduct();
        $store = $this->_getStore();

        if (is_null($priceIncludesTax)) {
            /** @var $config Mage_Tax_Model_Config */
            $config = Mage::getSingleton('tax/config');
            $priceIncludesTax = $config->priceIncludesTax($store) || $config->getNeedUseShippingExcludeTax();
        }

        $percent = $product->getTaxPercent();
        $includingPercent = null;

        $taxClassId = $product->getTaxClassId();
        if (is_null($percent)) {
            if ($taxClassId) {
                $request = Mage::getSingleton('tax/calculation')
                    ->getRateRequest($shippingAddress, $billingAddress, $ctc, $store);
                $percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxClassId));
            }
        }
        if ($taxClassId && $priceIncludesTax) {
            $taxHelper = Mage::helper('tax');
            if ($taxHelper->isCrossBorderTradeEnabled($store)) {
                $includingPercent = $percent;
            } else {
                $request = Mage::getSingleton('tax/calculation')->getDefaultRateRequest($store);
                $includingPercent = Mage::getSingleton('tax/calculation')
                    ->getRate($request->setProductClassId($taxClassId));
            }
        }

        if ($percent === false || is_null($percent)) {
            if ($priceIncludesTax && !$includingPercent) {
                return $price;
            }
        }
        $product->setTaxPercent($percent);

        if (!is_null($includingTax)) {
            if ($priceIncludesTax) {
                if ($includingTax) {
                    /**
                     * Recalculate price include tax in case of different rates
                     */
                    if ($includingPercent != $percent) {
                        $price = $this->_calculatePrice($price, $includingPercent, false);
                        /**
                         * Using regular rounding. Ex:
                         * price incl tax   = 52.76
                         * store tax rate   = 19.6%
                         * customer tax rate= 19%
                         *
                         * price excl tax = 52.76 / 1.196 = 44.11371237 ~ 44.11
                         * tax = 44.11371237 * 0.19 = 8.381605351 ~ 8.38
                         * price incl tax = 52.49531773 ~ 52.50 != 52.49
                         *
                         * that why we need round prices excluding tax before applying tax
                         * this calculation is used for showing prices on catalog pages
                         */
                        if ($percent != 0) {
                            $price = Mage::getSingleton('tax/calculation')->round($price);
                            $price = $this->_calculatePrice($price, $percent, true);
                        }
                    }
                } else {
                    $price = $this->_calculatePrice($price, $includingPercent, false);
                }
            } else {
                if ($includingTax) {
                    $price = $this->_calculatePrice($price, $percent, true);
                }
            }
        } else {
            if ($priceIncludesTax) {
                if ($includingTax) {
                    $price = $this->_calculatePrice($price, $includingPercent, false);
                    $price = $this->_calculatePrice($price, $percent, true);
                } else {
                    $price = $this->_calculatePrice($price, $includingPercent, false);
                }
            } else {
                if ($includingTax) {
                    $price = $this->_calculatePrice($price, $percent, true);
                }
            }
        }

        return $store->roundPrice($price);
    }

    /**
     * Calculate price imcluding/excluding tax base on tax rate percent
     *
     * @param float $price
     * @param float $percent
     * @param bool $includeTax true - for calculate price including tax and false if price excluding tax
     * @return float
     */
    protected function _calculatePrice($price, $percent, $includeTax)
    {
        /** @var $calculator Mage_Tax_Model_Calculation */
        $calculator = Mage::getSingleton('tax/calculation');
        $taxAmount = $calculator->calcTaxAmount($price, $percent, !$includeTax, false);

        return $includeTax ? $price + $taxAmount : $price - $taxAmount;
    }

    /**
     * Retrive tier prices in special format
     *
     * @return array
     */
    protected function _getTierPrices()
    {
        $tierPrices = array();
        foreach ($this->_getProduct()->getTierPrice() as $tierPrice) {
            $tierPrices[] = array(
                'qty' => $tierPrice['price_qty'],
                'price_with_tax' => $this->_applyTaxToPrice($tierPrice['price']),
                'price_without_tax' => $this->_applyTaxToPrice($tierPrice['price'], false)
            );
        }
        return $tierPrices;
    }

    /**
     * Default implementation. May be different for customer/guest/admin role.
     *
     * @return null
     */
    protected function _getCustomerGroupId()
    {
        return null;
    }

    /**
     * Default implementation. May be different for customer/guest/admin role.
     *
     * @param float $price
     * @param bool $withTax
     * @return float
     */
    protected function _applyTaxToPrice($price, $withTax = true)
    {
        return $price;
    }

    /**
     * Delete product by its ID
     *
     * @throws Mage_Api2_Exception
     */
    protected function _delete()
    {
        $product = $this->_getProduct();
        try {
            $product->delete();
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }
    }

    /**
     * Create vendor product
     *
     * @param array $data
     * @return string
     */
    protected function _create(array $data)
    {
        $data = $this->_prepareData($data);
        Mage::log($data);

        /* @var $validator Mage_Catalog_Model_Api2_Product_Validator_Product */
        $validator = Mage::getModel('catalog/api2_product_validator_product', array(
            'operation' => self::OPERATION_CREATE
        ));

        if (!$validator->isValidData($data)) {
            foreach ($validator->getErrors() as $error) {
                $this->_error($error, Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }
            $this->_critical(self::RESOURCE_DATA_PRE_VALIDATION_ERROR);
        }

        $type = $data['type_id'];
        if ($type !== 'simple') {
            $this->_critical("Creation of products with type '$type' is not implemented",
                Mage_Api2_Model_Server::HTTP_METHOD_NOT_ALLOWED);
        }
        $set = $data['attribute_set_id'];
        $vendor_sku = $data['vendor_sku'];

        /** @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product')
            ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->setAttributeSetId($set)
            ->setTypeId($type)
            ->setVendorSku($vendor_sku);

        //set vendor id from api key
        $apiKey = $this->getRequest()->getParam('api');
        $vendor = $this->_getVendorByApiKey($apiKey);
        if(!($vendor->getId())) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

        //set vendor approval product
        if(!$product->getId()){
            if(Mage::helper('vendorsproduct')->isProductApproval()){
                $product->setData('approval',VES_VendorsProduct_Model_Source_Approval::STATUS_NOT_SUBMITED);
            }else{
                $product->setData('approval',VES_VendorsProduct_Model_Source_Approval::STATUS_APPROVED);
            }
        }

        $product->setData('vendor_id', $vendor->getId());

        if($vendor->getVendorId()) {
            $product->setSku($data['sku']);
        }

        foreach ($product->getMediaAttributes() as $mediaAttribute) {
            $mediaAttrCode = $mediaAttribute->getAttributeCode();
            $product->setData($mediaAttrCode, 'no_selection');
        }

        $this->_prepareDataForSave($product, $data);
        try {
            $product->validate();
            $product->save();
            $this->_multicall($product->getId());
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $this->_critical(sprintf('Invalid attribute "%s": %s', $e->getAttributeCode(), $e->getMessage()),
                Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_UNKNOWN_ERROR);
        }

        return $this->_getLocation($product);
    }

    /**
     * Update product by its ID
     *
     * @param array $data
     * @return string
     */
    protected function _update(array $data)
    {
        $data = $this->_prepareData($data);

        /** @var $product Mage_Catalog_Model_Product */
        $product = $this->_getProduct();
        /* @var $validator Mage_Catalog_Model_Api2_Product_Validator_Product */
        $validator = Mage::getModel('catalog/api2_product_validator_product', array(
            'operation' => self::OPERATION_UPDATE,
            'product'   => $product
        ));

        if (!$validator->isValidData($data)) {
            foreach ($validator->getErrors() as $error) {
                $this->_error($error, Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }
            $this->_critical(self::RESOURCE_DATA_PRE_VALIDATION_ERROR);
        }
        if (isset($data['sku'])) {
            $product->setSku($data['sku'])
                    ->setVendorSku($data['vendor_sku'])
                ;
        }
        // attribute set and product type cannot be updated
        unset($data['attribute_set_id']);
        unset($data['type_id']);
        $this->_prepareDataForSave($product, $data);
        try {
            $product->validate();
            Mage::app()->setCurrentStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
            $product->save();
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $this->_critical(sprintf('Invalid attribute "%s": %s', $e->getAttributeCode(), $e->getMessage()),
                Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_UNKNOWN_ERROR);
        }
        return $this->_getLocation($product);
        //return $product->getId();
    }

    /**
     * Determine if stock management is enabled
     *
     * @param array $stockData
     * @return bool
     */
    protected function _isManageStockEnabled($stockData)
    {
        if (!(isset($stockData['use_config_manage_stock']) && $stockData['use_config_manage_stock'])) {
            $manageStock = isset($stockData['manage_stock']) && $stockData['manage_stock'];
        } else {
            $manageStock = Mage::getStoreConfig(
                Mage_CatalogInventory_Model_Stock_Item::XML_PATH_ITEM . 'manage_stock');
        }
        return (bool) $manageStock;
    }

    /**
     * Check if value from config is used
     *
     * @param array $data
     * @param string $field
     * @return bool
     */
    protected function _isConfigValueUsed($data, $field)
    {
        return isset($data["use_config_$field"]) && $data["use_config_$field"];
    }

    /**
     * Set additional data before product save
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $productData
     */
    protected function _prepareDataForSave($product, $productData)
    {
        if (isset($productData['stock_data'])) {
            if (!$product->isObjectNew() && !isset($productData['stock_data']['manage_stock'])) {
                $productData['stock_data']['manage_stock'] = $product->getStockItem()->getManageStock();
            }
            $this->_filterStockData($productData['stock_data']);
        } else {
            $productData['stock_data'] = array(
                'use_config_manage_stock' => 1,
                'use_config_min_sale_qty' => 1,
                'use_config_max_sale_qty' => 1,
            );
        }
        $product->setStockData($productData['stock_data']);
        // save gift options
        $this->_filterConfigValueUsed($productData, array('gift_message_available', 'gift_wrapping_available'));
        if (isset($productData['use_config_gift_message_available'])) {
            $product->setData('use_config_gift_message_available', $productData['use_config_gift_message_available']);
            if (!$productData['use_config_gift_message_available']
                && ($product->getData('gift_message_available') === null)) {
                $product->setData('gift_message_available', (int) Mage::getStoreConfig(
                    Mage_GiftMessage_Helper_Message::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS, $product->getStoreId()));
            }
        }
        if (isset($productData['use_config_gift_wrapping_available'])) {
            $product->setData('use_config_gift_wrapping_available', $productData['use_config_gift_wrapping_available']);
            if (!$productData['use_config_gift_wrapping_available']
                && ($product->getData('gift_wrapping_available') === null)
            ) {
                $xmlPathGiftWrappingAvailable = 'sales/gift_options/wrapping_allow_items';
                $product->setData('gift_wrapping_available', (int)Mage::getStoreConfig(
                    $xmlPathGiftWrappingAvailable, $product->getStoreId()));
            }
        }

        if (isset($productData['website_ids']) && is_array($productData['website_ids'])) {
            $product->setWebsiteIds($productData['website_ids']);
        } else {
            $product->setWebsiteIds(array($this->_getStore()->getWebsiteId()));
        }

        if (isset($productData['store_id'])) {
            $product->setStoreId($productData['store_id']);
        } else {
            $product->setStoreId(0);
        }

        // Create Permanent Redirect for old URL key
        if (!$product->isObjectNew()  && isset($productData['url_key'])
            && isset($productData['url_key_create_redirect'])
        ) {
            $product->setData('save_rewrites_history', (bool)$productData['url_key_create_redirect']);
        }
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        foreach ($product->getTypeInstance(true)->getEditableAttributes($product) as $attribute) {
            //Unset data if object attribute has no value in current store
            if (Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID !== (int)$product->getStoreId()
                && !$product->getExistsStoreValueFlag($attribute->getAttributeCode())
                && !$attribute->isScopeGlobal()
            ) {
                $product->setData($attribute->getAttributeCode(), false);
            }

            if ($this->_isAllowedAttribute($attribute)) {
                if (isset($productData[$attribute->getAttributeCode()])) {
                    $product->setData(
                        $attribute->getAttributeCode(),
                        $productData[$attribute->getAttributeCode()]
                    );
                }
            }
        }
    }

    /**
     * Filter stock data values
     *
     * @param array $stockData
     */
    protected function _filterStockData(&$stockData)
    {
        $fieldsWithPossibleDefautlValuesInConfig = array('manage_stock', 'min_sale_qty', 'max_sale_qty', 'backorders',
            'qty_increments', 'notify_stock_qty', 'min_qty', 'enable_qty_increments');
        $this->_filterConfigValueUsed($stockData, $fieldsWithPossibleDefautlValuesInConfig);

        if ($this->_isManageStockEnabled($stockData)) {
            if (isset($stockData['qty']) && (float)$stockData['qty'] > self::MAX_DECIMAL_VALUE) {
                $stockData['qty'] = self::MAX_DECIMAL_VALUE;
            }
            if (isset($stockData['min_qty']) && (int)$stockData['min_qty'] < 0) {
                $stockData['min_qty'] = 0;
            }
            if (!isset($stockData['is_decimal_divided']) || $stockData['is_qty_decimal'] == 0) {
                $stockData['is_decimal_divided'] = 0;
            }
        } else {
            $nonManageStockFields = array('manage_stock', 'use_config_manage_stock', 'min_sale_qty',
                'use_config_min_sale_qty', 'max_sale_qty', 'use_config_max_sale_qty');
            foreach ($stockData as $field => $value) {
                if (!in_array($field, $nonManageStockFields)) {
                    unset($stockData[$field]);
                }
            }
        }
    }

    /**
     * Filter out fields if Use Config Settings option used
     *
     * @param array $data
     * @param string $fields
     */
    protected function _filterConfigValueUsed(&$data, $fields) {
        foreach($fields as $field) {
            if ($this->_isConfigValueUsed($data, $field)) {
                unset($data[$field]);
            }
        }
    }

    /**
     * Check if attribute is allowed
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param array $attributes
     * @return boolean
     */
    protected function _isAllowedAttribute($attribute, $attributes = null)
    {
        $isAllowed = true;
        if (is_array($attributes)
            && !(in_array($attribute->getAttributeCode(), $attributes)
                || in_array($attribute->getAttributeId(), $attributes))
        ) {
            $isAllowed = false;
        }
        return $isAllowed;
    }

    /**
     * Remove specified keys from associative or indexed array
     *
     * @param array $array
     * @param array $keys
     * @param bool $dropOrigKeys if true - return array as indexed array
     * @return array
     */
    protected function _filterOutArrayKeys(array $array, array $keys, $dropOrigKeys = false)
    {
        $isIndexedArray = is_array(reset($array));
        if ($isIndexedArray) {
            foreach ($array as &$value) {
                if (is_array($value)) {
                    $value = array_diff_key($value, array_flip($keys));
                }
            }
            if ($dropOrigKeys) {
                $array = array_values($array);
            }
            unset($value);
        } else {
            $array = array_diff_key($array, array_flip($keys));
        }

        return $array;
    }

    protected function _prepareData($data) {
        $apiKey = $this->getRequest()->getParam('api');
        $vendor = $this->_getVendorByApiKey($apiKey);
        if(!$vendor->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

        if($data['sku']) {
            $data['vendor_sku'] = $data['sku'];
            $data['sku'] = $vendor->getVendorId().'_'.$data['sku'];
        }


        return $data;
    }

}
