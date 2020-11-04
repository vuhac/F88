<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員操作記錄
// File Name:	member_log.php
// Author:		eaouyuan@gmail.com
// Related:
//    member_log_action.php member_log_lib.php
//    DB table: root_memberlog
//    member_log：有收到 _GET 時會將 _GET 取得的值進行驗證，並檢查是否為可查詢對象，如果是
//        就直接丟入 $query_sql_array 中再引用 member_log_lib.php 中的涵式
//        show_member_logininfo($query_sql_array) 並將返回的資料放入 table 中給
//        datatable 處理。如果沒收到 _GET 值就顯示無資料的查詢介面，並在使用者按下"查詢"時
//        以 ajax 丟給 member_log_action.php 來查詢並將返回資料顯示出來。
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

// -------------------------------------------------------------------------
// PHP function
// -------------------------------------------------------------------------
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -------------------------------------------------------------------------
// PHP function END
// -------------------------------------------------------------------------


// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------
// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);

$account_query='';
if(isset($_GET['a'])) {
    $account_query = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
}

// ip validate
$ip_query = '';
if(isset($_GET['ip']) AND filter_var($_GET['ip'], FILTER_VALIDATE_IP)) {
    $ip_query = $_GET['ip'];
}

// fp validate
$fingerprint_query = '';
if(isset($_GET['fp'])) {
    $fingerprint_query = filter_var($_GET['fp'], FILTER_SANITIZE_STRING);
}

// sdate validate
if(isset($_GET['sdate'])  AND $_GET['sdate'] != NULL  ) {
    // 原版
    if(validateDate($_GET['sdate'], 'Y-m-d')) {
    // if(validateDate($_GET['sdate'], 'Y-m-d H:i')) {
    
        $sdate_query = $_GET['sdate'];
    }
}

// edate validate
if(isset($_GET['edate'])  AND $_GET['edate'] != NULL  ) {
    // 原版
    if(validateDate($_GET['edate'], 'Y-m-d')) {
    // if(validateDate($_GET['edate'], 'Y-m-d H:i')) {
        $edate_query = $_GET['edate'];
    }
}


// 預設區間為三個月
// if(!isset($sdate_query) AND !isset($edate_query)){
//     // 轉換為美東的時間 date
//     $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
//     // die(var_dump($current_datepicker));
//     $default_startdate = gmdate('Y-m-d',strtotime('-2 months',time()) + -4*3600);

//     //var_dump($default_startdate);
//     $sdate_query = $default_startdate;
//     $edate_query = $current_datepicker;
// }
// elseif(!isset($sdate_query)){
//     $default_startdate = gmdate('Y-m-d',strtotime('-7 days',$edate_query));
//     //var_dump($default_startdate);
//     $sdate_query = $default_startdate;
// }
// elseif(!isset($edate_query)){
//     $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
//     $edate_query = $current_datepicker;
// }


// 從會員線上人數、管端線上人數傳值
if(isset($_POST['account_query'])) {
    $account_query = filter_var($_POST['account_query'], FILTER_SANITIZE_STRING);
}

if(isset($_POST['ip_query']) AND filter_var($_POST['ip_query'], FILTER_VALIDATE_IP)) {
    $ip_query = $_POST['ip_query'];
}

if(isset($_POST['fp_query'])) {
    $fingerprint_query = filter_var($_POST['fp_query'], FILTER_SANITIZE_STRING);
}

// var_dump(isset($_GET));die();
// var_dump($query_sql_array);

// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數  $tr['Home'] = '首页';
// 功能標題，放在標題列及meta $tr['Agent profit and loss calculation'] = '代理商損益計算';
$function_title 		= $tr['Login log query'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';

// 擴充 head 內的 css or js
// 加 datetime picker 及 datatables 的 js
$extend_head = <<<HTML
   <!-- jquery datetimepicker js+css -->
  <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
  <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
  <script src="in/jquery-ui.js"></script>
  <link rel="stylesheet"  href="in/jquery-ui.css" >
  <!-- Jquery blockUI js  -->
  <script src="./in/jquery.blockUI.js"></script>
  <!-- Datatables js+css  -->
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
HTML;


// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Various reports'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// 查詢欄（左）title及內容
$indextitle_content 	= '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>'.$tr['Search criteria'];
$indexbody_content		= '';
// 結果欄（右）title及內容 
$paneltitle_content 	= '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>'.$tr['Query results'];
$panelbody_content		= '';

// 查詢欄（左）內容 START-------------------------------------------

// datepicker

$min_date = ' 00:00:00'; 
$max_date = ' 23:59';
$minus_date = '-01 00:00';

$current = gmdate('Y-m-d',time()+ -4*3600); // 今天
// $current_date = gmdate('Y-m-d H:i:s',time() + -4*3600); // 結束時間帶上美東目前時間
$current_date = gmdate('Y-m-d',time() + -4*3600).' 23:59:59'; // 結束時間帶上美東目前時間

$default_min_date = gmdate('Y-m-d',strtotime('- 7 days')); // 7天

$thisweekday = date("Y-m-d", strtotime("$current_date - ".date('w',strtotime($current_date))."days"));
$yesterday = date("Y-m-d", strtotime("$current_date - 1 days"));

// 上週
$lastweekday_s = date("Y-m-d", strtotime("$current_date - ".intval(date('w',strtotime($current_date))+7)."days"));
$lastweekday_e = date("Y-m-d", strtotime("$thisweekday - 1 days"));

$thismonth = date("Y-m", strtotime($current_date));

// 上個月
$lastmonth = date('Y-m',strtotime(date('Y-m-1').'-1 month'));
$lastmonth_e = date('Y-m-d',strtotime(date('Y-m-1').'-1 day'));

// 查詢條件 - 條件設定
//帳號/IP安控統計 (加入條件 IP / 帳號 2選一 ; 指紋碼欄位不能選)
$indexbody_content .= <<<HTML
<div class="row mb-2">
	<div class="col-12"><label>條件設定</label></div>
	<div class="col-12">
		<div class="form-check form-check-inline">
			<input id="log_default" type="radio" class="form-check-input" name="conditionsetting" checked>
			<label class="form-check-label" for="log_default">預設</label>
		</div>
		<div class="form-check form-check-inline">
			<input id="recent_one" type="radio" class="form-check-input" name="conditionsetting" value='1'>
			<label class="form-check-label" for="recent_one">最近一次登入</label>
		</div>
		<div class="form-check form-check-inline">
			<input id="account_ip_statistics" type="radio" class="form-check-input" name="conditionsetting">
			<label class="form-check-label" for="account_ip_statistics">帳號/IP安控統計</label>
            <i class="fas fa-info-circle text-secondary ml-2" data-toggle="tooltip" data-placement="bottom" title="填寫帳號或IP，二擇一，禁用指紋碼"></i>
		</div>
	</div>
</div>
HTML;

// 查詢條件 - 帳號
if(!isset($_GET['m'])) {
$indexbody_content .= <<<HTML
    <div class="row mb-2">
		<div class="col-12">
			<div class="alert alert-success alert-link p-1 px-2 mb-2 d-none alert_text" role="alert">* 操作人員與IP二擇一填寫!</div>
		</div>
		<div class="col-12">
			<label class="d-flex">
				{$tr['operator']}				
			</label>
		</div>
		<div class="col-12">
				<input type="text" class="form-control" name="a" id="account_query" placeholder="{$tr['Account']}" value="{$account_query}">
		</div>
    </div>
HTML;
}
// 查詢條件 - IP $tr['Query IP'] = '查詢IP';
$indexbody_content .= <<<HTML
    <div class="row">
        <div class="col-12"><label>{$tr['Query IP']}</label></div>
        <div class="col-12">
            <input type="text" class="form-control" name="ip" id="ip_query" placeholder="ex:192.168.100.1"
            value="{$ip_query}">
        </div>
    </div>
    <br>
HTML;

// 查詢條件 - 時間 $tr['Starting time'] = '開始時間';
$indexbody_content .= <<<HTML
<div class="row">
		<div class="col-12 d-flex">
			<label>{$tr['date']}</label>
			<div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
				<button type="button" class="btn btn-secondary first">{$tr['This week']}</button>
				<div class="btn-group btn-group-sm" role="group">
					<button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
					<div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
							<a class="dropdown-item week" onclick="settimerange('{$thisweekday} 00:00:00',getnowtime(),'week');">{$tr['This week']}</a>
							<a class="dropdown-item month" onclick="settimerange('{$thismonth}-01 00:00:00',getnowtime(),'month');">{$tr['this month']}</a>
							<a class="dropdown-item today" onclick="settimerange('{$current} 00:00:00','{$current_date}','today');">{$tr['Today']}</a>
							<a class="dropdown-item yesterday" onclick="settimerange('{$yesterday} 00:00:00','{$yesterday} 23:59:00','yesterday');">{$tr['yesterday']}</a>
							<a class="dropdown-item lastweek" onclick="settimerange('{$lastweekday_s} 00:00:00','{$lastweekday_e} 23:59:00','lastweek');">{$tr['Last week']}</a>
							<a class="dropdown-item lastmonth" onclick="settimerange('{$lastmonth}-01 00:00:00','{$lastmonth_e} 23:59:00','lastmonth');">{$tr['last month']}</a>
					</div>
				</div>
				</div>
		</div>
		<div class="col-12 form-group rwd_doublerow_time">
				<div class="input-group">
						<div class="input-group">
							<div class="input-group-prepend">
								<span class="input-group-text">{$tr['Starting time']}</span>
							</div>
							<input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" placeholder="ex:2017-01-20" value="{$default_min_date}{$min_date}">
						</div>
						<div class="input-group">
								<div class="input-group-prepend">
									<span class="input-group-text">{$tr['End time']}</span>
								</div>
								<input type="text" class="form-control" name="edate" id="query_date_end_datepicker" placeholder="ex:2017-01-20" value="{$current_date}">
						</div>

				</div>
		</div>
	</div>
HTML;

// $indexbody_content .= <<<HTML
// 	<div class="row">
// 			<div class="col-12"><label>{$tr['date']}</label></div>
// 			<div class="col-12">
// 					<div class="input-group">
// 							<div class="input-group">
// 									<input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" placeholder="ex:2017-01-20" value="{$default_min_date}{$min_date}">
// 									<span class="input-group-addon" id="basic-addon1">~</span>
// 									<input type="text" class="form-control" name="edate" id="query_date_end_datepicker" placeholder="ex:2017-01-20" value="{$current_date}{$max_date}">
// 							</div>

// 							<div class="btn-group btn-group-sm mr-1 my-1" role="group" aria-label="Button group with nested dropdown">
// 									<button type="button" class="btn btn-secondary" onclick="settimerange('{$thisweekday} 00:00',getnowtime());">{$tr['This week']}</button>
// 									<button type="button" class="btn btn-secondary" onclick="settimerange('{$thismonth}-01 00:00',getnowtime());">{$tr['this month']}</button>
// 									<div class="btn-group btn-group-sm" role="group">
// 											<button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
// 											<div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
// 													<a class="dropdown-item" onclick="settimerange('{$current_date} 00:00','{$current_date} 23:59');">{$tr['Today']}</a>
// 													<a class="dropdown-item" onclick="settimerange('{$yesterday} 00:00','{$yesterday} 23:59');">{$tr['yesterday']}</a>
// 													<a class="dropdown-item" onclick="settimerange('{$lastweekday_s} 00:00','{$lastweekday_e} 23:59');">{$tr['Last week']}</a>
// 													<a class="dropdown-item" onclick="settimerange('{$lastmonth}-01 00:00','{$lastmonth_e} 23:59');">{$tr['last month']}</a>
// 											</div>
// 											</div>
// 							</div>
// 					</div>
// 			</div>
// 	</div>
// <br>
// HTML;


// 查詢條件 - fingerprint $tr['FingerPrint'] = '指紋';
$indexbody_content .= <<<HTML
    <div class="row">
        <div class="col-12">
            <label>{$tr['FingerPrint']}</label>
        </div>
        <div class="col-12">
            <input type="text" class="form-control" name="fp" id="fingerprint_query" placeholder="fingerprint"
             value="{$fingerprint_query}">
        </div>
    </div>
 <br>
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



// 結果欄（右）內容 START--------------------------------------------------

    //統計表格
    $account_statistics = '
    <div class="row mb-3">
        <div class="col-12">
            <div class="border d-flex">
            <div class="sum_th border-right">統計帳號</div>
            <div class="sum_th border-right">登入IP數</div>
            <div class="sum_th">時間</div>
            </div>
            <div class="border border-top-0 d-flex">
            <div class="sum_td border-right statistics_unit"></div>
            <div class="sum_td border-right statistics_total"></div>
            <div class="sum_td statistics_date"></div>
            </div>
        </div>
    </div>
		';
		
	//統計表格
    $ip_statistics = '
    <div class="row mb-3">
        <div class="col-12">
            <div class="border d-flex">
            <div class="sum_th border-right">統計ip</div>
            <div class="sum_th border-right">登入帳號</div>
            <div class="sum_th">時間</div>
            </div>
            <div class="border border-top-0 d-flex">
            <div class="sum_td border-right statistics_unit"></div>
            <div class="sum_td border-right statistics_total"></div>
            <div class="sum_td statistics_date"></div>
            </div>
        </div>
    </div>
    ';

    $table_colname_html = '
    <tr>
        <th>'.$tr['ID'].'</th>
        <th>'.$tr['operator'].'</th>
        <th>'.$tr['event time'].'(EDT)</th>
        <th>'.$tr['source'].'IP</th>
        <th>'.$tr['ip location'].'</th>
        <th>'.$tr['detail'].'</th>
    </tr>
    ';

	$table_account_ip_html = '
    <tr>
        <th>'.$tr['ID'].'</th>
        <th>'.$tr['Account'].'</th>
        <th>IP</th>
        <th>次數</th>
    </tr>
    ';

	#accountip_list 第二個datatable
	//statistics_account_show 統計帳號上方欄位
	//statistics_ip_show 統計IP上方欄位
    $table_content = '
	    <div id="inquiry_result_area">
			<div class="row">
				<div class="col-12 statistics_account_show d-none">'.$account_statistics.'</div>
				<div class="col-12 statistics_ip_show d-none">'.$ip_statistics.'</div>
			</div>		
			<div class="showlist_statistics">
				<table id="show_list"  class="display" cellspacing="0" width="100%" >
				<thead>
				'.$table_colname_html.'
				</thead>
				<tbody id="show_content">
				</tbody>
				</table>
			</div>
			<div class="accountip_statistics d-none">
				<table id="accountip_list" class="display" cellspacing="0" width="100%" >
				<thead>
				'.$table_account_ip_html.'
				</thead>
				<tbody>
				</tbody>
				</table>
			</div>
		</div>
    ';

    // 表格放入右邊結果
    $panelbody_content	.= $table_content;
    // 將查詢結果，放在csv按紐，並放在查詢結果的右邊
    $paneltitle_content .= '<div id="csv"  style="float:right;margin-bottom:auto"></div>';

// 結果欄（右）內容 END-----------------------------------------------



$form_account_js=(isset($_GET['m']))?"'".$account_query."'":"$('#account_query').val()";

// 加到 body 底部的js
$extend_js=<<<HTML
    <script type="text/javascript" language="javascript" class="init">
        //查詢條件
        $(function(){
			//工具提示
			$('[data-toggle="tooltip"]').tooltip();
					
            //預設時,解鎖指紋欄
			$("#log_default").change(function() {
			    $('#fingerprint_query').prop('disabled',false);
			});

            //最近一次登入時,解鎖指紋欄
            $("#recent_one").change(function() {
			    $('#fingerprint_query').prop('disabled',false);
			});

            //帳號/IP安控統計 不執行 指纹
            $("#account_ip_statistics").change(function() {
			    $('.alert_text').removeClass('d-none');
			    $('#fingerprint_query').val('');
			    $('#fingerprint_query').prop('disabled',true);
			});
        });
        
        function paginateScroll() { // auto scroll to top of page
            $("html, body").animate({
                scrollTop: $(".dataTables_wrapper").offset().top
            }, 100);
            console.log("pagination button clicked");
            $(".paginate_button").unbind("click", paginateScroll);
            $(".paginate_button").bind("click", paginateScroll);
        };
      
        function get_parameter(){
            var account_query     = {$form_account_js};
            var datepicker_start  = $("#query_date_start_datepicker").val();
            var datepicker_end    = $("#query_date_end_datepicker").val();
            var ip_query          = $("#ip_query").val();
            var fingerprint_query = $("#fingerprint_query").val();
            if ($("#recent_one").prop('checked')){
                var recent_one='1';
            } else {
                var recent_one='0';
            }

            //check 帳號/IP安控統計是否有被選取
            if ($("#account_ip_statistics").prop('checked') && account_query != ''){
                var account_ip_statistics='1';
            } else if ($("#account_ip_statistics").prop('checked') && ip_query != '') {
                var account_ip_statistics='2';
            } else {
                var account_ip_statistics='0';
            }

            var url="?get=query_log&a="+account_query+"&sdate="+datepicker_start+"&edate="+datepicker_end+"&ip="+ip_query+"&fp="+fingerprint_query+"&recent_one="+recent_one+"&account_ip_statistics="+account_ip_statistics;
            // console.log(url);
            return url;
        }


        $(document).ready(function() {
            var send_url = get_parameter();
            var table = $("#show_list").DataTable( {
                "bProcessing": true,
                "bServerSide": true,
                "bRetrieve": true,
                "searching": false,
                "aaSorting": [[ 2, "desc" ]],
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
                    "url":"member_log_action.php"+send_url,
                    // "url":"member_log_action.php?get=query_log",
                    "dataSrc": function(json) {
                        if(json.data.list.length > 0) {
                            var link=`<a href="`+json.data.download_url+`" target="_blank" class="btn btn-success btn-sm" role="button" aria-pressed="true">{$tr['Export Excel']}</a>`;
                            $("#csv").html(link);
                        }else{
                            $("#csv").empty();
                        }
                        return json.data.list;
                    }
                },
                "columns": [
                    { "data": "id"},
                    // { "data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                    //     $(nTd).html("<a href=\"javascript:query_str(\'account\',\'"+oData.account+"\')\" data-role=\"button\" >"+oData.account+"</a>");}},
                    { "data": "account"},
                    { "data": "occurtime"},
                    // { "data": "logintime_html"},
                    // { "data": "service"},
                    { "data": "ip", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                        $(nTd).html(`<a href="#" data-role="button">`+oData.ip+`</a>`);}},
                    {"data":"ip_location"},
                    // { "data": "fp", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                    //     $(nTd).html(`<a href="javascript:query_str('fingerprint','`+oData.fp+`')" data-role="button" >`+oData.fp+`</a>`);}},
                    { "data": "detail"}
                ]
            });

			//第二個table
			var accountip = $("#accountip_list").DataTable( {
				"dom": '<tflip>',
                "bProcessing": true,
                "bServerSide": true,
                "bRetrieve": true,
                "searching": false,
                "aaSorting": [[ 2, "desc" ]],
                "oLanguage": {
                    "sSearch": "{$tr['search'] }",//"搜索:",
                    "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
                    "sLengthMenu": "{$tr['display']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
                    "sZeroRecords": "{$tr['no data']}",//"没有匹配结果",
                    "sInfo": "{$tr['Display']} _START_ {$tr['to']} _END_ {$tr['result']},{$tr['total']} _TOTAL_ {$tr['item']}",//"显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
                    "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
                    "sInfoFiltered": "({$tr['from']} _MAX_ {$tr['filtering in data']})"//"(由 _MAX_ 项结果过滤)"
                },
                "ajax":{
                    "url":"member_log_action.php"+send_url,
                    "dataSrc": function(json) {
                        $(".statistics_total").html(json.iTotalDisplayRecords);
                        if(json.data.list.length > 0) {
                            var link=`<a href="`+json.data.download_url+`" target="_blank" class="btn btn-success btn-sm" role="button" aria-pressed="true">{$tr['Export Excel']}</a>`;
                            $("#csv").html(link);
                        }else{
                            $("#csv").empty();
                        }
                        return json.data.list;
                    }
                },
                "columns": [
                    { "data": "id"},
                    { "data": "account"},
                    { "data": "ip", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                        $(nTd).html(`<a href="#" data-role="button">`+oData.ip+`</a>`);}},
					{ "data": "time","fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                        $(nTd).html(`<a href="javascript:account_ip_search('`+oData.unprocessed_account+`','`+oData.ip+`')" data-role="button" >`+oData.time+`</a>`);}
                    }
                ]
            });

        $("#submit_to_inquiry").click(function(){
            //條件設定 選擇帳號與IP
            if ($("#account_ip_statistics").prop('checked')){
			    var account_query = $.trim($('#account_query').val());
				var ip_query =  $.trim($('#ip_query').val());
                var sdate = $.trim($('#query_date_start_datepicker').val());
				var edate =  $.trim($('#query_date_end_datepicker').val());
				//檢查是否都有輸入
				if ( account_query != '' && ip_query != '' ) {
					$('.alert_text').addClass('alert-danger');								
					$('#account_query').addClass('alert-danger');
					$('#ip_query').addClass('alert-danger');
				}else if(account_query == '' && ip_query == '' ){
                    $('.alert_text').addClass('alert-danger');								
					$('#account_query').addClass('alert-danger');
					$('#ip_query').addClass('alert-danger');
                }else {
					//有選擇一個輸入
					$('.alert_text').removeClass('alert-danger');
					$('#account_query').removeClass('alert-danger');
					$('#ip_query').removeClass('alert-danger');

					//呼叫上方統計 帳號欄位不是空
					if ( account_query != '' ) {
						$('.statistics_account_show').removeClass('d-none');
						$('.statistics_ip_show').addClass('d-none');
                        $('.statistics_unit').html(account_query);
                        $('.statistics_date').html(sdate+' ~ '+edate);
					}else if ( ip_query != '' ){
						//ip欄位不是空
						$('.statistics_account_show').addClass('d-none');
						$('.statistics_ip_show').removeClass('d-none');
                        $('.statistics_unit').html(ip_query);
                        $('.statistics_date').html(sdate+' ~ '+edate);
					}

					//呼叫第二個表格
					$('.accountip_statistics').removeClass('d-none');
					//關閉第一個表格
					$('.showlist_statistics').addClass('d-none');

					$.blockUI({ message: "<img src=\"ui/loading_text.gif\" />" });
                    var send_url = get_parameter();

                    $("#accountip_list").DataTable()
                        .ajax.url("member_log_action.php"+send_url)
                        .load();
                    paginateScroll();
                    $.unblockUI();
			    }			
            }else {
                $('.statistics_account_show').addClass('d-none');
				$('.statistics_ip_show').addClass('d-none');
                //關閉第二個表格
				$('.accountip_statistics').addClass('d-none');
				//呼叫第一個表格
			    $('.showlist_statistics').removeClass('d-none');

                $.blockUI({ message: "<img src=\"ui/loading_text.gif\" />" });
                var send_url = get_parameter();
                // var account_query     = $("#account_query").val();
                // var datepicker_start  = $("#query_date_start_datepicker").val();
                // var datepicker_end    = $("#query_date_end_datepicker").val();
                // var ip_query          = $("#ip_query").val();
                // var fingerprint_query = $("#fingerprint_query").val();
                // if ($("#recent_one").prop('checked')){
                //     var recent_one='1';
                // } else {
                //     var recent_one='0';
                // }
                // var send_url="?get=query_result&a="+account_query+"&sdate="+datepicker_start+"&edate="+datepicker_end+"&ip="+ip_query+"&fp="+fingerprint_query+"&recent_one="+recent_one;

                $("#show_list").DataTable()
                    .ajax.url("member_log_action.php"+send_url)
                    .load();
                paginateScroll();
                $.unblockUI();
            }
        });


            // // datetimepicker
            $("#query_date_start_datepicker").datetimepicker({
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                maxDate: '{$current_date}',
                timepicker: false,
                format: "Y-m-d H:i:s",
                step:1,
                // defaultTime: "00:00:00"
            });
            $("#query_date_end_datepicker").datetimepicker({
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                maxDate: '{$current_date}',
                timepicker: false,
                format: "Y-m-d H:i:s",
                step:1,
                // defaultTime:"23:59"
            });
        })


        $(document).keydown(function(e) {
        switch(e.which) {
            case 13: // enter key
                $("#submit_to_inquiry").trigger("click");
            break;
        }
        });

        function getnowtime(){
            var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+' 23:59:59';

            return NowDate;
        }
        // 本日、昨日、本周、上周、上個月button
        function settimerange(sdate,edate,text){
            $("#query_date_start_datepicker").val(sdate);
            $("#query_date_end_datepicker").val(edate);
						
						//更換顯示到選單外 20200525新增
						var currentonclick = $('.'+text+'').attr('onclick');
						var currenttext = $('.'+text+'').text();

						//first change
						$('.application .first').removeClass('week month');
						$('.application .first').attr('onclick',currentonclick);
						$('.application .first').text(currenttext); 
        }

        //點擊次數->帶入account,ip搜索 並將radio設定為預設
        function account_ip_search(account,ip){
            $("input[name=conditionsetting]")[0].checked=true;
            $("#account_query").val(account);
            $("#ip_query").val(ip);
            $("#submit_to_inquiry").trigger("click");
        }
  </script>
HTML;

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 		= $tr['host_author'];
$tmpl['html_meta_title'] 			= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']						= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']					= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']						= $extend_js;
// 兩欄分割--左邊
$tmpl['indextitle_content']				= $indextitle_content;
$tmpl['indexbody_content'] 				= $indexbody_content;
// 兩欄分割--右邊
$tmpl['paneltitle_content']				= $paneltitle_content;
$tmpl['panelbody_content']				= $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
if(isset($_GET['m'])) {
    $page = 'member_log';
    include "template/member_overview_history.tmpl.php";
}else{
    include "template/s2col.tmpl.php";
}