<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- 查詢統計報表功能
// File Name: statistics_report.php
// Author:		Ian
// Related: statistics_report_action.php
// Log:
// 20200514 Bug #3857 【CS】VIP站後台，各式報表 > 查询统计报表；
//          全站投注此處體育類型判斷有問題，SABA、THREESING 娛樂城營運類別無法選擇體育 (DEMO站可以)
//          - 新增預設反水分類
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/statistics_report_lib.php";
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

// --------------------------------------------------------------------------
// 取得 get 傳來的變數
// --------------------------------------------------------------------------
$query_sql = '';
$query_chk = 0;
if(isset($_GET)){
  if(isset($_GET['a'])) {
    $query_sql = $query_sql.'&ag='.filter_var($_GET['a'], FILTER_SANITIZE_STRING);
    $account_query = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
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
  }elseif(isset($_GET['gc'])) {
    if(filter_var($_GET['gc'], FILTER_VALIDATE_IP)){
      $query_sql = $query_sql.'&gc='.$_GET['gc'];
      $gc_query = $_GET['gc'];
      $query_chk = 1;
    }
  }elseif(isset($_GET['casino'])) {
    $query_sql = $query_sql.'&casino='.filter_var($_GET['casino'], FILTER_SANITIZE_STRING);
    $casino_query = filter_var($_GET['casino'], FILTER_SANITIZE_STRING);
    $query_chk = 1;
  }

  // 預設區間為一個月
  if(!isset($sdate_query) AND !isset($edate_query)){
    // 轉換為美東的時間 date

    $current_datepicker = gmdate('Y-m-d',time() + -4*3600).' 23:59';

    // 原版
    // $default_startdate = gmdate('Y-m-d H:i',strtotime('-1 month') + -4*3600);
    $default_startdate = gmdate('Y-m-d H:i',strtotime('- 7 days') + -4*3600);

    $sdate_query = $default_startdate;
    $edate_query = $current_datepicker;
    $query_sql = $query_sql.'&sdate='.$sdate_query;
    $query_sql = $query_sql.'&edate='.$edate_query;

  }elseif(!isset($sdate_query)){
    // 原版
    // $default_startdate = gmdate('Y-m-d H:i',strtotime('-1 month',$edate_query));
    $default_startdate = gmdate('Y-m-d H:i',strtotime('-7 days',$edate_query));

    $sdate_query = $default_startdate;
    $query_sql = $query_sql.'&sdate='.$sdate_query;

  }elseif(!isset($edate_query)){
    $current_datepicker = gmdate('Y-m-d',time() + -4*3600).' 23:59';

    $edate_query = $current_datepicker;
    $query_sql = $query_sql.'&edate='.$edate_query;

  }
}

// 預設可查區間限制
// $current_date = gmdate('Y-m-d',time() + -4*3600);
// $default_min_date = gmdate('Y-m-d',strtotime('- 2 month'));
// $week = gmdate('Y-m-d',strtotime('- 7 days')); // 7天
// // var_dump($current_date);die();

if( $query_chk == 0){
  $query_sql = '';
}

// 初始化變數
$casinoLib = new casino_switch_process_lib();

// 功能標題，放在標題列及meta
// $function_title 		= $tr['Member betloginfo'];
$function_title 		= $tr['search Statistics report'];
$page_title						= '<h2><strong>'.$function_title.'</strong></h2><hr>';
// 擴充 head 內的 css or js
// 加 datetime picker 及 datatables 的 js
$extend_head				= '<!-- Jquery UI js+css  -->
                        <script src="in/jquery-ui.js"></script>
                        <link rel="stylesheet"  href="in/jquery-ui.css" >
                        <!-- jquery datetimepicker js+css -->
                        <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
                        <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
                        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	                      <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	                      <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
                        <script type="text/javascript" language="javascript" class="init">
                          // $(document).ready(function() {
                            // getquery();


                            // $.get("statistics_report_action.php?a=query_summary'.$query_sql.'",
                            //   function(result){
                            //     $("#show_sum_content").html(result);
                            //   });
                          // } )
                          function check_all(obj,cName,checked){
                            var checkboxs = document.getElementsByName(cName);
                            if(checked==\'1\'){
                              for(var i=0;i<checkboxs.length;i++){checkboxs[i].checked = obj.checked;}
                            }else{
                              for(var i=0;i<checkboxs.length;i++){checkboxs[i].checked = obj.unchecked;}
                            }
                          }
                        </script>
                        ';
// 放在結尾的 js
$extend_js					= '';
// 查詢欄（左）title及內容
$indextitle_content 	= '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>'.$tr['Search criteria'];
$indexbody_content		= '';
// 結果欄（右）title及內容 $tr['Home'] = '首頁';$tr['Various reports'] = '各式報表';
$paneltitle_content 	= '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>'.$tr['Query results'];
$panelbody_content		= '';
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
$search_time = time_convert();

// $min_date = ' 00:00'; 

// $thisweekday = date("Y-m-d", strtotime("$current_date - ".date('w',strtotime($current_date))."days"));
// $yesterday = date("Y-m-d", strtotime("$current_date - 1 days"));
// $lastweekday_s = date("Y-m-d", strtotime("$current_date - ".intval(date('w',strtotime($current_date))+7)."days"));
// $lastweekday_e = date("Y-m-d", strtotime("$thisweekday - 1 days"));
// $thismonth = date("Y-m", strtotime($current_date));

// $lastmonth = date('Y-m',strtotime('-1 month',strtotime($current_date)));
// $lastmonth_e = date("Y-m-t", strtotime($lastmonth));


// 查詢條件 - 時間 $tr['Query up to two months of data'] = '可查詢最多兩個月內資料';
// <a class="dropdown-item today" onclick="settimerange(\''.$search_time['current'].' 00:00\',getnowtime(),\'today\');">'.$tr['Today'].'</a>
$indexbody_content = $indexbody_content.'
<div class="row">
    <div class="col-12 d-flex">
    <label for="sdate">'.$tr['date'].' <span class="glyphicon glyphicon-info-sign" title="'.$tr['Query up to two months of data'].'"></span></label>
    
      <div class="btn-group btn-group-sm ml-auto application" role="group" aria-label="Button group with nested dropdown">
        <button type="button" class="btn btn-secondary first">'.$tr['grade default'].'</button>

        <div class="btn-group btn-group-sm" role="group">
          <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">            
          </button>
          <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
            <a class="dropdown-item week" onclick="settimerange(\''.$search_time['thisweekday'].' 00:00\',getnowtime(),\'week\');">'.$tr['This week'].'</a>
            <a class="dropdown-item month" onclick="settimerange(\''.$search_time['thismonth'].'-01 00:00\',getnowtime(),\'month\');">'.$tr['this month'].'</a>
            
            <a class="dropdown-item yesterday" onclick="settimerange(\''.$search_time['yesterday'].' 00:00\',\''.$search_time['yesterday'].' 23:59\',\'yesterday\');">'.$tr['yesterday'].'</a>
            <a class="dropdown-item lastweek" onclick="settimerange(\''.$search_time['lastweekday_s'].' 00:00\',\''.$search_time['lastweekday_e'].' 23:59\',\'lastweek\');">'.$tr['Last week'].'</a>
            <a class="dropdown-item lastmonth" onclick="settimerange(\''.$search_time['lastmonth'].'-01 00:00\',\''.$search_time['lastmonth_e'].' 23:59\',\'lastmonth\');">'.$tr['last month'].'</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 form-group rwd_doublerow_time">
      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text">開始</span>
        </div>
        <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" placeholder="ex:2017-01-20 " value="'.$search_time['default_min_date'].$search_time['min'].'">
      </div>
      <div class="input-group">
        <div class="input-group-prepend">
          <span class="input-group-text">結束</span>
        </div>
        <input type="text" class="form-control" name="edate" id="query_date_end_datepicker" placeholder="ex:2017-01-20" value="'.$current_datepicker.'">
      </div>    
    </div>
</div>
<script>
    $("#query_date_start_datepicker").datetimepicker({
      minDate: "'.$search_time['two_month'].'",
      maxDate: "'.$search_time['current'].'",
      showButtonPanel: true,
      timepicker:true,
      format: "Y-m-d H:i",
      changeMonth: true,
      changeYear: true,
      step:1,
      initTime: "00:00"
    });
    $("#query_date_end_datepicker").datetimepicker({
      minDate: "'.$search_time['two_month'].'",
      maxDate: "'.$search_time['current'].'",
      showButtonPanel: true,
      timepicker:true,
      format: "Y-m-d H:i",
      changeMonth: true,
      changeYear: true,
      step:1,
      initTime: "23:59"

    });
</script>';

// $indexbody_content = $indexbody_content.'
// <div class="row">
//     <label for="sdate">'.$tr['date'].' <span class="glyphicon glyphicon-info-sign" title="'.$tr['Query up to two months of data'].'"></span></label>
//     <div class="form-group input-group">
//       <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" placeholder="ex:2017-01-20 " value="'.$week.$min_date.'">
//       <span class="input-group-addon">~</span>
//       <input type="text" class="form-control" name="edate" id="query_date_end_datepicker" placeholder="ex:2017-01-20" value="'.$current_date.$max_date.'">
//     </div>

//     <div class="btn-group btn-group-sm mr-1 my-1" role="group" aria-label="Button group with nested dropdown">
//       <button type="button" class="btn btn-secondary" onclick="settimerange(\''.$thisweekday.' 00:00\',getnowtime());">'.$tr['This week'].'</button>
//       <button type="button" class="btn btn-secondary" onclick="settimerange(\''.$thismonth.'-01 00:00\',getnowtime());">'.$tr['this month'].'</button>

//       <div class="btn-group btn-group-sm" role="group">
//         <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">            
//         </button>
//         <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
//           <a class="dropdown-item" onclick="settimerange(\''.$current_date.' 00:00\',getnowtime());">'.$tr['Today'].'</a>
//           <a class="dropdown-item" onclick="settimerange(\''.$yesterday.' 00:00\',\''.$yesterday.' 23:59\');">'.$tr['yesterday'].'</a>
//           <a class="dropdown-item" onclick="settimerange(\''.$lastweekday_s.' 00:00\',\''.$lastweekday_e.' 23:59\');">'.$tr['Last week'].'</a>
//           <a class="dropdown-item" onclick="settimerange(\''.$lastmonth.'-01 00:00\',\''.$lastmonth_e.' 23:59\');">'.$tr['last month'].'</a>
//         </div>
//       </div>
//     </div>
// </div>
// <script>
//     $("#query_date_start_datepicker").datetimepicker({
//       minDate: "'.$default_min_date.'",
//       maxDate: "'.$current_date.'",
//       showButtonPanel: true,
//       timepicker:true,
//       format: "Y-m-d H:i",
//       changeMonth: true,
//       changeYear: true,
//       step:1,
//       initTime: "00:00"
//     });
//     $("#query_date_end_datepicker").datetimepicker({
//       minDate: "'.$default_min_date.'",
//       maxDate: "'.$current_date.'",
//       showButtonPanel: true,
//       timepicker:true,
//       format: "Y-m-d H:i",
//       changeMonth: true,
//       changeYear: true,
//       step:1,
//       initTime: "23:59"

//     });
// </script>
// <br>';

$v_acc=$account_query??'';

// 查詢條件 - 帳號 $tr['Statistics by agent when inquiring include the statistics of agents themselves and their level off-line'] = '依代理商查詢時統計資料包含代理商本人及其一級下線的統計值！';
$indexbody_content = $indexbody_content.'
  <div class="row">
      <div class="col-12">
        <label for="account_query">'.$tr['agent'].' <span class="glyphicon glyphicon-info-sign" title="'.$tr['Statistics by agent when inquiring include the statistics of agents themselves and their level off-line'].'"></span></label>
      </div>
      <div class="col-12 form-group">
      <input type="text" class="form-control" name="a" id="account_query" placeholder="'.$tr['agent'].'"
        value="'.$v_acc.'">
        </div>
  </div>';



// 查詢條件 - 遊戲城列表
//$tr['select all'] = '全選';
$casinolist_option = '<input type="radio" id="cidselectall" name="cidselectall" value="all" onclick="check_all(this,\'cidselect\',0)" checked>
       <label for="cidselectall" class="d-flex justify-content-center align-items-center">'.$tr['all'] .'</label>';

$menu_casinolist_item_result = casionlist();

for($l=1;$l<=$menu_casinolist_item_result[0];$l++){
	$casinoId = $menu_casinolist_item_result[$l]->casinoid;
	$casinoName = $casinoLib->getCurrentLanguageCasinoName($menu_casinolist_item_result[$l]->display_name, 'default');
    $casinolist_option .= '
      <div class="col-4 col-md-12 col-lg-6 col-xl-4 px-1">
          <input type="checkbox" id="cidsel_'. $casinoId .'" name="cidselect" value="'. $casinoId .'" onclick="check_all(this,\'cidselectall\',0)">
          <label for="cidsel_'.$casinoId.'" class="d-flex align-items-center justify-content-center">'. $casinoName .'</label>
      </div>';
}

// $tr['Casino'] = '娛樂城';
$indexbody_content = $indexbody_content.'
<div class="row">
<div class="col-12 form-group">
  <div class="card search_option_card">
    <div class="card-header">
    <p class="text-center font-weight-bold mb-0">'.$tr['Casino'].'</p>
   </div>
<div class="card-body" id="cidselect">
      <div class="row">
       '.$casinolist_option.'
       </div>
    </div>
  </div>
</div>
</div>
';

// // $tr['Casino'] = '娛樂城';
// $indexbody_content = $indexbody_content.'
// <div class="row">
//   <div class="card search_option_card">
//     <div class="card-header">
//    <!--<div class="col-12 col-md-3"><p class="text-right">\'.$tr[\'casino\'].\'</p></div>-->
//     <p class="text-center font-weight-bold">'.$tr['Casino'].'</p>
//    </div>
// <div class="card-body" id="cidselect">
//       <div class="row">
//        '.$casinolist_option.'
//        </div>
//     </div>
//   </div>
// </div>
// <br>
// ';
unset($menu_casinolist_item_result,$casinolist_option,$v_acc);


// 查詢條件 - 遊戲種類
$gc_option = '<input type="radio" id="gcselectall" name="gcselectall" value="all" onclick="check_all(this,\'gcselect\',0)" checked>
       <label for="gcselectall" class="d-flex align-items-center justify-content-center">'.$tr['all'] .'</label>';

$gcarr = getFavorableTypeToNameArray();

foreach($gcarr as $gckey =>$gcval){
  // 翻譯
  if(isset($tr[$gckey])) {
    $gc_option = $gc_option.'<div class="col-4 col-md-12 col-lg-4 col-xl-4 px-1"><input type="checkbox" id="sel_'.$gckey.'"
          name="gcselect" value="'.$gckey.'" onclick="check_all(this,\'gcselectall\',0)">
           <label for="sel_'.$gckey.'" class="d-flex align-items-center justify-content-center">'.$tr[$gckey].'</label></div>';
  }else{
    $gc_option = $gc_option.'<div class="col-4 col-md-12 col-lg-4 col-xl-4 px-1"><input type="checkbox" id="sel_'.$gckey.'"
          name="gcselect" value="'.$gckey.'" onclick="check_all(this,\'gcselectall\',0)">
           <label for="sel_'.$gckey.'" class="d-flex align-items-center justify-content-center">'.$gcval.'</label></div>';
  }
}
// $tr['category'] = '類別';
$indexbody_content = $indexbody_content.'
<div class="row">
  <div class="col-12">
  <div class="card search_option_card">
    <div class="card-header">
    <p class="text-center font-weight-bold mb-0">'.$tr['category'].'</p>
    </div>
    <div class="card-body" id="gcselect">
      <div class="row">
      '.$gc_option.'
      </div>
    </div>
  </div>
</div>
</div>
 ';

 // $tr['category'] = '類別';
// $indexbody_content = $indexbody_content.'
// <div class="row">
//   <div class="card search_option_card">
//     <div class="card-header">
//     <!--<div class="col-12 col-md-3"><p class="text-right">\'.$tr[\'game_category\'].\'</p></div>-->
//     <p class="text-center font-weight-bold">'.$tr['category'].'</p>
//     </div>
//     <div class="card-body" id="gcselect">
//       <div class="row">
//       '.$gc_option.'
//       </div>
//     </div>
//   </div>
// </div>
//  <br>
//  ';

$indexbody_content = $indexbody_content.'
<hr>
<div class="row">
  <div class="col-12">
  <button id="submit_to_inquiry" class="btn btn-success btn-block" type="submit">'.$tr['Inquiry'].'</button>
  </div>
</div>
';

// radio and checkbox button css    // margin-bottom: 2;     margin-left:-2px;

$extend_head = $extend_head.'
<style media="screen" type="text/css">
input[type=radio], input[type=checkbox] {
		display:none;
}
.search_option_card{
    width: 100%;
}
label[for="cidselectall"],label[for="gcselectall"]{
    position: absolute;
    top:6px;
    left:5px;
}
input[type=radio] + label, input[type=checkbox] + label {    
    min-height: 30px;
    text-align: center;
    box-sizing: border-box;
    border:1px #6c757d solid;
    color: #6c757d;
    background-color: transparent;
    border-radius: 0.25rem;
    transition: all 0.2s;
    font-size: .5em;
    word-wrap: break-word;
    word-break: break-all;
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
</style>
';

// $tr['Identity Member'] = '会员';$tr['Total station betting statistics'] = '全站投注統計';$tr['Betting statistics'] = '投注統計';$tr['Statistical interval'] = '统计区间';$tr['Download CSV'] = '下載CSV';$tr['Query the date range of 3 days'] = '查詢日期區間為3天內！';
// 按下 enter 後,等於 click 登入按鍵


$agent_inquiry_js_html = <<<HTML
<script>
$(document).ready(function() {
  $("#submit_to_inquiry").click(function(){
      getquery();
  });

  var cidselect_length = $('input[name=cidselect]').length;
  $('input[name=cidselect]').on('change',function(){
    if( $('input[name=cidselect]:checked').length - cidselect_length == 0 || $('input[name=cidselect]:checked').length == 0 ){
      //$("input[name=cidselectall]").prop("checked",true);
      $('#cidselectall').click();
    }  
  });
  
  var gcselect_length = $('input[name=gcselect]').length;
  $('input[name=gcselect]').on('change',function(){
    if( $('input[name=gcselect]:checked').length - gcselect_length == 0 || $('input[name=gcselect]:checked').length == 0 ){
      //$("input[name=cidselectall]").prop("checked",true);
      $('#gcselectall').click();
    }    
  });

  // 20200417
  getquery();
  $("#show_list").DataTable({
    "bProcessing": true,
    "bServerSide": true,
    "bRetrieve": true,
    "searching": false,
    "aaSorting": [[ 4, "desc" ]],
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
      "url":"statistics_report_action.php?a=get_init"
      // "url":"statistics_report_action.php?a=get_init_query"

    },
    "columns":[
      {"data":"item"},
      {"data":"account"},
      {"data":"bet_slip"},
      {"data":"bet_amount"},
      {"data":"profit_and_loss"},
      {"data":"update_time"}
    ]
  })
  // ----------------
});
$(function() {
  $(document).keydown(function(e) {
    switch(e.which) {
        case 13: // enter key
            $("#submit_to_inquiry").trigger("click");
        break;
    }
  });
});
function getnowtime(){
  var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+' 23:59';
  return NowDate;
}
function settimerange(sdate,edate,text){
  $("#query_date_start_datepicker").val(sdate);
  $("#query_date_end_datepicker").val(edate);
  // getquery();
		//更換顯示到選單外 20200525新增
    var currentonclick = $('.'+text+'').attr('onclick');
    var currenttext = $('.'+text+'').text();
		//first change
    $('.application .first').removeClass('week month');
    $('.application .first').attr('onclick',currentonclick);
    $('.application .first').text(currenttext); 
}

function getquery(){
  if($("#query_date_start_datepicker").val()=='' || $("#query_date_end_datepicker").val()==''){
      alert('请填入起讫日期，错误代码：1907171225946。');
      return false;
  }
  
  var account_query  = $("#account_query").val();
  var query_date_start_datepicker  = $("#query_date_start_datepicker").val();
  var query_date_end_datepicker  = $("#query_date_end_datepicker").val();
  var gc_query  = "";

  $("input:checkbox:checked[name=\"gcselect\"]").each( function(){
    gc_query=gc_query+"&gc[]="+$(this).val();
  });
  var casino_query  = "";
  var casino_title_name  = "";
  $("input:checkbox:checked[name=\"cidselect\"]").each( function(){
   casino_query=casino_query+"&casino[]="+$(this).val();
   if (!casino_title_name){
        casino_title_name=$(this).val();
    }else{
        casino_title_name=casino_title_name+' / '+$(this).val();
   }
  });
  if(!casino_title_name){
     casino_title_name = "{$tr['total station']}"; 
   }
  var query_str = "&ag="+account_query+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+gc_query+casino_query;
  if(account_query){
    var search_status = "<div class='alert alert-success'><h3>"+account_query+"及直属下线 ── {$tr['Betting statistics']}</h3></div>";
    // var search_status = "<h3 class='alert alert-success'>{$tr['Identity Member']} "+account_query+"及直属下线 ── {$tr['Betting statistics']}</h3>";
  }else{
    var search_status = "<div class='alert alert-success'><h3>"+casino_title_name+" {$tr['Betting statistics']} </h3><br> * 搜寻时间以派彩时间为主。 <br></div>";
  }
  
  $("#show_summary").hide();
  $("#searching").show();
    $.get("statistics_report_action.php?a=query_summary"+query_str,
      function(result){

        // 20200421
        if(result.logger == false){
          // 無資料
          alert('查无资料，娱乐城无该分类项目。');
          var no_data_html = `
              <tr>
                <td>0</td>
                <td>0</td>
                <td>$0.00</td>
                <td><span style="color: red">$0.00</span></td>
                <td></td>
              </tr>
          `;
          var no_link=`
            <a href="#" class="btn btn-success btn-sm" role="button" aria-pressed="true" disabled="disabled">{$tr['Export Excel']}</a>
          `;
          $("#show_sum_content").html(no_data_html);
          $("#csv").html(no_link);

        }else if(!result.logger){
          // search_status +="<h3 class='text-info'>{$tr['Statistical interval']}："+result.date_rang+"<br></h3>";
          search_status +="<h3 class='text-info'>{$tr['The current date of the query is']}："+result.date_rang+"<br></h3>";

          var ary_show_sum_content=`
              <tr>
                <td>\${result.member_betlog_result_count}</td>
                <td>\${result.member_betlog_counter}</td>
                <td>$\${result.num_member_betlog_betvalidsum}</td>
                <td><span style="\${result.difference_payout_style}">\${result.payout_style} $\${result.num_member_betlog_accumulated}</span></td>
                <td>\${result.lastupdate}</td>
              </tr>;
          `;
          $("#show_sum_content").html(ary_show_sum_content);
          $("#casino_title_name").html(casino_title_name);

          var link=`
                <a href="\${result.sum_report_url}" class="btn btn-success btn-sm" role="button" target="_blank" aria-pressed="true" >{$tr['Export Excel']}</a>
                `;
          $("#csv").html(link);

          $("#search_status").html(search_status);

        }else{
          alert(result.logger);
          $("#show_sum_content").html();

        }
        $("#show_summary").show();
        $("#searching").hide();
      }, 'json');
      //------------------------

      // 原版
      //   if(!result.logger){
      //     search_status +="<h3 class='text-info'>{$tr['Statistical interval']}："+result.date_rang+"<br></h3>";
      //     var ary_show_sum_content=`
      //         <tr>
      //           <td>\${result.member_betlog_result_count}</td>
      //           <td>\${result.member_betlog_counter}</td>
      //           <td>$\${result.num_member_betlog_betvalidsum}</td>
      //           <td><span style="\${result.difference_payout_style}">$\${result.num_member_betlog_accumulated}</span></td>
      //           <td>\${result.lastupdate}</td>
      //         </tr>;
      //     `;
      //     $("#show_sum_content").html(ary_show_sum_content);
      //     $("#casino_title_name").html(casino_title_name);

      //     var link=`
      //           <a href="\${result.sum_report_url}" class="btn btn-success btn-sm" role="button" target="_blank" aria-pressed="true" >{$tr['Export Excel']}</a>
      //           `;
      //     $("#csv").html(link);

      //     $("#search_status").html(search_status);

      //   }else{
      //     alert(result.logger);
      //     $("#show_sum_content").html();
      //   }
      //   $("#show_summary").show();
      //   $("#searching").hide();
      // }, 'json');

  }

  // 20200420
  // datatable搜尋
  $("#submit_to_inquiry").click(function(){
      
      var account_query  = $("#account_query").val();
      var query_date_start_datepicker  = $("#query_date_start_datepicker").val();
      var query_date_end_datepicker  = $("#query_date_end_datepicker").val();
      var gc_query  = "";
  
      var today = new Date();

      // 最小搜尋時間
      var minDateTime = today.getFullYear()+'-'+(today.getMonth()-1)+'-'+today.getDate()+ ' ' + '00:00';
      // 最大搜尋時間
      var maxDateTime = today.getFullYear()+'-'+(today.getMonth()+1)+'-'+today.getDate()+ ' ' + '23:59';//today.getHours()+':'+today.getMinutes();

      // 開始時間<最小搜尋時間
      if((Date.parse(query_date_start_datepicker)).valueOf() < (Date.parse(minDateTime)).valueOf()){
        alert('开始时间错误，查询区间超过2个月，请修改查询区间！');
        window.location.reload();
        return false;
      }
      // 結束時間>最大搜尋時間
      if((Date.parse(query_date_end_datepicker)).valueOf() > (Date.parse(maxDateTime)).valueOf()){
        alert( '结束时间错误，查询区间超过2个月，请修改查询区间!');
        window.location.reload();
        return false;
      }
      if((Date.parse(query_date_start_datepicker)).valueOf() > (Date.parse(query_date_end_datepicker)).valueOf()){
        alert( '开始时间错误，请修改查询区间!');
        window.location.reload();
        return false;
      }
      if((Date.parse(query_date_end_datepicker)).valueOf() < (Date.parse(query_date_start_datepicker)).valueOf()){
        alert( '结束时间错误，请修改查询区间!');
        location.reload();
        return false;
      }

      $("input:checkbox:checked[name=\"gcselect\"]").each( function(){
        gc_query=gc_query+"&gc[]="+$(this).val();
      });
      var casino_query  = "";
      var casino_title_name  = "";
      $("input:checkbox:checked[name=\"cidselect\"]").each( function(){
        casino_query=casino_query+"&casino[]="+$(this).val();
        if (!casino_title_name){
              casino_title_name=$(this).val();
          }else{
              casino_title_name=casino_title_name+' / '+$(this).val();
        }
      });
      if(!casino_title_name){
        casino_title_name = "{$tr['total station']}"; 
      }
      var query_str = "&ag="+account_query+"&sdate="+query_date_start_datepicker+"&edate="+query_date_end_datepicker+gc_query+casino_query;
      // console.log(query_str);

      $("#show_list").DataTable()
        .ajax.url("statistics_report_action.php?a=get_result"+query_str)
        .load();

      // $.get("statistics_report_action.php?a=get_result"+query_str,
      //   function(result){
      //     // console.log(result);
      //     // $("#show_detail").html(result);
      //     if(result.logger){
      //       $("#show_detail").html(result);
      //     }else{
      //       console.log('a');
      //     }
      //   });
  })
</script>
HTML;

// JS 放在檔尾巴
$extend_js				= $extend_js.$agent_inquiry_js_html;
// --------------------------------------
// jquery post ajax send end.
// --------------------------------------

// -------------------------------------------
// 左方索引內容 -- query 表單 END
// -------------------------------------------

// --------------------------------------
// 右方工作區內容 -- show account name and information
// --------------------------------------

// $tr['Data query'] = '資料查詢中';$tr['Online membership'] = '線上會員數';$tr['There are betting members in the time zone'] = '時間區間內有進行投注之會員數';$tr['The number of documents'] = '單量';$tr['Total effective betting amount'] = '總有效投注金額';$tr['Total profit and loss result'] = '總損益結果';$tr['last update time'] = '最後更新時間';
// $panelbody_content	= $panelbody_content.'
// <div id="inquiry_result_area">
//   <div id="searching" style="display: none">
//     <h5 align="center">'.$tr['Data query'].'...<img width="30px" height="30px" src="ui/loading.gif" /></h5>
//   </div>
//   <div id="show_summary">
//     <div id="search_status"></div>
//     <table id="show_sum_list" class="table" cellspacing="2" width="100%" >
//       <thead class="thead-inverse">
//         <tr>
//           <th>'.$tr['Online membership'].' <span class="glyphicon glyphicon-info-sign" title="'.$tr['There are betting members in the time zone'].'"></span></th>
//           <th>'.$tr['The number of documents'].'</th>
//           <th>'.$tr['Total effective betting amount'].'</th>
//           <th>'.$tr['Total profit and loss result'].'</th>
//           <th>'.$tr['last update time'].'</th>
//         </tr>
//       </thead>
//       <tbody id="show_sum_content">
//       </tbody>
//     </table>
//   </div>
// </div>';


// ---------------------------------------------------------
// 20200417
// 欄位名稱
$table_conname=<<<HTML
  <tr>
    <th>{$tr['ID']}</th></th>
    <th>{$tr['Account']}</th>
    <th>{$tr['bet slip']}</th>
    <th>{$tr['bet amount']}</th>
    <th>{$tr['profit and loss']}</th>
    <th>{$tr['last update time']}</th>
  </tr>
HTML;

$panelbody_content	= $panelbody_content.'
<div id="inquiry_result_area">
  <div id="searching" style="display: none">
    <h5 align="center">'.$tr['Data query'].'...<img width="30px" height="30px" src="ui/loading.gif" /></h5>
  </div>
  <div id="show_summary">
    <div id="search_status"></div>
    <table id="show_sum_list" class="table" cellspacing="2" width="100%" >
      <thead class="thead-inverse">
        <tr>
          <th>会员数 <span class="glyphicon glyphicon-info-sign" title="'.$tr['There are betting members in the time zone'].'"></span></th>
          <th>'.$tr['bet slip'].'</th>
          <th>'.$tr['Total effective betting amount'].'</th>
          <th>'.$tr['Total profit and loss result'].'</th>
          <th>'.$tr['last update time'].'</th>
        </tr>
      </thead>
      <tbody id="show_sum_content">
      </tbody>
    </table>
  </div>
</div>';

$panelbody_content .=<<<HTML
<hr>
  <div id="show_table">
    <table id ="show_list" class="display" cellspacing="0" width="100%">
      <thead>
      {$table_conname}
      </thead>
      <tfoot>
      {$table_conname}
      </tfoot>
      <tbody id="show_detail">

      </tbody>
    </table>
  </div>
HTML;
// --------------------------------------------------------

$paneltitle_content = $paneltitle_content.'<div id="csv"  style="float:right;margin-bottom:auto"></div>';
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
// 兩欄分割--左邊
$tmpl['indextitle_content']				= $indextitle_content;
$tmpl['indexbody_content'] 				= $indexbody_content;
// 兩欄分割--右邊
$tmpl['paneltitle_content']				= $paneltitle_content;
$tmpl['panelbody_content']				= $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/dashboard.tmpl.php");
include("template/s2col.tmpl.php");
