#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 反水計算及反水派送 -- 獨立排程執行
// File Name:	cron/preferential_calculation_cmd.php
// Author:		Barkley,Fix by Ian
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

require_once dirname(__FILE__) ."/lib_proccessing.php";

require_once dirname(__FILE__) ."/preferential_calculation_lib.php";

require_once dirname(__FILE__) . "/lib_file.php";

// xlsx
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// set memory limit
ini_set('memory_limit', '200M');


// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// set memory limit
// ini_set('memory_limit', '20M');

// At start of script
$time_start = microtime(true);
$origin_memory_usage = memory_get_usage();

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
		if($argv[2] <= $current_date){
	    //如果有的話且格式正確, 取得日期. 沒有的話中止
	    $current_datepicker = $argv[2];
		}else{
		  // command 動作 時間
		  echo "command [test|run] YYYY-MM-DD \n";
		  die('no test and run');
		}
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
  $file_key = sha1('preferential'.$current_datepicker);
  $reload_file = dirname(__FILE__) .'/tmp_dl/prefer_'.$file_key.'.tmp';
  $del_log_url = 'preferential_calculation_action.php?a=prefer_del&k=' . $file_key;

	$progressMonitor = new WebProgressMonitor($reload_file, $del_log_url);
}else{
	$progressMonitor = new TerminalProgressMonitor;
}

$logger ='';


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// round 1. 新增或更新會員反水資料
// ----------------------------------------------------------------------------
$progressMonitor->notifyProccessingStart('round 1. 新增或更新會員反水資料 - 更新中...');



//取得新版的反水資料 2017.10.5
//會員反水等級 table Loading , 狀態開啟, 且沒有被刪除的反水設定。

$preferentialCalculator = new PreferentialCalculator($current_datepicker);

// check existence of daily report
if(count($preferentialCalculator->statistics_daily_report_list) < 1) {
	echo "No root_statisticsdailyreport\n";
	return;
}

$progressMonitor->setTotalProgressStep(count($preferentialCalculator->statistics_daily_report_list));

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


// init sql executor
$batched_sql_executor = new BatchedSqlExecutor(200);
$insert_count = 0;

// calculate according first agent
foreach ($first_level_agent_result as $agent) {

  $member_list = MemberTreeNode::getMemberListByDate($current_datepicker, $agent->id);

  $tree_root = $member_list[(int)($agent->id)];
  MemberTreeNode::buildMemberTree($tree_root, $member_list, function($member){
    $member->node_data = new PreferentialCalculationData;
  });
  $preferentialCalculator->setMemberList($member_list, (int)($agent->id));

  // check preferential setting is under 100%
  $check_result = $preferentialCalculator->settingCheck();

  if(! $check_result->passed) {
    echo "反水設定錯誤!\n";
    print_r($check_result->member_with_config_error);

    return;
  }

  // calculate
  $preferentialCalculator->calculate();


  // insert or update commission_dailyreport
  foreach($member_list as $account => $member) {

    // 這次執行的使用者資訊
    $current_run_user_account = $member->account;

    $batched_sql_executor->push(
      insert_or_update_preferential_sql([
        'member_id'                => $member->id,
        'member_account'           => $member->account,
        'member_parent_id'         => $member->parent_id,
        'member_therole'           => $member->therole,
        'dailydate'                => $current_datepicker,
        'favorablerate_level'      => $member->favorablerule,
        'all_bets_amount'          => $member->node_data->all_bets_amount,
        'all_favorablerate_amount' => $member->node_data->all_favorablerate_amount,
        'favorable_limit'          => $member->node_data->favorable_limit,
        'favorable_audit'          => $member->node_data->favorable_audit,
        'all_favorablerate_amount_detail' => json_encode($member->node_data->all_favorablerate_amount_detail),
        'favorable_distribute' => json_encode($member->node_data->favorable_distribute),
        'updatetime' => (new \DateTime())->format('Y-m-d H:i:s'),
      ])
    );

    $insert_count++;

    // 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
    $progressMonitor->forwardProgress();
    $progressMonitor->notifyProccessingProgress('处理中...');

  }

    // cleanup
    unset($member_list);

}
// end of calculate according first agent

// execute rest sql statements
$batched_sql_executor->execute();



// ----------------------------------------------------------------------------
// round 2. 輸出 CSV 檔
// ----------------------------------------------------------------------------
$progressMonitor->resetProgress();
$progressMonitor->notifyProccessingStart('round 2. 輸出 CSV 檔 - 更新中...');

// -----------------------------------------------------------------------
// 列出所有的會員資料及人數 SQL
// -----------------------------------------------------------------------
// 算 root_member 人數
$userlist_sql = "SELECT * FROM root_statisticsdailypreferential WHERE dailydate = '".$current_datepicker."' ORDER BY member_id ASC;";
// var_dump($userlist_sql);
$userlist = runSQLall($userlist_sql);

$progressMonitor->setTotalProgressStep($userlist[0]);

// 判斷 root_member count 數量大於 1
if($userlist[0] >= 1) {
   // -------------------------------------------
  // 寫入 CSV 檔案的抬頭 - -和實際的 table 並沒有完全的對應
  // -------------------------------------------

  $j = 1;
  $csv_data[0][$j++] = '會員上層ID';
  $csv_data[0][$j++] = '會員ID';
  $csv_data[0][$j++] = '會員身份';
  $csv_data[0][$j++] = '會員帳號';
  $csv_data[0][$j++] = '生成日報表的日期(美東時間)';
  $csv_data[0][$j++] = 'ID_PK';
  $csv_data[0][$j++] = '最後更新時間';
  $csv_data[0][$j++] = '會員反水等級';
  $csv_data[0][$j++] = 'MG 電子投注量 ';
  $csv_data[0][$j++] = 'MG 電子會員等級反水比例 ';
  $csv_data[0][$j++] = 'MG 電子會員反水量 ';
  $csv_data[0][$j++] = '總投注量';
  $csv_data[0][$j++] = '總反水量';
  $csv_data[0][$j++] = '會員等級反水上限';
  $csv_data[0][$j++] = '會員等級稽核倍數';
  $csv_data[0][$j++] = '本日已經發送的反水金額';
  $csv_data[0][$j++] = '反水補發的差額';

  // -------------------------------------------
  // 將內容輸出到 檔案 , csv format
  // -------------------------------------------

  // 以會員為主要 key 依序列出每個會員的貢獻金額
  for($i = 1 ; $i <= $userlist[0]; $i++){
    // 存成 csv data
    // 2019/12/27
    $j = 1;
    $csv_data[$i][$j++] = $userlist[$i]->member_id;
    $csv_data[$i][$j++] = $userlist[$i]->member_parent_id;
    $csv_data[$i][$j++] = $userlist[$i]->member_therole;
    $csv_data[$i][$j++] = $userlist[$i]->member_account;
    $csv_data[$i][$j++] = $userlist[$i]->dailydate;
    $csv_data[$i][$j++] = $userlist[$i]->id;
    $csv_data[$i][$j++] = $userlist[$i]->updatetime;
    $csv_data[$i][$j++] = $userlist[$i]->favorablerate_level;
    $csv_data[$i][$j++] = $userlist[$i]->mg_totalwager;
    $csv_data[$i][$j++] = $userlist[$i]->mg_favorable_rate;
    $csv_data[$i][$j++] = $userlist[$i]->mg_favorablerate_amount;
    $csv_data[$i][$j++] = $userlist[$i]->all_bets_amount;
    $csv_data[$i][$j++] = $userlist[$i]->all_favorablerate_amount;
    $csv_data[$i][$j++] = $userlist[$i]->favorable_limit;
    $csv_data[$i][$j++] = $userlist[$i]->favorable_audit;
    $csv_data[$i][$j++] = $userlist[$i]->all_favorablerate_beensent_amount;
    $csv_data[$i][$j++] = $userlist[$i]->all_favorablerate_difference_amount;

    // 原版
    // $j = 0;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->member_id;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->member_parent_id;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->member_therole;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->member_account;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->dailydate;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->id;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->updatetime;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->favorablerate_level;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->mg_totalwager;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->mg_favorable_rate;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->mg_favorablerate_amount;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->all_bets_amount;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->all_favorablerate_amount;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->favorable_limit;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->favorable_audit;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->all_favorablerate_beensent_amount;
    // $csv_data['data'][$i][$j++] = $userlist[$i]->all_favorablerate_difference_amount;


		// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
    $progressMonitor->forwardProgress();
    $progressMonitor->notifyProccessingProgress('处理中...');

  }
  // // -------------------------------------------
  // // 寫入 CSV 檔案的抬頭 - -和實際的 table 並沒有完全的對應
  // // -------------------------------------------

  // $j = 0;
  // $csv_data['table_colname'][$j++] = '會員ID';
  // $csv_data['table_colname'][$j++] = '會員上層ID';
  // $csv_data['table_colname'][$j++] = '會員身份';
  // $csv_data['table_colname'][$j++] = '會員帳號';
  // $csv_data['table_colname'][$j++] = '生成日報表的日期(美東時間)';
  // $csv_data['table_colname'][$j++] = 'ID_PK';
  // $csv_data['table_colname'][$j++] = '最後更新時間';
  // $csv_data['table_colname'][$j++] = '會員反水等級';
  // $csv_data['table_colname'][$j++] = 'MG 電子投注量 ';
  // $csv_data['table_colname'][$j++] = 'MG 電子會員等級反水比例 ';
  // $csv_data['table_colname'][$j++] = 'MG 電子會員反水量 ';
  // $csv_data['table_colname'][$j++] = '總投注量';
  // $csv_data['table_colname'][$j++] = '總反水量';
  // $csv_data['table_colname'][$j++] = '會員等級反水上限';
  // $csv_data['table_colname'][$j++] = '會員等級稽核倍數';
  // $csv_data['table_colname'][$j++] = '本日已經發送的反水金額';
  // $csv_data['table_colname'][$j++] = '反水補發的差額';

  // // -------------------------------------------
  // // 將內容輸出到 檔案 , csv format
  // // -------------------------------------------

  // 有資料才執行 csv 輸出, 避免 insert or update or stats 生成同時也執行 csv 輸出
  // if(isset($csv_data['data'])) {
  //   $filename      = "bonuspreferential_result_".$current_datepicker.'.csv';
  //   $absfilename   = dirname(__FILE__) ."/tmp_dl/$filename";
  //   $filehandle    = fopen("$absfilename","w");
  //   // Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
  //   fwrite($filehandle,chr(0xEF).chr(0xBB).chr(0xBF));
  //   // -------------------------------------------

  //   // 將資料輸出到檔案 -- Title
  //   fputcsv($filehandle, $csv_data['table_colname']);

  //   // 將資料輸出到檔案 -- data
	// 	foreach ($csv_data['data'] as $wline) {
	// 		fputcsv($filehandle, $wline);
	// 	}

  //   fclose($filehandle);
  // }


  // 2019/12/25
  // -----------------------------------------------------------------------
  // 清除快取防亂碼
  if(ob_get_length()){
    ob_end_clean();
  }

  $spredsheet = new Spreadsheet();

  $myworksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spredsheet, '娱乐城反水计算');

  // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
  $spredsheet->addSheet($myworksheet, 0);

  // 總表索引標籤開始寫入資料
  $sheet = $spredsheet->setActiveSheetIndex(0);

  // 寫入資料陣列
  $sheet->fromArray($csv_data,NULL,'A1',true);

  // 自動欄寬
  $worksheet = $spredsheet->getActiveSheet();

  foreach (range('A', $worksheet->getHighestColumn()) as $column) {
    $spredsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
  };

  // 檔案名稱
  $filename    = "bonuspreferential_result_".$current_datepicker;

  $absfilename = "./tmp_dl/".$filename.".xlsx";

  flush();

  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
  header('Cache-Control: max-age=0');

  // 直接匯出，不存於disk
  $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spredsheet, 'Xlsx');
  //  $writer->save('php://output');
  $writer->save($absfilename);
  //-----------------------------------------------------------------------------

}



// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = round($program_end_time - $program_start_time, 3);

$output_html = "\n花費時間: ".$program_time."秒\n";
$output_html = $output_html.'紀錄已更新'.$insert_count.'筆'."\n";

$progressMonitor->notifyProccessingComplete($output_html);

?>
