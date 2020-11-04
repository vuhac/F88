<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 線上支付商戶管理 , 顯示詳細線上支付商戶資訊
// File Name:	deposit_onlinepayment_config_detail_action.php
// Author:		Yuan
// Related:   服務 deposit_onlinepayment_config_detail.php
// DB Table:
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 專屬本頁的文字檔案
require_once dirname(__FILE__) ."/deposit_onlinepayment_config_lib.php";

if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
//  var_dump($_GET);
} else {
  // $tr['Illegal test'] = '(x)不合法的測試。';
  die($tr['Illegal test']);
}
// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);

// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// 修改第三方支付 start
// ----------------------------------------------------------------------------
if($action == 'onlinepayment_edit_save' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  //var_dump($_POST);

  //該過濾的都過濾一遍
  $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

  $payname = filter_var($_POST['payname'], FILTER_SANITIZE_STRING);

  $merchantid = filter_var($_POST['merchantid'], FILTER_SANITIZE_STRING);

  $gradename = $_POST['gradename'];

  $cashfeerate = floatval(preg_replace('/[^0-9\.\-]/', '', $_POST['cashfeerate']));

  $status = filter_var($_POST['status'], FILTER_SANITIZE_NUMBER_INT);

  $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);

  $merchantname = filter_var($_POST['merchantname'], FILTER_SANITIZE_STRING);

//  $hashiv = filter_var($_POST['hashiv'], FILTER_SANITIZE_STRING);
  $hashiv = $_POST['hashiv'];

  //$hashkey = filter_var($_POST['hashkey'], FILTER_SANITIZE_STRING);
  $hashkey = $_POST['hashkey'];

  $singledepositlimits = floatval(preg_replace('/[^0-9\.]/', '', $_POST['singledepositlimits']));

  $depositlimits = floatval(preg_replace('/[^0-9\.]/', '', $_POST['depositlimits']));

  $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);

  $receiptaccount = filter_var($_POST['receiptaccount'], FILTER_SANITIZE_STRING);

  $receiptbank = filter_var($_POST['receiptbank'], FILTER_SANITIZE_STRING);

  $receiptname = filter_var($_POST['receiptname'], FILTER_SANITIZE_STRING);

  $effectiveseconds = filter_var($_POST['effectiveseconds'], FILTER_SANITIZE_NUMBER_INT);

  $error_msg = '';

  // $tr['Single deposit limit, the cumulative total deposits, fees (%), the number of seconds the transaction, can only fill in the numbers']= '單次存款上限、累積總存款、手續費(%)、交易有效秒數，只能填入數字，';
  if(preg_match('/[^0-9\.]/',$_POST['cashfeerate']) != 0 || preg_match('/[^0-9\.]/',$_POST['singledepositlimits']) != 0 || preg_match('/[^0-9\.]/',$_POST['depositlimits']) != 0 || preg_match('/[^0-9]/',$_POST['effectiveseconds']) != 0){
    $error_msg = $error_msg.$tr['Single deposit limit, the cumulative total deposits, fees (%), the number of seconds the transaction, can only fill in the numbers'];
  }

  //檢查id
  $search_sql = "SELECT * FROM root_deposit_onlinepayment WHERE id='".$id."';";
  $search_sql_result = runSQLall($search_sql);

  //理論上只會有一筆
  if($search_sql_result[0] == 1){

    //支付商名稱是否正確
    for($i=0;$i<count($payment_service);$i++){
        $msg = '';
      if($name == $payment_service[$i]['code']){
        $pay_channel = $payment_service[$i]['channel'];
        break;
      }else{
        // $tr['The name of the payment provider is incorrect']= '支付商名稱不正確，';
        $msg = $tr['The name of the payment provider is incorrect'];
      }
    }
    $error_msg = $error_msg.$msg;


    //檢查手續費設定是否在範圍內
    // $tr['fee set wrong (must be 0 ~ 100)'] = '手續費設定錯誤(須為0~100)，';
    if($cashfeerate > 100 || $cashfeerate <0){
      $error_msg = $error_msg.$tr['fee set wrong (must be 0 ~ 100)'];
    }

    //檢查交易有效秒數設定是否在範圍內
    if($effectiveseconds > 900 || $effectiveseconds < 60){
      // $tr['The number of valid seconds for trading is set incorrectly (it must be 60~900)']= '交易有效秒數設定錯誤(須為60~900)，';
      $error_msg = $error_msg.$tr['The number of valid seconds for trading is set incorrectly (it must be 60~900)'];
    }

    //檢查累積总存款是否在範圍內
    if($depositlimits <0){
      // $tr['Accumulated total deposit setting error (must be at least greater than 0)']= '累積總存款設定錯誤(須至少大於0)';
      $error_msg = $error_msg.$tr['Accumulated total deposit setting error (must be at least greater than 0)'];
    }

    //檢查单次存款上限是否在範圍內
    if($singledepositlimits <0){
      // $tr['Single deposit limit set incorrectly (must be at least greater than 0)']= '單次存款上限設定錯誤(須至少大於0)，';
      $error_msg = $error_msg.$tr['Single deposit limit set incorrectly (must be at least greater than 0)'];
    }


    //狀態 是否正確
    if($status != 1 && $status != 0){
      // $tr['status is wrong'] = '狀態有錯誤，';
      $error_msg = $error_msg.$tr['status is wrong'].'';
    }

    //會員等級轉成json格式
    $input_grade = '';
    for($i=0;$i<count($gradename)-1;$i++){
      $input_grade = $input_grade.$gradename[$i].',';
    }
      $input_grade = '{'.$input_grade.$gradename[count($gradename)-1].'}';

    //echo $input_grade;
    //都沒有出錯 執行SQL
    if($error_msg == NULL){
      $edit_sql = "UPDATE root_deposit_onlinepayment SET payname = '".$payname."' , merchantid = '".$merchantid."', grade = '".$input_grade."', cashfeerate = '".$cashfeerate."',
                                                         status = '".$status."', name = '".$name."', merchantname ='".$merchantname."' ,hashiv ='".trim($hashiv)."' , hashkey ='".trim($hashkey)."',
                                                         singledepositlimits ='".$singledepositlimits."' , depositlimits ='".$depositlimits."' , notes ='".$notes."' , receiptaccount='".$receiptaccount."',
                                                         receiptbank = '".$receiptbank."' , receiptname = '".$receiptname."' , changetime = now() , pay_channel = '".$pay_channel."' , effectiveseconds = '".$effectiveseconds."' WHERE id = '".$id."';";
      //var_dump($edit_sql);
      $edit_sql_result = runSQL($edit_sql);

      if($edit_sql_result == '0'){
        // $tr['Modify failed'] = '修改失敗，';
        $error_msg = $error_msg.$tr['Modify failed'];
      }else{
        // $tr['Change successful'] = '修改成功！';
        echo '<div class="alert alert-success" role="alert">'.$tr['Change successful'].'</div>
                <script type="text/javascript">
                  window.setTimeout(function(){ window.location.href = "deposit_onlinepayment_config.php";}, 3000);
                </script>';
      }
    }

  }else{
    // $tr['query modify the error'] = '查詢修改錯誤，';
    $error_msg = $error_msg.$tr['query modify the error'];
  }

  if($error_msg != NULL){
    // $tr['Please contact customer service'] = '(x)請聯絡客服。';
    echo '<div class="alert alert-warning" role="alert">'.$error_msg.$tr['Please contact customer service'].'</div>';
  }
// ----------------------------------------------------------------------------
// 修改第三方支付 end
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
} elseif($action == 'onlinepayment_add_save' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  //var_dump($_POST);

  $payname = filter_var($_POST['payname'], FILTER_SANITIZE_STRING);

  $merchantid = filter_var($_POST['merchantid'], FILTER_SANITIZE_STRING);

  $gradename = $_POST['gradename'];

  $cashfeerate = floatval(preg_replace('/[^0-9\.\-]/', '', $_POST['cashfeerate']));

  $status = filter_var($_POST['status'], FILTER_SANITIZE_NUMBER_INT);

  $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);

  $merchantname = filter_var($_POST['merchantname'], FILTER_SANITIZE_STRING);

  $hashiv = filter_var($_POST['hashiv'], FILTER_SANITIZE_STRING);

  $hashkey = filter_var($_POST['hashkey'], FILTER_SANITIZE_STRING);

  $singledepositlimits = floatval(preg_replace('/[^0-9\.]/', '', $_POST['singledepositlimits']));

  $depositlimits = floatval(preg_replace('/[^0-9\.]/', '', $_POST['depositlimits']));

  $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);

  $receiptaccount = filter_var($_POST['receiptaccount'], FILTER_SANITIZE_STRING);

  $receiptbank = filter_var($_POST['receiptbank'], FILTER_SANITIZE_STRING);

  $receiptname = filter_var($_POST['receiptname'], FILTER_SANITIZE_STRING);

  for($i=0;$i<count($payment_service);$i++){
    $msg = '';
    if($name == $payment_service[$i]['code']){
      $pay_channel = $payment_service[$i]['channel'];
      break;
      // $tr['The name of the payment provider is incorrect']= '支付商名稱不正確，';;
    }else{ $msg = $tr['The name of the payment provider is incorrect']; }
  }

  $error_msg = '';

  //檢查是否有重複
  $search_sql = "SELECT * FROM root_deposit_onlinepayment WHERE payname = '".$payname."' AND name = '".$name."' AND merchantid = '".$merchantid."';";

  $search_sql_result = runSQL($search_sql);

  if($search_sql_result == '0'){

    //會員等級轉成json格式
    $input_grade = '';
    for($i=0;$i<count($gradename)-1;$i++){
      $input_grade = $input_grade.$gradename[$i].',';
    }
      $input_grade = '{'.$input_grade.$gradename[count($gradename)-1].'}';


    $insert_sql = "INSERT INTO root_deposit_onlinepayment (payname, name, hashiv, hashkey, merchantid, singledepositlimits, depositlimits, merchantname, notes, changetime, status, pay_channel, cashfeerate, receiptaccount, receiptbank, receiptname, grade)
                    VALUES ('".$payname."','".$name."','".trim($hashiv)."','".trim($hashkey)."','". trim($merchantid) ."','".$singledepositlimits."','".$depositlimits."','".$merchantname."','".$notes."',now(),'".$status."','".$pay_channel."','".$cashfeerate."','".$receiptaccount."',
                    '".$receiptbank."','".$receiptname."','".$input_grade."');";

    //var_dump($insert_sql);
    $insert_sql_result = runSQL($insert_sql);

    if($insert_sql_result == '0'){
      // $tr['add failed'] = '新增失敗，';
      $error_msg = $error_msg.$tr['add failed'];
    }else{
      // $tr['Change successful'] = '修改成功！';
      echo '<div class="alert alert-success" role="alert">'.$tr['Change successful'].'</div>
              <script type="text/javascript">
                window.setTimeout(function(){ window.location.href = "deposit_onlinepayment_config.php";}, 3000);
              </script>';
    }
  }else{
    // $tr['Payment names, payment providers, and store codes have the same combination. Please change at least one of them']    = '支付名稱、支付商、商店代號 三者已有同樣的組合，請修改至少其中一項，';
    $error_msg = $error_msg.$tr['Payment names, payment providers, and store codes have the same combination. Please change at least one of them'];
  }

  if($error_msg != NULL){
    // $tr['If you have any questions, please contact customer service']    = '如有任何問題，請聯絡客服。';
    echo '<div class="alert alert-warning" role="alert">'.$error_msg.$tr['If you have any questions, please contact customer service'].'</div>';
  }

}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}
