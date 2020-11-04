<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 會員錢包動作處理
// File Name:	member_wallets_action.php
// Author:		Barkley
// Related:   bonus_commission_profit.php
// DB table:  root_statisticsbonusagent  放射線組織獎金計算-代理加盟金
// Log:
// 2019.12.24 #3003 娛樂城設定檔轉入資料庫 Letter 修改取回娛樂城餘額方法
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 自訂會員函式庫
require_once dirname(__FILE__) . "/member_lib.php";
// 取得 casino 的 function
require_once dirname(__FILE__) . "/casino/casino_config.php";

// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
	$d = DateTime::createFromFormat($format, $date);
	return $d && $d->format($format) == $date;
}

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------
// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);
$debug = 0;
global $system_mode;

if (isset($_GET['a'])) {
	$action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
	die('(x)不合法的測試');
}

if (isset($_GET['t'])) {
	$member_id = filter_var($_GET['t'], FILTER_VALIDATE_INT);
	$chk_sql = 'SELECT root_member.id as mid,account,therole,gtoken_lock FROM root_member JOIN root_member_wallets ON root_member_wallets.id=root_member.id WHERE root_member.id=\'' . $member_id . '\';';
	$chk_result = runSQLall($chk_sql);
	if ($chk_result[0] == 1) {
		$memberid = $chk_result[1]->mid;
		$member_account = $chk_result[1]->account;
		$casino_lock = $chk_result[1]->gtoken_lock;
	}
}
if (isset($_GET['cid'])) {
	$casino_id = filter_var($_GET['cid'], FILTER_SANITIZE_STRING);
	$casino_list_sql = 'SELECT casinoid FROM "casino_list" WHERE open = \'1\';';
	$casino_list_result = runSQLall($casino_list_sql);
	// var_dump($casino_list_result);
	if ($casino_list_result[0] >= 1) {
		for ($i = 1; $i <= $casino_list_result[0]; $i++) {
			if ($casino_list_result[$i]->casinoid == $casino_id) {
				$casinoid = $casino_list_result[$i]->casinoid;
			}
		}
	}
}

$check_therole_result = (object)check_member_therole($chk_result[1]);
if (!$check_therole_result->status) {
	$error_mag = $check_therole_result->result;
	echo '<script>alert("' . $error_mag . '");</script>';
	die();
}
// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------
// var_dump($memberid);
// var_dump($casinoid);

// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------
if ($action == 'agentTransferoutmember_Casino_balance' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($casinoid) AND isset($memberid)) {
	// ----------------------------------------------------------------------------
	// 當發現會員沒有錢的時候，就進入這個自動儲值轉換的程序。
	// 將 GCASH 依據設定值，自動加值到 GTOKEN 上面。
	// ----------------------------------------------------------------------------
	// 檢查是否已有客服在協助處理
	$lock_chk = agent_walletscontrol_check($member_account);
	if (isset($lock_chk) AND $lock_chk != '') {
		echo '客服人员 ' . $lock_chk . ' 正在协助处理此会员娱乐城钱包问题！';
	} elseif ($config['businessDemo'] == 1 AND !in_array($casinoid, $config['businessDemo_skipCasino'])) {
		echo '目前所在站台為業務展示站台，不支援進此娛樂城';
	} else {
		// 鎖定會員錢包操作，避免重覆操作
		$member_lock_key = sha1($member_account . 'AgentLock');
		Agent_runRedisSET($member_lock_key, 'AgentLock', 2);

		if (isset($casino_lock) AND $casino_lock != '' AND $casino_lock != $casinoid) {
			// 將錢轉往不同casino，需先將錢取回再轉到該casino
			require_once getRequirePath($casino_lock, $debug);
			// 取回娛樂城的餘額
			$rr = getCasinoRetrieve($casino_lock, $debug)($memberid, $debug);

			if ($debug == 1) {
				echo '<p align="center">取回：' . $rr['messages'] . '</p>';
			}

			//取回餘額成功 , == 1
			if ($rr['code'] != 1) {
				$logger = $rr['messages'];
				// 解除鎖定會員錢包操作
				Agent_runRedisDEL($member_lock_key, 2);
				echo $logger;
				die($logger);
			}
		}

		// ----------------------------------------------------------------------------
		// 讀取對應 casino 的 lib
		require_once getRequirePath($casinoid, $debug);

		// 取回娛樂城的餘額
		$rr = getCasinoTransferout($casinoid, $debug)($memberid, $debug);

		if ($debug == 1) {
			echo '<p align="center">转出：' . $rr['messages'] . '</p>';
		}

		//取回MG餘額成功 , == 100
		if ($rr['code'] == 1) {
			$logger = $rr['messages'];
			echo $logger;
		} else {
			$logger = $rr['messages'];
			echo '(' . $rr['code'] . ')' . $logger;
		}
		//var_dump($rr);
		// ----------------------------------------------------------------------------

		// 解除鎖定會員錢包操作
		Agent_runRedisDEL($member_lock_key, 2);
	}
// ----------------------------------------------------------------------------
} elseif ($action == 'agentRetrievemember_Casino_balance' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($memberid)) {
// ----------------------------------------------------------------------------
// 取回 Casino 的餘額，並檢查上次離開時和目前的差額得出派彩金額。 v2017.9.25
// gamelobby_action.php?a=Retrieve_Casino_balance
// ----------------------------------------------------------------------------
	if ($system_mode != 'developer') {
		// 檢查是否已有客服在協助處理
		$lock_chk = agent_walletscontrol_check($member_account);
		if (isset($lock_chk) AND $lock_chk != '') {
			echo '客服人员 ' . $lock_chk . ' 正在协助处理此会员娱乐城钱包问题！';
		} else {
			// 鎖定會員錢包操作，避免重覆操作
			$member_lock_key = sha1($member_account . 'AgentLock');
			Agent_runRedisSET($member_lock_key, 'AgentLock', 2);


			// ----------------------------------------------------------------------------
			// 旗標,轉餘額動作執行時，透過這個旗標控制同一個使用者，不能有第二個執行緒進入。除非已經清除這個旗標。
			$_SESSION['wallet_transfer'] = 'Account:' . $member_account . ' run in ' . 'Retrieve_Casino_balance';
			// 使用session時，若先前的頁面尚未執行完畢，預設session會被鎖住。
			// 此時，若執行另外一個也有使用session的頁面，則須等前一個頁面執行完畢，才能再執行。
			// 要避免等前一個頁面執行完畢，才能執行下一個頁面的情況，可以使用 session_write_close() ，告之不會再對session做寫入的動作，這樣其他頁面就不會等此頁面執行完才能再執行。
			// session_write_close() ;

			// 取得目前gtoken_lock的值
			$member_sql = "SELECT * FROM root_member_wallets WHERE id = '$memberid';";
			//echo $member_sql;
			$member = runSQLall($member_sql);
			$casino_lock = $member[1]->gtoken_lock;

			// 讀取對應 casino 的 lib
			require_once getRequirePath($casino_lock, $debug);

			// 取回娛樂城的餘額
			$rr = getCasinoRetrieve($casino_lock, $debug)($memberid, $debug);

			//取回MG餘額成功 , == 100
			if ($rr['code'] == 100) {
				$logger = $rr['messages'];
				echo $logger;
			} else {
				$logger = $rr['messages'];
				echo '(' . $rr['code'] . ')' . $logger;
			}
			//echo '<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';
			// 清除旗標的功能寫在呼叫他的地方。
			// ----------------------------------------------------------------------------

			// 解除鎖定會員錢包操作
			Agent_runRedisDEL($member_lock_key, 2);
		}
	} else {
		echo "開發環境不開放操作娛樂城API";
	}

// ----------------------------------------------------------------------------
} elseif ($action == 'agent_unlock_member_wallets' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($memberid)) {
	// 避免鎖定會員錢包操作後因故操作失敗造成前台鎖死無法運做
	// 故在後台加上一個解鎖的KEY，在確定流程走完後手動解鎖
	$member_lock_key = sha1($member_account . 'AgentLock');
	// 解除鎖定會員錢包操作
	Agent_runRedisDEL($member_lock_key, 2);
	echo $member_account . ' ' . $member_lock_key . ' unlock';
} elseif ($action == 'agent_lock_member_wallets' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($memberid)) {
	// 避免鎖定會員錢包操作後因故操作失敗造成前台鎖死無法運做
	// 故在後台加上一個解鎖的KEY，在確定流程走完後手動解鎖
	// 鎖定會員錢包操作，避免重覆操作
	$member_lock_key = sha1($member_account . 'AgentLock');
	Agent_runRedisSET($member_lock_key, $_SESSION['agent']->realname, 2);
	echo $member_account . ' ' . $member_lock_key . ' lock';
} else {
	die('(x)不合法的測試');
}
