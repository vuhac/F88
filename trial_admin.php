<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 試玩帳號申請
// File Name:	trial_admin.php
// Author:		Barkley
// Related:		index.php
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
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= $tr['Demo account management'];;
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首頁</a></li>
  <li><a href="#">會員與加盟聯營股東</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


//die("試用維護中系統");

// 有登入，且身份為管理員 R 才可以使用這個功能。
// if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
if(0) {
// STOP  有問題, 需要修正後在上線  2017.8.29

	//var_dump($_SESSION['wallet']);
	// max show records
	$sql_load_limit = ' LIMIT 2000';

	// 列出系統中所有的試用帳號，
	// $trial_list_sql = 'SELECT * FROM "root_membertriallog" WHERE "passwd" IS NOT NULL AND "account" IS NOT NULL '.$sql_load_limit.';';
	$trial_list_sql = 'SELECT id, ip, account, status, account, passwd, logintime, deviceinfo,'." to_char((logintime AT TIME ZONE 'CCT')  , 'YYYY-MM-DD  HH24:MI:SS' ) as cct_logintime, to_char((logintime AT TIME ZONE 'AST')  , 'YYYY-MM-DD  HH24:MI:SS' ) as est_logintime ".' FROM "root_membertriallog" WHERE "passwd" IS NOT NULL AND "account" IS NOT NULL '.$sql_load_limit.';';
	// echo $trial_list_sql;
	// echo $trial_list_sql;

	$list = runSQLall($trial_list_sql);
	// 資料數量

	$show_listrow_html = '';
	// var_dump($list_result);
	if($list[0] >= 1) {

		// 列出資料每一行 for loop

		for($i=1;$i<=$list[0];$i++) {

			// 取出 IP 之前的登入紀錄, 最後一筆資料
			$list_ip = $list[$i]->ip;
			// $list_messages_sql = 'SELECT * FROM "root_membertriallog" WHERE "ip" = '."'$list_ip'".' ORDER BY "id" DESC LIMIT 1;';
			$list_messages_sql = 'SELECT id, count, deviceinfo, '." to_char((logintime AT TIME ZONE 'AST')  , 'YYYY-MM-DD  HH24:MI:SS' ) as est_logintime ".' FROM "root_membertriallog" WHERE "ip" = '."'$list_ip'".' ORDER BY "id" DESC LIMIT 1;';
			$list_messages_result = runSQLall($list_messages_sql);
			$list_messages_html = '<a href="" title="'.$list_messages_result[1]->deviceinfo.'">'.$list_messages_result[1]->est_logintime.'</a>';
			$list_messages_count_html = '<span class="badge">'.$list_messages_result[1]->count.'</span></a>';

			// 對應底下的 xeditable JS
			if($list[$i]->status == 1) {
	  		$xedit_status_html = '<a href="#" id="status" class="trialswitch" data-type="select" data-pk="'.$list[$i]->id.'" data-title="Active/Blocked"><button type="button" class="btn btn-success navbar-btn">Active</button></a>';
			}else{
				$xedit_status_html = '<a href="#" id="status" class="trialswitch" data-type="select" data-pk="'.$list[$i]->id.'" data-title="Active/Blocked"><button type="button" class="btn btn-warning navbar-btn">Blocked</button></a>';
			}

			$show_listrow_html = $show_listrow_html.'
			<tr>
				<td>'.$list[$i]->id.'</td>
				<th><a href="#" title="'.$list[$i]->cct_logintime.'">'.$list[$i]->est_logintime.'</th>
				<td>'.$list[$i]->ip.'</td>
				<td>'.$list[$i]->account.'</td>
				<td>'.$list[$i]->passwd.'</td>
				<td>'.$xedit_status_html.'</td>
				<td>'.$list_messages_html.'</td>
				<td>'.$list_messages_count_html.'</td>
			</tr>
			';
		}
		// 列出資料每一行 for loop -- end
	}

	// 表格欄位名稱
	$table_colname_html = '
	<tr>
		<th>'.$tr['ID'].'</th>
		<th><a href="#" title="'.$tr['The link is China Coast Time'].'">'.$tr['Apply for account time'].'</a>('.$tr['Eastern time'].')</th>
		<th>'.$tr['Source IP'].'</th>
		<th>'.$tr['Account'].'</th>
		<th>'.$tr['Password'].'</th>
		<th>'.$tr['Account status'].'</th>
		<th><a href="#" title="'.$tr['The link is device information'].'">'.$tr['Last login'].'('.$tr['Eastern time'].')</a></th>
		<th>'.$tr['Number of logins'].'</th>
	</tr>
	';

	// 列出資料, 主表格架構
	$show_list_html = '';
	$show_list_html = $show_list_html.'
	<table id="show_list" class="display" cellspacing="0" width="100%">
	<thead>
	'.$table_colname_html.'
	</thead>
	<tfoot>
	'.$table_colname_html.'
	</tfoot>
	<tbody>
	'.$show_listrow_html.'
	</tbody>
	</table>
	';



	// 參考使用 datatables 顯示
	// https://datatables.net/examples/styling/bootstrap.html
	$extend_head = $extend_head.'
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
	';

	// DATA tables jquery plugging -- 要放在 head 內 不可以放 body
	$extend_head = $extend_head.'
	<script type="text/javascript" language="javascript" class="init">
		$(document).ready(function() {
			$("#show_list").DataTable( {
					"paging":   true,
					"ordering": true,
					"info":     true
			} );
		} )
	</script>
	';


	// 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
	$extend_head = $extend_head.'
	<!-- x-editable (bootstrap version) -->
	<link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
	<script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
	';

	// 即時編輯工具 編輯的欄位 JS
	$extend_js = $extend_js."
	<script>
	$(document).ready(function() {
		// for edit
		$('.trialswitch').editable({
			source: [
						{value: 1, text: 'Active'},
						{value: 9, text: 'Blocked'}
				 ],
			url: 'trial_admin_action.php?a=trialswitch',
			success: function(resultdata){
				$( '#preview_result' ).html(resultdata);
			}
		});
	});
	</script>
	";

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
		<div class="col-12 col-md-12">
		'.$show_list_html.'
		</div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';

}else{
	// 沒有登入的顯示提示俊息
	$show_list_html  = '(x)暫停使用, 開發中...';

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_list_html.'
	  </div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
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
