<?php

abstract class VES_VendorsAPI_Model_Api2_Vendor_Product_Image_Rest extends VES_VendorsAPI_Model_Api2_Vendor_Product_Rest
{
    /**
     * Attribute code for media gallery
     */
    const GALLERY_ATTRIBUTE_CODE = 'media_gallery';

    /**
     * Allowed MIME types for image
     *
     * @var array
     */
    protected $_mimeTypes = array(
        'image/jpg' => 'jpg',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/png' => 'png'
    );

    /**
     * Retrieve product image data for customer and guest roles
     *
     * @throws Mage_Api2_Exception
     * @return array
     */
    protected function _retrieve()
    {
       // $this->_critical('Method not support');
        $imageData = array();
        $imageId = (int)$this->getRequest()->getParam('image');
        $galleryData = $this->_getProduct()->getData(self::GALLERY_ATTRIBUTE_CODE);

        if (!isset($galleryData['images']) || !is_array($galleryData['images'])) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        foreach ($galleryData['images'] as $image) {
            if ($image['value_id'] == $imageId && !$image['disabled']) {
                $imageData = $this->_formatImageData($image);
                break;
            }
        }
        if (empty($imageData)) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $imageData;
    }

    /**
     * Retrieve product images data for customer and guest
     *
     * @return array
     */
    protected function _retrieveCollection()
    {
      //  $this->_critical('Method not support');
        $images = array();
        $galleryData = $this->_getProduct()->getData(self::GALLERY_ATTRIBUTE_CODE);
        if (isset($galleryData['images']) && is_array($galleryData['images'])) {
            foreach ($galleryData['images'] as $image) {
                //if (!$image['disabled']) {
                $images[] = $this->_formatImageData($image);
                //  }
            }
        }
        return $images;
    }

    /**
     * Product image delete
     *
     * @throws Mage_Api2_Exception
     */
    protected function _delete()
    {
        $imageId = (int)$this->getRequest()->getParam('image');
        $product = $this->_getProduct();
        $imageFileUri = $this->_getImageFileById($imageId);
        $this->_getMediaGallery()->removeImage($product, $imageFileUri);
        try {
            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }
    }

    /**
     * Update product image
     *
     * @throws Mage_Api2_Exception
     * @param array $data
     * @return bool
     */
    protected function _update(array $data)
    {
        $imageId = (int)$this->getRequest()->getParam('image');
        $imageFileUri = $this->_getImageFileById($imageId);
        $product = $this->_getProduct();
        $this->_getMediaGallery()->updateImage($product, $imageFileUri, $data);
        if (isset($data['types']) && is_array($data['types'])) {
            $assignedTypes = $this->_getImageTypesAssignedToProduct($imageFileUri);
            $typesToBeCleared = array_diff($assignedTypes, $data['types']);
            if (count($typesToBeCleared) > 0) {
                $this->_getMediaGallery()->clearMediaAttribute($product, $typesToBeCleared);
            }
            $this->_getMediaGallery()->setMediaAttribute($product, $data['types'], $imageFileUri);
        }
        try {
            $product->save();
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_INTERNAL_ERROR);
        }
    }

    /**
     * Product image add
     *
     * @throws Mage_Api2_Exception
     * @param array $data
     * @return string
     */
    protected function _create(array $data)
    {
        /* @var $validator Mage_Catalog_Model_Api2_Product_Image_Validator_Image */
        $validator = Mage::getModel('catalog/api2_product_image_validator_image');
        if (!$validator->isValidData($data)) {
            foreach ($validator->getErrors() as $error) {
                $this->_error($error, Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
            }
            $this->_critical(self::RESOURCE_DATA_PRE_VALIDATION_ERROR);
        }
        $imageFileContent = @base64_decode($data['file_content'], true);
        if (!$imageFileContent) {
            $this->_critical('The image content must be valid base64 encoded data',
                Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        unset($data['file_content']);

        $apiTempDir = Mage::getBaseDir('var') . DS . 'api' . DS . Mage::getSingleton('api/session')->getSessionId();
        $imageFileName = $this->_getFileName($data);

        try {
            $ioAdapter = new Varien_Io_File();
            $ioAdapter->checkAndCreateFolder($apiTempDir);
            $ioAdapter->open(array('path' => $apiTempDir));
            $ioAdapter->write($imageFileName, $imageFileContent, 0666);
            unset($imageFileContent);

            // try to create Image object to check if image data is valid
            try {
                new Varien_Image($apiTempDir . DS . $imageFileName);
            } catch (Exception $e) {
                $ioAdapter->rmdir($apiTempDir, true);
                $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
            }
            $product = $this->_getProduct();
            $imageFileUri = $this->_getMediaGallery()
                ->addImage($product, $apiTempDir . DS . $imageFileName, null, false, false);
            $ioAdapter->rmdir($apiTempDir, true);
            // updateImage() must be called to add image data that is missing after addImage() call
            $this->_getMediaGallery()->updateImage($product, $imageFileUri, $data);

            if (isset($data['types'])) {
                $this->_getMediaGallery()->setMediaAttribute($product, $data['types'], $imageFileUri);
            }
            $product->save();
            return $this->_getImageLocation($this->_getCreatedImageId($imageFileUri));
        } catch (Mage_Core_Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        } catch (Exception $e) {
            $this->_critical(self::RESOURCE_UNKNOWN_ERROR);
        }
    }

    /**
     * Get added image ID
     *
     * @throws Mage_Api2_Exception
     * @param string $imageFileUri
     * @return int
     */
    protected function _getCreatedImageId($imageFileUri)
    {
        $imageId = null;

        $imageData = Mage::getResourceModel('catalog/product_attribute_backend_media')
            ->loadGallery($this->_getProduct(), $this->_getMediaGallery());
        foreach ($imageData as $image) {
            if ($image['file'] == $imageFileUri) {
                $imageId = $image['value_id'];
                break;
            }
        }
        if (!$imageId) {
            $this->_critical('Unknown error during image save', Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }
        return $imageId;
    }

    /**
     * Retrieve media gallery
     *
     * @throws Mage_Api2_Exception
     * @return Mage_Catalog_Model_Product_Attribute_Backend_Media
     */
    protected function _getMediaGallery()
    {
        $attributes = $this->_getProduct()->getTypeInstance(true)->getSetAttributes($this->_getProduct());

        if (!isset($attributes[self::GALLERY_ATTRIBUTE_CODE])
            || !$attributes[self::GALLERY_ATTRIBUTE_CODE] instanceof Mage_Eav_Model_Entity_Attribute_Abstract
        ) {
            $this->_critical('Requested product does not support images', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        $galleryAttribute = $attributes[self::GALLERY_ATTRIBUTE_CODE];
        /** @var $mediaGallery Mage_Catalog_Model_Product_Attribute_Backend_Media */
        $mediaGallery = $galleryAttribute->getBackend();
        return $mediaGallery;
    }

    /**
     * Create image data representation for API
     *
     * @param array $image
     * @return array
     */
    protected function _formatImageData($image)
    {
        $result = array(
            'id' => $image['value_id'],
            'label' => $image['label'],
            'position' => $image['position'],
            'exclude' => $image['disabled'],
            'url' => $this->_getMediaConfig()->getMediaUrl($image['file']),
            'types' => $this->_getImageTypesAssignedToProduct($image['file'])
        );
        return $result;
    }

    /**
     * Retrieve image types assigned to product (base, small, thumbnail)
     *
     * @param string $imageFile
     * @return array
     */
    protected function _getImageTypesAssignedToProduct($imageFile)
    {
        $types = array();
        foreach ($this->_getProduct()->getMediaAttributes() as $attribute) {
            if ($this->_getProduct()->getData($attribute->getAttributeCode()) == $imageFile) {
                $types[] = $attribute->getAttributeCode();
            }
        }
        return $types;
    }

    /**
     * Retrieve media config
     *
     * @return Mage_Catalog_Model_Product_Media_Config
     */
    protected function _getMediaConfig()
    {
        return Mage::getSingleton('catalog/product_media_config');
    }

    /**
     * Create file name from received data
     *
     * @param array $data
     * @return string
     */
    protected function _getFileName($data)
    {
        $fileName = 'image';
        if (isset($data['file_name']) && $data['file_name']) {
            $fileName = $data['file_name'];
        }
        $fileName .= '.' . $this->_getExtensionByMimeType($data['file_mime_type']);
        return $fileName;
    }

    /**
     * Retrieve file extension using MIME type
     *
     * @throws Mage_Api2_Exception
     * @param string $mimeType
     * @return string
     */
    protected function _getExtensionByMimeType($mimeType)
    {
        if (!array_key_exists($mimeType, $this->_mimeTypes)) {
            $this->_critical('Unsuppoted image MIME type', Mage_Api2_Model_Server::HTTP_BAD_REQUEST);
        }
        return $this->_mimeTypes[$mimeType];
    }

    /**
     * Get file URI by its id. File URI is used by media backend to identify image
     *
     * @throws Mage_Api2_Exception
     * @param int $imageId
     * @return string
     */
    protected function _getImageFileById($imageId)
    {
        $file = null;
        $mediaGalleryData = $this->_getProduct()->getData('media_gallery');
        if (!isset($mediaGalleryData['images'])) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        foreach ($mediaGalleryData['images'] as $image) {
            if ($image['value_id'] == $imageId) {
                $file = $image['file'];
                break;
            }
        }
        if (!($file && $this->_getMediaGallery()->getImage($this->_getProduct(), $file))) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $file;
    }

    /**
     * Get image resource location
     *
     * @param int $imageId
     * @return string URL
     */
    protected function _getImageLocation($imageId)
    {
        /* @var $apiTypeRoute Mage_Api2_Model_Route_ApiType */
        $apiTypeRoute = Mage::getModel('api2/route_apiType');

        $chain = $apiTypeRoute->chain(
            new Zend_Controller_Router_Route($this->getConfig()->getRouteWithEntityTypeAction($this->getResourceType()))
        );
        $params = array(
            'api_type' => $this->getRequest()->getApiType(),
            'id' => $this->getRequest()->getParam('id'),
            'image' => $imageId
        );
        $uri = $chain->assemble($params);
        return '/' . $uri;
    }
}
