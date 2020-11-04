<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 針對 protal_setting.php 執行對應動作
// File Name:	protal_setting.php
// Author:		Yuan
// Related:		服務 protal_setting.php
// DB Table:  root_protalsetting
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";


if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $action = $_GET['a'];
} else {
  die('(x)不合法的測試');
}
//var_dump($_SESSION);
// var_dump($_POST);
//var_dump($_GET);


/**
 * 更新 switch 元件欄位開關狀態
 *
 * @param $data_colname - 功能欄位名稱
 * @param $value - DB 目前的開關狀態
 * @param $setttingname - 會員設定等級
 */
function switch_update_data($data_colname, $value, $setttingname)
{
  if ($value == 'on') {
    $update_value = 'off';
  } elseif ($value == 'off') {
    $update_value = 'on';
  }

  $switch_update_sql = "UPDATE root_protalsetting SET value = '".$update_value."' WHERE setttingname = '".$setttingname."' AND name = '".$data_colname."';";
//  var_dump($switch_update_sql);
  $switch_update_sql_result = runSQL($switch_update_sql);
//  var_dump($switch_update_sql_result);

  // 強制更新前後台memcache資料
  $update_result = memcache_forceupdate();

  if ($switch_update_sql_result == 0) {
    echo "<script>alert('更新失敗，請重新嘗試。');</script>";
    echo '<script>location.reload();</script>';
  }
}

if($action == 'edit_checkbox_switch' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//  var_dump($_POST);

  $data_colname = filter_var($_POST['name'], FILTER_SANITIZE_STRING);

  // 取出該欄位在 DB 內開關狀態
  $protal_setting_list_sql = "SELECT setttingname, value FROM root_protalsetting WHERE name = '$data_colname' AND status = '1';";
//  var_dump($protal_setting_list_sql);
  $protal_setting_list_sql_result = runSQLall($protal_setting_list_sql);
//  var_dump($protal_setting_list_sql_result);

  if ($data_colname == '') {
    die('(x)不合法的測試');
  } else {
    if ($protal_setting_list_sql_result[0] >= 1) {
      switch_update_data($data_colname, $protal_setting_list_sql_result[1]->value, $protal_setting_list_sql_result[1]->setttingname);
    } else {
      die('(x)不合法的測試');
    }
  }

// ----------------------------------------------------------------------------
} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);
  echo 'ERROR';

}

?>
