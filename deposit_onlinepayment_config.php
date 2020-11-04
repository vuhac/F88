<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 線上付商戶管理
// File Name:	deposit_onlinepayment_config.php
// Author:		Yuan
// Related:		對應前台 deposit_online_pay.php 入款帳戶
// DB Table:  root_deposit_onlinepayment
// Log:
// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 專屬本頁的文字檔案
require_once dirname(__FILE__) ."/deposit_onlinepayment_config_lib.php";

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
// 功能標題，放在標題列及meta  $tr['Online Payment Merchant Management'] = '線上支付商戶管理';
$function_title 		= $tr['Online Payment Merchant Management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] = '首頁'; $tr['System Management'] = '系統管理';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

// -----------------------------------------------------------------------------------------------------------------------------------------------
//  列出系統中所有的入款帳戶資訊 start
// -----------------------------------------------------------------------------------------------------------------------------------------------


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  表格內容 html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


    // 取出 DB 所有線上付商戶資訊
    $deposit_onlinepayment_list_sql = "SELECT * FROM root_deposit_onlinepayment WHERE status != '2' ORDER BY id;";
    //  var_dump($deposit_onlinepayment_list_sql);
    $deposit_onlinepayment_list_sql_result = runSQLall($deposit_onlinepayment_list_sql);
    //var_dump($deposit_onlinepayment_list_sql_result);

    $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
    // $sorttablecss = ' class="table table-striped" ';

    // table title
    // 表格欄位名稱 $tr['Member Level'] = '會員等級'; $tr['Fee'] = '手續費';$tr['State'] = '狀態'; $tr['edit'] = 編輯';$tr['payment name'] = '支付名稱';$tr['payer'] = '支付商';$tr['store code']='商店代號';                                                                                                              
    $table_colname_html = '
    <tr>
      <th>ID</th>
  		<th>'.$tr['payment name'].'</th>
      <th>'.$tr['payer'].'</th>
  		<th>'.$tr['store code'].'</th>
      <th>'.$tr['Member Level'].'</th>
      <th>'.$tr['Fee'].'(%)</th>
      <th>'.$tr['State'].'</th>
      <th>'.$tr['edit'].'</th>
    </tr>
    ';



    // 表格內容
    $show_listrow_html = '';
    if($deposit_onlinepayment_list_sql_result[0] >= 1) {
      for($i=1;$i<=$deposit_onlinepayment_list_sql_result[0];$i++) {

        $deposit_onlinepayment_id = $deposit_onlinepayment_list_sql_result[$i]->id;
        $deposit_onlinepayment_payname = $deposit_onlinepayment_list_sql_result[$i]->payname;
        $deposit_onlinepayment_merchantid = $deposit_onlinepayment_list_sql_result[$i]->merchantid;
        $deposit_onlinepayment_gradename = $deposit_onlinepayment_list_sql_result[$i]->grade;
        $deposit_onlinepayment_status = $deposit_onlinepayment_list_sql_result[$i]->status;
        $deposit_onlinepayment_cashfeerate = $deposit_onlinepayment_list_sql_result[$i]->cashfeerate;
        $deposit_onlinepayment_name = $deposit_onlinepayment_list_sql_result[$i]->name;

        //把抓出來的 json 轉成 array
        $deposit_onlinepayment_gradename = json_decode($deposit_onlinepayment_gradename, true);

        $list_grade_name = '';
        if(isset($deposit_onlinepayment_gradename)){
          foreach ($deposit_onlinepayment_gradename as $key => $value) {
            $list_grade_name = $list_grade_name.'<button type="button" class="btn btn-warning btn-xs">'.$key.'</button>&nbsp';
          }
        }else{
          // $tr['Not yet set'] = '尚未設定';
          $list_grade_name =$tr['Not yet set'];
        }

        // $tr['edit'] = '編輯';
        $show_listrow_html = $show_listrow_html . '
        <tr>
  				<td class="text-left">
  				 '.$deposit_onlinepayment_id.'
  				</td>
  				<td class="text-left">
  				  '.$deposit_onlinepayment_payname.'
  				</td>
          <td class="text-left">
  				  '.$payment_form_name[explode('_',$deposit_onlinepayment_name)[0]]['name'].'
  				</td>
          <td class="text-left">
  				  '.$deposit_onlinepayment_merchantid.'
  				</td>
          <td class="text-left">
            '.$list_grade_name.'
          </td>
          <td class="text-left">
  				  '.$deposit_onlinepayment_cashfeerate.'
  				</td>
          <td class="text-left">
  				  '.$select_status[$deposit_onlinepayment_status].'
  				</td>
          <td class="text-left tooltip-edit">
            <button type="button" class="btn btn-danger" onclick="delete_onlinepayment('.$deposit_onlinepayment_id.')"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>
  				  <a href="deposit_onlinepayment_config_detail.php?a=edit_'.$deposit_onlinepayment_id.'" title="'.$tr['edit'].'" class="btn btn-primary" ><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
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
// $tr['add Payer'] = '新增支付商';
  $show_list_html = '';
  $show_list_html = $show_list_html . '
	<div class="tab-content">
	<br>
		<div role="tabpanel" class="tab-pane active" id="inbox_View">
      <a href="deposit_onlinepayment_config_detail.php?a=add_'.base64_encode(date("Ymd")).'"><button type="button" class="btn btn-success" style="display:inline-block;float: right;margin-right: 5px;"><span class="glyphicon glyphicon-plus" aria-hidden="true">'.$tr['add Payer'].'</span></button></a>
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
      var oTable = $("#show_list").DataTable( {
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


  //刪除 js $tr['OK to delete'] = '確定要刪除'; $tr['Illegal test'] = '(x)不合法的測試。';
  $delete_btn_js = "
  <script>
    function delete_onlinepayment(edit_id_num){

        if(edit_id_num.length != 0 ){

          if(confirm('".$tr['OK to delete']."?') == true){

           // 使用 ajax 送出 post
            $.ajax ({
              url: 'deposit_onlinepayment_config_action.php?a=delete',
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
          }else{
            alert('".$tr['Illegal test']."');
          }
      };
  </script>";

  $extend_js = $extend_js.$delete_btn_js;

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  沒有任何要求 列出系統中所有的入款帳戶資訊 END
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  //都沒有以上的動作 顯示錯誤訊息 $tr['Wrong operation'] = '錯誤的操作';
  }else{
    $show_list_html  = '(x)'.$tr['Wrong operation'];
  }

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
// -----------------------------------------------------------------------------------------------------------------------------------------------
//  html 組合 end
// -----------------------------------------------------------------------------------------------------------------------------------------------

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
