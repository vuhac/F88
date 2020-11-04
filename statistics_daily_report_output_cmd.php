<?php
// ----------------------------------------------------------------------------
// Features:    後台 - 每日營收日結報表 in command mode run
// File Name:    statistics_daily_output_cmd.php
// Author:        Barkley
// DB table:  root_statisticsdailyreport  每日營收日結報表
// Related:   每日營收報表, 搭配的程式功能說明
// statistics_daily_immediately.php    後台 - 即時統計 - 每日營收日結報表, 要修改下面的程式增加項目的時候，需要先使用這只程式即時測試函式並驗證。
// statistics_daily_report.php         後台 - 每日營收日結報表(讀取已生成資料庫頁面), 透過 php system 功能呼叫 statistics_daily_output_cmd.php 執行, 主要都從這個程式開始呼叫。
// statistics_daily_report_lib.php     後台 - 每日營收日結報表 - 專用函式庫(計算資料使用函式, 每個統計項目的公式都放這裡)
// statistics_daily_report_action.php  後台 - 每日營收日結報表動作程式 - 透過此程式呼叫 php system command 功能, 及其他後續擴充功能.
// statistics_daily_output_cmd.php     後台 - 每日營收日結報表(命令列模式, 主要用來排程生成日報表)
// command example: /usr/bin/php70 /home/testgpk2demo/web/begpk2/statistics_daily_report_output_cmd.php run 2017-02-26
// Log:
// 2017.2.27 改寫,原本的即時計算移除.以資料庫為主,排程定時統計。
// ----------------------------------------------------------------------------

// session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
// require_once dirname(__FILE__) ."/lib.php";

// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
require_once dirname(__FILE__) . "/config_betlog.php";
// 日結報表函式庫
require_once dirname(__FILE__) . "/statistics_daily_report_lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

function init_statistics_daily_report_data()
{
    return (object) [
        'agent_review_reult' => 0,

        'gtoken_tokendeposit' => 0,
        'gtoken_tokenfavorable' => 0,
        'gtoken_tokenpreferential' => 0,
        'gtoken_tokenpay' => 0,
        'gtoken_tokengcash' => 0,
        'gtoken_tokenrecycling' => 0,
        'gtoken_tokenadministrationfees' => 0,
        'gtoken_tokenadministration' => 0,
        'gtoken_apitokenwithdrawal' => 0,
        'gtoken_apitokendeposit' => 0,

        'gtoken_cashdeposit' => 0,
        'gtoken_company_deposits' => 0,
        'gtoken_payonlinedeposit' => 0,
        'gtoken_cashtransfer' => 0,
        'gtoken_cashwithdrawal' => 0,
        'gtoken_cashgtoken' => 0,
        'gtoken_cashadministrationfees' => 0,
        // 'gtoken_apicashwithdrawal' => 0,
        // 'gtoken_apicashdeposit' => 0,

        'gcash_tokendeposit' => 0,
        'gcash_tokenfavorable' => 0,
        'gcash_tokenpreferential' => 0,
        'gcash_tokenpay' => 0,
        'gcash_tokengcash' => 0,
        'gcash_tokenrecycling' => 0,
        'gcash_tokenadministrationfees' => 0,
        'gcash_tokenadministration' => 0,
        // 'gcash_apitokenwithdrawal' => 0,
        // 'gcash_apitokendeposit' => 0,

        'gcash_cashdeposit' => 0,
        'gcash_company_deposits' => 0,
        'gcash_payonlinedeposit' => 0,
        'gcash_cashtransfer' => 0,
        'gcash_cashwithdrawal' => 0,
        'gcash_cashgtoken' => 0,
        'gcash_cashadministrationfees' => 0,
        'gcash_apicashwithdrawal' => 0,
        'gcash_apicashdeposit' => 0,

        'casino_all_bets' => 0,
        'casino_all_wins' => 0,
        'casino_all_profitlost' => 0,
        'casino_all_count' => 0,

        'ec_sales' => 0,
        'ec_cost' => 0,
        'ec_profitlost' => 0,
        'ec_count' => 0,

        'member_gcash' => 0,
        'member_gtoken' => 0,
        'member_commission' => 0,
        'member_prefer' => 0,
        'member_bonus' => 0,

        'realcash_info' => [],
        'betlog_detail' => [],
        'transaction_detail' => [],
    ];
}

function init_statistics_daily_report_detail_data(&$casino_game_categories)
{

    $attributes = [];

    $attributes['betlog_counts'] = [];

    return (object) $attributes;
}

function get_member_casino_accounts($member, &$casino_game_categories)
{
    $casino_accounts_array = json_decode($member->casino_accounts, true);
    $casino_accounts = [];

    foreach (array_keys($casino_game_categories) as $casino) {
        $casino_accounts[] = $casino_accounts_array[strtoupper($casino)]['account'] ?? '';
    }

    return array_unique($casino_accounts);
}

function get_accounts_info(&$member_list, &$casino_game_categories)
{
    $r = [
        'member_accounts' => [],
        'casino_accounts' => [],
        'ec_accounts' => [],
    ];

    foreach ($member_list as $member) {
        $r['member_accounts'][] = $member->account;

        if ($member->ec_verifyresult == 1 && !empty($member->ec_account)) {
            $r['ec_accounts'][] = $member->ec_account;
        }

        $casino_accounts = get_member_casino_accounts($member, $casino_game_categories);

        foreach ($casino_accounts as $casino_account) {
            if (!empty($casino_account)) {
                $r['casino_accounts'][] = $casino_account;
            }
        }

    }

    return $r;
}

// set memory limit
ini_set('memory_limit', '200M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// 程式 debug 開關, 0 = off , 1= on
$debug = 1;

// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// var_dump($_SERVER);
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if (isset($_SERVER['HTTP_USER_AGENT']) or isset($_SERVER['SERVER_NAME'])) {
    die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}
//if(isset($_SERVER['USER']) AND $_SERVER['USER'] == 'nginx' ) {
//  die('不允許使用網頁執行，請確認你的呼叫來源。');
//}
// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------
if (isset($argv[1]) and ($argv[1] == 'test' or $argv[1] == 'run')) {
    if (isset($argv[2]) and validateDate($argv[2], 'Y-m-d')) {
        //如果有的話且格式正確, 取得日期. 沒有的話中止
        $current_datepicker = $argv[2];
    } else {
        // 資料不正確，則以目前日期 EST 為執行的時間。

        // get EST time UTC-5 , 用美東時間來產生每天的日期並寫依序計算。
        //$est_date = gmdate('Y-m-d H:i:s',time() + -4*3600);
        //$current_datepicker = gmdate('Y-m-d',time() + -4*3600);
        /*
        // 跨日後的第一個小時, 仍是計算前一天的值
        // # 每日營收日結報表(生成資料庫)
        // 5 * * * * /usr/bin/php70 /home/testgpk2demo/web/begpk2/statistics_daily_report_output_cmd.php run
        // use crontab to run
        -4                       +8 CST
        2017-04-01 11    2017-04-01 23
        2017-04-01 12    2017-04-01 24
        2017-04-01 13    2017-04-02 00
        2017-04-01 14    2017-04-02 01
        ...
        2017-04-01 23    2017-04-02 11
        2017-04-01 24    2017-04-02 12
        2017-04-02 01    2017-04-02 13
        2017-04-02 02    2017-04-02 14

        -6                +8 CST
        2017-04-01 23    2017-04-02 13
        2017-04-01 24    2017-04-02 14
        2017-04-02 01    2017-04-02 15
        2017-04-02 02    2017-04-02 16

         */
        // $current_datepicker = gmdate('Y-m-d H:i:s',time() + -4*3600);
        $current_datepicker = gmdate('Y-m-d', time()+-6 * 3600);
        //$current_datepicker = gmdate('Y-m-d',time() + -6*3600);
        //$current_datepicker = date('Y-m-d');
        //echo '日期格式有問題，請確定有且格式正確，需要為 YYYY-MM-DD 的格式';
    }
    $argv_check = $argv[1];
    $current_datepicker_gmt = gmdate('Y-m-d H:i:s.u', strtotime($current_datepicker . '23:59:59 -04') + 8 * 3600) . '+08:00';
} else {
    // command 動作 時間 [分段的處理量] [第幾次分段]
    echo "command [test|run] YYYY-MM-DD [分段的處理量] [第幾次分段] \n";
    die('no test and run');
}

if (isset($argv[3]) and $argv[3] == 'web') {
    $file_key = sha1('dailyreport' . $argv[2]);
    $reload_file = dirname(__FILE__) . '/tmp_dl/dailyreport_' . $file_key . '.tmp';
    $del_log_url = 'statistics_daily_report_action.php?a=dailyreport_del&k=' . $file_key;

    $progressMonitor = new WebProgressMonitor($reload_file, $del_log_url);
} else {
    $progressMonitor = new TerminalProgressMonitor;
}
var_dump($progressMonitor);

// 每次的處理量 default = 100000 , 100K , 如果使用者數量超過的時候, 需要在調整
//$current_page_no = filter_var($argv[3], FILTER_SANITIZE_NUMBER_INT);
// 起始頁面 default = 0
//$current_per_size = filter_var($argv[4], FILTER_SANITIZE_NUMBER_INT);
// 先設定 10 萬，如果會員數量超過的時候，再來修正。
$current_per_size = 10000;
// 計算當前之分頁的起始紀錄

// ----------------------------------
// 判斷是否有 $argv_check 參數，有參數的話才開始
// ----------------------------------
if ($argv_check == 'test') {
    //var_dump($_SERVER);
    // 顯示帶入的參數
    var_dump($current_datepicker);
    // var_dump($current_page_no);
    var_dump($current_per_size);

    die();
}

// 每日營收日結報表計算
$progressMonitor->notifyProccessingStart('每日营收日结报表更新中...');

// 搜尋「會員帳號」、「會員+錢包」資訊 , 有限制資料數量
$member_list_sql = "SELECT
  root_member.id,
  root_member.account,
  root_member.therole,
  root_member.parent_id,
  root_member_wallets.gcash_balance,
  root_member_wallets.gtoken_balance,
  root_member_wallets.casino_accounts,
  root_member_opencart.ec_account,
  root_member_opencart.ec_verifyresult
  FROM root_member
    LEFT JOIN root_member_wallets ON root_member.id = root_member_wallets.id
    LEFT JOIN root_member_opencart ON root_member.id = root_member_opencart.id
  WHERE root_member.enrollmentdate <= '$current_datepicker_gmt' OR root_member.enrollmentdate IS NULL ORDER BY root_member.id ASC ";

$casino_game_categories = get_casino_game_categories();

$member_paginator = new Paginator($member_list_sql, $current_per_size);

# 判斷 root_member 是否有值
if ($member_paginator->total < 1) {
    $logger = '(x)會員資料有錯誤。';
    echo $logger;
    die();
}

$progressMonitor->setTotalProgressStep($member_paginator->total);

// 計算「寫入」資料庫的筆數
$insert_count = 0;

// init sql executor
$batched_sql_executor = new BatchedSqlExecutor(200);

// 代理商審查
$agent_review_records = agent_review($current_datepicker);

// calculate daily reports according pagination
for (
    $member_list_result = $member_paginator->getCurrentPage()->data;
    count($member_list_result) > 0;
    $member_list_result = $member_paginator->getNextPage()->data
) {

    $accounts_info = get_accounts_info($member_list_result, $casino_game_categories);

    // get bettingrecords from remix table
    $bettingrecords = get_bettingrecords($current_datepicker, $accounts_info['casino_accounts']);

    $gcash_summary_records = gcash_summary($current_datepicker, $accounts_info['member_accounts']);

    $gtoken_summary_records = gtoken_summary($current_datepicker, $accounts_info['member_accounts']);

    $ec_order_summary = [];
    if ($config['website_type'] != 'casino') {
        $ec_order_summary = get_ec_order_summary($current_datepicker, $accounts_info['ec_accounts']);
    }

    $receivemoney_summary_records = receivemoney_summary($current_datepicker, $accounts_info['member_accounts']);

    // start of one page loop
    foreach ($member_list_result as $member) {

        // ----------------------------------------------------
        // 開始整理注單資料庫的資料
        // ----------------------------------------------------
        $statistics_daily_report_data_obj = init_statistics_daily_report_data();
        $statistics_daily_report_detail_data_obj = init_statistics_daily_report_detail_data($casino_game_categories);

        // ----------------------------------------------------
        // 會員加盟金 -- 取得 agent_review() 函式，帶入「會員帳號」、「今日時間」
        // ----------------------------------------------------
        $agent_review_result = $agent_review_records[$member->account] ?? [];
        if (!empty($agent_review_result)) {
            $statistics_daily_report_data_obj->agent_review_reult = $agent_review_result['amount'];
        }

        // ----------------------------------------------------
        // CASINO 投注的狀況 -- 取得 bettingrecords_summary() 函式，帶入「會員帳號」
        // ----------------------------------------------------
        $summary_result = bettingrecords_summary(
            $bettingrecords,
            get_member_casino_accounts($member, $casino_game_categories)
        );
        // get casino summary
        foreach ($summary_result['casino_summary'] as $casino_summary_attribute => $value) {
            $statistics_daily_report_data_obj->$casino_summary_attribute = $value;
            ($statistics_daily_report_data_obj->betlog_detail)[$casino_summary_attribute] = $value;
        }
        // get casino category summary
        foreach ($summary_result['casino_category_summary'] as $casino_category_summary_attribute => $value) {
            ($statistics_daily_report_data_obj->betlog_detail)[$casino_category_summary_attribute] = $value;
        }
        // get betlog_counts
        $statistics_daily_report_detail_data_obj->betlog_counts = $summary_result['bet_count_summary'];

        // gcash summary
        $gcash_summary = $gcash_summary_records[$member->account] ?? [];
        foreach ($gcash_summary as $category => $detail) {
            if ($category == 'summary') {
                // gcash total balance
                $statistics_daily_report_data_obj->member_gcash = $detail['balance'] - $detail['deposit'] + $detail['withdrawal'];
                continue;
            }

            $statistics_daily_report_data_obj->{'gcash_' . $category} = ($detail[0]['balance'] ?? 0) + ($detail[1]['balance'] ?? 0);

            $category_realcash = ($statistics_daily_report_data_obj->realcash_info)[$category] ?? 0;
            ($statistics_daily_report_data_obj->realcash_info)[$category] = $category_realcash + ($detail[1]['balance'] ?? 0);

            ($statistics_daily_report_data_obj->transaction_detail)[$category]['gcash'] = [
                'not_realcash' => $detail[0]['balance'] ?? 0,
                'realcash' => $detail[1]['balance'] ?? 0,
            ];

        }

        // gtoken summary
        $gtoken_summary = $gtoken_summary_records[$member->account] ?? [];
        foreach ($gtoken_summary as $category => $detail) {
            if ($category == 'summary') {
                // gtoken total balance
                $statistics_daily_report_data_obj->member_gtoken = $detail['balance'] - $detail['deposit'] + $detail['withdrawal'];
                continue;
            }

            $statistics_daily_report_data_obj->{'gtoken_' . $category} = ($detail[0]['balance'] ?? 0) + ($detail[1]['balance'] ?? 0);

            $category_realcash = ($statistics_daily_report_data_obj->realcash_info)[$category] ?? 0;
            ($statistics_daily_report_data_obj->realcash_info)[$category] = $category_realcash + ($detail[1]['balance'] ?? 0);

            ($statistics_daily_report_data_obj->transaction_detail)[$category]['gtoken'] = [
                'not_realcash' => $detail[0]['balance'] ?? 0,
                'realcash' => $detail[1]['balance'] ?? 0,
            ];
        }

        // ec summary
        $ec_account = $member->ec_account;
        if ($member->ec_verifyresult == 1 && isset($ec_order_summary[$ec_account])) {

            $statistics_daily_report_data_obj->ec_sales = $ec_order_summary[$ec_account]['ec_sales'];
            $statistics_daily_report_data_obj->ec_cost = $ec_order_summary[$ec_account]['ec_cost'];
            $statistics_daily_report_data_obj->ec_profitlost = $ec_order_summary[$ec_account]['ec_profitlost'];
            $statistics_daily_report_data_obj->ec_count = $ec_order_summary[$ec_account]['ec_count'];
        }

        // receivemoney summary
        $receivemoney_summary_result = $receivemoney_summary_records[$member->account] ?? [];
        if (!empty($receivemoney_summary_result)) {
            if (!empty($receivemoney_summary_result['tokenpreferential'])) {
                $statistics_daily_report_data_obj->member_prefer = $receivemoney_summary_result['tokenpreferential'];
            }

            if (!empty($receivemoney_summary_result['tokenfavorable'])) {
                $statistics_daily_report_data_obj->member_bonus = $receivemoney_summary_result['tokenfavorable'];
            }

        }

        // ----------------------------------------------------
        // insert or update 寫入每日營收統計報表
        // ----------------------------------------------------
        // insert or update root_statisticsdailyreport
        $batched_sql_executor->push(
            insert_statistics_daily_report_data(
                $member->id,
                $member->parent_id,
                $member->account,
                $member->therole,
                $current_datepicker,
                $statistics_daily_report_data_obj
            )
        );
        // insert or update root_statisticsdailyreport_detail
        $batched_sql_executor->push(
            insert_statistics_daily_report_detail_data(
                $member->id,
                $member->parent_id,
                $member->account,
                $member->therole,
                $current_datepicker,
                $statistics_daily_report_detail_data_obj
            )
        );

        $insert_count++;

        // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
        $progressMonitor->forwardProgress();
        $progressMonitor->notifyProccessingProgress('处理中...');

    }
    // one page loop end

}
// all page loop end

// execute rest sql statements
$batched_sql_executor->execute();

// 算累積花費時間
$program_end_time = microtime(true);
$program_time = round($program_end_time - $program_start_time, 3);

$output_html = "\n目前處理 $current_datepicker, 花費時間: " . $program_time . "秒\n";
$output_html = $output_html . '紀錄已更新' . $insert_count . '筆' . "\n";

$progressMonitor->notifyProccessingComplete($output_html);
