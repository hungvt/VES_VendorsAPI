<?php


class VES_VendorsAPI_Model_Api2_Vendor_Product_Validator extends Mage_Catalog_Model_Api2_Product_Validator_Product
{
    /**
     * Validate product data
     *
     * @param array $data
     * @return bool
     */
    public function isValidData(array $data)
    {
        if ($this->_isUpdate()) {
            $product = $this->_getProduct();
            if (!is_null($product) && $product->getId()) {
                $data['attribute_set_id'] = $product->getAttributeSetId();
                $data['type_id'] = $product->getTypeId();
            }
        }

        try {
            $this->_validateProductType($data);
            /** @var $productEntity Mage_Eav_Model_Entity_Type */
            $productEntity = Mage::getModel('eav/entity_type')->loadByCode(Mage_Catalog_Model_Product::ENTITY);
            $this->_validateAttributeSet($data, $productEntity);
            $this->_validateSku($data);
            $this->_validateGiftOptions($data);
            $this->_validateGroupPrice($data);
            $this->_validateTierPrice($data);
            $this->_validateStockData($data);
            $this->_validateAttributes($data, $productEntity);
            $isSatisfied = count($this->getErrors()) == 0;
        } catch (Mage_Api2_Exception $e) {
            $this->_addError($e->getMessage());
            $isSatisfied = false;
        }


        return $isSatisfied;
    }

    /**
     * Collect required EAV attributes, validate applicable attributes and validate source attributes values
     *
     * @param array $data
     * @param Mage_Eav_Model_Entity_Type $productEntity
     * @return array
     */
    protected function _validateAttributes($data, $productEntity)
    {
        if (!isset($data['attribute_set_id']) || empty($data['attribute_set_id'])) {
            $this->_critical('Missing "attribute_set_id" in request.', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        if (!isset($data['type_id']) || empty($data['type_id'])) {
            $this->_critical('Missing "type_id" in request.', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        // Validate weight
        if (isset($data['weight']) && !empty($data['weight']) && $data['weight'] > 0
            && !Zend_Validate::is($data['weight'], 'Between', array(0, self::MAX_DECIMAL_VALUE))
        ) {
            $this->_addError('The "weight" value is not within the specified range.');
        }
        // msrp_display_actual_price_type attribute values needs to be a string to pass validation
        // see Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type_Price::getAllOptions()
        if (isset($data['msrp_display_actual_price_type'])) {
            $data['msrp_display_actual_price_type'] = (string)$data['msrp_display_actual_price_type'];
        }
        $requiredAttributes = array('attribute_set_id');
        $positiveNumberAttributes = array('weight', 'price', 'special_price', 'msrp');
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        foreach ($productEntity->getAttributeCollection($data['attribute_set_id']) as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $value = false;
            $isSet = false;
            if (isset($data[$attribute->getAttributeCode()])) {
                $value = $data[$attribute->getAttributeCode()];
                $isSet = true;
            }
            $applicable = false;
            if (!$attribute->getApplyTo() || in_array($data['type_id'], $attribute->getApplyTo())) {
                $applicable = true;
            }

            if (!$applicable && !$attribute->isStatic() && $isSet) {
                $productTypes = Mage_Catalog_Model_Product_Type::getTypes();
                $this->_addError(sprintf('Attribute "%s" is not applicable for product type "%s"', $attributeCode,
                    $productTypes[$data['type_id']]['label']));
            }

            if ($applicable && $isSet) {
                // Validate dropdown attributes
                if ($attribute->usesSource()
                    // skip check when field will be validated later as a required one
                    && !(empty($value) && $attribute->getIsRequired())
                ) {
                    $allowedValues = $this->_getAttributeAllowedValues($attribute->getSource()->getAllOptions());
                    if (!is_array($value)) {
                        // make validation of select and multiselect identical
                        $value = array($value);
                    }
                    foreach ($value as $selectValue) {
                        $useStrictMode = !is_numeric($selectValue);
                        if (!in_array($selectValue, $allowedValues, $useStrictMode)
                            && !$this->_isConfigValueUsed($data, $attributeCode)
                        ) {
                            $this->_addError(sprintf('Invalid value "%s" for attribute "%s".',
                                $selectValue, $attributeCode));
                        }
                    }
                }
                // Validate datetime attributes
                if ($attribute->getBackendType() == 'datetime') {
                    try {
                        $attribute->getBackend()->formatDate($value);
                    } catch (Zend_Date_Exception $e) {
                        $this->_addError(sprintf('Invalid date in the "%s" field.', $attributeCode));
                    }
                }
                // Validate positive number required attributes
                if (in_array($attributeCode, $positiveNumberAttributes) && (!empty($value) && $value !== 0)
                    && (!is_numeric($value) || $value < 0)
                ) {
                    $this->_addError(sprintf('Please enter a number 0 or greater in the "%s" field.', $attributeCode));
                }
            }

            if ($applicable && $attribute->getIsRequired() && $attribute->getIsVisible()) {
                if (!in_array($attributeCode, $positiveNumberAttributes) || $value !== 0) {
                    $requiredAttributes[] = $attribute->getAttributeCode();
                }
            }
        }

        foreach ($requiredAttributes as $key) {
            if (!array_key_exists($key, $data)) {
                if (!$this->_isUpdate()) {
                    $this->_addError(sprintf('Missing "%s" in request.', $key));
                    continue;
                }
            } else if (!is_numeric($data[$key]) && empty($data[$key])) {
                $this->_addError(sprintf('Empty value for "%s" in request.', $key));
            }
        }
    }

    /**
     * Validate SKU
     *
     * @param array $data
     * @return bool
     */
    protected function _validateSku($data)
    {
        if ($this->_isUpdate() && !isset($data['sku'])) {
            return true;
        }
        if (!Zend_Validate::is((string)$data['sku'], 'StringLength', array('min' => 0, 'max' => 64))) {
            $this->_addError('SKU length should be 64 characters maximum.');
        }
    }

    /**
     * Validate vendor SKU
     * @param array $data
     * @return bool
     */
    protected function _validateVendorSku($data) {
        if ($this->_isUpdate() && !isset($data['sku'])) {
            return true;
        }
        if (!Zend_Validate::is((string)$data['sku'], 'StringLength', array('min' => 0, 'max' => 64))) {
            $this->_addError('SKU length should be 64 characters maximum.');
        }
    }

}
