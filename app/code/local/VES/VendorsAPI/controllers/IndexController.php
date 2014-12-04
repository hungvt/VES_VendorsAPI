<?php

class VES_VendorsAPI_IndexController extends Mage_Core_Controller_Front_Action
{
    public $base_url;

    protected function _construct()
    {
        $this->base_url = Mage::getBaseUrl();
    }

    public function indexAction()
    {

        //Basic parameters that need to be provided for oAuth authentication
        //on Magento
        $params = array(
            'siteUrl' => $this->base_url . 'oauth',
            'requestTokenUrl' => $this->base_url . 'oauth/initiate',
            'accessTokenUrl' => $this->base_url . 'oauth/token',
            'authorizeUrl' => $this->base_url . 'admin/oAuth_authorize', //This URL is used only if we authenticate as Admin user type
            'consumerKey' => 'bb16ddd6bb39230ba2e6017488366f36', //Consumer key registered in server administration
            'consumerSecret' => '15237448f0b4378b26c81b8c2b23d708', //Consumer secret registered in server administration
            'callbackUrl' => $this->base_url . 'vendorsapi/index/callback', //Url of callback action below
        );

        // Initiate oAuth consumer with above parameters
        $consumer = new Zend_Oauth_Consumer($params);
        // Get request token
        $requestToken = $consumer->getRequestToken();
        // Get session
        $session = Mage::getSingleton('core/session');
        // Save serialized request token object in session for later use
        $session->setRequestToken(serialize($requestToken));
        // Redirect to authorize URL
        $consumer->redirect();

        return;
    }

    public function callbackAction()
    {

        //oAuth parameters
        $params = array(
            'siteUrl' => $this->base_url . 'oauth',
            'requestTokenUrl' => $this->base_url . 'oauth/initiate',
            'accessTokenUrl' => $this->base_url . 'oauth/token',
            'consumerKey' => 'bb16ddd6bb39230ba2e6017488366f36',
            'consumerSecret' => '15237448f0b4378b26c81b8c2b23d708'
        );

        // Get session
        $session = Mage::getSingleton('core/session');
        // Read and unserialize request token from session
        $requestToken = unserialize($session->getRequestToken());
        // Initiate oAuth consumer
        $consumer = new Zend_Oauth_Consumer($params);
        // Using oAuth parameters and request Token we got, get access token
        $acessToken = $consumer->getAccessToken($_GET, $requestToken);
        // Get HTTP client from access token object
        $restClient = $acessToken->getHttpClient($params);
        // Set REST resource URL
        $restClient->setUri($this->base_url . 'api/rest/vendors/cd257f23a80d0665d82a164b5b40ccd7/products');
        // In Magento it is neccesary to set json or xml headers in order to work
        $restClient->setHeaders('Accept', 'application/json');

        $productData = json_encode(array(
            'type_id' => 'simple',
            'attribute_set_id' => 4,
            'sku' => 'simple' . uniqid(),
            'weight' => 1,
            'status' => 1,
            'visibility' => 4,
            'name' => 'Simple Product',
            'description' => 'Simple Description',
            'short_description' => 'Simple Short Description',
            'price' => 99.95,
            'tax_class_id' => 0,
        ));


        // Get method
        $restClient->setMethod(Zend_Http_Client::POST);
        //Make REST request
        $response = $restClient->request();
        // Here we can see that response body contains json list of products
        Zend_Debug::dump($response);

        return;
    }

    public function getAction()
    {
        var_dump(Mage::getModel('vendors/vendor')->load('2')->getData());
    }
}