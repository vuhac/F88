#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 個人分紅傭金發放 -- 獨立排程執行
// File Name:	bonus_commission_agent_payout_cmd.php
// Author:		Ian
// Related:	bonus_commission_agent.php
// 					bonus_commission_agent_action.php
// 				DB table:  root_receivemoney 彩金發放
// 				DB table:  root_statisticsbonusagent 營運獎金報表, 資料由每日統計報表生成, 並且計算後輸出到此表.
// Log:
// ----------------------------------------------------------------------------
// How to run ?
// usage command line : /usr/bin/php70 bonus_commission_agent_payout_cmd.php test/run 2017-06-06 statustoken worker
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// session_start();

$stats_showdata_count = 0;
$stats_insert_count = 0;
$stats_update_count = 0;

// ----------------------------------------------------------------------------

require_once dirname(__FILE__) ."/config.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
}

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
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('Asia/Taipei'));
date_timezone_set($date, timezone_open('Asia/Taipei'));
$current_date = date_format($date, 'Y-m-d H:i:sP');


//
if(isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run') ){
  if(isset($argv[2]) AND validateDate($argv[2], 'Y-m-d') ){
		//如果有的話且格式正確, 取得日期. 沒有的話中止
		$current_datepicker = $argv[2];
  }else{
		// command 動作 時間
		echo "command [test|run] YYYY-MM-DD statustoken worker \n";
		die('no datetime');
	}
	if(isset($argv[3])){
	  $bonusstatus = get_object_vars(jwtdec('agentbonuspayout',$argv[3]));
	}else{
	  // command 動作 時間
	  echo "command [test|run] YYYY-MM-DD statustoken worker \n";
	  die('no status');
	}
	if(isset($argv[4]) AND filter_var($argv[4], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH) ){
	  $worker_account = filter_var($argv[4], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
	}else{
	  // command 動作 時間
	  echo "command [test|run] YYYY-MM-DD statustoken worker \n";
	  die('no worker account');
	}
  $argv_check = $argv[1];
}else{
  // command 動作 時間
  echo "command [test|run] YYYY-MM-DD statustoken worker \n";
  die('no test and run');
}

if(isset($argv[5]) AND $argv[5] == 'web' ){
	$web_check = 1;
	$output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
	$file_key = sha1('agentbonuspayout'.$current_datepicker);
	$reload_file = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$file_key.'.tmp';
	file_put_contents($reload_file,$output_html);
}elseif(isset($argv[5]) AND $argv[5] == 'sql' ){
	if(isset($argv[5]) AND filter_var($argv[5], FILTER_VALIDATE_INT) ){
		$web_check = 2;
		$updatelog_id = filter_var($argv[5], FILTER_VALIDATE_INT);
		$updatelog_sql = "SELECT * FROM root_bonusupdatelog WHERE id ='$updatelog_id';";
		$updatelog_result = runSQL($updatelog_sql);
		if($updatelog_result == 0){
			die('No root_bonusupdatelog ID');
		}
	}else{
		die('No root_bonusupdatelog ID');
	}
}else{
	$web_check = 0;
}

$logger ='';

// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// round 1. 新增或更新發放會員反水資料
// ----------------------------------------------------------------------------
if($web_check == 1){
	$output_html  = '<p align="center">round 1. 發放會員個人分紅傭金 - 更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},500);</script>';
	file_put_contents($reload_file,$output_html);
}elseif($web_check == 2){
	$updatlog_note = 'round 1. 發放會員個人分紅傭金 - 更新中';
	$updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \'0\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
	if($argv_check == 'test'){
		echo $updatelog_sql;
	}elseif($argv_check == 'run'){
		$updatelog_result = runSQLall($updatelog_sql);
	}
}else{
	echo "round 1. 發放會員個人分紅傭金 - 開始\n";
}

// -----------------------------------------------------------------------
// 列出所有的會員資料及人數 SQL
// -----------------------------------------------------------------------
// 算 root_member 人數
$userlist_sql = "SELECT * FROM root_statisticsbonusagent
WHERE dailydate_start = '$current_datepicker' AND dailydate_end = '$current_datepicker' AND member_bonusamount_paidtime IS NULL ORDER BY member_id ASC;";
// 取出 root_member 資料

// var_dump($userlist_sql);
$userlist = runSQLall($userlist_sql);

// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;

// 判斷 root_member count 數量大於 1
if($userlist[0] >= 1) {
	// 以會員為主要 key 依序列出每個會員的貢獻金額
	for($i = 1 ; $i <= $userlist[0]; $i++){
		$stats_showdata_count++;
		$b['id']                  = $userlist[$i]->id;
		$b['member_id']           = $userlist[$i]->member_id;
		$b['member_account']      = $userlist[$i]->member_account;
		$b['member_bonusamount']	= $userlist[$i]->member_bonusamount;

		$b['dailydate_start'] = $userlist[$i]->dailydate_start;
		$b['dailydate_end'] = $userlist[$i]->dailydate_end;
		$b['daterange'] = preg_replace('/([^A-Za-z0-9])/ui', '',$b['dailydate_start']);

		$givemoneytime = $current_date;
		$receivedeadlinetime = date("Y-m-d H:i:s", strtotime('+1 month', strtotime($current_date)));
		$prizecategories = $b['daterange'].'期間個人分紅傭金';
		$summary = $prizecategories;

		// 判斷獎金是以加盟金還是現金發放，如是現金則需設定稽核，加盟金則不用
		if($bonusstatus['bonus_type'] == 'token'){
			$bonusstatus['bonus_cash'] = '0';
			$bonusstatus['bonus_token'] = $b['member_bonusamount'];
		}elseif($bonusstatus['bonus_type'] == 'cash'){
			$bonusstatus['bonus_cash'] = $b['member_bonusamount'];
			$bonusstatus['bonus_token'] = '0';
			$bonusstatus['audit_type'] = 'freeaudit';
			$bonusstatus['audit_amount'] = '0';
		}else{
			die('Error(500):獎金類別錯誤！！');
		}

		if($b['member_bonusamount'] > 0){
			// 新增到 root_receivemoney
			$insert_sql = "INSERT INTO root_receivemoney (member_id,member_account,gcash_balance,gtoken_balance,givemoneytime,receivedeadlinetime,prizecategories,auditmode,auditmodeamount,summary,transaction_category,givemoney_member_account,status,updatetime) VALUES
				('".$b['member_id']."','".$b['member_account']."','".$bonusstatus['bonus_cash']."','".$bonusstatus['bonus_token']."','".$givemoneytime."','".$receivedeadlinetime."','".$prizecategories."','".$bonusstatus['audit_type']."','".$bonusstatus['audit_amount']."',
				'".$summary."','tokenfavorable','".$worker_account."','".$bonusstatus['bonus_status']."',now());";
			if($debug == 1) {
				print_r($insert_sql);
			}

			if($argv_check == 'test'){
				 var_dump($insert_sql);
				 $insert_result[0] = 1;
				 //print_r($insert_sql);
			}elseif($argv_check == 'run'){
				$insert_result = runSQLall($insert_sql);
			}
			$stats_insert_count++;
		}

		// 更新 分紅 記錄
		$update_sql = "UPDATE root_statisticsbonusagent SET
		 updatetime = now(),
		 member_bonusamount_paid = '".$b['member_bonusamount']."',
		 member_bonusamount_paidtime = now()
		 WHERE id = '".$b['id']."';";

		if($debug == 1) {
			echo '日期'.$current_date.'update會員個人分紅傭金'.$b['member_account'].' 更新SQL=';
			print_r($update_sql);
		}

		if($argv_check == 'test'){
			var_dump($update_sql);
			$update_sql_result[0] = 1;
			//print_r($insert_sql);
		}elseif($argv_check == 'run'){
			$update_sql_result = runSQLall($update_sql);
		}
		$stats_update_count++;

		// ------- bonus update log ------------------------
		// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
		$percentage_html     = round(($i/$userlist[0]),2)*100;
		$process_record_html = "$i/$userlist[0]";
		$process_times_html  = round((microtime(true) - $program_start_time),3);
		$counting_r = $percentage_html%10;

		if($web_check == 1 AND $counting_r == 0){
			$output_sub_html  = $output_html.'<p align="center">'.$percentage_html.'%</p>';
			file_put_contents($reload_file,$output_sub_html);
		}elseif($web_check == 2 AND $counting_r == 0){
		  $updatelog_sql = 'UPDATE root_bonusupdatelog SET bonus_status = \''.$percentage_html.'\', note = \''.$updatlog_note.'\' WHERE id = \''.$updatelog_id.'\';';
		  if($argv_check == 'test'){
		    echo $updatelog_sql;
		  }elseif($argv_check == 'run'){
		    $updatelog_result = runSQLall($updatelog_sql);
		  }
		}elseif($web_check == 0){
			if($percentage_html != $percentage_current) {
				if($counting_r == 0) {
					echo "\n目前處理 $current_datepicker 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
				}else{
					echo $percentage_html.'% ';
				}
				$percentage_current = $percentage_html;
			}
		}
		// -------------------------------------------------
  }
}


$run_report_result = "
  統計顯示的資料 =  $stats_showdata_count ,\n
  統計此時間區間插入(Insert)的會員資料 =  $stats_insert_count ,\n
  統計此時間區間更新(Update)的會員資料 =  $stats_update_count";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = $run_report_result."\n累積花費時間: ".$program_time ." \n";
if($web_check == 1){

	$dellogfile_js = '
	<script src="in/jquery/jquery.min.js"></script>
	<script type="text/javascript" language="javascript" class="init">
	function dellogfile(){
		$.get("bonus_commission_agent_action.php?a=agentbonus_del&k='.$file_key.'",
		function(result){
			window.close();
		});
	}
	</script>';

	$logger_html = nl2br($logger).'<br><br><p align="center"><button type="button" onclick="dellogfile();">關閉視窗</button></p><script>alert(\'已完成資料更新！\');</script>'.$dellogfile_js;
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
// MAIN END
// --------------------------------------------

?>
