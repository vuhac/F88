<?php
// ----------------------------------------------------------------------------
// Features:  後台--會員娛樂城轉換記錄
// File Name: member_casinotransferlog.php
// Author:    snowiant@gmail.com
// Related:
//    member_casinotransferlog_action.php member_casinotransferlog_lib.php
//    DB table: root_member_casino_transferrecords
//    member_log：有收到 _GET 時會將 _GET 取得的值進行驗證，並檢查是否為可查詢對象，如果是
//        就直接丟入 $query_sql_array 中再引用 member_log_lib.php 中的涵式
//        show_member_logininfo($query_sql_array) 並將返回的資料放入 table 中給
//        datatable 處理。如果沒收到 _GET 值就顯示無資料的查詢介面，並在使用者按下"查詢"時
//        以 ajax 丟給 member_casinotransferlog_action.php 來查詢並將返回資料顯示出來。
// Log:
// 2020.03.10 #3540 【後台】娛樂城、遊戲多語系欄位實作 - 修改娛樂城語系顯示名稱
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 娛樂城函式庫
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";
// 搜尋開始時間、結束時間函式
require_once dirname(__FILE__) ."/deposit_withdrawal_company_audit_lib.php";
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
$casinoLib = new casino_switch_process_lib();
// --------------------------------------------------------------------------
// 取得 get 傳來的變數
// --------------------------------------------------------------------------
$query_sql = '';
$query_chk = 0;
if(isset($_GET['a'])) {
  $query_sql = '&a='.filter_var($_GET['a'], FILTER_SANITIZE_STRING);
  $account_query = filter_var($_GET['a'], FILTER_SANITIZE_STRING);

}elseif(isset($_GET['check_casino'])){
    // checkbox
  $query_sql = '&casino_name='.filter_var($_GET['check_casino'],FILTER_SANITIZE_STRING);
  $casino_query = filter_var($_GET['check_casino'],FILTER_SANITIZE_STRING);
  $query_chk = 1;

}elseif(isset($_GET['sdate']) AND $_GET['sdate'] != NULL ) {
    // 判斷格式資料是否正確
  if(validateDate($_GET['sdate'], 'Y-m-d H:i')) {
    $query_sql = $query_sql.'&sdate='.$_GET['sdate'];
    $sdate_query = $_GET['sdate'];
    $query_chk = 1;
  }
}elseif(isset($_GET['edate']) AND $_GET['edate'] != NULL ) {
    // 判斷格式資料是否正確
    if(validateDate($_GET['edate'], 'Y-m-d H:i')) {
      $query_sql = $query_sql.'&edate='.$_GET['edate'];
      $edate_query = $_GET['edate'];
      $query_chk = 1;
    }
}
// 預設區間為一個月
if(!isset($sdate_query) AND !isset($edate_query)){
    // 轉換為美東的時間 date
  $current_datepicker = gmdate('Y-m-d H:i',time() + -4*3600);
    // die(var_dump($current_datepicker));
  $default_startdate = gmdate('Y-m-d H:i',strtotime('-1 month',time()) + -4*3600);
    //var_dump($default_startdate);
  $sdate_query = $default_startdate;
  $edate_query = $current_datepicker;
  $query_sql = $query_sql.'&sdate='.$sdate_query;
  $query_sql = $query_sql.'&edate='.$edate_query;
}elseif(!isset($sdate_query)){
  $default_startdate = gmdate('Y-m-d H:i',strtotime('-1 month',$edate_query));
    //var_dump($default_startdate);
  $sdate_query = $default_startdate;
  $query_sql = $query_sql.'&sdate='.$sdate_query;
}elseif(!isset($edate_query)){
  $current_datepicker = gmdate('Y-m-d H:i',time() + -4*3600);
  $edate_query = $current_datepicker;
  $query_sql = $query_sql.'&edate='.$edate_query;
}

if($query_chk == 0){
  $query_sql = '';
}

//var_dump($query_sql_array);

// 初始化變數

$datatables_pagelength = $page_config['datatables_pagelength'];

// 功能標題，放在標題列及meta
// $tr['Casino transfer record inquiry'] = '娛樂城轉帳紀錄查詢';
$function_title     = $tr['Casino transfer record inquiry'];
$page_title         = '<h2><strong>'.$function_title.'</strong></h2><hr>';

// 擴充 head 內的 css or js
// 加 datetime picker 及 datatables 的 js
//   { "data": "logintime_html", "searchable": false, "orderable": false },
$extend_head        =<<<HTML
                          <!-- Jquery UI js+css  -->
                        <script src="in/jquery-ui.js"></script>
                        <link rel="stylesheet"  href="in/jquery-ui.css" >
                        <!-- Datetimepicker -->
                        <link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>
                        <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
                        <!-- Jquery blockUI js  -->
                        <script src="./in/jquery.blockUI.js"></script>
                        <!-- Datatables js+css  -->
                        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
                        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
                        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
                        <script type="text/javascript" language="javascript" class="init">
                        $(document).ready(function() {
                          $("#show_list").DataTable( {
                              //"bAutoWidth": false,
                              "orderClasses": false,
                              "bProcessing": true,
                              "bServerSide": true,
                              "bRetrieve": true,
                              "searching": false,
                              "aaSorting": [[ 0, "desc" ]],
                              "oLanguage": {
                                "sSearch": "{$tr['Account']}",//"会员帐号:",
                                "sEmptyTable": "{$tr['no data']}",//"目前沒有資料",
                                "sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每頁顯示 _MENU_ 筆",
                                "sZeroRecords": "{$tr['no data']}",//"目前沒有資料",
                                "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 頁，共 _PAGES_ 頁",
                                "sInfoEmpty": "{$tr['no data']}",//"目前沒有資料",
                                "sInfoFiltered": "({$tr['from']}_MAX_{$tr['filtering in data']})",//"(從 _MAX_ 筆資料中過濾)"
                                "oPaginate": {
                                  "sPrevious": "{$tr['previous']}",//"上一页",
                                  "sNext": "{$tr['next']}",//"下一页"
                                }
                              },
                              "columnDefs":[
                                { className:"dt-right", "targets":[2,6] },
                                { className:"dt-left","targets": 3 },
                                { className:"dt-center", "targets": 5 },
                                { "targets":"_all", "searchable": false, "orderable": false }
                              ],
                              "ajax": "member_casinotransferlog_action.php?get=query_log{$query_sql}",
                              "columns": [
                                { "data": "id","width":"10px"},
                                //{ "data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                                //  $(nTd).html("<a href=\"javascript:query_str(\'account\',\'"+oData.account+"\')\" data-role=\"button\" >"+oData.account+"</a>");}},
                                { "data": "account","width":"65px"},
                                { "data": "token","width":"45px"},
                                { "data": "casino","width":"45px"},
                                { "data": "status","width":"28px"},
                                { "data": "transaction_id","width":"160px"},
                                { "data": "occurtime","width":"80px"},
                                { "data": "detail","width":"15px"},
                                { "data": "transfer", "width":"65px",
                                  createdCell: function( td, cData, rData, rowIndex, colIndex) {
                                    let btnHtml = '';
                                    let transferStatus = cData;
                                    if (transferStatus == 3) {
                                        btnHtml = '<button id="'+ rData.seq +'" class="btn btn-success btn-block reCheck"'+ 
                                        'onclick="checkTransactionStatus(\''+ rData.seq +'\',\''+ rData.transaction_id +'\',\''+ rData.id +'\',\''+ rowIndex +'\')">{$tr['Inquiry']}</button>';
                                    }
                                    $(td).html(btnHtml);
                                  }
                                }
                                ],
                                createdRow: function (row, data, dataIndex) {
                                  //console.log("test"+data.sourceid.banktype);
                                  if ( data.status == "fail" ) {
                                    $(row).children().addClass( 'bg-danger' );
                                  }
                                  if ( data.status == "error" ) {
                                    $(row).children().addClass( 'bg-warning' );
                                  }
                                }

                          } );
                          
                          
                          $.get("member_casinotransferlog_action.php?get=query_csv{$query_sql}",
                            function(result){
                              // console.log(result);
                              $("#csv_sum").html(result);
                            }
                          );
                          
                        });
                        
                        function paginateScroll() { // auto scroll to top of page
                               $("html, body").animate({
                                  scrollTop: $(".dataTables_wrapper").offset().top
                               }, 100);
                               console.log("pagination button clicked");
                               $(".paginate_button").unbind("click", paginateScroll);
                               $(".paginate_button").bind("click", paginateScroll);
                        }
                        
                        // 確認娛樂城交易狀態
                        function checkTransactionStatus(seq, tid, id, row) {
                            let form = new FormData();
                            form.append("id", seq);
                            form.append("tid", tid);
                            $.ajax({
                                url: "member_casinotransferlog_action.php?get=checkTransactionId",
                                type: "POST",
                                data : form,
                                cache:false,
                                contentType: false,
                                processData: false,
                                success: function(e) {
                                    let result = JSON.parse(e);
                                    let apiErrorCode = result.api;
                                    let dbCode = result.code.code;
                                    let dbMsg = result.code.messages;
                                    if (apiErrorCode == 0) {
                                        let table = $("#show_list").DataTable();
                                        let newLog = getTransferLog(seq, id);
                                        let oldLog = table.row(row);
                                        oldLog.data(newLog);
                                        oldLog.draw(false);
                                        if (dbCode != 1) {
                                            window.alert(dbMsg);
                                        }
                                    } else {
                                        window.alert('{$tr['error, please contact the developer for processing.']}');
                                    }
                                },
                                error: function() {
                                    window.alert('{$tr['error, please contact the developer for processing.']}');
                                }
                            });
                        }
                        
                        // 取得單筆紀錄
                        function getTransferLog(seq, id) {
                            $.ajax({
                                url: "member_casinotransferlog_action.php?get=transferLog&seq="+ seq +"&id="+ id,
                                cache:false,
                                contentType: false,
                                processData: false,
                                success: function(e) {
                                    let result = JSON.parse(e);
                                    return result;
                                },
                                error: function() {
                                    window.alert('{$tr['error, please contact the developer for processing.']}');
                                }
                            });
                        }
                        </script>

                        <style media="screen" type="text/css">

                        input[type=radio], input[type=checkbox] {
                          display:none;
                        }
                       
                        label[for="select_all"]{
                          position: absolute;
                          top:6px;
                          left:5px;
                        }
                        input[type=radio] + label, input[type=checkbox] + label {
                          height: 30px;
                          text-align: center;
                          line-height: 30px;
                          box-sizing: border-box;
                          border:1px #6c757d solid;
                          color: #6c757d;
                          background-color: transparent;
                          border-radius: 0.25rem;
                          transition: all 0.2s;
                          font-size: .5em;
                        }
                        input[type=radio] + label{
                          width: 60px;
                        }

                        input[type=checkbox] + label {
                          width: 100%;
                        }
                        input[type=radio] + label:hover, input[type=checkbox] + label:hover{
                          border-color: #007bffa8;
                          background-color: #007bffa8;
                          color: #fff;
                        }                        
                        input[type=radio]:checked + label, input[type=checkbox]:checked + label{
                          border-color: #007bff;
                          background-color: #007bff;
                          color: #fff;
                        }
                        input[type=radio]:checked ~ div input[type=checkbox]:checked + label{
                          border-color: #6c757d;
                          background: none;
                          color: #6c757d;
                        }
                        /* datatable 欄位換行 */
                        table.dataTable tbody td {
                          word-break: break-word;
                          vertical-align: top;
                        }

                      </style>
HTML;


// 放在結尾的 js
$extend_js          = '';
// 查詢欄（左）title及內容
$indextitle_content   = '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>'.$tr['Search criteria'];
$indexbody_content    = '';
// 結果欄（右）title及內容
$paneltitle_content   = '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>'.$tr['Query results'];
$panelbody_content    = '';
// $tr['Home'] = '首頁'; $tr['Various reports'] = '各式報表';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Various reports'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------
// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// --------------------------------------
// 左方索引內容 -- query 表單
// --------------------------------------

// 查詢條件 - 帳號
$indexbody_content = $indexbody_content.'
<div class="row">
    <div class="col-12"><label>'.$tr['Account'].'</label></div>
    <div class="col-12 form-group">
    <input type="text" class="form-control" name="a"
     id="account_query" placeholder="'.$tr['Account'].'"
     value="';
if(isset($account_query)){
  $indexbody_content = $indexbody_content.$account_query.'">
      </div>
  </div>
  ';
}else{
  $indexbody_content = $indexbody_content.'">
      </div>
  </div>
  ';
}

// // 查詢條件 - 帳號
// $indexbody_content = $indexbody_content.'
// <div class="row">
// <!--     <div class="col-12 col-md-3"><p class="text-right">'.$tr['Account'].'</p></div> -->
//     <div class="col-12"><label>'.$tr['Account'].'</label></div>

//     <!-- <div class="col-12 col-md-9"> -->
//     <div class="col-12">
//     <input type="text" class="form-control" name="a"
//      id="account_query" placeholder="'.$tr['Account'].'"
//      value="';
// if(isset($account_query)){
//   $indexbody_content = $indexbody_content.$account_query.'">
//       </div>
//   </div>
//   <br>
//   ';
// }else{
//   $indexbody_content = $indexbody_content.'">
//       </div>
//   </div>
//   <br>
//   ';
// }

// datepicker
$search_time = time_convert();
// $min_date = ' 00:00'; 
// $max_date = ' 23:59';
// $minus_date = '-01 00:00';

// $current = gmdate('Y-m-d',time()+ -4*3600); // 今天
// $current_date = gmdate('Y-m-d H:i',time() + -4*3600); // 結束時間帶上美東目前時間
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

// 查詢條件 - 時間
$indexbody_content .=<<<HTML
<div class="row">
    <div class="col-12 d-flex">
      <label>{$tr['date']}</label>
      <div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
        <button type="button" class="btn btn-secondary first">{$tr['grade default']}</button>

        <div class="btn-group btn-group-sm" role="group">
          <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">            
          </button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
              <a class="dropdown-item week" onclick="settimerange('{$search_time['thisweekday']} 00:00', getnowtime() ,'week')">{$tr['This week']}</a>
              <a class="dropdown-item month" onclick="settimerange('{$search_time['thismonth']}-01 00:00',getnowtime(),'month')">{$tr['this month']}</a>
              <a class="dropdown-item today" onclick="settimerange('{$search_time['current']} 00:00', getnowtime(), 'today')">{$tr['Today']}</a>
              <a class="dropdown-item yesterday" onclick="settimerange('{$search_time['yesterday']} 00:00', '{$search_time['yesterday']} 23:59','yesterday' );">{$tr['yesterday']}</a>
              <a class="dropdown-item lastmonth" onclick="settimerange('{$search_time['lastmonth']}-01 00:00','{$search_time['lastmonth_e']} 23:59','lastmonth');">{$tr['last month']}</a>
          </div>
        </div>
      </div>
    </div>

    <!-- <div class="col-12 col-md-3">-->
    <div class="col-12 form-group rwd_doublerow_time">
      <div class="input-group">
      <div class="input-group-prepend">
        <span class="input-group-text">{$tr['start']}</span>
      </div>
        <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" placeholder="ex:2017-01-20 00:00:00" value="{$search_time['default_min_date']}{$search_time['min']}">
      </div>

      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text">{$tr['end']}</span>
        </div>
        <input type="text" class="form-control" name="edate" id="query_date_end_datepicker" placeholder="ex:2017-01-20 00:00:00" value="{$search_time['current']}{$search_time['max']}">
      </div>
    </div>
</div>
HTML;

// $indexbody_content .=<<<HTML
// <div class="row">
//     <div class="col-12">
//       <label>{$tr['date']}</label>
//     </div>

//     <!-- <div class="col-12 col-md-3">-->
//     <div class="col-12">
//       <div class="input-group">
//         <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" placeholder="ex:2017-01-20 00:00:00" value="{$default_min_date}{$min_date}">
//         <span class="input-group-addon" id="basic-addon1">~</span>
//         <input type="text" class="form-control" name="edate" id="query_date_end_datepicker" placeholder="ex:2017-01-20 00:00:00" value="{$current_date}{$max_date}">
//       </div>

//       <div class="btn-group btn-group-sm mr-1 my-1" role="group" aria-label="Button group with nested dropdown">
//         <button type="button" class="btn btn-secondary" onclick="settimerange('{$thisweekday} 00:00', getnowtime());">{$tr['This week']}</button>
//         <button type="button" class="btn btn-secondary" onclick="settimerange('{$thismonth}-01 00:00',getnowtime());">{$tr['this month']}</button>

//         <div class="btn-group btn-group-sm" role="group">
//           <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">            
//           </button>
//           <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
//             <a class="dropdown-item" onclick="settimerange('{$current_date} 00:00', getnowtime())">{$tr['Today']}</a>
//             <a class="dropdown-item" onclick="settimerange('{$yesterday} 00:00', '{$yesterday} 23:59' );">{$tr['yesterday']}</a>
//             <a class="dropdown-item" onclick="settimerange('{$lastmonth}-01 00:00','{$lastmonth_e} 23:59');">{$tr['last month']}</a>
//           </div>
//         </div>
//       </div>

//     </div>
// </div>
// HTML;

$casino_list = runSQLall('SELECT casinoid FROM casino_list WHERE open=\'1\';');
unset($casino_list['0']);
$select_casino = '';

$select_casino =<<<HTML
  <input type="radio" id="select_all" name="select_all" value="all" onclick="selectAll(this)">
  <label for="select_all">{$tr['all']}</label>
HTML;

foreach ($casino_list as $key => $value) {
  $now_casinoid = $value->casinoid;
  $label = $casinoLib->getCasinoDefaultName($now_casinoid);
  $select_casino .=<<<HTML
    <div class="col-md-12 col-lg-6">
      <input type="checkbox" class="checkbox_casino" id="check_{$now_casinoid}" value="{$now_casinoid}" name="check_casino">
      <label for="check_{$now_casinoid}">{$label}</label>
    </div>
HTML;
};

$indexbody_content .=<<<HTML
<div class="row">
  <div class="col-12">
  <div class="card search_option_card">
    <div class="card-header">
      <p class="text-center font-weight-bold mb-0">{$tr['Casino']}</p>
    </div>
    <div class="card-body" id="check_casino">
      <div class="row">
       {$select_casino}
      </div>
    </div>
  </div>
  </div>
</div>
HTML;

// js
$extend_js =<<<HTML
<script>
function selectAll(obj,checked){
  var check = document.getElementsByName("check_casino");
  if(obj.checked == true){
    for(var i = 0 ; i < check.length ; i++){
      check[i].checked = obj.checked;
    }
  }
}

$(document).ready(function(){
  $('#select_all').click();
});

$(function(){
  $("input[name=check_casino]").on('change',function(){
  //if($(this).is(":checked")){
    if( $("input[name=select_all]:checked").length > 0 ){
      $("input[name=select_all]").prop("checked",false);
      $("input[name=check_casino]").prop("checked",false);
      $(this).prop("checked",true);
    }
    if ( $("input[name=check_casino]").length - $("input[name=check_casino]:checked").length == 0 || $("input[name=check_casino]:checked").length == 0 ) {
      $("input[name=select_all]").prop("checked",true);
    } 

    console.log($("input[name=check_casino]").length);  
    
  });
});

// datetimepicker
$("#query_date_start_datepicker").datetimepicker({
  showButtonPanel: true,
  timepicker: true,
  // formatTime: "H:i",
  format: "Y-m-d H:i",
  defaultTime: '00:00',
  changeMonth: true,
  changeYear: true,
  step:1
});
$("#query_date_end_datepicker").datetimepicker({
  showButtonPanel: true,
  timepicker: true,
  // formatTime: "H:i",
  format: "Y-m-d H:i",
  defaultTime: '23:59',
  changeMonth: true,
  changeYear: true,
  step:1
});

function getnowtime(){
    var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+' 23:59';

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
</script>
HTML;


$indexbody_content = $indexbody_content.'
<hr>
<div class="row">
  <div class="col-12 col-md-12">
  <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit">'.$tr['Inquiry'].'</button>
  </div>
</div>
';


// --------------------------------------
// 表單, JS 動作 , 按下submit_to_inquiry 透過 jquery 送出 post data 到 url 位址
// $tr['Download CSV'] = '下載CSV'; $tr['The number of queries should be between 1~10000']    = '查詢筆數應介於1~10000之間！';
// --------------------------------------
$agent_inquiry_js_html = <<<HTML
<script>
$(document).ready(function() {
    
    $("#submit_to_inquiry").click(function(){
        $.blockUI({ message: "<img src=\"ui/loading_text.gif\" />" });

        var account_query  = $("#account_query").val();
        var sdate_query  = $("#query_date_start_datepicker").val();
        var edate_query  = $("#query_date_end_datepicker").val();

        var casino_query = $(".checkbox_casino:checked").val();
        //console.log(casino_query);

        if($("input[name=select_all]").is("checked")) {
          var check_casino = '';
        } else{
          var check_casino = '';
          $("input:checkbox:checked[name=check_casino]").each(function(){
            check_casino = check_casino+"&casino_query[]="+ $(this).val();
            //console.log(check_casino);
            //console.log('check_casino:' + $(this).val());
          })
        }

        var query_str = "&a="+account_query+"&sdate="+sdate_query+"&edate="+edate_query+"&casino_name="+casino_query+check_casino;
        //console.log(query_str);

        $('.modal-btn').attr('disabled','disabled');

        $.get("member_casinotransferlog_action.php?get=query_csv"+query_str,
                function(result){
                    if(typeof(result.error_log)!='undefined'){
                        var link=result.error_log;
                    }else if(result.member_casinotransferlog_count > 0 && result.member_casinotransferlog_count < 10001){
                        var link=`<a href="\${result.download_url}" class="btn btn-success btn-sm" role="button" aria-pressed="true">{$tr['Export Excel']}</a>`;
                    }else{
                        var link=`<a href="#" class="btn btn-success btn-sm" role="button" aria-pressed="true"  onclick="alert('{$tr['The number of queries should be between 1~10000']}')" >{$tr['Export Excel']}</a>`;
                    }
                    $("#csv").html(link);
                },'json');

        $("#show_list").DataTable()
            .ajax.url("member_casinotransferlog_action.php?get=query_log"+query_str)
            .load();
        paginateScroll();
        $.unblockUI();

    });

});

// 按下 enter 後,等於 click 登入按鍵
$(function() {
    $(document).keydown(function(e) {
      if(!$('.modal').hasClass('show')){
        switch(e.which) {
            case 13: // enter key
                $("#submit_to_inquiry").trigger("click");
            break;
        }
      }
    });
})
</script>
HTML;

// datatable 內資料查詢用 FUNCTION
$extend_head = $extend_head.<<<HTML
<script type="text/javascript" language="javascript" class="init">
function query_str(query_state,query_datas){
  $.blockUI({ message: "<img src=\"ui/loading_text.gif\" />" });
  $(":input").not(":checkbox, :submit").val("");
  if( query_state == "account"){
    var query_str = "&a="+query_datas;
    $("#account_query").val(query_datas);
  }

  $.get("member_casinotransferlog_action.php?get=query_csv"+query_str,
          function(result){
              if(result.member_casinotransferlog_count > 0 && result.member_casinotransferlog_count < 10001){
                  var link=`<a href="\${result.download_url}" class="btn btn-success btn-sm" role="button" aria-pressed="true">{$tr['Export Excel']}</a>`;
              }else{
                  var link=`<a href="#" class="btn btn-success btn-sm"  role="button" aria-pressed="true"  onclick="alert('{$tr['The number of queries should be between 1~10000']}')" >{$tr['Export Excel']}</a>`;
              }
              $("#csv").html(link);
          },'json');

  $("#show_list").DataTable()
      .ajax.url("member_casinotransferlog_action.php?get=query_log"+query_str)
      .load();
  paginateScroll();
  $.unblockUI();
}
</script>
HTML;


// JS 放在檔尾巴
$extend_js        = $extend_js.$agent_inquiry_js_html;
// --------------------------------------
// jquery post ajax send end.
// --------------------------------------

// -------------------------------------------
// 左方索引內容 -- query 表單 END
// -------------------------------------------

// --------------------------------------
// 右方工作區內容 -- show account name and information
// --------------------------------------

// -------------------------------------------
// 取得查詢內容
// -------------------------------------------
// $tr['ID'] = '序號';$tr['about now'] = '大約距今';$tr['From'] = '從';$tr['source'] = '來源';$tr['FingerPrint'] = '指紋';$tr['Record time']    = '記錄時間';$tr['Switch to']    = '轉換到';$tr['Conversion token amount']    = '轉換代幣額度';$tr['Payout results']    = '派彩結果';$tr['Details']    = '詳情';
// 表格欄位名稱
$table_colname_html =<<<HTML
<tr>
        <th>{$tr['ID']}</th>
        <th>{$tr['Account']}</th>
        <th>{$tr['amount']}</th>
        <th>{$tr['Casino']}</th>
        <th>{$tr['State']}</th>
        <th>{$tr['Transaction order number']}</th>
        <th>{$tr['Transaction time']}</th>
        <th>{$tr['detail']}</th>
        <th>{$tr['pending']}</th>
</tr>
HTML;

$panelbody_content =<<<HTML
<div id="inquiry_result_area">
<table id="show_list"  class="display" cellspacing="0" style="width:100%"> <!--  width="100%"  -->
<thead>
{$table_colname_html}
</thead>
<tfoot>
{$table_colname_html}
</tfoot>
</table>
</div>
HTML;

//將查詢結果，放在csv按紐，並放在查詢結果的右邊
$paneltitle_content = $paneltitle_content.'<div id="csv" style="float:right;margin-bottom:auto"> </div>';

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']    = $tr['host_descript'];
$tmpl['html_meta_author']         = $tr['host_author'];
$tmpl['html_meta_title']          = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']               = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']              = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                = $extend_js;
// 兩欄分割--左邊
$tmpl['indextitle_content']       = $indextitle_content;
$tmpl['indexbody_content']        = $indexbody_content;
// 兩欄分割--右邊
$tmpl['paneltitle_content']       = $paneltitle_content;
$tmpl['panelbody_content']        = $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/dashboard.tmpl.php");
include("template/s2col.tmpl.php");


?>
