<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 首儲統計報表功能
// File Name: first_store_report.php
// Author: Damocles
// Related: first_store_report_*.php
// Log:
// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/lib_view.php";
require_once dirname(__FILE__) ."/first_store_report_lib.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();

// 系統預設時區 (用於帶入SQL查詢式搜尋，如果沒有設定的話會造成查詢式的時間戳欄位搜尋結果異常)
$is_setted_default_timezone = true;
$default_timezone_message = '';
if ( empty($config['default_timezone']) ) {
    $is_setted_default_timezone = false;
    $default_timezone_message = $tr['default_timezone_message'];
} else {
    date_default_timezone_set($config['default_timezone']);
}

// 現在時間
$now_datetime_start = date("Y-m-d 00:00");
$now_datetime_end = date("Y-m-d 23:59");

// 計算本週 & 上週
$weekday_today = date('w');
$sunday_date = '';
$saturday_date = '';
$last_sunday_date = '';
$last_saturday_date = '';
switch ($weekday_today) {
    case '0':
        $sunday_date = date('Y-m-d');
        $saturday_date = date('Y-m-d', strtotime('+6 day'));
        $last_sunday_date = date('Y-m-d', strtotime('-7 day'));
        $last_saturday_date = date('Y-m-d', strtotime('-1 day'));
        break;
    case '1':
        $sunday_date = date('Y-m-d', strtotime('-1 day'));
        $saturday_date = date('Y-m-d', strtotime('+5 day'));
        $last_sunday_date = date('Y-m-d', strtotime('-8 day'));
        $last_saturday_date = date('Y-m-d', strtotime('-2 day'));
        break;
    case '2':
        $sunday_date = date('Y-m-d', strtotime('-2 day'));
        $saturday_date = date('Y-m-d', strtotime('+4 day'));
        $last_sunday_date = date('Y-m-d', strtotime('-9 day'));
        $last_saturday_date = date('Y-m-d', strtotime('-3 day'));
        break;
    case '3':
        $sunday_date = date('Y-m-d', strtotime('-3 day'));
        $saturday_date = date('Y-m-d', strtotime('+3 day'));
        $last_sunday_date = date('Y-m-d', strtotime('-10 day'));
        $last_saturday_date = date('Y-m-d', strtotime('-4 day'));
        break;
    case '4':
        $sunday_date = date('Y-m-d', strtotime('-4 day'));
        $saturday_date = date('Y-m-d', strtotime('+2 day'));
        $last_sunday_date = date('Y-m-d', strtotime('-11 day'));
        $last_saturday_date = date('Y-m-d', strtotime('-5 day'));
        break;
    case '5':
        $sunday_date = date('Y-m-d', strtotime('-5 day'));
        $saturday_date = date('Y-m-d', strtotime('+1 day'));
        $last_sunday_date = date('Y-m-d', strtotime('-12 day'));
        $last_saturday_date = date('Y-m-d', strtotime('-6 day'));
        break;
    case '6':
        $sunday_date = date('Y-m-d', strtotime('-6 day'));
        $saturday_date = date('Y-m-d');
        $last_sunday_date = date('Y-m-d', strtotime('-13 day'));
        $last_saturday_date = date('Y-m-d', strtotime('-7 day'));
        break;
    default:
        break;
}

// 計算本月第一天跟最後一天
$this_year_month = date("Y-m");
$days_in_month = date("t", strtotime($this_year_month.'-1'));
$first_date_in_month = $this_year_month.'-01';
$last_date_in_month = $this_year_month.'-'.$days_in_month;

// 計算上個月第一天跟最後一天
$last_year_month = date("Y-m", strtotime('-1 month'));
$days_in_last_month = date("t", strtotime($last_year_month.'-1'));
$first_date_in_last_month = $last_year_month.'-01';
$last_date_in_last_month = $last_year_month.'-'.$days_in_last_month;

return render(
    'first_store_report_view.php',
    compact(
        'now_datetime_start',
        'now_datetime_end',
        'sunday_date',
        'saturday_date',
        'last_sunday_date',
        'last_saturday_date',
        'first_date_in_month',
        'last_date_in_month',
        'first_date_in_last_month',
        'last_date_in_last_month',
        'default_timezone_message',
        'is_setted_default_timezone'
    )
);
?>