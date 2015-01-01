<?php

class VES_VendorsAPI_Model_Vendor_Order_Invoice_Api extends VES_VendorsAPI_Model_Api_Resource_Order
{
    /**
     * Initialize attributes map
     */
    public function __construct()
    {
        $this->_attributesMap = array(
            'invoice' => array('invoice_id' => 'entity_id'),
            'invoice_item' => array('item_id' => 'entity_id'),
            'invoice_comment' => array('comment_id' => 'entity_id'));
    }

    /**
     * Initialize basic invoice model
     *
     * @param string $vendorApiKey
     * @param mixed $orderIncrementId
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function _initOrderInvoice($vendorApiKey, $orderIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($orderIncrementId);

        /* @var Mage_Sales_Model_Order_Invoice $invoice */

        if (!$invoice->getId()) {
            $this->_fault('not_exists');
        }

        if($invoice->getVendorId() != $vendor->getId()) {
            $this->_fault('vendor_not_change');
        }

        return $invoice;
    }

    /**
     * Retrive invoices list. Filtration could be applied
     *
     * @param string $vendorApiKey
     * @param null|object|array $filters
     * @return array
     */
    public function items($vendorApiKey,$filters = null)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $invoices = array();
        /** @var $invoiceCollection Mage_Sales_Model_Mysql4_Order_Invoice_Collection */
        $invoiceCollection = Mage::getResourceModel('sales/order_invoice_collection');
        $invoiceCollection->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('order_id')
            ->addAttributeToSelect('increment_id')
            ->addAttributeToSelect('created_at')
            ->addAttributeToSelect('state')
            ->addAttributeToSelect('grand_total')
            ->addAttributeToSelect('order_currency_code');

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        try {
            $filters = $apiHelper->parseFilters($filters, $this->_attributesMap['invoice']);
            foreach ($filters as $field => $value) {
                $invoiceCollection->addFieldToFilter($field, $value);
            }
            $invoiceCollection->addAttributeToFilter('vendor_id',$vendor->getId());

        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        foreach ($invoiceCollection as $invoice) {
            $invoices[] = $this->_getAttributes($invoice, 'invoice');
        }
        return $invoices;
    }

    /**
     * Retrieve vendor invoice information
     *
     * @param string $vendorApiKey
     * @param string $invoiceIncrementId
     * @return array
     */
    public function info($vendorApiKey, $invoiceIncrementId)
    {
        /*initialize order invoice basic model */
        $invoice = $this->_initOrderInvoice($vendorApiKey, $invoiceIncrementId);

        $result = $this->_getAttributes($invoice, 'invoice');
        $result['order_increment_id'] = $invoice->getOrderIncrementId();

        $result['items'] = array();
        foreach ($invoice->getAllItems() as $item) {
            $result['items'][] = $this->_getAttributes($item, 'invoice_item');
        }

        $result['comments'] = array();
        foreach ($invoice->getCommentsCollection() as $comment) {
            $result['comments'][] = $this->_getAttributes($comment, 'invoice_comment');
        }

        return $result;
    }

    /**
     * Create new invoice for order
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @param array $itemsQty
     * @param string $comment
     * @param boolean $email
     * @param boolean $includeComment
     * @return string
     */
    public function create($vendorApiKey, $orderIncrementId, $itemsQty, $comment = null, $email = false, $includeComment = false)
    {
        if(!$this->_isAdvancedMode()) $this->_fault('method_not_support');

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
       // Mage::log($vendor->getData());
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        $this->_isOrderOwner($vendor,$orderIncrementId,'order');

        /* @var $order Mage_Sales_Model_Order */
        /**
          * Check order existing
          */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }

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
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            $invoice->sendEmail($email, ($includeComment ? $comment : ''));
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $invoice->getIncrementId();
    }

    /**
     * Add comment to invoice
     *
     * @param string $vendorApiKey
     * @param string $invoiceIncrementId
     * @param string $comment
     * @param boolean $email
     * @param boolean $includeComment
     * @return boolean
     */
    public function addComment($vendorApiKey, $invoiceIncrementId, $comment, $email = false, $includeComment = false)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $invoice = $this->_initOrderInvoice($vendorApiKey, $invoiceIncrementId);

        /* @var $invoice Mage_Sales_Model_Order_Invoice */

        if (!$invoice->getId()) {
            $this->_fault('not_exists');
        }


        try {
            $invoice->addComment($comment, $email);
            $invoice->sendUpdateEmail($email, ($includeComment ? $comment : ''));
            $invoice->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }

    /**
     * Capture invoice
     *
     * @param string $vendorApiKey
     * @param string $invoiceIncrementId
     * @return boolean
     */
    public function capture($vendorApiKey, $invoiceIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $invoice = $this->_initOrderInvoice($vendorApiKey, $invoiceIncrementId);

        /* @var $invoice Mage_Sales_Model_Order_Invoice */

        if (!$invoice->getId()) {
            $this->_fault('not_exists');
        }

        if (!$invoice->canCapture()) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Invoice cannot be captured.'));
        }

        try {
            $invoice->capture();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        } catch (Exception $e) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Invoice capturing problem.'));
        }

        return true;
    }

    /**
     * Void invoice
     *
     * @param string $vendorApiKey
     * @param unknown_type $invoiceIncrementId
     * @return unknown
     */
    public function void($vendorApiKey, $invoiceIncrementId)
    {
        $this->_fault('method_not_support',Mage::helper('core')->__('Method not support!!!.'));

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $invoice = $this->_initOrderInvoice($vendorApiKey, $invoiceIncrementId);
        /* @var $invoice Mage_Sales_Model_Order_Invoice */

        if (!$invoice->getId()) {
            $this->_fault('not_exists');
        }

        if (!$invoice->canVoid()) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Invoice cannot be voided.'));
        }

        try {
            $invoice->void();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        } catch (Exception $e) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Invoice void problem'));
        }

        return true;
    }

    /**
     * Cancel invoice
     *
     * @param string $vendorApiKey
     * @param string $invoiceIncrementId
     * @return boolean
     */
    public function cancel($vendorApiKey, $invoiceIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $invoice = $this->_initOrderInvoice($vendorApiKey, $invoiceIncrementId);

        /* @var $invoice Mage_Sales_Model_Order_Invoice */

        if (!$invoice->getId()) {
            $this->_fault('not_exists');
        }

        if (!$invoice->canCancel()) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Invoice cannot be canceled.'));
        }

        try {
            $invoice->cancel();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('status_not_changed', $e->getMessage());
        } catch (Exception $e) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Invoice canceling problem.'));
        }

        return true;
    }
}
