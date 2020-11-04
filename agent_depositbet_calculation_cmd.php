#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- 聯營股東損益計算 command 模式
// File Name: agent_profitloss_calculation_cmd.php
// Author   :yaoyuan
// Related  :
// Log      :
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 此計算程式所使用的 LIB
require_once dirname(__FILE__) ."/agent_depositbet_calculation_lib.php";

// set memory limit
ini_set('memory_limit', '200M');

// At start of script
$time_start = microtime(true);
$origin_memory_usage = memory_get_usage();

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

/* 程式 debug 開關, 0 = off , 1= on , 2 ~11 等級細節內容不同
  debug_num，說明，變數名稱，行號
    2 = 列出遊戲分類            = $casino_game_categories
    3 = 取出區間內，所有帳號在日報加總資料 = $statistics_daily_report_list
    4 = 列出佣金規則            = $commission_rules
    5 = 算出下線總投注量          = $downline_allbet
    6 = 算出下線總投注量          = $map_agent_commission_grade
    7 = 傳回佣金明細、總表          = $calculate_total_commission

 */
$debug = 0;

// Main
// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// var_dump($_SERVER);
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if(isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
  die('禁止使用网页呼叫，来源错误，请使用命令列执行。');
}
//if(isset($_SERVER['USER']) AND $_SERVER['USER'] == 'nginx' ) {
//  die('不允許使用網頁執行，請確認你的呼叫來源。');
//}
// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// var_dump($argv);

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
date_timezone_set($date, timezone_open('America/St_Thomas'));
$current_date = date_format($date, 'Y-m-d');
$end_date = date_format($date, 'Y-m-d');

if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
  if(isset($argv[2]) AND validateDate($argv[2], 'Y-m-d') ){
		if($argv[2] <= $current_date){
	    //如果有的話且格式正確, 取得日期. 沒有的話中止
			$current_datepicker = $argv[2];
      $end_datepicker = $argv[2];

      if(isset($argv[3]) AND validateDate($argv[3], 'Y-m-d') ) {
        $end_datepicker = $argv[3];
      }
		}else{
		  // command 動作 時間
		  echo "command [test|run] YYYY-MM-DD \n";
		  die('no test and run');
		}
  }else{
		$current_datepicker = $current_date;
    $end_datepicker = $end_date;
		// $current_datepicker = date('Y-m-d');
  }
  $argv_check = $argv[1];
	$current_datepicker_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker.'23:59:59 -04')+8*3600).'+08:00';
}else{
  // command 動作 時間
  echo "command [test|run] YYYY-MM-DD \n";
  die('no test and run');
}

if(isset($argv[4]) AND $argv[4] == 'web' ){
	$web_check = 1;
	// $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	// $file_key = sha1('agentbonus'.$current_datepicker);
	// $reload_file = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$file_key.'.tmp';
	// file_put_contents($reload_file,$output_html);
} else {
	$web_check = 0;
}

$logger = '';

// 取得遊戲分類
$casino_game_categories = get_casino_game_categories();
if ($debug == 2) {var_dump ($casino_game_categories);die();}

// 取得區間內，日報所有加總資料
$statistics_daily_report_list = statisticsdailyreport_sum_list($current_datepicker, $end_datepicker, $casino_game_categories);
if ($debug == 3) {var_dump ($statistics_daily_report_list);die();}

if(count($statistics_daily_report_list) < 1) {
  echo "No root_statisticsdailyreport\n";
  die();
}

// 撈出佣金規則
$commission_rules = get_commission_rules();
if ($debug == 4) {var_dump($commission_rules);die();}

// 算出下線的總投注量
$downline_allbet = downline_allbet($current_datepicker, $end_datepicker, $casino_game_categories);
if ($debug == 5) {var_dump($downline_allbet);die();}

if ($downline_allbet[0] >= 1) {
    unset($downline_allbet[0]);
} else {
    echo "无下线总投注量资料。\n";
    die();
}

// 對映代理商的佣金等級，參數1：代理商下線總投注及佣金名稱，參數2:佣金設定
$map_agent_commission_grade = map_agent_commission_grade($downline_allbet, $commission_rules);
if ($debug == 6) {var_dump($map_agent_commission_grade);die();}

unset($commission_rules, $downline_allbet);

// 傳回佣金明細、總表
$calculate_total_commission = calculate_total_commission($statistics_daily_report_list, $map_agent_commission_grade, $casino_game_categories);
if ($debug == 7) {var_dump($calculate_total_commission);die();}

unset($statistics_daily_report_list, $map_agent_commission_grade, $casino_game_categories);
// var_dump($calculate_total_commission['detail']);die();
// var_dump($calculate_total_commission['detail'],count($calculate_total_commission['summary']));die();
// 組成佣金明細sql陣列
// 總表、明細交易單號，預設以 (c)20180515_useraccount_亂數3碼 為單號，其中 w:代表提款/d:代表存款/md:後台人工存款/mw:後台人工提款/c:佣金計算
$c_transaction_id     = 'c' . date("YmdHis") . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

// 要更新之前，先刪除舊有佣金資料
del_commission_data($current_datepicker, $end_datepicker);

// sql批次處理初始化
$batched_sql_executor = new BatchedSqlExecutor(100);

// 寫入佣金明細
// $insert_detail_buffer = [];
foreach ($calculate_total_commission['detail'] as $detail_table) {
  foreach ($detail_table as $parent_id => $member_detail) {
    // var_dump($member_detail);die();
        $batched_sql_executor->push(
            insert_update_commission_depositbet_detail_sql([
                // $insert_detail_buffer[] = insert_update_commission_depositbet_detail_sql([
                'member_id'              => $member_detail['member_id'],
                'member_account'         => $member_detail['member_account'],
                'member_therole'         => $member_detail['member_therole'],
                'parent_id'              => $member_detail['parent_id'],
                'parent_account'         => $member_detail['parent_account'],
                'parent_therole'         => $member_detail['parent_therole'],
                'agent_commissionrule'   => $member_detail['agent_commissionrule'],
                'downline_effective_bet' => $member_detail['downline_effective_bet'],
                'member_bets'            => $member_detail['member_bets'],
                'member_profitlost'      => $member_detail['member_profitlost'],
                'all_deposit'            => $member_detail['all_deposit'],
                'deposit_comsion_set'    => $member_detail['deposit_comsion_set'],
                'deposit_comsion'        => $member_detail['deposit_comsion'],
                'valid_bet_comsion_sum'  => $member_detail['valid_bet_comsion_sum'],
                'commission_detail'      => $member_detail['commission_detail'],
                'start_date'             => $current_datepicker,
                'end_date'               => $end_datepicker,
                'updatetime'             => (new \DateTime())->format('Y-m-d H:i:s'),
                'is_payout'              => 'false',
                'transaction_id'         => $c_transaction_id,
                'reach_bet_amount'       => $member_detail['reach_bet_amount'],
            ])
        )
        ;
        // print_r($insert_detail_buffer);die();
    }

}
// die();
// var_dump($calculate_total_commission['summary']);die();
// 寫入佣金總表
foreach ($calculate_total_commission['summary'] as $summary_table) {
    $batched_sql_executor->push(
        insert_update_commission_depositbet_summary_sql([
            // $insert_detail_buffer[] = insert_update_commission_depositbet_summary_sql([
            'agent_id'       => $summary_table['agent_id'],
            'agent_account'  => $summary_table['agent_account'],
            'agent_therole'  => $summary_table['agent_therole'],
            'commission'     => $summary_table['commission'],
            'valid_member'   => $summary_table['valid_member'],
            'valid_bet_sum'  => $summary_table['valid_bet_sum'],
            'profitlost_sum' => $summary_table['profitlost_sum'],
            'is_payout'      => 'false',
            'transaction_id' => $c_transaction_id,
            'start_date'     => $current_datepicker,
            'end_date'       => $end_datepicker,
            'updatetime'     => 'now()',
            'agent_commissionrule'       => $summary_table['agent_commissionrule'],
            'downline_effective_bet'     => $summary_table['downline_effective_bet'],
            'reach_bet_amount'           => $summary_table['reach_bet_amount'],
            'effective_member_set'       => $summary_table['effective_member_set'],
            'effective_membership_pass'  => $summary_table['effective_membership_pass' ],
        ])
    )
    ;
}
// print_r($insert_detail_buffer);die();

// 執行不足批量之sql
$batched_sql_executor->execute();

echo 'Total execution time in seconds: ' . round( (microtime(true) - $time_start), 3) . " sec\n";
echo 'memmory usage: ' . round( (memory_get_usage() - $origin_memory_usage) / (1024 * 1024), 3) . " MB.\n";

?>
