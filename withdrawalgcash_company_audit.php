<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 現金(GCASH)取款申請審核
// File Name:	withdrawalgcash_company_audit.php
// Author:    Yuan
// Related:		對應前台現金(GCASH)線上取款
// Log:
// ----------------------------------------------------------------------------
/*
主要操作的DB表格：
root_withdrawgcash_review 現金申請審查表
root_member_gcashpassbook 現金存款紀錄

前台
wallets.php 錢包顯示連結--取款、存簿都由這裡進入。
transactiongcash.php 前台現金的存簿
withdrawapplicationgcash.php 現金(GCASH)線上取款前台程式, 操作界面
withdrawapplicationgcash_action.php 現金(GCASH)線上取款前台動作, 會先預扣提款款項

後台
member_transactiongcash.php 後台的會員GCASH轉帳紀錄,預扣款項及回復款項會寫入此紀錄表格
withdrawalgcash_company_audit.php  後台GCASH提款審查列表頁面
withdrawalgcash_company_audit_review.php  後台GCASH提款單筆紀錄審查
withdrawalgcash_company_audit_review_action.php 後台GCASH提款審查用的同意或是轉帳動作SQL操作
*/
// ----------------------------------------------------------------------------


session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/deposit_withdrawal_company_audit_lib.php";

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
// 功能標題，放在標題列及meta $tr['Affiliate withdrawal application board'] = '加盟金取款申請看板';
$function_title 		= $tr['Affiliate withdrawal application board'];
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
  <li><a href="#">'.$tr['Account Management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// 查詢欄（左）title及內容
$indextitle_content 	= '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>'.$tr['Search criteria'];
// 結果欄（右）title及內容
$paneltitle_content 	= '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>'.$tr['Query results'].'<p id="cashwithdrawal_mq" class="mb-0 ml-auto float-right" style="color: #dc3545; display: none;"></p>';
$panelbody_content		= '';
// ----------------------------------------------------------------------------


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

	// datepicker
	$search_time = time_convert();
	// $current = gmdate('Y-m-d',time()+ -4*3600); // 今天
	// // $current_date = gmdate('Y-m-d H:i',time() + -4*3600); // 結束時間帶上美東目前時間
	// $current_date = gmdate('Y-m-d',time() + -4*3600).' 23:59'; // 結束時間帶上美東目前時間

	// $default_min_date = gmdate('Y-m-d',strtotime('- 7 days')); // 7天

	// $thisweekday = date("Y-m-d", strtotime("$current_date - ".date('w',strtotime($current_date))."days"));
	// $yesterday = date("Y-m-d", strtotime("$current_date - 1 days"));

	// // 上週
	// $lastweekday_s = date("Y-m-d", strtotime("$current_date - ".intval(date('w',strtotime($current_date))+7)."days"));
	// $lastweekday_e = date("Y-m-d", strtotime("$thisweekday - 1 days"));

	// $thismonth = date("Y-m", strtotime($current_date));

	// // 上個月
	// $lastmonth = date('Y-m',strtotime(date('Y-m-1').'-1 month'));
	// $lastmonth_e = date('Y-m-d',strtotime(date('Y-m-1').'-1 day'));

	// 交易單號
	$indexbody_content .= <<<HTML
		<div class="row">
			<div class="col-12"><label>{$tr['Transaction order number']}</label></div>
			<div class="col-12 form-group">
				<input type="text" class="form-control" name="transation_id" id="transation_query" placeholder="{$tr['Transaction order number']}" value="">
			</div>
		</div>
HTML;

	// 查詢條件 - 帳號
	$indexbody_content .= <<<HTML
		<div class="row">
			<div class="col-12"><label>{$tr['Account']}</label></div>
			<div class="col-12 form-group">
				<input type="text" class="form-control" name="account_query" id="account_query" placeholder="{$tr['Account']}" value="">
			</div>
		</div>
HTML;

	// 所屬代理
	$indexbody_content .= <<<HTML
		<div class="row">
			<div class="col-12"><label>{$tr['Affiliated agent']}</label></div>
			<div class="col-12 form-group">
				<input type="text" class="form-control" name="agent_query" id="agent_query" placeholder="{$tr['Affiliated agent']}" value="">
			</div>
		</div>
HTML;

	// 查詢條件 - 申請時間
	$indexbody_content .= <<<HTML
		<div class="row">
			<div class="col-12 d-flex">
				<label>{$tr['application time']}</label>
				<div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
				<button type="button" class="btn btn-secondary first">{$tr['grade default']}</button>
					<div class="btn-group btn-group-sm" role="group">
						<button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						</button>
						<div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
						<a class="dropdown-item" onclick="settimerange('{$search_time['thisweekday']} 00:00',getnowtime());">{$tr['This week']}</a>
						<a class="dropdown-item" onclick="settimerange('{$search_time['thismonth']}-01 00:00',getnowtime());">{$tr['this month']}</a>
						<a class="dropdown-item" onclick="settimerange('{$search_time['current']} 00:00',getnowtime());">{$tr['Today']}</a>
						<a class="dropdown-item" onclick="settimerange('{$search_time['yesterday']} 00:00','{$search_time['yesterday']} 23:59');">{$tr['yesterday']}</a>
						<a class="dropdown-item" onclick="settimerange('{$search_time['lastweekday_s']} 00:00','{$search_time['lastweekday_e']} 23:59');">{$tr['Last week']}</a>
						<a class="dropdown-item" onclick="settimerange('{$search_time['lastmonth']}-01 00:00','{$search_time['lastmonth_e']} 23:59');">{$tr['last month']}</a>
						</div>
					</div>
				</div>
			</div>
			<div class="col-12 form-group rwd_doublerow_time">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text">{$tr['start']}</span>
					</div>
					<input type="text" class="form-control" name="sdate" id="query_date_start_datepicker"
							placeholder="ex:2017-01-20" value="{$search_time['default_min_date']}{$search_time['min']}">
				</div>
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text">{$tr['end']}</span>
					</div>
					<input type="text" class="form-control" name="edate" id="query_date_end_datepicker"
						placeholder="ex:2017-01-20" value="{$search_time['current']}{$search_time['max']}">
				</div>
			</div>
		</div>
HTML;

// 	// 查詢條件 - 申請時間
// 	$indexbody_content .= <<<HTML
// 		<div class="row">
// 			<div class="col-12"><label>{$tr['application time']}</label></div>
// 			<div class="col-12">
// 				<div class="input-group">

// 				<div class="input-group">
// 					<input type="text" class="form-control" name="sdate" id="query_date_start_datepicker"
// 						placeholder="ex:2017-01-20" value="{$default_min_date} 00:00">
// 					<span class="input-group-addon" id="basic-addon1">~</span>
// 					<input type="text" class="form-control" name="edate" id="query_date_end_datepicker"
// 						placeholder="ex:2017-01-20" value="{$current_date}">
// 				</div>

// 				<div class="btn-group btn-group-sm mr-1 my-1" role="group" aria-label="Button group with nested dropdown">
// 					<button type="button" class="btn btn-secondary" onclick="settimerange('{$thisweekday} 00:00',getnowtime());">{$tr['This week']}</button>
// 					<button type="button" class="btn btn-secondary" onclick="settimerange('{$thismonth}-01 00:00',getnowtime());">{$tr['this month']}</button>
// 					<div class="btn-group btn-group-sm" role="group">
// 						<button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
// 						</button>
// 						<div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
// 						<a class="dropdown-item" onclick="settimerange('{$current} 00:00',getnowtime());">{$tr['Today']}</a>
// 						<a class="dropdown-item" onclick="settimerange('{$yesterday} 00:00','{$yesterday} 23:59');">{$tr['yesterday']}</a>
// 						<a class="dropdown-item" onclick="settimerange('{$lastweekday_s} 00:00','{$lastweekday_e} 23:59');">{$tr['Last week']}</a>
// 						<a class="dropdown-item" onclick="settimerange('{$lastmonth}-01 00:00','{$lastmonth_e} 23:59');">{$tr['last month']}</a>
// 						</div>
// 					</div>
// 				</div>
// 				</div>
// 			</div>
// 		</div>
// 		<br>
// HTML;

	// 金額
	$indexbody_content .= <<<HTML
	<div class="row">
		<div class="col-12"><label>{$tr['amount']}</label></div>
		<div class="col-12 form-group">
		<div class="input-group">
			<input type="number" class="form-control" step=".01" placeholder='' id="amount_lower" name="amount_lower">
			<span class="input-group-addon" id="basic-addon1">~</span>
			<input type="number" class="form-control" step=".01" placeholder='' id="amount_upper" name="amount_upper">
		</div>
		</div>
	</div>
HTML;

	// 查詢條件 - IP $tr['Query IP'] = '查詢IP';
	$indexbody_content .= <<<HTML
		<div class="row">
			<div class="col-12"><label>{$tr['ip address']}</label></div>
			<div class="col-12 form-group">
				<input type="text" class="form-control" name="ip" id="ip_query" placeholder="ex:192.168.100.1"
				value="">
			</div>
		</div>
HTML;

	// 審核狀態
	$review_status = [0=>$tr['Be Cancel'],1=>$tr['Qualified'],2=>$tr['Unreviewed']];
	$status_html = '';

	foreach ($review_status as $status_key => $status_value){
	$status_checked=($status_key==2)? ' checked':'';
	$status_html.=<<<HTML
		<div class="ck-button" >
		<label>
			<input type="checkbox" id="status_sel_{$status_key}" name="status_sel" value="{$status_key}" {$status_checked}>
			<span class="status_sel_{$status_key}">{$status_value}</span>
		</label>
		</div>
HTML;
	}

	$indexbody_content .=<<<HTML
	<div clss="row">
		<div class="col-12">
			<div class="row border">
				<h6 class="betlog_h6 text-center">{$tr['Approval Status']}</h6>
			</div>
		</div>
		<div class="col-12">
			<div class="row border">
			{$status_html}
			</div>
		</div>
	</div>
HTML;

	// 查詢按鈕
	$indexbody_content .= <<<HTML
		<hr>
		<div class="row">
			<div class="col-12 col-md-12">
				<button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit">{$tr['Inquiry']}</button>
			</div>
		</div>
HTML;
	// 查詢欄（左）內容 END-------------------------------------------

	/*
    // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
      $tz = $_SESSION['agent']->timezone;
    }else{
      $tz = '+08';
    }
    // 轉換時區所要用的 sql timezone 參數
    $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
    $tzone = runSQLALL($tzsql);
    // var_dump($tzone);
    if($tzone[0]==1){
      $tzonename = $tzone[1]->name;
    }else{
      $tzonename = 'posix/Etc/GMT-8';
    }
    // to_char((enrollmentdate AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as enrollmentdate_tz
  */

	// 列出系統中所有的待審查 agent 帳號及通過的帳號列表
	// -------------------------------------
	// $list_sql = 'SLECT *,'." to_char((applicationtime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as applicationtime_tz ".' FROM "root_agent_review" WHERE "status" IS NOT NULL AND "status" != 1 '.$sql_load_limit.';';


		// 列出資料每一行 for loop -- end
	// }
	// $tr['agent review seq'] = '申请单号';$tr['Member Account'] = '會員帳號'; $tr['agent review apply time'] = '申請的時間';$tr['agent review name'] = '姓名'; $tr['agent review phone'] = '電話'; $tr['IP/fingerprint'] = 'IP/指紋' $tr['withdrawal amount'] = '提款金額'; $tr['Fee'] = '手續費';$tr['administrative audit fee'] = '行政稽核費'; $tr['audit'] = '審覈';

	// 2020-03-17
	$table_colname_html = '
	<tr>
		<th>'.$tr['first_store_report-member-member_content-upper_agent'].'</th>
		<th>'.$tr['Account'].'</th>
		<th>'.$tr['Transaction order number'].'</th>
		<th>'.$tr['amount'].'</th>
		<th>'.$tr['Fee'].'</th>
		<th>'.$tr['apply_time'].'</th>
		<th>'.$tr['Payment method'].'</th>
		<th>'.$tr['ip address'].'</th>
		<th>'.$tr['audit'].'</th>
		<th>'.$tr['detail'].'</th>
	</tr>
	';

	// enable sort table
	$sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
	// $sorttablecss = ' class="table table-striped" ';

	/*
    // 提示管理人員如何進行操作。
    // 申請成為代理商的費用
    $become_agent_payment_cash = 50000;
    // 猶豫期(天)
    $become_agent_hesitation_period = 3;
    $become_agent_payment_cash_html = $deposit_count_amount_html = money_format('%i', $become_agent_payment_cash);
    */

	// 提示管理人員如何進行操作。$tr['GCASH Application for Withdrawal'] = '加盟金取款申請審核';
	$show_tips_html = '';
	// <div class="alert alert-success">
	// * '.$tr['GCASH Application for Withdrawal'].'<br>
	// </div>

	// 列出資料, 主表格架構
	$show_list_html = $show_tips_html;
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
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';

	// 表格放入右邊結果
	$panelbody_content = $show_list_html;


	// 參考使用 datatables 顯示
	// https://datatables.net/examples/styling/bootstrap.html
	$extend_head = $extend_head.'
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
	<link rel="stylesheet" type="text/css" href="ui/style_seting.css">
	<!-- jquery datetimepicker js+css -->
	<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
	<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
	';

	// DATA tables jquery plugging -- 要放在 head 內 不可以放 body
	// $extend_head = $extend_head.'
	// <script type="text/javascript" language="javascript" class="init">
	// 	$(document).ready(function() {
	// 		$("#show_list").DataTable( {
	// 				"paging":   true,
	// 				"ordering": true,
	// 				"info":     true,
	// 				"order": [[ 5, "desc" ]],
	// 				"pageLength": 10
	// 		});
	// 		$(".load_datatble_animate").hide();
	// 	});
	// </script>
	// ';


	// 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
	$extend_head = $extend_head.'
	<!-- x-editable (bootstrap version) -->
	<link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
	<script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
	';

	$extend_js = <<<JS
  <script>
	$(document).on("click",'.agreen_ok',function(){
		var id = $(this).val();
		$('#agreen_ok'+id).attr('disabled', 'disabled');
		$('#agreen_cancel'+id).attr('disabled', 'disabled');

		var r = confirm('{$tr['Are you sure to agree to this withdrawal']}?');
		if(r == true){
			$.post('withdrawalgcash_company_audit_review_action.php?a=withdrawalgcash_submit',
				{
					withdrawapplgcash_id: id
				},
				function(result){
					$('#preview_result').html(result);
				}
			)
		}else{
			$('#agreen_ok'+id).removeAttr('disabled');
			$('#agreen_cancel'+id).removeAttr('disabled');
		}
	});

	$(document).on("click",'.agreen_cancel',function(){
		var id = $(this).val();
		$('#agreen_ok'+id).attr('disabled', 'disabled');
		$('#agreen_cancel'+id).attr('disabled', 'disabled');

		var r = confirm('{$tr['Are you sure to cancel this withdrawal request']}?');
		if(r == true){
			$.post('withdrawalgcash_company_audit_review_action.php?a=withdrawalgcash_cancel',
				{
					withdrawapplgcash_id: id
				},
				function(result){
					$('#preview_result').html(result);
				}
			)
		}else{
			$('#agreen_ok'+id).removeAttr('disabled');
			$('#agreen_cancel'+id).removeAttr('disabled');
		}
	});
  </script>
JS;
	$extend_js.=<<<HTML
	<script type="text/javascript" language="javascript" class="init">
	$(document).ready(function(){
		if (location.search == '?unaudit'){
			url = "withdrawalgcash_company_audit_review_action.php?a=get_result&account=&agent=&sdate=2010-01-30%2000:00&edate="+moment().tz('America/St_Thomas').format('YYYY-MM-DD')+"%2359:59&amount_lower=&amount_upper=&ip=&transaction_id=&status_qy[]=2"
		} else{
			url = "withdrawalgcash_company_audit_review_action.php?a=get_init"
		}
		$("#show_list").DataTable({
			"bProcessing": true,
			"bServerSide": true,
			"bRetrieve": true,
			"searching": false,
			"aaSorting": [[ 6, "desc" ]],
			"oLanguage": {
				"sSearch": "{$tr['search'] }",//"搜索:",
				"sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
				"sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
				"sZeroRecords": "{$tr['no data']}",//"没有匹配结果",
				"sInfo": "{$tr['Display']} _START_ {$tr['to']} _END_ {$tr['result']},{$tr['total']} _TOTAL_ {$tr['item']}",//"显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
				"sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
				"sInfoFiltered": "({$tr['from']} _MAX_ {$tr['filtering in data']})"//"(由 _MAX_ 项结果过滤)"
			},
			"ajax":{
				"url":url
			},
			"columns":[
				{"data":"agent","orderable":false},
				{"data":"account"},
				{"data":"transation_id"},
				{"data":"amount"},
				{"data":"fee_amount"},
				{"data":"application_time"},
				{"data":"type","orderable":false},
				{"data":"ip_address"},
				{"data":"audit"},
				{"data":"detail","orderable":false}
			]
		})
		// $(".load_datatble_animate").hide();
	})
	// 搜尋
	$("#submit_to_inquiry").click(function(){
		var transaction_id = $("#transation_query").val();
		var account = $("#account_query").val();
		var agent = $("#agent_query").val();
		var query_date_start_datepicker = $("#query_date_start_datepicker").val();
		var query_date_end_datepicker = $("#query_date_end_datepicker").val();
		// 出款金額
		var amount_lower = $("#amount_lower").val();
		var amount_upper = $("#amount_upper").val();
		var ip_query = $("#ip_query").val();

		// 當全選或全不選注單狀態，查詢條件為空
		if (
			($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == 0 ||
			($('input[name=status_sel]').length - $('input[name=status_sel]:checked').length) == $('input[name=status_sel]').length )
		{
			var status_query  = "";
		}else{
			var status_query  = "";
			$("input:checkbox:checked[name=\"status_sel\"]").each( function(){
				status_query=status_query+"&status_qy[]="+$(this).val();
			});
		}

		var url_data ="&account="+account+"&agent="+agent+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+"&amount_lower="+amount_lower+"&amount_upper="+amount_upper+"&ip="+ip_query+"&transaction_id="+transaction_id+status_query;

		// console.log(url_data);
		$("#show_list").DataTable()
		.ajax.url("withdrawalgcash_company_audit_review_action.php?a=get_result"+url_data)
		.load();
		// $.unblockUI();

	})

	function getnowtime(){
		// var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD HH:mm');
		var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+ ' 23:59';


		return NowDate;
	}
	// 本日、昨日、本周、上周、上個月button
	function settimerange(sdate,edate){
		$("#query_date_start_datepicker").val(sdate);
		$("#query_date_end_datepicker").val(edate);
	}

	// datetimepicker
	$("#query_date_start_datepicker" ).datetimepicker({
		showButtonPanel: true,
		changeMonth: true,
		changeYear: true,
		maxDate: '{$search_time['current']}',
		timepicker: true,
		format: "Y-m-d H:i",
		step:1
	});
	$("#query_date_end_datepicker" ).datetimepicker({
		showButtonPanel: true,
		changeMonth: true,
		changeYear: true,
		maxDate: '{$search_time['current']}',
		timepicker: true,
		format: "Y-m-d H:i",
		step:1
	});
	</script>
HTML;

$extend_head .= <<<HTML
	<style>
	.ck-button {
		margin:0px;
		overflow:auto;
		float:left;
		width: 33.33%;
	}
	.ck-button:hover {
		border-color: #007bffaa;
		background-color: #007bffaa;
		color: #fff;
	}

	.ck-button label {
		float:left;
		width: 100%;
		height: 100%;
		margin-bottom:0;
		background-color: transparent;
		transition: all 0.2s;
	}

	.ck-button label span {
		text-align:center;
		display:block;
		font-size: 15px;
		line-height: 38px;
	}

	.ck-button label input {
		position:absolute;
		z-index: -5;
	}

	.ck-button input:checked + span {
		border-color: #007bff;
		background-color: #007bff;
		color: #fff;
	}
	.ck-button:nth-child(3n+2) label{
		border:1px solid #D0D0D0;
		border-top-style: none;
		border-bottom-style: none;
	}
	</style>

HTML;
	/*
    // 即時編輯工具 編輯的欄位 JS
    $extend_js = $extend_js."
    <script>
    $(document).ready(function() {
      // for edit
      $('.notes').editable({
        url: 'agent_review_action.php?a=agent_review_submit',
        rows: 6,
        success: function(resultdata){
          $( '#preview_result' ).html(resultdata);
        }
      });
      // for status
      $('.status').editable({
        source: [
              {value: 0, text: '".$review_agent_status[0]."'},
              {value: 1, text: '".$review_agent_status[1]."'}
           ],
        url: 'withdrawapplication_action.php?a=withdrawapplication_submit',
        success: function(resultdata){
          $( '#preview_result' ).html(resultdata);
        }
      });
    });
    </script>
    ";
  */
	$load_animate="<div class='load_datatble_animate'><img src='./ui/loading.gif'></div>";
	// 切成 1 欄版面
	// $indexbody_content = '';
	// $indexbody_content = $indexbody_content.'
	// <div class="row">
	// 	<div class="col-12">
	// 	'.$load_animate.'
	// 	'.$show_list_html.'
	// 	</div>
	// </div>
	// <br>
	// <div class="row">
	// 	<div id="preview_result"></div>
	// </div>
	// ';

}else{
	// 沒有登入的顯示提示俊息 $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
	$show_transaction_list_html  = $tr['only management and login mamber'];

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 position-relative">
	  '.$show_transaction_list_html.'
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
// // 主要內容 -- title
// $tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// // 主要內容 -- content
// $tmpl['panelbody_content']				= $indexbody_content;

// 兩欄分割--左邊
$tmpl['indextitle_content']				= $indextitle_content;
$tmpl['indexbody_content'] 				= $indexbody_content;
// 兩欄分割--右邊
$tmpl['paneltitle_content']				= $paneltitle_content;
$tmpl['panelbody_content']				= $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/beadmin_fluid.tmpl.php");
include("template/s2col.tmpl.php");
?>
