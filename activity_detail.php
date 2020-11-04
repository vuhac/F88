<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 優惠碼詳細列表
// File Name:	activity_detail.php
// Author:    Mavis
// Related:
// DB Table: root_promotion_activity,root_promotion_code
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 文字檔
require_once dirname(__FILE__) ."/activity_management_editor_lib.php";

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
$function_title 		= $tr['detail of promo code'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['profit and promotion']}</a></li>
  <li><a href="activity_management.php">{$tr['prmotional code']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;
// ----------------------------------------------------------------------------


if(isset($_GET['s']) && $_GET['s'] != NULL){
  $id = filter_var($_GET['s'],FILTER_SANITIZE_NUMBER_INT);
}else{
  header('Location:./home.php');
  die();
}

if($_SESSION['agent']->therole == 'R') {
	$extend_head=<<<HTML
		<!-- Jquery UI js+css  -->
        <script src="in/jquery-ui.js"></script>
        <link rel="stylesheet"  href="in/jquery-ui.css" >
        <!-- Jquery blockUI js  -->
        <script src="./in/jquery.blockUI.js"></script>
        <!-- Datatables js+css  -->
        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" class="init">
            $(document).ready(function() {

              // 優惠碼
                $("#systeminfo").DataTable( {
                    //"orderClasses": true,
                    "bProcessing": true,
                    "bServerSide": true,
                    "bRetrieve": true,
                    "searching": false,
                    "aaSorting": [[ 0, "desc" ]],
                   // "pageLength": 30,
                    "oLanguage": {
                      "sSearch": "{$tr['Account']}",//"会员帐号:",
                      "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
                      "sLengthMenu": "{$tr['each page']}_MENU_{$tr['Count']}",//"每页显示 _MENU_ 笔",
                      "sZeroRecords": "{$tr['no data']}",//"目前没有资料",
                      "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}",//"目前在第 _PAGE_ 页，共 _PAGES_ 页",
                      "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
                      "sInfoFiltered": "({$tr['from']}_MAX_{$tr['filtering in data']})",//"(从 _MAX_ 笔资料中过滤)",
                      "oPaginate": {
                        "sPrevious": "{$tr['previous']}",//"上一页",
                        "sNext": "{$tr['next']}",//"下一页"
                      }
                    },
                    "columnDefs":[
                      { className: "dt-center","targets": [0,1,2,3,5,6]},
                      { className: "dt-right", "targets": 4}
                    ],  
                    "ajax": "activity_detail_init_action.php?s={$id}&a=init",
                    "columns":[
                      {"data": "id"},
                      {"data": "promotion_id"},
                      {"data": "the_member_account"},
                      {"data": "bouns_classification"},
                      {"data": "bouns_amount"},
                      {"data": "promotion_status"},
                      {"data": "promotion_receivetime"}
                    ]
                });
  
                $.getJSON("activity_detail_init_action.php?s={$id}&a=summary", function(result) {
                  $("#show_summary").html(summary_tmpl(result));
                  $("#btn_csv").html(csv_download(result));
                 
                });
                
            } )
        </script>
HTML;
      // 欄位名稱
      $tablecol_html=<<<HTML
        <tr>
          <th class="info text-center">{$tr['ID']}</th>
          <th class="info text-center">{$tr['promo code']}</th>
          <th class="info text-center">{$tr['Account']}</th>
          <th class="info text-center">{$tr['bonus category']}</th>
          <th class="info text-center">{$tr['each promotion amount']}</th>
          <th class="info text-center">{$tr['State']}</th>
          <th class="info text-center">{$tr['Receive time (US East time)']}</th>
      </tr>
HTML;

    // csv
    $csv =<<<HTML
      <div id="btn_csv" style="float:right;margin-bottom:auto"></div>
HTML;

    // 列出資料
    $show_list_html =<<<HTML
    {$csv}
    <div id="show_summary"></div>
    <br>
    <table id="systeminfo"  class="display" cellspacing="0" width="100%">
      <thead>
        {$tablecol_html}
      </thead>
      <tfoot>
        {$tablecol_html}
      </tfoot>
      <tbody>
      </tbody>
    </table>
HTML;
}else{
  $show_list_html = "(x) {$tr['Wrong operation']}";
}

    // 切成 1 欄版面 
    $indexbody_content =<<<HTML
    <div class="row">
      <div class="col-12 col-md-12">
  
      {$show_list_html}
      </div>
    </div>
    <br>
    <div class="row">
      <div id="preview_result"></div>
    </div>
HTML;

  
$extend_js=<<<HTML
<script type="text/javascript" language="javascript">
  function summary_tmpl(result){
    return `
          <table id="show_sum_list" class="table" cellspacing="2" width="100%" >
              <thead class="thead-inverse">
                  <tr>
                    <th style="text-align:center;">{$tr['number of promotion code']}</th>
                    <th style="text-align:center;">{$tr['received']}</th>
                    <th style="text-align:center;">{$tr['non received']}</th>
                    <th style="text-align:center;">{$tr['total amount']}</th>
                    <th style="text-align:center;">{$tr['last update time']}</th>
                  </tr>
              </thead>
              <tbody id="show_sum_content">
                <tr>
                    <td align="center">\${result.all_promoction_code}</td>
                    <td align="center">\${result.get_promoction_code}</td>
                    <td align="center">\${result.unget_promoction_code}</td>
                    <td align="center">\${result.amount_promoction_code}</td>
                    <td align="center">\${result.latest_update}</td>
                </tr>
              </tbody>
          </table>
    `
  }

  // csv
  function csv_download(result){
    var link = '';
    if(result.all_promoction_code > 0){
        var link = `
        <a href="\${result.download_url}" data-loading-text="下载中..."
          data-filename="\${result.csv_filename}" class="js-download-csv btn btn-success btn-sm"
          role="button" aria-pressed="true">
          {$tr['Export Excel']}
        </a>
        `;
    }
    return link;
  }

  function query_str(){
    var updating_str = '<h5 align="center">{$tr['Data query']}...<img width="30px" height="30px" src="ui/loading.gif" /></h5>';
    $("#show_summary").html(updating_str);
    
    $.get("activity_detail_init_action.php?s={$id}&a=summary",
    function(result){
      if(!result.logger){
        $("#show_summary").html(summary_tmpl(result));
        $("#btn_csv").html(csv_download(result));
      }else{
        $("#show_summary").html('');
        alert(result.logger);
      }
    },
    'json'
    );
    
  };
</script>
HTML;


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