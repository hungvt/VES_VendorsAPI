<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Sales
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Sale api resource abstract
 *
 * @category   Mage
 * @package    Mage_Sales
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class VES_VendorsAPI_Model_Api_Resource_Order extends VES_VendorsAPI_Model_Api_Resource
{
    const MODE_GENERAL = 1;
    const MODE_ADVANCED = 2;

    /**
     * Default ignored attribute codes per entity type
     *
     * @var array
     */
    protected $_orderIgnoredAttributeCodes = array(
        'global'    =>  array('entity_id', 'attribute_set_id', 'entity_type_id'),
        'order_item'    => array('product_options','item_id', 'crc_id'),
    );

    /**
     * Attributes map array per entity type
     *
     * @var google
     */
    protected $_orderAttributesMap = array(
        'global'    => array()
    );

    /**
     * Update attributes for entity
     *
     * @param array $data
     * @param Mage_Core_Model_Abstract $object
     * @param array $attributes
     * @return VES_VendorsAPI_Model_Api_Resource_Order
     */
    protected function _updateAttributes($data, $object, $type,  array $attributes = null)
    {

        foreach ($data as $attribute=>$value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $object->setData($attribute, $value);
            }
        }

        return $this;
    }

    /**
     * Retrieve entity attributes values
     *
     * @param Mage_Core_Model_Abstract $object
     * @param array $attributes
     * @return Mage_Sales_Model_Api_Resource
     */
    protected function _getAttributes($object, $type, array $attributes = null)
    {
        $result = array();

        if (!is_object($object)) {
            return $result;
        }

        foreach ($object->getData() as $attribute=>$value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $result[$attribute] = $value;
            }
        }

        if (isset($this->_orderAttributesMap['global'])) {
            foreach ($this->_orderAttributesMap['global'] as $alias=>$attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }

        if (isset($this->_orderAttributesMap[$type])) {
            foreach ($this->_orderAttributesMap[$type] as $alias=>$attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }

        return $result;
    }

    /**
     * Check is attribute allowed to usage
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param string $entityType
     * @param array $attributes
     * @return boolean
     */
    protected function _isAllowedAttribute($attributeCode, $type, array $attributes = null)
    {
        if (!empty($attributes)
            && !(in_array($attributeCode, $attributes))) {
            return false;
        }

        if (in_array($attributeCode, $this->_orderIgnoredAttributeCodes['global'])) {
            return false;
        }

        if (isset($this->_orderIgnoredAttributeCodes[$type])
            && in_array($attributeCode, $this->_orderIgnoredAttributeCodes[$type])) {
            return false;
        }

        return true;
    }

    /**
     * check is order type owner
     * @param object|null $vendor
     * @param string $orderIncrementId
     * @param string $type
     */
    protected function _isOrderOwner($vendor,$orderIncrementId,$type) {
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        if ($vendor->getId()) {
            switch($type) {
                case 'order':
                    $obj = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId); break;
                case 'order_invoice':
                    $obj = Mage::getModel('sales/order_invoice')->loadByIncrementId($orderIncrementId); break;
                case 'order_shipment':
                    $obj = Mage::getModel('sales/order_shipment')->loadByIncrementId($orderIncrementId); break;
                case 'order_creditmemo':
                    $obj = Mage::getModel('sales/order_creditmemo')->loadByIncrementId($orderIncrementId); break;
            }

            if($obj->getVendorId() != $vendor->getId()) {
                $this->_fault('vendor_not_change');
            }
           // return true;
        }
    }

    protected function _isAdvancedMode() {
        return Mage::helper('vendors')->isAdvancedMode();
    }

    /**
     * check order (or invoice...) owned by vendor
     * in advanced mode check vendor id of order(invoice...)
     * in general mode check order item(invoice ... same advanced mode)
     * @param $vendor
     * @param $orderIncrementId
     * @param string $type
     */
    protected function _isOrderOwnerAdvanced($vendor,$orderIncrementId, $type='order') {
        if($this->_isAdvancedMode()) return $this->_isOrderOwner($vendor,$orderIncrementId,$type);
        else {
            if(in_array($type,array('order_invoice','order_shipment','order_creditmemo'))) {
                return $this->_isOrderOwner($vendor,$orderIncrementId,$type);
            } else {
                $obj = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
                $items = $obj->getAllItems();
                $_is_owned = false;
                foreach($items as $item) {
                    if($item->getVendorId() == $vendor->getId()) {$_is_owned = true;break;}
                }
                if($_is_owned == false) {
                    $this->_fault('vendor_not_change');
                }
            }
        }
    }
} // Class Mage_Sales_Model_Api_Resource End
