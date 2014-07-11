<?php
/**
 * Veritrans VT Web Payment Controller
 *
 * @category   Mage
 * @package    Mage_Veritrans_VtWeb_PaymentController
 * @author     Kisman Hong (plihplih.com), Ismail Faruqi (@ifaruqi_jpn)
 * This class is used for handle redirection after placing order.
 * function redirectAction -> redirecting to Veritrans VT Web
 * function responseAction -> when payment at Veritrans VT Web is completed or failed, the page will be redirected to this function,
 * you must set this url in your Veritrans MAP merchant account. http://yoursite.com/vtweb/payment/notification
 */

require_once(Mage::getBaseDir('lib') . '/veritrans-php/Veritrans.php');

class Veritrans_Vtweb_PaymentController extends Mage_Core_Controller_Front_Action {

  /**
   * @return Mage_Checkout_Model_Session
   */
  protected function _getCheckout() {
    return Mage::getSingleton('checkout/session');
  }

  // The redirect action is triggered when someone places an order, redirecting to Veritrans payment page.
  public function redirectAction() {
    $orderIncrementId = $this->_getCheckout()->getLastRealOrderId();
    $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
    $sessionId = Mage::getSingleton("core/session");

    /* send an order email when redirecting to payment page although payment has not been completed. */
    $order->setState(Mage::getStoreConfig('payment/vtweb/'), true, 'New order, waiting for payment.');
    $order->sendNewOrderEmail();
    $order->setEmailSent(true);

    $api_version = Mage::getStoreConfig('payment/vtweb/api_version');
    $payment_type = Mage::getStoreConfig('payment/vtweb/payment_types');

    Veritrans_Config::$isProduction =
        Mage::getStoreConfig('payment/vtweb/environment') == 'production'
        ? true : false;
    // $veritrans->environment = (Mage::getStoreConfig('payment/vtweb/environment') == 'production' ? Veritrans::ENVIRONMENT_PRODUCTION : Veritrans::ENVIRONMENT_DEVELOPMENT);

    Veritrans_Config::$serverKey =
        Mage::getStoreConfig('payment/vtweb/server_key_v2');
    // $veritrans->client_key = Mage::getStoreConfig('payment/vtweb/client_key_v2');
    // $veritrans->server_key = Mage::getStoreConfig('payment/vtweb/server_key_v2');

    Veritrans_Config::$is3ds =
        Mage::getStoreConfig('payment/vtweb/enable_3d_secure') == '1'
        ? true : false;

    // TODO
    Veritrans_Config::$isSanitized = false;

    $transaction_details = array();
    $transaction_details['order_id'] = $orderIncrementId;
    $transaction_details['gross_amount'] = (int) $order->getBaseGrandTotal();
    // $veritrans->order_id = $orderIncrementId;
    // $veritrans->gross_amount = (int)$order->getBaseGrandTotal(); // no need to set the gross amount in the new library

    $order_billing_address = $order->getBillingAddress();
    $billing_address = array();
    $billing_address['first_name']   = $order_billing_address->getFirstname();
    $billing_address['last_name']    = $order_billing_address->getLastname();
    $billing_address['address']      = $order_billing_address->getStreet(1);
    $billing_address['city']         = $order_billing_address->getCity();
    $billing_address['postal_code']  = $order_billing_address->getPostcode();
    $billing_address['country_code'] = 'IDN';
    $billing_address['phone']        = $order_billing_address->getTelephone();

    // $veritrans->first_name = $order->getBillingAddress()->getFirstname();
    // $veritrans->last_name = $order->getBillingAddress()->getLastname();
    // $veritrans->email = $order->getBillingAddress()->getEmail();
    // $veritrans->address1 = $order->getBillingAddress()->getStreet(1);
    // $veritrans->address2 = $order->getBillingAddress()->getStreet(2);
    // $veritrans->city = $order->getBillingAddress()->getCity();
    // $veritrans->country_code = $order->getBillingAddress()->getCountry(); // this is hard coded because magento and veritrans country code is not the same.
    // $veritrans->postal_code = $order->getBillingAddress()->getPostcode();
    // $veritrans->phone = $order->getBillingAddress()->getTelephone();

    $order_shipping_address = $order->getShippingAddress();
    $shipping_address = array();
    $shipping_address['first_name']   = $order_shipping_address->getFirstname();
    $shipping_address['last_name']    = $order_shipping_address->getLastname();
    $shipping_address['address']      = $order_shipping_address->getStreet(1);
    $shipping_address['city']         = $order_shipping_address->getCity();
    $shipping_address['postal_code']  = $order_shipping_address->getPostcode();
    $shipping_address['phone']        = $order_shipping_address->getTelephone();
    $shipping_address['country_code'] = 'IDN';

    // $veritrans->shipping_first_name = $order->getShippingAddress()->getFirstname();
    // $veritrans->shipping_last_name = $order->getShippingAddress()->getLastname();
    // $veritrans->shipping_address1 = $order->getShippingAddress()->getStreet(1);
    // $veritrans->shipping_address2 = $order->getShippingAddress()->getStreet(2);
    // $veritrans->shipping_city = $order->getShippingAddress()->getCity();
    // $veritrans->shipping_country_code = $order->getShippingAddress()->getCountry(); // this is hard coded because magento and veritrans country code is not the same.
    // $veritrans->shipping_postal_code = $order->getShippingAddress()->getPostcode();
    // $veritrans->shipping_phone = $order->getShippingAddress()->getTelephone();
    // $veritrans->enable_3d_secure = Mage::getStoreConfig('payment/vtweb/enable_3d_secure');

    $customer_details = array();
    $customer_details['billing_address']  = $billing_address;
    $customer_details['shipping_address'] = $shipping_address;
    $customer_details['first_name']       = $order_billing_address
        ->getFirstname();
    $customer_details['last_name']        = $order_billing_address
        ->getLastname();
    $customer_details['email']            = $order_billing_address->getEmail();
    $customer_details['phone']            = $order_billing_address
        ->getTelephone();

    // $bank = Mage::helper('vtweb/data')->_getInstallmentBank();
    // $veritrans->installment_banks = array($bank);
    // $terms = explode(',', Mage::helper('vtweb/data')->_getInstallmentTerms());
    // $veritrans->installment_terms = json_encode(array($bank => $terms));

    $items               = $order->getAllItems();
    $shipping_amount     = (int) $order->getShippingAmount();
    $shipping_tax_amount = (int) $order->getShippingTaxAmount();

    $item_details = array();
    foreach ($items as $each) {
      $item = array(
          'id'       => $each->getProductId(),
          'price'    => (int) $each->getPrice(),
          'quantity' => $each->getQtyToInvoice(),
          'name'     => $each->getName()
        );
      $item_details[] = $item;
    }

    // $commodities =  array();
    // foreach ($items as $itemId => $item)
    // {
    //   array_push($commodities,
    //     array(
    //       "item_id" => $item->getProductId(),
    //       "price" => (int)$item->getPrice(),
    //       "quantity" => $item->getQtyToInvoice(),
    //       "item_name1" => $item->getName(),
    //       "item_name2" => $item->getName(),
    //       ));
    // }

    if ($shipping_amount > 0) {
      $shipping_item = array(
          'id' => 'SHIPPING',
          'price' => $shipping_amount,
          'quantity' => 1,
          'name' => 'Shipping Cost'
        );
      $item_details[] =$shipping_item;

      // array_push($commodities,
      //   array(
      //     "item_id" => 'SHIPPING',
      //     "price" => $shipping_amount,
      //     "quantity" => 1,
      //     "item_name1" => 'Shipping Cost',
      //     "item_name2" => 'Shipping Cost',
      //     ));
    }

    if ($shipping_tax_amount > 0) {
      $shipping_tax_item = array(
          'id' => 'SHIPPING_TAX',
          'price' => $shipping_tax_amount,
          'quantity' => 1,
          'name' => 'Shipping Tax'
        );
      $item_details[] = $shipping_tax_item;
      // array_push($commodities,
      //   array(
      //     "item_id" => 'SHIPPING_TAX',
      //     "price" => $shipping_tax_amount,
      //     "quantity" => 1,
      //     "item_name1" => 'Shipping Tax',
      //     "item_name2" => 'Shipping Tax',
      //     ));
    }

    // convert to IDR
    $current_currency = Mage::app()->getStore()->getCurrentCurrencyCode();
    if ($current_currency != 'IDR') {
      $idr_exists =
          in_array('IDR', Mage::app()->getStore()->getAvailableCurrencyCodes());
      if ($idr_exists) {
        // attempt to use the built-in currency converter
        $conversion_func = function ($non_idr_price) use ($current_currency) {
            return Mage::helper('directory')
                ->currencyConvert($non_idr_price, $current_currency, 'IDR');
          };
      }
      else {
        $conversion_func = function ($non_idr_price) {
            return $non_idr_price *
                Mage::getStoreConfig('payment/vtweb/conversion_rate');
          };
      }
      foreach ($item_details as &$item) {
        $item['price'] =
            intval(round(call_user_func($conversion_func, $item['price'])));
      }
    }

    $payloads = array();
    $payloads['transaction_details'] = $transaction_details;
    $payloads['item_details']        = $item_details;
    $payloads['customer_details']    = $customer_details;

    try {
      // TODO enabled payments

      $redirUrl = Veritrans_VtWeb::getRedirectionUrl($payloads);
      $this->_redirectUrl($redirUrl);
    }
    catch (Exception $e) {
    }

    // $veritrans->items = $commodities;
    // $keys = $veritrans->getTokens();

    // if ($veritrans->api_version == 2)
    // {
    //   if ($payment_type == 'vtdirect')
    //   {

    //   } else
    //   {
    //     // vtweb
    //     if ($keys && $keys['status_code'] == 201)
    //     {
    //       $this->_redirectUrl($keys['redirect_url']);
    //     } else
    //     {
    //       var_dump($keys);
    //       exit;
    //     }
    //   }
    // } else
    // {
    //   if ($payment_type == 'vtdirect')
    //   {

    //   } else
    //   {
    //     $payment = $order->getPayment();
    //     $payment->setTokenMerchant($keys['token_merchant'])->save();

    //     $this->loadLayout();
    //     $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','vtweb',array('template' => 'vtweb/redirect.phtml'));
    //     $block->setData('token_browser', $keys['token_browser']);
    //     $block->setData('merchant_id', $veritrans->merchant_id);
    //     $block->setData('redirect_url', Veritrans::PAYMENT_REDIRECT_URL);
    //     $this->getLayout()->getBlock('content')->append($block);
    //     $this->getResponse()->setBody($block->toHtml());
    //     //$this->renderLayout();
    //   }

    // }

  }

  // The response action is triggered when your gateway sends back a response after processing the customer's payment, we will not update to success because success is valid when notification (security reason)
  public function responseAction() {
    //var_dump($_POST); use for debugging value.
    if($this->getRequest()->isPost()) {
      $orderId = $_POST['orderId']; // Generally sent by gateway
      $status = $_POST['mStatus'];
      if($status == 'success' && !is_null($orderId) && $orderId != '') {
        // Redirected by Veritrans, if ok
        Mage::getSingleton('checkout/session')->unsQuoteId();
        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
      }
      else {
        // There is a problem in the response we got
        $this->cancelAction();
        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure'=>true));
      }
    }
    else{
      Mage_Core_Controller_Varien_Action::_redirect('');
    }
  }

  // Veritrans will send notification of the payment status, this is only way we make sure that the payment is successed, if success send the item(s) to customer :p
  public function notificationAction() {
    error_log('payment notification');

    Veritrans_Config::$serverKey =
        Mage::getStoreConfig('payment/vtweb/server_key_v2');
    $notif = new Veritrans_Notification();

    if ($notif->isVerified()) {
      error_log('verified');

      $order = Mage::getModel('sales/order');
      $order->loadByIncrementId($notif->order_id);

      $transaction = $notif->transaction_status;
      $fraud = $notif->fraud_status;

      $logs = '';

      if ($transaction == 'capture') {
        $logs .= 'capture ';
        if ($fraud == 'challenge') {
          $logs .= 'challenge ';
          $order->setStatus('fraud');
        }
        else if ($fraud == 'accept') {
          $logs .= 'accept ';
          $invoice = $order->prepareInvoice()
            ->setTransactionId($order->getId())
            ->addComment('Payment successfully processed by Veritrans.')
            ->register()
            ->pay();

          $transaction_save = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

          $transaction_save->save();

          $order->setStatus('processing');
          $order->sendOrderUpdateEmail(true,
              'Thank you, your payment is successfully processed.');
        }
      }
      else if ($transaction == 'cancel') {
        $logs .= 'cancel ';
        if ($fraud == 'challenge') {
          $logs .= 'challenge ';
          $order->setStatus('canceled');
        }
        else if ($fraud == 'accept') {
          $logs .= 'accept ';
          $order->setStatus('canceled');
        }
      }
      else if ($transaction == 'deny') {
        $logs .= 'deny ';
        $order->setStatus('canceled');
      }
      else {
        $logs .= "*$transaction:$fraud ";
        $order->setStatus('fraud');
      }

      $order->save();

      error_log($logs);
    }

    // $notification = new VeritransNotification();

    // if (Mage::getStoreConfig('payment/vtweb/api_version') == 2)
    // {
    //   $order_id = $notification->order_id;
    //   $veritrans = new Veritrans();
    //   $veritrans->server_key = Mage::getStoreConfig('payment/vtweb/server_key_v2');
    //   $confirmation = $veritrans->confirm($order_id);

    //   if ($confirmation && $confirmation['status_code'] && $confirmation['status_code'] == 200)
    //   {
    //     $order = Mage::getModel('sales/order');
    //     $order->loadByIncrementId($order_id);
    //     if ($confirmation['transaction_status'] == 'capture')
    //     {
    //       // create proper invoice
    //       $invoice = $order->prepareInvoice()
    //         ->setTransactionId($order->getId())
    //         ->addComment('Payment successfully processed by Veritrans.')
    //         ->register()
    //         ->pay();

    //       $transaction_save = Mage::getModel('core/resource_transaction')
    //         ->addObject($invoice)
    //         ->addObject($invoice->getOrder());

    //       $transaction_save->save();

    //       $order->setStatus('processing');
    //       $order->sendOrderUpdateEmail(true, 'Thank you, your payment is successfully processed.');
    //     } elseif ($confirmation['transaction_status'] == 'challenge')
    //     {
    //       $order->setStatus('fraud');
    //     } elseif ($confirmation['transaction_status'] == 'deny')
    //     {
    //       $order->setStatus('canceled');
    //     }
    //     $order->save();
    //   } else
    //   {
    //     echo 'Confirmation failed!';
    //     var_dump($confirmation);
    //   }

    // }
  }

  // The cancel action is triggered when an order is to be cancelled
  public function cancelAction() {
    if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
        $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
        if($order->getId()) {
      // Flag the order as 'cancelled' and save it
            $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
        }
    }
  }
}

?>
