<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 系統獎金發放管理
// File Name:    receivemoney_management.php
// Author:    Barkley Fix by Ian,2019.12.20 by Yaoyuan
// Related:   對應 receivemoney_management_action.php、receivemoney_management_detail.php
//            DB root_receivemoney
// Log:
// 2019.12.20 update
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/receivemoney_management_lib.php";
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

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------

// 取得 today date get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if (isset($_GET['current_datepicker'])) {
  // 判斷格式資料是否正確
  if (validateDate($_GET['current_datepicker'], 'Y-m-d H:i')) {
    $current_datepicker = $_GET['current_datepicker'];

    $default_min_date = gmdate('Y-m-d',strtotime('- 7 days')).' 00:00'; // 7天
    $default_max_date = gmdate('Y-m-d',strtotime('+ 7 days')).' 00:00'; // 目前時間往後推7天
  } else {
    // 轉換為美東的時間 date
    $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
    date_timezone_set($date, timezone_open('America/St_Thomas'));
    $current_datepicker  = date_format($date, 'Y-m-d').' 23:59';
    // $current_datepicker = date('Y-m-d');

    $default_min_date = gmdate('Y-m-d',strtotime('- 7 days')).' 00:00'; // 7天
    $default_max_date = gmdate('Y-m-d',strtotime('+ 7 days')).' 00:00'; // 目前時間往後推7天
  }
} else {
  // php 格式的 2017-02-24
  // 轉換為美東的時間 date
  $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
  date_timezone_set($date, timezone_open('America/St_Thomas'));
  $current_datepicker = date_format($date, 'Y-m-d').' 23:59';

  $default_min_date = gmdate('Y-m-d',strtotime('- 7 days')).' 00:00'; // 7天
  $default_max_date = gmdate('Y-m-d',strtotime('+ 7 days')).' 00:00'; // 目前時間往後推7天
}
// $min_datepicker = gmdate('Y-m-d',strtotime('- 2 month'));

// 初始化變數
$extend_head = '';
$extend_js = '';
$function_title = $tr['System bonus payment management'];
$page_title = '';
$indextitle_content = '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>' . $tr['Search criteria'];
$indexbody_content = '';
$paneltitle_content = '<span class="glyphicon glyphicon-list" aria-hidden="true"></span>' . $tr['Query results'];
$panelbody_content = '';
$exportExcelBtn = '<a href="receivemoney_import.php" class="btn btn-primary js-modal-trigger text-white">'.$tr['import into excel'].'</a>';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置  $tr['Home'] = '首頁'; $tr['profit and promotion'] = '營收與行銷';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['profit and promotion'] . '</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// ----------------------------------------------------------------------------

// 除錯開關 debug =  1--> on , 0 --> off
$debug = 0;

// ----------------------------------------------------------------------------
// 左上角選單
$summary_menu = index_menu();
// ----------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// $tr['Member Account'] = '會員帳號';$tr['Inquiry'] = '查詢';$tr['Bonus'] = '反水';$tr['Bonus classification'] = '獎金分類';$tr['Bonus status'] = '獎金狀態';$tr['time out'] = '暫停';$tr['Can receive'] = '可領取';$tr['Cancel'] = '取消';$tr['Bonus effective date range'] = '獎金有效日期區間';
// ---------------------------------------------------------------------------

// 查詢欄（左）內容 START-------------------------------------------

// datepicker
$search_time = time_convert();
// $min_date = ' 00:00';
// $max_date = ' 23:59';
// $minus_date = '-01 00:00';

// $current = gmdate('Y-m-d',time()+ -4*3600); // 今天
// $current_date = gmdate('Y-m-d',time() + -4*3600).' 23:59'; // 結束時間帶上美東目前時間
// // $default_min_date = gmdate('Y-m-d',strtotime('- 7 days')); // 7天

// $thisweekday = date("Y-m-d", strtotime("$current_date - ".date('w',strtotime($current_date))."days"));
// $yesterday = date("Y-m-d", strtotime("$current_date - 1 days"));

// // 上週
// $lastweekday_s = date("Y-m-d", strtotime("$current_date - ".intval(date('w',strtotime($current_date))+7)."days"));
// $lastweekday_e = date("Y-m-d", strtotime("$thisweekday - 1 days"));

// $thismonth = date("Y-m", strtotime($current_date));

// // 上個月
// $lastmonth = date('Y-m',strtotime(date('Y-m-1').'-1 month'));
// $lastmonth_e = date('Y-m-d',strtotime(date('Y-m-1').'-1 day'));

//有抓發放時間所以 發放時間UI不可刪除
// <div class="row d-none">
// <div class="col-12">
//   <label>{$tr['Release time']}</label>
// </div>
// <div class="col-12 form-group">
//   <input type="text" id="bons_givemoneytime" class="form-control" disabled value="">
// </div>
// </div>

$indexbody_content =<<<HTML
<form method="get">
    <div class="row">
      <div class="col-12">
        <label>{$tr['Account']}</label>
      </div>
      <div class="col-12 form-group">
        <input type="text" class="form-control" name="member_account" id="member_account" placeholder="ex:abc">
      </div>
    </div>

    <div class="row">
      <div class="col-12">
        <label>{$tr['Bonus classification']}</label>
      </div>
      <div class="col-12 form-group">
        <input type="text" class="form-control" name="bonus_type" id="bonus_type" placeholder="ex:{$tr['Bonus']}" value="">
      </div>
    </div>

    <div class="row d-none">
      <div class="col-12">
        <label>{$tr['Release time']}</label>
      </div>
      <div class="col-12 form-group">
        <input type="text" id="bons_givemoneytime" class="form-control" disabled value="">
      </div>
    </div>   

    <div class="row">
      <div class="col-12">
        <label>{$tr['Bonus status']}</label>
      </div>
      <div class="col-12 form-group">
        <select class="form-control w-100" name="bonus_status" id="bonus_status" >
          <option value="" selected>--</option>
          <option value="0">{$tr['Cancel']}</option>
          <option value="1">{$tr['Can receive']}</option>
          <option value="2">{$tr['time out']}</option>
          <option value="3">{$tr['received']}</option>
          <option value="4">{$tr['expired']}</option>
        </select>
      </div>
    </div>    

    <div class="row">
      <div class="col-12 d-flex">
        <label>{$tr['Bonus effective date range']}</label>
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
      <div class="col-12 rwd_doublerow_time">
        <div class="input-group">
          <div class=" input-group">
            <div class="input-group-prepend">
              <span class="input-group-text">{$tr['start']}</span>
            </div>
            <input type="text" class="form-control" name="bonus_validdatepicker_start" id="bonus_validdatepicker_start" placeholder="ex:2017-01-20" value="{$search_time['default_min_date']}{$search_time['min']}">
          </div>

          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text">{$tr['end']}</span>
            </div>
            <input type="text" class="form-control" name="bonus_validdatepicker_end" id="bonus_validdatepicker_end" placeholder="ex:2017-01-20" value="{$search_time['current']}{$search_time['max']}">
          </div>              
        </div>
      </div>
    </div>
    
    <input type ="hidden" id="yn_lotto" placeholder="是否為彩票" value="">
    <input type ="hidden" id="timepoint" placeholder="時間點" value="">
    <input type ="hidden" id="db_time" value="">
    <hr>
    <button id="submit_to_inquiry" class="btn btn-success btn-block js-query-receivemoney-btn" type="button">{$tr['Inquiry']}</button>
</form>
HTML;
//<button type="button" class="btn btn-primary js-query-receivemoney-btn">{$tr['Inquiry']}</button>
//<button type="button" class="ml-2 btn btn-primary js-clear_data-btn">{$tr['Emptied']}</button>
// ---------------------------------------------------------------------------
// 獎金明細列表
// ---------------------------------------------------------------------------
// $tr['Bonus number'] = '獎金編號';
// $tr['Recipient account'] = '領取者帳號';
// $tr['Bonus release time (US East Time)'] = '獎金發放時間(美東時間)';
// $tr['Bonus Time to lose efficacy(US East Time)'] = '獎金失效時間(美東時間)';
// $tr['Bonus collection time (US East Time)'] = '獎金領取時間(美東時間)';
// $tr['Bonus summary'] = '獎金摘要';
// $tr['Bonus category'] = '獎金類別';
// $tr['Current record status'] = '目前紀錄狀態';
// $tr['Manager'] = '管理者';
// $tr['Manage bonuses'] = '管理獎金';

  $table_colname_html = '
  <tr>
    <th><input name="select_all" id="show_list_selectall" type="checkbox" class="selectall"></th>
    <th>'. $tr['NUM'] .'</th> 
    <th>'. $tr['Account'] .'</th>
    <th>' . $tr['Franchise'] . '</th>
    <th>' . $tr['Gtoken'] . '</th>
    <th>'.$tr['Release time'].'</th>
    <th>'.$tr['invalid time'].'</th>
    <th>' . $tr['Bonus classification'] . '</th>    
    <th>' . $tr['admin'] . '</th>
    <th></th>
  </tr>
  ';

// $table_colname_html = '
//   <tr>
//     <th><input name="select_all" id="show_list_selectall" type="checkbox" class="selectall"></th>
//     <th>' . $tr['Bonus number'] . '</th>
//     <th>' . $tr['Recipient account'] . '</th>
//     <th>' . $tr['Franchise'] . '</th>
//     <th>' . $tr['Gtoken'] . '</th>
//     <th>' . $tr['Bonus release time (US East Time)'] . '</th>
//     <th>' . $tr['Bonus Time to lose efficacy(US East Time)'] . '</th>
//     <th>' . $tr['Bonus collection time (US East Time)'] . '</th>
//     <th>' . $tr['Bonus summary'] . '</th>
//     <th>' . $tr['Bonus category'] . '</th>
//     <th>' . $tr['Current record status'] . '</th>
//     <th>' . $tr['Manager'] . '</th>
//     <th>' . $tr['last operator'].'</th>
//     <th>' . $tr['Manage bonuses'] . '</th>
//     </tr>
//   ';
// 列出資料, 主表格架構
$show_list_html ='
  <form id="show_list_form" action="POST">
    <table id="show_list"  class="display" cellspacing="0" width="100%">
      <thead>
      ' . $table_colname_html . '
      </thead>
    </table>
  </from>
  ';

$panelbody_content.= $summary_menu;

// $indexbody_content.=<<<HTML
//   <div class="row">
//       <div class="col-12 col-md-12">
//           <a href="receivemoney_import.php" class="btn btn-success js-modal-trigger">{$tr['import into excel']}</a>
//       </div>
//       <div class="col-12 col-md-12">
//           {$query_bar_htm}
//       </div>

//       <div class="col-12 col-md-12" style="margin-bottom:10px;">
//         <h4>{$tr['details of bonus']}</h4>
//         <div style="float:right;">
//           <a href="receivemoney_management_detail.php?a=bonus_edit" title="{$tr['Add a single bonus can be issued']}" class="btn btn-primary" target="_BLANK"><span class="glyphicon glyphicon-plus-sign"><span></a>

//           <button class="btn btn-success" title="{$tr['Set the selected item to be able to receive']}" onclick="update_select('access');">{$tr['Set to receive']}</button>

//           <button class="btn btn-danger" title="{$tr['Set the selected item to cancel']}" onclick="update_select('deny');">{$tr['Set to cancel']}</button>

//           <button class="btn btn-warning" title="{$tr['Set the selected item to pause']}" onclick="update_select('cancel');">{$tr['Set to pause']}</button><br>

//           <span>({$tr['Here set only for the current page selection item']})</span>
//         </div>
//       </div>

//       <div class="col-12 col-md-12">
//           {$show_list_html}
//       </div>

//       <div class="col-12 col-md-12" id="sumtbl_nonlotto"></div>
//       <div class="col-12 col-md-12" id="sumtbl_nonlotto_pagination"></div>
//       <div class="col-12 col-md-12" id="sumtbl_islotto"></div>
//       <div class="col-12 col-md-12" id="sumtbl_islotto_pagination"></div>

//       </div>

//       <div class="row"><div id="preview_result"></div>
//   </div>

// HTML;

//設定可領取  取消  暫停 按鈕
$state_setting_html = <<<HTML
<div class="d-flex btn_group">		
		<button class="btn btn-info mr-2" title="{$tr['Set the selected item to be able to receive']}" onclick="update_select('access');" disabled>{$tr['Set to receive']}</button>
		<button class="btn btn_pink mr-2" title="{$tr['Set the selected item to cancel']}" onclick="update_select('deny');" disabled>{$tr['Set to cancel']}</button>
		<button class="btn btn-warning mr-2" title="{$tr['Set the selected item to pause']}" onclick="update_select('cancel');" disabled>{$tr['Set to pause']}</button><br>
</div>
HTML;

//頁面切換tab
$tab_html = <<<HTML
<div class="d-flex align-items-center ml-auto mb-2">
  <div class="border-right mr-2">{$state_setting_html}</div>
  <div class="border-right mr-2 pr-2 d-flex">
    <div id="export" class="d-flex"></div> 
    {$exportExcelBtn}
  </div>
  <a href="receivemoney_management_detail.php?a=bonus_edit" title="{$tr['Add a single bonus can be issued']}" class="btn btn-success"><span class="glyphicon glyphicon-plus mr-1"></span>{$tr['add bonus']}</a>
</div>
HTML;

$panelbody_content.=<<<HTML
<nav>
  <div class="nav nav-tabs" id="nav-tab" role="tablist">
    <a class="nav-item nav-link position-relative active" id="nav-home-tab" data-toggle="tab" href="#nav-home" role="tab" aria-controls="nav-home" aria-selected="true">
      {$tr['details of bonus']}
    <a class="nav-item nav-link position-relative" id="nav_nonlotto" data-frequency="false" data-toggle="tab" href="#nav-profile" role="tab" aria-controls="nav-profile" aria-selected="false">
      {$tr['Bonus classification']}
		</a>
		{$tab_html}
  </div>
</nav>

<ul class="color_description mb-0 mt-3">
		<li class="status-info-sp">
			<!-- 可領取 -->
			<button type="button" data-val="1" data-record="false">{$tr['Can receive']}</button>
		</li>
		<li class="status-muted-sp">
			<!-- 已领取 -->
			<button type="button" data-val="3" data-record="false">{$tr['received']}</button>
		</li>
		<li class="status-orange-sp">
			<!-- 已过期 -->
			<button type="button" data-val="4" data-record="false">{$tr['expired']}</button>
		</li>
		<li class="status-warning-sp">      
			<!-- 暫停 -->  
			<button type="button" data-val="2" data-record="false">{$tr['time out']}</button>
		</li>
		<li class="status-pink-sp">
			<!-- 取消 -->
			<button type="button" data-val="0" data-record="false">{$tr['Cancel']}</button>
		</li>
	</ul>

<div class="tab-content" id="nav-tabContent">
  <div class="tab-pane fade show active" id="nav-home" role="tabpanel" aria-labelledby="nav-home-tab" data-title="彩金明细">
			{$show_list_html}	
	</div>
  <div class="tab-pane fade" id="nav-profile" role="tabpanel" aria-labelledby="nav_nonlotto" data-title="非彩票类">
		<div id="sumtbl_nonlotto"></div>
		<div id="sumtbl_nonlotto_pagination"></div>
	</div>
  <div class="tab-pane fade" id="nav-contact" role="tabpanel" aria-labelledby="nav_islotto" data-title="彩票类别">
		<div id="sumtbl_islotto"></div>
		<div id="sumtbl_islotto_pagination"></div>
	</div>
</div>

	<div class="row"><div id="preview_result"></div>
</div>
HTML;

// $panelbody_content.=<<<HTML
// <nav>
//   <div class="nav nav-tabs" id="nav-tab" role="tablist">
//     <a class="nav-item nav-link position-relative active" id="nav-home-tab" data-toggle="tab" href="#nav-home" role="tab" aria-controls="nav-home" aria-selected="true">
// 			彩金明细
//     <a class="nav-item nav-link position-relative" id="nav_nonlotto" data-toggle="tab" href="#nav-profile" role="tab" aria-controls="nav-profile" aria-selected="false">
// 			非彩票类
// 		</a>
//     <a class="nav-item nav-link position-relative" id="nav_islotto" data-toggle="tab" href="#nav-contact" role="tab" aria-controls="nav-contact" aria-selected="false">
// 			彩票类别
// 		</a>
// 		{$tab_html}
//   </div>
// </nav>

// <ul class="color_description mb-0 mt-3">
// 		<li class="status-info-sp">
// 			<!-- 可領取 -->
// 			<button type="button" data-val="1" data-record="false">{$tr['Can receive']}</button>
// 		</li>
// 		<li class="status-muted-sp">
// 			<!-- 已领取 -->
// 			<button type="button" data-val="3" data-record="false">{$tr['received']}</button>
// 		</li>
// 		<li class="status-orange-sp">
// 			<!-- 已过期 -->
// 			<button type="button" data-val="4" data-record="false">{$tr['expired']}</button>
// 		</li>
// 		<li class="status-warning-sp">      
// 			<!-- 暫停 -->  
// 			<button type="button" data-val="2" data-record="false">{$tr['time out']}</button>
// 		</li>
// 		<li class="status-pink-sp">
// 			<!-- 取消 -->
// 			<button type="button" data-val="0" data-record="false">{$tr['Cancel']}</button>
// 		</li>
// 	</ul>

// <div class="tab-content" id="nav-tabContent">
//   <div class="tab-pane fade show active" id="nav-home" role="tabpanel" aria-labelledby="nav-home-tab" data-title="彩金明细">
// 			{$show_list_html}	
// 	</div>
//   <div class="tab-pane fade" id="nav-profile" role="tabpanel" aria-labelledby="nav_nonlotto" data-title="非彩票类">
// 		<div id="sumtbl_nonlotto"></div>
// 		<div id="sumtbl_nonlotto_pagination"></div>
// 	</div>
//   <div class="tab-pane fade" id="nav-contact" role="tabpanel" aria-labelledby="nav_islotto" data-title="彩票类别">
// 		<div id="sumtbl_islotto"></div>
// 		<div id="sumtbl_islotto_pagination"></div>
// 	</div>
// </div>

// 	<div class="row"><div id="preview_result"></div>
// </div>
// HTML;
// ---------------------------------------------------------------------------

$extend_head = $extend_head . <<<HTML
  <!-- 參考使用 datatables 顯示 -->
  <!-- https://datatables.net/examples/styling/bootstrap.html -->
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
  <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
  <script>

    // 下方總表-批次處理，設定截止日期
    function bonus_batchedit_d(data){
      var deadline_id_val=$("#"+data.dateinput).val();
      if(deadline_id_val==''){
          alert("{$tr['please confirm all fields has been filled in']}");
          return false;
      }else if(deadline_id_val < "{$current_datepicker}"){
          alert("{$tr['Deadline date is expired']}");
          return false;
      }

      blockscreengotoindex();
      if(data.tab_type=='islotto'){
          var givemoneytime='';
      }else{
          var givemoneytime=data.givemoneytime;
      }
      var url_str = "a=bonus_batchedit&a2="+data.action+"&c="+data.bonus_name+"&bons_givemoneytime="+givemoneytime+"&tab_type="+data.tab_type+"&bonus_status="+data.bat_status+"&is_querybutton="+data.querybuton+"&t="+deadline_id_val;

      $(".modal").modal('hide');
      $.get("receivemoney_management_action.php?"+url_str, function(result){
          // $("#show_list").DataTable().ajax.reload(null, false);
          $.unblockUI();
          if(data.querybuton=='0'){
            window.location.reload();
          }else{
            query_receivemoney(data.querybuton);
          }

      });
    };

    // 下方總表-批次處理
    function bonus_batchedit(data){
      blockscreengotoindex();
      if(data.tab_type=='islotto'){
          var givemoneytime='';
      }else{
          var givemoneytime=data.givemoneytime;
      }
      var url_str = "a=bonus_batchedit&a2="+data.action+"&c="+data.bonus_name+"&bons_givemoneytime="+givemoneytime+"&tab_type="+data.tab_type+"&bonus_status="+data.bat_status+"&is_querybutton="+data.querybuton;
      $(".modal").modal('hide');

      $.get("receivemoney_management_action.php?"+url_str, function(result){
          $.unblockUI();
          if(data.querybuton=='0'){
            window.location.reload();
          }else{
            query_receivemoney(data.querybuton);
          }
      });

    }


    // 選單頁-批次領取
    function batched_receive(query_datas){
      var show_text = '即将批次领取 '+String(query_datas.prizecategories)+' 的彩金...';
      var updating_img = '批次领取中...<img width="20px" height="20px" src="ui/loading.gif"/>';
      if(query_datas.ynloto=='0'){
          var updatingcodeurl = 'receivemoney_management_action.php?a=batched_receive&prizecategories='+ query_datas.prizecategories+'&bons_givemoneytime='+ query_datas.bons_givemoneytime+'&tab_type=nonlotto';
      }else{
          var updatingcodeurl = 'receivemoney_management_action.php?a=batched_receive&prizecategories='+ query_datas.prizecategories+'&tab_type=islotto';
      }

      if(confirm(show_text)){
        $("#update_"+query_datas.i).html(updating_img);
        myWindow = window.open(updatingcodeurl, 'gpk_window', 'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800', false);
        myWindow.focus();
        setTimeout(function(){location.reload();},3000);
      }
    }

    function get_parameter(){
        var bonus_type = $("#bonus_type").val();
        var bons_givemoneytime = $("#bons_givemoneytime").val();
        var bonus_status = $("#bonus_status").val();
        var member_account = $("#member_account").val();
        var bonus_validdatepicker_start = $("#bonus_validdatepicker_start").val();
        var bonus_validdatepicker_end = $("#bonus_validdatepicker_end").val();
        var timepoint = $("#timepoint").val();
        // 存入db的時間，因有些資料存入的時間有小數點，因此無法顯示
        var db_time = $("#db_time").val();

        var query_str = '&bonus_type='+bonus_type+
                        '&bons_givemoneytime='+bons_givemoneytime+
                        '&bonus_status='+bonus_status+
                        '&member_account='+member_account+
                        '&bonus_validdatepicker_start='+bonus_validdatepicker_start+
                        '&bonus_validdatepicker_end='+bonus_validdatepicker_end+
                        '&timepoint='+timepoint+

                        '&db_time='+db_time;
        return query_str
    }

    // // 下方總表樣版
    // function sum_template(data,tab_type,modals){
    //   // 非彩票
    //     var sum_not_lotto_htm=`
    //         <hr>
    //         <h4>{$tr['non']}{$tr['lottosum']}{$tr['category']}</h4>
    //         <table class="table table-striped">
    //           <tbody>
    //             <tr>
    //                 <th class="text-left">{$tr['Bonus category']}</th>
    //                 <th class="text-center">{$tr['Release time']}</th>
    //                 <th class="text-center">{$tr['franchise sum']}</th>
    //                 <th class="text-center">{$tr['Sum of cash']}</th>
    //                 <th class="text-center">{$tr['number of people']}</th>
    //                 <th class="text-center">{$tr['payout status']}</th>
    //                 <th class="text-center">{$tr['Remark']}</th>
    //             </tr>
    //         `;
          
    //     // 彩票
    //     var sum_is_lotto_htm=`
    //         <hr>
    //         <h4>{$tr['lottosum']}{$tr['category']}</h4>
    //         <table class="table table-striped">
    //           <tbody>
    //             <tr>
    //                 <th class="text-left">{$tr['Bonus category']}</th>
    //                 <th class="text-center">{$tr['franchise sum']}</th>
    //                 <th class="text-center">{$tr['Sum of cash']}</th>
    //                 <th class="text-center">{$tr['number of people']}</th>
    //                 <th class="text-center">{$tr['payout status']}</th>
    //                 <th class="text-center">{$tr['Remark']}</th>
    //             </tr>
    //         `;
    //     if(tab_type=="nonlotto"){
    //       var html = sum_not_lotto_htm+data+`</tbody></table>`+modals;
    //     }else if (tab_type=="islotto") {
    //       var html = sum_is_lotto_htm+data+`</tbody></table>`+modals;
    //     };

    //     return html;


    // }
    //仿datatble無資料
    function nodata_template(tab_type){
      // 非彩票
      var nodata_not_lotto_htm=`
            <table class="display dataTable no-footer mt-3 table_tdbg">
              <thead>
                <tr>
                  <th class="text-left">{$tr['Bonus classification']}</th>
                  <th class="text-center">{$tr['Release time']}</th>
                  <th class="text-center">{$tr['franchise sum']}</th>
                  <th class="text-center">{$tr['Sum of cash']}</th>
                  <th class="text-center">{$tr['number of people']}</th>
                  <th class="text-center">{$tr['Bonus status']}</th>
                  <th class="text-center">{$tr['Remark']}</th>
                </tr>
              </thead>
              <tbody>  
                <tr class="odd"><td valign="top" colspan="10" class="dataTables_empty">{$tr['no_data']}</td></tr>        
            `;
          
        // 彩票
        var nodata_is_lotto_htm=`
            <table class="display dataTable no-footer mt-3 table_tdbg">
              <thead>
                <tr>
                  <th class="text-left">{$tr['Bonus classification']}</th>
                  <th class="text-center">{$tr['franchise sum']}</th>
                  <th class="text-center">{$tr['Sum of cash']}</th>
                  <th class="text-center">{$tr['number of people']}</th>
                  <th class="text-center">{$tr['Bonus status']}</th>
                  <th class="text-center">{$tr['Remark']}</th>
                </tr>
              </thead>
              <tbody>  
                <tr class="odd"><td valign="top" colspan="10" class="dataTables_empty">{$tr['no_data']}</td></tr>
            `;

            if(tab_type=="nonlotto"){
              var html = nodata_not_lotto_htm+`</tbody></table>`;
            }else if (tab_type=="islotto") {
              var html = nodata_is_lotto_htm+`</tbody></table>`;
            };

            return html;
    }
    // 下方總表樣版
    function sum_template(data,tab_type,modals){
      // 非彩票
        var sum_not_lotto_htm=`
            <table class="display dataTable no-footer mt-3 table_tdbg">
              <thead>
                <tr>
                  <th class="text-left">{$tr['Bonus classification']}</th>
                  <th class="text-center">{$tr['Release time']}</th>
                  <th class="text-center">{$tr['franchise sum']}</th>
                  <th class="text-center">{$tr['Sum of cash']}</th>
                  <th class="text-center">{$tr['number of people']}</th>
                  <th class="text-center">{$tr['Bonus status']}</th>
                  <th class="text-center">{$tr['Remark']}</th>
                </tr>
              </thead>
              <tbody>            
            `;
          
        // 彩票
        var sum_is_lotto_htm=`
            <table class="display dataTable no-footer mt-3 table_tdbg">
              <thead>
                <tr>
                  <th class="text-left">{$tr['Bonus classification']}</th>
                  <th class="text-center">{$tr['franchise sum']}</th>
                  <th class="text-center">{$tr['Sum of cash']}</th>
                  <th class="text-center">{$tr['number of people']}</th>
                  <th class="text-center">{$tr['Bonus status']}</th>
                  <th class="text-center">{$tr['Remark']}</th>
                </tr>
              </thead>
              <tbody>
            `;
        if(tab_type=="nonlotto"){
          var html = sum_not_lotto_htm+data+`</tbody></table>`+modals;
          //console.log(data);
        }else if (tab_type=="islotto") {
          var html = sum_is_lotto_htm+data+`</tbody></table>`+modals;
        };

        return html;


    }
    // 彩金下方總表，分頁查詢 START--------------------
    function loadData_summary(tab_type,page,is_querybutton) {
        loading_show(tab_type);
        var query_str=get_parameter();
        var export_excel = "{$tr['Export Excel']}";
        $("#export").empty();
        var link= `<a id="id_href" class="btn btn-primary disabled btn-sm mr-2 text-white" style="display:none"><span>`+export_excel+`</span></a>`;
        $("#export").append(link);

        $.ajax
            ({
              type: "POST",
              url: "receivemoney_management_action.php?a=query_summary"+query_str,
              datatype:"json",
              data: ({
                  "is_querybutton":is_querybutton,
                  "pageNumber": page,
                  "tab_type": tab_type,
                  "pageSize":10
              }),
              success: function (result) {
                  if(result.errorlog == ''){

                    // -----------------------
                    // 匯出excel
                    refresh(result.download_url);
                    // -----------------------
                    var datahtml = sum_template(result.source,tab_type,result.modals);
                    $("#sumtbl_"+tab_type).html(datahtml);
                    $("#sumtbl_"+tab_type+"_pagination").html(result.page_tool);
                  }else{
                    var nodatahtml = nodata_template(tab_type);
                    $("#sumtbl_"+tab_type).html(nodatahtml);
                    $("#sumtbl_"+tab_type+"_pagination").html('');
                  }
              }
            });
    }

    // 表格內，點帳號、獎金類別查詢
    function sub_query_str(name="",value=""){

      if(name=="account"){
        $("#bonus_type").val("");
        $("#member_account").val(value);
      }

      if(name=="bonustype"){
        $("#member_account").val("");
        $("#bonus_type").val(value);
      }
      // 按下查詢鈕
      $( ".js-query-receivemoney-btn" ).click();
    }

    // 按搜尋後才會執行
    // 獎金資料表顯示
    // 查詢產生結果
    function query_receivemoney(is_querybutton='0',search){
      //console.log('search1:' + search);
      if ( search == undefined ) {
        var bons_givemoneytime = $("#bons_givemoneytime").val();
        console.log('search1:' + search);
      }
      if ( search == true ) {
        var bons_givemoneytime = $("#bons_givemoneytime").val('');
        console.log('search6:' + search);
      }

      var bonus_status = $("#bonus_status").val();
      
      //同步右邊狀態按鈕
      $(".color_description li").removeClass('bg-light');
      $(".color_description li button").attr('data-record',false);
      $(".color_description").find("[data-val='" + bonus_status + "']").parent().addClass('bg-light');
      //重複 click 判斷 
      $(".color_description").find("[data-val='" + bonus_status + "']").attr('data-record',true);

      var bonus_type = $("#bonus_type").val();     
      var member_account = $("#member_account").val();
      var bonus_validdatepicker_start = $("#bonus_validdatepicker_start").val();
      var bonus_validdatepicker_end = $("#bonus_validdatepicker_end").val();
      var timepoint = $("#timepoint").val();

      // 存入db的時間，因有些資料存入的時間有小數點，因此無法顯示
      var db_time = $("#db_time").val();

      var query_str= '&bonus_type='+bonus_type+
                      '&bons_givemoneytime='+bons_givemoneytime+
                      '&bonus_status='+bonus_status+
                      '&member_account='+member_account+
                      '&bonus_validdatepicker_start='+bonus_validdatepicker_start+
                      '&bonus_validdatepicker_end='+bonus_validdatepicker_end+
                      '&timepoint='+timepoint+
                      '&db_time='+db_time;

      // console.log(query_str);

      var start = new Date(bonus_validdatepicker_start.replace(/\-/g, "/"));
      var end = new Date(bonus_validdatepicker_end.replace(/\-/g, "/"));

      //2個月前
      var previous = new Date();
      previous.setMonth(previous.getMonth()-2);
      var previous_year = previous.getFullYear();
      var previous_month = previous.getMonth()+1;
      var previous_date = previous.getDate()-1;
      var previous_hours = previous.getHours();
      var previous_minutes = previous.getMinutes();
      
      var next = new Date();
      var year = next.getFullYear();
      var month = next.getMonth()+1;
      var date = next.getDate()+1;
      var hours = next.getHours();
      var minutes = next.getMinutes();

      // 最小搜尋時間
      var minDateTime = previous_year+'-'+previous_month+'-'+previous_date+' '+previous_hours+':'+previous_minutes;//+'00:00';
      // 最大搜尋時間
      var maxDateTime = year +'-'+ month +'-'+ date + ' ' + hours + ':' + minutes;

      // 開始時間<最小搜尋時間
      if((Date.parse(start)).valueOf() < (Date.parse(minDateTime)).valueOf()){
        alert('开始时间错误，请修改查询区间!');
        window.location.reload();
        return false;
      }
      // 結束時間>最大搜尋時間
      /*
      if((Date.parse(end)).valueOf() > (Date.parse(maxDateTime)).valueOf()){
        alert('结束时间错误，请修改查询区间!');
        window.location.reload();
        return false;
      }*/
      // 開始時間>結束時間
      if((Date.parse(start)).valueOf() > (Date.parse(end)).valueOf()){
        alert('开始时间不能大于结束时间');
        window.location.reload();
        return false;
      }
      if((Date.parse(end)).valueOf() < (Date.parse(start)).valueOf()){
        alert('结束时间不能小于开始时间');
        window.location.reload();
        return false;
      }

      // 彩金明細
      $("#show_list").DataTable()
        .ajax.url("receivemoney_management_action.php?a=query_receivemoney"+query_str)
        .load();
      var yn_lotto = $("#yn_lotto").val();
   

      //-------------------------------------------------
      // download xlsx 
      $("#export").empty();
      var export_excel = "{$tr['Export Excel']}";
      var link= `<a id="id_href" class="btn btn-primary disabled mr-2 text-white" style="display:none"><span>`+export_excel+`</span></a>`;
      $("#export").append(link);

      $.get(
        "receivemoney_management_action.php?a=query_receivemoney"+query_str,
        function(result) {
          if(result.data == ''){

          }else{
            //refresh(result.download_url);
          }
        },
        'json'
      );
      // --------------------------------------------

      // 清空彩票及非彩票表格
      $("#sumtbl_nonlotto").html('');
      $("#sumtbl_nonlotto_pagination").html('');
      $("#sumtbl_islotto").html('');
      $("#sumtbl_islotto_pagination").html('');

      switch (yn_lotto) {
        case '0':
          // 非彩票類總結表格
          loadData_summary('nonlotto',1,is_querybutton);
          break;
        case '1':
          // 生成彩票類總結表格
          loadData_summary('islotto',1,is_querybutton);
          break;
        default:
          loadData_summary('nonlotto',1,is_querybutton);
          loadData_summary('islotto',1,is_querybutton);
      }
    }

    // 匯出excel  彩金明細
    function refresh(url){
      $("#export").empty();
      var export_excel = "{$tr['Export Excel']}";
      var link= `<a id="id_href" class="btn btn-primary mr-2 text-white" href="`+url+`" target="_blank" title="开发中"><span>`+export_excel+`</span></a>`;
      $("#export").append(link);
    }


    // 選單頁樣板 (左上)
    function Templating(data,tab_type) {
        var not_lotto_htm=`
            <table class="table table-bordered small" style="table-layout:fixed;word-wrap:break-word;">
                <thead>
                  <tr class="active">
                    <th style="width:130px;text-align:center;">{$tr['Bonus classification']}</th>
                    <th style="width:90px;text-align:center;">{$tr['Release time']}</th>
                    <th style="width:50px;text-align:center;">{$tr['franchise sum']}</th>
                    <th style="width:60px;text-align:center;">{$tr['Sum of cash']}</th>
                    <th style="width:40px;text-align:center;">{$tr['number of people']}</th>
                    <th style="width:50px;text-align:center;">{$tr['Remark']}</th>
                  </tr>
                </thead>
                <tbody>`;
        var is_lotto_htm=`
            <table class="table table-bordered small" style="table-layout:fixed;word-wrap:break-word;">
                <thead>
                  <tr class="active">
                    <th style="width:130px;text-align:center;">{$tr['Bonus classification']}</th>
                    <th style="width:50px;text-align:center;">{$tr['franchise sum']}</th>
                    <th style="width:60px;text-align:center;">{$tr['Sum of cash']}</th>
                    <th style="width:40px;text-align:center;">{$tr['number of people']}</th>
                    <th style="width:50px;text-align:center;">{$tr['Remark']}</th>
                  </tr>
                </thead>
                <tbody>`;

        if(tab_type=="can_receive"){
          var html = not_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="timeout") {
          var html = not_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="cancel") {
          var html = not_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="received") {
          var html = not_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="expired") {
          var html = not_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="lottosum_canreceive") {
          var html =is_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="lottosum_timeout") {
          var html =is_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="lottosum_cancel") {
          var html =is_lotto_htm+data+`
                </tbody>
            </table>
            `;

        }else if (tab_type=="lottosum_received") {
          var html =is_lotto_htm+data+`
                </tbody>
            </table>
            `;
        }else if (tab_type=="lottosum_expired") {
          var html =is_lotto_htm+data+`
                </tbody>
            </table>
            `;
        };
        return html;
    };

    // 選單頁樣板 (左上) 無資料 HTML
    function Templatingnodata() {
        var html=`
            <table class="table table-bordered small" style="table-layout:fixed;word-wrap:break-word;">
                <thead>
                  <tr class="active">
                    <th style="width:130px;text-align:center;">{$tr['Bonus classification']}</th>
                    <th style="width:90px;text-align:center;">{$tr['Release time']}</th>
                    <th style="width:50px;text-align:center;">{$tr['franchise sum']}</th>
                    <th style="width:60px;text-align:center;">{$tr['Sum of cash']}</th>
                    <th style="width:40px;text-align:center;">{$tr['number of people']}</th>
                    <th style="width:50px;text-align:center;">{$tr['Remark']}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="6" class="text-center">{$tr['no_data']}</td>
                  </tr>
                </tbody>
                </table>
        `;
        return html;
    };

    // 選單頁載入過場
    function loading_show(tab_type) {
        if(tab_type=='nonlotto' || tab_type=='islotto'){
            $("#sumtbl_"+tab_type).html('<h3 align="center" class="align-middle">...<img width="40px" height="40px" src="ui/loading.gif"/>...</h3>').fadeIn('fast');
        }else{
            $("#"+tab_type+"_data").html('<h3 align="center" class="align-middle">...<img width="40px" height="40px" src="ui/loading.gif"/>...</h3>').fadeIn('fast');
        }
    }

    // 選單頁，分頁查詢 START--------------------
    function loadData(tab_type,page) {
        loading_show(tab_type);
        $.ajax
            ({
              type: "POST",
              url: "receivemoney_management_action.php?a=tab_prmt"+get_parameter(),
              datatype:"json",
              data: ({
                  "pageNumber": page,
                  "tab_type": tab_type,
                  "pageSize":10
              }),
              success: function (msg) {
                  var result=JSON.parse(msg);
                  var datahtml = Templating(result.source,tab_type);
                  var nodata = Templatingnodata();

                  $("#"+tab_type+"_data").html(datahtml);
                  $("#"+tab_type+"_pagination").html(result.page_tool);

                  if ( result.source == '' ) {
                    $("#"+tab_type+"_data").html(nodata);
                    $("#"+tab_type+"_pagination").html('');
                  }
              }
            });
    }

    // Handle form submission event
    function update_select(key){      
      var id = $("#show_list_form").serialize();
      var inputid = id.split('&');
      // console.log('id:' + id);
      // console.log('inputid:' + inputid.length);
      // console.log(key);
      if ( inputid.length > 1 ){
        blockscreengotoindex();
        $.post("receivemoney_management_action.php?a=bonus_batchedit&a2=checked&status="+key,id,
          function(result){
            // Uncheck
            $(":checkbox[name=\"select_all\"]").prop("checked", false);
            $("#show_list").DataTable().ajax.reload(null, false);
            alert(result);
            $.unblockUI();
        });
      }else{
        alert('未选取任何项目');
      }
    }


</script>
HTML;


$extend_js=$extend_js.<<<HTML
  <style>
    #show_list thead tr th:nth-of-type(1){
      border-left: 10px solid #1b1b1b00;
      border-left-width: .5rem;
    }
    td.ellipsis{
      overflow: hidden;
      text-overflow: ellipsis;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      white-space: normal;
      line-height: 2em;
    }
    select.form-control:not([size]):not([multiple]){
      width: 68px;
      height: 28px;
    }
    #show_list_length > label{
      align-items: center;
    }
  </style>
  <script>
    function getnowtime(){
      var NowDate = moment().tz('America/St_Thomas').format('YYYY-MM-DD')+ ' 23:59';
      return NowDate;
    }
    // 本日、昨日、本周、上周、上個月button
    function settimerange(sdate,edate,text){
    $("#bonus_validdatepicker_start").val(sdate);
    $("#bonus_validdatepicker_end").val(edate);

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
    
    //伸縮內文 datatable 用
    function format ( d ) {
    //可领取
    if ( d.status == "{$tr['Can receive']}" ) {
      var point = '<span class="status-info-sp"></span>';
    //已领取
    }else if ( d.status == "{$tr['received']}" ) {
      var point = '<span class="status-muted-sp"></span>';
    //已过期
    }else if ( d.status == "{$tr['expired']}" ) {
      var point = '<span class="status-orange-sp"></span>';
    //暂停
    }else if ( d.status == "{$tr['time out']}" ) {
      var point = '<span class="status-warning-sp"></span>';
    //取消
    }else if ( d.status == "{$tr['Cancel']}" ) {
      var point = '<span class="status-pink-sp"></span>';
    }else{
      var point = '';
    }
    // 領取時間 null
    if ( d.receivetime == null ) {
      var vreceivetime = ''
    }else{
      var vreceivetime = d.receivetime
    }
    // 操作 null
    if ( d.last_modify_member_account == null ) {
      var vlast_modify_member_account = ''
    }else{
      var vlast_modify_member_account = d.last_modify_member_account
    }
    return "<ul class='receivemoney_listopen'>"+
             "<li><h3>{$tr['Bonus status']}:</h3>"+ point + d.status +"</li>"+
             "<li class='w-25'><h3>{$tr['Summary']}:</h3>"+d.summary+"</li>"+
             "<li><h3>{$tr['Receive time']}:</h3>"+vreceivetime+"</li>"+
             "<li><h3>{$tr['admin']}:</h3>"+d.givemoney_member_account+"</li>"+
             "<li><h3>{$tr['operation']}:</h3>"+vlast_modify_member_account+"</li>"+             
             "</ul>"
            
  }

    $(document).ready(function() {

        var table = $("#show_list").DataTable({
            "dom": '<tipl>',
            "oLanguage": {
              "sEmptyTable": "{$tr['no_data']}",//"目前没有资料!",
              "sLengthMenu": "{$tr['display']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
              "sInfo": "{$tr['Display']} _START_ {$tr['to']} _END_ {$tr['result']},{$tr['total']} _TOTAL_ {$tr['item']}",//"显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
              "sInfoFiltered": "({$tr['from']} _MAX_ {$tr['filtering in data']})"//"(由 _MAX_ 项结果过滤)"
            },
            "bProcessing": true,
            "bServerSide": true,
            "bRetrieve": true,
            "searching": false,
            "aaSorting": [[ 1, "desc" ]],
            "ajax": "receivemoney_management_action.php?a=query_receivemoney"+get_parameter(),
            "createdRow": function( row, data, dataIndex ) {
              $(row).attr('data-toggle','tooltip');
              $(row).attr('data-placement','top');   
              $(row).attr("title","{$tr['State']} : " + data.status);           
              if ( data.status == "{$tr['Can receive']}" ) {
                $(row).addClass( 'status-success' );
              }else if ( data.status == "{$tr['received']}" ) {
                $(row).addClass( 'status-muted' );
              }else if ( data.status == "{$tr['expired']}" ) {
                $(row).addClass( 'status-orange' );
              }else if ( data.status == "{$tr['time out']}" ) { 
                $(row).addClass( 'status-warning' );
              }else if ( data.status == "{$tr['Cancel']}" ) {
                $(row).addClass( 'status-pink' );
              }
            },
            "columnDefs": [
              { "targets": 0,
                "searchable":false,
                "orderable":false,
                "className": "dt-body-center",
                "render": function (nTd, sData, oData, iRow, iCol){
                    return '<input type="checkbox" class="checkinput" name="id[]" value="' + $("<div/>").text(oData.id).html() + '">';
              }
            }],
            "columns": [
              { "data": "id","orderable":false,"width": "15px"},
              { "data": "id","className": 'details-control'},
              { "data": "member_account","fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<a href=\"javascript:sub_query_str('account','"+oData.member_account+"')\" data-role=\"button\" >"+oData.member_account+"</a>");}
              },
              { "data": "gcash_balance","className": 'details-control'},
              { "data": "gtoken_balance","className": 'details-control'},
              { "data": "givemoneytime","className": 'details-control'},
              { "data": "receivedeadlinetime","className": 'details-control'},
              // { "data": "receivetime"},
              // { "data": "summary","width": "20%","className": "ellipsis"},
              { "data": "prizecategories", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\"javascript:sub_query_str('bonustype','"+oData.prizecategories+"')\" data-role=\"button\" >"+oData.prizecategories+"</a>");}
              },
              // { "data": "status"},
              // { "data": "givemoney_member_account"},
              // { "data": "last_modify_member_account"},
              { "data": "moredetail","orderable":false,"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<center><a href=\"receivemoney_management_detail.php?a=bonus_edit&d="+oData.id+"\" class=\"btn-sm btn-primary\" target=\"_BLANK\" title=\"{$tr['More info']}\"><span class=\"glyphicon glyphicon-cog\"></span></a></center>");
                  }
              },
              {
                "className":      'details-control show_btn',
                "orderable":      false,
                "data":           null,
                "defaultContent": '',
                "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                  $(nTd).html("<i class=\"fas fa-angle-down\"></i>");
                }
              },
              ],
              "fnDrawCallback": function (oSettings) {
                $('.checkinput').on('click',function(){
                  if( $('.checkinput:checked').length > 0 ){
                    $('.btn_group button').attr('disabled', false);
                  }
                  if( $('.checkinput:checked').length < 1 ){
                    $('.btn_group button').attr('disabled', true);
                  }
                });
              }
        });

        //全選效果
        $('#show_list_selectall').on('change',function(){
          if( $('#show_list_selectall:checked').length == 1 ){
            $('.btn_group button').attr('disabled', false);
          }
          if( $('#show_list_selectall:checked').length == 0 ){
            $('.btn_group button').attr('disabled', true);
          }
        });

        // Add event listener for opening and closing details
        //點擊欄位開啟 彩金明細
        $('#show_list tbody').on('click', 'tr td.details-control', function () {
          var tr = $(this).closest('tr');
          var row = table.row( tr );

          if ( row.child.isShown() ) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass('shown');
            tr.find('.show_btn i').removeClass('turn_open');
            //console.log(tr);
          }
          else {
          // Open this row
            row.child( format(row.data()) ).show();
            tr.addClass('shown');
            $('.shown .show_btn i').addClass('turn_open');
            //console.log(tr);
          }
        });      

      //click 執行分類查詢tab 只執行第一次點
      $("#nav_nonlotto").click(function(e){
        var frequency = $(this).attr('data-frequency');
        if ( frequency == 'false' ){
          query_receivemoney('1',true);
          $(this).attr('data-frequency','true');
        }
      });      

        //顏色提示狀態 點擊執行篩選功能
      $('.color_description li button').click(function(){
        var val = $(this).attr('data-val');
        //紀錄是否被重複click
        var record = $(this).attr('data-record');
        //被重複click
        if ( record == 'true' ) {
          $('.color_description li').removeClass('bg-light');
          $(this).attr('data-record',false);
          $('#bonus_status').val('');
          query_receivemoney('1',true);
        }
        //沒有被重複click
        if ( record == 'false' ) {
          $('.color_description li').removeClass('bg-light');
          $(this).parent().addClass('bg-light');
          $('#bonus_status').val(val);
          $('#show_list_selectall').attr('checked',false);
          $('.btn_group button').attr('disabled', true);
          $('.color_description li button').attr('data-record',false);
          $(this).attr('data-record',true);
          query_receivemoney('1',true);
        }
      });


        // 按下查詢鈕及清空鈕之行為----------START----------------------
        $(".js-query-receivemoney-btn").on("click", function(e){
          e.preventDefault();
          query_receivemoney('1',true);
        });

        

        $(".js-clear_data-btn").on("click", function(e){
          $("#bonus_type").val("");
          $("#bons_givemoneytime").val("");
          $("#bonus_status").val("");
          $("#member_account").val("");
          $("#bonus_validdatepicker_start").val("");
          $("#bonus_validdatepicker_end").val("");
          $("#yn_lotto").val("");
          $("#timepoint").val("");
        });
        // 按下查詢鈕及清空鈕之行為----------END-------------------------


        // ------------------------------------------------------------------
        
        // 彩金有效區間及之 datepicker-----START---------------------------------------
        // $("#bonus_validdatepicker_start,#bonus_validdatepicker_end").datepicker({
        //   showButtonPanel: true,
        //   dateFormat: "yy-mm-dd",
        //   changeMonth: true,
        //   changeYear: true,
        //   minDate : "-2m",
        //   maxDate : "+0d"
        // });
        $("#bonus_validdatepicker_start").datetimepicker('destroy');
        $("#bonus_validdatepicker_start").datetimepicker({
            showButtonPanel: true,
            changeMonth: true,
            changeYear: true,
            minDate:'{$search_time['two_month']}',
            //maxDate: '{$current_datepicker}',
            //defaultDate:'{$default_min_date}',
            timepicker: true,
            initTime: '00:00',
            format: 'Y-m-d H:i',
            step:1
        });
        $("#bonus_validdatepicker_end").datetimepicker({
            showButtonPanel: true,
            changeMonth: true,
            changeYear: true,
            //maxDate: '{$current_datepicker}',
            timepicker: true,
            initTime: '23:59',
            format: "Y-m-d H:i",
            step:1
        });

        $("#bonus_validdatepicker_start,#bonus_validdatepicker_end,#member_account,#bonus_status,#bonus_type").on('click',function(){
          $("#export").empty();
          var export_excel = "{$tr['Export Excel']}";
          var link= `<a id="id_href" class="btn btn-primary btn-sm disabled mr-2 text-white" style="display:none"><span>`+export_excel+`</span></a>`;
          $("#export").append(link);
        })
        // 彩金有效區間之 datepicker-----END-------------------------------

      // 彩金明細按下全選 START-------------------------------------------------------------
        // Handle click on \"Select all\" control
        $(":checkbox.selectall").on("click", function(){
          // Get all rows with search applied
          var rows = table.rows({ "search": "applied" }).nodes();
          // Check/uncheck checkboxes for all rows in the table
          $(":checkbox[name='id[]']").prop("checked", this.checked);
          $(":checkbox[name='select_all']").prop("checked", this.checked);
        });

        // Handle click on checkbox to set state of \"Select all\" control
        $("#show_list tbody").on("change", ":checkbox[name=\"id\[\]\"]", function(){
          // If checkbox is not checked
          if(!this.checked){
            var el = $("#show_list_selectall").get(0);
            // If \"Select all\" control is checked and has "indeterminate" property
            if(el && el.checked && ("indeterminate" in el)){
                // Set visual state of \"Select all\" control
                // as "indeterminate"
                el.indeterminate = true;
            }
          }
        });


      // 彩金明細按下全選 END---------------------------------------------------------------

        // 總結列表，彩票分頁查詢 START--------------------
        $('#sumtbl_islotto_pagination').on('click','.enab', function (e) {
            var page = $(this).attr('p'); // 頁碼
            var type = $(this).attr('attr_type'); // 非彩票nonlotto或彩票islotto
            var isquery = $(this).attr('attr_isquery');
            loadData_summary(type,page,isquery);

        });

        $('#sumtbl_islotto_pagination').on('click','.go_button',function(e){
            var type = $(this).attr('attr_type'); // 
            var isquery = $(this).attr('attr_isquery');
            var page = parseInt($(".goto_"+type).val());
            var total_pages = parseInt($(".total_"+type).attr('a'));
            if(page != 0 && page <= total_pages){
                loadData_summary(type,page,isquery);
            }else{
                alert('Enter a PAGE between 1 and '+total_pages);
                $('.goto').val("").focus();
                return false;
            }
        });
        // 總結列表，彩票分頁查詢 END--------------------
        
        // 總結列表，非彩票分頁查詢 START--------------------
        $('#sumtbl_nonlotto_pagination').on('click','.enab', function (e) {
            var page = $(this).attr('p');
            var type = $(this).attr('attr_type');
            var isquery = $(this).attr('attr_isquery');
            loadData_summary(type,page,isquery);

        });

        $('#sumtbl_nonlotto_pagination').on('click','.go_button',function(e){
            var type = $(this).attr('attr_type');
            var isquery = $(this).attr('attr_isquery');
            var page = parseInt($(".goto_"+type).val());
            var total_pages = parseInt($(".total_"+type).attr('a'));
            if(page != 0 && page <= total_pages){
                loadData_summary(type,page,isquery);
            }else{
                alert('Enter a PAGE between 1 and '+total_pages);
                $('.goto').val("").focus();
                return false;
            }
        });
        // 總結列表，非彩票分頁查詢 START--------------------


        // 選單頁，分頁查詢 START----------------------
        const load_menu_tab=[
            'can_receive',        'timeout',         'cancel',         'received',         'expired',
            'lottosum_canreceive','lottosum_timeout','lottosum_cancel','lottosum_received','lottosum_expired'];
        load_menu_tab.forEach(function(item,index,array){
          loadData(item,1);
        });

        // click menu pagination button
        $('.tab-content').on('click','.enab', function (e) {
            var page = $(this).attr('p');
            var type = $(this).attr('attr_type');
            loadData(type,page);
        });

        // click menu goto button
        $('.tab-content').on('click','.go_button',function(e){
            var type = $(this).attr('attr_type');
            var page = parseInt($(".goto_"+type).val());
            var total_pages = parseInt($(".total_"+type).attr('a'));
            if(page != 0 && page <= total_pages){
                loadData(type,page);
            }else{
                alert('Enter a PAGE between 1 and '+total_pages);
                $('.goto').val("").focus();
                return false;
            }
        });
      // 選單頁，分頁查詢 END----------------------
     });


     $(function(){
        // Create jQuery body object
        var \$body = $('body'),

        // Use a tags with 'class="modalTrigger"' as the triggers
        \$modalTriggers = $('a.js-modal-trigger'),
        // Trigger event handler
        openModal = function(evt) {
          var \$trigger = $(this),               // Trigger jQuery object

          modalPath = \$trigger.attr('href'),    // Modal path is href of trigger

          \$newModal,                     // Declare modal variable
          removeModal = function(evt) {         // Remove modal handler
            \$newModal.off('hidden.bs.modal');   // Turn off 'hide' event
            \$newModal.remove();                 // Remove modal from DOM
          },

          showModal = function(data) {                   // Ajax complete event handler
            // console.log(data);
            \$body.append(data);                          // Add to DOM
            \$newModal = $('.modal').last();              // Modal jQuery object
            \$newModal.modal('show');                     // Showtime!
            \$newModal.on('hidden.bs.modal',removeModal); // Remove modal from DOM on hide
          };

          $.get(modalPath,showModal);               // Ajax request

          evt.preventDefault();                     // Prevent default a tag behavior
        };

        \$modalTriggers.on('click',openModal);       // Add event handlers
      });


  </script>


HTML;




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
// include("template/beadmin.tmpl.php");
// include "template/beadmin_fluid.tmpl.php";
include "template/s2col.tmpl.php";

?>