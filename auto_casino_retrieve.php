<?php
// ----------------------------------------------------------------------------
// Features:	後台 - 娛樂城管理-自動回收會員代幣 in command mode run
// File Name:	auto_casino_retrieve.php
// Author:		Hunglin
// Related:
// Log:
// ----------------------------------------------------------------------------

// session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/casino/casino_config.php";

// 程式 debug 開關, 0 = off , 1= on
$debug = 0;

// API 每次送出的最大數據筆數
// 用於進行帳號批次 LOCK 時使用
$api_limit = 1000;

// -----------------------------------------------------------------
// 安全控管, 如果是 web 執行就立即中斷, 只允許 command 執行此程式。
// -----------------------------------------------------------------
// var_dump($_SERVER);
// 如果 HTTP_USER_AGENT OR SERVER_NAME 存在, 表示是直接透過網頁呼叫程式, 拒絕這樣的呼叫
if (isset($_SERVER['HTTP_USER_AGENT']) OR isset($_SERVER['SERVER_NAME'])) {
	die('禁止使用網頁呼叫，來源錯誤，請使用命令列執行。');
}

$logdir = (isset($argv[1])) ? $argv[1] : '/home/deployer/cron/log';


//if(isset($_SERVER['USER']) AND $_SERVER['USER'] == 'nginx' ) {
//  die('不允許使用網頁執行，請確認你的呼叫來源。');
//}

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// Session、前台、後台的 DB 的 DB 位址
$redisdb['db_session'] = 0;
$redisdb['db_back'] = 1;
$redisdb['db_front'] = 2;

// 從 redisdb db 2 ($redisdb['db_front']) 取得 所有的 session
$usersession_front = Agent_runRedisgetkeyvalue('*', $redisdb['db_front']);
// var_dump($usersession_front);

$exclude_usersession_sql = '';
// 拆解 session 的分隔字元, 有資料才拆
if ($usersession_front[0] > 1) {
	$sql_count = 0;
	$exclude_usersession_sql_arr = '';
	for ($i = 1; $i < $usersession_front[0]; $i++) {
		$session_data = explode(',', $usersession_front[$i]['value']);
		// ex: kt1_front_testagent 站台代碼_前台_帳號
		$session_account = explode('_', $session_data[0]);
		$explodecount = count($session_account);
		if ($explodecount >= 2) {
			if ($sql_count >= 1) $exclude_usersession_sql_arr = $exclude_usersession_sql_arr . ', ';
			$exclude_usersession_sql_arr = $exclude_usersession_sql_arr . '\'' . $session_account[2] . '\'';
			$sql_count++;
		}
	}
	if ($sql_count > 0) $exclude_usersession_sql = $exclude_usersession_sql . ' AND account NOT IN (' . $exclude_usersession_sql_arr . ')';
}

// 取得遊戲幣在遊戲城中的帳號，排除還在平台上的使用者
$gtoken_lock_member_list_sql = 'SELECT * FROM root_member_wallets JOIN root_member ON root_member.id=root_member_wallets.id WHERE gtoken_lock != \'NULL\'' . $exclude_usersession_sql . ';';

if ($debug == 1) {
	echo $gtoken_lock_member_list_sql . "\n";
}

$gtoken_lock_member_list_sql_result = runSQLall($gtoken_lock_member_list_sql);

if ($debug == 1) {
	var_dump($gtoken_lock_member_list_sql_result);
}

if ($gtoken_lock_member_list_sql_result[0] >= 1) {
	// 查詢當前casino_list中娛樂城狀態為1的娛樂城，只有在為1時能可以進行自動取錢
	$avaliable_casino_list = [];
	$avaliable_casino_list_sql = 'SELECT casinoid from casino_list WHERE open != \'1\';';
	$avaliable_casino_list_result = runSQLall($avaliable_casino_list_sql);
	for ($i = 1; $i <= $avaliable_casino_list_result[0]; $i++) {
		$avaliable_casino_list[] = $avaliable_casino_list_result[$i]->casinoid;
	}
	$gtoken_lock_member_grouping = NULL;
	// 設定時間範圍條件為現在時間30分鐘內
	$check_betting_datetime = (new DateTime("now", timezone_open("Asia/Taipei")))->modify('-30 min')->format('Y-m-d H:i:s');
	// var_dump($check_betting_datetime);
	for ($i = 1; $i <= $gtoken_lock_member_list_sql_result[0]; $i++) {
		// 各遊戲城帳號資訊
		$casino_accounts = json_decode($gtoken_lock_member_list_sql_result[$i]->casino_accounts);
		// 目前遊戲幣所在的遊戲城
		$gtoken_lock = $gtoken_lock_member_list_sql_result[$i]->gtoken_lock;
		// 判斷娛樂城狀態是否為1，如不是則跳到下一個
		if (in_array($gtoken_lock, $avaliable_casino_list)) continue;
		// 使用者 member id
		$member_id = $gtoken_lock_member_list_sql_result[$i]->id;
		// 彙整並加入 API 所需參數 account、password、balance
		$api_casino_account = strtolower($gtoken_lock) . '_account';
		$api_casino_account_value = $casino_accounts->$gtoken_lock->account;
		$gtoken_lock_member_list_sql_result[$i]->$api_casino_account = $api_casino_account_value;
		$api_casino_password = strtolower($gtoken_lock) . '_password';
		$api_casino_password_value = $casino_accounts->$gtoken_lock->password;
		$gtoken_lock_member_list_sql_result[$i]->$api_casino_password = $api_casino_password_value;
		$api_casino_balance = strtolower($gtoken_lock) . '_balance';
		$api_casino_balance_value = $casino_accounts->$gtoken_lock->balance;
		$gtoken_lock_member_list_sql_result[$i]->$api_casino_balance = $api_casino_balance_value;
		if (getCasinoType($gtoken_lock, $debug) == 0) $gtoken_lock_member_list_sql_result[$i]->gamehall = strtolower($gtoken_lock);

		// 使用10分鐘報表確認30分鐘內有無更新來辨識使用者在遊戲城的線上狀態
		$check_betting_sql = 'SELECT * FROM root_statisticsbetting WHERE member_id = \'' . $member_id . '\' AND casino_id = \'' . $gtoken_lock . '\' AND updatetime > \'' . $check_betting_datetime . '\';';
		if ($debug == 1) {
			echo $check_betting_sql . "\n";
		}
		$check_betting_sql_result = runSQLall($check_betting_sql);
		// 30分鐘內有更新報表迴圈繼續下一個
		if ($check_betting_sql_result[0] > 0) {
			continue;
		}

		// 根據遊戲城分類彙整要回收遊戲幣使用者
		if (isset($gtoken_lock_member_grouping[$gtoken_lock])) {
			$gtoken_lock_grouping_count = $gtoken_lock_member_grouping[$gtoken_lock][0];
			$add_new_gtoken_lock_grouping_count = $gtoken_lock_grouping_count + 1;
			$gtoken_lock_member_grouping[$gtoken_lock][0] = $add_new_gtoken_lock_grouping_count;
			$gtoken_lock_member_grouping[$gtoken_lock][$add_new_gtoken_lock_grouping_count] = $gtoken_lock_member_list_sql_result[$i];
		} else {
			$gtoken_lock_member_grouping[$gtoken_lock][0] = 1;
			$gtoken_lock_member_grouping[$gtoken_lock][1] = $gtoken_lock_member_list_sql_result[$i];
		}
	}

	if ($debug == 1) {
		var_dump($gtoken_lock_member_grouping);
	}

	if (isset($gtoken_lock_member_grouping)) {
		// 根據遊戲城分類回收使用者遊戲幣
		foreach ($gtoken_lock_member_grouping as $casinoid => $gtoken_lock_grouping) {
			// 引入對應遊戲城 function file
			require_once getRequirePath($casinoid);
			if (getCasinoType($casinoid) == 0) {
				$api_column['casinoid'] = $casinoid;
				$api_column['account'] = strtolower($api_column['casinoid']) . '_account';
				$api_column['password'] = strtolower($api_column['casinoid']) . '_password';
				$api_column['balance'] = strtolower($api_column['casinoid']) . '_balance';
				$api_column['gamehall'] = strtolower($api_column['casinoid']);
			}
			if ($gtoken_lock_grouping[0] >= 1) {
				$json_filename = $logdir . '/auto_retrieve_' . (new DateTime("now", timezone_open("Asia/Taipei")))->format('YmdHi') . '_' . $casinoid . '.json';
				// 開啟記錄用檔案，以利頁面查詢進度
				$casino_switch_json = fopen($json_filename, 'wb');
				getCasinoSwitchOffProcess($casinoid)($gtoken_lock_grouping, $api_limit, 1);
			}
		}
	}
}

?>
