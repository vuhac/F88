<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 公司入款帳戶管理
// File Name:	deposit_company_config.php
// Author:		Pia
// Related:		對應前台 deposit_company.php 入款帳戶
// DB Table:  root_deposit_company
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
//$tr['Company Account Management'] = '公司入款帳戶管理';$tr['homepage']= '首頁';$tr['System Management'] = '系統管理';
$function_title 		= $tr['Company Account Management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['homepage'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

function getDailyMonthlyTransactionTotal($type)
{
	$todayDate = date('Y-m-d', strtotime(date("Y-m-d")));

	$monthBeginDate = date('Y-m-01', strtotime(date("Y-m-d")));
	$monthEndDate = date('Y-m-d', strtotime("$monthBeginDate +1 month -1 day"));

	$sql = <<<SQL
	SELECT SUM(CASE WHEN transfertime BETWEEN '{$todayDate} 00:00:00' AND '{$todayDate} 23:59:59' THEN amount END) dailyTotal,
  			SUM(CASE WHEN transfertime BETWEEN '{$monthBeginDate} 00:00:00' AND '{$monthEndDate} 23:59:59' THEN amount END) monthlyTotal
	FROM root_deposit_review
	WHERE type = '{$type}'
	AND status = 1;
SQL;

  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  return $result[1];
}

function combineTransactionLimitTotalModal($id, $transactionLimit, $transactionTotal)
{
  global $tr;
  $dailyTotal = (empty($transactionTotal->dailytotal)) ? '0' : $transactionTotal->dailytotal;
  $monthlyTotal = (empty($transactionTotal->monthlytotal)) ? '0' : $transactionTotal->monthlytotal;

  $html = <<<HTML
  <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#limitModalCenter_{$id}">
   {$tr['Deposit limit']}
  </button>

  <div class="modal fade" id="limitModalCenter_{$id}" tabindex="-1" role="dialog" aria-labelledby="limitleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="limitleModalLongTitle">{$tr['Deposit limit']}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <table class="table table-striped">
            <thead>
              <tr>
                <th scope="col">{$tr['time']}</th>
                <th scope="col">{$tr['Current accumulation']}</th>
                <th scope="col">{$tr['Limit']}</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th scope="row">{$tr['today'] }</th>
                <td>\${$dailyTotal}</td>
                <td>\${$transactionLimit->dailyTransactionLimit}</td>
              </tr>
              <tr>
                <th scope="row">{$tr['this month']}</th>
                <td>\${$monthlyTotal}</td>
                <td>\${$transactionLimit->monthlyTransactionLimit}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">{$tr['off']}</button>
        </div>
      </div>
    </div>
  </div>
HTML;

  return $html;
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  表格內容 html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // 取出 DB 所有入款帳戶資訊
  $depository_company_list_sql = "SELECT *,to_char((changetime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS')  as changetime,member_id,member_level_id,grade FROM root_deposit_company WHERE status <2 ORDER BY id LIMIT 100;";
  // print("<pre>".$depository_company_list_sql."</pre>"); exit();
  $depository_company_list_sql_result = runSQLall($depository_company_list_sql);
  //  var_dump($depository_company_list_sql_result);

  $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
  // $sorttablecss = ' class="table table-striped" ';

  // table title
  // 表格欄位名稱
  // $tr['bank name'] = '銀行名稱';$tr['Bank account'] = '銀行帳號';$tr['State'] = '狀態';$tr['Member Level'] = '會員等級';$tr['edit'] = '編輯';$tr['Account Type'] = '帳戶型態';$tr['username'] = '戶 名';$tr['deposit note'] = '存款備註';$tr['modify time'] = '修改時間';
  $table_colname_html = '
  <tr>
    <th class="text-center"><input name="select_all" type="checkbox" class="checkAll"></input></th>
		<th>ID</th>
		<th>'.$tr['Account Type'].'</th>
		<th>'.$tr['service name'].'</th>
		<th>'.$tr['account name'].'</th>
    <th>'.$tr['account / receipt code'].'</th>
    <th>'.$tr['Limit'].'</th>
		<th>'.$tr['State'].'</th>
		<th>'.$tr['Member Level'].'</th>
    <th>'.$tr['edit'].'</th>
	</tr>
  ';

  // 表格內容
  $show_listrow_html = '';
  if($depository_company_list_sql_result[0] >= 1) {
    for($i=1;$i<=$depository_company_list_sql_result[0];$i++) {

      $depository_company_id              = $depository_company_list_sql_result[$i]->id;
      $depository_company_type            = $depository_company_list_sql_result[$i]->type;
      $depository_company_companyname     = $depository_company_list_sql_result[$i]->companyname;
      $depository_company_accountname     = $depository_company_list_sql_result[$i]->accountname;
      $depository_company_accountnumber   = $depository_company_list_sql_result[$i]->accountnumber;
      $depository_company_status          = $depository_company_list_sql_result[$i]->status;
      $depository_company_notes           = $depository_company_list_sql_result[$i]->notes;
      $depository_company_changetime      = $depository_company_list_sql_result[$i]->changetime;
      $depository_company_grade           = $depository_company_list_sql_result[$i]->grade;
      $depository_company_transaction_limit = json_decode($depository_company_list_sql_result[$i]->transaction_limit);

      $dailyMonthlyTransactionTotal = getDailyMonthlyTransactionTotal($depository_company_type.'_'.$depository_company_id);
      $dailyTotal = (empty($dailyMonthlyTransactionTotal->dailytotal)) ? '0' : $dailyMonthlyTransactionTotal->dailytotal;
      $monthlyTotal = (empty($dailyMonthlyTransactionTotal->monthlytotal)) ? '0' : $dailyMonthlyTransactionTotal->monthlytotal;

      $modalHtml = combineTransactionLimitTotalModal($depository_company_id, $depository_company_transaction_limit, $dailyMonthlyTransactionTotal);

      if ($depository_company_type == 'wechat' || $depository_company_type == 'virtualmoney') {
        $depository_company_accountnumber = '<img id="'.$depository_company_id.'_qrcode" src="'.$depository_company_list_sql_result[$i]->accountnumber.'" height="100" width="100">';
      }

      //把抓出來的 json 轉成 array
      $depository_company_grade = json_decode($depository_company_grade, true);
      //$tr['off'] = '關閉';$tr['Enabled'] = '啟用';$tr['edit'] = '編輯';$tr['Not yet set'] = '尚未設定';
      //會員等級
      $list_grade_name = '';
      if(isset($depository_company_grade)){
        foreach ($depository_company_grade as $key => $value) {
          $list_grade_name = $list_grade_name.'<button type="button" class="btn btn-warning btn-xs">'.$key.'</button>&nbsp';
        }
      }else{
        $list_grade_name = $tr['Not yet set'];
      }

      //狀態
       if ($depository_company_status == 0) {
         $status_select_option = $tr['off'];
       } elseif($depository_company_status == 1) {
         $status_select_option = $tr['Enabled'];
       }

       $show_listrow_html = $show_listrow_html . '
       <tr>
        <td class="text-center"><input type="checkbox" name="delete_checkbox" value="'.$depository_company_id.'" class="delete_checkbox_class"></td>
        <td class="text-left">
         '.$depository_company_id.'
        </td>
        <td class="text-left">
          '.$depository_company_type.'
        </td>
         <td class="text-left">
          '.$depository_company_companyname.'
        </td>
         <td class="text-left">
          '.$depository_company_accountname.'
        </td>
        <td class="text-left">
          '.$depository_company_accountnumber.'
        </td>
        <td class="text-left">
          '.$modalHtml.'
        </td>
        <td class="text-left">
          '.$status_select_option.'
        </td>
        <td class="text-left">
          '.$list_grade_name.'
        </td>
         <td class="text-left tooltip-edit">
          <a href="deposit_company_config_detail.php?i='.$depository_company_id.'" title="'.$tr['edit'].'" class="btn btn-primary" ><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
        </td>
      </tr>
       ';
    }
  }


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  表格內容 html 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------



  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  // $tr['Note: This operation is only valid on this page'] = '注意：此操作僅本頁面有效！';
  $show_list_html = '';
  $show_list_html = $show_list_html . '
	<div class="tab-content col-12 col-md-12">
	<br>
		<div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
      <button type="button" class="btn btn-danger" style="display:inline-block;float: right;" id="delete_company_deposit_btn"><span class="glyphicon glyphicon-minus" aria-hidden="true"></span></button>
      <a href="deposit_company_config_detail.php"><button type="button" class="btn btn-success" style="display:inline-block;float: right;margin-right: 5px;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span></button></a>
      <div style="padding-top: 40px;" align="right">('.$tr['Note: This operation is only valid on this page'].')</div>
      <form id="show_list_form" action="POST">
        <table '.$sorttablecss.'>
        <thead>
        '.$table_colname_html.'
        </thead>
        <tbody>
        '.$show_listrow_html.'
        </tbody>
        <tfoot>
        '.$table_colname_html.'
        </tfoot>
        </table>
      </from>
		</div>
	</div>
    '.'
	<div class="row">
		<div class="col-12 col-md-3">
		</div>

		<div class="col-12 col-md-6">
			<div id="preview"></div>
		</div>

		<div class="col-12 col-md-3">

		</div>
	</div>
    ';

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
	<div class="row">
		<div class="col-12 col-md-12">
		'.$show_list_html.'
		</div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';

  // 參考使用 datatables 顯示
  // https://datatables.net/examples/styling/bootstrap.html
  $extend_head = $extend_head.'
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  ';

  // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
  $extend_head = $extend_head.'
  <script>
    $(document).ready(function() {
      $("#show_list").DataTable( {
          "searching": false,
          "columnDefs": [ {
            "targets": 0,
            "orderable": false
            } ]
        });
      });
  </script>
  ';
  // ---------------------------------------------------------------------------


  //全選、全不選 js
  $extend_js = $extend_js.'
  <script>
    $(".checkAll").change(function () {
      $("input:checkbox").prop(\'checked\', $(this).prop("checked"));
    });
  </script>';

  //刪除 js $tr['OK delete'] = '確定刪除？';
  $extend_js = $extend_js."
  <script>
  $(document).ready(function(){

      $('#delete_company_deposit_btn').click(function() {

        var ifclick = confirm('{$tr['OK delete']}');

        if(ifclick == true){
          // 使用 ajax 送出 post
          var edit_id_num                 = $('.delete_checkbox_class').serialize();

          if( edit_id_num.length == 0){
             }else{
               $.ajax ({
                 url: 'deposit_company_config_action.php?a=delete',
                 type: 'POST',
                 data: ({
                   edit_id_num: edit_id_num
                 }),
                 success: function(response_data){
                   console.log(response_data);
                   location.reload();
                 },
                 error: function (errorinfo) {
                   console.log(errorinfo);
                 },
                });
             }
        }
      });
  });
  </script>";


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------

} else {
  // 沒有登入的顯示提示俊息 $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';$tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
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
include("template/beadmin_fluid.tmpl.php");
