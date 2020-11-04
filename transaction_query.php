<?php
// ----------------------------------------------------------------------------
// Features: 交易紀錄查詢
// File Name:	transcation_query.php
// Author: Neil
// Related:
// Log:
// 2020.08.06 Bug #4409 VIP站後台，交易紀錄查詢 > 詳細 > 遊戲幣派彩、現金轉遊戲幣 > 無交易單號 Letter
// 1. 派彩時，交易紀錄明細隱藏交易單號欄位
// 2. 現金轉遊戲幣時，交易紀錄明細隱藏交易單號及派彩欄位
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 報表匯出函式庫
require_once dirname(__FILE__) ."/lib_file.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";

// 搜尋開始時間、結束時間函式
require_once dirname(__FILE__) ."/deposit_withdrawal_company_audit_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

global $tr;
// 功能標題，放在標題列及meta
$function_title 		= $tr['Transaction history query'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// 查詢欄（左）title及內容
$indextitle_content = '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>' . $tr['Search criteria'];
$indexbody_content  = '';
// 結果欄（右）title及內容
$paneltitle_content = '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>' . $tr['Query results'];
$paneltitle_content .= '
<div id="csv" style="float:right;margin-bottom:auto"></div>';

$panelbody_content  = '';

$menu_breadcrumbs   = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Account Management'].'</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';

if(!isset($_SESSION['agent']) || $_SESSION['agent']->therole != 'R') {
  header('Location:./home.php');
  die();
}

$extend_head = <<<HTML
<!-- Jquery UI js+css  -->
<script src="in/jquery-ui.js"></script>
<link rel="stylesheet"  href="in/jquery-ui.css" >
<link rel="stylesheet"  href="ui/style_seting.css">
<!-- Jquery blockUI js  -->
<script src="./in/jquery.blockUI.js"></script>
<!-- jquery datetimepicker js+css -->
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<!-- Datatables js+css  -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<style type="text/css">
.ck-button {
    margin:0px;
    border:1px solid #D0D0D0;
    /*border-right-style: none;
    border-top-style: none;*/
    overflow:auto;
    float:left;
    width: 50%;
}

.ck-button:hover {
    /*background:red;*/
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

.ck-button label span ,.lh p{
    text-align:center;
    display:block;
    font-size: 15px;
    line-height: 38px;
}

.ck-button label input {
    position:absolute;
    z-index: -5;
    /*top:-20px;*/
}

.ck-button input:checked + span {
    border-color: #007bff;
    background-color: #007bff;
    color: #fff;
}

.transaction_cat_css{
    padding: 0px 0px 0px 32px;
}
input[name=transactionAllType], input[name=transactionType] {
    display: none;
}

input[name=transactionAllType] + label {
    width: 60px;
}
input[name=transactionType] + label {
    width: 100%;
}
input[name=transactionAllType]:checked + label, input[name=transactionType]:checked + label {
    border-color: #007bff;
    background-color: #007bff;
    color: #fff;
}
input[name=transactionAllType] + label,
input[name=transactionType] + label {
    min-height: 30px;
    text-align: center;
    /* line-height: 30px; */
    line-height: normal;
    box-sizing: border-box;
    border: 1px #6c757d solid;
    color: #6c757d;
    background-color: transparent;
    border-radius: 0.25rem;
    transition: all 0.2s;
    font-size: .5em;
    word-wrap: break-word;
    word-break: break-all;
}
label[for="transactionAllType"]{
    position: absolute;
    top: 6px;
    left: 5px;
}
input[name=transactionAllType]:checked ~ div input[name=transactionType]:checked + label {
	border-color: #6c757d;
	color: #6c757d;
	background-color: transparent;
}
/*.ck-button:nth-child(2n) label{
    border:1px solid #D0D0D0;
    border-top-style: none;
    border-bottom-style: none;
    border-right-style: none;
}*/
</style>
HTML;

$query_sql = '';
$query_chk = 0;
$query_sql = '';
if (isset($_GET['a'])) {
  $query_sql     = '&a=' . filter_var($_GET['a'], FILTER_SANITIZE_STRING);
  $account_query = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
  $query_chk = 1;

// 交易序號
// }elseif(isset($_GET['trans_id']) AND filter_var($_GET['trans_id'], FILTER_SANITIZE_STRING)) {
//     $query_sql = '&trans_id='.$_GET['trans_id'];
//     $trans_id_query = $_GET['trans_id'];
//     $query_chk = 1;

} elseif(isset($_GET['trans_id']) AND filter_var($_GET['trans_id'], FILTER_SANITIZE_STRING)) {
  $query_sql = '&trans_id='.$_GET['trans_id'];
  $trans_id_query = $_GET['trans_id'];
  $query_chk = 1;

} elseif(isset($_GET['gcash_deposit_lower']) AND filter_var($_GET['gcash_deposit_lower'], FILTER_SANITIZE_STRING)) {
  $query_sql = '&gcash_deposit_lower='.$_GET['gcash_deposit_lower'];
  $gcash_deposit_lower_query = $_GET['gcash_deposit_lower'];
  $query_chk = 1;

} elseif(isset($_GET['gcash_deposit_upper']) AND filter_var($_GET['gcash_deposit_upper'], FILTER_SANITIZE_STRING)) {
  $query_sql = '&gcash_deposit_upper='.$_GET['gcash_deposit_upper'];
  $gcash_deposit_upper_query = $_GET['gcash_deposit_upper'];
  $query_chk = 1;

}

if (isset($_GET['passbook_query']) and $_GET['passbook_query'] != null) {
  $query_database_array['passbook_query'] = filter_var_array($_GET['passbook_query'], FILTER_SANITIZE_STRING);
  foreach($query_database_array['passbook_query'] as $query_database_array_value){
    $query_sql.="&passbook_query[]=".$query_database_array_value;
  }
  $query_chk = 1;
}

// datepicker
// 搜尋開始、結束時間function
$search_time = time_convert();

// if($query_chk!=1) {
  // 預設區間為一個月
  if(!isset($sdate_query) AND !isset($edate_query)) {
    // 轉換為美東的時間 date
    // $current_datepicker = gmdate('Y-m-d H:i:s',time() + -4*3600);
    // $default_startdate = gmdate('Y-m-d H:i:s',strtotime('- 7 day') + -4*3600);

    // $sdate_query = $default_startdate;
    // $edate_query = $current_datepicker;

    $sdate_query = $search_time['default_min_date'].$search_time['min'];
    $edate_query = $search_time['current'].$search_time['max'];
    $query_sql = $query_sql.'&sdate='.$sdate_query;
    $query_sql = $query_sql.'&edate='.$edate_query;
  } elseif(!isset($sdate_query)) {
    // $default_startdate = gmdate('Y-m-d H:i:s',strtotime('- 7 day',$edate_query));


    // $sdate_query = $default_startdate;
    $sdate_query = $search_time['default_min_date'].$search_time['min'];

    $query_sql = $query_sql.'&sdate='.$sdate_query;
  } elseif(!isset($edate_query)) {
    // $current_datepicker = gmdate('Y-m-d H:i:s',time() + -4*3600);
    // $edate_query = $current_datepicker;
    
    $edate_query = $search_time['current'].$search_time['max'];

    $query_sql = $query_sql.'&edate='.$edate_query;
  }
// } else {
//   $sdate_query=$edate_query='';
// }

if( $query_chk == 0) {
  $query_sql = '';
}

$trans_id_query = $trans_id_query ?? '';
$account_query = $account_query ?? '';


$realarr = array(""=>$tr['grade default'],"1"=>$tr['y'],"0"=>$tr['n']);
$real_option='';
foreach($realarr as $realkey =>$realval){
  $real_option = $real_option.'<option value="'.$realkey.'">'.$realval.'</option>';
}


$member_grade_sql = "SELECT id, gradename FROM root_member_grade;";
$member_grade_sql_result = runSQLall($member_grade_sql);
if ($member_grade_sql_result[0] >= 1) {
	$member_grade_html = '';
	for ($i = 1; $i <= $member_grade_sql_result[0]; $i++) {
		$member_grade_html = $member_grade_html . '
		<div class="col-sm-3">
			<label class="text-ellipsis ng-binding" title="' . $member_grade_sql_result[$i]->gradename . '">
				<input type="checkbox" name="member_grade_checkbox" value="' . $member_grade_sql_result[$i]->id . '" class="member_grade_checkbox_class" checked>
				' . $member_grade_sql_result[$i]->gradename . '
			</label>
		</div>
		';

		if ($i % 4 == 0) {
			$member_grade_html = $member_grade_html . '
			<br>
			';
		}
	}
} else {
	$member_grade_html = $tr['Member level query error'];
}


$transaction_ary=[
  $tr['Artificial deposit']  =>"manualDeposit", // "人工存款"
  $tr['Artificial withdrawal']  =>"manualWithdrawal", // "人工提款"
  $tr['Online deposit']  =>"onlineDeposit", // "线上存款"
  // $tr['Online withdrawal']  =>"onlineWithdrawals", // "线上提款"
  $tr['company deposits']  =>"companyDeposits", // "公司入款"
  $tr['commission of agent']  =>"agencyCommission", //"代理佣金"
  $tr['cash transfer']  =>"agencyTransfer", // "现金转帐"
  $tr['Wallet transfer'] =>"walletTransfer", // "钱包转帐"
  $tr['Promotions']  =>"promotions", // "优惠活动"
  $tr['Payout']      =>"payout", // "派彩"
  $tr['Bonus']      =>"bouns", // "反水"
  $tr['others']      =>"other", // "其它"
  $tr['withdrawal administration fee'] =>"withdrawalAdministrationFee", // "提款行政费"
];

$transaction_caty = '';
foreach ($transaction_ary as $k => $v){
  $transaction_caty .= '
	<div class="col-4 col-md-12 col-lg-6 col-xl-4 px-1">
		<input class="form-check-input transactionType" type="checkbox" name="transactionType" id="'.$v.'" value="'.$v.'" checked>
		<label for="'.$v.'" class="d-flex justify-content-center align-items-center">'.$k.'</label>
	</div>
  ';
}


$indexbody_content .= <<< HTML
<div class="row">
  <div class="col-12"><label>{$tr['Transaction order number']}</label></div>
  <div class="col-12 form-group">
    <input type="text" class="form-control" name="transactionId" id="transactionId" placeholder="{$tr['Please enter transaction order number']}" value="{$trans_id_query}">
  </div>
</div>
HTML;

if(!isset($_GET['m'])) {
$indexbody_content .= <<< HTML
<div class="row">
  <div class="col-12"><label>{$tr['Account']}</label></div>
  <div class="col-12 form-group">
      <input type="text" class="form-control" name="account" id="account" placeholder="{$tr['Account']}" value="{$account_query}">
  </div>
</div>
HTML;
}
$indexbody_content .= <<< HTML
<div class="row">
    <div class="col-12 d-flex">
      <label>{$tr['Transaction time']}</label>
      <div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
        <button type="button" class="btn btn-secondary first">{$tr['grade default']}</button>
        <div class="btn-group btn-group-sm" role="group">
          <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            <a class="dropdown-item week" onclick="settimerange('{$search_time['thisweekday']}{$search_time['min']}', getnowtime(),'week');">{$tr['This week']}</a>
            <a class="dropdown-item month" onclick="settimerange('{$search_time['thismonth']}{$search_time['minus']}',getnowtime(),'month');">{$tr['this month']}</a>
            <a class="dropdown-item today" onclick="settimerange('{$search_time['current']}{$search_time['min']}', getnowtime(),'today')">{$tr['Today']}</a>
            <a class="dropdown-item yesterday" onclick="settimerange('{$search_time['yesterday']}{$search_time['min']}', '{$search_time['yesterday']}{$search_time['max']}','yesterday');">{$tr['yesterday']}</a>
            <a class="dropdown-item lastmonth" onclick="settimerange('{$search_time['lastmonth']}{$search_time['minus']}','{$search_time['lastmonth_e']}{$search_time['max']}','lastmonth');">{$tr['last month']}</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 form-group rwd_doublerow_time">
      <div class="input-group">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text">{$tr['start']}</span>
          </div>
          <input type="text" class="form-control" name="startDate" id="startDate" placeholder="ex:2017-01-20" value="{$sdate_query}">
        </div>
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text">{$tr['end']}</span>
          </div>
          <input type="text" class="form-control" name="endDate" id="endDate" placeholder="ex:2017-01-20" value="{$edate_query}">
        </div>
      </div>
    </div>
</div>

<!-- <div class="row">
  <div class="col-12"><label>{$tr['transaction start time']}</label></div>
  <div class="col-12">
    <input type="text" class="form-control" name="startDate" id="startDate" placeholder="ex:2017-01-20 00:00:00" value="{$sdate_query}">
  </div>
</div>
<br>
<div class="row">
  <div class="col-12"><label>{$tr['transaction end time']}</label></div>
  <div class="col-12">
    <input type="text" class="form-control" name="endDate" id="endDate" placeholder="ex:2017-01-20 00:00:00" value="{$edate_query}">
  </div>
</div> -->

<div class="row">
  <div class="col-12"><label>{$tr['deposit amount']}</label></div>
  <div class="col-12 form-group">
    <div class="input-group">
      <input type="number" class="form-control" step=".01" min="0" placeholder='{$tr['Lower limit']}' id="depositLower" name="depositLower" value="">
      <span class="input-group-addon" id="basic-addon1">~</span>
      <input type="number" class="form-control" step=".01" min="0" placeholder='{$tr['Upper limit']}' id="depositUpper" name="depositUpper" value="">
    </div>
  </div>
</div>
<div class="row">
  <div class="col-12"><label>{$tr['withdrawal amount']}</label></div>
  <div class="col-12 form-group">
    <div class="input-group">
      <input type="number" class="form-control" step=".01" min="0" placeholder='{$tr['Lower limit']}' id="withdrawalLower" name="withdrawalLower" value="">
      <span class="input-group-addon" id="basic-addon1">~</span>
      <input type="number" class="form-control" step=".01" min="0" placeholder='{$tr['Upper limit']}' id="withdrawalUpper" name="withdrawalUpper" value="">
    </div>
  </div>
</div>
<div class="row">
  <div class="col-12"><label>{$tr['Actual deposit']}</label></div>
  <div class="col-12 form-group">
    <select name="realCash" class="form-control" id="realCash" size=1>
      {$real_option}
    </select>
  </div>
</div>
<div class="row">
  <div class="col-12"><label>{$tr['Member Level']}</label></div>
  <div class="col-12 form-group">
  <div id="member_grade_preview_area"><p class="mb-1">{$tr['select all']}</p></div>
		<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal">{$tr['Select']}</button>

		<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="myModalLabel">{$tr['select a member level']}</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					</div>
					<div class="modal-body">
						<button type="button" class="btn btn-xs" id="select_all_checkbox">{$tr['select all']}</button>
						<button type="button" class="btn btn-xs" id="cancel_select_all_checkbox">{$tr['Emptied']}</button>
						<br>
						<br>
						<div class="row">
						  {$member_grade_html}
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
<div class="row">
  <div class="col-12 form-group">
		<div class="card search_option_card">
			<div class="card-header">
				<p class="text-center font-weight-bold mb-0">{$tr['type']}</p>
			</div>
			<div class="card-body input_lineheight">
				<div class="row">
					<input type="checkbox" id="transactionAllType" name="transactionAllType" value="all" checked="checked">
      		<label class="form-check-label d-flex align-items-center justify-content-center" for="transactionAllType">{$tr['select all']}</label>  					
          {$transaction_caty}
				</div>
			</div>
		</div>     
  </div>
</div>
<div class="row">
  <div class="col-12"><label>{$tr['Choosing a Wallet']}</label></div>
  <div class="col-12" id="passbooks">
    <div class="ck-button">
      <label>
        <input type="checkbox" class="passbook" id="cashPassbook" name="cashPassbook" value="cash" checked>
        <span class="class_cash_passbook">{$tr['Franchise']}</span>
      </label>
    </div>
    <div class="ck-button">
      <label>
        <input type="checkbox" class="passbook" id="tokenPassbook" name="cashPassbook" value="token" checked>
        <span class="class_token_passbook">{$tr['Gtoken']}</span>
      </label>
    </div>
  </div>
 </div>
 <hr>
<div class="row">
  <div class="col-12 col-md-12">
  <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit">{$tr['Inquiry']}</button>
  </div>
</div>
HTML;

$panelbody_content = <<<HTML
<div id="show_summary"></div>
<table id="show_list" class="display" width="100%">
  <thead>
    <tr>
      <th>{$tr['ID']}</th>
      <th>{$tr['Account']}</th>
      <th>{$tr['Trading Hours']}</th>
      <th>{$tr['Transaction Category']}</th>
      <th>{$tr['Deposit']}</th>
      <th>{$tr['Withdrawal']}</th>
      <th>{$tr['Payout']}</th>
      <th>{$tr['Token Balance']}</th>
      <th>{$tr['Cash Balance'] }</th>
      <th>{$tr['detail']}</th>
    </tr>
  </thead>
  <tfoot>
    <tr>
      <th>{$tr['ID']}</th>
      <th>{$tr['Account']}</th>
      <th>{$tr['Trading Hours']}</th>
      <th>{$tr['Transaction Category']}</th>
      <th>{$tr['Deposit']}</th>
      <th>{$tr['Withdrawal']}</th>
      <th>{$tr['Payout']}</th>
      <th>{$tr['Token Balance']}</th>
      <th>{$tr['Cash Balance'] }</th>
      <th>{$tr['detail']}</th>
    </tr>
  </tfoot>
</table>
<!-- <div class="row">
  <div id="preview_result"></div>
</div> -->
HTML;

$form_account_js=(isset($_GET['m']))?"'".$account_query."'":"$('#account').val()";

$extend_js .= <<<HTML
<script>
$(document).ready(function() {
  var table = jsonInitDatatable();
  var data = getSearchRequirementValue();

  initDataTable(data, 'init', table);
});

$('#submit_to_inquiry').on("click", function(){
  var data = getSearchRequirementValue();
  var table = $('#show_list').DataTable();
  // table.destroy();

  if (data.grade.length == 0) {
    table.clear();
    table.draw();
  } else {
    initDataTable(data, 'init', table);
  }
  // console.log(data);
});

$('#payout').on('change', function() {
  var table = $('#show_list').DataTable();

  if ($('#transactionAllType').prop('checked')) {
    table.columns([6]).visible(false, false);
  } else {
    table.columns([6]).visible(true, false);
  }
});

// $('#transactionAllType').on('change', function() {
//   $('#transactionAllType').click();
//   //$('.transactionType').prop('checked', true);
//   // if ($('#transactionAllType').prop('checked')) {
//   //   $('.transactionType').prop('checked', true);
//   // } else {
//   //   $('.transactionType').prop('checked', false);
//   // }
// });

//全選
$('#transactionAllType').on('change', function() {
  $('#transactionAllType').click();
  $('#transactionAllType').prop('checked', true);
  $('input[name="transactionType"]').prop('checked', true);
});

$('.passbook').on('change', function() {
  if (!$('#cashPassbook').prop('checked')) {
    $('#agencyTransfer').prop('checked', false);
  } else {
    $('#agencyTransfer').prop('checked', true);
  }

  if (!$('#tokenPassbook').prop('checked')) {
    $('#payout').prop('checked', false);
  } else {
    $('#payout').prop('checked', true);
  }

  if ($('.transactionType:checked').length == $('.transactionType').length || $('.transactionType:checked').length == 0) {
    $('#transactionAllType').click();
    $('#transactionAllType').prop('checked', true);
  } else {
    $('#transactionAllType').prop('checked', false);
  }
});

$('.transactionType').on('change', function() {
	if( $('#transactionAllType:checked').length > 0 ){
		$('.transactionType').prop('checked', false);
		$(this).prop('checked', true);
	}

  if ($('.transactionType:checked').length == $('.transactionType').length) {
    $('#transactionAllType').prop('checked', true);
  } else {
    $('#transactionAllType').prop('checked', false);
  }

  if( $('.transactionType:checked').length == 0 ){
      $('#transactionAllType').prop('checked', true);
      $('#transactionAllType').click();
	}
});

function initDataTable(data, action, table)
{
  var postData = JSON.stringify(data);

  if ($('#csvDownloadBtn').length != 0) {
    $('#csvDownloadBtn').remove();
  }

  $.blockUI({ message: "<img src=\"ui/loading_text.gif\" />" });
  $.ajax({
    type: 'POST',
    url: 'transaction_query_action.php',
    data: {
      action: action,
      data: postData
    },
    success: function(resp) {
      // $('#preview_result').html(resp);
      var res = JSON.parse(resp);
      if (res.status == 'success') {
        if ($.fn.dataTable.isDataTable('#show_list')) {
          table.clear();
          table.rows.add(res.result.datatable.data);
          table.draw();
        } else {
          jsonInitDatatable(res.result.datatable.data);
        }

        var csvDownloadBtn = `
        <a href="\${res.result.downloadUrl}" class="btn btn-success btn-sm" id="csvDownloadBtn" role="button" aria-pressed="true" target="_NEW" >{$tr['Export Excel']}</a>
        `;

        $.unblockUI();
        $("#show_summary").html(summary_tmpl(res.result.sum));
        $("#csv").html(csvDownloadBtn);

      } else if(res.status == 'permissionError') {
        $.unblockUI();
        setTimeout(() => {
          alert(res.result);
          window.location.href = './home.php'
        }, 500);
      } else {
        $.unblockUI();
        alert(res.result);
      }
    }
  });
}
function getnowtime(){
    // var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD HH:mm');
    var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+' 23:59';


    return NowDate;
}

// 本日、昨日、本周、上周、上個月button
function settimerange(sdate,edate){
  $("#startDate").val(sdate);
  $("#endDate").val(edate);
}


function summary_tmpl(result){
  return `
  <table class="table">
    <tr>
      <th style="text-align:center;">{$tr['totalamount']}</th>
      <th style="text-align:center;">{$tr['Deposit']}</th>
      <th style="text-align:center;">{$tr['Withdrawal']}</th>
    </tr>
    <tr>
      <td align="center">\${result.total}</td>
      <td align="center">\${result.deposit_sum}</td>
      <td align="center">\${result.withdrawal_sum}</td>
    </tr>
  </table>
  `;
}

function getSearchRequirementValue()
{
  var data = {
    'transactionId' : $('#transactionId').val(),
    'account' : {$form_account_js},
    'startDate' : $('#startDate').val(),
    'endDate' : $('#endDate').val(),
    'depositLower' : $('#depositLower').val(),
    'depositUpper' : $('#depositUpper').val(),
    'withdrawalLower' : $('#withdrawalLower').val(),
    'withdrawalUpper' : $('#withdrawalUpper').val(),
    'realCash' : $('#realCash').val(),
    'grade' : getSelectGradeValue(),
    'transactionType' : getSelectTransactionTypeValue(),
    'passbook' : getSelectPassBookValue()
  };

  return data;
}

function getSelectGradeValue() {
  var gradeVals = [];

  $('.member_grade_checkbox_class:checked').each(function() {
    gradeVals.push($(this).val());
  });

  return gradeVals;
}

function getSelectTransactionTypeValue() {
  var transactionTypeVals = [];

  $('.transactionType:checked').each(function() {
    transactionTypeVals.push($(this).val());
  });

  return transactionTypeVals;
}

function getSelectPassBookValue()
{
  var passbookVals= [];

  $('.passbook:checked').each(function() {
    passbookVals.push($(this).val());
  });

  if (passbookVals.length == 0) {
    passbookVals.push('cash');
    passbookVals.push('token');
  }

  return passbookVals;
}

function jsonInitDatatable(data = '')
{
  var table = $("#show_list").DataTable({
    "order": [[ 2, "desc" ]],
    "bProcessing" : true,
    "bserverSide": true,
    "bretrieve": true,
    "bsearching": true,
    // "pageLength": 1,
    "oLanguage": {
      //"sSearch": "會員帳號:",
      "sEmptyTable": "{$tr['no data']}",//"目前沒有資料",
      "sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每頁顯示 _MENU_ 筆",
      "sZeroRecords": "{$tr['no data']}",//"目前沒有資料",
      "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 頁，共 _PAGES_ 頁",
      "sInfoEmpty": "{$tr['no data']}",//"目前沒有資料",
      "sInfoFiltered": "({$tr['from']}_MAX_{$tr['filtering in data']})"//"(從 _MAX_ 筆資料中過濾)"
    },
    "data": data,
    "columns": [
      { "data": "id"},
      { "data": "account"},
      { "data": "transtime"},
      { "data": "transaction_category"},
      { "data": "deposit"},
      { "data": "withdrawal"},
      { "data": "payout"},
      { "data": "token_balance"},
      { "data": "cash_balance"},
      { "data": "detail_trans"}
    ],
    "columnDefs": [
      { className: "dt-right", "targets": [4,5,6,7,8]},
      { className: "dt-center", "targets": [0,1,2,3,9] },
      { "orderable": false, "targets": [9] }
    ],
    createdRow: function (row, data, dataIndex) {
      if ( data.payout < 0 ) {
        $('td', row).eq(6).css( "color", "green" );
      }else{
        $('td', row).eq(6).css( "color", "red" );
      }
    }
  });

  return table;
}

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

$(document).on("click",'#select_all_checkbox',function(){
  $('.modal-body input:checkbox').prop('checked', true);
  submit_select();
});

$(document).on("click",'#cancel_select_all_checkbox',function(){
  $('.modal-body input:checkbox').prop('checked', false);
  submit_select();
});

$(document).on("click",'.member_grade_checkbox_class',function(){
  submit_select();
});

$(document).keydown(function(e) {
  if(!$(".detailModal").hasClass("show")){
    switch(e.which) {
      case 13:
          $("#submit_to_inquiry").trigger("click");
      break;
    }
  }
});

$( "#startDate" ).datetimepicker({
  showButtonPanel: true,
  formatTime: "H:i",
  format: "Y-m-d H:i",
  defaultTime: '00:00',
  changeMonth: true,
  changeYear: true,
  step:1
  });

  $( "#endDate" ).datetimepicker({
    showButtonPanel: true,
    formatTime: "H:i",
    format: "Y-m-d H:i",
    defaultTime: '23:59',
    changeMonth: true,
    changeYear: true,
    step:1
  });
</script>
HTML;


// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author']      = $tr['host_author'];
$tmpl['html_meta_title']       = $function_title . '-' . $tr['host_name'];


// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 兩欄分割--左邊
$tmpl['indextitle_content'] = $indextitle_content;
$tmpl['indexbody_content']  = $indexbody_content;
// $tmpl['indexbody_content']  =  '<form id="query-form">' . $indexbody_content . '</form>';
// 兩欄分割--右邊
$tmpl['paneltitle_content'] = $paneltitle_content;
$tmpl['panelbody_content']  = $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
if(isset($_GET['m'])) {
  $page = 'transaction_query';
  include "template/member_overview_history.tmpl.php";
}else{
  include "template/s2col.tmpl.php";
}