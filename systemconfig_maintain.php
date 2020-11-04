<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 维运控管
// File Name:	systemconfig_maintain.php
// Author:    Barkley
// Related:
// DB Table:
// Log:
// ----------------------------------------------------------------------------
// 限制管理員才可以進入
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= '维运控管';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首页</a></li>
  <li><a href="#">维运功能</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------



// ----------------------------------------------------------------------------
// 本頁面只允許 $su['superuser'] 的帳號, 其餘一律沒有權限。
// 允許使用者的列表
$allow_user_html = '';
foreach ($su['superuser'] as &$value) {
  $allow_user_html = $allow_user_html.', '.$value;
}
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account, $su['superuser'])) {
  $extend_head				= $extend_head.'<!-- Jquery UI js+css  -->
                          <script src="in/jquery-ui.js"></script>
                          <link rel="stylesheet"  href="in/jquery-ui.css" >
                          <!-- Jquery blockUI js  -->
                          <script src="./in/jquery.blockUI.js"></script>
                          <!-- Datatables js+css  -->
                          <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
                          <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
                          <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
                          <script type="text/javascript" language="javascript" class="init">
                            $(document).ready(function() {
                              $("#systeminfo").DataTable( {
                                  "paging":   false,
                                  "ordering": false,
                                  "info":     false
                              } );
                            } )
                          </script>
                          ';


  // 內容
  $content_html = 'TODO.....';


  // 排版輸出
  $indexbody_content = $indexbody_content.'
  <div class="row">
	  <div class="col-12 col-md-12">
      <div class="alert alert-info">
      此页面只允许站长管理员 '.$allow_user_html.' 帐号存取
      </div>
    </div>
  </div>
  <div class="row">
	  <div class="col-12 col-md-12">
    '.$content_html.'
    </div>
  </div>
  ';

}else{

  // 沒有登入權限的處理
  $indexbody_content = $indexbody_content.'
  <br>
  <div class="row">
	  <div class="col-12 col-md-12">
      <div class="alert alert-danger">
      此页面只允许特定帐号 '.$allow_user_html.' 帐号存取
      </div>
    </div>
  </div>
  ';

}





// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");

?>
