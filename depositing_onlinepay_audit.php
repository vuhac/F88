<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 線上支付看板
// File Name:	depositing_onlinepay_audit.php
// Author:		Barkley
// Related:		對應前台 deposit_online_pay.php
// Log:
// ----------------------------------------------------------------------------
// 對應資料表：root_deposit_onlinepay_summons 線上支付訂單傳票
// 相關的檔案：
//
//
// 2017.8.29 update

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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
// $tr['Online payment dashboard'] = '線上支付看板';
$function_title 		= $tr['Online payment dashboard'];
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
  <li><a href="#">'.$tr['Account Management'] .'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

    // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    /*
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

  // ----------------------------------------------------------------------------------
  // 這組設定預計設定為全域變數，讓所有的程式都可以參考這個會員等級。
  // 取得會員等級資料 , 並且把會員等級轉換為對應的陣列
  // ----------------------------------------------------------------------------------
  //$start_memory = memory_get_usage();
  $grade_sql = "SELECT * FROM root_member_grade WHERE status = '1';";
  $member_grade_result = runSQLall($grade_sql);
  if($member_grade_result[0] > 0) {
    for($i=1;$i<=$member_grade_result[0];$i++) {
      $member_grade[$member_grade_result[$i]->id] = $member_grade_result[$i];
      //$member_grade[$member_grade_result[$i]->gradename] = $member_grade[$member_grade_result[$i]->id];
    }
  }else{
    $member_grade = NULL;
    // $tr['No membership grade information'] = '沒有會員等級資料，請聯絡客服人員處理。';
    $logger = $tr['No membership grade information'];
    die($logger);
  }
  //var_dump($member_grade_result);
  //var_dump($member_grade);
  //echo memory_get_usage() - $start_memory;
  // ----------------------------------------------------------------------------------



// ----------------------------------------------------------
// 透過 $salt 將 get 的變數 json + base64 包起來做成 CRC 檢核傳遞的參數
// ----------------------------------------------------------


// 入款訂單單號
// $merchantorderid = $list[$i]->merchantorderid;
//$merchantorderid = '<a href="https://test.gpk17.com/pay/spgateway/checkmerchantorderno.php?m='.$list[$i]->merchantorderid.'&p='.$_SESSION['agent']->registerfingerprinting.'&a='.round($list[$i]->amount,0).'&c='.$checkvalue.'" title="" target="_BLANK">'.$list[$i]->merchantorderid.'</a>';
// 支付公司--處理的入款詳細資訊 -- todo






  // 將 checkbox 堆疊成 switch 的 css
  $extend_head = $extend_head. "
  <style>

  .material-switch > input[type=\"checkbox\"] {
      visibility:hidden;
  }

  .material-switch > label {
      cursor: pointer;
      height: 0px;
      position: relative;
      width: 40px;
  }

  .material-switch > label::before {
      background: rgb(0, 0, 0);
      box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      content: '';
      height: 16px;
      margin-top: -8px;
      margin-left: -18px;
      position:absolute;
      opacity: 0.3;
      transition: all 0.4s ease-in-out;
      width: 30px;
  }
  .material-switch > label::after {
      background: rgb(255, 255, 255);
      border-radius: 16px;
      box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
      content: '';
      height: 16px;
      left: -4px;
      margin-top: -8px;
      margin-left: -18px;
      position: absolute;
      top: 0px;
      transition: all 0.3s ease-in-out;
      width: 16px;
  }
  .material-switch > input[type=\"checkbox\"]:checked + label::before {
      background: inherit;
      opacity: 0.5;
  }
  .material-switch > input[type=\"checkbox\"]:checked + label::after {
      background: inherit;
      left: 20px;
  }

  </style>
  ";

/*
  // switch 修改開關狀態 js
  $extend_js = $extend_js . "
  <script>
	$(document).ready(function() {
		$('#online_refresh_switch').click(function() {
      if($('#online_refresh_switch').prop('checked')) {
        // 定時執行檢查
        var reload_interval = setInterval( reload_datatable(), 30000 );
        console.log('定時執行檢查'+reload_interval);
      }else{
        // 清除指定的定時器, use reload page
        var clear_interval = window.location.reload();
        console.log('清除指定的定時器'+clear_interval);
      }
		});
	});
  </script>
  ";
*/

  // 自動更新開關的畫面
  // $tr['Auto-update information switch'] = '自動更新資訊開關';
  // $tr['Update information manually'] = '手動更新資訊';
  $switch_html = '
  <div class="col-md-3">
    <span class="material-switch pull-left">
      <input id="online_refresh_switch" class="checkbox_switch" value="1" type="checkbox"/>
      <label for="online_refresh_switch" class="label-success"></label>
    </span>'.$tr['Auto-update information switch'].'</div>
  <div class="col-md-3">
  <button id="reload_datatable" class="btn btn-default btn-xs">'.$tr['Update information manually'].'</button>
  </div>
  <br><br>
    ';



  // ---------------------------------------------
  // 表格欄位名稱
  // $tr['Entry Number'] = '入款單號';
  // $tr['Identity Member'] = '會員';
  // $tr['Member Level'] = '會員等級';
  // $tr['amount'] = '金額';
  // $tr['Fee'] = '手續費';
  // $tr['State'] = '狀態';
  // $tr['Status update time'] = '狀態更新時間';
  // $tr['merchant number'] = '商戶號';
  // $tr['Handler'] = '處理人員';
  // $tr['IP/fingerprint'] = 'IP/指紋';

  $table_colname_html = '
  <tr>
    <th>'.$tr['Entry Number'].'</th>
    <th>'.$tr['Identity Member'].'</th>
    <th>'.$tr['Member Level'].'</th>
    <th>'.$tr['amount'].'</th>
    <th>'.$tr['Fee'].'</th>
    <th>'.$tr['State'].'</th>
    <th>'.$tr['Status update time'].'</th>
    <th>'.$tr['merchant number'].'</th>
    <th>'.$tr['Handler'].'</th>
    <th>'.$tr['IP/fingerprint'].'</th>
  </tr>
  ';

  // $tr['Online payment dashboard'] = '線上支付看板';
  $show_tips_html = '<div id="return_message" class="alert alert-success">
	* '.$tr['Online payment dashboard'].' <br>
	</div>';

  // 搜尋表單 and message 表單
  $show_list_html = $show_tips_html;

  // 主框架
  $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
  $show_list_html = $show_list_html.'
  <table '.$sorttablecss.'>
  <thead>
  '.$table_colname_html.'
  </thead>
  <tfoot>
  '.$table_colname_html.'
  </tfoot>
  </table>
  ';

  // 參考使用 datatables 顯示
	// https://datatables.net/examples/styling/bootstrap.html
	$extend_head = $extend_head.'
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
	';

  // https://datatables.net/examples/ajax/objects.html
  // $tr['Manually click force to recapture payment information'] = '手動點擊強制重新抓取付款資訊';
  // $tr['Timing execution check'] = '定時執行檢查';
  // $tr['Clear the specified timer'] = '清除指定的定時器';
  // className: "dt-right", 金額靠右對齊
  $extend_head = $extend_head.'
	<script type="text/javascript" language="javascript" class="init">

  function reload_datatable(){
    var drawtime = Date.now();
    $("#show_list").DataTable().ajax.url("depositing_onlinepay_audit_action.php?a=depositing_onlinepay_audit_data&draw="+drawtime).load();
    console.log("Reload infomation.");
    return 1;
  }

  function show_datatable() {
    var drawtime = Date.now();
    $("#show_list").DataTable( {
      // searching: true,
      // paging: true,
      processing: true,
      serverSide: true,
      pageLength: 25,
      order: [ 0, "desc" ],
      ajax: {
        "url": "depositing_onlinepay_audit_action.php?a=depositing_onlinepay_audit_data",
        "type": "POST"
      },
      columns: [
        {className: "dt-center", data: "id" },
        { data: "member_check" },
        { data: "member_level" },
        { className: "dt-right", data: "amount" },
        { className: "dt-right", data: "cashfee_amount" },
        { data: "status" },
        { data: "transfertime_tz" },
        { data: "deposit_method" },
        { data: "processingaccount" },
        { data: "device_info" }
      ]
    });
  }

  // main
	$(document).ready(function() {
    // 第一次呈現表格資料
    show_datatable();

    //更新表格內的資料
    $("#reload_datatable").click(function() {
      reload_datatable();
      console.log("'. $tr['Manually click force to recapture payment information'].'");
		});

    // 定時開關
    $("#online_refresh_switch").click(function() {
      if($("#online_refresh_switch").prop("checked")) {
        // 定時執行檢查
        var cron_interval = setInterval(function(){ reload_datatable() }, 60000);
        console.log("'. $tr['Timing execution check'] .'"+cron_interval);
      }else{
        // 清除指定的定時器
        var clear_interval = location.reload();
        console.log("'.$tr['Clear the specified timer'].'"+clear_interval);
      }
		});

    // check payment status
    $("#show_list").on("click", ".js-check-payment-status", function(e){
      e.preventDefault();
      console.log( $(e.target).data("deposit-onlinepay-summon-id") );
      var onlinepayId = $(e.target).data("deposit-onlinepay-summon-id");

      $.post("/begpk2dev/depositing_onlinepay_audit_action.php?a=test", { id:254, name: "dright" })
        .done(function(data){ console.log(data); })
	      .fail(function(data){ console.log(data); });

    });

	} )
	</script>
	';


  // 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
    '.$switch_html.'
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
  // $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
	$show_transaction_list_html  = $tr['only management and login mamber'];

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
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
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");

?>
