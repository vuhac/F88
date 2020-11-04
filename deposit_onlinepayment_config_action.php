<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 公告訊息管理 對應 deposit_onlinepayment_config.php
// File Name:	deposit_onlinepayment_config_action.php
// Author:		Yuan
// Related:   服務 deposit_onlinepayment_config.php
// DB Table:  root_deposit_onlinepayment
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
//  var_dump($_GET);$tr['Illegal test'] = '(x)不合法的測試。';
} else {
  die($tr['Illegal test']);
}
// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);
if($action == 'delete' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){

  $id_num = $_POST['edit_id_num'];

  $id_num = filter_var($id_num, FILTER_SANITIZE_NUMBER_INT);

  $delete_sql = '';

  if($id_num){
    //確認要刪除的ID是否都存在
    $search_sql = "SELECT * FROM root_deposit_onlinepayment WHERE  ( status = '1' OR status = '0') AND id = '".$id_num."';";
    $search_sql_result = runSQLALL($search_sql);

    //如果只有一筆
    if($search_sql_result[0] == 1){

      $delete_sql = $delete_sql."UPDATE root_deposit_onlinepayment SET status = '2' WHERE id = '".$id_num."';";
      //echo $delete_sql;
      $delete_sql_result = runSQLtransactions($delete_sql);
      if($delete_sql_result == '0'){
// $tr['Delete payment, please contact customer service'] = '(x) 刪除支付商請聯絡客服';
        die($tr['Delete payment, please contact customer service']);
      }

    }else{
// $tr['There was a query error deleting the payment provider, please contact customer service'] = '(x) 刪除支付商時發生查詢錯誤，請聯絡客服';
      die($tr['There was a query error deleting the payment provider, please contact customer service']);
    }
  }



} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}
