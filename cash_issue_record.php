<?php
// ----------------------------------------------------------------------------
// Features:	點數管理_發行紀錄
// File Name:	cash_issue_record.php
// Author:		snow
// Related:		
// 資料庫初始化: cash_issue_record_init_action.php
// 查詢action: cash_issue_record_action.php
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
$function_title 		= $tr['system point management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';

$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] .'</a></li>
  <li><a href="#">' . $tr['maintenance'] . '</a></li>
  <li><a href="cash_management.php">'.$function_title.'</a></li>
  <li class="active">'.$tr['publication record'].'</li>
</ol>';

// 只有站長或維運也就是 $su['superuser'] 才有權限使用此頁
if(!($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
  header('Location:./home.php');
  die();
}


//$mct_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ? '<li><a href="maincategory_editor.php" target="_self">'.$tr['MainCategory Management'].'</a></li>' : '';


$cash_html = '<div class="col-md-12 tab">
<ul class="nav nav-tabs">
    <li><a href="cash_management.php" target="_self">'.$tr['point management'].'</a></li>
    <li class="active"><a href="" target="_self">'.$tr['publication record'].'</a></li>
</ul></div><br><br>';


// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) == $date;
}
// 取得 today date get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker'])) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['current_datepicker'], 'Y-m-d')) {
    $current_datepicker = $_GET['current_datepicker'];
  }else{
    // 轉換為美東的時間 date
    $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
    date_timezone_set($date, timezone_open('America/St_Thomas'));
    $current_datepicker = date_format($date, 'Y-m-d');
    // $current_datepicker = date('Y-m-d');
  }
}else{
  // php 格式的 2017-02-24
  // 轉換為美東的時間 date
  $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
  date_timezone_set($date, timezone_open('America/St_Thomas'));
  $current_datepicker = date_format($date, 'Y-m-d');
}

// 擴充 head 內的 css or js
$extend_head				= $extend_head.'<!-- Jquery UI js+css  -->
                        <script src="in/jquery-ui.js"></script>
                        <link rel="stylesheet"  href="in/jquery-ui.css" >
                        <!-- jquery datetimepicker js+css -->
                        <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
                        <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
                        <!-- Datatables js+css  -->
                        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
                        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
                        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
                        ';

//-------------------------------------------------------------
// 列表Table
//-------------------------------------------------------------
$indexbody_content = <<<HTML
<table id="show_list" class="display" width="100%">
  <thead>
    <tr>
      <th>{$tr['ID']}</th>
      <th>{$tr['reversal']}/{$tr['publication']} {$tr['time']}({$tr['EDT(GMT -5)']})</th>
      <th>{$tr['recipient']}</th>
      <th>{$tr['operator']}</th>
      <th>{$tr['type']}</th>
      <th>{$tr['transaction amount']}</th>
      <th>{$tr['current balance'] }</th>  
      <th>{$tr['State']}</th>      
    </tr>
  </thead>
  <tfoot>
    <tr>
      <th>{$tr['ID']}</th>
      <th>{$tr['reversal']}/{$tr['publication']} {$tr['time']}({$tr['EDT(GMT -5)']})</th>
      <th>{$tr['recipient']}</th>
      <th>{$tr['operator']}</th>
      <th>{$tr['type']}</th>
      <th>{$tr['transaction amount']}</th>
      <th>{$tr['current balance']}</th> 
      <th>{$tr['State']}</th>         
    </tr>
  </tfoot>
</table>
HTML;


$extend_js .= <<<JS
<script>
$(document).ready(function() {
  $("#show_list").DataTable({
      //"ordering": false,
      "order": [[ 0, "desc" ]],
      "bretrieve": true,
      //"serverSide": true,      
      "searching": false,
      // "pageLength": 1,
      "ajax": "cash_issue_record_init_action.php?a=init",
      "columns": [
        { "data": "id" },
        { "data": "changetime" },
        { "data": "account" },
        { "data": "operator" },
        { "data": "type"},
        { "data": "amount"},
        { "data": "balance"}, 
        { "data": "status"},        
      ] 
      
  });  
    
  $("#sum_list").DataTable({
    "paging":   false,
    "ordering": false,
    "searching": false,
    "info":     false,
    "ajax": "cash_issue_record_init_action.php?a=summary",
    "columns": [                 
      { "data": "sum1" },
      { "data": "sum2" },
      { "data": "sum3" },
      { "data": "sum4" },
    ]
  });
    
});

</script>
JS;


//-------------------------------------------------------------
// 總計Table
//-------------------------------------------------------------
$show_tips_html =  <<<HTML
<table id="sum_list" class="display" width="100%">
		<thead>    		
		<th class="info">{$tr['total gtoken reversal']}</th>
    <th class="info">{$tr['total gtoken publication'] }</th>    
    <th class="info">{$tr['total gcash reversal']}</th>
    <th class="info">{$tr['total gcash publication']}</th>
		</thead>		
    <tbody id="sum_list">
    </tbody>
</table>
<br><hr>
HTML;



//-------------------------------------------------------------
// 查詢UI
//-------------------------------------------------------------
$date_selector_html = '
<form class="form-inline" method="get">
    <div class="input-group input-group-sm mr-2 my-1">
      <div class="input-group-addon">'.$tr['recipient'].'</div>
      <div class="input-group-addon">
        <select class="form-control" name="cash_account" id="cash_account">      
        <option value="">----</option>
        <option value="gcashcashier">'.$tr['gcash cashier'].'</option>
        <option value="gtokencashier">'.$tr['gtoken cashier'].'</option>
        </select>
      </div>
    </div>  
    <div class="input-group input-group-sm mr-2 my-1">
    <div class="input-group-addon">'.$tr['type'].'</div>
    <div class="input-group-addon">
      <select class="form-control" name="cash_type" id="cash_type">      
      <option value="">----</option>
      <option value="1">'.$tr['gtoken reversal'].'</option>
      <option value="2">'.$tr['gtoken publication'].'</option>
      <option value="3">'.$tr['cash reversal'].'</option>
      <option value="4">'.$tr['cash publication'].'</option>
      </select>
    </div>
  </div> 
    <div class="input-group input-group-sm mr-2 my-1">
    <div class="input-group-addon">'.$tr['State'].'</div>
    <div class="input-group-addon">
      <select class="form-control" name="cash_status" id="cash_status">      
      <option value="">----</option>
      <option value="1">'.$tr['Success.'].'</option>
      <option value="2">'.$tr['fail'] .'</option>
      </select>
    </div>
  </div>  
    <div class="input-group input-group-sm mr-2 my-1">
    <div class="input-group-addon">'.$tr['publication and abolishment'].'</div>
    <input type="text" class="form-control" name="issue_validdatepicker_start" id="issue_validdatepicker_start" placeholder="ex:2017-01-20" value="'.$current_datepicker.'">
      <span class="input-group-addon" id="basic-addon1">~</span>
      <input type="text" class="form-control" name="issue_validdatepicker_end" id="issue_validdatepicker_end" placeholder="ex:2017-01-20" value="'.$current_datepicker.'">
    </div>  
    <button class="btn btn-primary js-query-receivemoney-btn">'.$tr['Inquiry'].'</button>
</form>
<br>
'
;

$dateyearrange_start 	= date("Y");
$dateyearrange_end 		= date("Y");
$dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;
$date_selector_js = '
  <script>
  

    $(document).ready(function() {
      
      $(".js-query-receivemoney-btn").on("click", function(e){
        e.preventDefault();
        
        
        var cash_account = $("#cash_account").val();
        var cash_type = $("#cash_type").val();
        var cash_status = $("#cash_status").val();
        var issue_validdatepicker_start = $("#issue_validdatepicker_start").val();
        var issue_validdatepicker_end = $("#issue_validdatepicker_end").val();    
        
        // 製作 get 字串
        
        var query_str = \'&cash_account=\'+cash_account+\'&cash_type=\'+cash_type+\'&cash_status=\'+cash_status+\'&issue_validdatepicker_start=\'+issue_validdatepicker_start+\'&issue_validdatepicker_end=\'+issue_validdatepicker_end;
        
        $.getJSON("cash_issue_record_action.php?a=query_summary"+query_str,
        function(result){          
          if(!result.logger){          
            //$("#summarytable_html").html(result);          
            $("#show_list").DataTable()
                .ajax.url("cash_issue_record_action.php?a=query_summary"+query_str)
                .load();                        
          }else{
            alert(result.logger);
            location.reload();
          }
        });
        $.getJSON("cash_issue_record_action.php?a=sum_amount"+query_str,
        function(result){          
          if(!result.logger){                
            $("#sum_list").DataTable()
              .ajax.url("cash_issue_record_action.php?a=sum_amount"+query_str)
              .load();                     
          }else{
            alert(result.logger);
            location.reload();
          }
        });
      });
      

      
      $( "#issue_validdatepicker_start" ).datepicker({
        yearRange: "'.$dateyearrange_start.':'.$dateyearrange_end.'",
        minDate: "-13w",
        showButtonPanel: true,
      	dateFormat: "yy-mm-dd",
      	changeMonth: true,
      	changeYear: true
      });
      $( "#issue_validdatepicker_end" ).datepicker({
        yearRange: "'.$dateyearrange_start.':'.$dateyearrange_end.'",
        minDate: "-13w",
        showButtonPanel: true,
      	dateFormat: "yy-mm-dd",
      	changeMonth: true,
      	changeYear: true
      });
    } );
  </script>
  ';
  
// 選擇日期 html
$date_selector_html = $date_selector_html.'
<div class="row">
  <div id="summarytable_html" type="hide"></div>
</div>
';
$show_dateselector_html = $date_selector_html.$date_selector_js;
//-------------------------------------------------------------

  
$cash_html .= $show_dateselector_html;
$cash_html .= $show_tips_html;
$cash_html .= $indexbody_content;




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
$tmpl['paneltitle_content']       = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']        = $cash_html;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");