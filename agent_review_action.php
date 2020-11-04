<?php
// ----------------------------------------------------------------------------
// Features:	代理商後台 - agent_review.php 的處理
// File Name:	agent_review_action.php
// Author:		侑駿
// Related:		對應後台 agent_review.php
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/lib_agents_setting.php";
require_once dirname(__FILE__) ."/gcash_lib.php";

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
}else{
    die('(x)不合法的测试');
}
// var_dump($_SESSION);
// die();
// var_dump($_POST);
//var_dump($_GET);

// 本功能的 global 變數設定
// 申請代理商最低需要的金額 CNY -- config
$agent_need_balance = $system_config['agency_registration_gcash'];;
$operator=$_SESSION['agent']->account;
// ----------------------------------
// 動作為會員登入檢查, 只有 Root 可以維護。
// ----------------------------------
if($action == 'agent_review_submit' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // 取得 agent_review_info 的 ID
  $agent_review_id = filter_var($_POST['agent_review_id'], FILTER_SANITIZE_NUMBER_INT);
  $agent_notes = filter_var($_POST['agent_notes'], FILTER_SANITIZE_STRING);

  // 查詢 root_member_wallets 資料表
  $agent_review_sql = "
  SELECT *
  FROM root_agent_review
  WHERE id = '".$agent_review_id."'
  ";

  $agent_review_result = runSQLALL($agent_review_sql);
  // 判斷是否有無單號
  if($agent_review_result[0] == 1){
    // 搜尋 root_member 會員資訊
    $root_member_sql = "SELECT * FROM root_member WHERE account = '".$agent_review_result[1]->account."'";
    // 搜尋 root_member GCASH 出納帳號資訊
    $root_member_gcash_sql = "SELECT * FROM root_member WHERE account = '".$gcash_cashier_account."'";
    // var_dump($root_member_gcash_sql);
    // 執行「會員」 runSQLALL
    $root_member_result = runSQLALL($root_member_sql);
    // 執行 「GCASH」 runSQLALL
    $root_member_gcash_result = runSQLALL($root_member_gcash_sql);
    // 判斷「會員」、「GCASH」是否有資料
    if($root_member_result[0] == 1 AND $root_member_gcash_result[0] == 1){
      // status 變更申請的狀態, 如果是 同意成為代理商 status = 1 , 紀錄審查者帳號及時間
      $review_sql = "UPDATE root_agent_review SET status = '1', processingaccount = '".$_SESSION['agent']->account."' , notes = '".$agent_notes."', processingtime = NOW() WHERE id = '".$agent_review_result[1]->id."';";
      $member_sql = "UPDATE root_member SET therole = 'A', becomeagentdate = now() WHERE id = '".$root_member_result[1]->id."';";
      // var_dump($review_sql);exit;
      // 操作： root_member_gcashpassbook
      //代理商交易訊息(管理員)
      $source_notes = "(管理员成功审核会员为代理商)。";
      $root_gcash_sql ='
      INSERT INTO "root_member_gcashpassbook" ("transaction_time", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount", "balance", "transaction_category","transaction_id","operator")'.
      "VALUES ('now()', '".$source_notes."', '".$_SESSION['agent']->id."', '". $config['currency_sign']."', '申请代理-同意', '".$gcash_cashier_account."', '".$root_member_result[1]->account."', (select gcash_balance from root_member_wallets where id = '".$root_member_gcash_result[1]->id."'), 'cashwithdrawal',
      '".$agent_review_result[1]->transaction_id."','".$operator."');";

      //代理商交易訊息(會員)
      $source_notes = "(会员成功审核成为代理商)。";
      $member_gcash_sql ='
      INSERT INTO "root_member_gcashpassbook" ("transaction_time", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount", "balance", "transaction_category","transaction_id","operator")'.
      "VALUES ('now()', '".$source_notes."', '".$_SESSION['agent']->id."', '". $config['currency_sign']."', '申请代理-同意', '".$root_member_result[1]->account."', '".$gcash_cashier_account."', (select gcash_balance from root_member_wallets where id = '".$root_member_result[1]->id."'), 'cashwithdrawal',
      '".$agent_review_result[1]->transaction_id."','".$operator."');";

      // var_dump($member_gcash_sql);die();
      $sql = 'BEGIN;'
      .$review_sql
      .$member_sql
      .$root_gcash_sql
      .$member_gcash_sql
      .'COMMIT;';

      $sql_result = runSQLtransactions($sql);

      if( $sql_result == 1) {
        // 成功
        // $logger = '审核状态: 会员'.$root_member_result[1]->account.'帐号变更为代理商更新成功。';
        $logger = $tr['Review Status: Member'].$root_member_result[1]->account.$tr['The account change is successful for the agent update.'];

        // 初始化新代理商的分佣比
        $member_id = $root_member_result[1]->id;
        $feedbackhelper = new FeedbackInfoHelper(compact('member_id'));
        $feedbackhelper->initFeedbackInfo();
        $feedbackhelper->save();
        $logger = $tr['Review Status: Member'].$root_member_result[1]->account.$tr['The account change is successful for the agent update.'].$tr['Agent sub-commission settings have been initialized'];
        // $logger = '审核状态: 会员'.$root_member_result[1]->account.'帐号变更为代理商更新成功。代理商分佣设置已初始化';

      }else{
        // 系统错误
        // $logger = '审核状态: 会员'.$root_member_result[1]->account.'变更失败，请联络维护人员处理。';
        $logger = $tr['Review Status: Member'].$root_member_result[1]->account.$tr['If the change fails, please contact the maintenance staff.'];
      }

    }else{
      $logger = $tr['Currently there is no member account information, please re-enter the order information.'];
      // $logger = '(x)目前此无会员帐号资料，请重新进入订单资讯。';

    }
  }else{
    $logger = $tr['This order number has been processed so far, please do not re-process it.'];
    // $logger = '(x)目前此订单号已处理过，请勿重新操作处理。';
  }
  echo '<script type="text/javascript">alert("'.$logger.'");location.href="./agent_review.php";</script>';die();

// ----------------------------------------------------------------------------
}elseif($action == 'agent_review_cancel' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // ----------------------------------
  // 動作為會員登入檢查, 只有 Root 可以維護。
  // ----------------------------------
  // 取得 agent_review_info 的 ID
  $agent_review_id = filter_var($_POST['agent_review_id'], FILTER_SANITIZE_NUMBER_INT);
  $agent_notes = filter_var($_POST['agent_notes'], FILTER_SANITIZE_STRING);

  // 查詢 root_member_wallets 資料表
  $agent_review_sql = "
  SELECT *
  FROM root_agent_review
  WHERE id = '".$agent_review_id."'
  ";

  $agent_review_result = runSQLALL($agent_review_sql);
  // var_dump($agent_review_result);die();
  // 判斷是否有無單號
  if($agent_review_result[0] == 1){
    // 搜尋 root_member 會員資訊
    $root_member_sql = "SELECT * FROM root_member WHERE account = '".$agent_review_result[1]->account."'";
    // 搜尋 root_member GCASH 出納帳號資訊
    $root_member_gcash_sql = "SELECT * FROM root_member WHERE account = '".$gcash_cashier_account."'";
    // var_dump($root_member_gcash_sql);
    // 執行「會員」 runSQLALL
    $root_member_result = runSQLALL($root_member_sql);
    // 執行 「GCASH」 runSQLALL
    $root_member_gcash_result = runSQLALL($root_member_gcash_sql);
    // 判斷「會員」、「GCASH」是否有資料
    if($root_member_result[0] == 1 AND $root_member_gcash_result[0] == 1){
      // status = 4 , 紀錄審查人員帳號及時間, 會員變更為 member
      $review_sql = "UPDATE root_agent_review SET status = '4', processingaccount = '".$_SESSION['agent']->account."', notes = '".$agent_notes."', processingtime = NOW() WHERE id = '".$agent_review_result[1]->id."';";
      $member_sql = "UPDATE root_member SET therole = 'M' WHERE id = '".$root_member_result[1]->id."';";

      // 出納gcash帳戶退款
      $update_member_gcash_sql = "UPDATE root_member_wallets SET changetime = NOW(), gcash_balance = gcash_balance - '".$agent_need_balance."' WHERE id = '".$root_member_gcash_result[1]->id."';";
      // 出納gcash帳戶存款至會員
      $update_gcash_sql = "UPDATE root_member_wallets SET changetime = NOW(), gcash_balance = gcash_balance + '".$agent_need_balance."' WHERE id = '".$root_member_result[1]->id."';";
      //代理商退款交易訊息(管理員)
      $source_notes = "(管理员出纳帐号gcash_cashier存款到".$root_member_result[1]->account."，代理商审核失败)";
      $root_gcash_sql ='
      INSERT INTO "root_member_gcashpassbook" ("transaction_time", "withdrawal", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount", "balance", "transaction_category","transaction_id","operator")'.
      "VALUES ('now()', '".$agent_need_balance."', '".$source_notes."', '".$_SESSION['agent']->id."', '". $config['currency_sign']."', '申请代理-退回', '".$gcash_cashier_account."', '".$root_member_result[1]->account."', (select gcash_balance from root_member_wallets where id = '".$root_member_gcash_result[1]->id."'), 'reject_cashwithdrawal','".$agent_review_result[1]->transaction_id."','".$operator."');";

      //代理商交易讯息(会员)
      $source_notes = "(管理员出纳存款到".$root_member_result[1]->account."，代理商审核失败)。";
      $member_gcash_sql ='
      INSERT INTO "root_member_gcashpassbook" ("transaction_time", "deposit", "system_note", "member_id", "currency", "summary", "source_transferaccount", "destination_transferaccount", "balance", "transaction_category","transaction_id","operator")'.
      "VALUES ('now()', '".$agent_need_balance."', '".$source_notes."', '".$_SESSION['agent']->id."', '".$config['currency_sign']."', '申请代理-退回', '".$root_member_result[1]->account."', '".$gcash_cashier_account."', (select gcash_balance from root_member_wallets where id = '".$root_member_result[1]->id."'), 'reject_cashwithdrawal','".$agent_review_result[1]->transaction_id."'
      ,'".$operator."');";

      $sql = 'BEGIN;'
      .$review_sql
      .$member_sql
      .$update_member_gcash_sql
      .$update_gcash_sql
      .$root_gcash_sql
      .$member_gcash_sql
      .'COMMIT;';

      $sql_result = runSQLtransactions($sql);

      if( $sql_result == 1) {
        // 取消
        $logger = '审核状态: 会员'.$root_member_result[1]->account.'帐号不符合资格已退件。';
        update_gcash_log_exist($root_member_result[1]->account);
      }else{
        // 系统错误
        $logger = '审核状态: 会员'.$root_member_result[1]->account.'变更失败，请联络维护人员处理。';
      }

      // var_dump($sql);exit;
    }else{
      $logger = '(x)目前此无会员帐号资料，请重新进入订单资讯。';
    }
  }else{
    $logger = '(x)目前此订单号已处理过，请勿重新操作处理。';
  }
  echo '<script type="text/javascript">alert("'.$logger.'");location.href="./agent_review.php";</script>';die();
}else if($action == 'agreen_update_notes' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
//  var_dump($_POST);

  //更新处理资讯的 notes
  $agent_review_id = filter_var($_POST['agent_review_id'], FILTER_SANITIZE_NUMBER_INT);
  $agent_notes = filter_var($_POST['agent_notes'], FILTER_SANITIZE_STRING);

  // 更新 root_agent_review 「notes」
  $review_sql = "UPDATE root_agent_review SET processingaccount = '".$_SESSION['agent']->account."', notes = '".$agent_notes."', processingtime = NOW() WHERE id = '".$agent_review_id."';";
//  var_dump($review_sql);
  $review_result = runSQLtransactions($review_sql);
//  var_dump($review_result);

  if($review_result == 1){
    // 更新 notes
    $logger = "更新处理资讯内容文章";
  }else{
    // 系统错误
    $logger = "更新未成功错误，请联络维护人员处理。";
  }
echo '<script type="text/javascript">alert("'.$logger.'");location.href="./agent_review_info.php?id='.$agent_review_id.'";</script>';die();
}else{
  $logger = '(x) 只有管理员或有权限的会员才可以登入观看。';
  echo '<script type="text/javascript">alert("'.$logger.'");</script>';die();
}



?>
