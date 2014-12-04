<?php
/**
 *
 * @author        VnEcoms Team <support@vnecoms.com>
 * @website        http://www.vnecoms.com
 */
class VES_VendorsAPI_Model_Observer
{
    public function vendor_save_before($observer) {
        $vendor = $observer->getVendor();
        if(!$vendor->getData('ves_api_key')) {
            $vendor->setData('ves_api_key', md5($vendor->getVendorId() . md5(strtotime(now()) . rand(1000, 9999))));
        }
        return;
    }
}