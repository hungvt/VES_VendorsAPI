<?php

class VES_VendorsAPI_Model_Vendor_Order_Shipment_Api extends VES_VendorsAPI_Model_Api_Resource_Order
{
    public function __construct()
    {
        $this->_attributesMap['shipment'] = array('shipment_id' => 'entity_id');

        $this->_attributesMap['shipment_item'] = array('item_id'    => 'entity_id');

        $this->_attributesMap['shipment_comment'] = array('comment_id' => 'entity_id');

        $this->_attributesMap['shipment_track'] = array('track_id'   => 'entity_id');
    }

    /**
     * Initialize basic shipment model
     *
     * @param string $vendorApiKey
     * @param mixed $orderIncrementId
     * @return Mage_Sales_Model_Order_Shipment
     */
    protected function _initOrderShipment($vendorApiKey, $orderIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($orderIncrementId);

        /* @var Mage_Sales_Model_Order_Shipment $shipment */

        if (!$shipment->getId()) {
            $this->_fault('not_exists');
        }

        if($shipment->getVendorId() != $vendor->getId()) {
            $this->_fault('vendor_not_change');
        }

        return $shipment;
    }

    /**
     * Retrieve shipments by filters
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

        $shipments = array();
        //TODO: add full name logic
        $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')
            ->addAttributeToSelect('increment_id')
            ->addAttributeToSelect('created_at')
            ->addAttributeToSelect('total_qty')
            ->joinAttribute('shipping_firstname', 'order_address/firstname', 'shipping_address_id', null, 'left')
            ->joinAttribute('shipping_lastname', 'order_address/lastname', 'shipping_address_id', null, 'left')
            ->joinAttribute('order_increment_id', 'order/increment_id', 'order_id', null, 'left')
            ->joinAttribute('order_created_at', 'order/created_at', 'order_id', null, 'left');

        /** @var $apiHelper Mage_Api_Helper_Data */
        $apiHelper = Mage::helper('api');
        try {
            $filters = $apiHelper->parseFilters($filters, $this->_attributesMap['shipment']);
            foreach ($filters as $field => $value) {
                $shipmentCollection->addFieldToFilter($field, $value);
            }
            $shipmentCollection->addAttributeToFilter('vendor_id', $vendor->getId());

        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
        }
        foreach ($shipmentCollection as $shipment) {
            $shipments[] = $this->_getAttributes($shipment, 'shipment');
        }

        return $shipments;
    }

    /**
     * Retrieve shipment information
     *
     * @param string $vendorApiKey
     * @param string $shipmentIncrementId
     * @return array
     */
    public function info($vendorApiKey, $shipmentIncrementId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $shipment = $this->_initOrderShipment($vendorApiKey, $shipmentIncrementId);

        /* @var $shipment Mage_Sales_Model_Order_Shipment */

        if (!$shipment->getId()) {
            $this->_fault('not_exists');
        }

        $result = $this->_getAttributes($shipment, 'shipment');

        $result['items'] = array();
        foreach ($shipment->getAllItems() as $item) {
            $result['items'][] = $this->_getAttributes($item, 'shipment_item');
        }

        $result['tracks'] = array();
        foreach ($shipment->getAllTracks() as $track) {
            $result['tracks'][] = $this->_getAttributes($track, 'shipment_track');
        }

        $result['comments'] = array();
        foreach ($shipment->getCommentsCollection() as $comment) {
            $result['comments'][] = $this->_getAttributes($comment, 'shipment_comment');
        }

        return $result;
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

        $this->_isOrderOwner($vendor,$orderIncrementId,'order');

        /**
          * Check order existing
          */
        if (!$order->getId()) {
             $this->_fault('order_not_exists');
        }

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
     * Add tracking number to order
     *
     * @param string $vendorApiKey
     * @param string $shipmentIncrementId
     * @param string $carrier
     * @param string $title
     * @param string $trackNumber
     * @return int
     */
    public function addTrack($vendorApiKey, $shipmentIncrementId, $carrier, $title, $trackNumber)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $shipment = $this->_initOrderShipment($vendorApiKey, $shipmentIncrementId);

        /* @var $shipment Mage_Sales_Model_Order_Shipment */

        if (!$shipment->getId()) {
            $this->_fault('not_exists');
        }

        $carriers = $this->_getCarriers($shipment);

        if (!isset($carriers[$carrier])) {
            $this->_fault('data_invalid', Mage::helper('sales')->__('Invalid carrier specified.'));
        }

        $track = Mage::getModel('sales/order_shipment_track')
                    ->setNumber($trackNumber)
                    ->setCarrierCode($carrier)
                    ->setTitle($title);

        $shipment->addTrack($track);

        try {
            $shipment->save();
            $track->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return $track->getId();
    }

    /**
     * Remove tracking number
     *
     * @param string $vendorApiKey
     * @param string $shipmentIncrementId
     * @param int $trackId
     * @return boolean
     */
    public function removeTrack($vendorApiKey, $shipmentIncrementId, $trackId)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }
        $shipment = $this->_initOrderShipment($vendorApiKey, $shipmentIncrementId);
        /* @var $shipment Mage_Sales_Model_Order_Shipment */

        if (!$shipment->getId()) {
            $this->_fault('not_exists');
        }

        if(!$track = $shipment->getTrackById($trackId)) {
            $this->_fault('track_not_exists');
        }

        try {
            $track->delete();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('track_not_deleted', $e->getMessage());
        }

        return true;
    }

    /**
     * Send email with shipment data to customer
     *
     * @param string $shipmentIncrementId
     * @param string $comment
     * @return bool
     */
    public function sendInfo($shipmentIncrementId, $comment = '')
    {
        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipmentIncrementId);

        if (!$shipment->getId()) {
            $this->_fault('not_exists');
        }

        try {
            $shipment->sendEmail(true, $comment)
                ->setEmailSent(true)
                ->save();
            $historyItem = Mage::getResourceModel('sales/order_status_history_collection')
                ->getUnnotifiedForInstance($shipment, Mage_Sales_Model_Order_Shipment::HISTORY_ENTITY_NAME);
            if ($historyItem) {
                $historyItem->setIsCustomerNotified(1);
                $historyItem->save();
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
    }

    /**
     * Retrieve tracking number info
     *
     * @param string $shipmentIncrementId
     * @param int $trackId
     * @return mixed
     */
    public function infoTrack($shipmentIncrementId, $trackId)
    {
         $shipment = Mage::getModel('sales/order_shipment')->loadByIncrementId($shipmentIncrementId);

        /* @var $shipment Mage_Sales_Model_Order_Shipment */

        if (!$shipment->getId()) {
            $this->_fault('not_exists');
        }

        if(!$track = $shipment->getTrackById($trackId)) {
            $this->_fault('track_not_exists');
        }

        /* @var $track Mage_Sales_Model_Order_Shipment_Track */
        $info = $track->getNumberDetail();

        if (is_object($info)) {
            $info = $info->toArray();
        }

        return $info;
    }

    /**
     * Add comment to shipment
     *
     * @param string $vendorApiKey
     * @param string $shipmentIncrementId
     * @param string $comment
     * @param boolean $email
     * @param boolean $includeInEmail
     * @return boolean
     */
    public function addComment($vendorApiKey, $shipmentIncrementId, $comment, $email = false, $includeInEmail = false)
    {
        $vendor = $this->_getVendorFromApiKey($vendorApiKey);
        if(!$vendor->getId()) {
            $this->_fault('vendor_not_exists');
        }

        $shipment = $this->_initOrderShipment($vendorApiKey, $shipmentIncrementId);

        /* @var $shipment Mage_Sales_Model_Order_Shipment */

        if (!$shipment->getId()) {
            $this->_fault('not_exists');
        }


        try {
            $shipment->addComment($comment, $email);
            $shipment->sendUpdateEmail($email, ($includeInEmail ? $comment : ''));
            $shipment->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('data_invalid', $e->getMessage());
        }

        return true;
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

        $this->_isOrderOwner($vendor, $orderIncrementId, 'order');
        /**
          * Check order existing
          */
        if (!$order->getId()) {
            $this->_fault('order_not_exists');
        }

        return $this->_getCarriers($order);
    }

    /**
     * Retrieve shipping carriers for specified order
     *
     * @param Mage_Eav_Model_Entity_Abstract $object
     * @return array
     */
    protected function _getCarriers($object)
    {
        $carriers = array();
        $carrierInstances = Mage::getSingleton('shipping/config')->getAllCarriers(
            $object->getStoreId()
        );

        $carriers['custom'] = Mage::helper('sales')->__('Custom Value');
        foreach ($carrierInstances as $code => $carrier) {
            if ($carrier->isTrackingAvailable()) {
                $carriers[$code] = $carrier->getConfigData('title');
            }
        }

        return $carriers;
    }

} // Class VES_VendorsAPI_Model_Vendor_Order_Shipment_Api End
