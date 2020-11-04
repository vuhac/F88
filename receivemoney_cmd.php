#!/usr/bin/php70
<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 反水計算及反水派送 -- 獨立排程執行
// File Name:	cron/receivemoney_cmd.php
// Author:		Barkley,Fix by Ian
// Related:   DB root_favorable(會員反水設定及打碼設定)
// 							preferential_calculation.php
// 							preferential_calculation_action.php
// Desc: 由每日報表，統計投注額後，依據設定比例 1% ~ 3% ，發放反水給予會員。
// 反水可以轉帳到代幣帳戶代幣帳戶可以設定稽核，也可以轉帳到現金帳戶
// Log:
// ----------------------------------------------------------------------------
// How to run ?
// usage command line : /usr/bin/php70 preferential_payout_cmd.php test/run 2017-06-06 statustoken worker
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

// gcash lib 現金轉帳函式庫
require_once dirname(__FILE__) ."/gcash_lib.php";
// gtoken lib 代幣轉帳函式庫
require_once dirname(__FILE__) ."/gtoken_lib.php";

require_once dirname(__FILE__) ."/lib_proccessing.php";

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

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s') {
	$d = DateTime::createFromFormat($format, $date);
	return $d && $d->format($format) == $date;
}

function do_receive($receivemoney,$oper_account='',$prizecategories='',$bons_givemoneytime='') {
	global $current_date, $gcash_cashier_account, $gtoken_cashier_account, $transaction_category,$config;
	// var_dump($config);die();
	// 交易的類別分類：應用在所有的錢包相關程式內 , 定義再 system_config.php 內.
	// global $transaction_category;
	// 轉帳摘要 -- 代幣轉現金(tokengcash)
	$transaction_category_index = $receivemoney->transaction_category;
	// 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
	$summary = $transaction_category[$transaction_category_index];
	// 操作者 ID
	$member_id = $receivemoney->member_id;
	// 轉帳目標帳號 -- 代幣出納帳號 $gtoken_cashier_account
	$destination_transferaccount = $receivemoney->member_account;

	// 來源帳號提款密碼 or 管理員登入的密碼
	// $pwd_verify_sha1 = 'tran5566'; // 移到config，改成 $config['withdrawal_pwd']

	// 提款手續費
	$fee_transaction_money = 0;
	// $fee_transaction_money = round(($wallet_withdrawal_amount * ($member_grade_config['withdrawalfee'] / 100) ),2);
	// 提款行政手續費(稽核不過的費用)
	$administrative_amount = 0;
	// 實際存提
	$realcash = 1;
	// 稽核方式
	$auditmode_select = $receivemoney->auditmode;
	// 稽核金額
	$auditmode_amount = $receivemoney->auditmodeamount;
	// 系統轉帳文字資訊(補充)
	$system_note = NULL;
	// $debug = 1 --> 進入除錯模式 , debug = 0 --> 關閉除錯
	$debug = 0;

	// 審查狀態, 0=cancel 1=ok 2=apply 3=reject null=del
	$status = 2;


	if ($receivemoney->gcash_balance != 0) {
		$source_transferaccount = $gcash_cashier_account;
		$transaction_money = $receivemoney->gcash_balance;

		// 原版
		// $error = member_gcash_transfer(
		// 	$transaction_category_index,
		// 	$summary,
		// 	$member_id,
		// 	$source_transferaccount,
		// 	$destination_transferaccount,
		// 	$pwd_verify_sha1,
		// 	$transaction_money,
		// 	$realcash,
		// 	$system_note,
		// 	$debug
		// );

		// 20191017
		$error = member_gcash_transfer(
			$transaction_category_index,
			$summary,
			$member_id,
			$source_transferaccount,
			$destination_transferaccount,
			$config['withdrawal_pwd'],
			$transaction_money,
			$realcash,
			$system_note,
			$debug
		);

	} else {
		$source_transferaccount = $gtoken_cashier_account;
		$transaction_money = $receivemoney->gtoken_balance;

		// 原版
		// $error = member_gtoken_transfer(
		// 	$member_id,
		// 	$source_transferaccount,
		// 	$destination_transferaccount,
		// 	$transaction_money,
		// 	$pwd_verify_sha1,
		// 	$summary,
		// 	$transaction_category_index,
		// 	0,
		// 	$auditmode_select,
		// 	$auditmode_amount,
		// 	$system_note,
		// 	$debug
		// );

		// 20191017
		$error = member_gtoken_transfer(
			$member_id,
			$source_transferaccount,
			$destination_transferaccount,
			$transaction_money,
			$config['withdrawal_pwd'],
			$summary,
			$transaction_category_index,
			0,
			$auditmode_select,
			$auditmode_amount,
			$system_note,
			$debug
		);
	}

	$ast_time = gmdate('Y-m-d H:i:s', strtotime($bons_givemoneytime)+-4 * 3600);

	if ($error['code'] == 1) {
		// 更新 root_receivemoney 領取彩金時間.領取者 ip 及 Fingerprint
		$update_sql = <<<SQL
			UPDATE root_receivemoney SET
				receivetime = '$current_date',
				status='3'
			WHERE id = '{$receivemoney->id}';
SQL;

		$update_sql_result = runSQL($update_sql);
	}elseif($error['code'] == '2'){
			// 假如查不到帳號，或帳號狀態有問題，則寫到memberlog

			$msg         = $receivemoney->member_account.'，彩金管理->批次领取彩金，发生问题。请确认帐号是否存在，状态是否启用。彩金类别： '.$prizecategories.'。发放日期： '.$ast_time.'。'; //客服
			$msg_log     = $receivemoney->member_account.'，彩金管理->批次领取彩金，发生问题。请确认帐号是否存在，状态是否启用。彩金类别： '.$prizecategories.'。发放日期： ' . $bons_givemoneytime . '。'; //RD
			$sub_service = 'payout';
			memberlogtodb($oper_account, 'marketing', 'error', $msg, $receivemoney->member_account, "$msg_log", 'b', $sub_service);
	}else{
			$msg         = $receivemoney->member_account .' '.$error['messages'].'。彩金類別： ' . $prizecategories . '。發放日期： ' . $ast_time . '。'; //客服
			$msg_log     = $receivemoney->member_account .' '.$error['messages'].'。彩金類別： ' . $prizecategories . '。發放日期： ' . $bons_givemoneytime . '。'; //RD
			$sub_service = 'payout';
			memberlogtodb($oper_account, 'marketing', 'error', $msg, $receivemoney->member_account, "$msg_log", 'b', $sub_service);
	}

}



// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if(isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
  die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}




// 取得今天的日期
// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('Asia/Taipei'));
date_timezone_set($date, timezone_open('Asia/Taipei'));
$current_date = date_format($date, 'Y-m-d H:i:sP');


// -----------------------------------------------------------------
// 命令列參數解析
// -----------------------------------------------------------------
// validate argv list
if(isset($argv[1]) AND $argv[1] == 'run' ){

	if(isset($argv[2])){
	  $search_array = get_object_vars(jwtdec('receivemoney', $argv[2]));
		// var_dump($search_array);die();

		$tab_type                   = $search_array['tab_type'];
		$prizecategories            = $search_array['prizecategories'];
		$last_modify_member_account = $search_array['last_modify_member_account'];
		$bons_givemoneytime         = '';
		if($tab_type=='nonlotto'){
			$bons_givemoneytime         = $search_array['bons_givemoneytime'];
		}

	}else{
	  // command 動作 時間
	  echo "command [run] searchtoken \n";
	  die('no statustoken');
	}

}else{
  // command 動作 時間
  echo "command [run] searchtoken  \n";
  die('no run');
}

if(isset($argv[3]) AND $argv[3] == 'web' ) {

	$web_check = 1;
	$file_key = sha1('receivemoney_batched'.$prizecategories);
	$reload_file = dirname(__FILE__) .'/tmp_dl/receivemoney_batched_'.$file_key.'.tmp';

} else {
	$web_check = 0;
}

$logger = '';


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// round 1. 新增或更新發放會員反水資料
// ----------------------------------------------------------------------------

// start proccessing
if($web_check == 1){
	notify_proccessing_start(
		'批次领取彩金 - 更新中...',
		$reload_file
	);
} else {
	echo "批次领取彩金 - 開始\n";
}



// var_dump($tab_type=='nonlotto');die();
if($tab_type=='nonlotto'){
	$receivemoney_sql =<<<SQL
	SELECT
		root_receivemoney.id,
		root_receivemoney.member_id,
		root_receivemoney.member_account,
		root_receivemoney.gcash_balance,
		root_receivemoney.gtoken_balance,
		root_receivemoney.auditmode,
		root_receivemoney.auditmodeamount,
		root_receivemoney.transaction_category,
		root_receivemoney.givemoneytime
	FROM root_receivemoney
	WHERE root_receivemoney.prizecategories = :prizecategories
	    AND root_receivemoney.givemoneytime = :givemoneytime
		AND root_receivemoney.receivetime IS NULL
		AND root_receivemoney.status='1'
		AND  root_receivemoney.receivedeadlinetime >='{$current_date}'
		AND ((root_receivemoney.gtoken_balance > 0) OR (root_receivemoney.gcash_balance > 0))
	;
SQL;
	$receivemoney_list = runSQLall_prepared($receivemoney_sql, [':prizecategories' => $prizecategories,':givemoneytime' => $bons_givemoneytime]);
}else{
	$receivemoney_sql =<<<SQL
	SELECT
		root_receivemoney.id,
		root_receivemoney.member_id,
		root_receivemoney.member_account,
		root_receivemoney.gcash_balance,
		root_receivemoney.gtoken_balance,
		root_receivemoney.auditmode,
		root_receivemoney.auditmodeamount,
		root_receivemoney.transaction_category,
		root_receivemoney.givemoneytime
	FROM root_receivemoney
	WHERE root_receivemoney.prizecategories = :prizecategories
		AND root_receivemoney.receivetime IS NULL
		AND root_receivemoney.status='1'
		AND  root_receivemoney.receivedeadlinetime >='{$current_date}'
		AND ((root_receivemoney.gtoken_balance > 0) OR (root_receivemoney.gcash_balance > 0))
	;
SQL;
	$receivemoney_list = runSQLall_prepared($receivemoney_sql, [':prizecategories' => $prizecategories]);
}


// 處理進度 % , 用來顯示紀錄進度。
$percentage_current = 0;

// 判斷 root_member count 數量大於 1
if(count($receivemoney_list) >= 1) {
  // 以會員為主要 key 依序列出每個會員的貢獻金額
  foreach($receivemoney_list as $i => $receivemoney) {
		// var_dump($receivemoney);die();

		do_receive($receivemoney,$last_modify_member_account,$prizecategories,$receivemoney->givemoneytime);

		$stats_insert_count++;
		$stats_update_count++;



		// ------- bonus update log ------------------------
		// 顯示目前的處理紀錄進度，及花費的時間。 換算進度 %
		$percentage_html     = round( $i / count($receivemoney_list), 2 ) * 100;
		$process_record_html = '{$i/count($receivemoney_list)}';
		$process_times_html  = round((microtime(true) - $program_start_time),3);
		$counting_r = $percentage_html % 5;

		if($web_check == 1 AND $counting_r == 0){

			notify_proccessing_progress(
				'批次领取彩金 - 更新中...',
				$percentage_html . ' %',
				$reload_file
			);

		} elseif($web_check == 0) {
			if($percentage_html != $percentage_current) {
				if($counting_r == 0) {
					echo "\n目前處理 $prizecategories 紀錄: $process_record_html ,執行進度: $percentage_html% ,花費時間: ".$process_times_html."秒\n";
				}else{
					echo $percentage_html.'% ';
				}
				$percentage_current = $percentage_html;
			}
		}
		// -------------------------------------------------
  }
}




// proccessing complete
$run_report_result = "
  統計顯示的資料 =  $stats_showdata_count ,\n
  統計此時間區間插入(Insert)的會員資料 =  $stats_insert_count ,\n
  統計此時間區間更新(Update)的會員資料 =  $stats_update_count";

// 算累積花費時間
$program_end_time =  microtime(true);
$program_time = $program_end_time-$program_start_time;
$logger = $run_report_result."\n累積花費時間: ".$program_time ." \n";

if($web_check == 1) {
	$bons_givemoneytime_ast = gmdate('Y-m-d H:i:s', strtotime($bons_givemoneytime)+-4 * 3600);

	notify_proccessing_complete(
		nl2br($logger).'<br><br>',
		'receivemoney_management_action.php?a=batched_receive_del&k='.$file_key.
			'&bonus_type='.$prizecategories.'&bons_givemoneytime='.$bons_givemoneytime_ast,
		$reload_file
	);

} else {
	echo $logger;
}


// --------------------------------------------
// MAIN END
// --------------------------------------------

?>
