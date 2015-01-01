<?php

class VES_VendorsAPI_Model_Api2_Vendor_Order_Item extends VES_VendorsAPI_Model_Api2_Vendor
{
    /**
     * check order object is owned of vendor (advanced mode only)
     * @param object $vendor
     * @param string $orderId
     * @param string|null $type
     */
    protected function _isOrderOwner($vendor, $orderId, $type='order') {
        switch($type) {
            case 'order': $obj = Mage::getModel('sales/order')->load($orderId);break;
            case 'order_invoice': $obj = Mage::getModel('sales/order_invoice')->load($orderId);break;
            case 'order_shipment': $obj = Mage::getModel('sales/order_shipment')->load($orderId);break;
            case 'order_creditmemo': $obj = Mage::getModel('sales/order_creditmemo')->load($orderId);break;
        }

        if(!$obj->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        if($obj->getVendorId() != $vendor->getId()) {
            switch($type) {
                case 'order':$this->_critical('This vendor not is owner of order.');break;
                case 'order_invoice':$this->_critical('This vendor not is owner of invoice.');break;
                case 'order_shipment':$this->_critical('This vendor not is owner of shipment.');break;
                case 'order_creditmemo':$this->_critical('This vendor not is owner of creditmemo.');break;
            }
        }
    }

    public function getVendorApiKey() {
        return $this->getRequest()->getParam('api');
    }

    /**
     * return true if system in advanced mode(Advanced and Advanced X),false if other
     * @return boolean
     */
    protected function _isAdvancedMode() {
        return Mage::helper('vendors')->isAdvancedMode();
    }

    /**
     * check order (or invoice...) owned by vendor
     * in advanced mode check vendor id of order(invoice...)
     * in general mode check order item(invoice ... same advanced mode)
     * @param $vendor
     * @param $orderId
     * @param string $type
     */
    protected function _isOrderOwnerAdvanced($vendor,$orderId, $type='order') {
        if($this->_isAdvancedMode()) return $this->_isOrderOwner($vendor,$orderId,$type);
        else {
            if(in_array($type,array('order_invoice','order_shipment','order_creditmemo'))) {
                return $this->_isOrderOwner($vendor,$orderId,$type);
            } else {
                $obj = Mage::getModel('sales/order')->load($orderId);
                $items = $obj->getAllItems();
                $_is_owned = false;
                foreach($items as $item) {
                    Mage::log('item'.$item->getId());
                    if($item->getVendorId() == $vendor->getId()) {$_is_owned = true;break;}
                }
                // Mage::log($_is_owned);
                if($_is_owned === false) {
                    $this->_critical('Order not is owned by vendor.');
                }
            }
        }
    }
}
