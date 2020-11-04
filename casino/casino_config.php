<?php
// ----------------------------------------------------------------------------
// Features:	Casino lobby 的專用設定
// File Name:	casino_config.php
// Author:		Ian
// Related:
// Log:
// 20190419 新增 AP 娛樂城 Letter
// 20190426 新增 VG 娛樂城 Letter
// 20191220 #3003 娛樂城設定檔轉入資料庫 Letter
// ----------------------------------------------------------------------------
// 此設定檔用於記錄各casino所使用的action、lib的php檔名及取錢時的function名，
// 這些資料將在gamelobby_action裡使用，用來做自動取回各casino的餘額以利轉換到其他casino，
// EX：
// $getbalance['MG'] MG取得會員娛樂城餘額使用的function
// $casino_transferout['MG'] 將gtoken全額轉到娛樂城
// $casino_retrieve['MG'] 自娛樂城取錢
// $casino_switch_process['MG'] 停用娛樂城用function
// UPDATE:
// 使用 API 時使用方法
// getRequirePath() 取得娛樂城函式庫
// getCasinoSwitchOffProcess() 取得娛樂城關閉程序方法
// getCasinoTransferout() 取得娛樂城餘額轉出方法
// getCasinoRetrieve() 取得取回娛樂城餘額方法
// getCasinoBalance() 透過 API 取得娛樂城餘額
// ----------------------------------------------------------------------------
$require_path['MG'] = dirname(__FILE__) . '/MG/casino_switch_lib.php';
$getbalance['MG'] = 'getbalance_mg';
$casino_transferout['MG'] = 'transferout_gtoken_mg_restful_casino_balance';
$casino_retrieve['MG'] = 'retrieve_mg_restful_casino_balance';
$casino_switch_process['MG'] = 'casino_switch_process_mg';

// MEGA
$require_path['MEGA'] = dirname(__FILE__) . '/MEGA/casino_switch_lib.php';
$getbalance['MEGA'] = 'getbalance_mega';
$casino_transferout['MEGA'] = 'transferout_gtoken_mega_casino_balance';
$casino_retrieve['MEGA'] = 'retrieve_mega_casino_balance';
$casino_switch_process['MEGA'] = 'casino_switch_process_mega';

// IG
$require_path['IG'] = dirname(__FILE__) . '/IG/casino_switch_lib.php';
$getbalance['IG'] = 'getbalance_ig';
$casino_transferout['IG'] = 'transferout_gtoken_ig_casino_balance';
$casino_retrieve['IG'] = 'retrieve_ig_casino_balance';
$casino_switch_process['IG'] = 'casino_switch_process_ig';


// RG (用RG Lottery API)
$require_path['RG'] = dirname(__FILE__).'/RG/casino_switch_lib.php';
$getbalance['RG'] = 'getbalance_rg';
$casino_transferout['RG'] = 'transferout_gtoken_rg_casino_balance';
$casino_retrieve['RG'] = 'retrieve_rg_casino_balance';
$casino_switch_process['RG'] = 'casino_switch_process_rg';


/**
 *  取得娛樂城建立來源
 *
 * @param mixed $casinoId 娛樂城ID
 * @param int   $debug    除錯模式，預設 0 為關閉
 *
 * @return int 0 表示透過 API 連接，1 為單接娛樂城
 */
function getCasinoType($casinoId, $debug = 0)
{
	$artificial_casino = -1;
	$sql = 'SELECT "artificial_casino" FROM casino_list WHERE "casinoid" = \'' . strtoupper($casinoId) . '\';';
	$result = runSQLall($sql, $debug);
	if ($result[0] > 0) {
		$artificial_casino = $result[1]->artificial_casino;
	}
	return $artificial_casino;
}


/**
 *  取得娛樂城函式庫
 *
 * @param string $casinoName 娛樂城名稱
 * @param int    $debug      除錯模式，預設 0 為關閉
 *
 * @return string 函式庫路徑
 */
function getRequirePath(string $casinoName, $debug = 0)
{
	$specialCasino = getCasinoType($casinoName, $debug);
	if ($specialCasino == 1) {
		$lib = dirname(__FILE__) . '/' . strtoupper($casinoName) . '/casino_switch_lib.php';
	} else {
		$lib = dirname(__FILE__) . '/casino_switch_lib.php';
	}
	return $lib;
}


/**
 *  取得娛樂城關閉程序方法
 *
 * @param mixed $casinoName 娛樂城名稱
 * @param int   $debug      除錯模式，預設 0 為關閉
 *
 * @return string 娛樂城關閉程序方法
 */
function getCasinoSwitchOffProcess($casinoName, $debug = 0)
{
	$specialCasino = getCasinoType($casinoName, $debug);
	if ($specialCasino == 1) {
		$process = 'casino_switch_process_' . strtolower($casinoName);
	} else {
		$process = 'casino_switch_process';
	}
	return $process;
}


/**
 *  取得娛樂城餘額轉出方法
 *
 * @param mixed $casinoName 娛樂城名稱
 * @param int   $debug      除錯模式，預設 0 為關閉
 *
 * @return string 娛樂城餘額轉出方法
 */
function getCasinoTransferout($casinoName, $debug = 0)
{
	$specialCasino = getCasinoType($casinoName, $debug);
	if ($specialCasino == 1) {
		$out = 'transferout_gtoken_' . strtolower($casinoName) . '_casino_balance';
	} else {
		$out = 'transferout_gtoken_to_casino_balance';
	}
	return $out;
}


/**
 *  透過 API 取得娛樂城餘額
 *
 * @param mixed $casinoName 娛樂城名稱
 * @param int   $debug      除錯模式，預設 0 為關閉
 *
 * @return string 透過 API 取回娛樂城餘額方法
 */
function getCasinoBalance($casinoName, $debug = 0)
{
	$specialCasino = getCasinoType($casinoName, $debug);
	if ($specialCasino == 1) {
		$balance = 'getbalance_' . strtolower($casinoName);
	} else {
		$balance = 'getCasinoBalanceByAPI';
	}
	return $balance;
}


/**
 *  取得確認會員是否在線
 *
 * @param mixed $casinoName 娛樂城名稱
 * @param int   $debug      除錯模式，預設 0 為關閉
 *
 * @return string 透過 API 確認會員是否在線方法
 */
function getCheckOnLineFunc($casinoName, $debug = 0)
{
	$specialCasino = getCasinoType($casinoName, $debug);
	if ($specialCasino == 1) {
		$check = 'check_account_online_' . strtolower($casinoName);
	} else {
		$check = 'check_casino_account_online';
	}
	return $check;
}


/**
 *  取得娛樂城取錢方法
 *
 * @param string $casinoName 娛樂城名稱
 * @param int    $debug 除錯模式，預設 0 為關閉
 *
 * @return string 取錢方法名稱
 */
function getCasinoRetrieve($casinoName, $debug = 0)
{
	$specialCasino = getCasinoType($casinoName, $debug);
	if ($specialCasino == 1) {
		$retrieve = 'retrieve_' . strtolower($casinoName) . '_casino_balance';
	} else {
		$retrieve = 'retrieve_casino_balance';
	}
	return $retrieve;
}


/**
 *  自動 GCASH TO GTOKEN，將會員本人的 GCASH 餘額，依據設定值轉換為 GTOKEN 餘額
 *
 * @param mixed $member_id 會員ID 同時也是操作者 ID 也是轉帳人員
 * @param mixed $gcash2gtoken_account 指定轉帳帳號
 * @param mixed $balance_input 轉帳金額
 * @param mixed $password_verify_sha1 會員的提款密碼
 * @param int $debug 除錯模式，預設 0 為關閉
 * @param mixed $system_note_input 備註新增
 *
 * @return mixed 轉帳結果
 */
function auto_gcash2gtoken($member_id, $gcash2gtoken_account, $balance_input, $password_verify_sha1, $debug = 0, $system_note_input = NULL)
{

	// 交易的變數 default
	global $transaction_category;
	// 系統現金出納
	global $gcash_cashier_account;
	// 系統代幣出納
	global $gtoken_cashier_account;
	global $config;

	// 確認帳號是否存在，存在才繼續
	$check_acc_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '$gcash2gtoken_account' AND root_member.status = '1';";
	$check_acc = runSQLall($check_acc_sql);
	if ($debug == 1) {
		var_dump($check_acc);
		var_dump($member_id);
		var_dump($balance_input);
		var_dump($password_verify_sha1);
	}

	if ($check_acc[0] == 1) {
		// 帳號正確
		$error['code'] = '1';
		$error['messages'] = '帐号正确';


		// 轉帳操作人員
		$d['member_id'] = $member_id;
		// 來源帳號
		$d['source_transferaccount'] = $check_acc[1]->account;
		// 目的轉帳帳號 = 來源帳號，同一個人的帳號
		$d['destination_transferaccount'] = $check_acc[1]->account;
		// 轉帳金額，需要依據會員等級限制每日可轉帳總額。如果不小心被輸入浮點數了，就取整數部位
		$d['transaction_money'] = round($balance_input, 2);
		// 真實轉換，其實在這個程式還找不到此欄位定義定位
		$d['realcash'] = 1;
		// 摘要資訊
		$d['summary'] = $transaction_category['cashgtoken'];
		// 交易類別 -- ref in config.php
		$d['transaction_category'] = 'cashgtoken';
		// 來源帳號的密碼驗證，驗證後才可以存款
		$d['password_verify_sha1'] = $password_verify_sha1;
		// 系統轉帳文字資訊
		$d['system_note_input'] = $system_note_input;

		// 確認轉帳密碼是否正確，和登入者的轉帳管理員密碼一樣，避免 api 被 xss 直接攻擊, 加上密碼稽核
		// 如果是管理員操作的話, 使用 5566bypass 為預設密碼
		if ($d['password_verify_sha1'] == $check_acc[1]->withdrawalspassword OR $d['password_verify_sha1'] == '5566bypass') {
			// correct
			$error['code'] = '1';
			$error['messages'] = '转帐密码正确';

			// 轉帳 gtoken 的動作
			// 0.取得目的端使用者完整的資料
			$destination_transferaccount_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '" . $d['destination_transferaccount'] . "';";
			$destination_transferaccount_result = runSQLALL($destination_transferaccount_sql);
			//var_dump($destination_transferaccount_result);
			if ($destination_transferaccount_result[0] == 1) {
				// 1. 取得來源端使用者完整的資料
				$error['code'] = '1';
				$error['messages'] = '取得来源端使用者完整的资料';

				$source_transferaccount_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '" . $d['source_transferaccount'] . "';";
				$source_transferaccount_result = runSQLALL($source_transferaccount_sql);
				if ($source_transferaccount_result[0] == 1) {
					// 2. 檢查帳戶 $source_transferaccount GCASH 是否有錢，且大於 $transaction_money，成立才工作，否則結束
					if ($source_transferaccount_result[1]->gcash_balance >= $d['transaction_money']) {
						$error['code'] = '1';
						$error['messages'] = $d['source_transferaccount'] . ' 现金(GCASH)有余额，且大于' . $d['transaction_money'];

						// 來源ID $source_transferaccount_result[1]->id
						// 目的ID $destination_transferaccount_result[1]->id
						// 稽核判斷寫入 notes 的文字，and 控制稽核金額
						// 存款稽核 * 1 倍
						$d['auditmode_select'] = 'depositaudit';
						// 稽核金額 * 1 倍
						$d['auditmode_amount'] = $d['transaction_money'];
						$audit_notes = '稽核金额' . $d['auditmode_amount'];

						// 取得現金出納及代幣出納的 ID 及檢查
						// 現金出納 ID
						$gcash_cashier_account_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '" . $gcash_cashier_account . "';";
						$gcash_cashier_account_result = runSQLall($gcash_cashier_account_sql);
						// 代幣出納 ID
						$gtoken_cashier_account_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '" . $gtoken_cashier_account . "';";
						$gtoken_cashier_account_result = runSQLall($gtoken_cashier_account_sql);
						if ($gcash_cashier_account_result[0] == 1 AND $gtoken_cashier_account_result[0] == 1) {
							// 檢查現金及代幣的餘額是否大於 0
							if ($gcash_cashier_account_result[1]->gcash_balance > 0 AND $gtoken_cashier_account_result[1]->gtoken_balance > 0) {
								$gcash_cashier_account_id = $gcash_cashier_account_result[1]->id;
								$gtoken_cashier_account_id = $gtoken_cashier_account_result[1]->id;
							} else {
								$error['code'] = '532';
								$error['messages'] = '系统现金帐号或是出纳帐号的余额没了，请联络客服人员处理。';
								echo '<p align="center"><button type="button" class="btn btn-danger">' . $error['messages'] . '</button></p>' . '<script>alert("' . $error['messages'] . '");</script>';
								return ($error);
								die();
							}
						} else {
							$error['code'] = '531';
							$error['messages'] = '现金帐号或是出纳帐号的取得有问题，请联络客服人员处理。';
							echo '<p align="center"><button type="button" class="btn btn-danger">' . $error['messages'] . '</button></p>' . '<script>alert("' . $error['messages'] . '");</script>';
							return ($error);
							die();
						}

						// 交易開始
						// * 將 GCASH 轉 $$ 到 GTOKEN
						// (A) 交易動作為  使用者的 gcash $$ to 系統現金出納
						// (B) 交易動作為  系統的代幣出納 to $$ 到使用者的 gtoken
						$transaction_money_sql = 'BEGIN;';

						// (A) 交易動作為 使用者的 gcash $$ to 系統現金出納
						// 操作：進行轉帳，在 會員錢包及娛樂城錢包(root_member_wallets)資料表 轉移金額
						// 會員 gcash 帳號餘額刪除 transaction_money
						$transaction_money_sql = $transaction_money_sql .
							'UPDATE root_member_wallets SET changetime = NOW(), gcash_balance = (SELECT (gcash_balance-' . $d['transaction_money'] . ') as amount FROM root_member_wallets WHERE id = ' . $source_transferaccount_result[1]->id . ') WHERE id = ' . $source_transferaccount_result[1]->id . ';';
						// 目的(系統出納)帳號加入上 transaction_money 餘額
						$transaction_money_sql = $transaction_money_sql .
							'UPDATE root_member_wallets SET changetime = NOW(), gcash_balance = (SELECT (gcash_balance+' . $d['transaction_money'] . ') as amount FROM root_member_wallets WHERE id = ' . $gcash_cashier_account_id . ') WHERE id = ' . $gcash_cashier_account_id . ';';

						// 操作：紀錄轉帳資訊在 現金 GCASH 存摺(root_member_gcashpassbook)資料表
						// 資料庫 新增 1 筆紀錄 帳號 source_transferaccount 轉帳到 $gcash_cashier_account_id 金額 transaction_money
						// 給會員看的紀錄
						$source_notes = "(帐号" . $d['source_transferaccount'] . " 现金转到同帐号游戏币)";
						$transaction_money_sql = $transaction_money_sql .
							'INSERT INTO "root_member_gcashpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")' .
							"VALUES ('now()', '0', '" . $d['transaction_money'] . "', '" . $source_notes . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "','" . $d['realcash'] . "', '" . $gcash_cashier_account . "', '" . $d['transaction_category'] . "', (SELECT gcash_balance FROM root_member_wallets WHERE id = " . $source_transferaccount_result[1]->id . ") );";

						// 資料庫 新增 1 筆紀錄 帳號 destination_transferaccount 收到來自 source_transferaccount 金額 transaction_money
						// 給系統出納看的紀錄
						$destination_notes = "(帐号" . $d['source_transferaccount'] . "转帐到" . $gcash_cashier_account . "帐号, " . $audit_notes . ')' . $d['system_note_input'];
						$transaction_money_sql = $transaction_money_sql .
							'INSERT INTO "root_member_gcashpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")' .
							"VALUES ('now()', '" . $d['transaction_money'] . "', '0', '" . $destination_notes . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $gcash_cashier_account . "', '" . $d['realcash'] . "', '" . $d['source_transferaccount'] . "', '" . $d['transaction_category'] . "', (SELECT gcash_balance FROM root_member_wallets WHERE id = " . $gcash_cashier_account_id . ") );";

						// (B) 交易動作為 系統的代幣出納 to $$ 到使用者的 gtoken
						// 操作：進行轉帳，在 會員錢包及娛樂城錢包(root_member_wallets)資料表 進行遊戲幣轉移
						// 系統出納 gtoken 帳號餘額刪除 transaction_money
						$transaction_money_sql = $transaction_money_sql .
							'UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (SELECT (gtoken_balance-' . $d['transaction_money'] . ') as amount FROM root_member_wallets WHERE id = ' . $gtoken_cashier_account_id . ') WHERE id = ' . $gtoken_cashier_account_id . ';';
						// 會員 gtoken 帳號加入上 transaction_money 餘額
						$transaction_money_sql = $transaction_money_sql .
							'UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (SELECT (gtoken_balance+' . $d['transaction_money'] . ') as amount FROM root_member_wallets WHERE id = ' . $source_transferaccount_result[1]->id . ') WHERE id = ' . $source_transferaccount_result[1]->id . ';';

						// 操作：root_member_gtokenpassbook
						// 資料庫新增 1 筆給會員看的紀錄：
						// 現金出納($gcash_cashier_account_id)帳號 轉帳到 來源帳號(source_transferaccount)
						// 金額 transaction_money (GTOKEN)
						$source_notes = "(帐号" . $d['source_transferaccount'] . '现金转游戏币, ' . $audit_notes . ')' . $d['system_note_input'];
						$transaction_money_sql = $transaction_money_sql .
							'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "auditmode", "auditmodeamount", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")' .
							"VALUES ('now()', '" . $d['auditmode_select'] . "', '" . $d['auditmode_amount'] . "', '" . $d['transaction_money'] . "', '0', '" . $source_notes . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $d['source_transferaccount'] . "','" . $d['realcash'] . "', '" . $gtoken_cashier_account . "', '" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $source_transferaccount_result[1]->id . ") );";
						// 資料庫新增 1 筆給游戏币出納人員看的：
						// 目的帳號($destination_transferaccount) 收到來自 來源帳號($source_transferaccount) 轉帳
						// 金額 transaction_money (GTOKEN)
						$destination_notes = "(帐号" . $gtoken_cashier_account . "存款到," . $d['source_transferaccount'] . $audit_notes . ')' . $d['system_note_input'];
						$transaction_money_sql = $transaction_money_sql .
							'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "auditmode", "auditmodeamount", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")' .
							"VALUES ('now()', '" . $d['auditmode_select'] . "', '" . $d['auditmode_amount'] . "', '0', '" . $d['transaction_money'] . "', '" . $destination_notes . "', '" . $d['member_id'] . "', '" . $config['currency_sign'] . "', '" . $d['summary'] . "', '" . $gtoken_cashier_account . "', '" . $d['realcash'] . "', '" . $d['source_transferaccount'] . "', '" . $d['transaction_category'] . "', (SELECT gtoken_balance FROM root_member_wallets WHERE id = " . $gtoken_cashier_account_id . ") );";

						// commit 提交
						$transaction_money_sql = $transaction_money_sql . 'COMMIT;';

						if ($debug == 1) {
							echo '<pre>';
							print_r($transaction_money_sql);
							echo '</pre>';
						}

						// 執行 transaction sql
						$transaction_money_result = runSQLtransactions($transaction_money_sql);
						// $transaction_money_result = 0;
						if ($transaction_money_result) {
							$error['code'] = '1';
							$transaction_money_html = money_format('%i', $d['transaction_money']);
							$error['messages'] = '成功将' . $d['source_transferaccount'] . '帐号 GCASH 转换为 GTOKEN 金额:' . $transaction_money_html;
						} else {
							$error['code'] = '7';
							$error['messages'] = 'SQL转帐失败从' . $d['source_transferaccount'] . '到' . $d['destination_transferaccount'] . '金額' . $d['transaction_money'];
						}

					} else {
						$error['code'] = '6';
						$error['messages'] = $d['source_transferaccount'] . '余额不足于' . $d['transaction_money'];
					}

				} else {
					$error['code'] = '4';
					$error['messages'] = '查不到来源端的使用者' . $d['source_transferaccount'] . '资料。';
				}

			} else {
				$error['code'] = '5';
				$error['messages'] = '查不到目的端的使用者' . $d['destination_transferaccount'] . '资料。';
			}

		} else {
			// incorrect
			$error['code'] = '3';
			$error['messages'] = $d['source_transferaccount'] . '来源帐号的转帐密码不正确';
		}

	} else {
		// error return
		$error['code'] = '2';
		$error['messages'] = '帐号有问题' . $check_acc[1]->account;
	}

	if ($debug == 1) {
		var_dump($error);
	}

	return ($error);
}


/**
 *  將 GCASH 依據設定值，自動加值到 GTOKEN 上面
 *  需要搭配上面的 auto_gcash2gtoken() 使用才可以。
 *  操作者通常只有會員本人, 所以預設值為同一人。
 *
 * @param string $userid 會員 ID
 * @param int    $debug  除錯模式，預設 0 為關閉
 *
 * @return array 交易資訊。回傳 code = 1 為成功，code != 1 為其他原因導致失敗
 */
function Transferout_GCASH_GTOKEN_balance($userid, $debug = 0)
{
	$user_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '$userid';";
	$user_result = runSQLall($user_sql);

	// 允許自動儲值
	if ($user_result[1]->auto_gtoken == 1) {
		// 檢查 gtoken代幣錢包 餘額是否還有錢？且餘額 小於 最低自動轉帳金額(auto_min_gtoken)
		if ($user_result[1]->gtoken_balance <= $user_result[1]->auto_min_gtoken) {
			// gcash現金錢包 是否還有錢
			if ($user_result[1]->gcash_balance < 1) {
				$logger = '你已經沒有現金餘額了，請透過銀行或線上存款方式儲值。';
				$r['code'] = 402;
				$r['messages'] = $logger;
				//echo "<p> $logger </p>";
			} else {
				// 判斷可以儲值多少？
				// 當現金餘額大於 >= 一次轉帳的金額auto_once_gotken時，儲值 auto_once_gotken
				// 當儲值auto_once_gotken大於現金時，儲值現金gcash_balance的全部金額。
				if ($user_result[1]->gcash_balance >= $user_result[1]->auto_once_gotken) {
					// 1. 當 gcash現金錢包(gcash_balance) 餘額 大於等於 每次儲值金額(auto_once_gtoken) 時，儲值 每次儲值金額(auto_once_gtoken)
					$auto_result = auto_gcash2gtoken($userid, $user_result[1]->account, $user_result[1]->auto_once_gotken, $user_result[1]->withdrawalspassword, $debug, NULL);

					if ($auto_result['code'] == 1) {
						$logger = '會員' . $user_result[1]->account . '現金轉代幣' . $user_result[1]->auto_once_gotken . '完成';
						$r['code'] = 1;
						$r['messages'] = $logger;
					} else {
						$logger = '會員' . $user_result[1]->account . '現金轉代幣' . $user_result[1]->auto_once_gotken . '失敗';
						$r['code'] = 551;
						$r['messages'] = $logger;
					}
				} else {
					// 2. 當 每次儲值金額(auto_once_gtoken) 大於現金時，儲值 gcash現金錢包(gcash_balance) 的全部金額
					$auto_result = auto_gcash2gtoken($userid, $user_result[1]->account, $user_result[1]->auto_once_gotken, $user_result[1]->withdrawalspassword, $debug, NULL);

					if ($auto_result['code'] == 1) {
						$logger = '會員' . $user_result[1]->account . '現金' . $user_result[1]->gcash_balance . '轉代幣' . $user_result[1]->gcash_balance . '完成';
						$r['code'] = 1;
						$r['messages'] = $logger;
					} else {
						$logger = '會員' . $user_result[1]->account . '現金' . $user_result[1]->gcash_balance . '轉代幣' . $user_result[1]->gcash_balance . '失敗';
						$r['code'] = 552;
						$r['messages'] = $logger;
					}
				}
			}
		} else {
			$logger = '你的代幣錢包還有餘額' . $user_result[1]->gtoken_balance . '，且大於最低自動轉帳餘額' . $user_result[1]->auto_min_gtoken . '暫停儲值';
			$r['code'] = 403;
			$r['messages'] = $logger;
		}
	} else {
		// 不允許自動儲值
		$logger = '你尚未設定允許自動儲值轉換，請至「會員錢包」功能處將自動儲值轉換功能打開。';
		$r['code'] = 401;
		$r['messages'] = $logger;
	}

	if ($debug == 1) {
		var_dump($r);
	}

	return ($r);
}


/**
 *  產生會員轉換娛樂城的紀錄
 *
 * @param mixed $source         來源
 * @param mixed $destination    目的地
 * @param mixed $token          轉帳金額
 * @param mixed $note           附註
 * @param mixed $memberid       會員ID
 * @param mixed $logstatus      紀錄等級
 * @param mixed $transaction_id transaction id
 * @param int   $casino_transfer_status 娛樂城轉帳狀態，預設 0 為 平台內轉帳或非轉帳API功能
 *
 * @return int|string|null 轉帳結果
 */
function member_casino_transferrecords($source, $destination, $token, $note, $memberid, $logstatus, $transaction_id = '', $casino_transfer_status = 0)
{
	global $config;

	// 定義log level所包含要記錄的訊息層級
	$log_level_list = [
		'debug' => ['success', 'info', 'fail', 'warning'],
		'info' => ['success', 'info', 'fail', 'warning'],
		'warning' => ['success', 'fail', 'warning'],
		'error' => ['success', 'fail']
	];

	$member_transferrecords_result = '';

	if (in_array($logstatus, $log_level_list[$config['casino_transferlog_level']])) {
		date_default_timezone_set('America/St_Thomas');
		$datetime_now = date("Y-m-d H:i:s");
		$source = filter_var($source, FILTER_SANITIZE_MAGIC_QUOTES);
		$destination = filter_var($destination, FILTER_SANITIZE_MAGIC_QUOTES);
		$token = filter_var($token, FILTER_SANITIZE_MAGIC_QUOTES);
		$note = filter_var($note, FILTER_SANITIZE_MAGIC_QUOTES);
		if (isset($_SESSION['agent'])) $note = $note . ' By 客服 ' . $_SESSION['agent']->realname . '(' . $_SESSION['agent']->account . ')';
		else $note = $note . ' By System';
		if (isset($_SERVER["REMOTE_ADDR"])) {
			$agent_ip = $_SERVER["REMOTE_ADDR"];
		} else {
			$agent_ip = 'no_remote_addr';
		}

		// 操作人員使用的 browser 指紋碼, 有可能會沒有指紋碼. JS close 的時候會發生
		if (isset($_SESSION['fingertracker'])) {
			$fingertracker = $_SESSION['fingertracker'];
		} else {
			$fingertracker = 'no_fingerprinting';
		}

		$member_transferrecords_sql = 'INSERT INTO "root_member_casino_transferrecords" ("memberid","source","destination","token","occurtime","agent_ip", "fingerprint","note","status","transaction_id", "casino_transfer_status") VALUES (\'' . $memberid . '\',\'' . $source . '\',\'' . $destination . '\',\'' . $token . '\',now(),\'' .$agent_ip . '\',\'' . $fingertracker . '\',\'' . $note . '\',\'' . $logstatus . '\',\'' . $transaction_id	. '\', '. $casino_transfer_status .');';
		$member_transferrecords_result = runSQL($member_transferrecords_sql);
	}

	return ($member_transferrecords_result);
}


/**
 *  檢查後台是否有在協助會員操作錢包轉錢至娛樂或是自娛樂城取錢
 *
 * @param mixed $account 會員帳號
 *
 * @return mixed 檢查結果
 */
function agent_walletscontrol_check($account)
{
	global $redisdb;

	$member_lock_key = sha1($account . 'AgentLock');

	$redis = new Redis();
	// 2 秒 timeout
	if ($redis->pconnect($redisdb['host'], 6379, 1)) {
		// success
		if ($redis->auth($redisdb['auth'])) {
			// echo 'Authentication Success';
		} else {
			return (0);
			die('Redisdb authentication failed');
		}
	} else {
		// error
		return (0);
		die('Redisdb Connection Failed');
	}
	// 選擇 DB , member 使用者自訂的 session 放在 db 2
	$redis->select(2);

	$alive_userkeys = $redis->get("$member_lock_key");
	// 同一個使用者，只能有一個登入.沒有登入使用者的時候，應該是 false
	return ($alive_userkeys);
}
