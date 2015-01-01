<?php

abstract class VES_VendorsAPI_Model_Api2_Vendor_Order_Rest extends VES_VendorsAPI_Model_Api2_Vendor_Order
{
    /**
     * Retrieve information about specified order item
     *
     * @throws Mage_Api2_Exception
     * @return array
     */
    protected function _retrieve()
    {
        $vendor = $this->_getVendorByApiKey($this->getVendorApiKey());
        if(!$vendor->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

        $orderId    = $this->getRequest()->getParam('id');
        $this->_isOrderOwnerAdvanced($vendor,$orderId,'order');

        $collection = $this->_getCollectionForSingleRetrieve($orderId);

        //Mage::log($collection->count());

        if ($this->_isPaymentMethodAllowed() and $this->_isAdvancedMode()) {
            $this->_addPaymentMethodInfo($collection);
        }
        if ($this->_isAdvancedMode() and $this->_isAdvancedMode()) {
            $this->_addGiftMessageInfo($collection);
        }
        if($this->_isAdvancedMode()) $this->_addTaxInfo($collection);

        $order = $collection->getFirstItem();
       // Mage::log($order->getData());
        if (!$order->getOrderId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }

       // $this->_isOrderOwner($vendor, $order->getId());

        $orderData = $order->getData();
        $addresses = $this->_getAddresses(array($orderId));
        $items     = $this->_getItems(array($orderId));
        $comments  = $this->_getComments(array($orderId));

        if ($addresses) {
            $orderData['addresses'] = $addresses[$orderId];
        }
        if ($items) {
            $orderData['order_items'] = $items[$orderId];
        }
        if ($comments) {
            $orderData['order_comments'] = $comments[$orderId];
        }
        return $orderData;
    }
}
