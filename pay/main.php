<?php

  require_once __DIR__ . '/common/lib_deposition.php';

  /**
   *
   */
  interface IPaymentGateway
  {
    /**
     * [genSign description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function genSign(array $data);

    /**
     * [verifySign description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function verifySign($data);

    /**
     * [sendPayment description]
     * @param  [type] $data [description]
     * @return [type]       [description]
     */
    public function sendPayment($data);

    public function notifyHandler($data);

    public function checkPaymentStatus($data);
  }


  /**
   *
   */
  class PaymentGatewayFactory
  {
    /**
     * [getPaymentGateway description]
     * @param  [type] $paymentGatewayName [description]
     * @param  array  $config             [from root_deposit_onlinepayment table] => [payname, name, hashiv, hashkey, merchantid, merchantname, pay_channel]
     * @return [IPaymentGateway]          [description]
     */
    static function getPaymentGateway($paymentGatewayName, array $config = [])
    {
      require_once __DIR__ . '/' . $paymentGatewayName . '/' . 'PaymentGateway.php';

      $class_name = 'Payment\\' . $paymentGatewayName . '\PaymentGateway';

      return new $class_name($config);
    }

  }


?>
