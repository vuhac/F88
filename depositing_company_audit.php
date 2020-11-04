<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 公司入款審核
// File Name:	depositing_company_audit.php
// Author:		Barkley
// Related:		對應前台 deposit_company.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once __DIR__ . '/lib_message.php';

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
// 功能標題，放在標題列及meta
// $tr['Depositing audit company'] = '公司入款審核';
$function_title 		= $tr['Depositing audit company'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['homepage']                 = '首頁';
// $tr['Account Management'] = '帳務管理';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['homepage'].'</a></li>
  <li><a href="#">'.$tr['Account Management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';

// 查詢欄（左）title及內容
$indextitle_content 	= '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>'.$tr['Search criteria'];
$indexbody_content		= '';
// 結果欄（右）title及內容
$paneltitle_content 	= '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>'.$tr['Query results'].'<p id="depositing_company_audit_mq" class="mb-0 ml-auto float-right" style="color: #dc3545; display: none;"></p>';
$panelbody_content		= '';

// ----------------------------------------------------------------------------

// 查詢欄（左）內容 START-------------------------------------------

// datepicker
$search_time = time_convert();
// var_dump($search_time);die();

$current_date = gmdate('Y-m-d',time() + -4*3600).' 23:59'; // 結束時間帶上美東目前時間
$current_time = gmdate('Y-m-d H:i',time() + -4*3600); // 開始時間帶上美東目前時間

// 3個月前
$threemonth = date("Y-m-d H:i", strtotime("$current_time - 91 days")); //三個月區間

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
                    <a class="dropdown-item week" onclick="settimerange('{$search_time['thisweekday']} 00:00', getnowtime(),'week')">{$tr['This week']}</a>
                    <a class="dropdown-item month" onclick="settimerange('{$search_time['thismonth']}-01 00:00',getnowtime(),'month')">{$tr['this month']}</a>
                    <a class="dropdown-item today" onclick="settimerange('{$search_time['current']} 00:00', getnowtime(), 'today')">{$tr['Today']}</a>
                    <a class="dropdown-item yesterday" onclick="settimerange('{$search_time['yesterday']} 00:00', '{$search_time['yesterday']} 23:59','yesterday' );">{$tr['yesterday']}</a>
                    <a class="dropdown-item lastmonth" onclick="settimerange('{$search_time['lastmonth']}-01 00:00','{$search_time['lastmonth_e']} 23:59','lastmonth');">{$tr['last month']}</a>
                  </div>
                </div>
              </div>
        </div>
        <div class="col-12 form-group rwd_doublerow_time">
            <div class="input-group">
              <div class=" input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text">{$tr['start']}</span>
                </div>
                <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker"
                    placeholder="ex:2017-01-20" value="{$search_time['default_min_date']}{$search_time['min']}">
              </div>

              <div class=" input-group">
              <div class="input-group-prepend">
                <span class="input-group-text">{$tr['end']}</span>
              </div>
                <input type="text" class="form-control" name="edate" id="query_date_end_datepicker"
                    placeholder="ex:2017-01-20" value="{$search_time['current']}{$search_time['max']}">
              </div>
            </div>
        </div>
    </div>
HTML;

// 查詢條件 - 申請時間
// $indexbody_content .= <<<HTML
//     <div class="row">
//         <div class="col-12"><label>{$tr['application time']}</label></div>
//         <div class="col-12 form-group">
//             <div class="input-group">
//               <div class=" input-group">
//                 <div class="input-group-prepend">
//                   <span class="input-group-text">起始</span>
//                 </div>
//                 <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker"
//                     placeholder="ex:2017-01-20" value="{$default_min_date}{$min_date}">
//               </div>

//               <div class=" input-group">
//               <div class="input-group-prepend">
//                 <span class="input-group-text">结束</span>
//               </div>
//                 <input type="text" class="form-control" name="edate" id="query_date_end_datepicker"
//                     placeholder="ex:2017-01-20" value="{$current_date}{$max_date}">
//               </div>

//               <div class="btn-group btn-group-sm mr-1 my-1" role="group" aria-label="Button group with nested dropdown">
//                 <button type="button" class="btn btn-secondary" onclick="settimerange('{$thisweekday} 00:00', getnowtime());">{$tr['This week']}</button>
//                 <button type="button" class="btn btn-secondary" onclick="settimerange('{$thismonth}-1 00:00',getnowtime());">{$tr['this month']}</button>

//                 <div class="btn-group btn-group-sm" role="group">
//                   <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
//                   </button>
//                   <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
//                     <a class="dropdown-item" onclick="settimerange('{$current_date} 00:00', getnowtime())">{$tr['Today']}</a>
//                     <a class="dropdown-item" onclick="settimerange('{$yesterday} 00:00', '{$yesterday} 23:59' );">{$tr['yesterday']}</a>
//                     <a class="dropdown-item" onclick="settimerange('{$lastmonth}-01 00:00','{$lastmonth_e} 23:59');">{$tr['last month']}</a>
//                   </div>
//                 </div>
//               </div>
//             </div>
//         </div>
//     </div>
// HTML;

// 查詢條件 - 申請時間
// $indexbody_content .= <<<HTML
//     <div class="row">
//         <div class="col-12"><label>{$tr['application time']}</label></div>
//         <div class="col-12">
//             <div class="input-group">
//               <div class=" input-group">
//                 <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker"
//                     placeholder="ex:2017-01-20" value="{$default_min_date}{$min_date}">
//                 <span class="input-group-addon" id="basic-addon1">~</span>
//                 <input type="text" class="form-control" name="edate" id="query_date_end_datepicker"
//                     placeholder="ex:2017-01-20" value="{$current_date}{$max_date}">
//               </div>

//               <div class="btn-group btn-group-sm mr-1 my-1" role="group" aria-label="Button group with nested dropdown">
//                 <button type="button" class="btn btn-secondary" onclick="settimerange('{$thisweekday} 00:00', getnowtime());">{$tr['This week']}</button>
//                 <button type="button" class="btn btn-secondary" onclick="settimerange('{$thismonth}-1 00:00',getnowtime());">{$tr['this month']}</button>

//                 <div class="btn-group btn-group-sm" role="group">
//                   <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
//                   </button>
//                   <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
//                     <a class="dropdown-item" onclick="settimerange('{$current_date} 00:00', getnowtime())">{$tr['Today']}</a>
//                     <a class="dropdown-item" onclick="settimerange('{$yesterday} 00:00', '{$yesterday} 23:59' );">{$tr['yesterday']}</a>
//                     <a class="dropdown-item" onclick="settimerange('{$lastmonth}-01 00:00','{$lastmonth_e} 23:59');">{$tr['last month']}</a>
//                   </div>
//                 </div>
//               </div>
//             </div>
//         </div>
//     </div>
//     <br>
// HTML;

// 金額
$indexbody_content .= <<<HTML
  <div class="row">
    <div class="col-12"><label>{$tr['amount']}</label></div>
    <div class="col-12 form-group">
      <div class="input-group">
        <input type="number" class="form-control" step=".01" placeholder='{$tr['Lower limit']}' id="amount_lower" name="amount_lower">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="number" class="form-control" step=".01" placeholder='{$tr['Upper limit']}' id="amount_upper" name="amount_upper">
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


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  // var_dump($_SESSION);

/*
  // 使用者所在的時區，sql 依據所在時區顯示 time
  	// -------------------------------------
  	if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
  		$tz = $_SESSION['agent']->timezone;
  	}else{
  		$tz = '+08';
  	}
    // var_dump($tz);
    // 轉換時區所要用的 sql timezone 參數
    $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
    $tzone = runSQLALL($tzsql);
    // var_dump($tzone);

    if($tzone[0]==1){
      $tzonename = $tzone[1]->name;
    }else{
      $tzonename = 'posix/Etc/GMT-8';
    }
    // var_dump($tzonename);
*/

  // 列出系統中所有的審查資料
	// -------------------------------------
/*
  $list_sql = "
    SELECT *, to_char((transfertime AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as transfertime_tz
    FROM root_deposit_review
    ORDER BY transfertime_tz DESC;
  ";
*/


  // 表格欄位名稱
  $table_colname_html =<<<HTML
  <tr>
    <th>{$tr['Affiliated agent']}</th>
    <th>{$tr['Account']}</th>
    <th class="depositingwidth">{$tr['Transaction order number']}</th>
    <th>{$tr['amount']}</th>
    <th>{$tr['application time']}</th>
    <th>{$tr['Deposit time']}</th>
    <th>{$tr['Deposit method']}</th>
    <th>{$tr['Import account']}</th>
    <th>{$tr['account reconciliation information']}</th>
    <th>{$tr['agent review review']}</th>
    <th>{$tr['detail']}</th>
  </tr>
HTML;


  // enable sort table
  // search data
	// $sorttablecss = ' class="table table-striped" ';

  // $tr['Company, bank check-in manua review'] = '* 公司、銀行入款人工審核<br>* 列出 90 天內的入款通知, 或是單號狀態還沒審查的。';
  $show_tips_html = '';
  // <div class="alert alert-success">'.$tr['Company, bank check-in manua review'].'</div>

  // 列出資料, 主表格架構
  $show_list_html = $show_tips_html;
  $show_list_html = $show_list_html.'
  <table id="show_list"  class="display" cellspacing="0" width="100%" >
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
  // 表格放入右邊結果
  $panelbody_content = $show_list_html;

  // 參考使用 datatables 顯示
	// https://datatables.net/examples/styling/bootstrap.html
  $extend_head = $extend_head.'
  <link rel="stylesheet" type="text/css" href="ui/style_seting.css">
  <!-- jquery datetimepicker js+css -->
  <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
  <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
	';


	// DATA tables jquery plugging -- 要放在 head 內 不可以放 body
	// $extend_head = $extend_head.'
	// <script type="text/javascript" language="javascript" class="init">
	// 	$(document).ready(function() {
	// 		$("#show_list").DataTable( {
  //         "paging":   true,
	// 				"ordering": true,
	// 				"info":     true,
  //         "order": [[ 4, "desc" ]]
  //     });
  //     $(".load_datatble_animate").hide();
	// 	})
	// </script>
  // ';


  $extend_js = <<<HTML
  <script type="text/javascript" language="javascript" class="init">
  $(document).ready(function() {
    if (location.search == '?unaudit'){
      url = "depositing_company_audit_action.php?a=get_result"+"&account="+"&agent="+"&sdate=$threemonth&edate=$current_date&amount_lower="+"&amount_upper="+"&ip="+"&transaction_id="+"&status_qy[]=2"
    } else{
      url ="depositing_company_audit_action.php?a=get_init"
    }
    $("#show_list").DataTable({
      "bProcessing": true,
      "bServerSide": true,
      "bRetrieve": true,
      "searching": false,
      "aaSorting": [[ 5, "desc" ]],
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
        {"data":"agent","orderable": false},
        {"data":"account"},
        {"data":"transaction_id"},
        {"data":"amount"},
        {"data":"transfertime_tz"},
        {"data":"changetime_tz"},
        {"data":"deposit_type","orderable": false},
        {"data":"bank_info","orderable": false},
        {"data":"reconciliation_notes"},
        {"data":"status"},
        {"data":"detail","orderable": false}
      ]
    })
  })

  $(document).on('click', '.agreen_ok', function() {
    var id = $(this).val();
    $('#agreen_ok'+id).attr('disabled', 'disabled');
    $('#agreen_cancel'+id).attr('disabled', 'disabled');

    var r = confirm('{$tr['Whether to confirm the audit consent']}?');
    if(r == true){
      $.post('depositing_company_audit_action.php?a=depositing_company_audit_submit',
        {
          depositing_id: id
        },
        function(result){
          $('#preview_result').html(result);
          $('#show_list').DataTable().ajax.reload(null, false);
        }
      )
    }else{
      $('#agreen_ok'+id).removeAttr('disabled');
      $('#agreen_cancel'+id).removeAttr('disabled');
    }
  });

  $(document).on('click', '.agreen_cancel', function() {
    var id = $(this).val();
    $('#agreen_ok'+id).attr('disabled', 'disabled');
    $('#agreen_cancel'+id).attr('disabled', 'disabled');

    var r = confirm('{$tr['Whether to cancel the audit consent']}?');
    if(r == true){
      $.post('depositing_company_audit_action.php?a=depositing_company_audit_cancel',
        {
          depositing_id: id
        },
        function(result){
          $('#preview_result').html(result);
          $('#show_list').DataTable().ajax.reload(null, false);
        }
      )
    }else{
      $('#agreen_ok'+id).removeAttr('disabled');
      $('#agreen_cancel'+id).removeAttr('disabled');
    }
  });

  function showHideText(id) {
		if(document.getElementById(id).className == 'div_hide') {
			document.getElementById(id).className='div_show';
		} else {
			document.getElementById(id).className='div_hide';
		}
	}
  $(function () {
    $('[data-toggle="tooltip"]').tooltip()
  })

  // ------------------------------------------------------------
  // 搜尋
  $("#submit_to_inquiry").click(function(){
    var transaction_id = $("#transation_query").val();
    var account = $("#account_query").val();
    var agent = $("#agent_query").val();
    var query_date_start_datepicker = $("#query_date_start_datepicker").val();
    var query_date_end_datepicker = $("#query_date_end_datepicker").val();
    var amount_lower = $("#amount_lower").val();
    var amount_upper = $("#amount_upper").val();
    // var ip_query = $("#ip_query").val();

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

    // var url_data ="&account="+account+"&agent="+agent+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+"&amount_lower="+amount_lower+"&amount_upper="+amount_upper+"&ip="+ip_query+"&transaction_id="+transaction_id+status_query;
    var url_data ="&account="+account+"&agent="+agent+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+"&amount_lower="+amount_lower+"&amount_upper="+amount_upper+"&transaction_id="+transaction_id+status_query;

    // console.log(url_data);
    // $.get("depositing_company_audit_action.php?a=get_result"+url_data,function(result){
    //   if(!result.logger){
    //     $("#show_list tbody").html(result);
    //   }
    //   $("#show_list tbody").html(result);
    // },'json');

    $("#show_list").DataTable()
      .ajax.url("depositing_company_audit_action.php?a=get_result"+url_data)
      .load();

  })

  function getnowtime(){
    // var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD HH:mm');
    var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+ ' 23:59';


    return NowDate;
  }
  // 本日、昨日、本周、上周、上個月button
  function settimerange(sdate,edate,text){
    $("#query_date_start_datepicker").val(sdate);
    $("#query_date_end_datepicker").val(edate);

    //更換顯示到選單外 20200525新增
    // console.log(sdate);
    // console.log(edate);
    var currentonclick = $('.'+text+'').attr('onclick');
    var currenttext = $('.'+text+'').text();

    //first change
    $('.application .first').removeClass('week month');
    $('.application .first').attr('onclick',currentonclick);
    $('.application .first').text(currenttext);

  }

  // datetimepicker
  $("#query_date_start_datepicker").datetimepicker({
      showButtonPanel: true,
      changeMonth: true,
      changeYear: true,
      // minDate: '{$search_time['min']}',
      maxDate: '{$search_time['current']}',
      timepicker: true,
      // defaultTime: '00:00',
      format: "Y-m-d H:i",
      step:1
  });
  $("#query_date_end_datepicker").datetimepicker({
      showButtonPanel: true,
      changeMonth: true,
      changeYear: true,
      // minDate: '{$search_time['min']}',
      maxDate: '{$search_time['current']}',
      timepicker: true,
      defaultTime: '23:59',
      format: "Y-m-d H:i",
      step:1
  });

  </script>
HTML;


$extend_head .= <<<HTML
  <style>
  .div_show
  {
    display:block;
  }
  .div_hide
  {
    display:none;
  }

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
		// $('.notes').editable({
		// 	url: 'depositing_company_audit_action.php?a=depositing_company_audit_submit',
		// 	rows: 6,
		// 	success: function(resultdata){
		// 		$( '#preview_result' ).html(resultdata);
		// 	}
		// });
		// for status
		// $('.status').editable({
		// 	source: [
    //       {value: 0, text: '".$review_agent_status[0]."'},
    //       {value: 1, text: '".$review_agent_status[1]."'}
		// 		 ],
		// 	url: 'depositing_company_audit_action.php?a=depositing_company_audit_submit',
		// 	success: function(resultdata){
		// 		$( '#preview_result' ).html(resultdata);
		// 	}
		// });

      // $('.agree').click(function(){
      //   var agree = $(this).attr('data-pk');
      //   alert('id:' + agree);
      //   $.post(\"depositing_company_audit_action.php?a=depositing_company_audit_submit\",
      //   {
      //     agree_submit: agree
      //   },
      //     function(resultdata){
      //       $('#preview_result' ).html(resultdata);
      //     }
      //   );
      // });
      //
      // $('.cancel').click(function(){
      //   var cancel = $(\".cancel\").val();
      //   alert(cancel);
      // });
	});
	</script>
	";
*/
  $load_animate="<div class='load_datatble_animate'><img src='./ui/loading.gif'></div>";

}else{
	// 沒有登入的顯示提示俊息
  // $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
	$show_transaction_list_html  = $tr['only management and login mamber'];

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12">
	  '.$show_transaction_list_html.'
	  </div>
	</div>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
}
$indexbody_content = $indexbody_content.'
<br>
<div class="row">
  <div id="preview_result"></div>
</div>
';

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------
$message_reciever_url = get_message_reciever_url('backstage', '');

$message_reciever_link = <<<HTML
<a
  class="btn btn-default btn-sm float-right"
  style="margin-top: -4px;"
  href="$message_reciever_url"
  onclick="window.open(this.href, 'targetWindow', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=500,height=' + screen.height);return false;"
>{$tr['Receive instant messages']}</a>
HTML;

$tips_alert = <<<HTML
<span class="glyphicon glyphicon-info-sign float-right mx-2" data-toggle="tooltip" data-placement="top" title="{$tr['Company, bank check-in manua review']}"></span>
HTML;

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
