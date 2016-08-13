<?php

/*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
* @since 1.5.0
*/
class PaypalProcessModuleFrontController extends ModuleFrontController
{
    private $url;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->url = (Configuration::get('PP_STATUS') == 'live' ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com');

        parent::initContent();
        
        $apiContext = $this->createApiContext();

        $data = $this->getTransactionData();

        $transaction = $this->makePayment($data, $apiContext->access_token);

        /**
         * Get the approval_url and redirect the user to the link 
         * for approval
         */
        
        foreach ($transaction->links as $key => $link) {
            if ($link->rel == 'approval_url') {
                Tools::redirect($link->href);
            }
        }
    }

    private function createApiContext()
    {
        $clientID = Configuration::get('PP_CLIENT_ID');
        $clientSecret = Configuration::get('PP_SECRET');
        
        /**
         * Create a paypal apiContext
         */

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->url . '/v1/oauth2/token',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "grant_type=client_credentials",
          CURLOPT_USERPWD => "$clientID:$clientSecret",
          CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            die('Ooops, we are having problem with your connection.');
        }

        return json_decode($response);
    }

    private function getTransactionData()
    {
        /**
         * Get the post data
         */

        return '
            {
              "intent":"sale",
              "redirect_urls":{
                "return_url":"'.$this->context->link->getModuleLink('paypal', 'return').'",
                "cancel_url":"'.$this->context->link->getModuleLink('paypal', 'cancel').'"
              },
              "payer":{
                "payment_method":"paypal"
              },
              "transactions":[
                {
                  "amount":{
                    "total":"'.Tools::getValue('total').'",
                    "currency":"'.Tools::getValue('currency').'"
                  }
                }
              ]
            }        
        ';
    }

    private function makePayment($data, $token)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->url . '/v1/payments/payment',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $data,
          CURLOPT_HTTPHEADER => array(
            "authorization: Bearer " . $token,
            "cache-control: no-cache",
            "content-type: application/json"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            die("cURL Error #:" . $err);
        }

        return json_decode($response);
    }
}