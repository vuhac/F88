#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 加盟金計算 -- 獨立排程執行
// File Name:	radiationbonus_organization_cmd.php
// Author:		Dright
// Related:   DB root_favorable(會員反水設定及打碼設定)
// 							preferential_calculation.php
// 							preferential_calculation_action.php
// Desc: 由每日報表，統計投注額後，依據設定比例 1% ~ 3% ，發放反水給予會員。
// 反水可以轉帳到代幣帳戶代幣帳戶可以設定稽核，也可以轉帳到現金帳戶
// Log:
// ----------------------------------------------------------------------------
// How to run ?
// usage command line : /usr/bin/php70 preferential_calculation_cmd.php test/run 2017-06-06
// ----------------------------------------------------------------------------

//die('反水計算功能因為修改後故障, 目前正在修復中');

// ----------------------------------------------------------------------------
// session_start();

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/radiationbonus_organization_lib.php";

require_once dirname(__FILE__) ."/lib_proccessing.php";

// set memory limit
ini_set('memory_limit', '200M');


// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// set memory limit
// ini_set('memory_limit', '20M');


// 程式 debug 開關, 0 = off , 1= on , 2 ~5 等級細節內容不同
$debug = 0;



// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// var_dump($_SERVER);
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if(isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
  die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}
//if(isset($_SERVER['USER']) AND $_SERVER['USER'] == 'nginx' ) {
//  die('不允許使用網頁執行，請確認你的呼叫來源。');
//}
// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------

// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/Panama'));
date_timezone_set($date, timezone_open('America/Panama'));
$current_date = date_format($date, 'Y-m-d');


// 判斷 command 的執行模式
if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
  if(isset($argv[2]) AND validateDate($argv[2], 'Y-m-d') ){
    //如果有的話且格式正確, 取得日期. 沒有的話中止
    $current_datepicker = $argv[2];
  }else{
		$current_datepicker = $current_date;
		// $current_datepicker = date('Y-m-d');
  }
  $argv_check = $argv[1];
	$current_datepicker_gmt = gmdate('Y-m-d H:i:s.u',strtotime($current_datepicker.'23:59:59 -05')+8*3600).'+08:00';
}else{
  // command 動作 時間
  echo "command [test|run] YYYY-MM-DD \n";
  die('no test and run');
}


if(isset($argv[3]) AND $argv[3] == 'web' ){
  $file_key = sha1('franchise'.$current_datepicker);
  $reload_file = dirname(__FILE__) .'/tmp_dl/franchise_'.$file_key.'.tmp';
  $del_log_url = 'radiationbonus_organization_action.php?a=franchise_del&k=' . $file_key;

	$progressMonitor = new WebProgressMonitor($reload_file, $del_log_url);

}else{
	$progressMonitor = new TerminalProgressMonitor;
}


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------


$calculator = new FranchiseBonusCalculator($current_datepicker);

// check existence of daily report
if(count($calculator->statistics_daily_report_list) < 1) {
  $progressMonitor->notifyProccessingComplete("No root_statisticsdailyreport\n");
	return;
}

$progressMonitor->notifyProccessingStart('计算加盟金 - 开始...');
$progressMonitor->setTotalProgressStep(count($calculator->statistics_daily_report_list));

// get first level agent
$first_level_agent_sql = "SELECT
  member_id as id,
  member_account as account,
  root_member.status,
  member_therole as therole,
  member_parent_id as parent_id,
  root_member.commissionrule,
  root_member.favorablerule,
  root_member.feedbackinfo
FROM root_statisticsdailyreport
  LEFT JOIN root_member on root_member.id = root_statisticsdailyreport.member_id
WHERE root_statisticsdailyreport.dailydate = :date
  AND root_statisticsdailyreport.member_parent_id = :root_id
  AND root_statisticsdailyreport.member_therole = :role
ORDER BY parent_id, id
;";

$first_level_agent_result = runSQLall_prepared($first_level_agent_sql, [':date' => $current_datepicker, ':root_id' => 1, ':role' => 'A'], null, 0, 'r');



// calculate according first agent
foreach ($first_level_agent_result as $agent) {

  // get member list according to date
  $member_list = MemberTreeNode::getMemberListByDate($current_datepicker, $agent->id);

  // build member tree
  $tree_root = $member_list[(int)($agent->id)];
  MemberTreeNode::buildMemberTree($tree_root, $member_list, function($member) use ($current_datepicker) {
    $member->node_data = new FranchiseBonusData($member, $current_datepicker);

    if($member->isFirstLevelAgent()) {
      $member->node_data->franchise_bonus_rule = new FranchiseBonusRuleData($member);
    }

  });

  // calculate
  $calculator->setMemberTree($member_list, (int)($agent->id));
  $calculator->calculate();

  // init sql executor
  $batched_sql_executor = new BatchedSqlExecutor();

  // insert or update calculate result
  foreach($member_list as $account => $member) {

    $batched_sql_executor->push(
      $member->node_data->toSql()
    );
    // echo $member->node_data->all_favorablerate_amount . "\n";

    $stats_update_count++;

    // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
    $progressMonitor->forwardProgress();
    $progressMonitor->notifyProccessingProgress('计算中...');
  }

  // execute rest sql statements
  $batched_sql_executor->execute();

  // cleanup
  unset($member_list);
}
// end of calculate according first agent



// output proccess summary
$run_report_result = "統計此時間區間更新的資料 =  $stats_update_count\n";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = round($program_end_time - $program_start_time, 3);
$summary = $run_report_result."\n累積花費時間: ".$program_time ." \n";

$progressMonitor->notifyProccessingComplete($summary);

// --------------------------------------------
// MAIN END
// --------------------------------------------

?>
