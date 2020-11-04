<?php
// ----------------------------------------------------------------------------
// Features:  後台 -- 站長廣播公告
// File Name: systemconfig_announce_read.php
// Author:     Mavis
// Related:
// DB Table:
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
$function_title     =  $tr['announcement'];
// 擴充 head 內的 css or js
$extend_head        = '';
// 放在結尾的 js
$extend_js          = '';
// body 內的主要內容
$indexbody_content  = '';
// 公告訊息
$messages = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['webmaster']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;
// ----------------------------------------------------------------------------

if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  $extend_head .=<<<HTML
                        <!-- Jquery UI js+css  -->
                          <link rel="stylesheet"  href="in/jquery-ui.css" >
                           <!-- Jquery UI js+css  -->
                          <script src="in/jquery-ui.js"></script>
                           <!-- Jquery blockUI js  -->
                          <script src="./in/jquery.blockUI.js"></script>
                          <!-- Datatables js+css  -->
                          <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
                          <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
                          <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
                          <script type="text/javascript" language="javascript" class="init">
                          $(document).ready(function(){             

                              // unread
                               $("#table-unread").DataTable({
                                "bProcessing": true,
                                "bServerSide": true,
                                "bRetrieve": true,
                                "searching": false,
                                "aaSorting": [[ 0, "desc" ]],
                                "pageLength": 50, 
                                "oLanguage": {
                                  //"sSearch": "{$tr['Account']}",//"帐号:",
                                  "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
                                  "sLengthMenu": "{$tr['each page']} _MENU_ {$tr['Count']}",//"每页显示 _MENU_ 笔",
                                  "sZeroRecords": "{$tr['no data']}",//"目前没有资料",
                                  "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}", //"目前在第 _PAGE_ 页，共 _PAGES_ 页",
                                  "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
                                  "sInfoFiltered": "{$tr['from']} _MAX_ {$tr['filtering in data']}",//"(从 _MAX_ 笔资料中过滤)",
                                  "oPaginate": {
                                    "sPrevious": "{$tr['previous']}",//"上一页",
                                    "sNext": "{$tr['next']}"//"下一页"
                                  }
                                },
                                "ajax":"systemconfig_announce_read_action.php?a=select&query=unread",
                                "columns":[
                                {"data": "id"},
                                {"data": "name"},
                                {"data": "title"},
                                {"data": "content"},
                                {"data": "effecttime"},
                                {"data": "details"}
                                ]
                              })
                               
                              // read
                               $("#table-read").DataTable({
                                "bProcessing": true,
                                "bServerSide": true,
                                "bRetrieve": true,
                                "searching": false,
                                "aaSorting": [[ 0, "desc" ]],
                                "pageLength": 50,  
                                "oLanguage": {
                                  //"sSearch": "{$tr['Account']}",//"帐号:",
                                  "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
                                  "sLengthMenu": "{$tr['each page']} _MENU_ {$tr['Count']}",//"每页显示 _MENU_ 笔",
                                  "sZeroRecords": "{$tr['no data']}",//"目前没有资料",
                                  "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}", //"目前在第 _PAGE_ 页，共 _PAGES_ 页",
                                  "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
                                  "sInfoFiltered": "{$tr['from']} _MAX_ {$tr['filtering in data']}",//"(从 _MAX_ 笔资料中过滤)",
                                  "oPaginate": {
                                    "sPrevious": "{$tr['previous']}",//"上一页",
                                    "sNext": "{$tr['next']}"//"下一页"
                                  }
                                },     
                                "ajax":"systemconfig_announce_read_action.php?a=select&query=read",
                                "columns":[
                                {"data": "id"},
                                {"data": "name"},
                                {"data": "title"},
                                {"data": "content"},
                                {"data": "effecttime"},
                                {"data": "details"}
                                ]
                              })

                                // datatable -all 
                              $("#table-all").DataTable({
                                "bProcessing": true,
                                "bServerSide": true,
                                "bRetrieve": true,
                                "searching": false,
                                "aaSorting": [[ 0,"desc" ]],
                                "pageLength": 50,
                                "oLanguage": {
                                  //"sSearch": "{$tr['Account']}",//"帐号:",
                                  "sEmptyTable": "{$tr['no data']}",//"目前没有资料!",
                                  "sLengthMenu": "{$tr['each page']} _MENU_ {$tr['Count']}",//"每页显示 _MENU_ 笔",
                                  "sZeroRecords": "{$tr['no data']}",//"目前没有资料",
                                  "sInfo": "{$tr['now at']} _PAGE_，{$tr['total']} _PAGES_ {$tr['page']}", //"目前在第 _PAGE_ 页，共 _PAGES_ 页",
                                  "sInfoEmpty": "{$tr['no data']}",//"目前没有资料",
                                  "sInfoFiltered": "{$tr['from']} _MAX_ {$tr['filtering in data']}",//"(从 _MAX_ 笔资料中过滤)",
                                  "oPaginate": {
                                    "sPrevious": "{$tr['previous']}",//"上一页",
                                    "sNext": "{$tr['next']}"//"下一页"
                                  }
                                },       
                                "ajax":"systemconfig_announce_read_action.php?a=select&query=select",
                                "columns":[
                                {"data": "id"},
                                {"data": "name"},
                                {"data": "title"},
                                {"data": "content"},
                                {"data": "effecttime"},
                                {"data": "details"}
                                ]
                              })

                            })
                          
                          </script>
                         
HTML;


// 表格欄位名稱
$table_colname_html = '';
// 欄位名稱
  $table_colname_html .=<<<HTML
  <tr>
    <th scope="col">{$tr['ID']}</th>
    <th scope="col">{$tr['announcement name']}</th>
    <th scope="col">{$tr['announcement title']}</th>
    <th scope="col">{$tr['content']}</th>
    <th scope="col">{$tr['Starting time']}</th>
    <th scope="col">{$tr['detail']}</th>
  </tr>
HTML;

// 按下button後出現的內容
  $indexbody_content .= <<<HTML
  
  <div class="row">

    <div class="tab-content col-xs-12 col-md-12">
    
    	<ul class="nav nav-tabs" id="myTab" role="tablist">
      	<li class="nav-item active">
        	<a class="nav-link" id="unread-tab" href="#unread" name="query" data-toggle="tab" role="tab" aria-controls="unread" aria-selected="true">{$tr['unread']}</a>
      	</li>
      	<li class="nav-item">
        	<a class="nav-link" id="read-tab" href="#read" name="query" data-toggle="tab" role="tab" aria-controls="read" aria-selected="false">{$tr['read']}</a>
      	</li> 
      	<li class="nav-item">
        	<a class="nav-link" id="all-tab" href="#all" name="query" data-toggle="tab" role="tab" aria-controls="all" aria-selected="false">{$tr['all'] }</a>
      	</li>
    	</ul>
    </div>

    
    <div class="tab-content col-xs-12 col-md-12" id="myContent">
     <div class="tab-pane show active col-xs-12 col-md-12" id="unread" role="tabpanel" aria-labelledby="unread-tab" value="unread">
      <table id="table-unread" class="display" cellspacing="0" width="100%">
        <thead>
          {$table_colname_html}
          <br>
          <br>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>

    <div class="tab-pane col-xs-12 col-md-12" id="read" role="tabpanel" aria-labelledby="read-tab" value="read">
      <table id="table-read" class="display" cellspacing="0" width="100%">
        <thead>
          {$table_colname_html}
          <br>
          <br>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>

    <div class="tab-pane col-xs-12 col-md-12" id="all" role="tabpanel" aria-labelledby="all-tab" value="all">
      <table id="table-all" class="display" cellspacing="0" width="100%">
        <thead>
          {$table_colname_html}
          <br>
          <br>
        </thead>
        <tbody>
        </tbody>
      </table>
    </div>
  	</div>
  </div>
  
  <br>

  <div class="row">
    <div id="preview_result"></div>
  </div>

HTML;

  $extend_js.=<<<HTML
  <script>

  $(document).ready(function(){
		                         
  	  // click 我知道了的button
    $("body").on("click", ".readed", function(){
      var id = $(this).val();

      if(id != ''){
        
        if($("#watchingstatus"+id).prop("checked")) {
          var watchingstatus = 1;
        } else {
          var watchingstatus = 2;
        }        
      $.ajax({
        url: "systemconfig_announce_read_action.php?a=read",
        type: "POST",
        data:({
          id: id,
          watchingstatus: watchingstatus
        }),
        success:function(response){
          // $("#preview_result").html(response);
          window.location.href="systemconfig_announce_read.php";
          // datatable reload
          $("#table-unread").DataTable().ajax.reload(); 
          $("#table-read").DataTable().ajax.reload();
          
        },
        error: function(error){
          $("#preview_result").html(error);
        },
      });
     }
    })

})

  </script>
HTML;
}


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
// 主要內容 -- title
$tmpl['paneltitle_content']       = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title.'<p id="announce_mq" class="mb-0 ml-auto float-right" style="color: #dc3545; display: none;"></p>';
// 主要內容 -- content
$tmpl['panelbody_content']        = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");

?>