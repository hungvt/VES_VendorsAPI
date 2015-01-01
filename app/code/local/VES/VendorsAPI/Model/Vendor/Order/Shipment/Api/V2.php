<?php

class VES_VendorsAPI_Model_Vendor_Order_Shipment_Api_V2 extends VES_VendorsAPI_Model_Vendor_Order_Shipment_Api
{
    protected function _prepareItemQtyData($data)
    {
        $_data = array();
        foreach ($data as $item) {
            if (isset($item->order_item_id) && isset($item->qty)) {
                $_data[$item->order_item_id] = $item->qty;
            }
        }
        return $_data;
    }

    /**
     * Create new shipment for order
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param boolean $email
     * @param boolean $includeComment
     * @return string
     */
    public function create($vendorApiKey, $orderIncrementId, $itemsQty = array(), $comment = null, $email = false,
        $includeComment = false
    ) {
        if(!$this->_isAdvancedMode()) $this->_fault('method_not_support');

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $itemsQty = $this->_prepareItemQtyData($itemsQty);
        /**
          * Check order existing
          */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }

        $this->_isOrderOwner($vendorApiKey, $orderIncrementId, 'order');

        /**
         * Check shipment create availability
         */
        if (!$order->canShip()) {
             $this->_fault('data_invalid', Mage::helper('sales')->__('Cannot do shipment for order.'));
        }

         /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = $order->prepareShipment($itemsQty);
        if ($shipment) {
            $shipment->register();
            $shipment->addComment($comment, $email && $includeComment);
            if ($email) {
                $shipment->setEmailSent(true);
            }
            $shipment->getOrder()->setIsInProcess(true);
            try {
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
                $shipment->sendEmail($email, ($includeComment ? $comment : ''));
            } catch (Mage_Core_Exception $e) {
                $this->_fault('data_invalid', $e->getMessage());
            }
            return $shipment->getIncrementId();
        }
        return null;
    }

    /**
     * Retrieve allowed shipping carriers for specified order
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @return array
     */
    public function getCarriers($vendorApiKey, $orderIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        /**
          * Check order existing
          */
        if (!$order->getId()) {
            $this->_fault('order_not_exists');
        }

        $this->_isOrderOwner($vendorApiKey, $orderIncrementId, 'order');

        $carriers = array();
        foreach ($this->_getCarriers($order) as $key => $value) {
            $carriers[] = array('key' => $key, 'value' => $value);
        }

        return $carriers;
    }
}
