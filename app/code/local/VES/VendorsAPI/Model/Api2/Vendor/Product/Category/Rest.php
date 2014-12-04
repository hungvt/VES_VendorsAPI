<?php

abstract class VES_VendorsAPI_Model_Api2_Vendor_Product_Category_Rest extends VES_VendorsAPI_Model_Api2_Vendor_Product_Rest
{
    /**
     * Product category assign is not available
     *
     * @param array $data
     */
    protected function _create(array $data)
    {
        /* @var $validator Mage_Api2_Model_Resource_Validator_Fields */
        $validator = Mage::getResourceModel('api2/validator_fields', array('resource' => $this));
        if (!$validator->isValidData($data)) {
            foreach ($validator->getErrors() as $error) {
                $this->_error($error, Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }
            $this->_critical(self::RESOURCE_DATA_PRE_VALIDATION_ERROR);
        }

        $product = $this->_getProduct();
        $category = $this->_getCategoryById($data['category_id']);

        $categoryIds = $product->getCategoryIds();
        if (!is_array($categoryIds)) {
            $categoryIds = array();
        }
        if (in_array($category->getId(), $categoryIds)) {
            $this->_critical(sprintf('Product #%d is already assigned to category #%d',
                $product->getId(), $category->getId()), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        if ($category->getId() == Mage_Catalog_Model_Category::TREE_ROOT_ID) {
            $this->_critical('Cannot assign product to tree root category.', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        $categoryIds[] = $category->getId();
        $product->setCategoryIds(implode(',', $categoryIds));

        try{
            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }

        return $this->_getLocation($category);
    }

    /**
     * Product category update is not available
     *
     * @param array $data
     */
    protected function _update(array $data)
    {
        $this->_critical(self::RESOURCE_METHOD_NOT_ALLOWED);
    }

    /**
     * Retrieve product data
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
        $this->_critical('Method not support');
    }

    protected function _retrieve() {
        $this->_critical('Method not support');
    }

    /**
     * vendor owned product for product category unassign
     */
    protected function _delete()
    {
        $product = $this->_getProduct();
        $category = $this->_getCategoryById($this->getRequest()->getParam('category_id'));

        $categoryIds = $product->getCategoryIds();
        $categoryToBeDeletedId = array_search($category->getId(), $categoryIds);
        if (false === $categoryToBeDeletedId) {
            $this->_critical(sprintf('Product #%d isn\'t assigned to category #%d',
                $product->getId(), $category->getId()), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }

        // delete category
        unset($categoryIds[$categoryToBeDeletedId]);
        $product->setCategoryIds(implode(',', $categoryIds));

        try{
            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }

        return true;
    }

    /**
     * Load category by id
     *
     * @param int $categoryId
     * @return Mage_Catalog_Model_Category
     */
    protected function _getCategoryById($categoryId)
    {
        /** @var $category Mage_Catalog_Model_Category */
        $category = Mage::getModel('catalog/category')->setStoreId(0)->load($categoryId);
        if (!$category->getId()) {
            $this->_critical('Category not found', Mage_Api2_Model_Server::HTTP_NOT_FOUND);
        }

        return $category;
    }

    /**
     * Get assigned categories ids
     *
     * @return array
     */
    protected function _getCategoryIds()
    {
        return $this->_getProduct()->getCategoryCollection()->addIsActiveFilter()->getAllIds();
    }

    /**
     * Get resource location
     *
     * @param Mage_Core_Model_Abstract $resource
     * @return string URL
     */
    protected function _getLocation($resource)
    {
        /** @var $apiTypeRoute Mage_Api2_Model_Route_ApiType */
        $apiTypeRoute = Mage::getModel('api2/route_apiType');

        $chain = $apiTypeRoute->chain(new Zend_Controller_Router_Route(
            $this->getConfig()->getRouteWithEntityTypeAction($this->getResourceType())
        ));
        $params = array(
            'api_type' => $this->getRequest()->getApiType(),
            'id' => $this->getRequest()->getParam('id'),
            'category_id' => $resource->getId()
        );
        $uri = $chain->assemble($params);

        return '/' . $uri;
    }
}
