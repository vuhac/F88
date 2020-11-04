<?php
// ----------------------------------------------------------------------------
// Features:  所有site_api都由這裡進入
// File Name:	site_api/gateway.php
// Author:		Dright
// Modifier: Damocles
// Related:
// DB table:  root_site_api_account, root_gcash_order
// Log:
// ----------------------------------------------------------------------------
/*
主要操作的DB表格：
root_site_api_account  site api 帳號
root_gcash_order       gcash api 訂單紀錄

前台
site_api/gateway.php 所有site_api都由這裡進入。
site_api/gcash/preview.php 對應的action
site_api/gcash/preview_action.php 對應的action


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

site api 付款流程
Step 1
gateway.php 驗證site account跟權限
跳轉到service對應的preview.php

Step 2
preview.php => preview_action.php
跳轉到login2page.php驗證使用者登入

Step 3
login2page.php => preview.php
驗證使用者身分 and 填取款密碼

Step 4
preview_action.php
執行service動作(ex: gcash扣款)

*/
// ----------------------------------------------------------------------------

// 主機及資料庫設定
require_once __DIR__ ."/../config.php";

// 自訂函式庫
require_once __DIR__ ."/../lib.php";

// site_api常用函式庫
require_once __DIR__ . '/lib_api.php';


// Main
$api_key = '';
$action_list = ['deposit', 'deposit_query', 'deposit_reserve'];

$action = filter_input(INPUT_GET, 'a') ?? 'withdraw';
if( in_array($action, $action_list) ){
    $api_data = json_decode( file_get_contents('php://input'), true );
}
else{
    $api_data = $_POST;
}

// debug mode
if( isset($_GET['debug_token']) && ($_GET['debug_token'] == 'j6bp61i6') ){
    $api_data = [
        'service' => 'gcash',
        'api_account' => 'yyZE89',
        'order_title' => 'test',
        'order_no' => ( 'ec_' . time() ),
        'payment_user' => 'jjj',
        'amount' => '1',
        'description' => 'just for testing',
        'return_url' => 'http://google.com?test=6',
        'notify_url' => 'http://google.com?test=6',
        'sign' => '',
        'timestamp' => '',
        'order_detail_url' => 'https://jutainetwebb.jutainet.com/ectest/index.php?route=checkout/cart'
    ];

  $api_key = get_api_account_key($api_data['api_account'], true);
  $api_data['sign'] = generate_sign($api_data, $api_key);
}
else if( isset($_GET['debug_token']) && ($_GET['debug_token'] == 'xj6m4jo6') ){
    // 要有平台訂單號，訂單描述，平台商戶號，金額，簽名，交易結果，交易結果描述，服務訂單號

    /**
     * pay_dev, EBD61EA8F77813129A81100B4B473014F34470D8
     */
    $api_data = [
        'api_account' => 'paydev',
        'account' => 'webbdemo',
        'amount' => '1',
        'description' => 'just for testing; money, money, and money',
        'service' => 'gcash',
        // 'sign' => '676D61663196585836BEB64AF242A97613DAF314',
        'transaction_order_id' => 'service_20180329'
        // 'order_detail_url' => 'https://jutainetwebb.jutainet.com/ectest/index.php?route=checkout/cart'
    ];

    $api_key = get_api_account_key($api_data['api_account']);
    $api_data['sign'] = generate_sign($api_data, $api_key);
    // var_dump($api_data);  die;
}

// get validator to check the existence of attributes
$validate_request = generate_validator([
    // 'api_account' => 'no api_account given.',
    'sign' => 'no sign given.',
    'service' => 'no service given.'
]);
// var_dump($validate_request); exit();
// var_dump($validate_request($api_data)); exit();

// validate request
if( !$validate_request($api_data) ) {
  return;
}

$api_key = $config['gpk2_pay']['apiKey'];

if( !isset($api_key) || empty($api_key) ) {
    respond_json(['message' => "this api account doesn't exist."], 406);
    return;
}

// verify sign
if( !check_sign($api_data, $api_key) ) {
    respond_json(['message' => 'sign validation failed.'], 406);
    return;
}

// Add timestamp to api_data
$api_data['timestamp'] = date('Y-m-d H:i:s');

// Update sign
$api_data['sign'] = generate_sign($api_data, $api_key);

// prepare for redirection
// $service = $api_data['service'];
$token = $send_code = jwtenc('123456', $api_data);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || getenv('HTTP_X_FORWARDED_PROTO') === 'https') ? "https://" : "http://";

// redirect to corresponding service
switch ($action):
    case 'deposit':
        $api_url = $protocol . $config['website_domainname'] . '/site_api/gtoken/service.php?t=' . $token . '&a=deposit';
    break;

    case 'deposit_query':
        $api_url = $protocol . $config['website_domainname'] . '/site_api/gtoken/service.php?t=' . $token . '&a=checkout';
    break;

    case 'deposit_reserve':
        $api_url = $protocol . $config['website_domainname'] . '/site_api/gtoken/service.php?t=' . $token . '&a=reserve';
    break;

    case 'withdraw':
        $api_url = $protocol . $config['website_domainname'] . '/site_api/gtoken/preview.php?t=' . $token;
    break;

    default:
        $api_url = $protocol . $config['website_domainname'];
    break;
endswitch;

// die( $api_url );
header("Location: " . $api_url); /* Redirect browser */

// passed all validation, return preview url
// respond_json(['url' => $api_url,]);
return;

?>
