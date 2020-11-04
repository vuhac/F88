<?php
// ----------------------------------------------------------------------------
// Features:	代理商後台， ajax 動作的處理 人工現金轉代幣及設定
// 登入、登出
// File Name:	member_gcash2gtoken_acton.php
// Author:		Barkley
// Related:   member_gcash2gtoken.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/member_lib.php";


if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}
// var_dump($_SESSION);
// var_dump($_POST);
//var_dump($_GET);

// ----------------------------------
// 本程式使用的 function
// ----------------------------------




// 檢查函式，檢查帳號是否存在，且合法可以使用。
// return: 1 --> valid   , 0 --> no valid , 2 ~ n --> other
// usage: check_account_available($transferaccount)
// -----------------------------------------------------------
function check_account_available($transferaccount) {

  if($transferaccount == ''){
    $check_return['messages'] = '帳號沒有帶入，尚無資料';
    $check_return['code'] = 3;
  }else{
    //var_dump($destination_transferaccount_input);
    $sql = "SELECT * FROM root_member WHERE status = '1' AND account = '".$transferaccount."';";
    $r = runSQLall($sql);
    // var_dump($r);

    // 如果登入者身份，存在系統內的話。
    if($r[0] == 1) {
      $transferaccount_input = $r[1]->account;
      $check_return['messages'] =  '轉入帳號 '.$transferaccount_input.' 存在可以使用';
      $check_return['code'] = 1;
    }else{
      $check_return['messages'] =  '無此帳號 '.$transferaccount_input;
      $check_return['code'] = 0;
    }
  }

  return($check_return);
}


// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------
if($action == 'member_gcash2gtoken' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // ----------------------------------------------------------------------------
  // 此為管理員或允許的客服人員進行人工將客戶的現金轉代幣的工作
  // ----------------------------------------------------------------------------


  // ----------------------------------------------------------------------------
  // Features:
  //   自動 GCASH TO GTOKEN
  //   將會員本人的 GCASH 餘額，依據設定值轉換為 GTOKEN 餘額。
  //   當 gtoken_lock 不是 null 的時候, 不可以轉帳.
  // Usage:
  //   auto_gcash2gtoken($member_id, $gcash2gtoken_account, $balance_input, $pwd_verify_sha1, $debug=0, $system_note_input)
  // Input:
  //   $member_id --> 會員ID 同時也是操作者 ID 也是轉帳人員
  //   $gcash2gtoken_account --> 指定轉帳帳號
  //   $balance_input --> 轉帳金額
  //   $pwd_verify_sha1 --> 會員的提款密碼
  //   debug = 1 --> 進入除錯模式
  //   debug = 0 --> 關閉除錯
  //   $system_note_input --> 備註新增
  // Return:
  //   code = 1  --> 成功
  //   code != 1  --> 其他原因導致失敗
  // Releated:
  //   後台 member_gcash2gtoken_action.php
  //   前台 lobby_casino_lib.php
  //   前台測試工具 test_unit.php , 直接引用此 lib
  //   使用到這個 lib , 如果修正的話, 需要兩個檔案一起修正
  // Log:
  //   by barkley 2017.5.7
  // ----------------------------------------------------------------------------
  function auto_gcash2gtoken($member_id, $gcash2gtoken_account, $balance_input, $pwd_verify_sha1, $debug=0, $system_note_input=NULL) {

    // 交易的變數 default
    global $transaction_category;
    // 系統現金出納
    global $gcash_cashier_account;
    // 系統代幣出納
    global $gtoken_cashier_account;
    global $config;

    // check account是否存在, 存在才繼續。
    //$check_acc_sql = "SELECT * FROM root_member WHERE status = '1' AND id = '".$member_id."';";
    // $check_acc_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '$member_id' AND root_member.status = '1';";
    $check_acc_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '$gcash2gtoken_account' AND root_member.status = '1';";
    $check_acc = runSQLall($check_acc_sql);
    if($debug == 1) {
      var_dump($check_acc);
      var_dump($member_id);
      var_dump($balance_input);
      var_dump($pwd_verify_sha1);
    }

    if($check_acc[0] == 1){
      $check_therole_result = (object)check_member_therole($check_acc['1']);
      if (!$check_therole_result->status) {
        $error_mag = $check_therole_result->result;
        echo '<script>alert("'.$error_mag.'");</script>';
        die();
      }

      // 帳號正確
      $error['code'] = '1';
      $error['messages'] = '帐号正确';

      // 如果代幣錢包沒有被鎖定, 才可以進行現金轉代幣 by barkley 2017.5.7
      // if($check_acc[1]->gtoken_lock == NULL) {

        // 轉帳操作人員
        $d['member_id']                   = $member_id;
        // 來源帳號
        $d['source_transferaccount']      = $check_acc[1]->account;
        // 目的轉帳帳號 = 來源帳號 , 同一個人的帳號
        $d['destination_transferaccount'] = $check_acc[1]->account;
        // 轉帳金額，需要依據會員等級限制每日可轉帳總額。如果不小心被輸入浮點數了，就取整數部位。
        $d['transaction_money']           = round($balance_input,2);
        // 真實轉換, 其實在這個程式還找不到此欄位定義定位。
        $d['realcash']                    = 1;
        // 摘要資訊
        $d['summary']                     = $transaction_category['cashgtoken'];
        // 交易類別 -- ref in config.php
        $d['transaction_category']        = 'cashgtoken';
        // 來源帳號的密碼驗證，驗證後才可以存款
        $d['password_verify_sha1']        = $pwd_verify_sha1;
        // 系統轉帳文字資訊
        $d['system_note_input']           = $system_note_input;

        // check 轉帳密碼是否正確和登入者的轉帳管理員密碼一樣 , 避免 api 被 xss 直接攻擊, 加上密碼稽核.
        // 如果是管理員操作的話, 使用 5566bypass 為預設密碼.
        if($d['password_verify_sha1'] == $check_acc[1]->withdrawalspassword OR $d['password_verify_sha1'] == '5566bypass') {
          // correct
          $error['code'] = '1';
          $error['messages'] = '转帐密码正确';

          // 轉帳 gtoken 的動作

          // 0. 取得目的端使用者完整的資料
          $destination_transferaccount_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$d['destination_transferaccount']."';";
          $destination_transferaccount_result = runSQLALL($destination_transferaccount_sql);
          //var_dump($destination_transferaccount_result);
          if($destination_transferaccount_result[0] == 1){
            // 1. 取得來源端使用者完整的資料
            $error['code'] = '1';
            $error['messages'] = '取得来源端使用者完整的资料';

            $source_transferaccount_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$d['source_transferaccount']."';";
            $source_transferaccount_result = runSQLALL($source_transferaccount_sql);
            //var_dump($source_transferaccount_result);
            if($source_transferaccount_result[0] == 1){
              // 2. 檢查帳戶 $source_transferaccount GCASH 是否有錢,且大於 $transaction_money , 成立才工作,否則結束
              if($source_transferaccount_result[1]->gcash_balance >= $d['transaction_money']){
                $error['code'] = '1';
                $error['messages'] = $d['source_transferaccount'].' 现金(GCASH)有余额，且大于'.$d['transaction_money'];

                // 來源ID $source_transferaccount_result[1]->id
                // 目的ID $destination_transferaccount_result[1]->id

                // 稽核判斷寫入 notes 的文字 , and 控制稽核金額
                // 存款稽核 * 1 倍
                $d['auditmode_select'] = 'depositaudit';
                // 稽核金額 * 1 倍
                $d['auditmode_amount'] = $d['transaction_money'];
                $audit_notes = '稽核金额'.$d['auditmode_amount'];

                // 取得現金出納及代幣出納的 ID , 及檢查
                // 現金出納 ID
                $gcash_cashier_account_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$gcash_cashier_account."';";
                $gcash_cashier_account_result = runSQLall($gcash_cashier_account_sql);
                // 代幣出納 ID
                $gtoken_cashier_account_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.account = '".$gtoken_cashier_account."';";
                $gtoken_cashier_account_result = runSQLall($gtoken_cashier_account_sql);
                // var_dump($gcash_cashier_account_result);
                // var_dump($gtoken_cashier_account_result);
                if($gcash_cashier_account_result[0] == 1 AND $gtoken_cashier_account_result[0] == 1 ){
                  // 檢查現金及代幣的餘額是否大於 0
                  if($gcash_cashier_account_result[1]->gcash_balance > 0  AND $gtoken_cashier_account_result[1]->gtoken_balance > 0) {
                    $gcash_cashier_account_id   = $gcash_cashier_account_result[1]->id;
                    $gtoken_cashier_account_id  = $gtoken_cashier_account_result[1]->id;
                    //var_dump($gcash_cashier_account_id);
                    //var_dump($gtoken_cashier_account_id);
                  }else{
                    $error['code'] = '532';
                    $error['messages'] = '系统现金帐号或是出纳帐号的余额没了，请联络客服人员处理。';
                    echo '<p align="center"><button type="button" class="btn btn-danger">'.$error['messages'].'</button></p>'.'<script>alert("'.$error['messages'].'");</script>';
                    return($error);
                    // die();
                  }
                }else{
                  $error['code'] = '531';
                  $error['messages'] = '现金帐号或是出纳帐号的取得有问题，请联络客服人员处理。';
                  echo '<p align="center"><button type="button" class="btn btn-danger">'.$error['messages'].'</button></p>'.'<script>alert("'.$error['messages'].'");</script>';
                  return($error);
                  // die();
                }

                // 交易開始
                // ----------------------------------------------------------------
                // * 將 GCASH 轉 $$ 到 GTOKEN
                // (A) 交易動作為  使用者的 gcash $$ to 系統現金出納
                // (B) 交易動作為  系統的代幣出納 to $$ 到使用者的 gtoken
                // ----------------------------------------------------------------
                $transaction_money_sql = 'BEGIN;';
                // ----------------------------------------------------------------
                // (A) 交易動作為  使用者的 gcash $$ to 系統現金出納
                // ----------------------------------------------------------------
                // 操作：root_member_wallets
                // 會員gcash帳號餘額刪除 transaction_money
                $transaction_money_sql = $transaction_money_sql.
                'UPDATE root_member_wallets SET changetime = NOW(), gcash_balance = (SELECT (gcash_balance-'.$d['transaction_money'].') as amount FROM root_member_wallets WHERE id = '.$source_transferaccount_result[1]->id.') WHERE id = '.$source_transferaccount_result[1]->id.';';
                // 目的(系統出納)帳號加入上 transaction_money 餘額
                $transaction_money_sql = $transaction_money_sql.
                'UPDATE root_member_wallets SET changetime = NOW(), gcash_balance = (SELECT (gcash_balance+'.$d['transaction_money'].') as amount FROM root_member_wallets WHERE id = '.$gcash_cashier_account_id.') WHERE id = '.$gcash_cashier_account_id.';';

                // 操作：root_member_gcashpassbook
                // PGSQL 新增 1 筆紀錄 帳號 source_transferaccount 轉帳到 $gcash_cashier_account_id 金額 transaction_money
                // 給會員看的紀錄
                $source_notes = "(帐号".$d['source_transferaccount']." 现金转到同帐号代币)";
                $transaction_money_sql = $transaction_money_sql.
                'INSERT INTO "root_member_gcashpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")'.
                "VALUES ('now()', '0', '".$d['transaction_money']."', '".$source_notes."', '".$d['member_id']."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."','".$d['realcash']."', '".$gcash_cashier_account."', '".$d['transaction_category']."', (SELECT gcash_balance FROM root_member_wallets WHERE id = ".$source_transferaccount_result[1]->id.") );";

                // PGSQL 新增 1 筆紀錄 帳號 destination_transferaccount 收到來自 source_transferaccount 金額 transaction_money
                // 給系統出納看的紀錄
                $destination_notes = "(帐号".$d['source_transferaccount']."转帐到".$gcash_cashier_account."帐号, ".$audit_notes.')'.$d['system_note_input'];
                $transaction_money_sql = $transaction_money_sql.
                'INSERT INTO "root_member_gcashpassbook" ("transaction_time", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")'.
                "VALUES ('now()', '".$d['transaction_money']."', '0', '".$destination_notes."', '".$d['member_id']."', '".$config['currency_sign']."', '".$d['summary']."', '".$gcash_cashier_account."', '".$d['realcash']."', '".$d['source_transferaccount']."', '".$d['transaction_category']."', (SELECT gcash_balance FROM root_member_wallets WHERE id = ".$gcash_cashier_account_id.") );";

                // ----------------------------------------------------------------
                // (B) 交易動作為  系統的代幣出納 to $$ 到使用者的 gtoken
                // ----------------------------------------------------------------
                // 操作：root_member_wallets
                // 系統出納 gtoken 帳號餘額刪除 transaction_money
                $transaction_money_sql = $transaction_money_sql.
                'UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (SELECT (gtoken_balance-'.$d['transaction_money'].') as amount FROM root_member_wallets WHERE id = '.$gtoken_cashier_account_id.') WHERE id = '.$gtoken_cashier_account_id.';';
                // 會員 gtoken 帳號加入上 transaction_money 餘額
                $transaction_money_sql = $transaction_money_sql.
                'UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = (SELECT (gtoken_balance+'.$d['transaction_money'].') as amount FROM root_member_wallets WHERE id = '.$source_transferaccount_result[1]->id.') WHERE id = '.$source_transferaccount_result[1]->id.';';

                // 操作：root_member_gtokenpassbook
                // PGSQL 新增 1 筆紀錄 帳號 $gcash_cashier_account_id 轉帳到 source_transferaccount 金額 transaction_money (GTOKEN)
                // 給會員看的
                $source_notes = "(帐号".$d['source_transferaccount'].'现金转代币, '.$audit_notes.')'.$d['system_note_input'];
                $transaction_money_sql = $transaction_money_sql.
                'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "auditmode", "auditmodeamount", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")'.
                "VALUES ('now()', '".$d['auditmode_select']."', '".$d['auditmode_amount']."', '".$d['transaction_money']."', '0', '".$source_notes."', '".$d['member_id']."', '".$config['currency_sign']."', '".$d['summary']."', '".$d['source_transferaccount']."','".$d['realcash']."', '".$gtoken_cashier_account."', '".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$source_transferaccount_result[1]->id.") );";
                // PGSQL 新增 1 筆紀錄 帳號 destination_transferaccount 收到來自 source_transferaccount 金額 transaction_money
                // 給代幣出納人員看的
                $destination_notes = "(帐号".$gtoken_cashier_account."存款到,".$d['source_transferaccount'].$audit_notes.')'.$d['system_note_input'];
                $transaction_money_sql = $transaction_money_sql.
                'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "auditmode", "auditmodeamount", "deposit", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount",  "realcash", "destination_transferaccount", "transaction_category", "balance")'.
                "VALUES ('now()', '".$d['auditmode_select']."', '".$d['auditmode_amount']."', '0', '".$d['transaction_money']."', '".$destination_notes."', '".$d['member_id']."', '".$config['currency_sign']."', '".$d['summary']."', '".$gtoken_cashier_account."', '".$d['realcash']."', '".$d['source_transferaccount']."', '".$d['transaction_category']."', (SELECT gtoken_balance FROM root_member_wallets WHERE id = ".$gtoken_cashier_account_id.") );";

                // commit 提交
                $transaction_money_sql = $transaction_money_sql.'COMMIT;';

                if($debug==1) {
                  echo '<pre>';
                  print_r($transaction_money_sql);
                  echo '</pre>';
                }


                // echo '<p>'.$transaction_money_sql.'</p>';
                // 執行 transaction sql
                $transaction_money_result = runSQLtransactions($transaction_money_sql);
                // $transaction_money_result = 0;
                if($transaction_money_result){
                  $error['code'] = '1';
                  $transaction_money_html = money_format('%i', $d['transaction_money']);
                  $error['messages'] = '成功将'.$d['source_transferaccount'].'帐号 GCASH 转换为 GTOKEN 金额:'.$transaction_money_html;
                }else{
                  $error['code'] = '7';
                  $error['messages'] = 'SQL转帐失败从'.$d['source_transferaccount'].'到'.$d['destination_transferaccount'].'金額'.$d['transaction_money'];;
                }
                // to exit
              }else{
                $error['code'] = '6';
                $error['messages'] = $d['source_transferaccount'].'余额不足于'.$d['transaction_money'];
              }

            }else{
              $error['code'] = '4';
              $error['messages'] = '查不到来源端的使用者'.$d['source_transferaccount'].'资料。';
            }

          }else{
            $error['code'] = '5';
            $error['messages'] = '查不到目的端的使用者'.$d['destination_transferaccount'].'资料。';
          }

        }else{
          // incorrect
          $error['code'] = '3';
          $error['messages'] = $d['source_transferaccount'].'来源帐号的转帐密码不正确';
        }

      // }else{
      //   $error['code'] = '505';
      //   $error['messages'] = '代币钱包被锁定在'.$check_acc[1]->gtoken_lock.'请先取回娱乐城的钱包';
      // }

    }else{
      // error return
      $error['code'] = '2';
      $error['messages'] = '帐号有问题'.$check_acc[1]->account;
    }

    if($debug == 1){
        var_dump($error);
    }

    return($error);
  }
  // ---------------------------------------------------------------------------
  // END 現金轉代幣 auto_gcash2gtoken()
  // ---------------------------------------------------------------------------

  // var_dump($_POST);
  // 需要有管理員密碼, 才可以執行
  if($_SESSION['agent']->therole == 'R' AND $_POST['password_input'] == $_SESSION['agent']->passwd) {
    global $config;
    // 只有管理員身份，才可以幫使用者轉帳，使用來自 post 的來源 data

    // 轉帳操作人員
    $d['member_id']                   = $_SESSION['agent']->id;
    $d['source_transferaccount']      = filter_var($_POST['source_transferaccount_input'], FILTER_SANITIZE_STRING);
    $d['system_note_input']           = filter_var($_POST['system_note_input'], FILTER_SANITIZE_STRING);
    // 目的轉帳帳號 = 來源帳號 , 同一個人的帳號
    $d['destination_transferaccount'] = $d['source_transferaccount'];

    // 轉帳金額，需要為整數型態，才可以繼續. 浮點數要過濾，非法字串也要過濾。
    if (!filter_var($_POST['balance_input'], FILTER_VALIDATE_INT) === false) {
      // echo("轉帳金額 is an integer");
      $d['transaction_money']           = round($_POST['balance_input'],2);
    } else {
      $error['code'] = '521';
      $error['messages'] = '轉換金額非數字金額，請修正';
      echo '<p align="center"><button type="button" class="btn btn-danger">'.$error['messages'].'</button></p>'.'<script>alert("'.$error['messages'].'");</script>';
      die();
      // 中止
    }

    // 移到config
    // $pwd_verify_sha1 = '5566bypass';

    // 執行現金轉代幣
    // 原本
    // $gcash2gtoken_result = auto_gcash2gtoken($d['member_id'], $d['source_transferaccount'], $d['transaction_money'], $pwd_verify_sha1, $debug=0, $d['system_note_input']);

    // 20191017
    $gcash2gtoken_result = auto_gcash2gtoken($d['member_id'], $d['source_transferaccount'], $d['transaction_money'], $config['pwd_verify_sha1'], $debug=0, $d['system_note_input']);
    //  var_dump($gcash2gtoken_result);

    $error['code'] = $gcash2gtoken_result['code'];
    $error['messages'] = $gcash2gtoken_result['messages'];
    if($error['code'] == 1){
      echo '<p align="center"><button type="button" class="btn btn-success" onClick="window.location.reload();">'.$error['messages'].'</button></p>'.'<script>alert("'.$error['messages'].'");</script>';
    }else{
      echo '<p align="center"><button type="button" class="btn btn-danger" onClick="window.location.reload();">'.$error['messages'].'</button></p>'.'<script>alert("'.$error['messages'].'");</script>';
    }

  }else{
    $error['code'] = '404';
    $error['messages'] = '管理員帳號密碼錯誤 ，請重新輸入。';
    echo '<p align="center"><button type="button" class="btn btn-danger" onClick="window.location.reload();">'.$error['messages'].'</button></p>'.'<script>alert("'.$error['messages'].'");</script>';
  }


// --------------------------------------------------------------------------------
}elseif($action == 'tokenautostart' AND isset($_SESSION['agent']) AND ($_SESSION['agent']->therole == 'C' OR $_SESSION['agent']->therole == 'R') ) {
// --------------------------------------------------------------------------------
  // 	最低自動轉帳餘額 auto_min_gtoken
  //var_dump($_POST);
  // PK
  $pk         = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  // 欄位
  $name       = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
  // 最低自動轉帳餘額
  $value      = filter_var(floor($_POST['value']), FILTER_SANITIZE_NUMBER_INT);

  $sql = "SELECT * FROM root_member_wallets WHERE id = '$pk';";
  $result = runSQLall($sql);
  //var_dump($result);
  if($result[0] == 1 and $name == 'tokenautostart') {
    // 最低自動轉帳餘額, 不可以小於 1 元 且 不可以大於每次儲值金額
    if(($result[1]->auto_once_gotken > $value) AND ($value >= 1)){
      // 符合 , 將 最低自動轉帳餘額,  資料寫入資料庫. 合成 sql 時選擇越少sql漏洞越少
      $wsql = "UPDATE root_member_wallets SET changetime = NOW(), auto_min_gtoken = '$value' WHERE id = '$pk';";
      //var_dump($wsql);
      $wresult = runSQLall($wsql);
      if($wresult[0] == 1) {
        $error['code'] = 100;
        $error['messages'] = '最低自動轉帳餘額更新為 '.$config['currency_sign'].' $value 完成';
      }else{
        $error['code'] = 403;
        $error['messages'] = '資料庫更新失敗';
      }
    }else{
      $error['code'] = 402;
      $error['messages'] = '最低自動轉帳餘額, 不可以小於 '.$config['currency_sign'].' 1 元 且 不可以大於每次儲值金額';
    }
  }else{
    $error['code'] = 401;
    $error['messages'] = '資料庫存取錯誤';
  }

  // var_dump($error);
  if($error['code'] == 100) {
    echo '<p align="center"><button type="button" class="btn btn-success" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
  }else{
    echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
  }

// --------------------------------------------------------------------------------
}elseif($action == 'tokenoncesave' AND isset($_SESSION['agent']) AND ($_SESSION['agent']->therole == 'C' OR $_SESSION['agent']->therole == 'R') ) {
// --------------------------------------------------------------------------------
  // 	每次儲值金額
  //var_dump($_POST);
  // PK
  $pk         = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  // 欄位
  $name       = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
  // 每次儲值金額
  $value      = filter_var(floor($_POST['value']), FILTER_SANITIZE_NUMBER_INT);

  $sql = "SELECT * FROM root_member_wallets WHERE id = '$pk';";
  //var_dump($sql);
  $result = runSQLall($sql);
  //var_dump($result);
  if($result[0] == 1 and $name == 'tokenoncesave') {
    // 每次儲值金額, 不可以小於 1 元 且 不可以小於 最低自動轉帳餘額
    if(($value > $result[1]->auto_min_gtoken) AND ($value >= 1)){
      // 符合 , 將 每次儲值金額,  資料寫入資料庫. 合成 sql 時選擇越少sql漏洞越少
      $wsql = "UPDATE root_member_wallets SET changetime = NOW(), auto_once_gotken = '$value' WHERE id = '$pk';";
      //var_dump($wsql);
      $wresult = runSQLall($wsql);
      if($wresult[0] == 1) {
        $error['code'] = 100;
        $error['messages'] = '每次儲值金額餘額更新為 '.$config['currency_sign'].' $value 完成';
      }else{
        $error['code'] = 403;
        $error['messages'] = '資料庫更新失敗';
      }
    }else{
      $error['code'] = 402;
      $error['messages'] = '每次儲值金額, 不可以小於 1 元 且 不可以小於 最低自動轉帳餘額';
    }
  }else{
    $error['code'] = 401;
    $error['messages'] = '資料庫存取錯誤';
  }

  // var_dump($error);
  if($error['code'] == 100) {
    echo '<p align="center"><button type="button" class="btn btn-success" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
  }else{
    echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
  }


// --------------------------------------------------------------------------------
}elseif($action == 'autocash2token' AND isset($_SESSION['agent']) AND ($_SESSION['agent']->therole == 'C' OR $_SESSION['agent']->therole == 'R') ) {
// --------------------------------------------------------------------------------
  // 自動化儲值開啟(開/關) -- auto_gtoken
  //var_dump($_POST);
  // 煮鍵
  $pk         = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  // 欄位 auto_gtoken
  $name       = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
  // 開=1/關=0
  $value      = filter_var($_POST['value'], FILTER_SANITIZE_STRING);

  if($name == 'autocash2token') {
    // 符合 , 將 自動化儲值開啟(開/關),  資料寫入資料庫. 合成 sql 時選擇越少sql漏洞越少
    $wsql = "UPDATE root_member_wallets SET changetime = NOW(), auto_gtoken = '$value' WHERE id = '$pk';";
    //var_dump($wsql);
    $wresult = runSQLall($wsql);
    if($wresult[0] == 1) {
      $error['code'] = 100;
      $error['messages'] = "帳號".$pk."自動化儲值設定為 $value (開=1/關=0)";
    }else{
      $error['code'] = 403;
      $error['messages'] = '資料庫更新失敗';
    }
  }else{
    $error['code'] = 405;
    $error['messages'] = '欄位有問題';
  }

  // var_dump($error);
  if($error['code'] == 100) {
    echo '<p align="center"><button type="button" class="btn btn-success" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
  }else{
    echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
  }

// --------------------------------------------------------------------------------
// 設定自動儲值的設定區塊動作 code , start
// --------------------------------------------------------------------------------

// ----------------------------------------------------------------------------
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}



?>
