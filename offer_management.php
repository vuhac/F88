<?php
// ----------------------------------------------------------------------------
// Features: 優惠管理
// File Name:	offer_management.php
// Author: Neil
// Related:
// Log:
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

// 功能標題，放在標題列及meta
$function_title 		= $tr['promotion Offer Editor'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';

$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';

// if(!isset($_SESSION['agent']) || !in_array($_SESSION['agent']->account,$su['ops'])) {
//   header('Location:./home.php');
//   die();
// }

$indexbody_content = <<<HTML
<table id="show_list" class="display" width="100%">
  <thead>
    <tr>
      <th>{$tr['ID']}</th>
      <th>{$tr['domain']}</th>
      <!-- <th>子网域</th> -->
      <th>{$tr['domain status']}</th>
      <th>{$tr['operation']}</th>
    </tr>
  </thead>
  <tfoot>
    <tr>
      <th>{$tr['ID']}</th>
      <th>{$tr['domain']}</th>
      <!-- <th>子网域</th> -->
      <th>{$tr['domain status']}</th>
      <th>{$tr['operation']}</th>
    </tr>
  </tfoot>
</table>
HTML;

$extend_head = '
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
';

$extend_js .= <<<JS
<script>
$(document).ready(function() {
  $("#show_list").DataTable({
      "order": [[ 0, "asc" ]],
      "bretrieve": true,
      "bserverSide": true,
      "bretrieve": true,
      "bsearching": true,
      // "pageLength": 1,
      "ajax": "offer_management_init_action.php?a=init",
      "columns": [
        { "data": "id" },
        { "data": "admain" },
        // { "data": "subadmain" },
        { "data" : "admainStatus"},
        { "data": "operate" }
      ]
  });
});
</script>
JS;


// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']    = $tr['host_descript'];
$tmpl['html_meta_author']         = $tr['host_author'];
$tmpl['html_meta_title']          = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']               = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']              = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']       = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']        = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");
