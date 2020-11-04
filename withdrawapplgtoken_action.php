<?php
// ----------------------------------------------------------------------------
// Features: 取款申請審核後台 - withdrawapplication_action.php 處理
// File Name:	withdrawapplication_action.php
// Author:		Barkley 侑駿
// Related:		後台的 withdrawapplication.php
// Log: ※withdrawapplication_action.php 後台對應前台，申請取款轉帳的程式 in 後台
// ----------------------------------------------------------------------------



session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{//$tr['Illegal test'] = '(x)不合法的測試。';
    die($tr['Illegal test']);
}

// var_dump($_SESSION);
// var_dump($_POST);
//var_dump($_GET);
// var_dump($action);


// ----------------------------------
// 動作檢查
// ----------------------------------

// 1.判斷 $_SESSION 權限
if($action == 'withdrawalgtoken_submit' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // var_dump($action);

  // 取得 root_deposit_review 的 ID
  $withdrawapplgtoken_id = filter_var($_POST['withdrawapplgtoken_id'], FILTER_SANITIZE_NUMBER_INT);

  // 查詢 root_member_wallets 資料表
  $withdrawapplgtoken_sql = "
  SELECT *
  FROM root_withdraw_review
  WHERE id = '".$withdrawapplgtoken_id."'
  ";
  // var_dump($withdrawapplgtoken_sql);

  $withdrawapplgtoken_result = runSQLALL($withdrawapplgtoken_sql);
  // var_dump($withdrawapplgtoken_result);

  // 判斷單號審核資料
  if($withdrawapplgtoken_result[0] == 1){
    // 搜尋 root_member 會員資訊
    $root_member_sql = "SELECT * FROM root_member WHERE account = '".$withdrawapplgtoken_result[1]->account."'";
    // 搜尋 root_member GTOKEN 出納帳號資訊
    $root_member_gcash_sql = "SELECT * FROM root_member WHERE account = '".$gtoken_cashier_account."'";
    // var_dump($root_member_gcash_sql);

    // 執行「會員」 runSQLALL
    $root_member_result = runSQLALL($root_member_sql);
    // 執行 「GTOKEN」 runSQLALL
    $root_member_gtoken_result = runSQLALL($root_member_gcash_sql);

    // 判斷搜索「會員」、「GTOKEN」資訊
    if($root_member_result[0] == 1 AND $root_member_gtoken_result[0] == 1){
      // 最終開始交易
      // 更新 root_withdraw_review 變數
      $withdraw_review_update_sql = "UPDATE root_withdraw_review SET status = 1, processingaccount = '".$_SESSION['agent']->account."', processingtime = now() WHERE id = ".$withdrawapplgtoken_result[1]->id.";";
      // 取款同意(管理員) $tr['Administrator agrees'] = '管理員同意';
      $source_notes = '('.$tr['Administrator agrees'].$root_member_result[1]->account.')。';
      $withdrawalgtoken_manager_insert_sql = 'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount","auditmode", "auditmodeamount", "realcash", "balance", "transaction_category")'.
      "VALUES ('now()', '".$source_notes."', '".$_SESSION['agent']->id."', '".$config['currency_sign']."', '".$transaction_category['tokengcash']."', '".$gtoken_cashier_account."', '".$root_member_result[1]->account."', 'freeaudit', '".$withdrawapplgtoken_result[1]->amount."', '0', (select gtoken_balance from root_member_wallets where id = '".$root_member_gtoken_result[1]->id."'),'tokengcash');";
      // var_dump($withdrawalgtoken_manager_insert_sql);

      // 取款同意(會員) $tr['Identity Member'] = '會員'; $tr['Receive crediting notification message'] = '收到入款通知訊息。';
      $source_notes = '('.$tr['Identity Member'].$root_member_result[1]->account.$tr['Receive crediting notification message'].')';
      $withdrawalgtoken_member_insert_sql = 'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount","auditmode", "auditmodeamount", "realcash", "balance", "transaction_category")'.
      "VALUES ('now()', '".$source_notes."', '".$_SESSION['agent']->id."', '".$config['currency_sign']."', '".$transaction_category['tokengcash']."', '".$root_member_result[1]->account."', '".$gtoken_cashier_account."', 'freeaudit', '".$withdrawapplgtoken_result[1]->amount."', '0', (select gtoken_balance from root_member_wallets where id = '".$root_member_result[1]->id."'),'tokengcash');";

      // 最後資料輸入動態
      $withdrawgtoken_sql = 'BEGIN;'
      .$withdraw_review_update_sql
      .$withdrawalgtoken_manager_insert_sql
      .$withdrawalgtoken_member_insert_sql
      .'COMMIT;';

      $withdrawgtoken_result = runSQLtransactions($withdrawgtoken_sql);
      // var_dump($withdrawgtoken_result);exit;

      // 最終是否正確執行取款同意資料 $tr['Successfully agreed to withdraw money'] = '成功同意取款。';
       if($withdrawgtoken_result == 1){
           $logger = $tr['Successfully agreed to withdraw money'];
       }else{//$tr['data processing error'] = '(x)資料處理錯誤，請聯絡維護人員處理。';
         $logger = $tr['data processing error'];
       }

    }else{//$tr['currently no member account'] = '(x)目前此無會員帳號資料，請重新進入訂單資訊。';
      $logger = $tr['currently no member account'];
    }

  }else{//$tr['This order number has been processed so far, do not re-operate.'] = '目前此訂單號已處理過，請勿重新操作處理。';
    $logger = '(x)'.$tr['This order number has been processed so far, do not re-operate.'];
  }
  echo '<script type="text/javascript">alert("'.$logger.'");location.href="./withdrawalgtoken_company_audit.php";</script>';die();

}else if($action == 'withdrawalgtoken_cancel' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // 取得 root_deposit_review 的 ID
  $withdrawapplgtoken_id = filter_var($_POST['withdrawapplgtoken_id'], FILTER_SANITIZE_NUMBER_INT);

  // 查詢 root_member_wallets 資料表
  $withdrawapplgtoken_sql = "
  SELECT *
  FROM root_withdraw_review
  WHERE id = '".$withdrawapplgtoken_id."'
  ";
  // var_dump($withdrawapplgtoken_sql);

  $withdrawapplgtoken_result = runSQLALL($withdrawapplgtoken_sql);
  // var_dump($withdrawapplgtoken_result);

  // 判斷單號審核資料
  if($withdrawapplgtoken_result[0] == 1){
    // 搜尋 root_member 會員資訊
    $root_member_sql = "SELECT * FROM root_member WHERE account = '".$withdrawapplgtoken_result[1]->account."'";
    // 搜尋 root_member GTOKEN 出納帳號資訊
    $root_member_gcash_sql = "SELECT * FROM root_member WHERE account = '".$gtoken_cashier_account."'";
    // var_dump($root_member_gcash_sql);

    // 執行「會員」 runSQLALL
    $root_member_result = runSQLALL($root_member_sql);
    // 執行 「GTOKEN」 runSQLALL
    $root_member_gtoken_result = runSQLALL($root_member_gcash_sql);

    // 判斷搜索「會員」、「GTOKEN」資訊
    if($root_member_result[0] == 1 AND $root_member_gtoken_result[0] == 1){
      // 最終開始交易

      // 更新 root_withdraw_review 變數
      $withdraw_review_update_sql = "UPDATE root_withdraw_review SET status = 0, processingaccount = '".$_SESSION['agent']->account."', processingtime = now() WHERE id = ".$withdrawapplgtoken_result[1]->id.";";

      //提款
      $manager_member_wallets_sql = "UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = gtoken_balance - ".$withdrawapplgtoken_result[1]->amount." WHERE id = ".$root_member_gtoken_result[1]->id.";";
      // var_dump($manager_member_wallets_sql);
      //存款
      $member_member_wallets_sql = "UPDATE root_member_wallets SET changetime = NOW(), gtoken_balance = gtoken_balance + ".$withdrawapplgtoken_result[1]->amount." WHERE id = ".$root_member_result[1]->id.";";
      // var_dump($member_member_wallets_sql);exit;

      // 取款取消(管理員) $tr['Identity Management Title'] = '管理員'; $tr['Cancel the payment to members'] = '取消入款給會員';$tr['account id'] = '帳戶';
      $source_notes = '('.$tr['Identity Management Title'].$tr['Cancel the payment to members'].$withdrawapplgtoken_result[1]->account.$tr['account id'].')。';
      $withdrawalgtoken_manager_insert_sql = 'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount","auditmode", "auditmodeamount", "realcash", "balance", "transaction_category")'.
      "VALUES ('now()', '".$withdrawapplgtoken_result[1]->amount."', '".$source_notes."', '".$_SESSION['agent']->id."', '".$config['currency_sign']."', '".$transaction_category['tokengcash']."', '".$gtoken_cashier_account."', '".$withdrawapplgtoken_result[1]->account."', 'freeaudit', '".$withdrawapplgtoken_result[1]->amount."', '0', (select gtoken_balance from root_member_wallets where id = '".$root_member_gtoken_result[1]->id."'),'tokengcash');";
      // var_dump($withdrawalgtoken_manager_insert_sql);

      // 取款取消(會員) $tr['Cancel the payment to members'] = '取消入款給會員'; $tr['account id'] = '帳戶';
      $source_notes = '('.$tr['Cancel the payment to members'].$withdrawapplgtoken_result[1]->account.$tr['account id'].')。';
      $withdrawalgtoken_member_insert_sql = 'INSERT INTO "root_member_gtokenpassbook" ("transaction_time", "deposit", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount","auditmode", "auditmodeamount", "realcash", "balance", "transaction_category")'.
      "VALUES ('now()', '".$withdrawapplgtoken_result[1]->amount."', '".$source_notes."', '".$_SESSION['agent']->id."', '".$config['currency_sign']."', '".$transaction_category['tokengcash']."', '".$withdrawapplgtoken_result[1]->account."', '".$gtoken_cashier_account."', 'freeaudit', '".$withdrawapplgtoken_result[1]->amount."', '0', (select gtoken_balance from root_member_wallets where id = '".$root_member_result[1]->id."'),'tokengcash');";
      // var_dump($withdrawalgtoken_member_insert_sql);exit;

      // 最後資料輸入動態
      $withdrawgtoken_sql = 'BEGIN;'
      .$withdraw_review_update_sql
      .$manager_member_wallets_sql
      .$member_member_wallets_sql
      .$withdrawalgtoken_manager_insert_sql
      .$withdrawalgtoken_member_insert_sql
      .'COMMIT;';

      $withdrawgtoken_result = runSQLtransactions($withdrawgtoken_sql);
      // var_dump($withdrawgtoken_result);

      // 最終是否正確執行取款同意資料 $tr['Successful withdrawal of withdrawals'] = '成功取消提款。';
       if($withdrawgtoken_result == 1){
           $logger = $tr['Successful withdrawal of withdrawals'];
       }else{
         //$tr['data processing error'] = '(x)資料處理錯誤，請聯絡維護人員處理。';
         $logger = $tr['data processing error'];
       }

    }else{
      //$tr['currently no member account'] = '(x)目前此無會員帳號資料，請重新進入訂單資訊。';
      $logger = $tr['currently no member account'] ;
    }

  }else{
    //$tr['This order number has been processed so far, do not re-operate.'] = '目前此訂單號已處理過，請勿重新操作處理。';
    $logger = '(x)'.$tr['This order number has been processed so far, do not re-operate.'];
  }
  echo '<script type="text/javascript">alert("'.$logger.'");location.href="./withdrawalgtoken_company_audit.php";</script>';die();
}else{//$tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
  $logger =$tr['only management and login mamber'] ;
  echo '<script type="text/javascript">alert("'.$logger.'");location.href="./depositing_company_audit.php"</script>';die();
}
?>
