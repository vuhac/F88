<?php
// ----------------------------------------------------------------------------
// Features:  後台-- 線上支付看板
// File Name: depositing_siteapi_audit_action.php
// Author:    orange
// Related:   前台無對應
// Log:       2017.8.29 update
// ----------------------------------------------------------------------------
//處理查快速入款訂單功能

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
require_once __DIR__ . "/site_api/lib_http.php";
require_once __DIR__ . "/site_api/lib_api.php";
require_once __DIR__ . '/protal_setting_lib.php';
require_once __DIR__ . '/Utils/RabbitMQ/Publish.php';
require_once __DIR__ . '/Utils/MessageTransform.php';

use Jutainet\Http\Controller;
use Jutainet\Http\Request;
use Jutainet\Http\Response;
use Onlinepay\SDK\PaymentGateway;

Agent_RegSession2RedisDB();
agent_permission();

class OnlinepayAuditController extends Controller {
  public function getOrders(Request $request, Response $response) {

  }

  public function getApiQueryOrder(Request $request, Response $responseObject) {
    global $tr;

    $api_query = $request->getParam('api_query');
    try {
      $onlinepayGateway = new PaymentGateway($this->config['gpk2_pay']);
      $response = $onlinepayGateway->getTxDetail($api_query);
      if ($response->status->code != 0) {
        throw new \Exception(
          "Api error: " . json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
      }
    } catch (\Exception $e) {
      die("(x){$tr['error, please contact the developer for processing.']}Err: <hr><pre>" . $e->getMessage() . "</pre>");
    }

    $dollars = "$";
    $status = function ($key, $with_format = true) use ($tr) {
      $status = [
        null => $tr['Find failure'],
        0 => $tr['pending'],
        1 => $tr['deposits'] . $tr['completed'],
        2 => $tr['failure to deposit'],
        3 => $tr['deposits'] . $tr['Processing'],
        4 => $tr['expired'],
        5 => $tr['processed expired'],
      ];

      if ($with_format) {
        $status = [
          null => '<span class="label badge-warning">' . $tr['Find failure'] . '</span>',
          0 => '<span class="label badge-danger">' . $tr['pending'] . '</span>',
          1 => '<span class="label badge-success">' . $tr['completed'] . '</span>',
          2 => '<span class="label badge-danger">' . $tr['failure to deposit'] . '</span>',
          3 => '<span class="label badge-warning">' . $tr['Processing'] . '</span>',
          4 => '<span class="label badge-danger">' . $tr['expired'] . '</span>',
          5 => '<span class="label badge-danger">' . $tr['processed expired'] . '</span>',
        ];
      }

      return $status[$key];
    };

    $debug_code = $response->status->extra_message ?? '';

    $platform_msg = "{$tr['platform']}{$status($response->data->platform_status, false)}";
    $payment_msg = "{$tr['payer']}{$status($response->data->payment_status, false)}";
    $platform_color = 'danger';
    $payment_color = 'warning';

    if ($response->data->payment_status == 1) {
      $payment_msg = "{$tr['payer']}{$status($response->data->payment_status, false)}";
      $payment_color = 'success';
    }

    $html = <<<HTML
      <div class="alert alert-{$payment_color}">$payment_msg; {$debug_code}</div> <!-- 金流商查訂單說明 -->
      <div><label>{$tr['deposit number']}：</label>{$response->data->order_no}</div>
      <div><label>{$tr['member']}：</label>{$response->data->account}</div>
      <div><label>{$tr['amount']}：</label>{$dollars}{$response->data->amount}</div>
      <div><label>{$tr['cash flow name']}：</label>{$response->data->payment}</div>
      <div><label>{$tr['cash flow deposit status']}：</label>{$status($response->data->payment_status)}</div>
      <div><label>{$tr['provider order number']}：</label>{$response->data->payment_order_no}({$response->data->payment})</div>
      <div><label>{$tr['count of notifications']}：</label>{$response->data->platform_status}</div>
      <div><label>{$tr['description']}：</label>{$response->data->description}</div>
      <div class="d-none"><label>debug：</label>{$debug_code}</div>
    HTML;
    echo $html;
  }

  public function postReviewOrder(Request $request, Response $response) {
    $id = $request->getParam('id');
    $action = $request->getParam('action');
    $order = ApiDepositOrder::find($id);

    if (!$order->isArchived()) {
      $order->reviewOrder($action);

      $rabmq = Publish::getInstance();
      $rabmsg = MessageTransform::getInstance();
      $nowsec = date('Y-m-d H:i:s');
      $notifyMsg = $rabmsg->notifyMsg('OnlinePay-Passed', $order->account, $nowsec);
      $notifyResult = $rabmq->fanoutNotify('msg_notify', $notifyMsg);
      // $notifyResult = $rabmq->directNotify('direct_test', 'direct_test', $notifyMsg);

      return $response->withJson(['data' => $order->toArray(), 'rabmq' => $notifyResult]);
    }

    return $response->withJson(['data' => $order->toArray()], 400);
  }
}

Controller::dispatch('onlinepayAudit');
return;
