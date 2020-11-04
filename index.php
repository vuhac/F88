<?php
// ----------------------------------------------------------------------------
// Features:	後台--系統首頁預設轉移到 index.php
// File Name:	index.php
// Author:		Barkley
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

// var_dump($_SESSION);

// var_dump(session_id());
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
// ----------------------------------------------------------------------------
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------

$function_title = 'Entry to HOME';
$extend_head = '<link rel="stylesheet"  href="ui/login_style.css" >';
$extend_js = '';
$indexbody_content = '

<div class="login_welcome" onmouseover="" onclick="javascript:location.href=\'agent_login.php\'" >
  <p>Welcome to '.$config['hostname'].'</p>
  <img src="./ui/loading.gif" width="50px" title="'.$config['hostname'].'" />

</div>
';

// 轉移到 agent_login.php
$indexbody_content = $indexbody_content.'<script>document.location.href="agent_login.php";</script>';

// ----------------------------------------------------------------------------
// 帆布指紋偵測機制 , 可以識別訪客的瀏覽器唯一值。
// ref: http://blog.jangmt.com/2017/03/canvas-fingerprinting.html
// ----------------------------------------------------------------------------
$fingerprintsession_html = '<iframe name="print" frameborder="0" src="fingerprintsession.php" height="0px" width="100%" scrolling="no">
  <p>Your browser does not support iframes.</p>
</iframe>';
// 指紋偵測 iframe
$indexbody_content = $indexbody_content.$fingerprintsession_html;
// ----------------------------------------------------------------------------



// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= '<h1><strong>'.$function_title.'</strong></h1><hr>';
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
include("template/login.tmpl.php");

?>
