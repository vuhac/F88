<?php
// ----------------------------------------------------------------------------
// Features:
// File Name:
// Author:		Webb
// Related:
// DB table:  root_site_api_account, root_gcash_order
// Log:
//       2020/04/11 abandoned
//       統一由 gtoken/service 處理通知

// ----------------------------------------------------------------------------
/*
主要操作的DB表格：
root_site_api_account  site api 帳號
root_gcash_order       gcash api 訂單紀錄

前台


*/
// ----------------------------------------------------------------------------

exit('This file is abandoned! Check ../gateway.php;');

// 主機及資料庫設定
require_once __DIR__ . '/../../config.php';
// 自訂函式庫
require_once __DIR__ ."/../../lib.php";
// 支援多國語系
require_once __DIR__ ."/../../i18n/language.php";
require_once __DIR__ . '/../lib_api.php';

// 取得入款對象的帳戶資訊
function get_payee_info($account) {
  $acc_sql     = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = :account AND root_member.status = '1';";
  $acc_result  = runSQLall_prepared($acc_sql, ['account' => $account]);
  return $acc_result;
}

function check_api_data($api_data) {
  $key = $config['gpk2_pay']['apiKey'];
  return check_sign(get_object_vars($api_data), $key);
}

// 負責 json response 與訊息描述
function service_response($record) {
  $result_case = [
    0 => '存款请求已接受，api 存款记录为待存款',
    1 => "单号 $record->custom_transaction_id 透过站台接口存款成功",
    2 => "单号 $record->custom_transaction_id 透过站台接口存款失败，原因为站台出纳馀额不足，请联络客服",
    3 => "单号 $record->custom_transaction_id 透过站台接口存款失败，原因为接口用戶 $record->site_account_name 的额度已超出當日限制，请联络客服",
    4 => "单号 $record->custom_transaction_id 透过站台接口存款失败，原因为接口用户 $record->site_account_name 的额度已超出每日限制，请联络客服",
    5 => "单号 $record->custom_transaction_id 透过站台接口存款失败，原因为接口用户 $record->site_account_name 的额度已超出每月限制，请联络客服",
    6 => "单号 $record->custom_transaction_id 透过站台接口存款失败，原因为接口用户 $record->site_account_name 的存款金额与系统订单纪录不相符，请联络客服",
  ];

  respond_json(
    [
      'status' => $record->status,
      'description' => $result_case[$record->status],
      'processed_time' => $record->request_time
    ]
  );
}

// 處理待入款訂單的函式
function service_deposit_reserve($api_data) {
  // Step 2 取得錢包資訊、用戶是否存在、用戶狀態是否正常
  if(is_null(get_payee_info($api_data->account))):
    respond_json(['description' => 'The user account does not exist or is not enabled.'], 400);
    return;
  endif;

  // 先查詢是否有過待入款訂單(正常情況沒有，有就不新增)
  $record = ApiDepositOrder::read($api_data->transaction_order_id);

  // 沒有待入款訂單
  if(empty($record)) {
    $record = new ApiDepositOrder($api_data);
    $record->status = 0;
    $record->add();
  } else {
    $record->init($api_data);
    $record->update();
  }
  // var_dump($record);
  // 成功處理請求後，回應對應的入款成功資訊(同查詢時的回應)
  return service_response($record);
}


// 處理入款的函式
function service_deposit($api_data) {
    /**
     * 驗證同一筆單號是否已入款成功
     * 找出入款帳戶的資訊，驗證帳戶是否存在
     * 驗證 api 額度
     * 執行實際轉錢行為，入款成功則更新 passbook 與 api record table
     */

    // Step 1 驗證這筆單號是否已入款過
    $record = ApiDepositOrder::read($api_data->transaction_order_id);

    // 如果沒有單，可能預約時掉單
    if(empty($record)) {
        throw new \Exception('site_api 接到存款请求，但是找不到订单');
        return false;
    }

    if($record->isCompleted()) {
        return service_response($record);
    }

    if($record->isOutIpWhiteList()) {
        return @respond_json(['description' => "不合法的来源 IP ({$record->getRemoteIP()})，请向站方请求加入白名单"], 400);
    }

    if($record->isBeyondPerQuotas()) {
        $record->status = 3;
        $record->request_time = $record->requestTime();
        $record->update();
        return service_response($record);
    }

    if($record->isBeyondDailyQuotas()) {
        $record->status = 4;
        $record->request_time = $record->requestTime();
        $record->update();
        return service_response($record);
    }

    if($record->isBeyondMonthlyQuotas()) {
        $record->status = 5;
        $record->request_time = $record->requestTime();
        $record->update();
        return service_response($record);
    }

    if($record->amount != $api_data->amount) {
        $record->status = 6;
        $record->request_time = $record->requestTime();
        $record->update();
        return service_response($record);
    }

  // Step 2 取得錢包資訊、用戶是否存在、用戶狀態是否正常
  if(is_null(get_payee_info($api_data->account)))
    return respond_json(['description' => 'The user account does not exist or is not enabled.'], 400);

  // Step 3 成功轉錢後，寫到 api 的金流入款紀錄
  // 執行平台內的轉帳：目前在 Step 4 做
  // 更新訂單
  // 做完才實際入款處理，避免更新失敗造成的可重複入款動作
  $record->status = 1;
  $api_account_config = $record->getSiteApiAccount();
  $record->api_transaction_fee = round($record->amount * $api_account_config->fee_rate * 0.01, 2);
  $record->request_time = $record->requestTime();
  $record->update();

  // Step 4 執行實際入款
  $error = apideposit($api_data->account, $api_data->amount, $api_data->api_account, $api_data->transaction_order_id, $api_data->service);
  if($error['code'] != 1)
    return respond_json(['description' => $error], 500);

  // 轉帳成功，要檢查首充的時間欄位
  if (!$record->getFirstDepositDate()) {
    $record->setFirstDepositDate();
  }

  // mqtt notify
  notify_new_income();
  // Step 5 成功處理請求後，回應對應的入款成功資訊(同查詢時的回應)
  service_response($record);

  return $record;
}

// 處理入款查詢的函式
function service_deposit_query($api_data) {
  $record = ApiDepositOrder::read($api_data->transaction_order_id);
  return !is_null($record) ? service_response($record) : respond_json([
    'description' => 'No data of this transaction_id'
  ], 404);
}

// Main
if( !isset($_GET['t']) ) {
    http_response_code(406);
    return;
}
$token = $_GET['t'];

// actions: deposit
$action = filter_input(INPUT_GET, 'a') ?? null;
if( !$action ) {
    http_response_code(406);
    return;
}

// 驗證 sign 與 api_account
$api_data = jwtdec('123456', $token);
// @var_dump($api_data);

// Step 1
if( !check_api_data($api_data) ) {
  http_response_code(406);
  return;
}

switch($action):
    case 'deposit':
      service_deposit($api_data);
    break;

    case 'checkout':
      service_deposit_query($api_data);
    break;

    case 'reserve':
      service_deposit_reserve($api_data);
    break;
endswitch;

return;
?>
