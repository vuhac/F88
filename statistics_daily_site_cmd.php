#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 透過程式定時分析站台資訊 -- 預設每 10 分鐘
// File Name:   statistics_daily_site_cmd.php
// Author:      Webb Lu
// Related:
// Desc: 透過程式定時每 10 分鐘分析站台資訊
// Log:
//
// ----------------------------------------------------------------------------
// How to run ?
// usage command line :
//  /usr/bin/php70 statistics_daily_site_cmd.php run|test time_interval date time
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

$stats_insert_count = 0;
$stats_update_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
require_once dirname(__FILE__) ."/config_betlog.php";

// set memory limit
ini_set('memory_limit', '200M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// 程式 debug 開關, 0 = off , 1= on
$debug = 0;

// API 每次送出的最大數據筆數
// 用於進行帳號批次 LOCK 時使用
$api_limit = 1000;

// ----------------------------------
// 本程式使用的 function
// ----------------------------------
// -----------------------------------------------------------------------------
// 傳入指定日期和 Casion_ID, 輸出查詢到的指定統計時間
// -----------------------------------------------------------------------------
function stat_site($current_datepicker, $chk_time_start, $chk_time) {
    // config.php
    global $stats_config;
    // system_config.php
    global $gtoken_cashier_account, $gcash_cashier_account;
    global $debug;

    // 轉換時間為GMT+8
    $chk_timestamp_start = strtotime($chk_time_start.' -04')+8*3600;
    $chk_timestamp = strtotime($chk_time.' -04')+8*3600;
    $chk_time_start_gmt = gmdate('Y-m-d H:i:s', $chk_timestamp_start).'+08:00';
    $chk_time_gmt = gmdate('Y-m-d H:i:s', $chk_timestamp).'+08:00';
    $chk_date_start_gmt = gmdate('Y-m-d', $chk_timestamp_start).'+08:00';
    $chk_date_gmt = gmdate('Y-m-d', $chk_timestamp_start + 86400).'+08:00';

    // 在區間內各類存取款總額
    $stat_sql = <<<SQL
        -- 生成 10 分鐘區間內的暫存表
        WITH "interval_gcashpassbook" AS (
            SELECT
            sum(withdrawal) as withdrawal_sum,
            sum(deposit) as deposit_sum,
            count(withdrawal) as withdrawal_count,
            count(deposit) as deposit_count,
            sum(deposit) - sum(withdrawal) as balance_sum,
            transaction_category, realcash,
            'gcashpassbook'::text as src
            FROM root_member_gcashpassbook
            WHERE -- source_transferaccount = '".\$gtoken_account."' AND
            -- transaction_time >= '2017-11-19 14:40:00-04' AND
            -- transaction_time < '2017-11-19 14:50:00-04'
            transaction_time >= '{$chk_time_start}-04' AND
            transaction_time < '{$chk_time}-04' AND
            source_transferaccount != '{$gcash_cashier_account}'
            GROUP BY transaction_category, realcash
        ), "interval_gtokenpassbook" AS (
            SELECT
            sum(withdrawal) as withdrawal_sum,
            sum(deposit) as deposit_sum,
            count(withdrawal) as withdrawal_count,
            count(deposit) as deposit_count,
            sum(deposit) - sum(withdrawal) as balance_sum,
            transaction_category, realcash,
            'gtokenpassbook'::text as src
            FROM root_member_gtokenpassbook
            WHERE -- source_transferaccount = '".\$gtoken_account."' AND
            transaction_time >= '{$chk_time_start}-04' AND
            transaction_time < '{$chk_time}-04' AND
            source_transferaccount != '{$gtoken_cashier_account}'
            GROUP BY transaction_category, realcash
        )

        SELECT * FROM interval_gcashpassbook UNION ALL SELECT * FROM interval_gtokenpassbook;
SQL;

    // 加入轉帳到 token 的判斷
	// table: root_protalsetting
	$values = [
		'setting_name' => 'default',
		'setting_attr' => 'member_deposit_currency'
	];
    $setting_query_res = runSQLall_prepared("SELECT value FROM root_protalsetting WHERE setttingname = :setting_name AND name = :setting_attr", $values)[0];
    if($debug == 1) var_dump($setting_query_res);
    switch ($setting_query_res->value):
        case 'gcash':
        $first_deposit_review_sql = <<<SQL
        -- 首存的帳號、時間、金額
            SELECT source_transferaccount, deposit, transaction_time AS first_deposit_time FROM "root_member_gcashpassbook" WHERE transaction_time IN (
                SELECT MIN(transaction_time) FROM "root_member_gcashpassbook" GROUP BY source_transferaccount
            ) AND source_transferaccount != '{$gcash_cashier_account}'
SQL;
            break;
    case 'gtoken':
        $first_deposit_review_sql = <<<SQL
            -- 首存的帳號、時間、金額
            SELECT source_transferaccount, deposit, transaction_time AS first_deposit_time FROM "root_member_gtokenpassbook" WHERE transaction_time IN (
                SELECT MIN(transaction_time) FROM "root_member_gtokenpassbook" GROUP BY source_transferaccount
            ) AND source_transferaccount != '{$gtoken_cashier_account}'
SQL;
            break;
        default:
            die('沒有適合的貨幣類別!');
            break;
    endswitch;

    // global 資訊
    $stmt_global_sql = <<<SQL
        WITH "interval_root_member" AS (
            SELECT count(account) AS new_member_count FROM root_member WHERE enrollmentdate >= '{$chk_time_start} -04' AND enrollmentdate  < '{$chk_time} -04' AND status = '1'
        ), "first_deposit_review" AS (
            {$first_deposit_review_sql}
        ), "interval_first_deposit_count" AS (
        -- 此處計算的是 10 分鐘內，執行了一生當中的第一次存款
            SELECT count(*) AS first_depositmember_count, sum(deposit) AS first_depositamount_sum FROM (
                SELECT root_member.id, root_member.account, first_deposit_review.deposit, first_deposit_review.first_deposit_time FROM root_member
                LEFT JOIN first_deposit_review ON root_member.account = first_deposit_review.source_transferaccount
                -- 當天註冊的會員
                WHERE -- enrollmentdate >= '{$chk_date_start_gmt}' AND enrollmentdate < '{$chk_date_gmt}' AND
                status = '1' AND
                -- 在時間間隔內首存
                first_deposit_time >= '{$chk_time_start}-04' AND first_deposit_time < '{$chk_time}-04'
            ) fd_count_by_acc
        ), "interval_root_withdraw_review" AS (
            SELECT sum(administrative_amount) AS tokenadministration FROM root_withdraw_review WHERE processingtime >= '{$chk_time_start}-04' AND processingtime < '{$chk_time}-04' AND status = '1'
        ), "interval_root_agent_review" AS (
            SELECT count(*) AS new_agent_count, sum(amount) AS new_agent_amount FROM root_agent_review WHERE processingtime >= '{$chk_time_start}-04' AND processingtime < '{$chk_time}-04' AND status = '1'
        )

        SELECT * FROM interval_root_member
        , interval_first_deposit_count
        , interval_root_agent_review
        , interval_root_withdraw_review
SQL;

// 定義回傳用的統計資料; 1D array
    $record = [
        'updatetime'                => 'now()',
        'dailydate'                 => $current_datepicker,
        'dailytime_start'           => $chk_time_start,
        'dailytime_end'             => $chk_time,
        // src from gcashpassbook
        'apicashwithdrawal'         => 0,
        'cashadministrationfees'    => 0,
        'cashdeposit'               => 0,
        'cashgtoken'                => 0,
        'cashtransfer'              => 0,
        'cashwithdrawal'            => 0,
        'payonlinedeposit'          => 0,
        // src from gtokenpassbook
        'MEGA_tokenpay'             => 0,
        'MG_tokenpay'               => 0,
        'PT_tokenpay'               => 0,
        'tokenadministrationfees'   => 0,
        'tokendeposit'              => 0,
        'tokenfavorable'            => 0,
        'tokengcash'                => 0,
        'tokenpay'                  => 0,
        'tokenpreferential'         => 0,
        'tokenrecycling'            => 0,
        // common statistics
        'new_member_count'          => 0,
        'first_depositmember_count' => 0,
        'first_depositamount_sum' => 0,
        'others'                    => '',
        // src from root_agent_review
        'new_agent_count' => 0,
        'new_agent_amount' => 0.00,
        // src from root_withdraw_review
        'tokenadministration' => 0.00,
        'realcash_info' => null,
        'transaction_detail' => null,
    ];

    // 取得存取款所有類別資訊
    $stat_result = runSQLall($stat_sql);
    $stmt_global_result = runSQLall($stmt_global_sql);
    // 除錯用
    $statistics = [];

    if($stat_result[0] > 0) {
        $passbookremix = array_slice($stat_result, 1);

        // passbook: 取得所有 transcation_category key-value array
        array_walk($passbookremix, function($row, $key) use (&$statistics, &$record) {
            $statistics[$row->transaction_category] = $row;

            // 未排除出納帳號的情況
            // if($row->withdrawal_sum != $row->deposit_sum) {
            //     var_dump($row);
            //     die('存取款總和不相等，請檢查 passbook 資料正確信');
            // }

            if(isset($record[$row->transaction_category])) $record[$row->transaction_category] += $row->balance_sum;

            if ($row->realcash == 1) {
                $record['realcash_info'][$row->transaction_category] = $row->balance_sum ?? 0;
            }

            // 增加 transaction_detail 欄位(JSON)
            $currency_type = str_replace('passbook', '', $row->src);
            if ($row->realcash == 1) {
                $record['transaction_detail'][$row->transaction_category][$currency_type]['realcash'] = $row->balance_sum ?? null;
            } elseif ($row->realcash == 0) {
                $record['transaction_detail'][$row->transaction_category][$currency_type]['not_realcash'] = $row->balance_sum ?? null;
            }
        });
        $record['realcash_info'] = json_encode($record['realcash_info']);
        $record['transaction_detail'] = json_encode($record['transaction_detail']);
    }

    if($stmt_global_result[0] > 0) {
        $globalsiteinfo = (array) $stmt_global_result[1];
        array_walk($globalsiteinfo, function($value, $key) use (&$statistics, &$record) {
            if($value === null) return;
            $statistics[$key] = $value;
            $record[$key] = $value;
        });
    }

    $record['realcash_info'] = is_null($record['realcash_info']) ? '[]' : $record['realcash_info'];
    $record['transaction_detail'] = is_null($record['transaction_detail']) ? '[]' : $record['transaction_detail'];

    var_dump($record);
    // die;
    return $record;
}

// -----------------------------------------------------------------------------

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// Preserve for cli --param=value
function arguments($argv) {
    $ARG = [];
    foreach ($argv as $key => $value) {
        $val = $argv[$i];
        if (preg_match('/^[-]{1,2}([^=]+)=([^=]+)/', $val, $match)) $ARG[$match[1]] = $match[2];
    }
    return $ARG;
}

// ----------------------------------
// 本程式使用的 function END
// ----------------------------------

// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------

if(PHP_SAPI != 'cli') die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
// var_dump($_SERVER);
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
// if(isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
//   die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
// }
//if(isset($_SERVER['USER']) AND $_SERVER['USER'] == 'nginx' ) {
//  die('不允許使用網頁執行，請確認你的呼叫來源。');
//}
// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('AST'));
date_timezone_set($date, timezone_open('AST'));
if(isset($argv[3]) AND validateDate($argv[3], 'Y-m-d')){
    $current_datepicker = date_format($date, 'Y-m-d');
    if(strtotime($argv[3]) <= strtotime($current_datepicker)){
        $current_datepicker = $argv[3];
    }else{
        die('future datetime(date)!');
    }
}elseif(isset($argv[3]) AND !validateDate($argv[3], 'Y-m-d')){
    die('error date format(Y-m-d) !');
}else{
    $current_datepicker = date_format($date, 'Y-m-d');
}
// 拆解日期為年、月、日，用來組成要更新的時間用
$current_datepicker_y = date('Y',strtotime($current_datepicker));
$current_datepicker_m = date('m',strtotime($current_datepicker));
$current_datepicker_d = date('d',strtotime($current_datepicker));

if(isset($argv[4]) AND validateDate($argv[4], 'H:i:s')){
    $current_date = date_format($date, 'H:i:s');
    if($argv[4] <= $current_date){
        $current_datepicker = $argv[3];
    }elseif(strtotime($current_datepicker) >=  strtotime(date_format($date, 'Y-m-d'))){
        die('future datetime(time)!');
    }
    $current_date = $argv[4];
}elseif(isset($argv[4]) AND !validateDate($argv[4], 'Y-m-d')){
    die('error time format(H:i:s) !');
}else{
    $current_date = date_format($date, 'H:i:s');
}
// 拆解日期為時、分，用來組成要更新的時間用
$current_timepicker_hour = date('H',strtotime($current_date));
$current_timepicker_min = date('i',strtotime($current_date));

// 取得更新的時間區間，預設每 10 分鐘一次
if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
  if(isset($argv[2]) AND filter_var($argv[2], FILTER_VALIDATE_INT) ){
        $time_interval = filter_var($argv[2], FILTER_VALIDATE_INT); #default interval: 0/10/20/30
  }else{
        $time_interval = 10;
  }
  $argv_check = $argv[1];
}else{
  // command 動作 時間
  echo "Command: [test|run] time_interval Y-m-d h:i:s [web|sql] updatelog_id force_update=[0|1]\n";
  echo "Example: run 10 2017-11-06 03:00:00 web 0 0\n";
  // echo "上述以 10 分鐘為間隔計算出\n";
  echo "
    [test|run] 以外的值出現此幫助訊息，test 不更新資料表; run 為實際更新
    time_interval 時間區間，單位為分鐘，預設 10 分，需小於 30
    Y-m-d 美東 date
    h:i:s 美東 time
    [web|sql] web 寫入 tmp, sql 保留 web socket
    updatelog_id 前一參數為 sql 時生效
    force_update: 0 為忽略已存在資料，1 為覆寫已存在資料\n\n";
  die();
}

// 計算起始及結束時間
if($time_interval <= 30 AND $time_interval != 0){
    $time_interval_str = '-'.$time_interval.' min';
    $chk_time_min = floor($current_timepicker_min/$time_interval)*$time_interval;
    $chk_time = date('H:i:s',mktime($current_timepicker_hour,$chk_time_min,'00'));
    $chk_time_start = date('H:i:s',mktime($current_timepicker_hour,$chk_time_min-$time_interval,'00'));
    $chk_datetime = date('Y-m-d H:i:s',mktime($current_timepicker_hour,$chk_time_min,'00',$current_datepicker_m,$current_datepicker_d,$current_datepicker_y));
    $chk_datetime_start = date('Y-m-d H:i:s',mktime($current_timepicker_hour,$chk_time_min-$time_interval,'00',$current_datepicker_m,$current_datepicker_d,$current_datepicker_y));
}else{
    $chk_time = date('H:i:s',mktime($current_timepicker_hour,'00','00'));
    $chk_time_start = date('H:i:s',mktime($current_timepicker_hour-1,'00','00'));
    $chk_datetime = date('Y-m-d H:i:s',mktime($current_timepicker_hour,'00','00',$current_datepicker_m,$current_datepicker_d,$current_datepicker_y));
    $chk_datetime_start = date('Y-m-d H:i:s',mktime($current_timepicker_hour-1,'00','00',$current_datepicker_m,$current_datepicker_d,$current_datepicker_y));
}

$current_datepicker = date('Y-m-d',strtotime($chk_datetime_start));

/*
if($argv_check == 'test'){
    //var_dump($current_datepicker_y);
    //var_dump($current_datepicker_m);
    //var_dump($current_datepicker_d);
    var_dump($current_datepicker);
    var_dump($current_date);
    var_dump($chk_time_start);
    var_dump($chk_time );
    var_dump($chk_datetime_start);
    var_dump($chk_datetime );
}*/

if(isset($argv[5]) AND $argv[5] == 'web'){
    $web_check = 1;
    $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
    $reload_file = dirname(__FILE__).'/tmp_dl/statistics_daily_site_update.tmp';
    file_put_contents($reload_file,$output_html);

// Preverse for web socket
} elseif(isset($argv[5]) AND $argv[5] == 'sql') {
    if(isset($argv[6]) AND filter_var($argv[6], FILTER_VALIDATE_INT)){
        $web_check = 2;
        $updatelog_id = filter_var($argv[6], FILTER_VALIDATE_INT);
        $updatelog_sql = "SELECT * FROM root_bonusupdatelog WHERE id ='$updatelog_id';";
        $updatelog_result = runSQL($updatelog_sql);
        if($updatelog_result == 0) die('No root_bonusupdatelog ID');
    } else die('No root_bonusupdatelog ID');
} else $web_check = 0;

$force_update = 0;
if(isset($argv[7]) AND filter_var($argv[7], FILTER_VALIDATE_INT)) $force_update = filter_var($argv[7], FILTER_VALIDATE_INT);
$action_mode = ($force_update) ? 'Update' : 'Insert';

$logger ='';
$logger_timerange = $chk_time_start.'~'.$chk_time;
// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// round 1. 新增或更新會員於時間區間的資料
// ----------------------------------------------------------------------------

if($web_check == 1){
    $output_html  = '<p align="center">round 1. 新增或更新會員於時間'.$logger_timerange.'區間的資料 - 更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
    file_put_contents($reload_file,$output_html);
}elseif($web_check == 2){
    $updatlog_note = 'round 1. 新增或更新會員於時間'.$logger_timerange.'區間的資料 - 更新中';
    $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
    if($argv_check == 'test'){
        echo $updatelog_sql;
    }elseif($argv_check == 'run'){
        $updatelog_result = runSQLall($updatelog_sql);
    }
}else{
    echo "round 1. 新增或更新會員於時間 $logger_timerange 區間的資料 - 開始\n";
}

// ----------------------------------------------------------------------------
// 取得更新資料
// ----------------------------------------------------------------------------

$stats_insert_count = 0;
$stats_update_count = 0;

// 查詢資料是否已經存在資料庫內, 存在 update , 不存在則 insert
$check_sql = <<<SQL
    SELECT id FROM root_statisticssite WHERE dailydate = '$current_datepicker' AND dailytime_start = '$chk_time_start' AND dailytime_end = '$chk_time';
SQL;

$check_result  = runSQLall($check_sql);
$record = stat_site($current_datepicker, $chk_datetime_start, $chk_datetime);

// 區間以計算且不強制更新
if (empty($record)) {
  $logger = 'False, '.$action_mode.' 統計的資料 '.$current_datepicker.'_'.$chk_time_start.'_'.$chk_time;
  $logger .= "\n異常狀況：無統計資料，請查檢程式";

} elseif(($check_result[0] > 0) AND !$force_update) $logger = "區間內有資料且不強制更新\n";
else {
    // 生成 insert 與更新用的 update 語句
    if($check_result[0] > 0) {
        $column2value_array = array_map(function($key, $value) { return $key . "='" . $value . "'"; }, array_keys($record), $record);
        $column2value = implode(',', $column2value_array);
        $column2value = str_replace("'now()'", 'now()', $column2value);

        $update_sql = "UPDATE root_statisticssite SET $column2value WHERE id = {$check_result[1]->id};";
    }

    $columns = implode(array_keys($record), ',');
    $insert_sql = "INSERT INTO root_statisticssite ($columns) VALUES ";
    $_values = implode($record, "','");
    str_replace("'now()'", 'now()', $_values);
    $insert_sql .= "('" . $_values . "');";

    if($argv_check == 'test') echo @$update_sql, "\n", @$insert_sql, "\n";
    elseif($argv_check == 'run') {
        if($check_result[0] > 0) {
          $stats_update_count += 1;
          $runSQLrest = runSQLall($update_sql);
        } else {
            $stats_insert_count += 1;
            $runSQLrest = runSQLall($insert_sql);
        }
    }

    if(isset($runSQLrest[0]) && $runSQLrest[0] > 0) $logger = 'Success, '.$action_mode.' 統計的資料 '.$record['dailydate'].'_'.$record['dailytime_start'].'_'.$record['dailytime_end'];
    else $logger = 'False, '.$action_mode.' 統計的資料 '.$record['dailydate'].'_'.$record['dailytime_start'].'_'.$record['dailytime_end'];
}
echo $logger, "\n";
// --------------------------------------------
// MAIN END
// --------------------------------------------

// ----------------------------------------------------------------------------
// 統計結果
// ----------------------------------------------------------------------------
$run_report_result = "
  統計此時間區間插入(Insert)的資料 =  $stats_insert_count ,\n
  統計此時間區間更新(Update)   =  $stats_update_count";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = $run_report_result."\n累積花費時間: ".$program_time ." \n";
if($web_check == 1){
    $logger_html = nl2br($logger).'<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';
    file_put_contents($reload_file,$logger_html);
}elseif($web_check == 2){
    $updatlog_note = nl2br($logger);
    $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'1000\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
    if($argv_check == 'test'){
        echo $updatelog_sql;
    }elseif($argv_check == 'run'){
        $updatelog_result = runSQLall($updatelog_sql);
    }
}else{
    echo $logger;
}
// --------------------------------------------
// 統計結果 END
// --------------------------------------------

?>
