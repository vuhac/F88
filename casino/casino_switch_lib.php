<?php
// ----------------------------------------------------------------------------
// Features:	Casino Switch 的通用函式庫
// File Name:	casino_switch_lib.php
// Author:		Letter
// Related:
// Log:
// 20191220 新增
// ----------------------------------------------------------------------------
/*
// function 索引及說明：
1. API 文件函式及用法 sample , 操作 API
getDataByAPI($method, $debug=0, $API_data)
2. 產生會員轉換娛樂城的紀錄
member_casino_transferrecords($source,$destination,$token,$note,$memberid)
3. 取回 娛樂城 的餘額 -- 針對 db 的處理函式，只針對此功能有用，不能單獨使用，需要搭配 retrieve_casino_balance
db_retrieve_casino_balance_for_casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account, api_balance, $payout_balance, $casino_balance_db, $debug = 0)
4. 娛樂城停用，取回會員在娛樂城代幣用
retrieve_casino_balance_for_casino_switchoff($i, $accountid, $account, $casino_account, $gtoken_balance, $casino_balance_db, $check_count, $debug = 0)
5. 娛樂城停用，取回會員在娛樂城代幣
casino_switch_process($casino_switch_member_list_result, $api_limit, $debug = 0)
6. 將傳入的 member id 值，取得他的 DB GTOKEN 餘額並全部傳送到  CASINO 上
transferout_gtoken_to_casino_balance($memberid, $debug = 0)
7. 取回 娛樂城 的餘額 -- 針對 db 的處理函式，只針對此功能有用，不能單獨使用，需要搭配 retrieve_casino_balance
db_retrieve_casino_balance($memberaccount, $memberid, $member_casino_account, $gtoken_cashier_account, $api_balance, $payout_balance, $casino_balance_db, $debug = 0)
8. 取回 娛樂城 的餘額，不能單獨使用，需要搭配 db_retrieve_casino_balance 使用
retrieve_casino_balance($memberid, $debug = 0)
9. 透過 API 取得娛樂城餘額
getCasinoBalanceByAPI($memberid, $debug = 0)
10. 確認會員是否在線上遊戲
check_casino_account_online($casino_member, $debug = 0)
*/
require_once dirname(__FILE__) ."/../casino_switch_process_lib.php";
require_once 'member_lib.php';

if (isset($casinoid_now)) {
	$api_column['casinoid'] = $casinoid_now;
} elseif (isset($casino_lock)) {
	$api_column['casinoid'] = $casino_lock;
} else {
	$api_column['casinoid'] = $casinoid;
}
$api_column['account'] = strtolower($api_column['casinoid']) . '_account';
$api_column['password'] = strtolower($api_column['casinoid']) . '_password';
$api_column['balance'] = strtolower($api_column['casinoid']) . '_balance';
$api_column['gamehall'] = strtolower($api_column['casinoid']);


/**
 *  sign key generator
 *
 * @param mixed $data   傳遞的參數陣列，若沒傳遞參數則放空陣列
 * @param mixed $apiKey 代理商的API KEY
 *
 * @return string 加密字串
 */
function generateSign($data, $apiKey)
{
	ksort($data);
	return md5(http_build_query($data) . $apiKey);
}


/**
 *  login Casino through API function
 *
 * @param mixed $method      方法名稱
 * @param int   $debug       除錯模式，預設 0 為關閉，1為開啟
 * @param mixed $API_data 資料陣列
 *
 * @return array|string API回傳資料
 */
function getDataByAPI($method, $debug = 0, $API_data)
{
	// 設定 socket_timeout , http://php.net/manual/en/soapclient.soapclient.php
	ini_set('default_socket_timeout', 5);

	global $API_CONFIG;
	global $system_mode;
	global $config;

	// Setting restful url
	$url = $API_CONFIG['url'];
	$apiKey = $config['gpk2_apikey'];
	$token = $config['gpk2_token'];

	if ($method == 'AddAccount') {
		$url .= '/api/player';
		$apimethod = 'post';
		$API_data['sign'] = generateSign($API_data, $apiKey);
	} elseif ($method == 'Deposit') {
		$url .= '/api/transaction/deposit';
		$apimethod = 'post';
		$API_data['sign'] = generateSign($API_data, $apiKey);
	} elseif ($method == 'Withdrawal') {
		$url .= '/api/transaction/withdraw';
		$apimethod = 'post';
		$API_data['sign'] = generateSign($API_data, $apiKey);
	} elseif ($method == 'GetAccountDetails') {
		$API_data['sign'] = generateSign($API_data, $apiKey);
		$uri = http_build_query($API_data);
		$url .= '/api/player/wallet?' . $uri;
		$apimethod = 'get';
	} elseif ($method == 'CheckUser') {
		$API_data['sign'] = generateSign([], $apiKey);
		$url .= '/api/player/check/' . $API_data['account'] . '?sign=' . $API_data['sign'];
		$apimethod = 'get';
	} elseif ($method == 'KickUser') {
		$url .= '/api/player/logout';
		$apimethod = 'post';
		$API_data['sign'] = generateSign($API_data, $apiKey);
	} elseif ($method == 'GetGameUrl') {
		$API_data['sign'] = generateSign($API_data, $apiKey);
		$uri = http_build_query($API_data);
		$url .= '/api/game/game-link?' . $uri;
		$apimethod = 'get';
	} elseif ($method == 'GameHallLists') {
		$API_data['sign'] = generateSign($API_data, $apiKey);
		$url .= '/api/game/halls';
		$apimethod = 'get';
	} elseif ($method == 'GamenameLists') {
		$API_data['sign'] = generateSign($API_data, $apiKey);
		$uri = http_build_query($API_data);
		$url .= '/api/game/game-list?' . $uri;
		$apimethod = 'get';
	} elseif ($method == 'CheckUserIsGaming') {
		$API_data['sign'] = generateSign($API_data, $apiKey);
		$uri = http_build_query($API_data);
		$url .= '/api/player/is-gaming?' . $uri;
		$apimethod = 'get';
	} elseif ($method == 'GetBetDetail') {
		$API_data['sign'] = generateSign($API_data, $apiKey);
		$uri = http_build_query($API_data);
		$url .= '/api/betlog/playcheck?' . $uri;
		$apimethod = 'get';
	} else {
		$ret = 'nan';
	}

	if (isset($API_data)) {
		$ret = array();
		try {
			//HTTP headers
			// $headertype = 'application/json';
			// $headertype = 'application/x-www-form-urlencoded';
			$headers = ["Content-Type: multipart/form-data", "Authorization: $token"];


			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_CAINFO, $_SERVER['DOCUMENT_ROOT'] . '/cacert.pem');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			if ($apimethod == 'post') {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $API_data);
			}

			$response = curl_exec($ch);

			if ($debug == 1) {
				echo $method . "\n";
				echo curl_error($ch);
				var_dump($response);
			}

			if ($response) {
				$body = json_decode($response);

				if ($debug == 1) {
					var_dump($body);
				}
				// 如果 curl 讀取投注紀錄成功的話
				if (isset($body->data) and $body->status->code == 0) {
					// curl 正確
					$ret['curl_status'] = 0;
					// 計算取得的紀錄數量有多少
					$ret['count'] = (is_array($body->data) OR is_object($body->data)) ? count((array)$body->data) : '1';
					// 取得紀錄沒有錯誤
					$ret['errorcode'] = 0;
					// 存下 body
					$ret['Status'] = $body->status->code;
					$ret['Result'] = $body->data;
				} else {
					// curl 正確
					$ret['curl_status'] = 0;
					// 計算取得的紀錄數量有多少
					$ret['count'] = (is_array($body->data) OR is_object($body->data)) ? count((array)$body->data) : '1';
					// 取得紀錄沒有錯誤
					$ret['errorcode'] = $body->status->code;
					// 存下 body
					$ret['Status'] = $body->status->code;
					$ret['Result'] = $body->status->message;
				}
			} else {
				// curl 錯誤
				$ret['curl_status'] = 1;
				$ret['errorcode'] = curl_errno($ch);
				// 錯誤訊息
				$ret['Result'] = '系统维护中，请稍候再试';
			}
			// 關閉 curl
			curl_close($ch);
		} catch (Exception $e) {
			// curl 錯誤
			$ret['curl_status'] = 1;
			$ret['errorcode'] = 500;
			// 錯誤訊息
			$ret['Result'] = $e->getMessage();
		}
	} else {
		$ret = 'NAN';
	}

	return ($ret);
}


/**
 *  取回 娛樂城 Casino 的餘額，並檢查上次離開時和目前的差額得出派彩金額，紀錄存簿
 *  方法：
 *  retrieve_casino_balance(...)  取回 娛樂城 的餘額
 *  db_retrieve_casino_balance(...) 取回 娛樂城 的餘額 -- 針對 db 的處理函式
 *
 * 1. 查詢 DB 的 gtoken_lock  是否有紀錄在 娛樂城 帳戶，NULL 沒有紀錄的話表示沒有餘額在 娛樂城 帳戶
 * 2. AND 當 session 有 娛樂城_balance 的時候才動作，如果沒有則結束，表示 db 帳號資料有問題
 * (Deprecated) 3. lock 這個程序, 確保唯一性。使用 $_SESSION['wallet_transfer']  旗標，鎖住程序，不要同時間同一個人執行。需要配合 session_write_close() 才可以。
 * 4.  session 有 娛樂城_balance，gtoken 紀錄為 目的地娛樂城 ，API 檢查 娛樂城 的餘額有多少
 * 5. 承接 4 ,如果 娛樂城 餘額 > 1
 *     5.1 執行 API 取回 娛樂城 餘額 到 娛樂城 的出納帳戶(API操作) ， 成功才執行 5.2、5.3
 *     5.2 把 API 傳回的餘額，透過 GTOKEN 出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶(DB操作)
 *     5.3 紀錄當下 DB 娛樂城_balance 的餘額紀錄：存摺 GTOKEN 紀錄: ( CASINO API)收入 =4 ，(DB CASINO 餘額)支出 = 10 ，(派彩)餘額 = -6 + 原有結餘，
 *          摘要：娛樂城 派彩(DB操作)
 *     5.1 ~ 5.3 必須要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理(message system)
 * 6. 紀錄這次的 retrieve_casino_balance 操作，為一次交易紀錄。後續可以查詢(Confirmation Number)
 * 7. 執行完成後，需要 reload page，把 $_SESSION['wallet_transfer'] 變數清除, 其他程式才可以進入
 * 8. 把 GTOKEN_LOCK 設定為 NULL , 表示已經沒有餘額在娛樂城了
 */

/**
 * 取回 娛樂城 的餘額 -- 針對 db 的處理函式，只針對此功能有用
 * 不能單獨使用，需要搭配 retrieve_casino_balance
 *  本方法執行下列邏輯：
 *  5.2 把 API 傳回的餘額，透過 GTOKEN 出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶(DB操作)
 *  5.3 紀錄當下 DB 娛樂城_balance 的餘額紀錄：存摺 GTOKEN 紀錄: (CASINO API)收入 =4 ，(DB GPCASINO 餘額)支出 = 10 ，(派彩)餘額 = -6 + 原有結餘
 *
 * @param mixed $accountid              會員ID
 * @param mixed $account                會員帳號
 * @param mixed $casino_account         娛樂城會員帳號
 * @param mixed $gtoken_cashier_account 系統代幣出納帳號
 * @param mixed $api_balance         API 取得娛樂城餘額
 * @param mixed $payout_balance         派彩
 * @param mixed $casino_balance_db      資料庫錢包餘額
 * @param int   $debug                  除錯模式，預設 0 為關閉
 *
 * @return mixed 處理結果，1 為成功
 */
function db_retrieve_casino_balance_for_casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account, $api_balance, $payout_balance, $casino_balance_db, $debug = 0)
{
	global $gtoken_cashier_account;
	global $transaction_category;
	global $config;
	global $auditmode_select;
	global $api_column;

	$casinoLib = new casino_switch_process_lib();
	$casinoSql = 'SELECT display_name FROM "casino_list" WHERE "casinoid" = \''. strtoupper($api_column['casinoid'])	.'\'';
	$displayName = runSQLall($casinoSql, $debug)[1]->display_name;
	$defaultCasinoName = $casinoLib->getCurrentLanguageCasinoName($displayName, 'default');

	// 取得來源與目的帳號的 id，$gtoken_cashier_account(此為系統代幣出納帳號 global var.)
	$d['source_transferaccount'] = $gtoken_cashier_account;
	$d['destination_transferaccount'] = $account;

	$source_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['source_transferaccount'] . "';";
	$source_id_result = runSQLall($source_id_sql);
	$destination_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['destination_transferaccount'] . "';";
	$destination_id_result = runSQLall($destination_id_sql);

	if ($source_id_result[0] == 1 and $destination_id_result[0] == 1) {
		$d['source_transfer_id'] = $source_id_result[1]->id;
		$d['destination_transfer_id'] = $destination_id_result[1]->id;
	} else {
		$logger = '转帐的来源与目的帐号可能有问题，请稍候再试。';
		$r['ErrorCode'] = 590;
		$r['ErrorMessage'] = $logger;
		echo "<p> $logger </p>";
		die();
	}

	if ($debug == 1) {
		var_dump($payout_balance);
	}

	// 派彩有三種狀態，要有不同的對應 SQL 處理
	if ($payout_balance >= 0) {
		// $payout_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 娛樂城 餘額取回

		// 先取得當下的 wallets 變數資料，等等 sql 更新後就會消失了
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
		$wallets_result = runSQLall($wallets_sql);

		// 在剛取出的 wallets 資料庫中 娛樂城 的餘額(支出)
		$gtoken_casino_balance_db = round($wallets_result[1]->casino_balance, 2);
		// 在剛取出的 wallets 資料庫中 gtoken(代幣) 的餘額(支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端 支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_casino_balance_db + $payout_balance), 2);

		// 交易開始
		$payout_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $casino_balance_db;
		// 操作者
		$d['member_id'] = $accountid;
		// (說明) 娛樂城 + 代幣派彩
		$d['summary'] = $api_column['casinoid'] . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select[strtolower($api_column['casinoid'])];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// 娛樂城 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 娛樂城 + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = $payout_balance;

		// 操作 root_member_wallets DB, 把 娛樂城 balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額 , 把 娛樂城 balance 扣除全部表示支出(投注)
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"" . $api_column['casinoid'] . "\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 ' . $defaultCasinoName . ' 派彩' . $d['balance'] . ') by 关闭娱乐城';
		// 針對目的會員的存簿寫入，$payout_balance >= 1 表示贏錢，所以從出納匯款到使用者帳號。
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance - " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 幫' . $api_column['casinoid'] . '派彩到會員 ' . $d['destination_transferaccount'] . ')';
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '0', '" . $d['balance'] . "', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$payout_transaction_sql = $payout_transaction_sql . 'COMMIT;';
		if ($debug == 1) {
			echo '<p>SQL=' . $payout_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$payout_transaction_result = runSQLtransactions($payout_transaction_sql);
		if ($payout_transaction_result) {
			$logger = '从' . $defaultCasinoName . '帐号' . $casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords($api_column['casinoid'], 'lobby', $api_balance, $logger, $accountid, 'info');
		} else {
			// 5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理(message system)
			$logger = '操作者:' . $d['member_id'] . $defaultCasinoName . '储值成功，但资料库处理错误，请通知客服人员处理。';
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords($api_column['casinoid'], 'lobby', $api_balance, $logger, $accountid, 'warning');
		}

		if ($debug == 1) {
			var_dump($r);
		}

	} elseif ($payout_balance < 0) {
		// $payout_balance < 0; 從娛樂城輸錢
		// 先取得當下的 wallets 變數資料，等等 sql 更新後就會消失了
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
		$wallets_result = runSQLall($wallets_sql);

		// 在剛取出的 wallets 資料庫中gpk2的餘額(支出)
		$gtoken_casino_balance_db = round($wallets_result[1]->casino_balance, 2);
		// 在剛取出的 wallets 資料庫中gtoken的餘額(支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端JTNAPI支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_casino_balance_db + $payout_balance), 2);

		// 交易開始
		$payout_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $casino_balance_db;
		// 操作者
		$d['member_id'] = $accountid;
		// (說明)娛樂城 + 代幣派彩
		$d['summary'] = $api_column['casinoid'] . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select[strtolower($api_column['casinoid'])];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// 娛樂城 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 娛樂城 + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = abs($payout_balance);

		// 操作 root_member_wallets DB，把 娛樂城 balance 設為 0，把 gtoken_lock = null，把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額，把 娛樂城 balance 扣除全部表示支出(投注)
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"" . $api_column['casinoid'] . "\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 ' . $defaultCasinoName . ' 派彩' . $payout_balance . ') by 关闭娱乐城';
		// 針對目的會員的存簿寫入
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance + " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 從會員 ' . $d['destination_transferaccount'] . ' 取回派彩餘額)';
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['balance'] . "', '0', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$payout_transaction_sql = $payout_transaction_sql . 'COMMIT;';
		if ($debug == 1) {
			echo '<p>SQL=' . $payout_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$payout_transaction_result = runSQLtransactions($payout_transaction_sql);
		if ($payout_transaction_result) {
			$logger = '从' . $defaultCasinoName . '帐号' . $casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords($defaultCasinoName, 'lobby', $api_balance, $logger, $accountid, 'info');
		} else {
			//5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
			$logger = '从' . $defaultCasinoName . '帐号' . $casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
			$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords($defaultCasinoName, 'lobby', $api_balance, $logger, $accountid, 'warning');
		}

	} else {
		// 不可能
		$logger = '不可能发生';
		$r['ErrorCode'] = 500;
		$r['ErrorMessage'] = $logger;
		echo "<p> $logger </p>";
	}

	return ($r);
}


/**
 *  娛樂城停用，取回會員在娛樂城代幣用
 *
 * @param mixed $i                 當前處理的組數，由FOR迴圈中的 $i 取得，配合 $check_count 用來計算處理進度用
 * @param mixed $accountid         娛樂城會員ID
 * @param mixed $account           會員帳號
 * @param mixed $casino_account    娛樂城會員帳號
 * @param mixed $gtoken_balance    會員在代幣存薄中的代幣餘額
 * @param mixed $casino_balance_db 會員在代幣存薄中記錄的娛樂城代幣餘額
 * @param mixed $check_count       此次處理的總組數，配合 $i 一起用來計算處理進度用
 * @param int   $debug             除錯模式，預設 0 為關閉
 *
 * @return string 處理結果，以 html 格式表示
 */
function retrieve_casino_balance_for_casino_switchoff($i, $accountid, $account, $casino_account, $gtoken_balance, $casino_balance_db, $check_count, $debug = 0)
{
	global $gtoken_cashier_account;
	global $api_column;

	// 檢查會員在娛樂城的代幣餘額
	$API_data_accountarr = $casino_account;
	$API_data = array(
		'account' => $API_data_accountarr,
		'gamehall' => $api_column['gamehall']
	);
	if ($debug == 1) {
		var_dump($API_data);
	}

	$API_result_account = getDataByAPI('GetAccountDetails', $debug, $API_data);
	if ($debug == 1) {
		var_dump($API_result_account);
	}

	if ($API_result_account['errorcode'] == 0 and $API_result_account['Status'] == 0 and $API_result_account['count'] > 0) {
		$casino_balance = $API_result_account['Result']->balance;
		$process_schedule = round(($i / $check_count) * 100, 2);

		//取回會員代幣
		if ($casino_balance > 0) {
			// 正式取回代幣前先將娛樂城的帳號 UNLOCK 以利接下來取回代幣
			$API_data = array(
				'account' => $API_data_accountarr,
				'gamehall' => $api_column['gamehall']
			);
			if ($debug == 1) {
				var_dump($API_data);
			}

			$API_Lock_result = getDataByAPI('KickUser', $debug, $API_data);
			if ($debug == 1) {
				var_dump($API_Lock_result);
			}
			// 5.1 執行 API 取回 娛樂城 餘額 ，到系統的出納帳戶(API操作) , 成功才執行 5.2,5.3
			// 動作： Withdrawal 帳戶取款
			$API_data = array(
				'account' => $casino_account,
				'amount' => $casino_balance,
				'gamehall' => $api_column['gamehall'],
				'transaction_id' => substr($api_column['casinoid'], 0, 3) . '0Withdrawal0' . date("Ymdhis")
			);

			if ($debug == 1) {
				echo '5.1 執行 ' . $api_column['casinoid'] . ' API 取回 ' . $api_column['casinoid'] . ' 餘額，到 totle egame 的出納帳戶(API操作)，成功才執行 5.2，5.3';
				var_dump($API_data);
			}

			$API_result_account = getDataByAPI('Withdrawal', $debug, $API_data);
			if ($debug == 1) {
				var_dump($API_result_account);
			}

			if ($API_Lock_result['errorcode'] == 0 and $API_result_account['errorcode'] == 0 and $API_result_account['Status'] == 0 and $API_result_account['count'] > 0) {
				// 取回 娛樂城 餘額成功
				$logger = $api_column['casinoid'] . ' API 从帐号' . $casino_account . '取款余额' . $casino_balance . '成功。交易编号为' . $API_result_account['Result']->transaction_id;
				$r['code'] = 100;
				$r['messages'] = $logger;
				member_casino_transferrecords($api_column['casinoid'], 'lobby', $casino_balance, $logger, $accountid,'success', $API_result_account['Result']->transaction_id, 1);
				if ($debug == 1) {
					echo "<p> $logger </p>";
					var_dump($API_result_account);
				}

				// 先取得當下的  wallets 變數資料,等等 sql 更新後，就會消失了。
				// wallets 資料庫中的餘額(支出)
				$casino_balance_local = round($casino_balance_db, 2);
				// 派彩 = 娛樂城餘額 - 本地端 娛樂城 支出餘額
				$payout_balance = round(($casino_balance - $casino_balance_local), 2);

				// 處理 DB 的轉帳問題 -- 5.2 and 5.3
				$db_retrieve_casino_balance_for_casino_switchoff_result = db_retrieve_casino_balance_for_casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account, $casino_balance, $payout_balance, $casino_balance_local);
				if ($debug == 1) {
					echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
					var_dump($db_retrieve_casino_balance_for_casino_switchoff_result);
				}

			} else if (($API_result_account['errorcode'] == 1006 or $API_result_account['errorcode'] == 1015 or
					$API_result_account['errorcode'] == 1999) or ($API_result_account['curl_status'] == 1 and $API_result_account['errorcode'] == 28)) {
				// 帳款轉移時發生 timeout，可能會發生在 API -> 娛樂城 及 平台 -> API
				$logger = $api_column['casinoid'] . ' API 从帐号' . $casino_account . '取款余额' . $casino_balance . '，帐款处理中。交易编号为 ' . $API_result_account['Result']->transaction_id;
				$r['code'] = 19;
				$r['messages'] = $logger;
				// 凍結會員錢包
				updateMemberStatusById($accountid, 2, $debug);
				member_casino_transferrecords($api_column['casinoid'], 'lobby', $casino_balance, $logger, $accountid,'fail', $API_data['transaction_id'], 3);
			} else {
				// 5.1 執行 API 取回 娛樂城 餘額 ，到 娛樂城 的出納帳戶(API操作)，成功才執行 5.2、5.3
				$logger = $api_column['casinoid'] . '娱乐城停用，' . $api_column['casinoid'] . ' API 从帐号' . $casino_account . '取款余额' . $casino_balance . '失败';
				$r['code'] = 405;
				$r['messages'] = $logger;
				member_casino_transferrecords($api_column['casinoid'], 'lobby', $casino_balance, $logger, $accountid,'success', $API_result_account['Result']->transaction_id, 1);
				if ($debug == 1) {
					echo '5.1 執行 ' . $api_column['casinoid'] . ' API 取回 ' . $api_column['casinoid'] . ' 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3';
					echo "<p> $logger </p>";
					var_dump($r);
				}
			}
		} elseif ($casino_balance == 0) {
			$logger = $api_column['casinoid'] . '娱乐城停用，' . $api_column['casinoid'] . '余额 = 0 ，' . $api_column['casinoid'] . '没有余额，无法取回任何的余额，将余额转回 GPK。';
			$r['code'] = 406;
			$r['messages'] = $logger;
			member_casino_transferrecords($api_column['casinoid'], 'lobby', '0', $logger, $accountid, 'success');

			// 先取得當下的 wallets 變數資料，等等 sql 更新後就會消失了
			// wallets 資料庫中的餘額(支出)
			$casino_balance_local = round($casino_balance_db, 2);
			// 派彩 = 娛樂城餘額 - 本地端 娛樂城 支出餘額
			$payout_balance = round(($casino_balance - $casino_balance_local), 2);
			// -----------------------------------------------------------------------------------

			// 處理 DB 的轉帳問題 -- 5.2 and 5.3
			$db_retrieve_casino_balance_for_casino_switchoff_result = db_retrieve_casino_balance_for_casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account, $casino_balance, $payout_balance, $casino_balance_local);
			if ($debug == 1) {
				echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
				var_dump($db_retrieve_casino_balance_for_casino_switchoff_result);
			}
		} else {
			// 娛樂城餘額 < 0 , 不可能發生
			$logger = $api_column['casinoid'] . '余额 < 1 ，不可能发生。';
			$r['code'] = 404;
			$r['messages'] = $logger;
		}

		if ($debug == 1) {
			var_dump($r);
		}

		// 最出處理結果存入JSON檔中以供頁面檢視
		$casino_shutdown_member_html = '<tr>
        <td>' . $account . '</td>
        <td>' . $gtoken_balance . '</td>
        <td>' . $casino_balance_db . '</td>
        <td>' . $casino_balance . '</td>
        <td>' . $process_schedule . '</td>
        </tr>';
	} else {
		$casino_shutdown_member_html = '';
	}

	return ($casino_shutdown_member_html);
}


/**
 * 娛樂城停用，取回會員在娛樂城代幣
 *
 * @param mixed $casino_switch_member_list_result 查詢欲停用娛樂城的現行使用中會員資料
 * @param int $api_limit 娛樂城 API 的批次上限(通用未使用)
 * @param int $debug 除錯模式，預設 0 為關閉
 */
function casino_switch_process($casino_switch_member_list_result, $api_limit, $debug = 0)
{
	global $casino_switch_json;
	global $api_column;

	for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i++) {
		$API_data_accountarr = $casino_switch_member_list_result[$i]->{$api_column['account']};
		// 取回代幣前先將娛樂城的帳號LOCK住，讓會員在取回過程無法下注，以免取回時掉錢
		$API_data = array(
			'account' => $API_data_accountarr,
			'gamehall' => $api_column['gamehall']
		);
		if ($debug == 1) {
			var_dump($API_data);
		}

		$API_Lock_result = getDataByAPI('KickUser', $debug, $API_data);
		if ($debug == 1) {
			var_dump($API_Lock_result);
		}
	}

	// 等待15秒，讓正在下注的會員結束此次的下注，並取得派彩結果
	sleep(15);

	// 確定帳號lock了後再進行回收代幣的動作
	for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i++) {
		$now = $i;
		$step_count = $casino_switch_member_list_result['0'] * 2;
		// 對現行在娛樂城的會員進行代幣回收
		$casino_switch_member_html = retrieve_casino_balance_for_casino_switchoff($now, $casino_switch_member_list_result[$i]->id, $casino_switch_member_list_result[$i]->account, $casino_switch_member_list_result[$i]->{$api_column['account']}, $casino_switch_member_list_result[$i]->gtoken_balance, $casino_switch_member_list_result[$i]->{$api_column['balance']}, $step_count, 1);
		fwrite($casino_switch_json, $casino_switch_member_html);
	}

}


/**
 *  將傳入的 member id 值，取得他的 DB GTOKEN 餘額並全部傳送到  CASINO 上
 *  把本地端的資料庫 root_member_wallets 的 GTOKEN_LOCK設定為 CASINO，餘額儲存在 casino_balance 上面
 *
 * @param mixed $memberid 會員 ID
 * @param int $debug 除錯模式，預設 0 為關閉
 *
 * @return array 遊戲幣轉帳訊息
 */
function transferout_gtoken_to_casino_balance($memberid, $debug = 0)
{
	global $config;
	global $api_column;

	// 將目前所在的 ID 值驗證並取得帳戶資料
	$member_sql = "SELECT root_member.id,gtoken_balance,account,gtoken_lock,
                casino_accounts->'" . $api_column['casinoid'] . "'->>'account' as casino_account,
                casino_accounts->'" . $api_column['casinoid'] . "'->>'password' as casino_password,
                casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '" . $memberid . "';";
	$r = runSQLall($member_sql);
	if ($debug == 1) {
		var_dump($r);
	}

	$casinoLib = new casino_switch_process_lib();
	$casinoSql = 'SELECT display_name FROM "casino_list" WHERE "casinoid" = \''. strtoupper($api_column['casinoid'])	.'\'';
	$displayName = runSQLall($casinoSql, $debug)[1]->display_name;
	$defaultCasinoName = $casinoLib->getCurrentLanguageCasinoName($displayName, 'default');

	if ($r[0] == 1 and $config['casino_transfer_mode'] == 1) {
		// 沒有 娛樂城 帳號的話，根本不可以進來。
		if ($r[1]->casino_account == null or $r[1]->casino_account == '') {
			$check_return['messages'] = '你還沒有 ' . $defaultCasinoName . ' 帳號。';
			$check_return['code'] = 12;
		} else {
			$memberid = $r[1]->id;
			$memberaccount = $r[1]->account;
			$casino_balance = round($r[1]->casino_balance, 2);
			$member_casino_account = $r[1]->casino_account;

			// 需要 gtoken_lock 沒有被設定的時候，才可以使用這功能。
			if ($r[1]->gtoken_lock == null or $r[1]->gtoken_lock == $api_column['casinoid']) {
				// 動作： 將本地端所有的 gtoken 餘額 Deposit 到對應的帳戶
				$accountNumber = $member_casino_account;
				$amount = $r[1]->gtoken_balance;
				$API_data = array(
					'gamehall' => $api_column['gamehall'],
					'account' => $accountNumber,
					'amount' => $amount,
					'transaction_id' => substr($api_column['casinoid'], 0, 3) . '0Deposit0' . date("Ymdhis")
				);

				$API_result = getDataByAPI('Deposit', $debug, $API_data);
				if ($API_result['errorcode'] == 0 and $API_result['Status'] == 0 and $API_result['count'] >= 0) {
					if ($debug == 1) {
						var_dump($API_data);
						var_dump($API_result);
					}
					// 本地端 db 的餘額處理
					$casino_balance = $casino_balance + $amount;
					$togtoken_sql = "UPDATE root_member_wallets SET gtoken_lock = '" . $api_column['casinoid'] . "'  WHERE id = '$memberid';";
					$togtoken_sql = $togtoken_sql . 'UPDATE root_member_wallets SET gtoken_balance = gtoken_balance - \'' . $amount . '\',casino_accounts= jsonb_set(casino_accounts,\'{"' . $api_column['casinoid'] . '","balance"}\',\'' . $casino_balance . '\') WHERE id = \'' . $memberid . '\';';
					$togtoken_sql_result = runSQLtransactions($togtoken_sql);
					if ($debug == 1) {
						var_dump($togtoken_sql);
						var_dump($togtoken_sql_result);
					}
					if ($togtoken_sql_result) {
						$check_return['messages'] = '所有GTOKEN余额已经转到' . $defaultCasinoName . '娱乐城。 ' . $defaultCasinoName . '转帐单号 ' . $API_result['Result']->transaction_id . $defaultCasinoName . '帐号' . $accountNumber . $defaultCasinoName . '新增' . $amount;
						$check_return['code'] = 1;
						memberlog2db($memberaccount, 'casino transferout', 'info', $check_return['messages']);
						member_casino_transferrecords('lobby', $defaultCasinoName, $amount, $check_return['messages'], $memberid, 'success', $API_result['Result']->transaction_id, 1);
					} else {
						$check_return['messages'] = '余额处理，本地端资料库交易错误。';
						$check_return['code'] = 14;
						memberlog2db($memberaccount, 'casino transferout', 'error', $check_return['messages']);
						member_casino_transferrecords('lobby', $defaultCasinoName, $amount, $check_return['messages'], $memberid, 'warning', $API_result['Result']->transaction_id, 2);
					}
				} elseif (($API_result['errorcode'] == 1006 or $API_result['errorcode'] == 1015 or
						$API_result['errorcode'] == 1999) or ($API_result['curl_status'] == 1 and $API_result['errorcode'] == 28)) {
					$check_return['messages'] = '所有GTOKEN余额转到' . $defaultCasinoName . '娱乐城，帐款处理中。转帐单号 '. $API_result['Result']->transaction_id .' 帐号 ' . $accountNumber .'。';
					$check_return['code'] = 19;
					// 凍結會員錢包
					updateMemberStatusById($memberid, 2, $debug);
					member_casino_transferrecords('lobby', $defaultCasinoName, $amount, $check_return['messages'],	$memberid, 'fail', $API_data['transaction_id'], 3);
				} else {
					$check_return['messages'] = '余额转移到 ' . $defaultCasinoName . ' 时失败！！';
					$check_return['code'] = 13;
					memberlog2db($memberaccount, 'casino transferout', 'error', $check_return['messages']);
					member_casino_transferrecords('lobby', $defaultCasinoName, $amount, $check_return['messages'] . '(' . $API_result['Result'] . ')', $memberid, 'fail', $API_result['Result']->transaction_id, 2);
				}
			} else {
				$check_return['messages'] = '此帐号已经在 ' . $defaultCasinoName . ' 娱乐城活动，请勿重复登入。';
				$check_return['code'] = 11;
				member_casino_transferrecords('lobby', $defaultCasinoName, '0', $check_return['messages'], $memberid, 'warning');
			}
		}
	} elseif ($r[0] == 1 and $config['casino_transfer_mode'] == 0) {
		$check_return['messages'] = '测试环境不进行转帐交易';
		$check_return['code'] = 1;
		member_casino_transferrecords('lobby', $defaultCasinoName, '0', $check_return['messages'], $memberid, 'info');
	} else {
		$check_return['messages'] = '无此帐号 ID = ' . $memberid;
		$check_return['code'] = 0;
		member_casino_transferrecords('lobby', $defaultCasinoName, '0', $check_return['messages'], $memberid, 'fail');
	}

	return ($check_return);
}


/**
 *  取回 娛樂城 的餘額 -- 針對 db 的處理函式，只針對此功能有用
 *  能單獨使用，需要搭配 retrieve_casino_balance
 *  本方法執行下列邏輯：
 *  5.2 把 API 傳回的餘額，透過 GTOKEN 出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶(DB操作)
 *  5.3 紀錄當下 DB 娛樂城_balance 的餘額紀錄：存摺 GTOKEN 紀錄: (CASINO API)收入 =4 ，(DB GPCASINO 餘額)支出 = 10 ，(派彩)餘額 = -6 + 原有結餘
 *
 * @param mixed $memberaccount 會員帳號
 * @param mixed $memberid 會員ID
 * @param mixed $member_casino_account 會員娛樂城帳號
 * @param mixed $gtoken_cashier_account 統代幣出納帳號
 * @param mixed $api_balance  API 餘額
 * @param mixed $payout_balance 派彩
 * @param mixed $casino_balance_db 資料庫錢包餘額
 * @param int $debug 除錯模式，預設 0 為關閉
 *
 * @return mixed 處理結果，1 為成功
 */
function db_retrieve_casino_balance($memberaccount, $memberid, $member_casino_account, $gtoken_cashier_account, $api_balance, $payout_balance, $casino_balance_db, $debug = 0)
{
	global $gtoken_cashier_account;
	global $transaction_category;
	global $auditmode_select;
	global $api_column;
	global $config;

	$casinoLib = new casino_switch_process_lib();
	$casinoSql = 'SELECT display_name FROM "casino_list" WHERE "casinoid" = \''. strtoupper($api_column['casinoid'])	.'\'';
	$displayName = runSQLall($casinoSql, $debug)[1]->display_name;
	$defaultCasinoName = $casinoLib->getCurrentLanguageCasinoName($displayName, 'default');

	// 取得來源與目的帳號的 id，$gtoken_cashier_account(此為系統代幣出納帳號 global var.)
	$d['source_transferaccount'] = $gtoken_cashier_account;
	$d['destination_transferaccount'] = $memberaccount;
	$source_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['source_transferaccount'] . "';";
	$source_id_result = runSQLall($source_id_sql);
	$destination_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['destination_transferaccount'] . "';";
	$destination_id_result = runSQLall($destination_id_sql);

	if ($source_id_result[0] == 1 and $destination_id_result[0] == 1) {
		$d['source_transfer_id'] = $source_id_result[1]->id;
		$d['destination_transfer_id'] = $destination_id_result[1]->id;
	} else {
		$logger = '转帐的来源与目的帐号可能有问题，请稍候再试。';
		$r['ErrorCode'] = 590;
		$r['ErrorMessage'] = $logger;
		echo "<p> $logger </p>";
		die();
	}

	if ($debug == 1) {
		var_dump($payout_balance);
	}

	// 派彩有三種狀態，要有不同的對應 SQL 處理
	if ($payout_balance >= 0) {
		// $payout_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 娛樂城 餘額取回

		// 先取得當下的 wallets 變數資料，等等 sql 更新後就會消失了
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
		$wallets_result = runSQLall($wallets_sql);

		// 在剛取出的 wallets 資料庫中 娛樂城 的餘額(支出)
		$gtoken_casino_balance_db = round($wallets_result[1]->casino_balance, 2);
		// 在剛取出的 wallets 資料庫中 gtoken(代幣) 的餘額(支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端 支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_casino_balance_db + $payout_balance), 2);

		// 交易開始
		$payout_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $casino_balance_db;
		// 操作者
		$d['member_id'] = $memberid;
		// (說明)娛樂城 + 代幣派彩
		$d['summary'] = $api_column['casinoid'] . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select[strtolower($api_column['casinoid'])];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// 娛樂城 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 娛樂城 + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = $payout_balance;

		// 操作 root_member_wallets DB，把 casino_balance 設為 0，把 gtoken_lock = null，把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額 , 把 casino_balance 扣除全部表示支出(投注)
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"" . $api_column['casinoid'] . "\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 ' . $defaultCasinoName . ' 派彩' . $d['balance'] . ' by 客服人員 ' . $_SESSION['agent']->account . ')';
		// 針對目的會員的存簿寫入，$payout_balance >= 1 表示贏錢，所以從出納匯款到使用者帳號
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance - " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 幫 ' . $defaultCasinoName . ' 派彩到會員 ' . $d['destination_transferaccount'] . ')';
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '0', '" . $d['balance'] . "', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$payout_transaction_sql = $payout_transaction_sql . 'COMMIT;';
		if ($debug == 1) {
			echo '<p>SQL=' . $payout_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$payout_transaction_result = runSQLtransactions($payout_transaction_sql);
		if ($payout_transaction_result) {
			$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'gpk2game', 'info', "$logger");
			member_casino_transferrecords($defaultCasinoName, 'lobby', $api_balance, $logger, $memberid, 'info');
		} else {
			// 5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理(message system)
			$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
			$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
			memberlog2db($d['member_id'], 'gpk2_transaction', 'error', "$logger");
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'gpk2game', 'error', "$logger");
			member_casino_transferrecords($defaultCasinoName, 'lobby', $api_balance, $logger, $memberid, 'warning');
		}

		if ($debug == 1) {
			var_dump($r);
		}
	} elseif ($payout_balance < 0) {
		// $payout_balance < 0; 從娛樂城輸錢
		// 先取得當下的  wallets 變數資料，等等 sql 更新後，就會消失了
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
		$wallets_result = runSQLall($wallets_sql);

		// 在剛取出的 wallets 資料庫中 娛樂城 的餘額(支出)
		$gtoken_casino_balance_db = round($wallets_result[1]->casino_balance, 2);
		// 在剛取出的 wallets 資料庫中 gtoken(代幣) 的餘額(支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端 支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_casino_balance_db + $payout_balance), 2);

		// 交易開始
		$payout_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $casino_balance_db;
		// 操作者
		$d['member_id'] = $memberid;
		// (說明)娛樂城 + 代幣派彩
		$d['summary'] = $api_column['casinoid'] . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select[strtolower($api_column['casinoid'])];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// 娛樂城 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 娛樂城 + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = abs($payout_balance);

		// 操作 root_member_wallets DB，把 casino_balance 設為 0，把 gtoken_lock = null，把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額，把 casino_balance 扣除全部表示支出(投注).
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"" . $api_column['casinoid'] . "\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 ' . $defaultCasinoName . ' 派彩' . $d['balance'] . ' by 客服人員 ' . $_SESSION['agent']->account . ')';
		// 針對目的會員的存簿寫入
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$payout_transaction_sql = $payout_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance + " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 從會員 ' . $d['destination_transferaccount'] . ' 取回派彩餘額)';
		$payout_transaction_sql = $payout_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['balance'] . "', '0', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$payout_transaction_sql = $payout_transaction_sql . 'COMMIT;';
		if ($debug == 1) {
			echo '<p>SQL=' . $payout_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$payout_transaction_result = runSQLtransactions($payout_transaction_sql);
		if ($payout_transaction_result) {
			$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'gpk2game', 'info', "$logger");
			member_casino_transferrecords($defaultCasinoName, 'lobby', $api_balance, $logger, $memberid, 'info');
		} else {
			// 5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理(message system)
			$logger = '从' . $defaultCasinoName . '帐号' . $member_casino_account . '取回余额到游戏币，统计后收入=' . $api_balance . '，支出=' . $casino_balance_db . '，共计派彩=' . $payout_balance;
			$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'gpk2game', 'error', "$logger");
			member_casino_transferrecords($defaultCasinoName, 'lobby', $api_balance, $logger, $memberid, 'warning');
		}
	} else {
		// 不可能
		$logger = '不可能发生';
		$r['ErrorCode'] = 500;
		$r['ErrorMessage'] = $logger;
		memberlog2db($memberaccount, 'gpk2game', 'error', "$logger");
		echo "<p> $logger </p>";
	}

	return ($r);
}


/**
 *  取回 娛樂城 Casino 的餘額，並檢查上次離開時和目前的差額得出派彩金額，紀錄存簿
 *  方法：
 *  retrieve_casino_balance(...)  取回 娛樂城 的餘額
 *  db_retrieve_casino_balance(...) 取回 娛樂城 的餘額 -- 針對 db 的處理函式
 *
 * 1. 查詢 DB 的 gtoken_lock  是否有紀錄在 娛樂城 帳戶，NULL 沒有紀錄的話表示沒有餘額在 娛樂城 帳戶
 * 2. AND 當 session 有 娛樂城_balance 的時候才動作，如果沒有則結束，表示 db 帳號資料有問題
 * (Deprecated) 3. lock 這個程序, 確保唯一性。使用 $_SESSION['wallet_transfer']  旗標，鎖住程序，不要同時間同一個人執行。需要配合 session_write_close() 才可以。
 * 4.  session 有 娛樂城_balance，gtoken 紀錄為 目的地娛樂城 ，API 檢查 娛樂城 的餘額有多少
 * 5. 承接 4，如果 娛樂城 餘額 > 1
 *     5.1 執行 API 取回 娛樂城 餘額 到 娛樂城 的出納帳戶(API操作) ， 成功才執行 5.2、5.3
 *     5.2 把 API 傳回的餘額，透過 GTOKEN 出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶(DB操作)
 *     5.3 紀錄當下 DB 娛樂城_balance 的餘額紀錄：存摺 GTOKEN 紀錄: ( CASINO API)收入 =4 ，(DB CASINO 餘額)支出 = 10 ，(派彩)餘額 = -6 + 原有結餘，
 *          摘要：娛樂城 派彩(DB操作)
 *     5.1 ~ 5.3 必須要全部成功，才算成功。如果 5.1 成功後，但 5.2、5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理(message system)
 * 6. 紀錄這次的 retrieve_casino_balance 操作，為一次交易紀錄。後續可以查詢(Confirmation Number)
 * 7. 執行完成後，需要 reload page，把 $_SESSION['wallet_transfer'] 變數清除, 其他程式才可以進入
 * 8. 把 GTOKEN_LOCK 設定為 NULL , 表示已經沒有餘額在娛樂城了
 */

/**
 *  取回 娛樂城 的餘額，不能單獨使用，需要搭配 db_retrieve_casino_balance 使用
 *
 * @param mixed $memberid 會員 ID
 * @param int $debug 除錯模式，預設 0 為關閉
 *
 * @return array 轉帳結果
 */
function retrieve_casino_balance($memberid, $debug = 0)
{
	global $gtoken_cashier_account;
	global $api_column;

	// 判斷會員是否 status 是否被鎖定了!!
	$member_sql = "SELECT * FROM root_member WHERE id = '" . $memberid . "' AND status = '1';";
	$member_result = runSQLall($member_sql);

	// 先取得當下的 member_wallets 變數資料，等等 sql 更新後就會消失了
	$wallets_sql = "SELECT gtoken_balance,gtoken_lock,
              casino_accounts->'" . $api_column['casinoid'] . "'->>'account' as casino_account,
              casino_accounts->'" . $api_column['casinoid'] . "'->>'password' as casino_password,
              casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $memberid . "';";
	$wallets_result = runSQLall($wallets_sql);
	if ($debug == 1) {
		var_dump($wallets_sql);
		var_dump($wallets_result);
	}

	$casinoLib = new casino_switch_process_lib();
	$casinoSql = 'SELECT display_name FROM "casino_list" WHERE "casinoid" = \''. strtoupper($api_column['casinoid'])	.'\'';
	$displayName = runSQLall($casinoSql, $debug)[1]->display_name;
	$casinoDefaultName = $casinoLib->getCurrentLanguageCasinoName($displayName, 'default');

	// 1. 查詢 DB 的 gtoken_lock  是否有紀錄在 娛樂城 帳戶，NULL 沒有紀錄的話表示沒有餘額在 娛樂城 帳戶
	// 2. AND 當 session 有 娛樂城_balance 的時候才動作，如果沒有則結束，表示 db 帳號資料有問題
	if ($member_result[0] == 1 and $wallets_result[0] == 1 and $wallets_result[1]->casino_account != null and $wallets_result[1]->gtoken_lock == $api_column['casinoid']) {
		$memberaccount = $member_result[1]->account;
		$memberid = $member_result[1]->id;
		$member_casino_account = $wallets_result[1]->casino_account;

		// 4. session 有 娛樂城_balance，gtoken 紀錄為 目的地娛樂城 ，API 檢查 娛樂城 的餘額有多少
		$delimitedAccountNumbers = $wallets_result[1]->casino_account;
		$API_data = array(
			'account' => $delimitedAccountNumbers,
			'gamehall' => $api_column['gamehall']
		);
		if ($debug == 1) {
			var_dump($API_data);
		}
		$API_result = getDataByAPI('GetAccountDetails', $debug, $API_data);
		$API_kickuser_result = getDataByAPI('KickUser', $debug, $API_data);
		if ($API_result['errorcode'] == 0 and $API_result['Status'] == 0 and $API_result['count'] >= 0 and $API_kickuser_result['errorcode'] == 0) {
			// 查詢餘額動作，成立後執行，失敗的話結束，可能網路有問題
			// 取得的 API 餘額 , 保留小數第二位 round( $x, 2);
			$casino_balance_api = round($API_result['Result']->balance, 2);
			$logger = $casinoDefaultName . ' API 查询余额为' . $API_result['Result']->balance . '操作的余额为' . $casino_balance_api;
			$r['code'] = 1;
			$r['messages'] = $logger;

			// 5. 承接 4，如果 娛樂城 餘額 > 1
			// -----------------------------------------------------------------------------------
			if ($casino_balance_api > 0) {
				// 5.1 執行 API 取回 娛樂城 餘額 到 娛樂城 的出納帳戶(API操作)，成功才執行 5.2、5.3
				// 動作： Withdrawal 帳戶取款
				$API_data = array(
					'gamehall' => $api_column['gamehall'],
					'account' => $wallets_result[1]->casino_account,
					'amount' => $casino_balance_api,
					'transaction_id' => substr($api_column['casinoid'], 0, 3) . '0Withdrawal0' . date("Ymdhis")
				);
				if ($debug == 1) {
					echo '5.1 執行 ' . $casinoDefaultName . ' API 取回 ' . $casinoDefaultName . ' 餘額 ，到 ' . $casinoDefaultName . ' 的出納帳戶(API操作) , 成功才執行 5.2,5.3';
					var_dump($API_data);
				}
				$API_result = getDataByAPI('Withdrawal', $debug, $API_data);
				if ($debug == 1) {
					var_dump($API_result);
				}

				if ($API_result['errorcode'] == 0 and $API_result['Status'] == 0 and $API_result['count'] >= 0) {
					// 取回 娛樂城 餘額成功
					$logger = $casinoDefaultName . ' API 从帐号' . $wallets_result[1]->casino_account . '取款余额' . $casino_balance_api . '成功。交易编号为' . $API_result['Result']->transaction_id;
					$r['code'] = 100;
					$r['messages'] = $logger;
					memberlog2db($memberaccount, 'gpk2game', 'info', "$logger");
					member_casino_transferrecords($casinoDefaultName, 'lobby', $casino_balance_api, $logger, $memberid, 'success', $API_result['Result']->transaction_id, 1);
					if ($debug == 1) {
						echo "<p> $logger </p>";
						var_dump($API_result);
					}

					// 先取得當下的 wallets 變數資料，等等 sql 更新後就會消失了
					$wallets_sql = "SELECT casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $memberid . "';";
					$wallets_result = runSQLall($wallets_sql);
					// 在剛取出的 wallets 資料庫中的餘額(支出)
					$casino_balance_db = round($wallets_result[1]->casino_balance, 2);
					// 派彩 = 娛樂城餘額 - 本地端 娛樂城 支出餘額
					$payout_balance = round(($casino_balance_api - $casino_balance_db), 2);

					// 處理 DB 的轉帳問題 -- 5.2 and 5.3
					$db_retrieve_casino_balance_result = db_retrieve_casino_balance($memberaccount, $memberid, $member_casino_account, $gtoken_cashier_account, $casino_balance_api, $payout_balance, $casino_balance_db);
					if ($db_retrieve_casino_balance_result['ErrorCode'] == 1) {
						$r['code'] = 1;
						$r['messages'] = $db_retrieve_casino_balance_result['ErrorMessage'];
						$logger = $r['messages'];
						memberlog2db($memberaccount, 'gpk22gpk', 'info', "$logger");
					} else {
						$r['code'] = 523;
						$r['messages'] = $db_retrieve_casino_balance_result['ErrorMessage'];
						$logger = $r['messages'];
						memberlog2db($memberaccount, 'gpk22gpk', 'error', "$logger");
					}

					if ($debug == 1) {
						echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
						var_dump($db_retrieve_casino_balance_result);
					}
				} elseif (($API_result['errorcode'] == 1006 or $API_result['errorcode'] == 1015 or
						$API_result['errorcode'] == 1999) or ($API_result['curl_status'] == 1 and $API_result['errorcode'] == 28)) {
					$check_return['messages'] = $casinoDefaultName . ' API 从帐号' . $member_casino_account . '取款余额' . $casino_balance_api . '，帐款处理中。转帐单号 '. $API_result['Result']->transaction_id .' 帐号 ' . $memberid .'。';
					$check_return['code'] = 19;
					// 凍結會員錢包
					updateMemberStatusById($memberid, 2, $debug);
					member_casino_transferrecords($casinoDefaultName, 'lobby', $casino_balance_api, $check_return['messages'], $memberid, 'fail', $API_data['transaction_id'], 3);
				} else {
					// 5.1 執行 API 取回 娛樂城 餘額，到 娛樂城 的出納帳戶(API操作)，成功才執行 5.2、5.3
					$logger = $casinoDefaultName . ' API 从帐号' . $member_casino_account . '取款余额' . $casino_balance_api . '失败';
					$r['code'] = 405;
					$r['messages'] = $logger;
					memberlog2db($memberaccount, 'gpk2game', 'error', "$logger");

					if ($debug == 1) {
						echo "5.1 執行 {$casinoDefaultName} API 取回 {$casinoDefaultName} 餘額 ，到 {$casinoDefaultName} 的出納帳戶(API操作) , 成功才執行 5.2,5.3";
						echo "<p> $logger </p>";
						var_dump($r);
					}
				}
			} elseif ($casino_balance_api == 0) {
				$logger = $casinoDefaultName . '余额 = 0 ，' . $casinoDefaultName . '没有余额，无法取回任何的余额，将余额转回 GPK。';
				$r['code'] = 406;
				$r['messages'] = $logger;
				memberlog2db($memberaccount, 'gpk2game', 'info', "$logger");
				member_casino_transferrecords($casinoDefaultName, 'lobby', '0', $logger, $memberid, 'success');

				// 先取得當下的 wallets 變數資料，等等 sql 更新後就會消失了
				$wallets_sql = "SELECT casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $memberid . "';";
				$wallets_result = runSQLall($wallets_sql);

				// 在剛取出的 wallets 資料庫中的餘額(支出)
				$casino_balance_db = round($wallets_result[1]->casino_balance, 2);
				// 派彩 = 娛樂城餘額 - 本地端 娛樂城 支出餘額
				$payout_balance = round(($casino_balance_api - $casino_balance_db), 2);
				// -----------------------------------------------------------------------------------

				// 處理 DB 的轉帳問題 -- 5.2 and 5.3
				$db_retrieve_casino_balance_result = db_retrieve_casino_balance($memberaccount, $memberid, $member_casino_account, $gtoken_cashier_account, $casino_balance_api, $payout_balance, $casino_balance_db);
				if ($db_retrieve_casino_balance_result['ErrorCode'] == 1) {
					$r['code'] = 1;
					$r['messages'] = $db_retrieve_casino_balance_result['ErrorMessage'];
					$logger = $r['messages'];
					memberlog2db($memberaccount, 'gpk22gpk', 'info', "$logger");
				} else {
					$r['code'] = 523;
					$r['messages'] = $db_retrieve_casino_balance_result['ErrorMessage'];
					$logger = $r['messages'];
					memberlog2db($memberaccount, 'gpk22gpk', 'error', "$logger");
				}

				if ($debug == 1) {
					echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
					var_dump($db_retrieve_casino_balance_result);
				}
			} else {
				// 娛樂城 餘額 < 0，不可能發生
				$logger = $casinoDefaultName . '余额 < 1 ，不可能发生。';
				$r['code'] = 404;
				$r['messages'] = $logger;
			}
			// -----------------------------------------------------------------------------------
		} else {
			// 4. session 有 娛樂城_balance，gtoken 紀錄為 目的地娛樂城 ，API 檢查 娛樂城 的餘額有多少
			$logger = $casinoDefaultName . ' API 查询余额失败，系统维护中请晚点再试。';
			$r['code'] = 403;
			$r['messages'] = $logger;
			member_casino_transferrecords($casinoDefaultName, 'lobby', '0', $logger . '(' . $API_result['Result'] . ')', $memberid, 'fail');
			if ($debug == 1) {
				var_dump($API_result);
			}
		}
	} else {
		// 1. 查詢 DB 的 gtoken_lock  是否有紀錄在 娛樂城 帳戶，NULL 沒有紀錄的話表示沒有餘額在 娛樂城 帳戶
		// 2. AND 當 session 有 娛樂城_balance 的時候才動作，如果沒有則結束，表示 db 帳號資料有問題
		$logger = '没有余额在 ' . $casinoDefaultName . ' 帐户 OR DB 帐号资料有问题 ';
		$r['code'] = 401;
		$r['messages'] = $logger;
		member_casino_transferrecords($casinoDefaultName, 'lobby', '0', $logger, $memberid, 'fail');
	}

	if ($debug == 1) {
		echo "<p> $logger </p>";
		var_dump($r);
	}
	if ($r['code'] == 1) {
		unset($_SESSION['wallet_transfer']);
	}

	return ($r);
}


/**
 *  透過 API 取得娛樂城餘額
 *
 * @param mixed $memberid 會員ID
 * @param int $debug 除錯模式，預設 0 為關閉
 *
 * @return mixed 娛樂城餘額
 */
function getCasinoBalanceByAPI($memberid, $debug = 0)
{
	global $api_column;
	$casino_balance_api = '';

	// 判斷會員是否 status 是否被鎖定了!!
	$member_sql = "SELECT * FROM root_member WHERE id = '" . $memberid . "' AND status = '1';";
	$member_result = runSQLall($member_sql);

	// 先取得當下的 member_wallets 變數資料
	$wallets_sql = "SELECT gtoken_balance,gtoken_lock,
                casino_accounts->'" . $api_column['casinoid'] . "'->>'account' as casino_account,
                casino_accounts->'" . $api_column['casinoid'] . "'->>'password' as casino_password,
                casino_accounts->'" . $api_column['casinoid'] . "'->>'balance' as casino_balance FROM root_member_wallets WHERE id = '" . $memberid . "';";
	$wallets_result = runSQLall($wallets_sql);
	if ($debug == 1) {
		var_dump($wallets_sql);
		var_dump($wallets_result);
	}

	if ($member_result[0] == 1 and $wallets_result[0] == 1 and $wallets_result[1]->casino_account != null) {
		// 查詢在 casino 的餘額
		$delimitedAccountNumbers = $wallets_result[1]->casino_account;
		$API_data = array(
			'account' => $delimitedAccountNumbers,
			'gamehall' => $api_column['gamehall']
		);
		if ($debug == 1) {
			var_dump($API_data);
		}

		$API_result = getDataByAPI('GetAccountDetails', $debug, $API_data);
		if ($API_result['errorcode'] == 0 and $API_result['Status'] == 0 and $API_result['count'] >= 0) {
			// 查詢餘額動作，成立後執行，失敗的話結束，可能網路有問題
			// 取得的 API 餘額 , 保留小數第二位 round( $x, 2);
			$casino_balance_api = round($API_result['Result']->balance, 2);
		}
	}
	return $casino_balance_api;
}


/**
 * 確認會員是否在線上遊戲
 *
 * @param mixed $casino_member 會員娛樂城帳號
 * @param int $debug 除錯模式，預設 0 為關閉
 *
 * @return bool 是否在線
 */
function check_casino_account_online($casino_member, $debug = 0)
{
	global $api_column;
	$check_result = false;
	$casino_accounts = json_decode($casino_member->casino_accounts);

	$API_data = array(
		'account' => $casino_accounts->{$api_column['casinoid']}->account,
		'gamehall' => $api_column['gamehall']
	);
	if ($debug == 1) {
		var_dump($API_data);
	}

	$API_result = getDataByAPI('CheckUserIsGaming', $debug, $API_data);
	if ($API_result['errorcode'] == 0 and $API_result['Status'] == 0 and $API_result['count'] >= 0) {
		// 查詢成立後執行，失敗的話結束，可能網路有問題
		$check_result = $API_result['Result']->is_gaming;
	}

	return $check_result;
}
