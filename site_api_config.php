<?php
// ----------------------------------------------------------------------------
// Features:  後台-- 帳台開放 api 帳戶管理
// File Name:  site_api_config.php
// Author:    Webb
// Modifier:  Damocles
// Related:
// DB Table:  root_site_api_account
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
require_once dirname(__FILE__) . "/lib_view.php";
require_once dirname(__FILE__) . "/lib_site_api_config.php";
require_once dirname(__FILE__) . "/lib_errorcode.php";
// 線上金流 pay api
use Api\SiteDeposit\SiteApiConfig; // 從lib_site_api_config.php來的
use Onlinepay\SDK\PaymentGateway;

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 初始化變數
$allowed_actions = ['add', 'edit', 'del'];
$action = filter_input(INPUT_GET, 'a');
$member_grade_rows = runSQLall_prepared("SELECT id, gradename FROM root_member_grade WHERE status = 1");
$tmp = [];
array_walk($member_grade_rows, function (&$a, $b) use (&$tmp) { // 把array的key變成id值
  $tmp[$a->id] = $a;
});
$member_grade_rows = $tmp;

$pay_config = $config['gpk2_pay'] ?? ['apiKey' => '', 'apiToken' => ''];

$extend_head_alert = array_key_exists('gpk2_pay', $config) ? '' : <<<HTML
<script>
  alert('config 缺少 gpk2_pay!!');
</script>
HTML;

$pay_config = $config['gpk2_pay'] + ['system_mode' => PaymentGateway::SYS_MODE_TEST, 'debug' => 1];
$config['payment_mode'] == PaymentGateway::SYS_MODE_PROD and $pay_config = $config['gpk2_pay'] + ['system_mode' => PaymentGateway::SYS_MODE_PROD];

$onlinepayGateway = new PaymentGateway($pay_config);
$apiEntry = $config['gpk2_pay']['apiHost'] ?? null;
$apiEntry and $onlinepayGateway->setApiEntry($config['gpk2_pay']['apiHost'] . '/api');
$onlinepayGateway->lang = $_GET['lang'] ?? 'zh-cn';

// 頁面上的各個服務狀態
$compinfo = [
  'onlinepay' => ['code' => 0, 'desc' => 'success'],
];

$agentinfo = [
  'account' => 'N/A',
  'agent_code' => 'N/A',
  'deposit_limits' => 'N/A',
  'deposit_alerts' => 'N/A',
  'single_deposit_limits' => 'N/A',
  'accumulated_amount' => 'N/A',
  'status' => $tr['is maintenance'],
  'expired_at' => '1970/01/01',
  'remain_days' => $tr['expired'],
  'office_url' => ''
];

$currency = $config['currency_sign'];
$payment_methods = (object) ['data' => []];

try {
  $payment_methods = $onlinepayGateway->getServiceList();
  $office_url = $onlinepayGateway->getOfficeUrl();
  $txList = $onlinepayGateway->getTxSummary();
  $agent_detail = $onlinepayGateway->getAgentInfo();
  $apidata = compact('payment_methods', 'agent_detail', 'txList', 'office_url');
  // check api result
  array_walk($apidata, function ($api_result, $key) {
    if ($api_result->status->code == 0) return;
    throw new Exception("$key: " . json_encode($api_result), ErrorCode::API_EXCEPTION);
  });

  $currency = $agent_detail->data->currency_info->codename;
  foreach ($agentinfo as $key => &$value) {
    $value = $agent_detail->data->$key ?? 'N/A';
  }
  $agentinfo['office_url'] = $office_url->data;
} catch (Throwable $e) {
  $debug_fmt = "%s (%s): %s";
  $compinfo['onlinepay'] = [
      'code' => $e->getCode() ?: ErrorCode::CURL_EXCEPTION,
      'desc' => "{$tr['error, please contact the developer for processing.']}({$tr['onlinepay']})",
      'debuginfo' => $e->getMessage(),
  ];
  error_log(sprintf($debug_fmt, $_SERVER['SCRIPT_NAME'], date('c'), $e->getMessage()));
}

$is_su = isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') && in_array($_SESSION['agent']->account, $su['superuser']);

//管控前往金流管理後台
$payment_flow_control_check = true;
// if($is_su) {
//     $payment_flow_control_check = true;
// }
// else{
//     $admin_pchk = admin_power_chk('site_api_config','htm');
//     if($admin_pchk['option']['payment_flow_control']=='1'){
//         $payment_flow_control_check = true;
//     }
//     else{
//         $payment_flow_control_check = false;
//     }
// }

// 取得後台登入連結並前往
$url_to_onlinepay_admin = $config['onlinepay_service']['admin']['url'] ?? 'http://www.baidu.com';

$tr['Site Api Account Management'] = $tr['Third-party payment business management'];

// AJAX 請求的部分
if (is_ajax()) {
  /* Abandoned */
  /* if (!$payment_flow_control_check) {
    echo json_encode(['invalid operation!']);
    return;
  }

  $json_data = (object) json_decode(file_get_contents("php://input"), true);
  $response = [
    'success' => 'N',
    'description' => 'is not yet processed',
  ];

  switch ($_SERVER['REQUEST_METHOD']) {
  case 'POST':
    $new_config = new SiteApiConfig(['jsonData' => $json_data]);
    $new_config->create();
    // 新增到遠端伺服器
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || getenv('HTTP_X_FORWARDED_PROTO') === 'https') ? "https://" : "http://";
    $result = $onlinepayAdminGateway->post('CREATE_PLATFORM_ACCOUNT', $params = [
      'title' => $new_config->account_name,
      'site_name' => $config['projectid'],
      'status' => 0,
      'api_account' => $new_config->api_account,
      'api_key' => $new_config->api_key,
      'gateway' => $protocol . $config['website_domainname'] . '/site_api/gateway.php',
      'available_services' => $new_config->available_services,
      'owner' => $configObj->account,
    ]);

    $response['success'] = 'Y';
    $response['description'] = 'The config is added at ' . $new_config->change_time;
    $response['description'] = ";\nThe response from onlinepay server: $result";

    echo json_encode($response);

    // curl to onlinepay, 預留
    break;

  case 'GET':
    break;

  case 'PATCH':
    $older_config = SiteApiConfig::read(['id' => $json_data->id]);

    if ($older_config->isConflicted($json_data)) {
      $response['description'] = 'Conficting! The config is updated at ' . $older_config->change_time . ', please reload and check out the config.';
      var_dump(json_encode($response));
    }

    foreach ($older_config as $attr => $value) {
      // 不允許修改的欄位
      if (in_array($attr, ['api_key', 'api_account'])) {
        continue;
      }
      $older_config->$attr = $json_data->$attr;
    }

    $older_config->update();
    // 查詢這筆 api 資料在遠端金流服務的資訊
    $remote_config_list = $onlinepayAdminGateway->get('READ_PLATFORM_ACCOUNT');
    $remote_config_info = array_filter($remote_config_list, function ($value) use ($older_config) {
      return $value->api_account == $older_config->api_account && $value->api_key == $older_config->api_key;
    });

    if (!empty($remote_config_info)) {
      $remote_config_info = get_object_vars(array_pop($remote_config_info));

      if ($older_config->status == 0) {
        // 開啟
        $remote_status = 1;
      } elseif ($older_config->status == 1) {
        // 關閉
        $remote_status = 0;
      } else {
        // 維護中
        $remote_status = 2;
      }

      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || getenv('HTTP_X_FORWARDED_PROTO') === 'https') ? "https://" : "http://";
      $params = array_merge($remote_config_info, [
        'title' => $older_config->account_name,
        'available_services' => $older_config->available_services,
        'gateway' => $protocol . $config['website_domainname'] . '/site_api/gateway.php',
        'status' => $remote_status
      ]);

      // 更新到遠端伺服器
      // 重要！必須知道要修改的資源 id, 透過 read 指令取得
      $result = $onlinepayAdminGateway->post('UPDATE_PLATFORM_ACCOUNT', $params);
    } else {
      $result = '遠端金流伺服器無對應的 api 組，請聯絡客服';
    }

    $response['success'] = 'Y';
    $response['description'] = 'The config is updated at ' . $older_config->change_time;
    $response['description'] = ";\nThe response from onlinepay server: $result";

    echo json_encode($response);
    break;

  case 'DELETE':
    // SiteApiConfig::delete(['id' => $json_data->id]);
    $older_config = SiteApiConfig::read(['id' => $json_data->id]);
    // 刪除(封存))
    $older_config->status = 3;
    $older_config->update();

    // 查詢這筆 api 資料在遠端金流服務的資訊
    $remote_config_list = $onlinepayAdminGateway->get('READ_PLATFORM_ACCOUNT');
    $remote_config_info = array_filter($remote_config_list, function ($value) use ($older_config) {
      return $value->api_account == $older_config->api_account && $value->api_key == $older_config->api_key;
    });

    if (!empty($remote_config_info)) {
      $remote_config_info = array_pop($remote_config_info);
      // 更新遠端伺服器的資訊
      $result = $onlinepayAdminGateway->post('UPDATE_PLATFORM_ACCOUNT', $params = [
        // 重要！必須知道要修改的資源 id, 透過 read 指令取得
        'id' => $remote_config_info->id,
        'title' => $older_config->account_name,
        'api_account' => $older_config->api_account,
        'api_key' => $older_config->api_key,
        'available_services' => $older_config->available_services,
        'owner' => $remote_config_info->owner,
        'groups' => $remote_config_info->groups,
        'change_time' => $remote_config_info->change_time,
        'status' => 3
      ]);
    }

    $response['success'] = 'Y';
    $response['description'] = 'The config is processed.';

    echo json_encode($response);
    break;
  }
  */
  return;
}

// GET 請求 | 透過 browser 直接訪問
// 如果不是允許的特定動作，只 render 列表頁
if (!in_array($action, $allowed_actions)) {
    $api_configs = SiteApiConfig::readAll(function ($value) {
        return $value->status < 3;
    });

    // 取得代理商資料
    is_numeric($agentinfo['deposit_alerts']) and $agentinfo['deposit_alerts'] = number_format($agentinfo['deposit_alerts'], 2);
    is_numeric($agentinfo['deposit_limits']) and $agentinfo['deposit_limits'] = number_format($agentinfo['deposit_limits'], 2);
    is_numeric($agentinfo['single_deposit_limits']) and $agentinfo['single_deposit_limits'] = number_format($agentinfo['single_deposit_limits'], 2);
    is_numeric($agentinfo['accumulated_amount']) and $agentinfo['accumulated_amount'] = number_format($agentinfo['accumulated_amount'], 2);
    $expired_at_strtotime = strtotime($agentinfo['expired_at']);
    $agentinfo['expired_at'] = date("Y/m/d", $expired_at_strtotime);
    $agentinfo['remain_days'] < 0 and $agentinfo['remain_days'] = $tr['expired'];
    is_numeric($agentinfo['remain_days']) and $agentinfo['remain_days'] .= ($agentinfo['remain_days'] > 1 ? ' days' : ' day');
    $agentinfo['status'] = $agentinfo['status'] == 1 ? $tr['enable'] : $tr['disable'];

    // 當日交易額度圖表
    $today = /* '2019-11-18' */date("Y-m-d"); // 指定當天日期
    // 初始化各時間區間的交易額度
    $time_range_all = [
        '00:00' => 0, '01:00' => 0, '02:00' => 0, '03:00' => 0, '04:00' => 0, '05:00' => 0, '06:00' => 0, '07:00' => 0,
        '08:00' => 0,'09:00' => 0, '10:00' => 0, '11:00' => 0, '12:00' => 0,'13:00' => 0, '14:00' => 0,'15:00' => 0,
        '16:00' => 0,'17:00' => 0, '18:00' => 0, '19:00' => 0, '20:00' => 0, '21:00' => 0, '22:00' => 0, '23:00' => 0
    ];
    $time_range_completed = [
        '00:00' => 0, '01:00' => 0, '02:00' => 0, '03:00' => 0, '04:00' => 0, '05:00' => 0, '06:00' => 0, '07:00' => 0,
        '08:00' => 0,'09:00' => 0, '10:00' => 0, '11:00' => 0, '12:00' => 0,'13:00' => 0, '14:00' => 0,'15:00' => 0,
        '16:00' => 0,'17:00' => 0, '18:00' => 0, '19:00' => 0, '20:00' => 0, '21:00' => 0, '22:00' => 0, '23:00' => 0
    ];

    $txListData = $txList->data ?? [];
    foreach($txListData as $key => $val){
        $_explode = explode(" ", $key); // 依照空白去拆資料
        $date = date( 'Y-m-d', strtotime($_explode[0]) ); // 紀錄日期
        $start_time = date( 'H:i', strtotime($_explode[1]) ); // 紀錄開始時間
        // 判斷是否為今天的紀錄，是的話才會取出時間當作X軸
        if( $today == $date ){
            // 把資料寫回 $time_range_all & $time_range_completed
            $time_range_all[$start_time] = (float)$val->statistics->all->order_amount;
            $time_range_completed[$start_time] = (float)$val->statistics->completed->order_amount;
        }
    } // end foreach

    // 把資料組合成array
    $data_all = [];
    foreach( $time_range_all as $val ){
        array_push( $data_all, $val );
    } // end foreach
    $data_all = json_encode( $data_all );

    $data_completed = [];
    foreach( $time_range_completed as $val ){
        array_push( $data_completed, $val );
    } // end foreach
    $data_completed = json_encode( $data_completed );

    // 支付方式
    foreach ($payment_methods->data as $key=>$val) {
        $payment_methods->data[$key]->available_payment_methods_count = count($val->available_payment_methods); // 加上可用支付方式總計屬性

        if( $payment_methods->data[$key]->available_payment_methods_count == 0 ){ // 如果沒有可用支付方式，則把所有支付方式加到不可用支付方式裡面
            foreach($val->payment_methods as $key_outer=>$val_outer){
                $currency_count = count($val_outer->currency);
                if( $currency_count == 0 ){
                    $val->payment_methods[$key_outer]->currency = '----';
                }
                else if( $currency_count == 1 ){
                    $val->payment_methods[$key_outer]->currency = $val_outer->currency[0];
                }
                else{
                    $is_first = true;
                    $currency_string = '';
                    foreach($val_outer->currency as $key_inner=>$val_inner){
                        if($is_first){
                            $is_first = false;
                            $currency_string .= $val_inner;
                        }
                        else{
                            $currency_string .= '、'.$val_inner;
                        }
                    } // end inner foreach
                    $val->payment_methods[$key_outer]->currency = $currency_string;
                }
            } // end foreach
            $payment_methods->data[$key]->not_available_payment_methods = $val->payment_methods;
        }
        else{
            foreach($val->available_payment_methods as $key_outer=>$val_outer){
                $currency_count = count($val_outer->currency);
                if( $currency_count == 0 ){
                    $val_outer->currency = '----';
                }
                else if( $currency_count == 1 ){
                    $val_outer->currency = $val_outer->currency[0];
                }
                else{
                    $is_first = true;
                    $currency_string = '';
                    foreach($val_outer->currency as $key_inner=>$val_inner){
                        if($is_first){
                            $is_first = false;
                            $currency_string .= $val_inner;
                        }
                        else{
                            $currency_string .= '、'.$val_inner;
                        }
                    } // end inner foreach
                    $payment_methods->data[$key]->available_payment_methods[$key_outer]->currency = $currency_string;
                }
            } // end foreach

            $payment_methods->data[$key]->not_available_payment_methods = []; // 初始化不可用支付方式
            foreach($val->payment_methods as $key_outer=>$val_outer){ // 所有支付方式
                $is_available = false;
                foreach($val->available_payment_methods as $key_inner=>$val_inner){ // 可用支付方式
                    if( ($val_outer->payment == $val_inner->payment) && ($val_outer->title == $val_inner->title) ){ // 比對支付方式 是否在 可用支付方式中
                        $is_available = true;
                    }
                } // end inner foreach
                if(!$is_available){
                    $currency_count = count($val_outer->currency);
                    if( $currency_count == 0 ){
                        $val_outer->currency = '----';
                    }
                    else if( $currency_count == 1 ){
                        $val_outer->currency = $val_outer->currency[0];
                    }
                    else{
                        $is_first = true;
                        $currency_string = '';
                        foreach($val_outer->currency as $key_inner=>$val_inner){
                            if($is_first){
                                $is_first = false;
                                $currency_string .= $val_inner;
                            }
                            else{
                                $currency_string .= '、'.$val_inner;
                            }
                        } // end inner foreach
                        $val_outer->currency = $currency_string;
                    }
                    array_push($payment_methods->data[$key]->not_available_payment_methods, $val_outer);
                }
            } // end outer foreach
        }
    } // end foreach

    // 判斷是否有建立測試訂單的帳號
    $isset_order_tester_account = ( isset($stationmail['sendto_system_cs']) ? true : false );

    return render(
        __DIR__ . '/site_api_config.view.php',
        compact(
            'api_configs', 'member_grade_rows', 'url_to_onlinepay_admin',
            'payment_flow_control_check', 'is_su',
            'extend_head_alert', 'payment_methods',
            'data_all', 'data_completed', 'today', 'currency',
            'agentinfo', 'compinfo',
            'isset_order_tester_account'
        )
    );
}

