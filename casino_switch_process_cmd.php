<?php
// ----------------------------------------------------------------------------
// Features:	後台 - 娛樂城管理-回收會員代幣 in command mode run
// File Name:	casino_switch_process_cmd.php
// Author:		Barkley
// DB table:  casino_list root_member_wallets
// Related:   casino_switch_process.php casino_switch_process_action.php mg_casino_restful_lib.php
// command example: /usr/bin/php70 /home/testgpk2demo/web/begpk2/casino_switch_process_cmd.php run MG
//        在 casino_switch_process_action.php 背景執行 casino_switch_process_cmd.php 後，會開一個 json 檔
//        在 log 資料夾下，用來記錄操作進度，另外在 log 資料夾下會有一個 casino_switch_process.log 用來記錄操作
//        過程的各種訊息，待 casino_switch_process_cmd.php 執行完後會自行變更 casino_list 中的 open 為 0。
// Log:
// 2019.03.13 新增娛樂城停用狀態 Letter
//     做完後變更 casino_list 內為停用(4)或關閉(0)
// 2019.12.24 #3003 娛樂城設定檔轉入資料庫 Letter
//     修改引用函式庫等方法
// ----------------------------------------------------------------------------


require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 取得娛樂城關閉用function名
require_once dirname(__FILE__) . '/casino/casino_config.php';

// set memory limit
ini_set('memory_limit', '200M');

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// 程式 debug 開關, 0 = off , 1= on
$debug = 0;
global $tr;

// API 每次送出的最大數據筆數
// 用於進行帳號批次 LOCK 時使用
$api_limit = 1000;

// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if (isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
	die($tr['error message source error']);
}

// 命令列參數解析
if (isset($argv[1]) AND ($argv[1] == 'test' OR $argv[1] == 'run')) {
	if (isset($argv[2]) AND isset($argv[3])) {
		// 取得傳入的娛樂城代號，並設定該娛樂城在 root_member_wallets 中
		// 使用的帳號及餘額的 column 名以利查詢時使用
		$casinoid = $argv[2];
		$casinoState = $argv[3];
		require_once getRequirePath($casinoid);
	} else {
		// command 動作 娛樂城id
		echo "command [test|run] CasinoID \n";
		die('no test and run');
	}
	$argv_check = $argv[1];
} else {
	// command 動作 娛樂城id
	echo "command [test|run] CasinoID \n";
	die('no test and run');
}

$json_filename = 'log/' . $casinoid . '.json';
// 開啟記錄用檔案，以利頁面查詢進度
$casino_switch_json = fopen($json_filename, 'wb');

if ($argv_check == 'test') {
	// 查詢欲停用娛樂城的現行使用中會員資料
	$casino_switch_member_list_sql = 'SELECT * FROM root_member_wallets JOIN root_member ON root_member.id=root_member_wallets.id WHERE gtoken_lock =\'' . $casinoid . '\';';
	if ($debug == 1) {
		echo $casino_switch_member_list_sql;
	}

	$casino_switch_member_list_result = runSQLall($casino_switch_member_list_sql);
	if ($casino_switch_member_list_result['0'] >= 1) {
		getCasinoSwitchOffProcess($casinoid, $debug)($casino_switch_member_list_result, $api_limit, 1);
	}

	// 在完成代幣回收作業後，將娛樂城的狀態由處理中改為停用
	$query_sql = 'UPDATE casino_list SET open = \'' . $casinoState . '\' WHERE casinoid=\'' . $casinoid . '\';';
	if ($debug == 1) {
		echo $query_sql;
	}

	$query_result = runSQL($query_sql);
	if ($debug == 1) {
		echo $query_result;
	}
} elseif ($argv_check == 'run') {
	// 等待1秒避開剛進GAME的會員
	sleep(1);
	try {
		// ------------------------------------
		// 查詢欲停用娛樂城的現行使用中會員資料
		// ------------------------------------
		$casino_switch_member_list_sql = 'SELECT *,casino_accounts->\'' . $casinoid . '\'->>\'account\' as ' . strtolower($casinoid) . '_account
                                              ,casino_accounts->\'' . $casinoid . '\'->>\'balance\' as ' . strtolower($casinoid) . '_balance FROM root_member_wallets JOIN root_member ON root_member.id=root_member_wallets.id WHERE gtoken_lock =\'' . $casinoid . '\';';

		if ($debug == 1) {
			echo $casino_switch_member_list_sql;
		}

		$casino_switch_member_list_result = runSQLall($casino_switch_member_list_sql);
		if ($casino_switch_member_list_result['0'] >= 1) {
			getCasinoSwitchOffProcess($casinoid)($casino_switch_member_list_result, $api_limit, 0);
		}
	} catch (EXCEPTION $e) {
		var_dump($e);
	} finally {
		// 在完成代幣回收作業後，將娛樂城的狀態由處理中改為停用
		$query_sql = 'UPDATE casino_list SET open = \'' . $casinoState . '\' WHERE casinoid=\'' . $casinoid . '\';';
		if ($debug == 1) {
			echo $query_sql;
		}
		$query_result = runSQL($query_sql);
		if ($debug == 1) {
			echo $query_result;
		}
	}
} else {
	$logger = $tr['error message params error'];
	echo $logger;
}

fclose($casino_switch_json);


?>
