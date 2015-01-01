<?php

class VES_VendorsAPI_Model_Vendor_Order_Creditmemo_Api_V2 extends VES_VendorsAPI_Model_Vendor_Order_Creditmemo_Api
{

    /**
     * Prepare filters
     *
     * @deprecated since 1.7.0.1
     * @param null|object $filters
     * @return array
     */
    protected function _prepareListFilter($filters = null)
    {
        $preparedFilters = array();
        $helper = Mage::helper('api');
        if (isset($filters->filter)) {
            $helper->associativeArrayUnpack($filters->filter);
            $preparedFilters += $filters->filter;
        }
        if (isset($filters->complex_filter)) {
            $helper->associativeArrayUnpack($filters->complex_filter);
            foreach ($filters->complex_filter as &$filter) {
                $helper->associativeArrayUnpack($filter);
            }
            $preparedFilters += $filters->complex_filter;
        }
        foreach ($preparedFilters as $field => $value) {
            if (isset($this->_attributesMap['creditmemo'][$field])) {
                $preparedFilters[$this->_attributesMap['creditmemo'][$field]] = $value;
                unset($preparedFilters[$field]);
            }
        }

        return $preparedFilters;
    }

    /**
     * Prepare data
     *
     * @param null|object $data
     * @return array
     */
    protected function _prepareCreateData($data)
    {
        // convert data object to array, if it's null turn it into empty array
        $data = (isset($data) and is_object($data)) ? get_object_vars($data) : array();
        // convert qtys object to array
        if (isset($data['qtys']) && count($data['qtys'])) {
            $qtysArray = array();
            foreach ($data['qtys'] as &$item) {
                if (isset($item->order_item_id) && isset($item->qty)) {
                    $qtysArray[$item->order_item_id] = $item->qty;
                }
            }
            $data['qtys'] = $qtysArray;
        }
        return $data;
    }
}
