<?php
// ----------------------------------------------------------------------------
// Features:  gtoken 相關服務
// File Name: service.php
// Author:    Webb
// Modifier: Damocles
// Related:
// DB table:  root_site_api_account, root_gcash_order
// Log:
// ----------------------------------------------------------------------------
/*
主要操作的DB表格：
root_site_api_account  site api 帳號
root_gcash_order       gcash api 訂單紀錄

備註:
clone from ../gcash/service.php
目前邏輯相同，先拆開預留未來可能的新功能
 */
// ----------------------------------------------------------------------------

// 主機及資料庫設定
require_once __DIR__ . '/../../config.php';
// 自訂函式庫
require_once __DIR__ . "/../../lib.php";
// 支援多國語系
require_once __DIR__ . "/../../i18n/language.php";
require_once __DIR__ . '/../lib_api.php';
require_once __DIR__ . '/../../protal_setting_lib.php';
// rabbitMQ
require_once __DIR__ . '/../../Utils/RabbitMQ/Publish.php';
require_once __DIR__ . '/../../Utils/MessageTransform.php';

// 取得入款對象的帳戶資訊
function get_payee_info($account) {
  $acc_sql = <<<SQL
        SELECT
          *,
          root_member_grade.pointcardfee_member_rate AS apifee_rate
        FROM root_member
        JOIN root_member_wallets ON (root_member.id=root_member_wallets.id)
        LEFT JOIN root_member_grade ON root_member.grade::bigint = root_member_grade.id
        WHERE (root_member.account = :account) AND
              (root_member.status = '1');
    SQL;
  $acc_result = runSQLall_prepared($acc_sql, ['account' => $account]);
  $acc_result = array_pop($acc_result);
  return $acc_result;
}

function check_api_data($api_data, $config) {
  $key = $config['gpk2_pay']['apiKey'];
  return check_sign(get_object_vars($api_data), $key);
}

// 處理待入款訂單的函式
function service_deposit_reserve($api_data) {
  // Step 2 取得錢包資訊、用戶是否存在、用戶狀態是否正常
  if (is_null(get_payee_info($api_data->account))):
    respond_json(['description' => 'The user account does not exist or is not enabled.'], 400);
    return;
  endif;

  // 先查詢是否有過待入款訂單(正常情況沒有，有就不新增)
  $record = ApiDepositOrder::read($api_data->transaction_order_id);

  // 沒有待入款訂單
  if (empty($record)) {
    $record = new ApiDepositOrder($api_data);
    $record->status = 0;
    $record->add();
  } else {
    $record->init($api_data);
    $record->update();
  }
  // var_dump($record);
  // 成功處理請求後，回應對應的入款成功資訊(同查詢時的回應)
  respond_json([
    'status' => $record->status,
    'description' => ApiDepositOrder::recordStatusDesc()[$record->status],
    'processed_time' => date('c'),
  ]);
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

  $rabmq = Publish::getInstance();
  $rabmsg = MessageTransform::getInstance();

  $msg = new Message();
  $msg
    ->setTitle('新的存款通知')
    ->setMessage('接收到存款通知，待驗證')
    ->setUrl('/depositing_siteapi_audit.php')
    ->setDelay(5000); //顯示1sec後dismiss 不設則不會消失

  // 如果沒有單，可能預約時掉單
  if (empty($record)) {
    $msg->setMessage('接到存款请求，但是找不到订单');
    // notify_new_income($msg);
    // throw new \Exception($msg->message);
    return respond_json([
      'message' => 'Order not found.',
    ]);
  } else {
    $record->notification_json = $api_data;
    $record->update();
  }

  if ($record->isCompleted()) {
    return respond_json([
      'status' => $record->status,
      'description' => ApiDepositOrder::recordStatusDesc()[$record->status],
      'processed_time' => date('c'),
    ]);
  }

  if ($record->amount != $api_data->amount) {
    $msg->setMessage("单号 $record->custom_transaction_id 存款失败，原因为存款金额与系统订单纪录不相符，请联络客服");
    notify_new_income($msg);
    return respond_json(['description' => $msg->message], 400);
  }

  // Step 2 取得錢包資訊、用戶是否存在、用戶狀態是否正常、是否為測試訂單帳號
  // $record->isTestOrder() and exit(respond_json(['description' => 'This is a test account, Please skip this order.'], 400));
  $payeeinfo = $record->isTestOrder() ? (object) ['apifee_rate' => 100] : get_payee_info($api_data->account);
  !$payeeinfo and exit(respond_json(['description' => 'The user account does not exist or is not enabled.'], 400));

  // Step 3 成功轉錢後，寫到 api 的金流入款紀錄
  // 執行平台內的轉帳：目前在 Step 4 做
  // 更新訂單
  // 做完才實際入款處理，避免更新失敗造成的可重複入款動作
  $record->status = ApiDepositOrder::STATUS_NOTIFIED;

  $fee_rate = $payeeinfo->apifee_rate * 10 ** -2;
  $record->api_transaction_fee = round($record->amount * $fee_rate, 2);
  $record->update();

  $portalsetting_res = get_protalsetting_list('default');
  $onlinepay_review_switch = $portalsetting_res['status'] ? $portalsetting_res['result']['onlinepay_review_switch'] : 'manual';

  $nowsec = date("Y-m-d H:i:s", strtotime('now'));
  if ($onlinepay_review_switch == 'automatic' and $api_data->status == '1') {
    // Step 4 執行實際入款
    $record->reviewOrder('agree');
  } else {
    // 待人工審核 rabbitmq
    $notifyMsg = $rabmsg->notifyMsg('OnlinePay', $api_data->account, $nowsec);
    $notifyResult = $rabmq->fanoutNotify('msg_notify', $notifyMsg);
    // $notifyResult = $rabmq->directNotify('direct_test', 'direct_test', $notifyMsg);
  }

  // 轉帳成功，要檢查首充的時間欄位
  if (!$record->getFirstDepositDate()) {
    $record->setFirstDepositDate();
  }

  // mqtt notify
  $msg->setMessage("您有一笔新的收入，请查照");
  notify_new_income($msg);

  // rabbitmq notify
  $notifyMsg = $rabmsg->notifyMsg('OnlinePay-Passed', $api_data->account, $nowsec);
  $notifyResult = $rabmq->fanoutNotify('msg_notify', $notifyMsg);
  // $notifyResult = $rabmq->directNotify('direct_test', 'direct_test', $notifyMsg);

  // Step 5 成功處理請求後，回應對應的入款成功資訊(同查詢時的回應)
  respond_json([
    'status' => $record->status,
    'description' => ApiDepositOrder::recordStatusDesc()[$record->status],
    'processed_time' => date('c'),
  ]);

  return $record;
}

// 處理入款查詢的函式
function service_deposit_query($api_data) {
  $record = ApiDepositOrder::read($api_data->transaction_order_id);
  return !is_null($record)
  ? respond_json(
    [
      'status' => $record->status,
      'description' => ApiDepositOrder::recordStatusDesc()[$record->status],
      'processed_time' => date('c'),
    ]
  )
  : respond_json(
    [
      'description' => 'No data of this transaction_id',
    ]
    , 404
  );
}

/**
 * 取得會員入款帳戶 gtoken or gcash
 *
 * @return string $currency gcash | gtoken
 */
function get_system_currency() {
  $values = ['setting_name' => 'default',
    'setting_attr' => 'member_deposit_currency'];
  $setting_query_res = runSQLall_prepared(
    "SELECT value FROM root_protalsetting WHERE setttingname = :setting_name AND name = :setting_attr",
    $values
  )[0];

  return $setting_query_res->value ?? 'gtoken';
}

// Main
if (!isset($_GET['t'])) {
  http_response_code(406);
  return;
}
$token = $_GET['t'];

// actions: deposit
$action = filter_input(INPUT_GET, 'a') ?? null;
if (!$action) {
  http_response_code(406);
  return;
}

// 驗證 sign 與 api_account
$api_data = jwtdec('123456', $token);

// Step 1
if (!check_api_data($api_data, $config)) {
  http_response_code(406);
  return;
}

switch ($action):
case 'deposit':
  service_deposit($api_data);
  break;

case 'checkout':
  service_deposit_query($api_data);
  break;

case 'reserve':
  // service_deposit_reserve($api_data);
  break;
  endswitch;

  return;
  ?>
