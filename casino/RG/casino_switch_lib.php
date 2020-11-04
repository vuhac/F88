<?php
/**
 *  RG Lottery Switch 的專用函式庫
 *
 * @author Letter
 * @date   2018/12/21
 * @since  version_no 2018/12/21 Letter: 新建
 */

/*
// function 索引及說明：
1. RG Lottery API 文件函式及用法 sample , 操作 RG Lottery API
rg_api($method, $debug=0, $RG_API_data)
2. 產生會員轉換娛樂城的紀錄
member_casino_transferrecords($source, $destination, $token, $note, $memberid)
3. 停用娛樂城時取回會員在娛樂城餘額用
db_rg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account, $rg_balance_api,
$rg2gpk_balance, $rg_balance_db, $debug=0 )
4. 娛樂城停用，取回會員在娛樂城代幣用取回FUNCTION,需配合 db_rg2gpk_balance_4casino_switchoff()
retrieve_rg_restful_casino_balance_4casino_switchoff($i, $accountid, $account, $casino_account, $gtoken_balance,
$casino_dbbalance, $check_count, $debug=0)
5. 娛樂城停用，取回會員在娛樂城代幣用主FUNCTION,需配合 retrieve_rg_restful_casino_balance_4casino_switchoff()
casino_switch_process_rg($casino_switch_member_list_result, $api_limit, $debug=0)
6. 取得會員的 DB GTOKEN 餘額並全部傳送到 RG CASINO 上
transferout_gtoken_rg_casino_balance($memberid, $debug = 0)
7. 取回 RG Casino 的餘額 -- 針對 db 的處理函式，只針對此功能有用。不能單獨使用，需要搭配 retrieve_rg_casino_balance
db_rg2gpk_balance($gtoken_cashier_account, $pt_balance_api, $rg2gpk_balance, $pt_balance_db, $debug=0)
8. 取回 RG Casino 的餘額
retrieve_rg_casino_balance($memberid, $debug=0)
9. 取得會員目前在 RG Casino 的餘額
getbalance_rg($memberid, $debug=0)
10. 取得會員是否在線狀態
check_account_online_rg($account, $debug=0)
*/


/**
 * 呼叫 RG Lottery API 方法
 *
 * @param string $method      呼叫方法
 * @param int    $debug       是否為除錯模式，0為非除錯模式
 * @param array  $RG_API_data API 所需要參數
 *
 * @return mixed 呼叫 API 結果
 */
function rg_api($method, $debug = 0, array $RG_API_data)
{
	//$debug=1;
	// 設定 socket_timeout , http://php.net/manual/en/soapclient.soapclient.php
	ini_set('default_socket_timeout', 5);

	global $RGAPI_CONFIG;

	// 依照叫用API方法組成完整 API URL
	// url patten: https://api.base.rul/method.url?Key=key_patten&param1=param1&...
	$url = $RGAPI_CONFIG['api_url'] . $RGAPI_CONFIG['sub_url'][$method];
	$apiUrl = '';

	// switch case for method block start
	switch ($method) {
		case 'GetMemberCurrentInfo':
			$key = genApiKey($RGAPI_CONFIG['apikey'], $RG_API_data);
			$apiUrl = $url . 'Key=' . $key . '&memberIds=' . $RG_API_data['memberIds'];
			break;
		case 'Transfer':
			$key = genApiKey($RGAPI_CONFIG['apikey'], $RG_API_data);
			$apiUrl = $url . 'Key=' . $key . '&memberId=' . $RG_API_data['memberId'] . '&transactionId=' .
				$RG_API_data['transactionId'] . '&amount=' . $RG_API_data['amount'] . '&transferType=' .
				$RG_API_data['transferType'];
			break;
		case 'Login':
			$key = genApiKey($RGAPI_CONFIG['apikey'], $RG_API_data);
			$apiUrl = $url . 'Key=' . $key . '&masterId=' . $RG_API_data['masterId'] . '&memberId=' .
				$RG_API_data['memberId'] . '&gameId=' . $RG_API_data['gameId'] . '&memberBranch=' .
				json_encode($RG_API_data['memberBranch']);
			break;
		case 'KickMember':
			$key = genApiKey($RGAPI_CONFIG['apikey'], $RG_API_data);
			$apiUrl = $url . 'Key=' . $key . '&memberIds=' . $RG_API_data['memberIds'];
			break;
		default:
			break;
	}
	// switch case for method block end

	// 執行 curl 呼叫 API if/else block start
	if (isset($RG_API_data)) {
		$ret = array();
		try {
			$ch = curl_init($apiUrl);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  true);
			curl_setopt($ch, CURLOPT_CAINFO,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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

				// curl 正確
				$ret['curl_status'] = 0;
				$ret['error'] = 0;
				$ret['Result'] = $body;
			} else {
				// curl 錯誤
				$ret['curl_status'] = 1;
				$ret['error'] = curl_errno($ch);
				// 錯誤訊息
				$ret['Result'] = '系统维护中，请稍候再试';
			}
			// 關閉 curl
			curl_close($ch);
		} catch (Exception $e) {
			// curl 錯誤
			$ret['curl_status'] = 1;
			$ret['error'] = 500;
			// 錯誤訊息
			$ret['Result'] = $e->getMessage();
		}
	} else {
		$ret = 'NAN';
	}
	// 執行 curl 呼叫 API if/else block start

	return ($ret);
}


/**
 * 取回 RG Casino 的餘額
 * 1.針對 db 的處理函式，只針對此功能有用，不能單獨使用，需要搭配 retrieve_rg_restful_casino_balance_4casino_switchoff
 * 2.把 RG Lottery API 傳回的餘額，透過 GTOKEN出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶。 (DB操作)
 * 3.紀錄當下 DB rg_balance 的餘額紀錄：存摺 GTOKEN 紀錄
 * EX. (RG API)收入=4 , (DB RG餘額)支出=10 ,(派彩)餘額 = -6 + 原有結餘 , 摘要：RG派彩 (DB操作)
 *
 * @param mixed $accountid 會員 ID
 * @param mixed $account 會員帳號
 * @param mixed $casino_account 娛樂城帳號
 * @param mixed $gtoken_cashier_account 此為系統代幣出納帳號 global var.
 * @param mixed $rg_balance_api 取得的 RG API 餘額 , 保留小數第二位 round( $x, 2)
 * @param mixed $rg2gpk_balance 派彩 = 娛樂城餘額 - 本地端RG支出餘額
 * @param mixed $rg_balance_db 在剛取出的 wallets 資料庫中的餘額(支出)
 * @param int $debug 是否為除錯模式，0為非除錯模式
 *
 * @return mixed 執行結果
 */
function db_rg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account,
                                            $rg_balance_api, $rg2gpk_balance, $rg_balance_db, $debug = 0)
{
	//$debug=1;
	global $gtoken_cashier_account;
	global $transaction_category;
	global $auditmode_select;

	$d['source_transferaccount'] = $gtoken_cashier_account;
	$d['destination_transferaccount'] = $account;

	$source_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['source_transferaccount'] . "';";
	//var_dump($source_id_sql);
	$source_id_result = runSQLall($source_id_sql);
	$destination_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['destination_transferaccount'] . "';";
	//var_dump($destination_id_sql);
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
	// ---------------------------------------------------------------------------------

	if ($debug == 1) {
		var_dump($rg2gpk_balance);
	}

	// 派彩有三種狀態，要有不同的對應 SQL 處理
	// --------------------------------
	// $rg2gpk_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 RG 餘額取回平台。
	// $rg2gpk_balance < 0; 從娛樂城輸錢
	// --------------------------------
	if ($rg2gpk_balance >= 0) {
		// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id =
'" . $d['destination_transfer_id'] . "';";
		//var_dump($wallets_sql);
		$wallets_result = runSQLall($wallets_sql);
		//var_dump($wallets_result);
		// 在剛取出的 wallets 資料庫中 rg 的餘額(支出)
		$gtoken_rg_balance_db = round($wallets_result[1]->rg_balance, 2);
		// 在剛取出的 wallets 資料庫中 gtoken 的餘額(支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_rg_balance_db + $rg2gpk_balance), 2);

		// 交易開始
		$rg2gpk_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $rg_balance_db;
		// 操作者
		$d['member_id'] = $accountid;
		// RG + 代幣派彩
		$d['summary'] = 'RG' . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select['rg'];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// RG 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 RG + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = $rg2gpk_balance;
		// var_dump($d);

		// 操作 root_member_wallets DB, 把 rg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額 , 把 rg_balance 扣除全部表示支出 (投注).
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"RG\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 RG 派彩' . $d['balance'] . ' by 关闭娱乐城)';
		// 針對目的會員的存簿寫入，$rg2gpk_balance >= 1 表示贏錢，所以從出納匯款到使用者帳號。
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance - " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 幫 RG 派彩到會員 ' .
			$d['destination_transferaccount'] . ')';
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '0', '" . $d['balance'] . "', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . 'COMMIT;';

		if ($debug == 1) {
			echo '<p>SQL=' . $rg2gpk_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$rg2gpk_transaction_result = runSQLtransactions($rg2gpk_transaction_sql);
		if ($rg2gpk_transaction_result) {
			$logger = '从 RG 帐号' . $casino_account . '取回余额到游戏币，统计后收入=' . $rg_balance_api . '，支出=' . $rg_balance_db . '，共计派彩=' . $rg2gpk_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $accountid, 'info');
		} else {
			// 1 ~ 3 必須一定要全部成功，才算成功。如果 1 成功後，但 2,3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
			$logger = '操作者:' . $d['member_id'] . ' RG 储值成功，但资料库处理错误，请通知客服人员处理。';
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $accountid, 'warning');
		}

		if ($debug == 1) {
			var_dump($r);
		}

	} elseif ($rg2gpk_balance < 0) {
		// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id =
'" . $d['destination_transfer_id'] . "';";
		//var_dump($wallets_sql);
		$wallets_result = runSQLall($wallets_sql);
		//var_dump($wallets_result);
		// 在剛取出的 wallets 資料庫中 rg 的餘額(支出)
		$gtoken_rg_balance_db = round($wallets_result[1]->rg_balance, 2);
		// 在剛取出的 wallets 資料庫中gtoken的餘額(支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_rg_balance_db + $rg2gpk_balance), 2);

		// 交易開始
		$rg2gpk_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $rg_balance_db;
		// 操作者
		$d['member_id'] = $accountid;
		// RG + 代幣派彩
		$d['summary'] = 'RG' . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select['rg'];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// RG 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 RG + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = abs($rg2gpk_balance);
		// var_dump($d);

		// 操作 root_member_wallets DB, 把 rg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額 , 把 rg_balance 扣除全部表示支出 (投注).
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"RG\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 RG 派彩' . $rg2gpk_balance . ' by 关闭娱乐城)';
		// 針對目的會員的存簿寫入，
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance + " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 從會員 ' . $d['destination_transferaccount'] . ' 取回派彩餘額)';
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['balance'] . "', '0', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . 'COMMIT;';
		if ($debug == 1) {
			echo '<p>SQL=' . $rg2gpk_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$rg2gpk_transaction_result = runSQLtransactions($rg2gpk_transaction_sql);
		if ($rg2gpk_transaction_result) {
			$logger = '从 RG 帐号' . $casino_account . '取回余额到游戏币，统计后收入=' . $rg_balance_api . '，支出=' . $rg_balance_db . '，共计派彩=' . $rg2gpk_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $accountid, 'info');
		} else {
			// 1 ~ 3 必須一定要全部成功，才算成功。如果 1 成功後，但 2,3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
			$logger = '从 RG 帐号' . $casino_account . '取回余额到游戏币，统计后收入=' . $rg_balance_api . '，支出=' . $rg_balance_db . '，共计派彩=' . $rg2gpk_balance;
			$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $accountid, 'warning');
		}
		// var_dump($r);
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
 * 娛樂城停用時取回會員在娛樂城代幣
 *
 * @param mixed $i 當前處理的組數，由FOR迴圈中的 $i 取得，配合 $check_count 用來計算處理進度用
 * @param mixed $accountid 會員 ID
 * @param mixed $account 會員帳號
 * @param mixed $casino_account 會員娛樂城遊戲帳號
 * @param mixed $gtoken_balance 會員在平台代幣存簿餘額
 * @param mixed $casino_dbbalance 會員在平台代幣存簿的娛樂城遊戲代幣餘額
 * @param mixed $check_count 此次處理的總組數，配合 $i 一起用來計算處理進度用
 * @param int $debug 是否為除錯模式，0 為非除錯模式
 *
 * @return string 執行結果，輸出為 html 格式
 */
function retrieve_rg_restful_casino_balance_4casino_switchoff($i, $accountid, $account, $casino_account,
                                                             $gtoken_balance, $casino_dbbalance, $check_count, $debug = 0)
{
	global $gtoken_cashier_account;
	// $debug=1;
	// 檢查會員在娛樂城的代幣餘額
	$RG_API_data_accountarr = $casino_account;
	$RG_API_data = array(
		'memberIds' => $RG_API_data_accountarr
	);
	if ($debug == 1) {
		var_dump($RG_API_data);
	}
	$RG_API_result = rg_api('GetMemberCurrentInfo', $debug, $RG_API_data);
	if ($debug == 1) {
		var_dump($RG_API_result);
	}

	if ($RG_API_result['error'] == 0) { // 0 means no error
		$casino_balance = $RG_API_result['Result']->data[0]->coin;
		$process_schedule = round(($i / $check_count) * 100, 2);

		//取回會員代幣
		if ($casino_balance > 0) {
			// 正式取回代幣前先將娛樂城的帳號 UNLOCK 以利接下來取回代幣
			$RG_API_data = array(
				'memberIds' => $RG_API_data_accountarr
			);
			if ($debug == 1) {
				var_dump($RG_API_data);
			}

			$RG_API_Lock_result = rg_api('KickMember', $debug, $RG_API_data);
			if ($debug == 1) {
				var_dump($RG_API_Lock_result);
			}
			// 1 執行 RG API 取回 RG 餘額, 成功才執行 2, 3
			// 動作：至遊戲帳戶取款
			$transactionId = 'RG' . '0deposit_all0' . date("Ymdhis"); // casino_id + trans_type + datetime
			$RG_API_data = array(
				'memberId' => $casino_account,
				'transactionId' => $transactionId,
				'amount' => "$casino_balance",
				'transferType' => 2 // 2 means Deposit all. Transfer from Game site to GPK and return current balance
			);
			if ($debug == 1) {
				echo '1 執行 RG API 取回 RG 餘額, 成功才執行 2, 3';
				var_dump($RG_API_data);
			}
			$RG_API_result = rg_api('Transfer', 0, $RG_API_data);
			if ($debug == 1) {
				var_dump($RG_API_result);
			}

			if ($RG_API_Lock_result['error'] == 0 and $RG_API_result['error'] == 0) {
				// 取回 RG 餘額成功
				$logger = 'RG API 从帐号' . $casino_account . '取款余额' . $casino_balance . '成功。交易编号为' . $transactionId;
				$r['code'] = 100;
				$r['messages'] = $logger;
				member_casino_transferrecords('RG', 'lobby', $casino_balance, $logger, $accountid, 'success',
					$transactionId, 1);
				if ($debug == 1) {
					echo "<p> $logger </p>";
					var_dump($RG_API_result);
				}

				// 先取得當下的 wallets 變數資料,等等 sql 更新後. 就會消失了
				// wallets 資料庫中的餘額 (支出)
				$rg_balance_db = round($casino_dbbalance, 2);
				// 派彩 = 娛樂城餘額 - 本地端 RG 支出餘額
				$rg2gpk_balance = round(($casino_balance - $rg_balance_db), 2);

				// 處理 DB 的轉帳問題 -- 2 and 3
				$db_rg2gpk_balance_result = db_rg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account,
					$gtoken_cashier_account, $casino_balance, $rg2gpk_balance, $rg_balance_db);
				if ($db_rg2gpk_balance_result['ErrorCode'] == 1) {
					$r['code'] = 1;
					$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
				} else {
					$r['code'] = 523;
					$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
				}
				if ($debug == 1) {
					echo '處理 DB 的轉帳問題 -- 2 and 3';
					var_dump($db_rg2gpk_balance_result);
				}
			} else {
				// 1 執行 RG API 取回 RG 餘額, 成功才執行 2, 3
				$logger = 'RG 娱乐城停用，RG API 从帐号' . $casino_account . '取款余额' . $casino_balance . '失败';
				$r['code'] = 405;
				$r['messages'] = $logger;
				if ($debug == 1) {
					echo "1 執行 RG API 取回 RG 餘額, 成功才執行 2, 3";
					echo "<p> $logger </p>";
					var_dump($r);
				}
			}
		} elseif ($casino_balance == 0) {
			$logger = 'RG 娱乐城停用，RG 余额 = 0 ，RG 没有余额，无法取回任何的余额，将余额转回 GPK。';
			$r['code'] = 406;
			$r['messages'] = $logger;
			member_casino_transferrecords('RG', 'lobby', '0', $logger, $accountid, 'success');

			// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了
			// wallets 資料庫中的餘額 (支出)
			$rg_balance_db = round($casino_dbbalance, 2);
			// 派彩 = 娛樂城餘額 - 本地端 RG 支出餘額
			$rg2gpk_balance = round(($casino_balance - $rg_balance_db), 2);

			// 處理 DB 的轉帳問題 -- 2 and 3
			$db_rg2gpk_balance_result = db_rg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account,
				$gtoken_cashier_account, $casino_balance, $rg2gpk_balance, $rg_balance_db);
			if ($db_rg2gpk_balance_result['ErrorCode'] == 1) {
				$r['code'] = 1;
				$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
			} else {
				$r['code'] = 523;
				$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
			}
			if ($debug == 1) {
				echo '處理 DB 的轉帳問題 -- 2 and 3';
				var_dump($db_rg2gpk_balance_result);
			}
		} else {
			// RG 餘額 < 0 , 不可能發生
			$logger = 'RG 余额 < 0 ，不可能发生。';
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
        <td>' . $casino_dbbalance . '</td>
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
 * @param     mixed $casino_switch_member_list_result 娛樂城會員清單
 * @param     int   $debug                            是否為除錯模式，0 為非除錯模式
 */
function casino_switch_process_rg($casino_switch_member_list_result, $debug = 0)
{
	global $casino_switch_json;
	$casino_account_dbcolumn_name = 'rg_account';
	$casino_balance_dbcolumn_name = 'rg_balance';

	for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i++) {
		$RG_API_data_accountarr = $casino_switch_member_list_result[$i]->$casino_account_dbcolumn_name;
		// 取回代幣前先將娛樂城的帳號LOCK住, 讓會員在取回過程無法下注，以免取回時掉錢
		$RG_API_data = array(
			'memberIds' => $RG_API_data_accountarr
		);
		if ($debug == 1) {
			var_dump($RG_API_data);
		}

		$RG_API_Lock_result = rg_api('KickMember', $debug, $RG_API_data);
		if ($debug == 1) {
			var_dump($RG_API_Lock_result);
		}
	}
	// 等待15秒，讓正在下注的會員結束此次的下注，並取得派彩結果
	sleep(15);

	// 確定帳號lock了後再進行回收代幣的動作
	for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i++) {
		$now = $i;
		$step_count = $casino_switch_member_list_result['0'] * 2;
		// 對現行在娛樂城的會員進行代幣回收
		$casino_switch_member_html = retrieve_rg_restful_casino_balance_4casino_switchoff($now,
			$casino_switch_member_list_result[$i]->id, $casino_switch_member_list_result[$i]->account,
			$casino_switch_member_list_result[$i]->$casino_account_dbcolumn_name,
			$casino_switch_member_list_result[$i]->gtoken_balance,
			$casino_switch_member_list_result[$i]->$casino_balance_dbcolumn_name, $step_count, 1);
		fwrite($casino_switch_json, $casino_switch_member_html);
	}
}


/**
 * 會員平台代幣轉出至娛樂城
 * 將傳入的 member id 值，取得他的 DB GTOKEN 餘額並全部傳送到 RG CASINO 上
 * 把本地端的資料庫 root_member_wallets 的 GTOKEN_LOCK 設定為 RG 餘額儲存在 rg_balance 上面
 *
 * @param mixed $memberid 會員 ID
 * @param int $debug 是否為除錯模式，0 為非除錯模式
 *
 * @return int 轉出結果，1 為成功
 */
function transferout_gtoken_rg_casino_balance($memberid, $debug = 0)
{
	global $config;
	// 驗證並取得帳戶資料
	$member_sql = "SELECT root_member.id,gtoken_balance,account,gtoken_lock,
                casino_accounts->'RG'->>'account' as rg_account,
                casino_accounts->'RG'->>'password' as rg_password,
                casino_accounts->'RG'->>'balance' as rg_balance FROM root_member JOIN root_member_wallets ON
                root_member.id=root_member_wallets.id WHERE root_member.id = '" . $memberid . "';";
	$r = runSQLall($member_sql);
	if ($debug == 1) {
		var_dump($r);
	}

	if ($r[0] == 1 and $config['casino_transfer_mode'] == 1) {
		// 沒有 RG 帳號的話，根本不可以進來。
		if ($r[1]->rg_account == null or $r[1]->rg_account == '') {
			$check_return['messages'] = '你還沒有 RG 帳號。';
			$check_return['code'] = 12;
		} else {
			$memberid = $r[1]->id;
			$memberaccount = $r[1]->account;
			$amount = round($r[1]->gtoken_balance, 2);
			$rg_balance = round($r[1]->rg_balance, 2);
			$member_rg_account = $r[1]->rg_account;

			// 需要 gtoken_lock 沒有被設定的時候，才可以使用這功能。
			if ($r[1]->gtoken_lock == null or $r[1]->gtoken_lock == 'RG') {
				// 動作：將本地端所有的 gtoken 餘額轉出到 rg 對應的帳戶
				$accountNumber = $member_rg_account;
				$transactionId = 'RG' . '0withdraw0' . date("Ymdhis"); // casino id + transfer type + datetime
				$RG_API_data = array(
					'memberId' => $accountNumber,
					'transactionId' => $transactionId,
					'amount' => $amount,
					'transferType' => 0 // means Withdraw. Transfer from GPK to Game site
				);

				$RG_API_result = rg_api('Transfer', $debug, $RG_API_data);
				if ($RG_API_result['error'] == 0 and $RG_API_result['Result']->data[0]->status == 0) {
					if ($debug == 1) {
						var_dump($RG_API_data);
						var_dump($RG_API_result);
					}
					// 本地端 db 的餘額處理
					$rg_balance = $rg_balance + $amount;
					$togtoken_sql = "UPDATE root_member_wallets SET gtoken_lock = 'RG'  WHERE id = '$memberid';";
					$togtoken_sql = $togtoken_sql . "UPDATE root_member_wallets SET gtoken_balance = gtoken_balance - '$amount',casino_accounts= jsonb_set(casino_accounts,'{\"RG\",\"balance\"}','$rg_balance') WHERE id = '$memberid';";
					$togtoken_sql_result = runSQLtransactions($togtoken_sql);
					if ($debug == 1) {
						var_dump($togtoken_sql);
						var_dump($togtoken_sql_result);
					}
					if ($togtoken_sql_result) {
						$check_return['messages'] = '所有 GTOKEN 余额已经转到 RG 娱乐城。 RG 转帐单号 ' .
							$transactionId . ' RG 帐号' . $accountNumber . 'RG 新增' . $amount;
						$check_return['code'] = 1;
						memberlog2db($memberaccount, 'gpk2rg', 'info', $check_return['messages']);
						member_casino_transferrecords('lobby', 'RG', $amount, $check_return['messages'], $memberid, 'success', $transactionId, 1);
					} else {
						$check_return['messages'] = '余额处理，本地端资料库交易错误。';
						$check_return['code'] = 14;
						memberlog2db($memberaccount, 'gpk2rg', 'error', $check_return['messages']);
						member_casino_transferrecords('lobby', 'RG', $amount, $check_return['messages'], $memberid, 'warning', $transactionId, 2);
					}
				} else {
					$check_return['messages'] = '余额转移到 RG 时失败！！';
					$check_return['code'] = 13;
					memberlog2db($memberaccount, 'gpk2rg', 'error', $check_return['messages']);
					member_casino_transferrecords('lobby', 'RG', $amount, $check_return['messages'] . '(' .
						$RG_API_result['Result'] . ')', $memberid, 'fail');
				}
			} else {
				$check_return['messages'] = '此帐号已经在 RG 娱乐城活动，请勿重复登入。';
				$check_return['code'] = 11;
				member_casino_transferrecords('lobby', 'RG', '0', $check_return['messages'], $memberid, 'warning');
			}
		}
	} elseif ($r[0] == 1 and $config['casino_transfer_mode'] == 0) {
		$check_return['messages'] = '测试环境不进行转帐交易';
		$check_return['code'] = 1;
		member_casino_transferrecords('lobby', 'RG', '0', $check_return['messages'], $memberid, 'info');
	} else {
		$check_return['messages'] = '无此帐号 ID = ' . $memberid;
		$check_return['code'] = 0;
		member_casino_transferrecords('lobby', 'RG', '0', $check_return['messages'], $memberid, 'fail');
	}
	// var_dump($check_return);
	return ($check_return);
}


/**
 * 取回 RG 娛樂城遊戲代幣的餘額
 * 針對 db 的處理函式，只針對此功能有用，不能單獨使用，需要搭配 retrieve_rg_casino_balance
 * 2. 把 RG API 傳回的餘額，透過 GTOKEN出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶。 (DB操作)
 * 3. 紀錄當下 DB rg_balance 的餘額紀錄
 *     存摺 GTOKEN 紀錄: (RG API)收入=4 , (DB RG餘額)支出=10 , (派彩)餘額 = - 6 + 原有結餘 , 摘要：RG 派彩 (DB操作)
 *
 * @param mixed $memberaccount 會員帳號
 * @param mixed $memberid 會員 ID
 * @param mixed $member_rg_account 會員娛樂城帳號
 * @param mixed $gtoken_cashier_account 系統代幣出納帳號
 * @param mixed $rg_balance_api 取得的 RG API 餘額 , 保留小數第二位 round( $x, 2)
 * @param mixed $rg2gpk_balance 派彩 = 娛樂城餘額 - 本地端 RG 支出餘額
 * @param mixed $rg_balance_db 在剛取出的 wallets 資料庫中的餘額 (支出)
 * @param int $debug 是否為除錯模式，0為非除錯模式
 *
 * @return int 是否執行成功，1 為成功
 */
function db_rg2gpk_balance($memberaccount, $memberid, $member_rg_account, $gtoken_cashier_account,
                          $rg_balance_api, $rg2gpk_balance, $rg_balance_db, $debug = 0)
{
	global $gtoken_cashier_account;
	global $transaction_category;
	global $auditmode_select;

	// 取得來源與目的帳號的 id ,  $gtoken_cashier_account(此為系統代幣出納帳號 global var.)
	$d['source_transferaccount'] = $gtoken_cashier_account;
	$d['destination_transferaccount'] = $memberaccount;
	$source_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['source_transferaccount'] . "';";
	// var_dump($source_id_sql);
	$source_id_result = runSQLall($source_id_sql);
	$destination_id_sql = "SELECT * FROM root_member WHERE account = '" . $d['destination_transferaccount'] . "';";
	// var_dump($destination_id_sql);
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
		var_dump($rg2gpk_balance);
	}

	// 派彩有三種狀態，要有不同的對應 SQL 處理
	// $rg2gpk_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 RG 餘額取回 gpk
	// $rg2gpk_balance < 0; 從娛樂城輸錢
	if ($rg2gpk_balance >= 0) {
		// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id =
'" . $d['destination_transfer_id'] . "';";
		// var_dump($wallets_sql);
		$wallets_result = runSQLall($wallets_sql);
		// var_dump($wallets_result);
		// 在剛取出的 wallets 資料庫中 rg 的餘額 (支出)
		$gtoken_rg_balance_db = round($wallets_result[1]->rg_balance, 2);
		// 在剛取出的 wallets 資料庫中 gtoken 的餘額 (支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端平台支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_rg_balance_db + $rg2gpk_balance), 2);

		// 交易開始
		$rg2gpk_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $rg_balance_db;
		// 操作者
		$d['member_id'] = $memberid;
		// RG + 代幣派彩
		$d['summary'] = 'RG' . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select['rg'];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// RG 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 RG + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = $rg2gpk_balance;
		// var_dump($d);

		// 操作 root_member_wallets DB, 把 rg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額 , 把 rg_balance 扣除全部表示支出 (投注).
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"RG\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 RG 派彩' . $d['balance'] . ' by 客服人員 ' . $_SESSION['agent']->account . ')';
		// 針對目的會員的存簿寫入，$rg2gpk_balance >= 1 表示贏錢，所以從出納匯款到使用者帳號。
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance - " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 幫 RG 派彩到會員 ' .
			$d['destination_transferaccount'] . ')';
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '0', '" . $d['balance'] . "', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . 'COMMIT;';

		if ($debug == 1) {
			echo '<p>SQL=' . $rg2gpk_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$rg2gpk_transaction_result = runSQLtransactions($rg2gpk_transaction_sql);
		if ($rg2gpk_transaction_result) {
			$logger = '从 RG 帐号' . $member_rg_account . '取回余额到游戏币，统计后收入=' . $rg_balance_api . '，支出=' . $rg_balance_db
				. '，共计派彩=' . $rg2gpk_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'rglottery', 'info', "$logger");
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $memberid, 'info');
		} else {
			// 1 ~ 3 必須一定要全部成功，才算成功。如果 1 成功後，但 2, 3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
			$logger = '从 RG 帐号' . $member_rg_account . '取回余额到游戏币，统计后收入=' . $rg_balance_api . '，支出=' . $rg_balance_db .
				'，共计派彩=' . $rg2gpk_balance;
			$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
			memberlog2db($d['member_id'], 'rg_transaction', 'error', "$logger");
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'rglottery', 'error', "$logger");
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $memberid, 'warning');
		}

		if ($debug == 1) {
			var_dump($r);
		}
	} elseif ($rg2gpk_balance < 0) {
		// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
		$wallets_sql = "SELECT gtoken_balance,casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id =
'" . $d['destination_transfer_id'] . "';";
		// var_dump($wallets_sql);
		$wallets_result = runSQLall($wallets_sql);
		// var_dump($wallets_result);
		// 在剛取出的 wallets 資料庫中 rg 的餘額(支出)
		$gtoken_rg_balance_db = round($wallets_result[1]->rg_balance, 2);
		// 在剛取出的 wallets 資料庫中gtoken的餘額(支出)
		$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
		// 派彩 = 娛樂城餘額 - 本地端平台支出餘額
		$gtoken_balance = round(($gtoken_balance_db + $gtoken_rg_balance_db + $rg2gpk_balance), 2);

		// 交易開始
		$rg2gpk_transaction_sql = 'BEGIN;';
		// 存款金額 -- 娛樂城餘額
		$d['deposit'] = $gtoken_balance;
		// 提款金額 -- 本地端支出
		$d['withdrawal'] = $rg_balance_db;
		// 操作者
		$d['member_id'] = $memberid;
		// RG + 代幣派彩
		$d['summary'] = 'RG' . $transaction_category['tokenpay'];
		// 稽核方式
		$d['auditmode'] = $auditmode_select['rg'];
		// 稽核金額 -- 派彩無須稽核
		$d['auditmodeamount'] = 0;
		// RG 取回的餘額為真錢
		$d['realcash'] = 2;
		// 交易類別 RG + $transaction_category['tokenpay']
		$d['transaction_category'] = 'tokenpay';
		// 變化的餘額
		$d['balance'] = abs($rg2gpk_balance);
		// var_dump($d);

		// 操作 root_member_wallets DB, 把 rg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
		// 錢包存入 餘額 , 把 rg_balance 扣除全部表示支出 (投注).
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = " . $d['deposit'] . ", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"RG\",\"balance\"}','0') WHERE id = '" . $d['destination_transfer_id'] . "'; ";
		// 目的帳號上的註記
		$d['destination_notes'] = '(會員收到 RG 派彩' . $rg2gpk_balance . ' by 客服人員 ' . $_SESSION['agent']->account . ')';
		// 針對目的會員的存簿寫入，
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['deposit'] . "', '" . $d['withdrawal'] . "', '" . $d['destination_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['destination_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['destination_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['destination_transfer_id'] . ") );";

		// 針對來源出納的存簿寫入
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . "
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance + " . $d['balance'] . ") WHERE id = '" . $d['source_transfer_id'] . "'; ";
		// 來源帳號上的註記
		$d['source_notes'] = '(出納帳號 ' . $d['source_transferaccount'] . ' 從會員 ' . $d['destination_transferaccount'] . ' 取回派彩餘額)';
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql .
			'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")' .
			"VALUES ('now()', '" . $d['balance'] . "', '0', '" . $d['source_notes'] . "', '" . $d['member_id'] . "', '".$config['currency_sign']."', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "', '" . $d['auditmode'] . "', '" . $d['auditmodeamount'] . "', '" . $d['realcash'] . "', " .
			"'" . $d['source_transferaccount'] . "','" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $d['source_transfer_id'] . "));";

		// commit 提交
		$rg2gpk_transaction_sql = $rg2gpk_transaction_sql . 'COMMIT;';
		if ($debug == 1) {
			echo '<p>SQL=' . $rg2gpk_transaction_sql . '</p>';
		}

		// 執行 transaction sql
		$rg2gpk_transaction_result = runSQLtransactions($rg2gpk_transaction_sql);
		if ($rg2gpk_transaction_result) {
			$logger = '从 RG 帐号' . $member_rg_account . '取回余额到游戏币，统计后收入=' . $rg_balance_api . '，支出=' . $rg_balance_db .
				'，共计派彩=' . $rg2gpk_balance;
			$r['ErrorCode'] = 1;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'rglottery', 'info', "$logger");
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $memberid, 'info');
			// echo "<p> $logger </p>";
		} else {
			// 1 ~ 3 必須一定要全部成功，才算成功。如果 1 成功後，但 2, 3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
			$logger = '从 RG 帐号' . $member_rg_account . '取回余额到游戏币，统计后收入=' . $rg_balance_api . '，支出=' . $rg_balance_db .
				'，共计派彩=' . $rg2gpk_balance;
			$logger = $logger . '但资料库处理错误，请通知客服人员处理。';
			$r['ErrorCode'] = 406;
			$r['ErrorMessage'] = $logger;
			memberlog2db($memberaccount, 'rglottery', 'error', "$logger");
			member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $memberid, 'warning');
			// echo "<p> $logger </p>";
		}
		// var_dump($r);
	} else {
		// 不可能
		$logger = '不可能发生';
		$r['ErrorCode'] = 500;
		$r['ErrorMessage'] = $logger;
		memberlog2db($memberaccount, 'rglottery', 'error', "$logger");
		echo "<p> $logger </p>";
	}

	return ($r);
}


/**
 * 取回 RG Lottery 的遊戲幣餘額
 * 不能單獨使用，需要搭配 db_rg2gpk_balance 使用
 *
 * @param mixed $memberid
 * @param int $debug
 *
 * @return mixed 執行結果
 */
function retrieve_rg_casino_balance($memberid, $debug = 0)
{
	//$debug=1;
	global $gtoken_cashier_account;

	// 判斷會員是否 status 是否被鎖定
	$member_sql = "SELECT * FROM root_member WHERE id = '" . $memberid . "' AND status = '1';";
	$member_result = runSQLall($member_sql);

	// 先取得當下的  member_wallets 變數資料,等等 sql 更新後. 就會消失了。
	$wallets_sql = "SELECT gtoken_balance,gtoken_lock,
              casino_accounts->'RG'->>'account' as rg_account,
              casino_accounts->'RG'->>'password' as rg_password,
              casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id = '" . $memberid . "';";
	$wallets_result = runSQLall($wallets_sql);
	if ($debug == 1) {
		var_dump($wallets_sql);
		var_dump($wallets_result);
	}

	// 查詢 DB 的 gtoken_lock  是否有紀錄在 RG 帳戶，NULL 沒有紀錄的話表示沒有餘額在 RG 帳戶
	// (已經取回了，代幣一次只能對應一個娛樂城)
	// AND 當 DB 有 rg_balance 的時候才動作, 如果沒有則結束。表示 db 帳號資料有問題
	if ($member_result[0] == 1 and $wallets_result[0] == 1 and $wallets_result[1]->rg_account != null and
		$wallets_result[1]->gtoken_lock == 'RG') {
		$memberaccount = $member_result[1]->account;
		$memberid = $member_result[1]->id;
		$member_rg_account = $wallets_result[1]->rg_account;

		// gtoken 紀錄為 RG , API 檢查 RG 的餘額有多少
		$delimitedAccountNumbers = $wallets_result[1]->rg_account;
		$RG_API_data = array(
			'memberIds' => $delimitedAccountNumbers
		);
		if ($debug == 1) {
			var_dump($RG_API_data);
		}

		$RG_API_result = rg_api('GetMemberCurrentInfo', $debug, $RG_API_data);
		$RG_API_kickuser_result = rg_api('KickMember', $debug, $RG_API_data);
		if ($RG_API_result['error'] == 0 and $RG_API_kickuser_result['error'] == 0) {
			// 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。
			// echo '4. 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。';
			// var_dump($RG_API_result);
			// 取得的 RG API 餘額 , 保留小數第二位 round( $x, 2);
			$rg_balance_api = round($RG_API_result['Result']->data[0]->coin, 2);
			$logger = 'RG API 查询余额为' . $RG_API_result['Result']->data[0]->coin . '操作的余额为' . $rg_balance_api;
			$r['code'] = 1;
			$r['messages'] = $logger;
			// echo "<p> $logger </p>";

			// 如果 RG 餘額 > 0
			if ($rg_balance_api > 0) {
				// 1.執行 RG API 取回 RG 餘額 ，到 RG 的出納帳戶 (API操作) , 成功才執行 2, 3
				// 動作：由遊戲帳戶取款
				$transactionId = 'RG' . '0deposit_all0' . date("Ymdhis"); // casino id + transfer type + datetime
				$RG_API_data = array(
					'memberId' => $wallets_result[1]->rg_account,
					'transactionId' => $transactionId,
					'amount' => "$rg_balance_api",
					'transferType' => 2 // means Deposit all. Transfer from Game site to GPK and return current balance
				);
				if ($debug == 1) {
					echo '1.執行 RG API 取回 RG 餘額 ，到 RG 的出納帳戶(API操作) , 成功才執行 2, 3';
					var_dump($RG_API_data);
				}

				$RG_API_result = rg_api('Transfer', $debug, $RG_API_data);
				if ($debug == 1) {
					var_dump($RG_API_result);
				}

				if ($RG_API_result['error'] == 0) {
					// 取回 RG 餘額成功
					$logger = 'RG API 从帐号' . $wallets_result[1]->rg_account . '取款余额' . $rg_balance_api . '成功。交易编号为'
						. $transactionId;
					$r['code'] = 100;
					$r['messages'] = $logger;
					memberlog2db($memberaccount, 'rglottery', 'info', "$logger");
					member_casino_transferrecords('RG', 'lobby', $rg_balance_api, $logger, $memberid, 'success',
						$transactionId, 1);
					if ($debug == 1) {
						echo "<p> $logger </p>";
						var_dump($RG_API_result);
					}

					// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
					$wallets_sql = "SELECT casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id = '" . $memberid . "';";
					// var_dump($wallets_sql);
					$wallets_result = runSQLall($wallets_sql);
					// var_dump($wallets_result);
					// 在剛取出的 wallets 資料庫中的餘額(支出)
					$rg_balance_db = round($wallets_result[1]->rg_balance, 2);
					// 派彩 = 娛樂城餘額 - 本地端 RG 支出餘額
					$rg2gpk_balance = round(($rg_balance_api - $rg_balance_db), 2);
					// -----------------------------------------------------------------------------------

					// 處理 DB 的轉帳問題 -- 2 and 3
					$db_rg2gpk_balance_result = db_rg2gpk_balance($memberaccount, $memberid, $member_rg_account,
						$gtoken_cashier_account, $rg_balance_api, $rg2gpk_balance, $rg_balance_db);
					if ($db_rg2gpk_balance_result['ErrorCode'] == 1) {
						$r['code'] = 1;
						$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
						$logger = $r['messages'];
						memberlog2db($memberaccount, 'rg2gpk', 'info', "$logger");
					} else {
						$r['code'] = 523;
						$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
						$logger = $r['messages'];
						memberlog2db($memberaccount, 'rg2gpk', 'error', "$logger");
					}
					if ($debug == 1) {
						echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
						var_dump($db_rg2gpk_balance_result);
					}
				} else {
					// 1.執行 RG API 取回 RG 餘額 ，到 RG 的出納帳戶 (API操作) , 成功才執行 2, 3
					$logger = 'RG API 从帐号' . $member_rg_account . '取款余额' . $rg_balance_api . '失败';
					$r['code'] = 405;
					$r['messages'] = $logger;
					memberlog2db($memberaccount, 'rglottery', 'error', "$logger");
					if ($debug == 1) {
						echo "1.執行 RG API 取回 RG 餘額 ，到 RG 的出納帳戶 (API操作) , 成功才執行 2, 3";
						echo "<p> $logger </p>";
						var_dump($r);
					}
				}
			} elseif ($rg_balance_api == 0) {
				$logger = 'RG 余额 = 0，RG 没有余额，无法取回任何的余额，将余额转回 GPK。';
				$r['code'] = 406;
				$r['messages'] = $logger;
				memberlog2db($memberaccount, 'rglottery', 'info', "$logger");
				member_casino_transferrecords('RG', 'lobby', '0', $logger, $memberid, 'success');

				// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
				$wallets_sql = "SELECT casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id = '" . $memberid . "';";
				// var_dump($wallets_sql);
				$wallets_result = runSQLall($wallets_sql);
				// var_dump($wallets_result);
				// 在剛取出的 wallets 資料庫中的餘額(支出)
				$rg_balance_db = round($wallets_result[1]->rg_balance, 2);
				// 派彩 = 娛樂城餘額 - 本地端 RG 支出餘額
				$rg2gpk_balance = round(($rg_balance_api - $rg_balance_db), 2);

				// 處理 DB 的轉帳問題 -- 2 and 3
				$db_rg2gpk_balance_result = db_rg2gpk_balance($memberaccount, $memberid, $member_rg_account,
					$gtoken_cashier_account, $rg_balance_api, $rg2gpk_balance, $rg_balance_db);
				if ($db_rg2gpk_balance_result['ErrorCode'] == 1) {
					$r['code'] = 1;
					$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
					$logger = $r['messages'];
					memberlog2db($memberaccount, 'rg2gpk', 'info', "$logger");
				} else {
					$r['code'] = 523;
					$r['messages'] = $db_rg2gpk_balance_result['ErrorMessage'];
					$logger = $r['messages'];
					memberlog2db($memberaccount, 'rg2gpk', 'error', "$logger");
				}

				if ($debug == 1) {
					echo '處理 DB 的轉帳問題 -- 2 and 3';
					var_dump($db_rg2gpk_balance_result);
				}
			} else {
				// RG 餘額 < 0 , 不可能發生
				$logger = 'RG 余额 < 1 ，不可能发生。';
				$r['code'] = 404;
				$r['messages'] = $logger;
			}
			// -----------------------------------------------------------------------------------
		} else {
			// gtoken 紀錄為 RG , API 檢查 RG 的餘額有多少
			$logger = 'RG API 查询余额失败，系统维护中请晚点再试。';
			$r['code'] = 403;
			$r['messages'] = $logger;
			member_casino_transferrecords('RG', 'lobby', '0', $logger . '(' . $RG_API_result['Result'] . ')',
				$memberid, 'fail');
			if ($debug == 1) {
				var_dump($RG_API_result);
			}
		}
	} else {
		// 查詢 session 的 gtoken_lock  是否有紀錄在 RG 帳戶，NULL 沒有紀錄的話表示沒有餘額在 RG 帳戶。
		// AND 當 session 有 rg_balance 的時候才動作, 如果沒有則結束。表示 db 帳號資料有問題。
		$logger = '没有余额在 RG 帐户 OR DB 帐号资料有问题 ';
		$r['code'] = 401;
		$r['messages'] = $logger;
		member_casino_transferrecords('RG', 'lobby', '0', $logger, $memberid, 'fail');
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
 * 取得會員目前在 RG Lottery 的餘額
 *
 * @param mixed $memberid 會員 ID
 * @param int $debug 是否為除錯模式，0 為非除錯模式
 *
 * @return float 會員 RG Lottery 遊戲幣餘額
 */
function getbalance_rg($memberid, $debug = 0)
{
	$rg_balance_api = '';

	// 判斷會員是否 status 是否被鎖定了!!
	$member_sql = "SELECT * FROM root_member WHERE id = '" . $memberid . "' AND status = '1';";
	$member_result = runSQLall($member_sql);

	// 先取得當下的  member_wallets 變數資料
	$wallets_sql = "SELECT gtoken_balance,gtoken_lock,
                casino_accounts->'RG'->>'account' as rg_account,
                casino_accounts->'RG'->>'password' as rg_password,
                casino_accounts->'RG'->>'balance' as rg_balance FROM root_member_wallets WHERE id = '" . $memberid .
		"';";
	$wallets_result = runSQLall($wallets_sql);
	if ($debug == 1) {
		var_dump($wallets_sql);
		var_dump($wallets_result);
	}

	if ($member_result[0] == 1 and $wallets_result[0] == 1 and $wallets_result[1]->rg_account != null) {
		// 查詢在 casino 的餘額
		$delimitedAccountNumbers = $wallets_result[1]->rg_account;
		$RG_API_data = array(
			'memberIds' => $delimitedAccountNumbers
		);
		if ($debug == 1) {
			var_dump($RG_API_data);
		}

		$RG_API_result = rg_api('GetMemberCurrentInfo', $debug, $RG_API_data);
		if ($RG_API_result['error'] == 0) {
			// 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。
			// echo '4. 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。';
			// var_dump($RG_API_result);
			// 取得的 RG API 餘額 , 保留小數第二位 round( $x, 2);
			$rg_balance_api = round($RG_API_result['Result']->data[0]->coin, 2);
		}
	}
	return $rg_balance_api;

}


/**
 * 確認 RG 會員是否在線
 *
 * @param string $account 帳號
 * @param int    $debug 是否為除錯模式，0 為非除錯模式
 *
 * @return mixed 會員在線回傳 True，否則回傳 False，若發生 CURL 或 API 錯誤則丟出 RuntimeException
 */
function check_account_online_rg(string $account, $debug = 0)
{
	$RG_API_data = array(
		'memberIds' => $account
	);
	if ($debug == 1) {
		var_dump($RG_API_data);
	}

	$RG_API_result = rg_api('GetMemberCurrentInfo', $debug, $RG_API_data);
	if (($RG_API_result['error'] == 0)) {
		if ($RG_API_result['Result']->response->error == 0) {
			$isOnline = $RG_API_result['Result']->data[0]->isOnline;
		} else {
			$error_message = 'API error code: ' . $RG_API_result['Result']->response->error . '. ' .$RG_API_result['Result']->response->message;
			memberlog2db($account, 'rgapi', 'error', $error_message);
			throw new RuntimeException($error_message);
		}
	} else {
		$error_message = 'Error code: ' . $RG_API_result['error'] . ' . Get RG member online status error!';
		memberlog2db($account, 'rgapi', 'error', $error_message);
		throw new RuntimeException($error_message);
	}
	return $isOnline;
}


/**
 * 生成API Key
 *
 * @param string $secret API演算key
 * @param array  $params 參數
 *
 * @return string API Key
 */
function genApiKey(string $secret, array $params = []): string
{
	$head = randomStrGenerator(5);
	$footer = randomStrGenerator(5);
	$middle = '';
	foreach ($params as $key => $value) {
		if ($key == 'memberBranch') {
			$middle = $middle . $key . '=' . json_encode($value) . '&';
		} else {
			$middle = $middle . $key . '=' . $value . '&';
		}
	}
	return $head . md5($middle . 'Key=' . $secret) . $footer;
}


/**
 * 隨意字串生成器
 *
 * @param int $count 需要字串長度
 *
 * @return string 生成字串
 */
function randomStrGenerator($count = 5)
{
	$seed = str_split('abcdefghijklmnopqrstuvwxyz'
		. 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
		. '0123456789_');
	shuffle($seed);
	$rand = '';
	foreach (array_rand($seed, $count) as $k) $rand .= $seed[$k];
	return $rand;
}
