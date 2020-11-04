<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員總覽
// File Name:	member_overview.php
// Author:		
// Related:   
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

//var_dump($_SESSION);
// var_dump(session_id());

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
$extend_head = '';
$extend_js = '';
$indextitle_content = '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>' . $tr['Search criteria'];
$indexbody_content = '';
$paneltitle_content = '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>' . $tr['Query results'];
// $exportExcelBtn = '	<button id="excelBtn" style="float: right;margin-bottom: auto" class="btn btn-success btn-sm">'. $tr['export excel'] .'</button>';
// $paneltitle_content .= $exportExcelBtn;
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li class="active">會員總覽</li>
</ol>';
// ----------------------------------------------------------------------------
// HTML;
$extend_js = <<<HTML
<script src="./in/jig_manage/js/chunk-vendors.js"></script>
<script src="./in/jig_manage/js/chunk-common.js"></script>
<script src="./in/jig_manage/js/memberOverview.js"></script>
HTML;
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $tr['member overview'] . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;

// 兩欄分割--左邊
$tmpl['indextitle_content'] = $indextitle_content;
$tmpl['indexbody_content'] = $indexbody_content;
// 兩欄分割--右邊
$tmpl['paneltitle_content'] = $paneltitle_content;
$tmpl['panelbody_content'] = '';
// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/jigmanage.tmpl.php";

?>
