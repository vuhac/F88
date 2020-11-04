<?php
// ----------------------------------------------------------------------------
// Features:	配合 trial_admin.php 的動作操作
// File Name:	trial_admin_action.php
// Author:		Barkley
// Related:
// Log:
// 2016.10.18
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 有 $_SESSION['agent'] (代表為後台變數)及 a 兩個變數，才繼續工作. 避免遠端不正常的攻擊行為。
if(isset($_GET['a']) AND isset($_SESSION['agent']) ) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING,FILTER_FLAG_ENCODE_AMP);
}else{
	$logger = '(x)Illegal testing';
  die($logger);
}
// ----------------------------------------------------------------------------
// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);

// ----------------------------------------------------------------------------
// 執行的功能選擇
// ----------------------------------------------------------------------------
if($action == 'trialswitch') {
// ----------------------------------------------------------------------------
// trial_admin.php 管理測試帳號，動作：切換是用帳號是否開啟
// ----------------------------------------------------------------------------

  $name   = filter_var($_POST['name'], FILTER_SANITIZE_STRING,FILTER_FLAG_ENCODE_AMP);
  $value  = filter_var($_POST['value'], FILTER_SANITIZE_NUMBER_INT);
  $pk     = filter_var($_POST['pk'], FILTER_SANITIZE_NUMBER_INT);
  if($name == 'status' AND  ($value == '1' OR $value == '9')) {
    $sql = "UPDATE root_membertriallog SET status = '$value' WHERE id = '$pk';";
    //var_dump($sql);
    $r = runSQL($sql);
    if($r == 1) {
      $logger = '資料已經更新';
      echo $logger.'<script>window.location.reload();</script>';
    }else{
      $logger = '資料更新失敗';
    }
    echo $logger;
  }


// ----------------------------------------------------------------------------
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    var_dump($_POST);
		// echo '<script>location.reload();</script>';


}
// ----------------------------------------------------------------------------

?>
