<?php
// ----------------------------------------------------------------------------
// Features:	MG Casino Switch 的專用函式庫
// File Name:	casino_switch_lib.php
// Author:		Ian
// Related:
// Log:
// ----------------------------------------------------------------------------
/*
// function 索引及說明：
// -------------------
1. MG API 文件函式及用法 sample , 操作 MG API (by totalegame)
mg_restfulapi($method, $debug=0, $MG_API_data)
2. 產生會員轉換娛樂城的紀錄
member_casino_transferrecords($source,$destination,$token,$note,$memberid,$logstatus)
3. 停用娛樂城時取回會員在娛樂城餘額用
db_mg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account,$gtoken_cashier_account, $mg_balance_api, $mg2gpk_balance, $mg_balance_db, $debug=0 )
4. 娛樂城停用，取回會員在娛樂城代幣用取回FUNCTION,需配合 db_mg2gpk_balance_4casino_switchoff()
retrieve_mg_restful_casino_balance_4casino_switchoff($i, $accountid, $account, $casino_account, $gtoken_balance, $casino_dbbalance, $check_count, $debug=0){
5. 娛樂城停用，取回會員在娛樂城代幣用主FUNCTION,需配合 retrieve_mg_restful_casino_balance_4casino_switchoff()
casino_switch_process_mg($casino_switch_member_list_result,$api_limit,$debug=0)
6.取得會員的 DB GTOKEN 餘額並全部傳送到 PT CASINO 上
transferout_gtoken_mg_restful_casino_balance($memberid, $debug=0)
7. 取回 PT Casino 的餘額 -- 針對 db 的處理函式，只針對此功能有用。不能單獨使用，需要搭配 retrieve_pt_casino_balance
db_pt2gpk_balance($gtoken_cashier_account, $pt_balance_api, $pt2gpk_balance, $pt_balance_db, $debug=0 )
8. 取回 PT Casino 的餘額
retrieve_pt_casino_balance($memberid, $debug=0)
9. 取得會員目前在 PT Casino 的餘額
getbalance_mg($memberid, $debug=0)
*/

// ---------------------------------------------------------------------------
// Features:
//  填入設定的功能，登入 MG api 的函式
// Usage:
//  mg_restfulapi($method, $debug=0, $MG_API_data)
// Input:
//  $method --> 操作的功能
//  $debug=0 --> 設定為 1 為除錯。
//  $MG_API_data --> 填入需的參數，需要搭配 method
// Return:
// -- 如果讀取投注紀錄成功的話 --
// $MG_API_result['curl_status'] = 0; // curl 正確
// $MG_API_result['count'] // 計算取得的紀錄數量有多少
// $MG_API_result['errorcode'] = 0; // 取得紀錄沒有錯誤
// $MG_API_result['Status'] // 回傳的狀態
// $MG_API_result['Result'] // 回傳的緬果
//
// -- 如果讀取投注紀錄失敗的話 --
// $MG_API_result['curl_status'] = 1; // curl 錯誤
// $MG_API_result['errorcode'] = 500; // 錯誤碼
// $MG_API_result['Result'] // 回傳的錯誤訊息
// ---------------------------------------------------------------------------
function mg_restfulapi($method, $debug=0, $MG_API_data) {
  //$debug=1;
	// 設定 socket_timeout , http://php.net/manual/en/soapclient.soapclient.php
	ini_set('default_socket_timeout', 5);

	global $MG_CONFIG;
  // Setting restful url
  $url = $MG_CONFIG['url'];

  $apilogin = $MG_CONFIG['apiaccount'].':'.$MG_CONFIG['apipassword'];

  if($method == 'GetVersion'){
    // 取得目前的 API 版本, 不一樣就 GG 了!!
    // Product name: TeG API. Product version: 2.8.9.2 ,  2016.8.13
    $service_url = $url.'GetVersion';
  }elseif($method == 'GetAccountDetails'){
		$service_url = $url.'GetAccountDetails';
  }elseif($method == 'GetAccountBalance'){
    $service_url = $url.'GetAccountBalance';
  }elseif($method == 'Deposit'){
    $service_url = $url.'Deposit';
  }elseif($method == 'Withdrawal'){
    $service_url = $url.'Withdrawal';
  }elseif($method == 'GetBettingProfileList'){
    $service_url = $url.'GetBettingProfileList';
  }elseif($method == 'GetPlaycheckUrl'){
    $service_url = $url.'GetPlaycheckUrl';
  }elseif($method == 'AddAccount'){
    $service_url = $url.'AddPlayerAccount';
  }elseif($method == 'IsAccountAvailable'){
    $service_url = $url.'IsAccountAvailable';
  }elseif($method == 'LockAccounts'){
    $service_url = $url.'LockAccounts';
  }else{
    $result = NULL;
  }


  $ret = array();
  try {
    // 轉成 json 代入 http head
    $postJson = json_encode($MG_API_data);

    //HTTP headers
    $headers = array(
      'Content-Type: application/json',
      //'Authorization: Basic '. base64_encode($username.":".$password),
      'Content-Length: ' . strlen($postJson)
    );

    //Call API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $service_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $apilogin);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST , TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postJson);
    //curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $response = curl_exec($ch);

		if($debug == 1) {
			echo curl_error($ch);
			var_dump($response);
		}

    // Then, after your curl_exec call , 移除 http head 剩下 body
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $body = json_decode($body);

    if($debug == 1) {
      var_dump($body);
    }
    // 如果 curl 讀取投注紀錄成功的話
    if(isset($body->Status) AND isset($body->Result) AND $body->Status->ErrorCode == 0 ) {
      // curl 正確
      $ret['curl_status'] = 0;
      // 計算取得的紀錄數量有多少
      $ret['count']	= count($body->Result);
      // 取得紀錄沒有錯誤
      $ret['errorcode'] = 0;
      // 存下 body
      $ret['Status'] = $body->Status;
      $ret['Result'] = $body->Result;
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
  return($ret);
}
// ----------------------------------------------------------------------------
// login MG API function end
// ----------------------------------------------------------------------------

  // ---------------------------------------------------------------------------------
  // 取回 MG Casino 的餘額 -- 針對 db 的處理函式，只針對此功能有用。 -- retrieve_mg_restful_casino_balance_4casino_switchoff
  // 不能單獨使用，需要搭配 retrieve_mg_restful_casino_balance_4casino_switchoff
  // ---------------------------------------------------------------------------
  // Features:
  //  5.2 把 MG API 傳回的餘額，透過 GTOKEN出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶。 (DB操作)
  //  5.3 紀錄當下 DB mg_balance 的餘額紀錄：存摺 GTOKEN 紀錄: (MG API)收入=4 , (DB MG餘額)支出=10 ,(派彩)餘額 = -6 + 原有結餘 , 摘要：MG派彩 (DB操作)
  // Usage:
  //  db_mg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account,$gtoken_cashier_account, $mg_balance_api, $mg2gpk_balance, $mg_balance_db );
  // Input:
  //  $gtoken_cashier_account   --> $gtoken_cashier_account(此為系統代幣出納帳號 global var.)
  //  $mg_balance_api           --> 取得的 MG API 餘額 , 保留小數第二位 round( $x, 2);
  //  $mg2gpk_balance           --> 派彩 = 娛樂城餘額 - 本地端MG支出餘額
  //  $mg_balance_db            --> 在剛取出的 wallets 資料庫中的餘額(支出)
  // Return:
  //  $r['ErrorCode']     = 1;  --> 成功 $accountid, $account, $casino_account, $gtoken_balance, $casino_dbbalance
  // ---------------------------------------------------------------------------
  function db_mg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account,$gtoken_cashier_account, $mg_balance_api, $mg2gpk_balance, $mg_balance_db, $debug=0 ) {
    //$debug=1;
    global $gtoken_cashier_account;
    global $transaction_category;
		global $auditmode_select;

    // 取得來源與目的帳號的 id ,  $gtoken_cashier_account(此為系統代幣出納帳號 global var.)
    // --------
    $d['source_transferaccount']        = $gtoken_cashier_account;
    $d['destination_transferaccount']   = $account;
    // --------
    $source_id_sql      = "SELECT * FROM root_member WHERE account = '".$d['source_transferaccount']."';";
    //var_dump($source_id_sql);
    $source_id_result   = runSQLall($source_id_sql);
    $destination_id_sql = "SELECT * FROM root_member WHERE account = '".$d['destination_transferaccount']."';";
    //var_dump($destination_id_sql);
    $destination_id_result   = runSQLall($destination_id_sql);
    if($source_id_result[0] == 1 AND $destination_id_result[0] == 1) {
      $d['source_transfer_id']  = $source_id_result[1]->id;
      $d['destination_transfer_id']  = $destination_id_result[1]->id;
    }else{
      $logger = '转帐的来源与目的帐号可能有问题，请稍候再试。';
      $r['ErrorCode']     = 590;
      $r['ErrorMessage']  = $logger;
      echo "<p> $logger </p>";
      die();
    }
    // ---------------------------------------------------------------------------------

    if($debug == 1) {
      var_dump($mg2gpk_balance);
    }

    // 派彩有三種狀態，要有不同的對應 SQL 處理
    // --------------------------------
    // $mg2gpk_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 MG 餘額取回 gpk。
    // $mg2gpk_balance < 0; 從娛樂城輸錢
    // --------------------------------
    if($mg2gpk_balance >= 0){
      // ---------------------------------------------------------------------------------
      // $mg2gpk_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 MG 餘額取回 gpk。
      // ---------------------------------------------------------------------------------

			// 判斷是否為測試環境, 如是則必需用DB中gtoken的值加上玩家的變化餘額才是真正的錢包餘額
			//if ($config['casino_transfer_mode'] == 2) {
				// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
				$wallets_sql = "SELECT gtoken_balance,casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
				//var_dump($wallets_sql);
				$wallets_result = runSQLall($wallets_sql);
				//var_dump($wallets_result);
				// 在剛取出的 wallets 資料庫中的mg餘額(支出)
				$gtoken_mg_balance_db = round($wallets_result[1]->mg_balance, 2);
				// 在剛取出的 wallets 資料庫中的gtoken餘額(支出)
				$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
				// 派彩 = 娛樂城餘額 - 本地端PT支出餘額
				$gtoken_balance = round(($gtoken_balance_db + $gtoken_mg_balance_db + $mg2gpk_balance), 2);
			//} else {
			//	$gtoken_balance = $mg_balance_api;
			//}

      // 交易開始
  		$mg2gpk_transaction_sql = 'BEGIN;';
      // 存款金額 -- 娛樂城餘額
      $d['deposit']  = $gtoken_balance;
      // 提款金額 -- 本地端支出
      $d['withdrawal']  = $mg_balance_db;
      // 操作者
      $d['member_id']  = $accountid;
      // MG + 代幣派彩
      $d['summary']  = 'MG'.$transaction_category['tokenpay'];
      // 稽核方式
      $d['auditmode']  = $auditmode_select['mg'];
      // 稽核金額 -- 派彩無須稽核
      $d['auditmodeamount']  = 0;
      // MG 取回的餘額為真錢
      $d['realcash'] = 2;
      // 交易類別 MG + $transaction_category['tokenpay']
      $d['transaction_category']       = 'tokenpay';
      // 變化的餘額
      $d['balance']       = $mg2gpk_balance;
      // var_dump($d);

      // 操作 root_member_wallets DB, 把 mg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
      // 錢包存入 餘額 , 把 mg_balance 扣除全部表示支出(投注).
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = ".$d['deposit'].", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"MG\",\"balance\"}','0') WHERE id = '".$d['destination_transfer_id']."'; ";
      // 目的帳號上的註記
      $d['destination_notes']  = '(會員收到MG派彩'.$d['balance'].' by 关闭娱乐城)';
      // 針對目的會員的存簿寫入，$mg2gpk_balance >= 1 表示贏錢，所以從出納匯款到使用者帳號。
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '".$d['deposit']."', '".$d['withdrawal']."', '".$d['destination_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['destination_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['destination_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['destination_transfer_id'].") );";

      // 針對來源出納的存簿寫入
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance - " . $d['balance'] .") WHERE id = '" . $d['source_transfer_id'] . "'; ";
      // 來源帳號上的註記
      $d['source_notes']  = '(出納帳號 '.$d['source_transferaccount'].' 幫MG派彩到會員 '.$d['destination_transferaccount'].')';
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '0', '".$d['balance']."', '".$d['source_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['source_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['source_transfer_id']."));";

      // commit 提交
  		$mg2gpk_transaction_sql = $mg2gpk_transaction_sql.'COMMIT;';

      if($debug == 1) {
  		    echo '<p>SQL='.$mg2gpk_transaction_sql.'</p>';
      }

      // 執行 transaction sql
  		$mg2gpk_transaction_result = runSQLtransactions($mg2gpk_transaction_sql);
  		if($mg2gpk_transaction_result){
        $logger = '从MG帐号'.$casino_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'，共计派彩='.$mg2gpk_balance;
        $r['ErrorCode']     = 1;
        $r['ErrorMessage']  = $logger;
        #memberlog2db($account,'mggame','info', "$logger");
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$accountid,'info');
  		}else{
        //5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2,5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
	      $logger = '从MG帐号'.$casino_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'，共计派彩='.$mg2gpk_balance;
        $logger = $logger.'但资料库处理错误，请通知客服人员处理。';
        #memberlog2db($d['member_id'],'mg_transaction','error', "$logger");
        $r['ErrorCode']     = 406;
        $r['ErrorMessage']  = $logger;
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$accountid,'warning');
        #memberlog2db($account,'mggame','error', "$logger");
      }

      if($debug == 1) {
        var_dump($r);
      }
      // ---------------------------------------------------------------------------------


    }elseif($mg2gpk_balance < 0){
      // ---------------------------------------------------------------------------------
      // $mg2gpk_balance < 0; 從娛樂城輸錢
      // ---------------------------------------------------------------------------------

			// 判斷是否為測試環境, 如是則必需用DB中gtoken的值加上玩家的變化餘額才是真正的錢包餘額
			//if ($config['casino_transfer_mode'] == 2) {
				// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
				$wallets_sql = "SELECT gtoken_balance,casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
				//var_dump($wallets_sql);
				$wallets_result = runSQLall($wallets_sql);
				//var_dump($wallets_result);
				// 在剛取出的 wallets 資料庫中的mg餘額(支出)
				$gtoken_mg_balance_db = round($wallets_result[1]->mg_balance, 2);
				// 在剛取出的 wallets 資料庫中的gtoken餘額(支出)
				$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
				// 派彩 = 娛樂城餘額 - 本地端PT支出餘額
				$gtoken_balance = round(($gtoken_balance_db + $gtoken_mg_balance_db + $mg2gpk_balance), 2);
			//} else {
			//	$gtoken_balance = $mg_balance_api;
			//}

      // 交易開始
  		$mg2gpk_transaction_sql = 'BEGIN;';
      // 存款金額 -- 娛樂城餘額
      $d['deposit']           = $mg_balance_api;
      // 提款金額 -- 本地端支出
      $d['withdrawal']        = $mg_balance_db;
      // 操作者
      $d['member_id']         = $accountid;
      // MG + 代幣派彩
      $d['summary']           = 'MG'.$transaction_category['tokenpay'];
      // 稽核方式
      $d['auditmode']         = $auditmode_select['mg'];
      // 稽核金額 -- 派彩無須稽核
      $d['auditmodeamount']   = 0;
      // MG 取回的餘額為真錢
      $d['realcash'] = 2;
      // 交易類別 MG + $transaction_category['tokenpay']
      $d['transaction_category'] = 'tokenpay';
      // 變化的餘額
      $d['balance']       = abs($mg2gpk_balance);
      // var_dump($d);

      // 操作 root_member_wallets DB, 把 mg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
      // 錢包存入 餘額 , 把 mg_balance 扣除全部表示支出(投注).
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = ".$d['deposit'].", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"MG\",\"balance\"}','0') WHERE id = '".$d['destination_transfer_id']."'; ";
      // 目的帳號上的註記
      $d['destination_notes']  = '(會員收到MG派彩'.$mg2gpk_balance.' by 关闭娱乐城)';
      // 針對目的會員的存簿寫入，
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '".$d['deposit']."', '".$d['withdrawal']."', '".$d['destination_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['destination_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['destination_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['destination_transfer_id'].") );";

      // 針對來源出納的存簿寫入
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance + " . $d['balance'] .") WHERE id = '" . $d['source_transfer_id'] . "'; ";
      // 來源帳號上的註記
      $d['source_notes']  = '(出納帳號 '.$d['source_transferaccount'].' 從會員 '.$d['destination_transferaccount'].' 取回派彩餘額)';
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '".$d['balance']."', '0', '".$d['source_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['source_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['source_transfer_id']."));";

      // commit 提交
  		$mg2gpk_transaction_sql = $mg2gpk_transaction_sql.'COMMIT;';
      if($debug == 1) {
  		  echo '<p>SQL='.$mg2gpk_transaction_sql.'</p>';
      }

      // 執行 transaction sql
  		$mg2gpk_transaction_result = runSQLtransactions($mg2gpk_transaction_sql);
  		if($mg2gpk_transaction_result){
        $logger = '从MG帐号'.$casino_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'，共计派彩='.$mg2gpk_balance;
        $r['ErrorCode']     = 1;
        $r['ErrorMessage']  = $logger;
        #memberlog2db($account,'mggame','info', "$logger");
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$accountid,'info');
        // echo "<p> $logger </p>";
  		}else{
        //5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2,5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
        $logger = '从MG帐号'.$casino_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'共计派彩='.$mg2gpk_balance;
        $logger = $logger.'但资料库处理错误，请通知客服人员处理。';
        $r['ErrorCode']     = 406;
        $r['ErrorMessage']  = $logger;
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$accountid,'warning');
        #memberlog2db($account,'mggame','error', "$logger");
        // echo "<p> $logger </p>";
      }
      // var_dump($r);
      // ---------------------------------------------------------------------------------
    }else{
      // 不可能
      $logger = '不可能发生';
      $r['ErrorCode']     = 500;
      $r['ErrorMessage']  = $logger;
      #memberlog2db($account,'mggame','error', "$logger");
      echo "<p> $logger </p>";
    }

    return($r);
  }
  // ---------------------------------------------------------------------------------
  // 針對 db 的處理函式，只針對此功能有用。 END
  // ---------------------------------------------------------------------------------

  // ---------------------------------------------
  // 娛樂城停用，取回會員在娛樂城代幣用取回FUNCTION
  // member_casino_switchoff_transfer($i, $account, $casino_account, $gtoken_balance, $casino_dbbalance, $check_count, $debug=0){
  // $i 當前處理的組數，由FOR迴圈中的 $i 取得，配合 $check_count 用來計算處理進度用
  // $casino_account 會員的MG帳號
  // $casino_balance 會員目前在MG擁有的代幣
  // $gtoken_balance 會員在代幣存薄中的代幣餘額
  // $casino_dbbalance 會員在代幣存薄中記錄的娛樂城代幣餘額
  // $check_count 此次處理的總組數，配合 $i 一起用來計算處理進度用
  // ---------------------------------------------
  function retrieve_mg_restful_casino_balance_4casino_switchoff($i, $accountid, $account, $casino_account, $gtoken_balance, $casino_dbbalance, $check_count, $debug=0){
    global $gtoken_cashier_account;
    //$debug=1;
    // 檢查會員在娛樂城的代幣餘額
    $MG_API_data_accountarr[] = $casino_account;
    $MG_API_data  = array(
      'Accounts' => $MG_API_data_accountarr
    );
    if($debug == 1){
      var_dump($MG_API_data);
    }

    $MG_API_result = mg_restfulapi('GetAccountBalance', $debug, $MG_API_data);
    if($debug == 1){
      var_dump($MG_API_result);
    }

    if($MG_API_result['errorcode'] == 0 AND $MG_API_result['Status']->ErrorCode == 0 AND $MG_API_result['count'] > 0){
      //echo $MG_API_result['Result'][$i]->Balance;
      $casino_balance = $MG_API_result['Result']['0']->Balance;
      $process_schedule = round(($i/$check_count)*100,0);

      //取回會員代幣
      if($casino_balance > 0) {
        // 正式取回代幣前先將娛樂城的帳號 UNLOCK 以利接下來取回代幣
        $MG_API_data  = array(
          'Accounts' => $MG_API_data_accountarr,
          'IsLock' => 'False'
        );
        if($debug == 1){
          var_dump($MG_API_data);
        }

        $MG_API_Lock_result = mg_restfulapi('LockAccounts', $debug, $MG_API_data);
        if($debug == 1){
          var_dump($MG_API_Lock_result);
        }
        //5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3
        // 動作： Withdrawal 帳戶取款
        $MG_API_data  = array(
          'AccountNumber' => $casino_account,
          'Amount' => "$casino_balance" ,
          'TransactionReferenceNumber' => 'mg0Withdrawal0'.date("Ymdhis")
        );

        if($debug == 1) {
          echo '5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3';
          var_dump($MG_API_data);
        }

        $MG_API_result = mg_restfulapi('Withdrawal', 0, $MG_API_data);
        if($debug == 1){
          var_dump($MG_API_result);
        }

        if($MG_API_Lock_result['errorcode'] == 0 AND $MG_API_result['errorcode'] == 0 AND $MG_API_result['Status']->ErrorCode == 0 AND $MG_API_result['count'] > 0){
          // 取回MG餘額成功
          $logger = 'MG娱乐城停用，MG API 从帐号'.$casino_account.'取款余额'.$MG_API_result['Result']->TransactionAmount.'成功。交易编号为'.$MG_API_result['Result']->ConfirmationNumber;
          $r['code']     = 100;
          $r['messages']  = $logger;
          #memberlog2db($account,'mggame','info', "$logger");
	        member_casino_transferrecords('MG','lobby',$MG_API_result['Result']->TransactionAmount,$logger, $accountid,'success', $MG_API_result['Result']->ConfirmationNumber, 1);
          if($debug ==1) {
            echo "<p> $logger </p>";
            var_dump($MG_API_result);
          }
          // 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
          // -----------------------------------------------------------------------------------
          // wallets 資料庫中的餘額(支出)
          $mg_balance_db = round($casino_dbbalance,2);
          // 派彩 = 娛樂城餘額 - 本地端MG支出餘額
          $mg2gpk_balance = round(($casino_balance - $mg_balance_db),2);
          // -----------------------------------------------------------------------------------

          // 處理 DB 的轉帳問題 -- 5.2 and 5.3
          $db_mg2gpk_balance_result = db_mg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account, $casino_balance, $mg2gpk_balance, $mg_balance_db);
          if($db_mg2gpk_balance_result['ErrorCode'] == 1) {
            $r['code']     = 1;
            $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
            $logger = $r['messages'];
            #memberlog2db($account,'mg2gpk','info', "$logger");
          }else{
            $r['code']     = 523;
            $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
            $logger = $r['messages'];
            #memberlog2db($account,'mg2gpk','error', "$logger");
          }

          if($debug ==1) {
            echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
            var_dump($db_mg2gpk_balance_result);
          }
        }else{
          //5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3
          $logger = 'MG娱乐城停用，MG API 从帐号'.$casino_account.'取款余额'.$casino_balance.'失败';
          $r['code']     = 405;
          $r['messages']  = $logger;
          #memberlog2db($account,'mggame','error', "$logger");

          if($debug ==1) {
            echo "5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3";
            echo "<p> $logger </p>";
            var_dump($r);
          }
        }

      }elseif($casino_balance == 0) {
        $logger = 'MG娱乐城停用，MG余额 = 0 ，MG没有余额，无法取回任何的余额，将余额转回 GPK。';
        $r['code']     = 406;
        $r['messages']  = $logger;
        #memberlog2db($account,'mggame','info', "$logger");
        member_casino_transferrecords('MG','lobby','0',$logger,$accountid,'success');

        // 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
        // -----------------------------------------------------------------------------------
        // wallets 資料庫中的餘額(支出)
        $mg_balance_db = round($casino_dbbalance,2);
        // 派彩 = 娛樂城餘額 - 本地端MG支出餘額
        $mg2gpk_balance = round(($casino_balance - $mg_balance_db),2);
        // -----------------------------------------------------------------------------------

        // 處理 DB 的轉帳問題 -- 5.2 and 5.3
        $db_mg2gpk_balance_result = db_mg2gpk_balance_4casino_switchoff($accountid, $account, $casino_account, $gtoken_cashier_account, $casino_balance, $mg2gpk_balance, $mg_balance_db);
        if($db_mg2gpk_balance_result['ErrorCode'] == 1) {
          $r['code']     = 1;
          $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
          $logger = $r['messages'];
          #memberlog2db($account,'mg2gpk','info', "$logger");
        }else{
          $r['code']     = 523;
          $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
          $logger = $r['messages'];
          #memberlog2db($account,'mg2gpk','error', "$logger");
        }

        if($debug ==1) {
          echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
          var_dump($db_mg2gpk_balance_result);
        }

      }else{
        // MG餘額 < 0 , 不可能發生
        $logger = 'MG余额 < 1 ，不可能发生。';
        $r['code']     = 404;
        $r['messages']  = $logger;
      }
      // -----------------------------------------------------------------------------------

      if($debug == 1){
        var_dump($r);
      }

      // 最出處理結果存入JSON檔中以供頁面檢視
      $casino_shutdown_member_html = '<tr>
        <td>'.$account.'</td>
        <td>'.$gtoken_balance.'</td>
        <td>'.$casino_dbbalance.'</td>
        <td>'.$casino_balance.'</td>
        <td>'.$process_schedule.'</td>
        </tr>';
    }else{
      $casino_shutdown_member_html = '';
    }

    return($casino_shutdown_member_html);
  }
  // ---------------------------------------------
  // 產生會員轉換娛樂城的紀錄 END
  // ---------------------------------------------

  // ---------------------------------------------
  // 娛樂城停用，取回會員在娛樂城代幣用取回FUNCTION
  // casino_switch_process_mg($casino_switch_member_list_result,$api_limit,$debug=0)
  // $api_limit 娛樂城 API 的批次上限
  // ---------------------------------------------
  function casino_switch_process_mg($casino_switch_member_list_result,$api_limit,$debug=0){
		global $casino_switch_json;
    $casino_account_dbcolumn_name = 'mg_account';
    $casino_balance_dbcolumn_name = 'mg_balance';

    if($casino_switch_member_list_result['0'] >= $api_limit){
      for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i+=$api_limit) {
        $MG_API_data_accountarr = array();
        for ($j = 1; $j <= $api_limit; $j++) {
          $count = $i+$j-1;
          $MG_API_data_accountarr[] = $casino_switch_member_list_result[$count]->$casino_account_dbcolumn_name;
        }
        // 取回代幣前先將娛樂城的帳號LOCK住, 讓會員在取回過程無法下注，以免取回時掉錢
        $MG_API_data  = array(
          'Accounts' => $MG_API_data_accountarr,
          'IsLock' => 'True'
        );
        if($debug == 1){
          var_dump($MG_API_data);
        }

        $MG_API_Lock_result = mg_restfulapi('LockAccounts', $debug, $MG_API_data);
        if($debug == 1){
          var_dump($MG_API_Lock_result);
        }
      }
    }else{
      for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i++) {
        $MG_API_data_accountarr[] = $casino_switch_member_list_result[$i]->$casino_account_dbcolumn_name;
      }
        // 取回代幣前先將娛樂城的帳號LOCK住, 讓會員在取回過程無法下注，以免取回時掉錢
        $MG_API_data  = array(
          'Accounts' => $MG_API_data_accountarr,
          'IsLock' => 'True'
        );
        if($debug == 1){
          var_dump($MG_API_data);
        }

        $MG_API_Lock_result = mg_restfulapi('LockAccounts', $debug, $MG_API_data);
        if($debug == 1){
          var_dump($MG_API_Lock_result);
        }
    }

    // 等待15秒，讓正在下注的會員結束此次的下注，並取得派彩結果
    sleep(15);

    // 確定帳號lock了後再進行回收代幣的動作
    if($MG_API_Lock_result['errorcode'] == 0 AND $MG_API_Lock_result['Status']->ErrorCode == 0 AND $MG_API_Lock_result['Status']->ErrorCode == 'SUCCEED'){
      for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i++) {
        $now = $i;
        $step_count = $casino_switch_member_list_result['0']*2;
        // 對現行在娛樂城的會員進行代幣回收
        $casino_switch_member_html = retrieve_mg_restful_casino_balance_4casino_switchoff($now,$casino_switch_member_list_result[$i]->id,$casino_switch_member_list_result[$i]->account,$casino_switch_member_list_result[$i]->$casino_account_dbcolumn_name,$casino_switch_member_list_result[$i]->gtoken_balance,$casino_switch_member_list_result[$i]->$casino_balance_dbcolumn_name,$step_count,1);
        fwrite($casino_switch_json,$casino_switch_member_html);
      }
    }
      /*
      sleep(50);
      for ($i = 1; $i <= $casino_switch_member_list_result['0']; $i++) {
        $now = $i+$casino_switch_member_list_result['0'];
        $step_count = $casino_switch_member_list_result['0']*2;
        $casino_switch_member_html = retrieve_mg_restful_casino_balance_4casino_switchoff($now,$casino_switch_member_list_result[$i]->id,$casino_switch_member_list_result[$i]->account,$casino_switch_member_list_result[$i]->$casino_account_dbcolumn_name,$casino_switch_member_list_result[$i]->gtoken_balance,$casino_switch_member_list_result[$i]->$casino_balance_dbcolumn_name,$step_count,1);
        fwrite($casino_switch_json,$casino_switch_member_html);
      }*/
  }


	// ----------------------------------------------------------------------------
	// Features:
	//   將傳入的 member id 值，取得他的 DB GTOKEN 餘額並全部傳送到 MG CASINO 上
	//   把本地端的資料庫 root_member_wallets 的 GTOKEN_LOCK設定為 MG 餘額儲存在 mg_balance 上面
	// Usage:
	//   transferout_gtoken_mg_restful_casino_balance($memberid)
	// Input:
	//   $memberid --> 會員 ID
	//   debug = 1 --> 進入除錯模式
	//   debug = 0 --> 關閉除錯
	// Return:
	//   code = 1  --> 成功
	//   code != 1  --> 其他原因導致失敗
	// ----------------------------------------------------------------------------
	function transferout_gtoken_mg_restful_casino_balance($memberid, $debug = 0) {
		global $config;
	  // 將目前所在的 ID 值
	  // $memberid = $memberid;
	  // 驗證並取得帳戶資料
	  $member_sql = "SELECT root_member.id,gtoken_balance,account,gtoken_lock,
		        casino_accounts->'MG'->>'account' as mg_account,
		        casino_accounts->'MG'->>'password' as mg_password,
		        casino_accounts->'MG'->>'balance' as mg_balance FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '".$memberid."';";
	  $r = runSQLall($member_sql);
	  if($debug == 1) {
	    var_dump($r);
	  }
	  if($r[0] == 1 AND $config['casino_transfer_mode'] == 1) {

	    // 沒有 MG 帳號的話，根本不可以進來。
	    if($r[1]->mg_account == NULL OR $r[1]->mg_account == NULL) {
	      $check_return['messages'] =  '你還沒有 MG 帳號。';
	      $check_return['code'] = 12;
	    }else{
				$memberid = $r[1]->id;
				$memberaccount = $r[1]->account;

	      // 需要 gtoken_lock 沒有被設定的時候，才可以使用這功能。
	      if($r[1]->gtoken_lock == NULL OR $r[1]->gtoken_lock == 'MG') {

	        // 動作： 將本地端所有的 gtoken 餘額 Deposit 到 mg 對應的帳戶
	        $accountNumber = $r[1]->mg_account;
					$mg_balance = $r[1]->mg_balance;
	        $amount = $r[1]->gtoken_balance;
	        $MG_API_data  = array(
	          'AccountNumber' => $accountNumber,
	          'Amount' => $amount ,
	          'TransactionReferenceNumber' => 'mg0Depositl0'.date("Ymdhis")
	        );

	        $MG_API_result = mg_restfulapi('Deposit', 0, $MG_API_data);
	        if($MG_API_result['errorcode'] == 0 AND $MG_API_result['Status']->ErrorCode == 0 AND $MG_API_result['count'] > 0){
	          if($debug == 1) {
	            var_dump($MG_API_data);
	            var_dump($MG_API_result);
	          }
	          // 本地端 db 的餘額處理
						$mg_balance = $mg_balance + $amount;
	          $togtoken_sql = "UPDATE root_member_wallets SET gtoken_lock = 'MG'  WHERE id = '$memberid';";
	          $togtoken_sql = $togtoken_sql."UPDATE root_member_wallets SET gtoken_balance = gtoken_balance - '$amount',casino_accounts= jsonb_set(casino_accounts,'{\"MG\",\"balance\"}','$mg_balance')  WHERE id = '$memberid';";
	          $togtoken_sql_result = runSQLtransactions($togtoken_sql);
	          if($debug == 1) {
	            var_dump($togtoken_sql);
	            var_dump($togtoken_sql_result);
	          }
	          if($togtoken_sql_result){
	            $check_return['messages'] =  '所有GTOKEN余额已经转到MG娱乐城。 MG转帐单号 '.$MG_API_result['Result']->ConfirmationNumber.' MG帐号'.$MG_API_result['Result']->AccountNumber.'MG新增'.$MG_API_result['Result']->TransactionAmount;
	            $check_return['code'] = 1;
	            memberlog2db($memberaccount,'gpk2mg','info', $check_return['messages']);
	            member_casino_transferrecords('lobby','MG',$amount,$check_return['messages'],$memberid,'success', $MG_API_result['Result']->ConfirmationNumber, 1);
	          }else{
	            $check_return['messages'] =  '余额处理，本地端资料库交易错误。';
	            $check_return['code'] = 14;
	            memberlog2db($memberaccount,'gpk2mg','error', $check_return['messages']);
	            member_casino_transferrecords('lobby','MG',$amount,$check_return['messages'],$memberid,'warning', $MG_API_result['Result']->ConfirmationNumber, 2);
	          }
	        }else{
	          $check_return['messages'] =  '余额转移到 MG 时失败！！';
	          $check_return['code'] = 13;
	          memberlog2db($memberaccount,'gpk2mg','error', $check_return['messages']);
						member_casino_transferrecords('lobby','MG',$amount,$check_return['messages'].'('.$MG_API_result['Result'].')',$memberid,'fail');
	        }

	      }else{
	        $check_return['messages'] =  '此帐号已经在 MG 娱乐城活动，请勿重复登入。';
	        $check_return['code'] = 11;
					member_casino_transferrecords('lobby','MG','0',$check_return['messages'],$memberid,'warning');
	      }

	    }
	  } elseif ($r[0] == 1 AND $config['casino_transfer_mode'] == 0) {
			$check_return['messages'] = '测试环境不进行转帐交易';
			$check_return['code'] = 1;
			member_casino_transferrecords('lobby', 'MG', '0', $check_return['messages'],$memberid, 'info');
		}else{
	    $check_return['messages'] =  '无此帐号 ID = '.$memberid;
	    $check_return['code'] = 0;
			member_casino_transferrecords('lobby','MG','0',$check_return['messages'],$memberid,'fail');
	  }

	  // var_dump($check_return);
	  return($check_return);
	}
	// ----------------------------------------------------------------------------
	// END: 將傳入的 member id 值，取得他的 DB GTOKEN 餘額並全部傳送到 MG CASINO 上
	// ----------------------------------------------------------------------------

	// ---------------------------------------------------------------------------------
  // 取回 MG Casino 的餘額 -- 針對 db 的處理函式，只針對此功能有用。 -- retrieve_mg_restful_casino_balance
  // 不能單獨使用，需要搭配 retrieve_mg_restful_casino_balance
  // ---------------------------------------------------------------------------
  // Features:
  //  5.2 把 MG API 傳回的餘額，透過 GTOKEN出納帳號，轉帳到的相對使用者 account 的 GTOKEN 帳戶。 (DB操作)
  //  5.3 紀錄當下 DB mg_balance 的餘額紀錄：存摺 GTOKEN 紀錄: (MG API)收入=4 , (DB MG餘額)支出=10 ,(派彩)餘額 = -6 + 原有結餘 , 摘要：MG派彩 (DB操作)
  // Usage:
  //  db_mg2gpk_balance($memberaccount,$memberid,$member_mg_account,$gtoken_cashier_account, $mg_balance_api, $mg2gpk_balance, $mg_balance_db );
  // Input:
  //  $gtoken_cashier_account   --> $gtoken_cashier_account(此為系統代幣出納帳號 global var.)
  //  $mg_balance_api           --> 取得的 MG API 餘額 , 保留小數第二位 round( $x, 2);
  //  $mg2gpk_balance           --> 派彩 = 娛樂城餘額 - 本地端MG支出餘額
  //  $mg_balance_db            --> 在剛取出的 wallets 資料庫中的餘額(支出)
  // Return:
  //  $r['ErrorCode']     = 1;  --> 成功
  // ---------------------------------------------------------------------------
  function db_mg2gpk_balance($memberaccount,$memberid,$member_mg_account,$gtoken_cashier_account, $mg_balance_api, $mg2gpk_balance, $mg_balance_db, $debug=0 ) {

    global $gtoken_cashier_account;
    global $transaction_category;
		global $auditmode_select;

    // 取得來源與目的帳號的 id ,  $gtoken_cashier_account(此為系統代幣出納帳號 global var.)
    // --------
    $d['source_transferaccount']        = $gtoken_cashier_account;
    $d['destination_transferaccount']   = $memberaccount;
    // --------
    $source_id_sql      = "SELECT * FROM root_member WHERE account = '".$d['source_transferaccount']."';";
    //var_dump($source_id_sql);
    $source_id_result   = runSQLall($source_id_sql);
    $destination_id_sql = "SELECT * FROM root_member WHERE account = '".$d['destination_transferaccount']."';";
    //var_dump($destination_id_sql);
    $destination_id_result   = runSQLall($destination_id_sql);
    if($source_id_result[0] == 1 AND $destination_id_result[0] == 1) {
      $d['source_transfer_id']  = $source_id_result[1]->id;
      $d['destination_transfer_id']  = $destination_id_result[1]->id;
    }else{
      $logger = '转帐的来源与目的帐号可能有问题，请稍候再试。';
      $r['ErrorCode']     = 590;
      $r['ErrorMessage']  = $logger;
      echo "<p> $logger </p>";
      die();
    }
    // ---------------------------------------------------------------------------------

    if($debug == 1) {
      var_dump($mg2gpk_balance);
    }

    // 派彩有三種狀態，要有不同的對應 SQL 處理
    // --------------------------------
    // $mg2gpk_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 MG 餘額取回 gpk。
    // $mg2gpk_balance < 0; 從娛樂城輸錢
    // --------------------------------
    if($mg2gpk_balance >= 0){
      // ---------------------------------------------------------------------------------
      // $mg2gpk_balance >= 0; 從娛樂城贏錢 or 沒有輸贏，把 MG 餘額取回 gpk。
      // ---------------------------------------------------------------------------------

			// 判斷是否為測試環境, 如是則必需用DB中gtoken的值加上玩家的變化餘額才是真正的錢包餘額
			//if ($config['casino_transfer_mode'] == 2) {
				// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
				$wallets_sql = "SELECT gtoken_balance,casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
				//var_dump($wallets_sql);
				$wallets_result = runSQLall($wallets_sql);
				//var_dump($wallets_result);
				// 在剛取出的 wallets 資料庫中的mg餘額(支出)
				$gtoken_mg_balance_db = round($wallets_result[1]->mg_balance, 2);
				// 在剛取出的 wallets 資料庫中的gtoken餘額(支出)
				$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
				// 派彩 = 娛樂城餘額 - 本地端PT支出餘額
				$gtoken_balance = round(($gtoken_balance_db + $gtoken_mg_balance_db + $mg2gpk_balance), 2);
			//} else {
			//	$gtoken_balance = $mg_balance_api;
			//}

      // 交易開始
  		$mg2gpk_transaction_sql = 'BEGIN;';
      // 存款金額 -- 娛樂城餘額
      $d['deposit']  = $gtoken_balance;
      // 提款金額 -- 本地端支出
      $d['withdrawal']  = $mg_balance_db;
      // 操作者
      $d['member_id']  = $memberid;
      // MG + 代幣派彩
      $d['summary']  = 'MG'.$transaction_category['tokenpay'];
      // 稽核方式
      $d['auditmode']  = $auditmode_select['mg'];
      // 稽核金額 -- 派彩無須稽核
      $d['auditmodeamount']  = 0;
      // MG 取回的餘額為真錢
      $d['realcash'] = 2;
      // 交易類別 MG + $transaction_category['tokenpay']
      $d['transaction_category']       = 'tokenpay';
      // 變化的餘額
      $d['balance']       = $mg2gpk_balance;
      // var_dump($d);

      // 操作 root_member_wallets DB, 把 mg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
      // 錢包存入 餘額 , 把 mg_balance 扣除全部表示支出(投注).
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = ".$d['deposit'].", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"MG\",\"balance\"}','0') WHERE id = '".$d['destination_transfer_id']."'; ";
      // 目的帳號上的註記
      $d['destination_notes']  = '(會員收到MG派彩'.$d['balance'].' by 客服人員 '.$_SESSION['agent']->account.')';
      // 針對目的會員的存簿寫入，$mg2gpk_balance >= 1 表示贏錢，所以從出納匯款到使用者帳號。
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '".$d['deposit']."', '".$d['withdrawal']."', '".$d['destination_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['destination_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['destination_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['destination_transfer_id'].") );";

      // 針對來源出納的存簿寫入
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance - " . $d['balance'] .") WHERE id = '" . $d['source_transfer_id'] . "'; ";
      // 來源帳號上的註記
      $d['source_notes']  = '(出納帳號 '.$d['source_transferaccount'].' 幫MG派彩到會員 '.$d['destination_transferaccount'].')';
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '0', '".$d['balance']."', '".$d['source_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['source_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['source_transfer_id']."));";

      // commit 提交
  		$mg2gpk_transaction_sql = $mg2gpk_transaction_sql.'COMMIT;';

      if($debug == 1) {
  		    echo '<p>SQL='.$mg2gpk_transaction_sql.'</p>';
      }

      // 執行 transaction sql
  		$mg2gpk_transaction_result = runSQLtransactions($mg2gpk_transaction_sql);
  		if($mg2gpk_transaction_result){
        $logger = '从MG帐号'.$member_mg_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'，共计派彩='.$mg2gpk_balance;
        $r['ErrorCode']     = 1;
        $r['ErrorMessage']  = $logger;
        memberlog2db($memberaccount,'mggame','info', "$logger");
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$memberid,'info');
  		}else{
        //5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2,5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
	      $logger = '从MG帐号'.$member_mg_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'，共计派彩='.$mg2gpk_balance;
	      $logger = $logger.'但资料库处理错误，请通知客服人员处理。';
        memberlog2db($d['member_id'],'mg_transaction','error', "$logger");
        $r['ErrorCode']     = 406;
        $r['ErrorMessage']  = $logger;
        memberlog2db($memberaccount,'mggame','error', "$logger");
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$memberid,'warning');
      }

      if($debug == 1) {
        var_dump($r);
      }
      // ---------------------------------------------------------------------------------


    }elseif($mg2gpk_balance < 0){
      // ---------------------------------------------------------------------------------
      // $mg2gpk_balance < 0; 從娛樂城輸錢
      // ---------------------------------------------------------------------------------

			// 判斷是否為測試環境, 如是則必需用DB中gtoken的值加上玩家的變化餘額才是真正的錢包餘額
			//if ($config['casino_transfer_mode'] == 2) {
				// 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
				$wallets_sql = "SELECT gtoken_balance,casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '" . $d['destination_transfer_id'] . "';";
				//var_dump($wallets_sql);
				$wallets_result = runSQLall($wallets_sql);
				//var_dump($wallets_result);
				// 在剛取出的 wallets 資料庫中的mg餘額(支出)
				$gtoken_mg_balance_db = round($wallets_result[1]->mg_balance, 2);
				// 在剛取出的 wallets 資料庫中的gtoken餘額(支出)
				$gtoken_balance_db = round($wallets_result[1]->gtoken_balance, 2);
				// 派彩 = 娛樂城餘額 - 本地端PT支出餘額
				$gtoken_balance = round(($gtoken_balance_db + $gtoken_mg_balance_db + $mg2gpk_balance), 2);
			//} else {
			//	$gtoken_balance = $mg_balance_api;
			//}

      // 交易開始
  		$mg2gpk_transaction_sql = 'BEGIN;';
      // 存款金額 -- 娛樂城餘額
      $d['deposit']           = $gtoken_balance;
      // 提款金額 -- 本地端支出
      $d['withdrawal']        = $mg_balance_db;
      // 操作者
      $d['member_id']         = $memberid;
      // MG + 代幣派彩
      $d['summary']           = 'MG'.$transaction_category['tokenpay'];
      // 稽核方式
      $d['auditmode']         = $auditmode_select['mg'];
      // 稽核金額 -- 派彩無須稽核
      $d['auditmodeamount']   = 0;
      // MG 取回的餘額為真錢
      $d['realcash'] = 2;
      // 交易類別 MG + $transaction_category['tokenpay']
      $d['transaction_category'] = 'tokenpay';
      // 變化的餘額
      $d['balance']       = abs($mg2gpk_balance);
      // var_dump($d);

      // 操作 root_member_wallets DB, 把 mg_balance 設為 0 , 把 gtoken_lock = null, 把 gtoken_balance = $d['deposit']
      // 錢包存入 餘額 , 把 mg_balance 扣除全部表示支出(投注).
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = ".$d['deposit'].", gtoken_lock = NULL,casino_accounts= jsonb_set(casino_accounts,'{\"MG\",\"balance\"}','0') WHERE id = '".$d['destination_transfer_id']."'; ";
      // 目的帳號上的註記
      $d['destination_notes']  = '(會員收到MG派彩'.$mg2gpk_balance.' by 客服人員 '.$_SESSION['agent']->account.')';
      // 針對目的會員的存簿寫入，
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '".$d['deposit']."', '".$d['withdrawal']."', '".$d['destination_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['destination_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['destination_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['destination_transfer_id'].") );";

      // 針對來源出納的存簿寫入
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql."
      UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (gtoken_balance + " . $d['balance'] .") WHERE id = '" . $d['source_transfer_id'] . "'; ";
      // 來源帳號上的註記
      $d['source_notes']  = '(出納帳號 '.$d['source_transferaccount'].' 從會員 '.$d['destination_transferaccount'].' 取回派彩餘額)';
      $mg2gpk_transaction_sql = $mg2gpk_transaction_sql.
      'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "auditmode", "auditmodeamount", "realcash", "destination_transferaccount", "transaction_category", "balance")'.
      "VALUES ('now()', '".$d['balance']."', '0', '".$d['source_notes']."', '".$d['member_id'] ."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."', '".$d['auditmode']."', '".$d['auditmodeamount']."', '".$d['realcash']."', ".
      "'".$d['source_transferaccount']."','".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$d['source_transfer_id']."));";

      // commit 提交
  		$mg2gpk_transaction_sql = $mg2gpk_transaction_sql.'COMMIT;';
      if($debug == 1) {
  		  echo '<p>SQL='.$mg2gpk_transaction_sql.'</p>';
      }

      // 執行 transaction sql
  		$mg2gpk_transaction_result = runSQLtransactions($mg2gpk_transaction_sql);
  		if($mg2gpk_transaction_result){
        $logger = '从MG帐号'.$member_mg_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'，共计派彩='.$mg2gpk_balance;
        $r['ErrorCode']     = 1;
        $r['ErrorMessage']  = $logger;
        memberlog2db($memberaccount,'mggame','info', "$logger");
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$memberid,'info');
        // echo "<p> $logger </p>";
  		}else{
        //5.1 ~ 5.3 必須一定要全部成功，才算成功。如果 5.1 成功後，但 5.2,5.3 失敗的話，紀錄為 ERROR LOG，需要通知管理員處理。(message system)
        $logger = '从MG帐号'.$member_mg_account.'取回余额到游戏币，统计后收入='.$mg_balance_api.'，支出='.$mg_balance_db.'，共计派彩='.$mg2gpk_balance;
        $logger = $logger.'但资料库处理错误，请通知客服人员处理。';
        $r['ErrorCode']     = 406;
        $r['ErrorMessage']  = $logger;
        memberlog2db($memberaccount,'mggame','error', "$logger");
        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$memberid,'warning');
        // echo "<p> $logger </p>";
      }
      // var_dump($r);
      // ---------------------------------------------------------------------------------
    }else{
      // 不可能
      $logger = '不可能发生';
      $r['ErrorCode']     = 500;
      $r['ErrorMessage']  = $logger;
      memberlog2db($memberaccount,'mggame','error', "$logger");
      echo "<p> $logger </p>";
    }

    return($r);
  }
  // ---------------------------------------------------------------------------------
  // 針對 db 的處理函式，只針對此功能有用。 END
  // ---------------------------------------------------------------------------------



  // ---------------------------------------------------------------------------------
  // 取回 MG Casino 的餘額 -- retrieve_mg_restful_casino_balance
  // 不能單獨使用，需要搭配 db_mg2gpk_balance 使用
  // ---------------------------------------------------------------------------
  // Features:
  // Usage:
  // Input:
  // Return:
  // ---------------------------------------------------------------------------
  function retrieve_mg_restful_casino_balance($memberid, $debug=0) {
    //$debug=1;
    global $gtoken_cashier_account;
    // $memberid
    // $memberid = $memberid;

    // 判斷會員是否 status 是否被鎖定了!!
    $member_sql = "SELECT * FROM root_member WHERE id = '".$memberid."' AND status = '1';";
    $member_result = runSQLall($member_sql);

    // 先取得當下的  member_wallets 變數資料,等等 sql 更新後. 就會消失了。
    $wallets_sql = "SELECT gtoken_balance,gtoken_lock,
		        casino_accounts->'MG'->>'account' as mg_account,
		        casino_accounts->'MG'->>'password' as mg_password,
		        casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '".$memberid."';";
    $wallets_result = runSQLall($wallets_sql);
    if($debug == 1){
      var_dump($wallets_sql);
      var_dump($wallets_result);
    }

    // 1. 查詢 DB 的 gtoken_lock  是否有紀錄在 MG 帳戶，NULL 沒有紀錄的話表示沒有餘額在 MG 帳戶。(已經取回了，代幣一次只能對應一個娛樂城)
    // 2. AND 當 DB 有 mg_balance 的時候才動作, 如果沒有則結束。表示 db 帳號資料有問題。
    // -----------------------------------------------------------------------------------
    if($member_result[0] == 1 AND $wallets_result[0] == 1 AND $wallets_result[1]->mg_account != NULL AND $wallets_result[1]->gtoken_lock == 'MG' ) {
			$memberaccount = $member_result[1]->account;
			$memberid = $member_result[1]->id;
			$member_mg_account = $wallets_result[1]->mg_account;

      // 4. Y , gtoken 紀錄為 MG , API 檢查 MG 的餘額有多少
      // -----------------------------------------------------------------------------------
      // $delimitedAccountNumbers = $member_mg_account;
      $delimitedAccountNumbers[] = $wallets_result[1]->mg_account;
      $MG_API_data  = array(
        'Accounts' => $delimitedAccountNumbers
      );
      if($debug == 1){
        var_dump($MG_API_data);
      }
      $MG_API_result = mg_restfulapi('GetAccountBalance', 0, $MG_API_data);
      if($MG_API_result['errorcode'] == 0 AND $MG_API_result['Status']->ErrorCode == 0 AND $MG_API_result['count'] > 0){
        // 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。
        //echo '4. 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。';
         //var_dump($MG_API_result);
        // 取得的 MG API 餘額 , 保留小數第二位 round( $x, 2);
        $mg_balance_api = round($MG_API_result['Result']['0']->Balance,2);
        $logger = 'MG API 查询余额为'.$MG_API_result['Result']['0']->Balance.'操作的余额为'.$mg_balance_api;
        $r['code']     = 1;
        $r['messages']  = $logger;
        // echo "<p> $logger </p>";
        // -----------------------------------------------------------------------------------

        // 5. 承接 4 ,如果 MG餘額 > 0
        // -----------------------------------------------------------------------------------
        if($mg_balance_api > 0) {
          //5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3
          // 動作： Withdrawal 帳戶取款
          $MG_API_data  = array(
            'AccountNumber' => $wallets_result[1]->mg_account,
            'Amount' => "$mg_balance_api" ,
            'TransactionReferenceNumber' => 'mg0Withdrawal0'.date("Ymdhis")
          );

          if($debug == 1) {
            echo '5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3';
            var_dump($MG_API_data);
          }

          $MG_API_result = mg_restfulapi('Withdrawal', 0, $MG_API_data);
          if($MG_API_result['errorcode'] == 0 AND $MG_API_result['Status']->ErrorCode == 0 AND $MG_API_result['count'] > 0){
            // 取回MG餘額成功
            $logger = 'MG API 从帐号'.$wallets_result[1]->mg_account.'取款余额'.$MG_API_result['Result']->TransactionAmount.'成功。交易编号为'.$MG_API_result['Result']->ConfirmationNumber;
            $r['code']     = 100;
            $r['messages']  = $logger;
            memberlog2db($memberaccount,'mggame','info', "$logger");
	        member_casino_transferrecords('MG','lobby',$mg_balance_api,$logger,$memberid,'success', $MG_API_result['Result']->ConfirmationNumber, 1);

            if($debug ==1) {
              echo "<p> $logger </p>";
              var_dump($MG_API_result);
            }
            // 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
            // -----------------------------------------------------------------------------------
            $wallets_sql = "SELECT casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '".$memberid."';";
            //var_dump($wallets_sql);
            $wallets_result = runSQLall($wallets_sql);
            //var_dump($wallets_result);
            // 在剛取出的 wallets 資料庫中的餘額(支出)
            $mg_balance_db = round($wallets_result[1]->mg_balance,2);
            // 派彩 = 娛樂城餘額 - 本地端MG支出餘額
            $mg2gpk_balance = round(($mg_balance_api - $mg_balance_db),2);
            // -----------------------------------------------------------------------------------

            // 處理 DB 的轉帳問題 -- 5.2 and 5.3
            $db_mg2gpk_balance_result = db_mg2gpk_balance($memberaccount,$memberid,$member_mg_account,$gtoken_cashier_account, $mg_balance_api, $mg2gpk_balance, $mg_balance_db);
            if($db_mg2gpk_balance_result['ErrorCode'] == 1) {
              $r['code']     = 1;
              $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
              $logger = $r['messages'];
              memberlog2db($memberaccount,'mg2gpk','info', "$logger");
            }else{
              $r['code']     = 523;
              $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
              $logger = $r['messages'];
              memberlog2db($memberaccount,'mg2gpk','error', "$logger");
            }

            if($debug ==1) {
              echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
              var_dump($db_mg2gpk_balance_result);
            }
          }else{
            //5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3
            $logger = 'MG API 从帐号'.$member_mg_account.'取款余额'.$mg_balance_api.'失败';
            $r['code']     = 405;
            $r['messages']  = $logger;
            memberlog2db($memberaccount,'mggame','error', "$logger");

            if($debug ==1) {
              echo "5.1 執行 MG API 取回 MG 餘額 ，到 totle egame 的出納帳戶(API操作) , 成功才執行 5.2,5.3";
              echo "<p> $logger </p>";
              var_dump($r);
            }
          }

        }elseif($mg_balance_api == 0) {
          $logger = 'MG余额 = 0 ，MG没有余额，无法取回任何的余额，将余额转回 GPK。';
          $r['code']     = 406;
          $r['messages']  = $logger;
          memberlog2db($memberaccount,'mggame','info', "$logger");
          member_casino_transferrecords('MG','lobby','0',$logger,$memberid,'success');

          // 先取得當下的  wallets 變數資料,等等 sql 更新後. 就會消失了。
          // -----------------------------------------------------------------------------------
          $wallets_sql = "SELECT casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '".$memberid."';";
          //var_dump($wallets_sql);
          $wallets_result = runSQLall($wallets_sql);
          //var_dump($wallets_result);
          // 在剛取出的 wallets 資料庫中的餘額(支出)
          $mg_balance_db = round($wallets_result[1]->mg_balance,2);
          // 派彩 = 娛樂城餘額 - 本地端MG支出餘額
          $mg2gpk_balance = round(($mg_balance_api - $mg_balance_db),2);
          // -----------------------------------------------------------------------------------

          // 處理 DB 的轉帳問題 -- 5.2 and 5.3
          $db_mg2gpk_balance_result = db_mg2gpk_balance($memberaccount,$memberid,$member_mg_account,$gtoken_cashier_account, $mg_balance_api, $mg2gpk_balance, $mg_balance_db);
          if($db_mg2gpk_balance_result['ErrorCode'] == 1) {
            $r['code']     = 1;
            $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
            $logger = $r['messages'];
            memberlog2db($memberaccount,'mg2gpk','info', "$logger");
          }else{
            $r['code']     = 523;
            $r['messages']  = $db_mg2gpk_balance_result['ErrorMessage'];
            $logger = $r['messages'];
            memberlog2db($memberaccount,'mg2gpk','error', "$logger");
          }

          if($debug ==1) {
            echo '處理 DB 的轉帳問題 -- 5.2 and 5.3';
            var_dump($db_mg2gpk_balance_result);
          }

        }else{
          // MG餘額 < 0 , 不可能發生
          $logger = 'MG余额 < 1 ，不可能发生。';
          $r['code']     = 404;
          $r['messages']  = $logger;
        }
        // -----------------------------------------------------------------------------------
      }else{
        // 4. Y , gtoken 紀錄為 MG , API 檢查 MG 的餘額有多少
        $logger = 'MG API 查询余额失败，系统维护中请晚点再试。';
        $r['code']     = 403;
        $r['messages']  = $logger;
				member_casino_transferrecords('MG','lobby','0',$logger.'('.$MG_API_result['Result'].')',$memberid,'fail');
        if($debug ==1) {
          var_dump($MG_API_result);
        }
      }
      // -----------------------------------------------------------------------------------
    }else{
      // 1. 查詢 session 的 gtoken_lock  是否有紀錄在 MG 帳戶，NULL 沒有紀錄的話表示沒有餘額在 MG 帳戶。
      // 2. AND 當 session 有 mg_balance 的時候才動作, 如果沒有則結束。表示 db 帳號資料有問題。
      $logger = '没有余额在 MG 帐户 OR DB 帐号资料有问题 ';
      $r['code']     = 401;
      $r['messages']  = $logger;
			member_casino_transferrecords('MG','lobby','0',$logger,$memberid,'fail');
    }

    if($debug ==1) {
      echo "<p> $logger </p>";
      var_dump($r);
    }
		if($r['code'] == 1){
			unset($_SESSION['wallet_transfer']);
		}

    return($r);
  }
  // -----------------------------------------------------------------------------------

	// ---------------------------------------------------------------------------------
	// 取得會員目前在 MG Casino 的餘額
	// ---------------------------------------------------------------------------------
	function getbalance_mg($memberid, $debug=0){
		$mg_balance_api = '';

		// 判斷會員是否 status 是否被鎖定了!!
		$member_sql = "SELECT * FROM root_member WHERE id = '".$memberid."' AND status = '1';";
    $member_result = runSQLall($member_sql);

    // 先取得當下的  member_wallets 變數資料
    $wallets_sql = "SELECT gtoken_balance,gtoken_lock,
		        casino_accounts->'MG'->>'account' as mg_account,
		        casino_accounts->'MG'->>'password' as mg_password,
		        casino_accounts->'MG'->>'balance' as mg_balance FROM root_member_wallets WHERE id = '".$memberid."';";
    $wallets_result = runSQLall($wallets_sql);
    if($debug == 1){
      var_dump($wallets_sql);
      var_dump($wallets_result);
    }

    if($member_result[0] == 1 AND $wallets_result[0] == 1 AND $wallets_result[1]->mg_account != NULL) {

      // 查詢在 casino 的餘額
      $delimitedAccountNumbers[] = $wallets_result[1]->mg_account;
      $MG_API_data  = array(
        'Accounts' => $delimitedAccountNumbers
      );
      if($debug == 1){
        var_dump($MG_API_data);
      }
      $MG_API_result = mg_restfulapi('GetAccountBalance', 0, $MG_API_data);
      if($MG_API_result['errorcode'] == 0 AND $MG_API_result['Status']->ErrorCode == 0 AND $MG_API_result['count'] > 0){
        // 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。
        //echo '4. 查詢餘額動作，成立後執行. 失敗的話結束，可能網路有問題。';
         //var_dump($MG_API_result);
        // 取得的 MG API 餘額 , 保留小數第二位 round( $x, 2);
        $mg_balance_api = round($MG_API_result['Result']['0']->Balance,2);
			}
		}
		return $mg_balance_api;
	}
	// ---------------------------------------------------------------------------------


?>
