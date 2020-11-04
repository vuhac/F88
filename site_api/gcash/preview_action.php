<?php
// ----------------------------------------------------------------------------
// Features:  前台 - 現金(GCASH) api preview action
// File Name:	site_api/gcash/preview_action.php
// Author:		Dright
// Related:  對應 site_api/gcash/preview.php
// DB table:  root_site_api_account, root_gcash_order
// Log:
// ----------------------------------------------------------------------------
/*
主要操作的DB表格：
root_site_api_account  site api 帳號
root_gcash_order       gcash api 訂單紀錄

前台
site_api/gateway.php 所有site_api都由這裡進入。
site_api/gcash/preview.php 對應的view

*/
// ----------------------------------------------------------------------------


require_once __DIR__ . '/../../config.php';
require_once __DIR__ ."/../../lib.php";
require_once __DIR__ ."/../../gcash_lib.php";
require_once __DIR__ . '/../lib_api.php';

// redirect to login2page
function login2page() {
  global $config;
  $value_array = [
    'formtype' => 'GET',
    'formurl'  => 'https://' . $config['website_domainname'] . '/site_api/gcash/preview.php',
    'a' => 'confirm',
  ];

  $api_data = $_POST;


  // 產生 token , salt是檢核密碼預設值為123456 ,需要配合 jwtdec 的解碼, 此範例設定為 123456
  $send_code = jwtenc('123456', array_merge($value_array, $api_data));

  $login2page_url = 'https://' . $config['website_domainname'] . '/login2page.php?t=' . $send_code;

  header("Location: " . $login2page_url); /* Redirect browser */
}

// verify request
function verify_request(array $api_data) {

  // get validator to check the existence of attributes
  $validator = generate_validator([
    'service' => 'no service given.',
    'api_account' => 'no api_account given.',
    'order_title' => 'no order_title given.',
    'order_no' => 'no order_no given.',
    'payment_user' => 'no payment_user given.',
    'amount' => 'no amount given.',
    'return_url' => 'no return_url given.',
    'notify_url' => 'no notify_url given.',
    'sign' => 'no sign given.',
    'timestamp' => 'no timestamp given.',
    'withdrawal_password' => 'no withdrawal_password given.',
  ]);

  if(!$validator($api_data)) return false;

  $api_key = get_api_account_key($api_data['api_account']);
  if(empty($api_key)) {
    respond_json(['message' => "this api account doesn't exist."], 406);
    return false;
  }

  return true;
}

function is_order_timeout(array $api_data) {
  $order_create_datetime = date_create_from_format('Y-m-d H:i:s', $api_data['timestamp']);

  $diff_in_seconds = (new DateTime())->getTimestamp() - $order_create_datetime->getTimestamp();

  if($diff_in_seconds > 300) {
    return true;
  }

  return false;
}

function is_gcash_order_exist(array $api_data, $debug = 0) {
  // checkout if order_no is unique
  $gcash_order_check_sql = "SELECT * FROM root_gcash_order WHERE custom_transaction_id = '" . $api_data['order_no'] . "' AND site_account_name = '" . $api_data['api_account'] . "'";
  $gcash_order_check_result = runSQLall($gcash_order_check_sql);
  if($debug == 1) {
    var_dump($gcash_order_check_sql);
    var_dump($gcash_order_check_result);
  }
  if($gcash_order_check_result[0] == 1){
    return true;
  }

  return false;
}

function gen_return_url($url, array $result_data) {
  parse_str(parse_url($url, PHP_URL_QUERY), $query_data);

  $result_query_string = http_build_query($result_data);

  if(empty($query_data)) {
    return $url . '?' . $result_query_string;
  }

  return $url . '&' . $result_query_string;
}

// do transaction
function do_gcash_transaction($api_data, $api_key, $withdrawal_password) {

  // $api_data = [
  //   'service' => 'gcash',                 required
  //   'api_account' => 'ec_test',           required
  //   'order_title' => 'test',              required
  //   'order_no' => 'ec_11111',             required
  //   'payment_user' => 'jjj',              required
  //   'amount' => '1',                      required
  //   'description' => 'just for testing',
  //   'return_url' => '',                   required
  //   'notify_url' => '',                   required
  //   'sign' => '',                         required
  //   'timestamp' => '',
  // ];

  // 取得 microtime
  list($usec, $sec) = explode(" ", microtime());
  $microtime_float = ((float)$usec + (float)$sec);

  // 訂單使用, 小數點四位拉出來到整數.
  $microtime_stamp = round($microtime_float, 4) * 10000;

  $transaction_id = $api_data['api_account'] . 'at' . $microtime_stamp;

  $gcash_order_data = [
    'transaction_id' => $transaction_id,
    'customer_transaction_id' => $api_data['order_no'],
    'account' => $api_data['payment_user'],
    'site_account_name' => $api_data['api_account'],
    'amount' => $api_data['amount'],
    'title' => $api_data['order_title'],
    'notes' => $api_data['description'],
    'return_url' => $api_data['return_url'],
    'notify_url' => $api_data['notify_url'],
  ];

  $error = apicashwithdrawal($api_data['payment_user'], $api_data['amount'], $transaction_id, $withdrawal_password);

  if($error['code'] == 3) {
    $notify_data = [
      'trade_status' => 'fail',
      'order_no'       => $api_data['order_no'],
      'transaction_id' => $transaction_id,
      'payment_user'   => $api_data['payment_user'],
      'amount'         => $api_data['amount'],
      'order_title'    => $api_data['order_title'],
      'description'    => $api_data['description'],
    ];

    $notify_data['sign'] = generate_sign($notify_data, $api_key);

    // use curl to post to notify_url
    $ch = curl_init();
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $api_data['notify_url']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  true);
    curl_setopt($ch, CURLOPT_CAINFO,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify_data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $result = curl_exec($ch);

    respond_json([
      'url' => gen_return_url($api_data['return_url'], ['status' => 'fail', 'message' => '帳號餘額不足。']),
      // 'info' => curl_getinfo($ch),
      // 'error' => curl_error($ch)
    ]);
    curl_close ($ch);
    return;
  }

  if($error['code'] != 1) {
    respond_json(['message' => $error['messages']], 406);
    return;
  }

  // gcash order成立
  if( create_gcash_order((object)$gcash_order_data) ) {
    $notify_data = [
      'trade_status' => 'success',
      'order_no'       => $api_data['order_no'],
      'transaction_id' => $transaction_id,
      'payment_user'   => $api_data['payment_user'],
      'amount'         => $api_data['amount'],
      'order_title'    => $api_data['order_title'],
      'description'    => $api_data['description'],
    ];

    $notify_data['sign'] = generate_sign($notify_data, $api_key);

    // use curl to post to notify_url
    $ch = curl_init();
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $api_data['notify_url']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  true);
    curl_setopt($ch, CURLOPT_CAINFO,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify_data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  	$result = curl_exec($ch);

    respond_json([
      'url' => gen_return_url($api_data['return_url'], ['status' => 'success', 'message' => '付款成功']),
      // 'info' => curl_getinfo($ch),
      // 'error' => curl_error($ch)
    ]);
    curl_close ($ch);
    return;
  }

  respond_json(['message' => "gcash_order create failed."], 500);
  return;

}



// Main

// only allow post request
if($_SERVER['REQUEST_METHOD'] != 'POST') {
  http_response_code(405);
  return;
}

// actions: confirm, preview, transaction
$action = 'transaction';

if(isset($_GET['a'])) {
  $action = $_GET['a'];
}

switch($action) {
  case 'preview':
    login2page();
    return;

  default:
    // verify request and do transaction

    // $api_data = [
    //   'service' => 'gcash',                 required
    //   'api_account' => 'ec_test',           required
    //   'order_title' => 'test',              required
    //   'order_no' => 'ec_11111',             required
    //   'payment_user' => 'jjj',              required
    //   'amount' => '1',                      required
    //   'description' => 'just for testing',
    //   'return_url' => '',                   required
    //   'notify_url' => '',                   required
    //   'sign' => '',                         required
    //   'timestamp' => '',
    // ];
    // var_dump($_POST);
    $api_data = $_POST;

    if(!verify_request($api_data)) {
      return;
    }

    // 取款密码
    $withdrawal_password = $api_data['withdrawal_password'];
    unset($api_data['withdrawal_password']);

    $api_key = get_api_account_key($api_data['api_account']);
    if(empty($api_key)) {
      respond_json(['message' => "this api account doesn't exist."], 406);
      return;
    }

    // verify sign
    if(!check_sign($api_data, $api_key)) {
      respond_json(['message' => 'sign validation failed.'], 406);
      return;
    }

    if(is_order_timeout($api_data)) {
      $notify_data = [
        'trade_status' => 'fail',
        'order_no'       => $api_data['order_no'],
        'payment_user'   => $api_data['payment_user'],
        'amount'         => $api_data['amount'],
        'order_title'    => $api_data['order_title'],
        'description'    => $api_data['description'],
      ];

      $notify_data['sign'] = generate_sign($notify_data, $api_key);

      // use curl to post to notify_url
      $ch = curl_init();
      $headers = ['Accept: application/json', 'Content-Type: application/json'];
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_URL, $api_data['notify_url']);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  true);
      curl_setopt($ch, CURLOPT_CAINFO,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify_data));
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      $result = curl_exec($ch);

      curl_close($ch);

      respond_json([
        'url' => gen_return_url($api_data['return_url'], ['status' => 'fail', 'message' => 'gcash 訂單逾時']),
      ]);

      return;
    }

    if(is_gcash_order_exist($api_data)) {
      $notify_data = [
        'trade_status' => 'finished',
        'order_no'       => $api_data['order_no'],
        'payment_user'   => $api_data['payment_user'],
        'amount'         => $api_data['amount'],
        'order_title'    => $api_data['order_title'],
        'description'    => $api_data['description'],
      ];

      $notify_data['sign'] = generate_sign($notify_data, $api_key);

      // use curl to post to notify_url
      $ch = curl_init();
      $headers = ['Accept: application/json', 'Content-Type: application/json'];
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_URL, $api_data['notify_url']);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  'POST');
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  true);
      curl_setopt($ch, CURLOPT_CAINFO,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify_data));
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
      $result = curl_exec($ch);

      curl_close($ch);

      respond_json([
        'url' => gen_return_url($api_data['return_url'], ['status' => 'finished', 'message' => 'gcash 訂單已存在']),
      ]);

      return;
    }

    do_gcash_transaction($api_data, $api_key, $withdrawal_password);
}

?>
