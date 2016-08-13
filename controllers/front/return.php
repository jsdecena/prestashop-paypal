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
class PaypalReturnModuleFrontController extends ModuleFrontController
{
    private $url;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->url = Configuration::get('PP_STATUS') == 'live' ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

        parent::initContent();

        /**
         * Execute the payment
         */
        
        if (Tools::getValue('paymentId') && Tools::getValue('PayerID')) {
            
            try {

                $payment = $this->executePayment(Tools::getValue('paymentId'), Tools::getValue('PayerID'));
                $this->guardPayment($payment);

                /**
                 * Process the order
                 */
                
                $this->postProcess();
                
            } catch (Exception $e) {
                
                error_log("\n".$e->getMessage(), 3, _MODULE_DIR_.'paypal/log/paypal_error.log');       
            }
        }

        return Tools::redirect('/');
    }

    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'bankwire')
            {
                $authorized = true;
                break;
            }
        if (!$authorized)
            die($this->module->l('This payment method is not available.', 'validation'));

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
/*        $mailVars = array(
            '{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
            '{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
            '{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
        );*/

        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, NULL, null, (int)$currency->id, false, $customer->secure_key);
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
    }

    private function executePayment($paymentId, $payerID)
    {
        /**
         * Retrieve the token
         */
        $apiContext = $this->createApiContext();

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->url . '/v1/payments/payment/'.$paymentId.'/execute/',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => '{ "payer_id" : "'.$payerID.'" }',
          CURLOPT_HTTPHEADER => array(
            "authorization: Bearer " . $apiContext->access_token,
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

    private function guardPayment($payment)
    {
/*        if ($payment->state != "approved") {
            Tools::redirect('order?step=3&message=' . urlencode("There is a problem with your paypal transaction"));
        }*/
    }
}