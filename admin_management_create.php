<?php
// ----------------------------------------------------------------------------
// Features:	後台--子帳號管理/建立管理員
// File Name:	admin_management_create.php
// Author:		yaoyuan
// Related:
//    系統主程式：admin_management.php
//    主程式樣版：admin_management_view.php
//    主程式action：admin_management_action.php 
//    新增管理員：admin_management_add.php
//    修改管理員：admin_management_edit.php
//    DB table: root_member
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
require_once dirname(__FILE__) ."/actor_management_lib.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------

if(!(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
  $logger = $tr['You do not have permission to build the sub-account function!'];
  // 您无权限建立子帐号功能!
  echo '<script>alert("'.$logger.'");history.go(-1);</script>';die();
}

// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------

// --------------------------------------------------------------------------
// 取得 get 傳來的變數
// --------------------------------------------------------------------------
$query_sql = '';
$query_chk = 0;
if(isset($_GET)){
  if(isset($_GET['admin_account'])) {
    $account_query = filter_var($_GET['admin_account'], FILTER_SANITIZE_STRING);
    $query_sql    .= '&admin_account='.$account_query;
    $query_chk     = 1;
  }

  if(isset($_GET['actor_name_id'])) {
    $actor_name_id_query = filter_var($_GET['actor_name_id'], FILTER_SANITIZE_STRING);
    $query_sql   .= '&actor_name_id='.$actor_name_id_query;
    $query_chk    = 1;
  }

  if(isset($_GET['sel_status'])) {
    $status_query = filter_var($_GET['sel_status'], FILTER_SANITIZE_STRING);
    $query_sql   .= '&sel_status='.$status_query;
    $query_chk    = 1;
  }
}

if( $query_chk == 0){
  $query_sql = '';
}



// 保護這個頁面的 post 不會被 CSRF 刻意攻擊,CSRF token 有效期間天為單位
$csrftoken = sha1(date('d'));
$_SESSION['csrftoken_valid'] = sha1($csrftoken.$_SESSION['agent']->salt);
// var_dump($csrftoken);
// var_dump($_SESSION['csrftoken_valid']);die();

// render view
$function_title 		= $tr['add administrator'];
// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_title']= $function_title.'-'.$tr['host_name'];



return render(
  __DIR__ . '/admin_management_create_view.php',
  compact(
    'permission_group_map_id',        //角色大分類對映到角色細項
    'permission_id_map_actorid',      //角色id對映角色英文名稱
    'permission_id_map_actorname',    //角色id對映角色英文名稱
    'actor_group_name',               //角色的大分類英文對映中文
    'function_title',
    'csrftoken',
    // '_SESSION[\'csrftoken_valid\']',
    'query_sql',
    'checkable_file_list',            //檔案讀取可勾選之檔案列表
    'classification_order',           //大分類順序
    'is_ops'                          //pencil

  )
);
