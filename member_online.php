<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 前台會員線上人數
// File Name:	member_online.php
// Author:		Barkley
// Related:		對應後台由上繳顯示點選
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/online_lib.php";
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
$function_title 		= $tr['Online members at front stage'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

	$online_index_html = '
	<p align="right">
	<a href="member_online.php" class="btn btn-primary" role="button">'.$tr['Online members at front stage'].'</a>
	<a href="agent_online.php" class="btn btn-light" role="button">'.$tr['Online admin at back stage'].'</a>
	</p>';

	// 表格欄位名稱
	$table_colname_html =<<<HTML
	<tr>
		<th>{$tr['ID']}</th>
		<th>{$tr['Account']}</th>
		<th>{$tr['last update time']}</th>
		<th>{$tr['browse page']}</th>
		<th>{$tr['source IP']}</th>
    	<th>{$tr['FingerPrint']}</th>
		<th>{$tr['device']}</th>
    <th>{$tr['admin']}</th>
	</tr>
HTML;

	// enable sort table
	$sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
	// $sorttablecss = ' class="table table-striped" id="show_list"';

	// 列出資料, 主表格架構
	$show_list_html = '';
	$show_list_html = $show_list_html.'
	<table '.$sorttablecss.'>
	<thead>
	'.$table_colname_html.'
	</thead>
	<tfoot>
	'.$table_colname_html.'
	</tfoot>
	<tbody>

	</tbody>
	</table>
	';


	// 參考使用 datatables 顯示
	// https://datatables.net/examples/styling/bootstrap.html
	$extend_head =<<<HTML
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" class="init">
		$(document).ready(function() {
			// 有分頁效果
			// $("#show_list").DataTable( {
			// 	"paging":   true,
			// 	"ordering": true,
			// 	"info":     true,
			// 	"pageLength": 30,
			// });
			
			var table = $("#show_list").DataTable( {
				"bProcessing": true,
				"bServerSide": true,
				"bRetrieve": true,
				"searching": false,
				"aaSorting": [[ 0, "desc" ]],
				"oLanguage": {
					"sSearch": "{$tr['Account']}",//"会员帐号:",
					"sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
					"sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
					"sZeroRecords": "{$tr['no data']}",//"目前没有资料",
					"sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
					"sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
					"sInfoFiltered": "({$tr['from']}_MAX_{$tr['filtering in data']})",//"(从 _MAX_ 笔资料中过滤)",
					"oPaginate": {
						"sPrevious": "{$tr['previous']}",//"上一页",
						"sNext": "{$tr['next']}",//"下一页"
					}
          		},
				"columnDefs":[
            		{ className: "dt-center","targets": [0,1,2,3,4,5,6,7]}
          		],  
				"ajax":	"member_online_action.php?a=member_detail",
				"columns":[
					{"data": "no"},
					{"data": "account"},
					{"data": "last_time"},
					{"data": "browser_page"},
					{"data": "source_ip"},
					{"data": "fp"},
					{"data": "device"},
					{"data": "admin"}
          		]
			});

			// datatable自動更新
			setInterval( function () {
				table.ajax.reload();
			}, 60000 );// 1分鐘

		});

		// 前台 強制會員登出
		$(document).on('click','.force_logout',function(e){
			e.preventDefault();
			var f_account_name  = $(this).data("logout"); // 帳號
			var f_session_id = $(this).data("session"); // sesssion key

			if(confirm('确定强制登出该会员吗？') == true){
				$.ajax({
					url: 'member_online_action.php?a=f_force_logout',
					type: 'POST',
					data:{
						f_account_name: f_account_name,
						f_session_id: f_session_id
					},
					success:function(result){
						// console.log('success');
						window.location.href="member_online.php";
					},
					error:function(resp){
						console.log('error');
					}
				})
			}

		})

		// 停用會員帳號
		$(document).on('click','.edit_status',function(e){
			e.preventDefault();
			var f_account_name  = $(this).data("logout"); // 帳號
			var member_id = $(this).data("id"); // id
			var f_session_id = $(this).data("session"); // sesssion key

			if(confirm('确定停用该会员帐号吗？') == true){
				$.ajax({
					url: 'member_online_action.php?a=edit_account_status',
          type: 'POST',
          data: ({
            f_account_name: f_account_name,
            member_id: member_id,
						f_session_id:f_session_id
          }),
          success: function(response){
            // console.log('success');
            window.location.href="member_online.php";
          },
          error: function (error) {
            $('#preview_result').html(error);
          },
				})
			}

		})

		// 前台 連結到會員詳細資料
		$(document).on('click','.link_detail',function(e){
			e.preventDefault();
			var account_id = $(this).data("account");

			var member_detail_url = 'member_account.php?a='+account_id;
			var new_window = window.open( member_detail_url);

		})

		</script>
HTML;


	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
		<div class="col-12 col-md-12">
		'.$online_index_html.'
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
	$show_list_html  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

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