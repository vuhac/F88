<?php
// ----------------------------------------------------------------------------
// Features:  後台-- 線上支付看板
// File Name: depositing_siteapi_audit.php
// Author:    Webb
// Related:   前台無對應
// Log:       2017.8.29 update
// ----------------------------------------------------------------------------
// 對應資料表：root_site_api_deposit 站台 api 入款訂單
// 相關的檔案：

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/lib_view.php";
// require_once dirname(__FILE__) ."/lib_site_api_deposit_order.php";
require_once __DIR__ . '/site_api/lib_api.php';
require_once dirname(__FILE__) ."/lib_site_api_config.php";
require_once __DIR__ . '/lib_message.php';
require_once __DIR__ . '/protal_setting_lib.php';

// use Api\SiteDeposit\ApiDepositOrder;
use Api\SiteDeposit\SiteApiConfig;

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

/** 根據訂單資訊與接口帳戶，產生查詢訂單的 qToken */
function getQueryToken($order_no = 'kt1_1537155329908', $amount = '1', $api_account = 'kt1_5AFBDB2517E50')
{
    $data = array_filter(compact('order_no', 'amount', 'api_account'));
    ksort($data);
    $data['sign'] = md5(http_build_query($data) . '@@@' . $api_account);

    return base64_encode(http_build_query($data));
}

// 初始化變數
$action = filter_input(INPUT_GET, 'a');
$tr['Currency Type'] = $tr['currency type'];//'币别';
$tr['Api Account'] = $tr['API Account'];//'API 帐户';
$tr['Gcash'] = $tr['Franchise'];//'现金';
$tr['gtoken'] = $tr['Gtoken'];//'游戏币';
$tr['Site Api Deposit Dashboard'] = $tr['Online payment dashboard'];
$message_reciever_url = get_message_reciever_url('backstage', '');
$message_reciever_channel = get_message_channel('backstage', 'test');
// $api_account_list = SiteApiConfig::readAll();
$api_deposits = null;
$portalsetting_res = get_protalsetting_list('default');
$onlinepay_review_switch = $portalsetting_res['status'] ? $portalsetting_res['result']['onlinepay_review_switch'] ?? 'manual' : 'manual';
$managers = $su['superuser'];
$testers = $stationmail['sendto_system_cs'];

// AJAX 請求的部分
if (is_ajax()) {
    $response = [];

    switch ($action) {
        case 'apideposit_orders_json':
            // GET 請求
            if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            }
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // var_dump($_POST);
                $irecord_start = filter_input(INPUT_POST, 'start', FILTER_VALIDATE_INT);
                $irecord_length = filter_input(INPUT_POST, 'length', FILTER_VALIDATE_INT);
                $irecord_draw = filter_input(INPUT_POST, 'draw', FILTER_VALIDATE_INT);
                $irecord_columns = $_POST['columns'];
                $irecord_order = $_POST['order'];
                $irecord_search = filter_var($_POST['search']['value'], FILTER_SANITIZE_STRING);
                $order = [
                    $irecord_columns[ $irecord_order[0]['column'] ]['name'] => $irecord_order[0]['dir']
                ];

                // custom search
                $searches = $_POST['custom_search'];
                $txno = filter_var($searches['txno'] ?? '', FILTER_SANITIZE_STRIPPED);
                $account = filter_var($searches['account'] ?? '', FILTER_SANITIZE_STRIPPED);
                $agent = filter_var($searches['agent'] ?? '', FILTER_SANITIZE_STRIPPED);
                $sdate = filter_var($searches['sdate'] ?? '', FILTER_SANITIZE_STRIPPED);
                $edate = filter_var($searches['edate'] ?? '', FILTER_SANITIZE_STRIPPED);
                $samount = filter_var($searches['samount'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $eamount = filter_var($searches['eamount'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $ipaddr = filter_var($searches['ipaddr'], FILTER_VALIDATE_IP);
                $status = filter_var($searches['status'] ?? '', FILTER_SANITIZE_STRIPPED);

                $statuslist = [];
                foreach (explode(',', $status) as $review_status) {
                    $statuslist = array_merge($statuslist, ApiDepositOrder::auditStatusMap()[$review_status] ?? []);
                }
                $statuslist = array_unique($statuslist);
                $status = implode(',', $statuslist);

                $conditions = [];
                // $condition = ['FULL_TEXT' => $irecord_search];
                $txno and $conditions['txno'] = ['custom_transaction_id', 'LIKE', "%$txno%"];
                $account and $conditions['account'] = ['account', '=', $account];
                $agent and $conditions['agent'] = ['account', '=', $agent, 'root_parent', "OR root_site_api_deposit.account = '$agent'"];
                $sdate and $conditions['sdate'] = ['request_time', '>=', "$sdate -04"];

                $edate
                    and $edateYmd = date('Y-m-d', strtotime("$edate + 1 day"))
                    and $conditions['edate'] = ['request_time', '<', "$edateYmd 00:00 -04"]
                ;
                $samount and $conditions['samount'] = ['amount', '>=', $samount];
                $eamount and $conditions['eamount'] = ['amount', '<=', $eamount];
                $ipaddr and $conditions['ipaddr'] = ['agent_ip', '=', $ipaddr];
                strlen($status) > 0 and $conditions['status'] = ['RAWSQL' => "(root_site_api_deposit.status IN ($status))"];

                $api_deposits = ApiDepositOrder::count($conditions);
                $api_deposits_current = ApiDepositOrder::readCondition(
                    $irecord_start,
                    $irecord_length,
                    $conditions,
                    $order
                );

                // 對每一筆訂單資訊做額外
                foreach ($api_deposits_current as $transaction) {
                    // $transaction->api_account_title = 'No Name!!';

                    // // 取得對應的接口設定
                    // $api_account_info = array_filter($api_account_list, function($value) use ($transaction) {
                    //     return $value->api_account == $transaction->site_account_name;
                    // });
                    // $api_account_info = array_pop($api_account_info);

                    // if (!empty($api_account_info)) {
                    //     $transaction->api_account_title = $api_account_info->account_name;
                    //     $transaction->api_account_id = $api_account_info->id;
                    // }
                    $transaction->is_archived = $transaction->isArchived();
                }

                $response = [
                    'draw' => $irecord_draw,
                    'iTotalDisplayRecords' => $api_deposits,
                    'iTotalRecords' => count($api_deposits_current),
                    'data' => $api_deposits_current
                ];
            }

            echo json_encode($response);
            break;
    }
    return;
}

// $api_deposit_records = ApiDepositOrder::readAll();
// var_dump($api_deposit_records);
// echo memory_use_now();

return render(
    __DIR__ . '/'. pathinfo(__FILE__, PATHINFO_FILENAME) . '.view.php',
    compact(
        'api_deposits',
        'message_reciever_url',
        'message_reciever_channel',
        'onlinepay_review_switch',
        'managers',
        'testers'
    )
);
