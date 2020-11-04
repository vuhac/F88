<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 公告編輯器的動作處理
// File Name:	announcement_editor_action.php
// Author:    Pia
// Related:   announcement_editor.php
// DB Table:
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

// ----------------------------------------------------------------------------
// 新增 or 修改公告訊息
//-----------------------------------------------------------------------------
if($action == 'edit_offer' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
//  var_dump($_POST);

//公告內容
$editor_data_id = filter_var($_POST['editor_data_id'], FILTER_SANITIZE_NUMBER_INT);

$editor_data_remove_jstag     = preg_replace('/<.*script.*>/', '', $_POST['editor_data']);
$editor_data_remove_iframetag = preg_replace('/<.*iframe.*>/', '', $editor_data_remove_jstag);
$editor_data_encode           = trim(htmlspecialchars($editor_data_remove_iframetag, ENT_QUOTES));

//ID
$announcement_id       = filter_var($_POST['announcement_id'], FILTER_SANITIZE_NUMBER_INT);
//名稱
// $announcement_name     = filter_var($_POST['announcement_name'], FILTER_SANITIZE_STRING);
//標題
$announcement_title    = filter_var($_POST['announcement_title'], FILTER_SANITIZE_STRING);
//開始日期
$start_day             = filter_var($_POST['start_day'], FILTER_SANITIZE_STRING);
//結束日期
$end_day               = filter_var($_POST['end_day'], FILTER_SANITIZE_STRING);
//狀態
$announcement_isopen   = filter_var($_POST['announcement_isopen'], FILTER_SANITIZE_NUMBER_INT);
//站內信件顯示公告
$announcement_showinmessage   = filter_var($_POST['announcement_showinmessage'], FILTER_SANITIZE_NUMBER_INT);


if ($start_day != '') {
  $start_day = $start_day.' 00:00:00';
}

if ($end_day != '') {
  $end_day = $end_day.' 23:59:59';
}
//if ($announcement_name != '' AND $announcement_title != '' AND $editor_data_encode != '') {
if ($announcement_title != '' AND $editor_data_encode != '') {

  if ($start_day != '' AND $end_day != '' AND $start_day < $end_day) {

    // 如果沒有這筆優惠資料, 傳進來的 id 會是空的
    if ($announcement_id == '') {
      $select_announcement_sql = "SELECT * FROM root_announcement WHERE id = NULL AND status != '2';";
    } else {
      $select_announcement_sql = "SELECT * FROM root_announcement WHERE id = '".$announcement_id."' AND status != '2';";
    }

    $select_announcement_sql_result = runSQL($select_announcement_sql);

    if ($select_announcement_sql_result == 0) {

      // $sql = 'INSERT INTO root_announcement ("operator", "title", "name", "effecttime", "endtime", "status", "content","showinmessage")';
      // $sql = $sql."
      // VALUES ('".$_SESSION['agent']->account."', '".$announcement_title."', '".$announcement_name."', '".$start_day."', '".$end_day."', '".$announcement_isopen."', '".$editor_data_encode."', '".$announcement_showinmessage."');";
      // $insert_result = runSQL($sql);

      $sql = 'INSERT INTO root_announcement ("operator", "title", "effecttime", "endtime", "status", "content","showinmessage")';
      $sql = $sql."
      VALUES ('".$_SESSION['agent']->account."', '".$announcement_title."', '".$start_day."', '".$end_day."', '".$announcement_isopen."', '".$editor_data_encode."', '".$announcement_showinmessage."');";
      $insert_result = runSQL($sql);

      $logger = $insert_result ? '公告新增成功。' : '公告新增失敗。';
    } else {
      //$sql = "UPDATE root_announcement SET operator = '".$_SESSION['agent']->account."', title='".$announcement_title."', name = '".$announcement_name."', effecttime = '".$start_day."', endtime = '".$end_day."', status = '".$announcement_isopen."', content = '".$editor_data_encode."', showinmessage = '".$announcement_showinmessage."' WHERE id = '".$announcement_id."';";
      $sql = "UPDATE root_announcement SET operator = '".$_SESSION['agent']->account."', title='".$announcement_title."', effecttime = '".$start_day."', endtime = '".$end_day."', status = '".$announcement_isopen."', content = '".$editor_data_encode."', showinmessage = '".$announcement_showinmessage."' WHERE id = '".$announcement_id."';";
      $update_result = runSQL($sql);

      $logger = $update_result ? '公告更新成功。' : '公告更新失敗。';
    }

    echo '<script>alert("'.$logger.'");location.reload();</script>';
  } else {
    $logger = '未選擇上架時間，或開始時間大於結束時間，請選擇正確時間。';
    echo '<script>alert("'.$logger.'");</script>';
  }

} else {
  $logger = '請確認公告名稱、公告標題及公告內容是否正確填入。';
  echo '<script>alert("'.$logger.'");</script>';
}

}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);
  echo 'ERROR';

}

?>
