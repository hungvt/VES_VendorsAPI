<?php

class VES_VendorsAPI_Model_Vendor_Order_Invoice_Api_V2 extends VES_VendorsAPI_Model_Vendor_Order_Invoice_Api
{
    /**
     * Create new invoice for order
     *
     * @param string $vendorApiKey
     * @param string $invoiceIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param bool $email
     * @param bool $includeComment
     * @return string
     */
    public function create($vendorApiKey, $invoiceIncrementId, $itemsQty, $comment = null, $email = false, $includeComment = false)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($invoiceIncrementId);

        $itemsQty = $this->_prepareItemQtyData($itemsQty);
        /* @var $order Mage_Sales_Model_Order */
        /**
          * Check order existing
          */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }

        $this->_isOrderOwner($vendorApiKey, $invoiceIncrementId, 'order');

        /**
         * Check invoice create availability
         */
        if (!$order->canInvoice()) {
             $this->_fault('data_invalid', Mage::helper('sales')->__('Cannot do invoice for order.'));
        }

        $invoice = $order->prepareInvoice($itemsQty);

        $invoice->register();

        if ($comment !== null) {
            $invoice->addComment($comment, $email);
        }

        if ($email) {
            $invoice->setEmailSent(true);
        }

        $invoice->getOrder()->setIsInProcess(true);

        try {
            Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
            $invoice->sendEmail($email, ($includeComment ? $comment : ''));
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $invoice->getIncrementId();
    }

    /**
     * Prepare items quantity data
     *
     * @param array $data
     * @return array
     */
    protected function _prepareItemQtyData($data)
    {
        $quantity = array();
        foreach ($data as $item) {
            if (isset($item->order_item_id) && isset($item->qty)) {
                $quantity[$item->order_item_id] = $item->qty;
            }
        }
        return $quantity;
    }
}
