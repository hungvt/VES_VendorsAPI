<?php
class VES_VendorsAPI_Model_Api2_Vendor_Product extends VES_VendorsAPI_Model_Api2_Vendor
{
    /**
     * Get available attributes of API resource
     *
     * @param string $userType
     * @param string $operation
     * @return array
     */
    public function getAvailableAttributes($userType, $operation)
    {
        $attributes = $this->getAvailableAttributesFromConfig();
        /** @var $entityType Mage_Eav_Model_Entity_Type */
        $entityType = Mage::getModel('eav/entity_type')->loadByCode('catalog_product');
        $entityOnlyAttrs = $this->getEntityOnlyAttributes($userType, $operation);
        /** @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
        foreach ($entityType->getAttributeCollection() as $attribute) {
            if ($this->_isAttributeVisible($attribute, $userType)) {
                $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
            }
        }
        $excludedAttrs = $this->getExcludedAttributes($userType, $operation);
        $includedAttrs = $this->getIncludedAttributes($userType, $operation);
        foreach ($attributes as $code => $label) {
            if (in_array($code, $excludedAttrs) || ($includedAttrs && !in_array($code, $includedAttrs))) {
                unset($attributes[$code]);
            }
            if (in_array($code, $entityOnlyAttrs)) {
                $attributes[$code] .= ' *';
            }
        }

        return $attributes;
    }

    /**
     * Define if attribute should be visible for passed user type
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @param string $userType
     * @return bool
     */
    protected function _isAttributeVisible(Mage_Catalog_Model_Resource_Eav_Attribute $attribute, $userType)
    {
        $isAttributeVisible = false;
        $isAttributeVisible = $attribute->getIsVisible();
        /*if ($userType == Mage_Api2_Model_Auth_User_Admin::USER_TYPE) {
            $isAttributeVisible = $attribute->getIsVisible();
        } else {
            $systemAttributesForNonAdmin = array(
                'sku', 'name', 'short_description', 'description', 'tier_price', 'meta_title', 'meta_description',
                'meta_keyword',
            );
            if ($attribute->getIsUserDefined()) {
                $isAttributeVisible = $attribute->getIsVisibleOnFront();
            } else if (in_array($attribute->getAttributeCode(), $systemAttributesForNonAdmin)) {
                $isAttributeVisible = true;
            }
        }*/
        return (bool)$isAttributeVisible;
    }
}