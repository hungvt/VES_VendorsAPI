<?php

class VES_VendorsAPI_Model_Vendor_Order_Creditmemo_Api extends VES_VendorsAPI_Model_Api_Resource_Order
{

    /**
     * Initialize attributes mapping
     */
    public function __construct()
    {
        $this->_attributesMap = array(
            'creditmemo' => array('creditmemo_id' => 'entity_id'),
            'creditmemo_item' => array('item_id' => 'entity_id'),
            'creditmemo_comment' => array('comment_id' => 'entity_id')
        );
    }

    /**
     * Retrieve credit memos list. Filtration could be applied
     *
     * @param string $vendorApiKey
     * @param null|object|array $filters
     * @return array
     */
    public function items($vendorApiKey, $filters = null)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $creditmemos = array();
        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        $filters = $apiHelper->parseFilters($filters, $this->_attributesMap['creditmemo']);
        /** @var $creditmemoModel Mage_Sales_Model_Order_Creditmemo */
        $creditmemoModel = Mage::getModel('sales/order_creditmemo');
        try {
            $creditMemoCollection = $creditmemoModel->getFilteredCollectionItems($filters);
            $creditMemoCollection->addAttributeToFilter('vendor_id', $vendor->getId());

            foreach ($creditMemoCollection as $creditmemo) {
                $creditmemos[] = $this->_getAttributes($creditmemo, 'creditmemo');
            }
        } catch (Exception $e) {
            $this->_fault('invalid_filter', $e->getMessage());
        }
        return $creditmemos;
    }

    /**
     * Make filter of appropriate format for list method
     *
     * @deprecated since 1.7.0.1
     * @param array|null $filter
     * @return array|null
     */
    protected function _prepareListFilter($filter = null)
    {
        // prepare filter, map field creditmemo_id to entity_id
        if (is_array($filter)) {
            foreach ($filter as $field => $value) {
                if (isset($this->_attributesMap['creditmemo'][$field])) {
                    $filter[$this->_attributesMap['creditmemo'][$field]] = $value;
                    unset($filter[$field]);
                }
            }
        }
        return $filter;
    }

    /**
     * Retrieve credit memo information
     *
     * @param string $vendorApiKey
     * @param string $creditmemoIncrementId
     * @return array
     */
    public function info($vendorApiKey, $creditmemoIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $creditmemo = $this->_getCreditmemo($vendorApiKey, $creditmemoIncrementId);

        $this->_isOrderOwner($vendor, $creditmemoIncrementId, 'order_creditmemo');

        // get credit memo attributes with entity_id' => 'creditmemo_id' mapping
        $result = $this->_getAttributes($creditmemo, 'creditmemo');
        $result['order_increment_id'] = $creditmemo->getOrder()->load($creditmemo->getOrderId())->getIncrementId();
        // items refunded
        $result['items'] = array();
        foreach ($creditmemo->getAllItems() as $item) {
            $result['items'][] = $this->_getAttributes($item, 'creditmemo_item');
        }
        // credit memo comments
        $result['comments'] = array();
        foreach ($creditmemo->getCommentsCollection() as $comment) {
            $result['comments'][] = $this->_getAttributes($comment, 'creditmemo_comment');
        }

        return $result;
    }

    /**
     * Create new credit memo for order
     *
     * @param string $vendorApiKey
     * @param string $orderIncrementId
     * @param array $creditmemoData array('qtys' => array('sku1' => qty1, ... , 'skuN' => qtyN),
     *      'shipping_amount' => value, 'adjustment_positive' => value, 'adjustment_negative' => value)
     * @param string|null $comment
     * @param bool $notifyCustomer
     * @param bool $includeComment
     * @param string $refundToStoreCreditAmount
     * @return string $creditmemoIncrementId
     */
    public function create($vendorApiKey, $orderIncrementId, $creditmemoData = null, $comment = null, $notifyCustomer = false,
        $includeComment = false, $refundToStoreCreditAmount = null)
    {
        if(!$this->_isAdvancedMode()) $this->_fault('method_not_support');

        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        /** @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->load($orderIncrementId, 'increment_id');
        if (!$order->getId()) {
            $this->_fault('order_not_exists');
        }
        $this->_isOrderOwner($vendor, $orderIncrementId, 'order');

        if (!$order->canCreditmemo()) {
            $this->_fault('cannot_create_creditmemo');
        }
        $creditmemoData = $this->_prepareCreateData($creditmemoData);

        /** @var $service Mage_Sales_Model_Service_Order */
        $service = Mage::getModel('sales/service_order', $order);
        /** @var $creditmemo Mage_Sales_Model_Order_Creditmemo */
        $creditmemo = $service->prepareCreditmemo($creditmemoData);

        // refund to Store Credit
        if ($refundToStoreCreditAmount) {
            // check if refund to Store Credit is available
            if ($order->getCustomerIsGuest()) {
                $this->_fault('cannot_refund_to_storecredit');
            }
            $refundToStoreCreditAmount = max(
                0,
                min($creditmemo->getBaseCustomerBalanceReturnMax(), $refundToStoreCreditAmount)
            );
            if ($refundToStoreCreditAmount) {
                $refundToStoreCreditAmount = $creditmemo->getStore()->roundPrice($refundToStoreCreditAmount);
                $creditmemo->setBaseCustomerBalanceTotalRefunded($refundToStoreCreditAmount);
                $refundToStoreCreditAmount = $creditmemo->getStore()->roundPrice(
                    $refundToStoreCreditAmount*$order->getStoreToOrderRate()
                );
                // this field can be used by customer balance observer
                $creditmemo->setBsCustomerBalTotalRefunded($refundToStoreCreditAmount);
                // setting flag to make actual refund to customer balance after credit memo save
                $creditmemo->setCustomerBalanceRefundFlag(true);
            }
        }
        $creditmemo->setPaymentRefundDisallowed(true)->register();
        // add comment to creditmemo
        if (!empty($comment)) {
            $creditmemo->addComment($comment, $notifyCustomer);
        }
        try {
            Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($order)
                ->save();
            // send email notification
            $creditmemo->sendEmail($notifyCustomer, ($includeComment ? $comment : ''));
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }
        return $creditmemo->getIncrementId();
    }

    /**
     * Add comment to credit memo
     *
     * @param string $vendorApiKey
     * @param string $creditmemoIncrementId
     * @param string $comment
     * @param boolean $notifyCustomer
     * @param boolean $includeComment
     * @return boolean
     */
    public function addComment($vendorApiKey, $creditmemoIncrementId, $comment, $notifyCustomer = false, $includeComment = false)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $creditmemo = $this->_getCreditmemo($vendorApiKey, $creditmemoIncrementId);
        $this->_isOrderOwner($vendor, $creditmemoIncrementId, 'order_creditmemo');

        try {
            $creditmemo->addComment($comment, $notifyCustomer)->save();
            $creditmemo->sendUpdateEmail($notifyCustomer, ($includeComment ? $comment : ''));
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }

    /**
     * Cancel credit memo
     *
     * @param string $vendorApiKey
     * @param string $creditmemoIncrementId
     * @return boolean
     */
    public function cancel($vendorApiKey, $creditmemoIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $creditmemo = $this->_getCreditmemo($vendorApiKey, $creditmemoIncrementId);
        $this->_isOrderOwner($vendor, $creditmemoIncrementId, 'order_creditmemo');

        if (!$creditmemo->canCancel()) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Credit memo cannot be canceled.'));
        }
        try {
            $creditmemo->cancel()->save();
        } catch (Exception $e) {
            $this->_fault('status_not_changed', Mage::helper('sales')->__('Credit memo canceling problem.'));
        }

        return true;
    }

    /**
     * Hook method, could be replaced in derived classes
     *
     * @param  array $data
     * @return array
     */
    protected function _prepareCreateData($data)
    {
        $data = isset($data) ? $data : array();

        if (isset($data['qtys']) && count($data['qtys'])) {
            $qtysArray = array();
            foreach ($data['qtys'] as $qKey => $qVal) {
                // Save backward compatibility
                if (is_array($qVal)) {
                    if (isset($qVal['order_item_id']) && isset($qVal['qty'])) {
                        $qtysArray[$qVal['order_item_id']] = $qVal['qty'];
                    }
                } else {
                    $qtysArray[$qKey] = $qVal;
                }
            }
            $data['qtys'] = $qtysArray;
        }
        return $data;
    }

    /**
     * Load CreditMemo by IncrementId
     *
     * @param string $vendorApiKey
     * @param mixed $incrementId
     * @return Mage_Core_Model_Abstract|Mage_Sales_Model_Order_Creditmemo
     */
    protected function _getCreditmemo($vendorApiKey, $incrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        /** @var $creditmemo Mage_Sales_Model_Order_Creditmemo */
        $creditmemo = Mage::getModel('sales/order_creditmemo')->load($incrementId, 'increment_id');
        if (!$creditmemo->getId()) {
            $this->_fault('not_exists');
        }

        if($creditmemo->getVendorId() != $vendor->getId()) {
            $this->_fault('vendor_not_change_creditmemo');
        }
        return $creditmemo;
    }

}
