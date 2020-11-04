<?php
// ----------------------------------------------------------------------------
// Features:	後台--刪除公司入帳 對應 deposit_company_config.php
// File Name:	deposit_company_config_action.php
// Author:		Pia
// Related:   服務 deposit_company_config.php
// DB Table:  root_deposit_company
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
//  var_dump($_GET); $tr['Illegal test'] = '(x)不合法的測試。';
} else {
  die($tr['Illegal test']);
}
// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);



// ----------------------------------------------------------------------------
// 刪除司入款帳戶 start
// ----------------------------------------------------------------------------
if($action == 'delete' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//  var_dump($_POST);

//因為是使用 serialize 來傳值所以會連name一起傳過來 ex: delete_checkbox=1&delete_checkbox=2
//所以需要將前面的一起傳過來 name 移除掉 並有多個值的話 用 & 來切開
$id_num = $_POST['edit_id_num'];

$id_num = str_replace('delete_checkbox=','',$id_num);

$id_num = explode("&", $id_num);

$delete_sql = '';

//確認要刪除的ID是否都存在
$search_sql = "SELECT * FROM root_deposit_company WHERE status = '1' OR status = '0';";
$search_sql_result = runSQLALL($search_sql);

for($i=1;$i<=$search_sql_result[0];$i++){
  $check_id[$search_sql_result[$i]->id] = $search_sql_result[$i]->id;
}

for($i=0;$i<count($id_num);$i++){
  $delete_id = filter_var($id_num[$i], FILTER_SANITIZE_NUMBER_INT);

  if(isset($check_id[$delete_id])){
    $delete_sql = $delete_sql."UPDATE root_deposit_company SET status = '2' WHERE id = '".$delete_id."';";
  }
}

//只要一次有兩行以上的sql的話，不能使用 runSQLall 須使用 runSQLtransactions
$delete_sql_result = runSQLtransactions($delete_sql);
// $tr['Please contact customer service'] = '(x)請聯絡客服。';
if($delete_sql_result == '0'){
  die($tr['Please contact customer service']);
}



// ----------------------------------------------------------------------------
// 刪除公司入款帳戶 end
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}
