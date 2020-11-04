<?php
// ----------------------------------------------------------------------------
// Features:  後台--投注紀錄lib
// File Name: member_betlog_lib.php
// Author:    YaoYuan
// Related:
// DB Table:
// Log:
// ----------------------------------------------------------------------------
// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
require_once dirname(__FILE__) . "/config_betlog.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";
// for csv manipulate
require_once __DIR__ . '/lib_file.php';
// ----------------------------------------------------------------------------


// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// show sample:days ago Seconds ago
function convert_to_fuzzy_time($times){
    global $tr;
    date_default_timezone_set('America/St_Thomas');
    $unix     = strtotime($times);
    $now      = time();
    $diff_sec = $now - $unix;

    if ($diff_sec < 60) {
        $time = $diff_sec;
        $unit = $tr['Seconds ago'];
    } elseif ($diff_sec < 3600) {
        $time = $diff_sec / 60;
        $unit = $tr['minutes ago'];
    } elseif ($diff_sec < 86400) {
        $time = $diff_sec / 3600;
        $unit = $tr['hours ago'];
    } elseif ($diff_sec < 2764800) {
        $time = $diff_sec / 86400;
        $unit = $tr['days ago'];
    } elseif ($diff_sec < 31536000) {
        $time = $diff_sec / 2592000;
        $unit = $tr['months ago'];
    } else {
        $time = $diff_sec / 31536000;
        $unit = $tr['years ago'];
    }

    return (int) $time . $unit;
}

// 比較時間大小函數：回傳大的max，小的min 時間陣列
function compare_tiem_detail($max_date, $min_date, $source_date){
    if (!strtotime($max_date)) {$max_date = $source_date;}
    if (!strtotime($min_date)) {$min_date = $source_date;}
    if (strtotime($source_date) > strtotime($max_date)) {$max_date = $source_date;}
    if (strtotime($source_date) < strtotime($min_date)) {$min_date = $source_date;}
    $return['max_date'] = $max_date;
    $return['min_date'] = $min_date;
    return $return;
}

//帶入時間日期，依序傳入比較時間大小函數，得到最大及最小時間
function compare_time($query_sql_array){
    // var_dump(strtotime($query_sql_array['query_betdate_start_datepicker']));
    $compare_date['max_date'] = '';
    $compare_date['min_date'] = '';
    if (isset($query_sql_array['query_betdate_start_datepicker'])) {
        $compare_date = compare_tiem_detail($compare_date['max_date'], $compare_date['min_date'], $query_sql_array['query_betdate_start_datepicker']);}
    if (isset($query_sql_array['query_betdate_end_datepicker'])) {
        $compare_date = compare_tiem_detail($compare_date['max_date'], $compare_date['min_date'], $query_sql_array['query_betdate_end_datepicker']);}
    if (isset($query_sql_array['query_date_start_datepicker'])) {
        $compare_date = compare_tiem_detail($compare_date['max_date'], $compare_date['min_date'], $query_sql_array['query_date_start_datepicker']);}
    if (isset($query_sql_array['query_date_end_datepicker'])) {
        $compare_date = compare_tiem_detail($compare_date['max_date'], $compare_date['min_date'], $query_sql_array['query_date_end_datepicker']);}
    // $compare_date['max_date']=date('Y-m-d',strtotime('+1 day',strtotime($compare_date['max_date'])));
    // var_dump($compare_date);
    return $compare_date;
}

// 算出日期區間
function date_range($first, $last){
    $dates  = array();
    $period = new DatePeriod(
        new DateTime($first),
        new DateInterval('P1D'),
        new DateTime($last.'+1 days')
    );
    foreach ($period as $date) {
        // var_dump($period,$date);
        $dates[] = $date->format('Ymd');
    }
    return $dates;
}

function game_name_key_helper($casino, $game_name)
{
    switch ($casino) {
        case 'PT':
            $gamenamekey = trim(strtolower(explode(" (", $game_name)[0]));
            break;
        case 'IG':
            $gamenamekey = str_ireplace("lottery", "时时彩", $game_name);
            $gamenamekey = str_ireplace("lotto", "香港彩", $gamenamekey);
            break;

        default:
            $gamenamekey = trim(strtolower($game_name));
            break;
    }
    //將混表注單裡，遊戲名稱有&#39;符號，改為'這樣才會正常
    // $gamenamekey = str_replace("&#39;","'",$gamenamekey);
    $gamenamekey = html_entity_decode($gamenamekey, ENT_QUOTES);

    return $gamenamekey;
}
