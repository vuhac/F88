<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員查詢功能
// File Name:	member.php
// Author:		Barkley
// Related:   member_action.php
// Log:
// 2019.03.27 新增匯出功能 Letter
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
$function_title = $tr['Member inquiry'];
$page_title = '';
$indextitle_content = '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>' . $tr['Search criteria'];
$indexbody_content = '';
$paneltitle_content = '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>' . $tr['Query results'];
$exportExcelBtn = '<button id="excelBtn" style="float: right;margin-bottom: auto" class="btn btn-success btn-sm">'. $tr['Export Excel'] .'</button>';
$paneltitle_content .= $exportExcelBtn;
$sns1 = $protalsetting["custom_sns_rservice_1"]??$tr['sns1'];
$sns2 = $protalsetting["custom_sns_rservice_2"]??$tr['sns2'];
// 進階匯出
$advanceExportHtml = <<< HTML
	<div id="adv_export" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="adv_export_label" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h2 class="modal-title"></h2>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-3">
							<div class="member_allselect">
								<input class="export_item_select" id="export_select_all" type="checkbox" value="select_all"><label for="export_select_all">{$tr['select all']}</label>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-12">
							<div class="d-flex flex-row bd-highlight">
							  <div class="bd-highlight member_modal_input">
							  	<div><input class="export_item required" id="export_account_id" type="checkbox" value="id" checked disabled><label>{$tr['ID']}</label></div>
							  	<div><input class="export_item" id="export_email" type="checkbox" value="email"><label for="export_email">{$tr['Email']}</label></div>
							  	<div><input class="export_item" id="export_sex" type="checkbox" value="sex"><label for="export_sex">{$tr['sex']}</label></div>
							  	<div><input class="export_item" id="export_bankaccount" type="checkbox" value="bankaccount"><label for="export_bankaccount">{$tr['bankaccount']}</label></div>
							  	<div><input class="export_item" id="export_grade" type="checkbox" value="grade"><label for="export_grade">{$tr['grade']}</label></div>
							  	<div><input class="export_item" id="export_gcash_balance" type="checkbox" value="gcash_balance"><label for="export_gcash_balance">{$tr['gcash_balance']}</label></div>
							  </div>
							  <div class="bd-highlight pl-4 member_modal_input">
							  	<div><input class="export_item required" id="export_account" type="checkbox"value="account" checked disabled><label for="export_account">{$tr['Account']}</label></div>
							  	<div><input class="export_item" id="export_status" type="checkbox" value="status"><label for="export_status">{$tr['account status']}</label></div>
							  	<div><input class="export_item" id="export_birthday" type="checkbox" value="birthday"><label for="export_birthday">{$tr['birthday']}</label></div>
							  	<div><input class="export_item" id="export_favorablerule" type="checkbox" value="favorablerule"><label for="export_favorablerule">{$tr['favorablerule']}</label></div>
							  	<div><input class="export_item" id="export_enrollmentdate" type="checkbox" value="enrollmentdate"><label for="export_enrollmentdate">{$tr['enrollmentdate']}</label></div>
							  	<div><input class="export_item" id="export_gtoken_balance" type="checkbox" value="gtoken_balance"><label for="export_gtoken_balance">{$tr['gtoken_balance']}</label></div>
							  </div>
							  <div class="bd-highlight pl-4 member_modal_input">
							  	<div><input class="export_item" id="export_realname" type="checkbox" value="realname"><label for="export_realname">{$tr['realname']}</label></div>
							  	<div><input class="export_item" id="export_therole" type="checkbox" value="therole"><label for="export_therole">{$tr['therole']}</label></div>
							  	<div><input class="export_item" id="export_wechat" type="checkbox" value="wechat"><label for="export_wechat">{$sns1}</label></div>
							  	<div><input class="export_item" id="export_lastlogin" type="checkbox" value="lastlogin"><label for="export_lastlogin">{$tr['lastlogin']}</label></div>
							  	<div><input class="export_item" id="export_registerfingerprinting" type="checkbox" value="registerfingerprinting"><label for="export_registerfingerprinting">{$tr['registerfingerprinting']}</label></div>
							  	<div><input class="export_item" id="export_casino_accounts" type="checkbox" value="casino_accounts"><label for="export_casino_accounts">{$tr['casino_accounts']}</label></div>
							  	<div></div>
							  </div>
							  <div class="bd-highlight pl-4 member_modal_input">
							  	<div><input class="export_item" id="export_mobilenumber" type="checkbox" value="mobilenumber"><label for="export_mobilenumber">{$tr['mobilenumber']}</label></div>
							  	<div><input class="export_item" id="export_parent_id" type="checkbox" value="parent_id"><label for="export_parent_id">{$tr['parent_id']}</label></div>
							  	<div><input class="export_item" id="export_qq" type="checkbox" value="qq"><label for="export_qq">{$sns2}</label></div>
							  	<div><input class="export_item" id="export_lastbetting" type="checkbox" value="lastbetting"><label for="export_lastbetting">{$tr['lastbetting']}</label></div>
							  	<div><input class="export_item" id="export_registerip" type="checkbox" value="registerip"><label for="export_registerip">{$tr['registerip']}</label></div>
							  </div>
							</div>
						</div>
					</div>
				<div class="modal-footer">
					<button id="excel" style="float: right;margin-bottom: auto" class="btn btn-success btn-sm">{$tr['Export Excel']}</button>
					<form id="excelform" action="member_action.php?a=member_inquiry" method="post">
						<input type="hidden" id="isQuery" name="isQuery" value="0">
						<input type="hidden" id="export" name="export" value="none">
					</form>
				</div>
			</div>
		</div>
	</div>
HTML;
$paneltitle_content .= $advanceExportHtml;
$panelbody_content = '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['Home'] = '首頁';
// $tr['Members and Agents'] = '會員與加盟聯營股東';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// ----------------------------------------------------------------------------

// --------------------------------------
// 左方索引內容 -- query 表單
// --------------------------------------
$indexbody_content = $indexbody_content . '
<div class="row">
  <div class="col-12"><label>' . $tr['Account'] . '</label></div>
  <div class="col-12 form-group">
  	<input type="text" class="form-control" id="account_query" placeholder="' . $tr['Account'] . '">
  </div>
</div>
';

$indexbody_content = $indexbody_content . '
<div class="row mb-2">
	<div class="col-12"><label>'. $tr['Type of account'] .'</label></div>
	<div class="col-12">
		<div class="form-check form-check-inline">
			<input class="form-check-input acc_type" type="checkbox" name="acc_type" value="M" checked>
			<label class="form-check-label" for="inlineCheckbox1">' . $tr['member'] . '</label>
		</div>
		<div class="form-check form-check-inline">
			<input class="form-check-input acc_type" type="checkbox" name="acc_type" value="A" checked>
			<label class="form-check-label" for="inlineCheckbox1">' . $tr['agent'] . '</label>
		</div>
	</div>
</div>
';

// $tr['Enrollment date']        = '註冊日期';
// $tr['Starting time'] = '開始時間';
// $tr['End time'] = '結束時間';
// $indexbody_content = $indexbody_content . '
// <div class="row mb-4">
// 	<div class="col-12"><p>' . $tr['Enrollment date'] . '</p></div>
// 	<div class="col-12">
// 		<div class="input-group">
// 			<div class=" input-group">
// 			<span class="input-group-addon">'.$tr['Starting time'].'</span>
// 			<input type="text" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="register_date_start_time" value="">
// 			</div>
// 			<div class=" input-group">
// 			<span class="input-group-addon">'.$tr['End time'].'</span>
// 			<input type="text" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="register_date_end_time" value="">
// 			</div>
// 		</div>
// 	</div>
// 	<div class="col-12 col-md-7"></div>
// </div>
// ';

// $tr['accAccount Affiliate Balance'] = '帳戶加盟金餘額';
// $tr['Lower limit'] = '下限';
// $tr['Upper limit'] = '上限';
// $indexbody_content = $indexbody_content . '
// <div class="row mb-2">
// 	<div class="col-12"><p>' . $tr['Account Affiliate Balance'] . '</p></div>
// 	<div class="col-12">
// 		<div class="input-group">
// 			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Lower limit'] . ' id="account_cash_balance_lower" value="">
// 			<div class="input-group-append">
// 			    <span class="input-group-text" id="basic-addon1">~</span>
// 		    </div>
// 			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Upper limit'] . ' id="account_cash_balance_upper" value="">
// 		</div>
// 	</div>
// 	<div class="col-12 col-md-7"></div>
// </div>
// ';

// $tr['Account cash balance'] = '帳戶現金餘額';
// $indexbody_content = $indexbody_content . '
// <div class="row mb-2">
// 	<div class="col-12"><p>' . $tr['Account cash balance'] . '</p></div>
// 	<div class="col-12">
// 		<div class="input-group">
// 			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Lower limit'] . ' id="account_token_balance_lower" value="">
// 			<div class="input-group-append">
// 			    <span class="input-group-text" id="basic-addon1">~</span>
// 		    </div>
// 			<input type="number" class="form-control" step=".01" placeholder=' . $tr['Upper limit'] . ' id="account_token_balance_upper" value="">
// 		</div>
// 	</div>
// </div>
// ';

// $tr['State'] = '狀態';
$indexbody_content = $indexbody_content . '
<div class="row">
<div class="col-12"><label>' . $tr['State'] . '</label></div>
<div class="col-12">
	<div class="form-group">
		<select class="form-control" id="mamber_status_select">
			<option></option>
			<option value="0">' . $tr['account disable'] . '</option>
			<option value="1">' . $tr['account valid'] . '</option>
			<option value="2">' . $tr['account freeze'] . '</option>
			<option value="3">' . $tr['blocked'] . '</option>
			<option value="4">' . $tr['auditing'] . '</option>
		</select>
	</div>
</div>
</div>

';

// $tr['Member level query error'] = '會員等級查詢錯誤。';
$member_grade_sql = "SELECT id, gradename, status FROM root_member_grade;";
$member_grade_sql_result = runSQLall($member_grade_sql);
// $member_grade_sql_result = array(8, "basic1", "small1", "basic2", "small2", "basic3", "small3", "basic4", "small4");
// var_dump($member_grade_sql_result);
// $member_grade_sql_result[0] = 0;
if ($member_grade_sql_result[0] >= 1) {
	$member_grade_html = '';
	for ($i = 1; $i <= $member_grade_sql_result[0]; $i++) {
		if($member_grade_sql_result[$i]->status == '0'){
			$member_grade_html = $member_grade_html . '
			  <div class="col-sm-3">
				<label class="text-ellipsis ng-binding" title="' . $member_grade_sql_result[$i]->gradename . '">
				  <input type="checkbox" name="member_grade_checkbox" value="' . $member_grade_sql_result[$i]->id . '" class="member_grade_checkbox_class" checked><strike>
				  ' . $member_grade_sql_result[$i]->gradename . '</strike>
				</label>
			  </div>
			';
		  }else{
			$member_grade_html = $member_grade_html . '
			  <div class="col-sm-3">
				<label class="text-ellipsis ng-binding" title="' . $member_grade_sql_result[$i]->gradename . '">
				  <input type="checkbox" name="member_grade_checkbox" value="' . $member_grade_sql_result[$i]->id . '" class="member_grade_checkbox_class" checked>
				  ' . $member_grade_sql_result[$i]->gradename . '
				</label>
			</div>
			';
		  }

		if ($i % 4 == 0) {
			$member_grade_html = $member_grade_html . '

			';
		}
	}
} else {
	$member_grade_html = $tr['Member level query error'];
}

// $tr['Please select a member level'] = '請選擇會員等級';
// $tr['Select'] = '選擇';
// $tr['select a member level'] = '選擇會員等級';
// $tr['select all'] = '全選';
// $tr['Emptied'] = '清空';
$indexbody_content = $indexbody_content . '
<div class="row">
	<div class="col-12"><label>' . $tr['Member Level'] . '</label></div>
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

$favorable_sql = "SELECT DISTINCT (name) AS name, (group_name), status FROM root_favorable WHERE deleted = '0' ORDER BY name;";
$favorable_sql_result = runSQLall($favorable_sql);
// $tr['Return bonus level query error'] = '反水等級查詢錯誤。';
// $tr['Bonus level'] = '反水等級';
if ($favorable_sql_result[0] >= 1) {
	$favorable_option_html = '';
	for ($i = 1; $i <= $favorable_sql_result[0]; $i++) {
		if($favorable_sql_result[$i]->status == '0'){
			$del_html = '('.$tr['n'].')';
		  }else{
			$del_html = '';
		  }
		$favorable_option_html = $favorable_option_html . '
  <option value="' . $favorable_sql_result[$i]->name . '">' .$del_html. $favorable_sql_result[$i]->group_name . '</option>
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

$indexbody_content = $indexbody_content . '
<div class="row">
<div class="col-12"><label>' . $tr['Bonus level'] . '</label></div>
<div class="col-12">
 ' . $favorable_html . '
</div>
<div class="col-12 col-md-7"></div>
</div>
';

// $tr['Affiliated agent'] = '所屬代理';
$indexbody_content = $indexbody_content . '
<div class="row">
  <div class="col-12"><label>' . $tr['Affiliated agent'] . '</label></div>
  <div class="col-12 form-group">
  	<input type="text" class="form-control" id="agent_account" placeholder="' . $tr['Affiliated agent'] . '">
  </div>
</div>
';

// $indexbody_content = $indexbody_content.'
// <div class="row">
//   <div class="col-12 col-md-3"><p class="text-right">遊戲帳號</p></div>
//   <div class="col-12 col-md-9">
//   	<input type="text" class="form-control" id="casino_account" placeholder="">
//   </div>
// </div>
// <br>
// ';

$indexbody_content = $indexbody_content . '
<div class="row">
  <div class="col-12"><label>IP</label></div>
  <div class="col-12">
  	<input type="text" class="form-control" id="registerip" placeholder="IP">
  </div>
</div>
';

// $tr['Fingerprint code'] = '指紋碼';
// $indexbody_content = $indexbody_content . '
// <div class="row mb-2">
//   <div class="col-12"><p>' . $tr['Fingerprint code'] . '</p></div>
//   <div class="col-12">
//   	<input type="text" class="form-control" id="registerfingerprinting" placeholder="">
//   </div>
// </div>
// ';

// $tr['Advanced Search'] = '進階搜尋';
// $tr['Real name'] = '真實姓名';
// $tr['complete'] = '完全';
// $tr['contain'] = '包含';
// $tr['Cell Phone'] = '手機';
// $tr['Gender'] = '性别';
// $tr['Gender Male'] = '男';
// $tr['Gender Female'] = '女';
// $tr['Not known'] = '未填';
// $tr['Birth'] = '生日';
// $tr['WeChat Number'] = '微信號';
// $tr['Bank account'] = '銀行帳號';
// $tr['Last betting'] = '最後投注';
// $tr['Check for a month'] = '限查一個月';
// $tr['Last login'] = '上次登入';
$indexbody_content = $indexbody_content . '
<div class="row">
<div class="col">
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
							<input type="text" class="form-control" id="real_name" placeholder="' . $tr['realname'] . '">
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
							<input type="text" class="form-control" id="mobile_number" placeholder="' . $tr['Cell Phone'] . '">
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
							<input type="text" class="form-control" id="email" placeholder="Email">
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
						<label class="col-sm-2">' . $sns1 . '</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="wechat" placeholder="' . $sns1 . '">
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
						<label class="col-sm-2">' . $sns2 . '</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="qq" placeholder="' . $sns2 . '">
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
							<input type="text" class="form-control" id="bank_account" placeholder="' . $tr['Bank account'] . '">
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
					<!--註冊時間  -->
					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Enrollment date'] . '</label>
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" placeholder=' . $tr['Starting time'] . ' aria-describedby="basic-addon1" id="register_date_start_time" value="">
								<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
								<input type="text" class="form-control" placeholder=' . $tr['End time'] . ' aria-describedby="basic-addon1" id="register_date_end_time" value="">
							</div>
						</div>
						<div class="col-sm-4">
						</div>
					</div>

					<!--帐户现金余额  -->
					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Account Affiliate Balance'] . '</label>
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" placeholder=' . $tr['Lower limit'] . ' aria-describedby="basic-addon1" id="account_cash_balance_lower" value="">
								<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
								<input type="text" class="form-control" placeholder=' . $tr['Upper limit'] . ' aria-describedby="basic-addon1" id="account_cash_balance_upper" value="">
							</div>
						</div>
						<div class="col-sm-4">
						</div>
					</div>

					<!--帐户遊戲幣余额  -->
					<div class="d-flex form-group">
						<label class="col-sm-2">' . $tr['Account cash balance'] . '</label>
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" placeholder=' . $tr['Lower limit'] . ' aria-describedby="basic-addon1" id="account_token_balance_lower" value="">
								<div class="input-group-append">
			    <span class="input-group-text" id="basic-addon1">~</span>
		    </div>
								<input type="text" class="form-control" placeholder=' . $tr['Upper limit'] . ' aria-describedby="basic-addon1" id="account_token_balance_upper" value="">
							</div>
						</div>
						<div class="col-sm-4">
						</div>
					</div>

				<!-- 指紋碼 -->
					<div class="d-flex form-group">
						<label class="col-sm-2">'.$tr['Fingerprint code'].'</label>
						<div class="col-sm-6">
							<input type="text" class="form-control" id="registerfingerprinting" placeholder="'.$tr['Fingerprint code'].'">
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
<hr>
<div class="row">
  <div class="col-12 col-md-12">
  <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit">' . $tr['Inquiry'] . '</button>
  </div>
</div>
';


// --------------------------------------
// 表單, JS 動作 , 按下submit_to_inquiry 透過 jquery 送出 post data 到 url 位址
// --------------------------------------
// $agent_inquiry_js = "
// $('#submit_to_inquiry').click(function(){
// 	var account_query  = $('#account_query').val();
// 	$.post('member_action.php?a=member_inquiry',
// 		{ account: account_query},
// 		function(result){
// 			$('#inquiry_result_area').html(result);}
// 	);
// });
// ";
$agent_inquiry_js = '';

// 按下 enter 後,等於 click 登入按鍵
$agent_inquiry_keypress_js = '
$(function() {
	 $(document).keydown(function(e) {
		switch(e.which) {
				case 13: // enter key
						$("#submit_to_inquiry").trigger("click");
				break;
		}
});
});
';
//清除用來判斷是否下載成功的cookie
setcookie("memberdown","",time()-1800);
// 匯出 Excel function
$exportExcelJSFunction = '
<script>
$(document).ready(function() {

	$("#excel").on("click", function(e){
	    // 判斷是否有搜尋的資料
	    var excel = $(\'#export\').val();
			var isQuery = $(\'#isQuery\').val();
	    if (excel == \'none\' && isQuery == 0) return;

	    // 取得要匯出的欄位
    	var selectCols = $("input.export_item:checkbox:checked").map(function() {
	    	  return $(this).val();
    	}).get().join(",");

	    // 擷取搜尋值
		var account_query  = $(\'#account_query\').val();
		var acc_type = $(\'.acc_type\').serialize();
		var register_date_start_time = $(\'#register_date_start_time\').val();
		var register_date_end_time = $(\'#register_date_end_time\').val();
		var account_cash_balance_lower = $(\'#account_cash_balance_lower\').val();
		var account_cash_balance_upper = $(\'#account_cash_balance_upper\').val();
		var account_token_balance_lower = $(\'#account_token_balance_lower\').val();
		var account_token_balance_upper = $(\'#account_token_balance_upper\').val();
		var mamber_status_select = $(\'#mamber_status_select\').val();
		var select_member_grade = $(\'.member_grade_checkbox_class\').serialize();
		var favorable_select = $(\'#favorable_select\').val();
		var agent_account = $(\'#agent_account\').val();
	    var registerip = $(\'#registerip\').val();
	    if(!ValidateIPaddress($(\'#registerip\').val()))
	    {
		    return false;
	    }
		var registerfingerprinting = $(\'#registerfingerprinting\').val();
		var real_name = $(\'#real_name\').val();
		var real_name_search = $(\'input[name=real_name_search]:checked\').val();
		var mobile_number = $(\'#mobile_number\').val();
		var mobile_number_search = $(\'input[name=mobile_number_search]:checked\').val();
		var sex_select = $(\'#sex_select\').val();
		var email = $(\'#email\').val();
		var email_search = $(\'input[name=email_search]:checked\').val();
		var birthday_start_date = $(\'#birthday_start_date\').val();
		var birthday_end_date = $(\'#birthday_end_date\').val();
		var wechat = $(\'#wechat\').val();
		var wechat_search = $(\'input[name=wechat_search]:checked\').val();
		var qq = $(\'#qq\').val();
		var qq_search = $(\'input[name=qq_search]:checked\').val();
		var bank_account = $(\'#bank_account\').val();
		var bank_account_search = $(\'input[name=bank_account_search]:checked\').val();
		var last_betting_start_date = $(\'#last_betting_start_date\').val();
		var last_betting_end_date = $(\'#last_betting_end_date\').val();
		var last_login_start_date = $(\'#last_login_start_date\').val();
		var last_login_end_date = $(\'#last_login_end_date\').val();

		// 回傳後端
		$("<input/>").attr("type", "hidden").attr("name", "account").val(account_query).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "acc_type").val(acc_type).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "register_date_start_time").val(register_date_start_time).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "register_date_end_time").val(register_date_end_time).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "account_cash_balance_lower").val(account_cash_balance_lower).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "account_cash_balance_upper").val(account_cash_balance_upper).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "account_token_balance_lower").val(account_token_balance_lower).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "account_token_balance_upper").val(account_token_balance_upper).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "mamber_status_select").val(mamber_status_select).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "select_member_grade").val(select_member_grade).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "favorable_select").val(favorable_select).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "agent_account").val(agent_account).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "registerip").val(registerip).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "registerfingerprinting").val(registerfingerprinting).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "real_name").val(real_name).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "real_name_search").val(real_name_search).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "mobile_number").val(mobile_number).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "mobile_number_search").val(mobile_number_search).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "sex_select").val(sex_select).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "email").val(email).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "email_search").val(email_search).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "birthday_start_date").val(birthday_start_date).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "birthday_end_date").val(birthday_end_date).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "wechat").val(wechat).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "wechat_search").val(wechat_search).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "qq").val(qq).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "qq_search").val(qq_search).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "bank_account").val(bank_account).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "bank_account_search").val(bank_account_search).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "last_betting_start_date").val(last_betting_start_date).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "last_betting_end_date").val(last_betting_end_date).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "last_login_start_date").val(last_login_start_date).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "last_login_end_date").val(last_login_end_date).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "excel").val(excel).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "isQuery").val(isQuery).appendTo("#excelform");
		$("<input/>").attr("type", "hidden").attr("name", "selectCols").val(selectCols).appendTo("#excelform");
		$("#excelform").submit();
		$("#adv_export").modal("hide");	

		//load動畫載入
		var loadanimatedown = `
		<div id="loadingdown">
		<h5 align="center">资料下载中...<img width="30px" height="30px" src="ui/loading.gif" /></h5>
		</div>
		`;		
		$("body").append(loadanimatedown);
		
		//查詢目前的cookie
		var cookies = document.cookie;

		//執行查詢
		var timecookie = setInterval(getcookie, 2000);
		//如果一直查詢成功就在1分鐘關閉
		var stoptimecookie = setTimeout(stopcookie, 60000);

		//停止尋找 cookie
		function stopcookie() {
			clearInterval(timecookie);
		}

		//移除 cookie
		function delete_cookie(name) {
			document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:01 GMT;";
			clearInterval(timecookie);
			//console.log("移除+停止 :" + document.cookie);
		}

		//尋找 cookie
		function getcookie() {
			var cookies = document.cookie;
			var cookieval = "memberdown=false";
			var cookiearray = cookies.split(";");
			const newarray = [];
			cookiearray.forEach(function(item){
				newarray.push(item.trim())
			});

			if ( newarray.indexOf(cookieval) != "-1" ){
				$("#loadingdown").remove();
				delete_cookie("memberdown");
			}
			// console.log(cookiearray);	
			// console.log(newarray);	
			//console.log(newarray.indexOf(cookieval));				
		}
	});
	// var cookies = document.cookie;
	// console.log("開始 : " + "cookies :" +cookies);
});
</script>
<style>
	body{
		position: relative;
	}
	#loadingdown{
		position: fixed;
		z-index: 999;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.6);
		top: 0;
	}
	#loadingdown h5{
		display: block;
		background: #FFF;
		width: 380px;
		max-width: 50%;
		padding: 2%;
		margin: 20% auto 0 auto;
		border-radius: 8px;
	}
</style>
';

$agent_inquiry_js_html = "
<script>
	$(document).ready(function() {
		". $agent_inquiry_js ."
	});
	" . $agent_inquiry_keypress_js . "
</script>
";

// ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
// 取得日期的 jquery datetime picker -- for birthday
$extend_head = $extend_head . '<link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>';
$extend_js = $extend_js . '<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
						   <link rel="stylesheet"  href="ui/style_seting.css">';

// date 選擇器 https://jqueryui.com/datepicker/
// http://api.jqueryui.com/datepicker/
// 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
$dateyearrange_start = date("Y") - 100;
$dateyearrange_end = date("Y/m/d");
$datedefauleyear = date("Y/m/d");

// $betdate_range_start = date("Y/m/d");
$d = new DateTime(date("Y/m/d"));
$d->modify('-1 month');
$betdate_range_start = $d->format('Y/m/d');
$betdate_range_end = date("Y/m/d");
$datedefaule = date("Y/m/d");

$extend_js = $extend_js . "
<script>
// for select day
$('#register_date_start_time, #register_date_end_time, #birthday_start_date, #birthday_end_date, #last_login_start_date, #last_login_end_date').datetimepicker({
	defaultDate:'" . $datedefauleyear . "',
	minDate: '" . $dateyearrange_start . "/01/01',
	maxDate: '" . $dateyearrange_end . "',
	timepicker:false,
	format:'Y/m/d',
	lang:'en'
});

$('#last_betting_start_date, #last_betting_end_date').datetimepicker({
	defaultDate:'" . $datedefaule . "',
	minDate: '" . $betdate_range_start . "',
	maxDate: '" . $betdate_range_end . "',
	timepicker:false,
	format:'Y/m/d',
	lang:'en'
});
</script>
";

$extend_js = $extend_js . "
<script>
// 全選.取消全選
$(document).ready(function() {
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
});
</script>
";
		// var acc_type = $('input[name=acc_type]:checked').val();		console.log(acc_type);

$extend_js = $extend_js . "
<script>
// 查詢會員
function queryMember(){
    var account_query  = $('#account_query').val();
		// console.log(account_query);

	var acc_type = $('.acc_type').serialize();
	var register_date_start_time = $('#register_date_start_time').val();
	var register_date_end_time = $('#register_date_end_time').val();
	var account_cash_balance_lower = $('#account_cash_balance_lower').val();
	var account_cash_balance_upper = $('#account_cash_balance_upper').val();

	var account_token_balance_lower = $('#account_token_balance_lower').val();
	var account_token_balance_upper = $('#account_token_balance_upper').val();
	var mamber_status_select = $('#mamber_status_select').val();

	var select_member_grade = $('.member_grade_checkbox_class').serialize();

	var favorable_select = $('#favorable_select').val();

	var agent_account = $('#agent_account').val();

    var registerip = $('#registerip').val();
    if(!ValidateIPaddress($('#registerip').val()))
    {
	    return false;
    }

	var registerfingerprinting = $('#registerfingerprinting').val();

	//------

	var real_name = $('#real_name').val();
	var real_name_search = $('input[name=real_name_search]:checked').val();

	var mobile_number = $('#mobile_number').val();
	var mobile_number_search = $('input[name=mobile_number_search]:checked').val();

	var sex_select = $('#sex_select').val();

	var email = $('#email').val();
	var email_search = $('input[name=email_search]:checked').val();

	var birthday_start_date = $('#birthday_start_date').val();
	var birthday_end_date = $('#birthday_end_date').val();

	var wechat = $('#wechat').val();
	var wechat_search = $('input[name=wechat_search]:checked').val();

	var qq = $('#qq').val();
	var qq_search = $('input[name=qq_search]:checked').val();

	var bank_account = $('#bank_account').val();
	var bank_account_search = $('input[name=bank_account_search]:checked').val();

	var last_betting_start_date = $('#last_betting_start_date').val();
	var last_betting_end_date = $('#last_betting_end_date').val();

	var last_login_start_date = $('#last_login_start_date').val();
	var last_login_end_date = $('#last_login_end_date').val();

	$.post('member_action.php?a=member_inquiry',
	{
			account: account_query,
			acc_type: acc_type,
			register_date_start_time: register_date_start_time,
			register_date_end_time: register_date_end_time,
			account_cash_balance_lower: account_cash_balance_lower,
			account_cash_balance_upper: account_cash_balance_upper,

			account_token_balance_lower: account_token_balance_lower,
			account_token_balance_upper: account_token_balance_upper,

			mamber_status_select: mamber_status_select,

			select_member_grade: select_member_grade,

			favorable_select: favorable_select,

			agent_account: agent_account,

			registerip: registerip,

			registerfingerprinting: registerfingerprinting,

			real_name: real_name,
			real_name_search: real_name_search,

			mobile_number: mobile_number,
			mobile_number_search: mobile_number_search,

			sex_select: sex_select,

			email: email,
			email_search: email_search,

			birthday_start_date: birthday_start_date,
			birthday_end_date: birthday_end_date,

			wechat: wechat,
			wechat_search: wechat_search,

			qq: qq,
			qq_search: qq_search,

			bank_account: bank_account,
			bank_account_search: bank_account_search,

			last_betting_start_date: last_betting_start_date,
			last_betting_end_date: last_betting_end_date,

			last_login_start_date: last_login_start_date,
			last_login_end_date: last_login_end_date,

		},
		function(result){
    	    $('#inquiry_result_area').html(result);
		}
	);
}

var loadanimate = `
<div id='loading'>
<h5 align='center'>{$tr['Data query']}...<img width='30px' height='30px' src='ui/loading.gif' /></h5>
</div>
`;

$(document).ready(function() {
	$('#submit_to_inquiry').click(function(){
		$('#inquiry_result_area').html(loadanimate);
		queryMember('query');
		$('#isQuery').val(1);
		$('#export').val('excel');
	});
});

function ValidateIPaddress(ipaddress)
{
  if(ipaddress != ''){
   if (/^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(ipaddress))
    {
      return (true)
    }
    alert('请输入正确的IP位址!')
    return (false)
  }else{
    return (true)
  }
}
</script>";
// 設定datatable 高度
// JS 放在檔尾巴
$extend_js = $extend_js . $agent_inquiry_js_html;
$extend_js .= $exportExcelJSFunction;
// --------------------------------------
// jquery post ajax send end.
// --------------------------------------

// --------------------------------------
// 右方工作區內容 -- show account name and information
// --------------------------------------
//請輸入查詢條件
$panelbody_content = $panelbody_content . '
<div id="inquiry_result_area">
	<div class="alert alert-info" role="alert">
	<span class="glyphicon glyphicon-filter" aria-hidden="true"></span>' . $tr['search query info'] . '
	</div>
</div>
';

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title . '-' . $tr['host_name'];

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