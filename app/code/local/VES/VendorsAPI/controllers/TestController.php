<?php

class VES_VendorsAPI_TestController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $callbackUrl = "http://localhost/marketplace/vendorsapi/test/index";
        $temporaryCredentialsRequestUrl = "http://localhost/marketplace/oauth/initiate?oauth_callback=" . urlencode($callbackUrl);
        //$adminAuthorizationUrl = 'http://localhost/marketplace/admin/oauth_authorize';
        $adminAuthorizationUrl = 'http://localhost/marketplace/oauth/authorize';
        $accessTokenRequestUrl = 'http://localhost/marketplace/oauth/token';
        $apiUrl = 'http://localhost/marketplace/api/rest';
        $consumerKey = 'bb16ddd6bb39230ba2e6017488366f36';
        $consumerSecret = '15237448f0b4378b26c81b8c2b23d708';

        session_start();
        if (!isset($_GET['oauth_token']) && isset($_SESSION['state']) && $_SESSION['state'] == 1) {
            $_SESSION['state'] = 0;
        }
        try {
            $authType = ($_SESSION['state'] == 2) ? OAUTH_AUTH_TYPE_AUTHORIZATION : OAUTH_AUTH_TYPE_URI;
            $oauthClient = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, $authType);
            $oauthClient->enableDebug();

            if (!isset($_GET['oauth_token']) && !$_SESSION['state']) {
                $requestToken = $oauthClient->getRequestToken($temporaryCredentialsRequestUrl);
                $_SESSION['secret'] = $requestToken['oauth_token_secret'];
                $_SESSION['state'] = 1;
                header('Location: ' . $adminAuthorizationUrl . '?oauth_token=' . $requestToken['oauth_token']);
                exit;
            } else if ($_SESSION['state'] == 1) {
                $oauthClient->setToken($_GET['oauth_token'], $_SESSION['secret']);
                $accessToken = $oauthClient->getAccessToken($accessTokenRequestUrl);
                $_SESSION['state'] = 2;
                $_SESSION['token'] = $accessToken['oauth_token'];
                $_SESSION['secret'] = $accessToken['oauth_token_secret'];
                header('Location: ' . $callbackUrl);
                exit;
            } else {
                $oauthClient->setToken($_SESSION['token'], $_SESSION['secret']);

                $resourceUrl = "$apiUrl/vendors/cd257f23a80d0665d82a164b5b40ccd7/products/253/categories";

                $productData = json_encode(array(
                    'type_id' => 'simple',
                    'approval'=>'2',
                    'attribute_set_id' => 9,
                    'sku' => 'hiep_customer_'.rand(1000,9999),
                    'weight' => 199,
                    'status' => 1,
                    'visibility' => 4,
                    'name' => 'Simple Product 1',
                    'description' => 'Simple Description',
                    'short_description' => 'Simple Short Description',
                    'price' => 99.95,
                    'tax_class_id' => 0,
                    'meta_title'    => 'meta title',
                    'meta_description'    => 'meta_description title',
                    'url_key' => 'url_hiep_test',
                    'country_of_manufacture'    => 'AD',
                    'price' => '222',
                    'special_price'=>'199',
                    'special_from_date'=>'2012-03-15 00:00:00',
                    'stock_data'=>array(
                        'qty'=>'888',
                        'min_qty'=>'5',
                        'is_in_stock'   => '1',
                        'manage_stock'=>'1',
                    ),
                ));

                $cData = json_encode(array('category_id'=>'8'));

                //var_dump($productData);exit;


                $oauthClient->fetch($resourceUrl,$cData, 'POST', array('Content-Type' => 'application/json'));
                $productsList = json_decode($oauthClient->getLastResponse());
                var_dump($productsList);
            }
        } catch (OAuthException $e) {
            print_r($e->getMessage());
            echo "<br/>";
            print_r($e->lastResponse);
        }
    }

    public function getAction() {
        $vendor = Mage::getModel('vendors/vendor')->load('4');
        var_dump($vendor->getData());
    }
}