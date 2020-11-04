<?php
// ----------------------------------------------------------------------------
// Features:	後台--系統管理/角色管理
// File Name:	member_import.php
// Author:		Ian
// Related:
//    member_import_view.php  member_import_action.php
//    DB table: root_member, root_member_wallets
//    member_import：批次汇入会员资料
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 傳到view所需引入函式
require_once dirname(__FILE__) ."/lib_view.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 检查是否有权限操作
if(!(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
        echo '<script>alert("您无此操作权限!");history.go(-1);</script>';die();
}

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------

// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -----------------------------------------

// --------------------------------------------------------------------------
// 取得 get 傳來的變數
// --------------------------------------------------------------------------
$query_sql = '';
$query_chk = 0;
if(isset($_GET)){
  if(isset($_GET['actor_name'])) {
    $query_sql = $query_sql.'&actor_name='.filter_var($_GET['actor_name'], FILTER_SANITIZE_STRING);
    $account_query = filter_var($_GET['actor_name'], FILTER_SANITIZE_STRING);
    $query_chk = 1;
  }
  if(isset($_GET['sel_status'])) {
    $query_sql = $query_sql.'&sel_status='.filter_var($_GET['sel_status'], FILTER_SANITIZE_NUMBER_INT);
    $casino_query = filter_var($_GET['sel_status'], FILTER_SANITIZE_STRING);
    $query_chk = 1;
  }
}

if( $query_chk == 0){
  $query_sql = '';
}
// var_dump($_SESSION);die();



// render view
$function_title 		= $tr['Member import management'];
// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_title']= $function_title.'-'.$tr['host_name'];

return render(
  __DIR__ . '/member_import_view.php',
  compact(
    'function_title',
    'query_sql'
    //'allow_user_html'

  )
);
