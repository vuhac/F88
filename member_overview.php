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
$exportExcelBtn = '	<button id="excelBtn" style="float: right;margin-bottom: auto" class="btn btn-success btn-sm">'. $tr['Export Excel'] .'</button>';
$paneltitle_content .= $exportExcelBtn;
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li class="active">會員總覽</li>
</ol>';
// ----------------------------------------------------------------------------
//日期 篩選用
$dateyearrange_start = date("Y") - 100;
$dateyearrange_end = date("Y/m/d");
$datedefauleyear = date("Y/m/d");

// $betdate_range_start = date("Y/m/d");
$d = new DateTime(date("Y/m/d"));
$d->modify('-1 month');
$betdate_range_start = $d->format('Y/m/d');
$betdate_range_end = date("Y/m/d");
$datedefaule = date("Y/m/d");
$extend_head = '';
//左側內容
//帳戶
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
  <div class="col-12"><p class="font-weight-bold">' . $tr['Account'] . '</p></div>
  <div class="col-12">
  	<input type="text" class="form-control" id="account_query" placeholder="' . $tr['Account'] . '">
  </div>
</div>
';
//帳戶類型
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
	<div class="col-12"><p class="font-weight-bold">'. $tr['Type of account'] .'</p></div>
	<div class="col-12">
		<label class="checkbox-inline">
			<input type="checkbox" class="acc_type" name="acc_type" value="M" checked>' . $tr['member'] . '
		</label>
		<label class="checkbox-inline ng-binding">
			<input type="checkbox" class="acc_type" name="acc_type" value="A" checked>' . $tr['agent'] . '
		</label>
	</div>
</div>
';

$indexbody_content = $indexbody_content . '
<div class="row mb-4">
	<div class="col-12"><p class="font-weight-bold">' . $tr['Enrollment date'] . '</p></div>
	<div class="col-12">
		<div class="input-group">
			<div class=" input-group">
			<span class="input-group-addon">'.$tr['Starting time'].'</span>
			<input type="text" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="register_date_start_time" value="">
			</div>
			<div class=" input-group">
			<span class="input-group-addon">'.$tr['End time'].'</span>
			<input type="text" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="register_date_end_time" value="">
			</div>
		</div>
	</div>
	<div class="col-12 col-md-7"></div>
</div>
';

// $tr['accAccount Affiliate Balance'] = '帳戶加盟金餘額';
// $tr['Lower limit'] = '下限';
// $tr['Upper limit'] = '上限';
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
	<div class="col-12"><p class="font-weight-bold">' . $tr['Account Affiliate Balance'] . '</p></div>
	<div class="col-12">
		<div class="input-group">
			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Lower limit'] . ' id="account_cash_balance_lower" value="">
			<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Upper limit'] . ' id="account_cash_balance_upper" value="">
		</div>
	</div>
	<div class="col-12 col-md-7"></div>
</div>
';

// $tr['Account cash balance'] = '帳戶現金餘額';
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
	<div class="col-12"><p class="font-weight-bold">' . $tr['Account cash balance'] . '</p></div>
	<div class="col-12">
		<div class="input-group">
			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Lower limit'] . ' id="account_token_balance_lower" value="">
			<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Upper limit'] . ' id="account_token_balance_upper" value="">
		</div>
	</div>
</div>
';

// $tr['State'] = '狀態';
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
<div class="col-12"><p class="font-weight-bold">' . $tr['State'] . '</p></div>
<div class="col-12">
	<div class="form-group">
		<select class="form-control" id="mamber_status_select">
			<option></option>
			<option value="0">' . $tr['account disable'] . '</option>
			<option value="1">' . $tr['account valid'] . '</option>
			<option value="2">' . $tr['account freeze'] . '</option>
		</select>
	</div>
</div>
</div>
';
// 會員等級
// $tr['Member level query error'] = '會員等級查詢錯誤。';
$member_grade_sql = "SELECT id, gradename FROM root_member_grade;";
$member_grade_sql_result = runSQLall($member_grade_sql);
// $member_grade_sql_result = array(8, "basic1", "small1", "basic2", "small2", "basic3", "small3", "basic4", "small4");
//var_dump($member_grade_sql_result);
// $member_grade_sql_result[0] = 0;

if ($member_grade_sql_result[0] >= 1) {
	$member_grade_html = '';
	$member_gradelist_html = '';
	for ($i = 1; $i <= $member_grade_sql_result[0]; $i++) {
		$member_grade_html = $member_grade_html . '
		<div class="col-sm-3">
			<label class="text-ellipsis ng-binding" title="' . $member_grade_sql_result[$i]->gradename . '">
				<input type="checkbox" name="member_grade_checkbox" value="' . $member_grade_sql_result[$i]->id . '" class="member_grade_checkbox_class" checked>
				' . $member_grade_sql_result[$i]->gradename . '
			</label>
		</div>
		';

		$member_gradelist_html = $member_gradelist_html .'
		<option value="' . $member_grade_sql_result[$i]->gradename . '">'.$member_grade_sql_result[$i]->gradename.'</option>
		';

		$member_gradelistarray[] = array(
			'id'          => $member_grade_sql_result[$i]->id,
			'name'     => $member_grade_sql_result[$i]->gradename
		);		

		if ($i % 4 == 0) {
			$member_grade_html = $member_grade_html . '
			
			';
		}
	}

	$member_gradelistjson = json_encode($member_gradelistarray);

} else {
	$member_grade_html = $tr['Member level query error'];
}

// 會員等級
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
	<div class="col-12"><p class="font-weight-bold">' . $tr['Member Level'] . '</p></div>
	<div class="col-12">
		<div id="member_grade_preview_area"><p class="mb-1">'.$tr['select all'].'</p></div>
		<button type="button" class="btn btn-primary btn-xs mb-2" data-toggle="modal" data-target="#myModal">' . $tr['Select'] . '</button>

		<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="myModalLabel">' . $tr['select a member level'] . '</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body">
						<button type="button" class="btn btn-xs" id="select_all_checkbox">' . $tr['select all'] . '</button>
						<button type="button" class="btn btn-xs" id="cancel_select_all_checkbox">' . $tr['Emptied'] . '</button>
						<br>
						<br>
						<div class="row">
						' . $member_grade_html . '
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal" id="close_member_grade_btn">Close</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
';
$favorable_sql = "SELECT DISTINCT (name) AS name FROM root_favorable ORDER BY name;";
$favorable_sql_result = runSQLall($favorable_sql);
// $tr['Return bonus level query error'] = '反水等級查詢錯誤。';
// $tr['Bonus level'] = '反水等級';

if ($favorable_sql_result[0] >= 1) {
	$favorable_option_html = '';	
	for ($i = 1; $i <= $favorable_sql_result[0]; $i++) {
		$favorable_option_html = $favorable_option_html . '
  <option value="' . $favorable_sql_result[$i]->name . '">' . $favorable_sql_result[$i]->name . '</option>
	';	
	}
$favorable_html =
	' <div class="form-group">
		<select class="form-control" id="favorable_select">   <option></option>
			' . $favorable_option_html . '
			</select>
		</div>';
		
} else {
	$favorable_html = '<div class="text-danger">' . $tr['Return bonus level query error'] . '</div>';
}
// 反水等級
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
<div class="col-12">
	<p class="font-weight-bold">' . $tr['Bonus level'] . '</p>
</div>
<div class="col-12">
 ' . $favorable_html . '
</div>
<div class="col-12 col-md-7"></div>
</div>
';

// $tr['Affiliated agent'] = '所屬代理';
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
  <div class="col-12"><p class="font-weight-bold">' . $tr['Affiliated agent'] . '</p></div>
  <div class="col-12">
  	<input type="text" class="form-control" id="agent_account" placeholder="">
  </div>
</div>
';

//IP
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
  <div class="col-12"><p class="font-weight-bold">IP</p></div>
  <div class="col-12">
  	<input type="text" class="form-control" id="registerip" placeholder="">
  </div>
</div>
';

// $tr['Fingerprint code'] = '指紋碼';
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
  <div class="col-12"><p class="font-weight-bold">' . $tr['Fingerprint code'] . '</p></div>
  <div class="col-12">
  	<input type="text" class="form-control" id="registerfingerprinting" placeholder="">
  </div>
</div>
';
// 進階搜尋
$indexbody_content = $indexbody_content . '
<div class="row mb-2">
<div class="col-12 col-md-8"></div>
<div class="col-12 col-md-4">
	<button type="button" class="btn btn-primary btn-xs pull-right" data-toggle="modal" data-target="#myModal1">' . $tr['Advanced Search'] . '</button>

	<div class="modal fade" id="myModal1" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="myModalLabel">' . $tr['Advanced Search'] . '</h4>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<div class="modal-body">
					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['realname'] . '</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="real_name" placeholder="">
						</div>
						<div class="col-sm-4">
							<label class="radio-inline">
								<input type="radio" class="real_name_search" name="real_name_search" value="precise_search" checked>
								' . $tr['complete'] . '
							</label>
							<label class="radio-inline ng-binding">
								<input type="radio" class="real_name_search" name="real_name_search" value="fuzzy_search">
								' . $tr['contain'] . '
							</label>
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Cell Phone'] . '</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="mobile_number" placeholder="">
						</div>
						<div class="col-sm-4">
							<label class="radio-inline">
								<input type="radio" class="mobile_number_search" name="mobile_number_search" value="precise_search" checked>
								' . $tr['complete'] . '
							</label>
							<label class="radio-inline ng-binding">
								<input type="radio" class="mobile_number_search" name="mobile_number_search" value="fuzzy_search">
								' . $tr['contain'] . '
							</label>
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Gender'] . '</label>
						<div class="col-sm-6">
							<div class="form-group">
								<select class="form-control" id="sex_select">
									<option></option>
									<option value="1">' . $tr['Gender Male'] . '</option>
									<option value="0">' . $tr['Gender Female'] . '</option>
								</select>
							</div>
						</div>
						<div class="col-sm-4">
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">Email</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="email" placeholder="">
						</div>
						<div class="col-sm-4">
							<label class="radio-inline">
								<input type="radio" class="email_search" name="email_search" value="precise_search" checked>
								' . $tr['complete'] . '
							</label>
							<label class="radio-inline ng-binding">
								<input type="radio" class="email_search" name="email_search" value="fuzzy_search">
								' . $tr['contain'] . '
							</label>
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Birth'] . '</label>
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="birthday_start_date" value="">
								<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
								<input type="text" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="birthday_end_date" value="">
							</div>
						</div>
						<div class="col-sm-4">
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['WeChat Number'] . '</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="wechat" placeholder="">
						</div>
						<div class="col-sm-4">
							<label class="radio-inline">
								<input type="radio" class="wechat_search" name="wechat_search" value="precise_search" checked>
								' . $tr['complete'] . '
							</label>
							<label class="radio-inline ng-binding">
								<input type="radio" class="wechat_search" name="wechat_search" value="fuzzy_search">
								' . $tr['contain'] . '
							</label>
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">QQ</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="qq" placeholder="">
						</div>
						<div class="col-sm-4">
							<label class="radio-inline">
								<input type="radio" class="qq_search" name="qq_search" value="precise_search" checked>
								' . $tr['complete'] . '
							</label>
							<label class="radio-inline ng-binding">
								<input type="radio" class="qq_search" name="qq_search" value="fuzzy_search">
								' . $tr['contain'] . '
							</label>
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Bank account'] . '</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="bank_account" placeholder="">
						</div>
						<div class="col-sm-4">
							<label class="radio-inline">
								<input type="radio" class="bank_account_search" name="bank_account_search" value="precise_search" checked>
								' . $tr['complete'] . '
							</label>
							<label class="radio-inline ng-binding">
								<input type="radio" class="bank_account_search" name="bank_account_search" value="fuzzy_search">
								' . $tr['contain'] . '
							</label>
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Last betting'] . '</label>
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="last_betting_start_date" value="">
								<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
								<input type="text" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="last_betting_end_date" value="">
							</div>
						</div>
						<div class="col-sm-4">
							' . $tr['Check for a month'] . '
						</div>
					</div>

					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Last login'] . '</label>
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="last_login_start_date" value="">
								<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
								<input type="text" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="last_login_end_date" value="">
							</div>
						</div>
						<div class="col-sm-4">
						</div>
					</div>

				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="col-12 col-md-7"><div id="offer_name_result"></div></div>
</div>
';

$indexbody_content = $indexbody_content . '
<div class="w-100 border-top pt-3 d-flex">
  <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit">' . $tr['Inquiry'] . '</button>
</div>
';

// 佣金設定
$commission_sql = "SELECT DISTINCT group_name, name FROM root_commission WHERE deleted = '0';";
$commission_sql_result = runSQLall($commission_sql);

if ($commission_sql_result[0] >= 1) {
	$commission_option_html = '';
	for ($i = 1; $i <= $commission_sql_result[0]; $i++) {
		$commission_option_html = $commission_option_html . '
  <option value="' . $commission_sql_result[$i]->name . '">' . $commission_sql_result[$i]->name . '</option>
  ';
}
}

// 右側內容
$panelbody_content = <<<HTML
	<table id="overview" class="member_g_information display compact dataTable" style="width:100%;">
		<thead>
			<tr>                
				<th>{$tr['Account']}</th>
				<th>{$tr['identity']}</th>
				<th>{$tr['the upline agents']}</th>
				<th>{$tr['Register time']}</th>
				<th>{$tr['Gtoken']}</th>
				<th>{$tr['Franchise']}</th>
				<th>{$tr['State']}</th>
				<th>{$tr['Member Level']}</th>
				<th>{$tr['Preferential setting'] }</th>
				<th>{$tr['Commission setting']}</th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
			</tr>
		</thead>
	</table>
	<div id="preview_result"></div>
HTML;
$extend_js = <<<HTML
  <!-- 參考使用 datatables 顯示 -->
  <!-- https://datatables.net/examples/styling/bootstrap.html -->
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
  <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
	<style>
		/* bootstrap的CSS被強制改掉了，之後有時間修正一下 */
		.bg-danger{
			background-color: #dc3545 !important;
		}
		/* 樣式確認後搬移至style.css */
		.member_g_information .toolshow{
			position: relative;
		}
		.member_g_information tbody tr td i{
			font-size: 16px;
		}
		.toolshow span{
			position: absolute;
			top: -1px;
			left: 19px;
			width: 8px;
			height: 8px;
			border-radius: 50%;
		}
		/* 提示工具 */
		.popover_show{
			position: relative;
			display: none;
		}
		.popover_toolip{
			position: absolute;
			width: 200px;
			min-height: 120px;
			top: -70px;
			left: -195px;
			z-index: 1060;
			font-size: .875rem;
			word-wrap: break-word;
			background-color: #fff;
			background-clip: padding-box;
			border: 1px solid rgba(184, 183, 183, 0.42);
			border-radius: .3rem;
		}
		.popover_toolip_header{
			padding: .5rem .75rem;
			margin-bottom: 0;
			font-size: 1rem;
			color: inherit;
			background-color: #f7f7f7;
			border-bottom: 1px solid #ebebeb;
			border-top-left-radius: calc(.3rem - 1px);
			border-top-right-radius: calc(.3rem - 1px);  
		}
		.popover_toolip_body{
			padding: .5rem .75rem;
			color: #212529;
			border: 0;
			width: 100%;
			height: 80px;
			padding-bottom: 50px;
		}
		textarea:focus{
			outline: none;
		}
		.popover_toolip .popover_checked{
			position: absolute;
			left: 10px;
			bottom: 10px;
			padding: 1px 4px;
		}
		.popover_hover[data-off=false] .toolip_text,
		.popover_hover[data-off=true]:hover .toolip_text{
			color: red;
		}
		.popover_toolip button[aria-label=Close]{
			position: absolute;
			right: 5px;
			top: 4px;
			display: none;
		}
		.popover_hover[data-off=false] button[aria-label=Close]{
			display: block;
		}		
		.toolip_text{
			background: none;
			border: 0;
			cursor: pointer;
			color: #6c757d;
		}
		.toolip_text:focus{
			outline: none;
		}
		/* 需要改CSS 名稱以面控制所有 */
		#overview_paginate{
		 display: flex;
		 margin-top: 10px;
		}
		#overview_length{
			margin-top: 10px;
			padding-top: 0.25em;
		}
		#overview_paginate .pagination{
			margin-left: auto;			
		}
		#overview tr td{
			height: 40px;
		}
		#overview select {
			width: 100%;
		}
		#overview tr td span.icon_member{
			color: #337ab7;
		}
		#overview .dataTable_empty{
			vertical-align: middle;
		}
	</style>
  <script>
	$(document).ready(function() {
		var gradelistjson = $member_gradelistjson;
		function filtername(id){
			return gradelistjson.filter(item => item.id == id);
		}
		//左側篩選按鈕
		function submit_select() {
			var select_member_grade = $('.member_grade_checkbox_class').serialize();

			$.post('member_action.php?a=select_member_grade',
			{
				select_member_grade: select_member_grade
			},
			function(result) {
				$('#member_grade_preview_area').html(result);
			});
		}
		$('#select_all_checkbox').click(function () {
			$('.modal-body input:checkbox').prop('checked', true);
			submit_select();
			});

		$('#cancel_select_all_checkbox').click(function () {
			$('.modal-body input:checkbox').prop('checked', false);
			submit_select();
		});

		$('.member_grade_checkbox_class').click(function () {
			submit_select();
		});
		
		var dt = $('#overview').DataTable({
			"dom": '<tflip>',
			"bRetrieve": true,
			"searching": false,
			"order": [3, 'desc'],
			"language": {
				"loadingRecords": "<img src='./ui/loading.gif' style='width:35px;'> loading..."
			},
			"ajax": "member_overview_action.php?a=memberlist",	
			"columns": [
			{ "data": "account",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var accounthtml = '<a href="member_account.php?a='+oData.id+'" target="_blank">'+oData.account+'</a>'
					$(nTd).html(accounthtml);
				}
			},
			{ "data": "therole","class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					if ( oData.therole == 'R' ) {
						//管理員
						var html = "{$tr['Identity Management']}";
					} else if ( oData.therole == 'A' ) {
						//代理商
						var text = "{$tr['Identity Agent']}";
						var html = '<span class="glyphicon glyphicon-knight icon_member" data-text="'+text+'" aria-hidden="true"></span>';
					} else if ( oData.therole == 'M' ) {
						//會員
						var text = "{$tr['Identity Member']}";
						var html = '<span class="glyphicon glyphicon-user icon_member" aria-hidden="true" data-text="'+text+'"></span>';
					} else if ( oData.therole == 'T' ) {
						//試用帳號
						var html = "{$tr['Identity Trial Account']}";
					}
					$(nTd).html(html);
				}
			},//身分
			{ "data": "parent_account" },// 上級代理商
			{ "data": "enrollmentdate" },//註冊時間
			{ "data": "gtoken_balance",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var html = oData.gtoken_balance;
					$(nTd).html('$ ' + html);
				}
			},//遊戲幣
			{ "data": "gcash_balance",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var html = oData.gcash_balance;
					$(nTd).html('$ ' + html);
				}
			},//現金
			{ "data": "status",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var disable_text = "{$tr['Wallet Disable']}";
					var valid_text = "{$tr['Wallet Valid']}";
					var freeze_text = "{$tr['account freeze']}";
					var stop_text = "暫時封鎖";
					var select_option = '<option value="'+ disable_text +'">'+ disable_text +'</option>' +
					'<option value="'+ valid_text +'">'+ valid_text +'</option>' +
					'<option value="'+ freeze_text +'">'+ freeze_text +'</option>' +
					'<option value="'+ stop_text +'">'+ stop_text +'</option>';
					var effective_text = "{$tr['Wallet Valid']}";

					if ( oData.status == '0' ){
						//停用
						var html_text = disable_text;
					}else if ( oData.status == '1' ){
						//有效
						var html_text = valid_text;
					}else if ( oData.status == '2' ){
						//錢包凍結
						var html_text = freeze_text;
					}else if ( oData.status == '3' ){
						//帳號暫時封鎖
						var html_text = stop_text;
					}
					
					var html = '<select class="form-control-xs rounded select_gradeselect" data-account="'+oData.account+'" data-beforeval="'+ html_text +'" id="gradeselect_'+oData.id+'">'+
					'<option disabled selected hidden>'+ html_text +'</option>'+
					select_option +
					'</select>';
					$(nTd).html(html);
				}
			},//狀態
			{ "data": "grade", "orderable": false,"class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var op_html = `{$member_gradelist_html}`;		
					var html_text = filtername(oData.grade);
					var html = '<select class="form-control-xs rounded select_memberleve" id="memberleve_'+oData.id+'" data-account="'+oData.account+'" data-beforeval="'+ html_text[0].name +'">'+
					'<option disabled selected hidden>'+ html_text[0].name +'</option>'+
					op_html +
					'</select>';
					$(nTd).html(html);					
				}			
			},//會員等級
			{ "data": "favorablerule", "orderable": false, "class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var op_html = `{$favorable_option_html}`;
					var html = '<select class="form-control-xs rounded select_favorable" id="favorablerule_'+oData.id+'" data-account="'+oData.account+'" data-beforeval="'+ oData.favorablerule +'">'+
					'<option disabled selected hidden>'+oData.favorablerule+'</option>'+
					op_html +
					'</select>';
					$(nTd).html(html);
				}
			},//反水等級
			{ "data": "commissionrule", "orderable": false,"class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var op_html = `{$commission_option_html}`;
					var html = '<select class="form-control-xs rounded select_commission" id="commissionrule_'+oData.id+'" data-account="'+oData.account+'" data-beforeval="'+ oData.commissionrule +'">'+
					'<option disabled selected hidden>'+ oData.commissionrule +'</option>'+
					op_html +
					'</select>';
					$(nTd).html(html);					
				}
			},//佣金設定
			{ "data": null, "orderable": false,"class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					var member_text ="{$tr['member data']}";
					var member_link = '<a href="member_account.php?a='+ oData.id +'" class="btn btn-default btn-xs bg-light" target="_blank">'+ member_text +'</a>';
					$(nTd).html(member_link);
				}			
			},
			{ "data": null, "orderable": false, "class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					//功能操作
					var operation_text = "{$tr['functional operation']}";//功能操作
					var features_link = '<a href="member_overview_operating.php" class="btn btn-default btn-xs bg-light" target="_blank">'+ operation_text +'</a>';
					$(nTd).html(features_link);
				}
			},//功能操作
			{ "data": null, "orderable": false, "class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					//歷程記錄
					var history_text = "{$tr['historical record']}";//歷程記錄
					var history_link = '<a href="member_betlog.php?m&a='+oData.account+'" class="btn btn-default btn-xs bg-light" target="_blank">'+ history_text +'</a>';
					$(nTd).html(history_link);
				}	
			},//歷程記錄
			{ "data": null, "orderable": false, "class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					//站內信
					//oData json  oData.account = account
					// href="mail.php?s='+oData.account+'" 僅供展示，之後可以改為其他帶入方式 (需帶入到站內信找尋未讀信件)
					var total_text ="{$tr['total'] }"; //共
					var letter_text = "{$tr['a letter']}"; //封
					var unread_text ="{$tr['unread']}"; //未讀
					var mail_prompt = '<a href="mail.php?s='+oData.account+'" class="toolshow px-2" target="_blank" data-toggle="tooltip" data-placement="top" title="' + 
					oData.account +' : '+ total_text +'10025'+ letter_text +'，'+ unread_text +'15'+ letter_text +'">' +
					'<i class="fas fa-envelope text-secondary" btn-ms></i>' +
					'<span class="bg-danger" alt="警示用，如果未讀有1封或者更多的話，顯示這個紅點，如果沒有未讀則不顯示(套程式前的註解-確認後刪除)">&nbsp;</span>' +
					'</a>';
					$(nTd).html(mail_prompt);
				}
			},
			{ "data": null, "orderable": false,"class": "text-center",
				"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
					//備註
					// <h3 class="popover_toolip_header">'+oData.account+'</h3> 帳號資訊
					// <textarea class="popover_toolip_body">6666666666666</textarea> 備註內容
					// <button type="button" class="btn btn-success btn-sm popover_checked">確認</button> 確認按鈕
					var fixed_text = "{$tr['click on pin window']}"; //點擊固定視窗
					var confirm_text ="{$tr['confirm']}";//確認
					var note_html = '<div class="popover_hover" data-off="true">' +
					'<button class="toolip_text" title="'+ fixed_text +'"><i class="fas fa-edit"></i></button>' +
					'<div class="popover_show">' +
					'<div class="popover_toolip" role="tooltip">' +
					'<div class="popover_toolip_arrow"></div>' +
					'<h3 class="popover_toolip_header">'+oData.account+'</h3>' +
					'<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
					'<span aria-hidden="true">×</span>' +
					'</button>' +
					'<textarea class="popover_toolip_body">6666666666666</textarea>' +
					'<button type="button" class="btn btn-success btn-sm popover_checked">'+ confirm_text +'</button>' +
					'</div>' +
					'</div>' +
					'</div>';
					$(nTd).html(note_html);
				}			
			},
		],		
		"fnDrawCallback": function (oSettings) {
			//備註提示窗
			$('.popover_hover').hover(function(){
					//在開關是打開的狀況下可以使用hover功能 data-off = true
					var data = $(this).data('off');
					if ( data == true){
						$(this).find('.popover_show').show();
					}
			},function(){
					var data = $(this).data('off');
					if ( data == true){
						$(this).find('.popover_show').hide();
					}
			});

			$('.toolip_text').click(function(){
				//使用點擊功能可以固定視窗方便編輯 hover停用 data-off = false
				//關閉其他被開啟的編輯視窗
				$('.popover_show').hide();
				$('.popover_hover').data('off',true);
				$('.popover_hover').attr('data-off',true);

				$(this).next('.popover_show').show();
				$(this).parent().data('off',false);
				$(this).parent().attr('data-off',false);          
			});

			$('.popover_hover button[aria-label=Close]').click(function(){
				//點擊關閉按鈕重新開啟 hover 功能 data-off = true
				$(this).parents('.popover_hover').data('off',true);
				$(this).parents('.popover_hover').attr('data-off',true);
				$(this).parents('.popover_show').hide();
			});

			$('#overview tbody tr td:nth-child(13)').each(function () {
				$(this).tooltip({
					html: true
				});
			});
			
			//狀態更改
			$('.select_gradeselect').change(function(e){
				var idname = $(this).attr('id');
				var idarray = idname.split('_');
				var id = idarray[1];
				var status_name = jQuery.trim($(this).val());
				var account = $(this).data('account');
				// 原本的狀態
				var before_status =  jQuery.trim($(this).data('beforeval'));
				//訊息
				var suremember_text ="{$tr['Sure member']}";
				var accountstatus_text ="{$tr['Account status change']}";
				var message = suremember_text + account + accountstatus_text + status_name + '?';
				var illegal_text ="{$tr['Illegal test']}";
				if(jQuery.trim(status_name) != '') {
					if(before_status != status_name) {
						if(confirm(message)) {
							$.post('member_overview_action.php?a=change_member_status',
							{
								status_name: status_name,
								pk: id
							},
							function(result) {
								$('#preview_result').html(result);
							});
						} else {
							$(this).val(before_status);
						}
					}
				} else {
					alert(illegal_text);
				}
			});

			//會員等級更改
			$('.select_memberleve').change(function(e){
				var idname = $(this).attr('id');
				var idarray = idname.split('_');
				var id = idarray[1];
				var grade_name = jQuery.trim($(this).val());
				var account = $(this).data('account');
				// 原本的狀態
				var before_grade =  jQuery.trim($(this).data('beforeval'));
				//訊息
				var suremember_text ="{$tr['Sure member']}";
				var levelchange_text ="{$tr['Membership level change']}";
				var message = suremember_text + account + levelchange_text + grade_name + '?';
				var illegal_text ="{$tr['Illegal test']}";

				// console.log(id);
				// console.log(grade_name);
				// console.log(account);
				// console.log(before_status);
				// console.log(message);
				if(jQuery.trim(grade_name) != '') {
					if(before_grade != grade_name) {
						if(confirm(message)) {
							$.post('member_action.php?a=change_mamber_grade',
							{
								grade_name: grade_name,
								pk: id
							},
							function(result) {
								$('#preview_result').html(result);
							});
						} else {
							$(this).val(before_grade);
						}
					}
				} else {
					alert(illegal_text);
				}
				
			});

			//反水設定
			$('.select_favorable').change(function(e){
				var idname = $(this).attr('id');
				var idarray = idname.split('_');
				var id = idarray[1];
				var preferential_name = jQuery.trim($(this).val());
				var account = $(this).data('account');
				// 原本的狀態
				var before_preferential =  jQuery.trim($(this).data('beforeval'));
				//訊息
				var suremember_text ="{$tr['Sure member']}";
				var bonuschange_text ="{$tr['Membership bonus change']}";
				var message = suremember_text + account + bonuschange_text + preferential_name + '?';
				var illegal_text ="{$tr['Illegal test']}";

				if(jQuery.trim(preferential_name) != '') {
					if(before_preferential != preferential_name) {
						if(confirm(message)) {
							$.post('member_overview_action.php?a=change_mamber_preferential_name',
							{
								preferential_name: preferential_name,
								pk: id
							},
							function(result) {
								$('#preview_result').html(result);
							});
						} else {
							$(this).val(before_preferential);
						}
					}
				} else {
					alert(illegal_text);
				}		
			});

		//佣金設定
		$('.select_commission').change(function(e){
				var idname = $(this).attr('id');
				var idarray = idname.split('_');
				var id = idarray[1];
				var commission_name = jQuery.trim($(this).val());
				var account = $(this).data('account');
				// 原本的狀態
				var before_commission =  jQuery.trim($(this).data('beforeval'));
				//訊息
				var message = '確定要變更會員' + account + '帳號佣金設定' + commission_name + '?';
				var illegal_text ="{$tr['Illegal test']}";

				if(jQuery.trim(commission_name) != '') {
				if(before_commission != commission_name) {
					if(confirm(message)) {
						$.post('member_overview_action.php?a=change_mamber_commission_name',
						{
							commission_name: commission_name,
							pk: id
						},
						function(result) {
							$('#preview_result').html(result);
						});
					} else {
						$(this).val(before_commission);
					}
				}
			} else {
				alert('(x)不合法的測試。');
			}
			});
		
		}
		});
		
		//入會日期
		$('#register_date_start_time, #register_date_end_time, #birthday_start_date, #birthday_end_date, #last_login_start_date, #last_login_end_date').datetimepicker({
			defaultDate:'{$datedefauleyear}',
			minDate: '{$dateyearrange_start}/01/01',
			maxDate: '{$dateyearrange_end}',
			timepicker:false,
			format:'Y/m/d',
			lang:'en'
		});
		// 最後投注時間
		$('#last_betting_start_date, #last_betting_end_date').datetimepicker({
			defaultDate:'{$datedefaule}',
			minDate: '{$betdate_range_start}',
			maxDate: '{$betdate_range_end}',
			timepicker:false,
			format:'Y/m/d',
			lang:'en'
		});	
		
	});
  </script>
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
$tmpl['panelbody_content'] = $panelbody_content;
// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/s2col.tmpl.php";

?>
