<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 公告訊息管理
// File Name:	announcement_admin.php
// Author:		Yuan
// Related:		對應前台 announcement.php , 連接動作後台 announcement_admin.php
// DB Table:  root_announcement
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
// 功能標題，放在標題列及meta $tr['announcement message management'] = '公告訊息管理';
$function_title 		= $tr['announcement message management'];
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
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {


	// 使用者所在的時區，sql 依據所在時區顯示 time
	// -------------------------------------
	if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
		$tz = $_SESSION['agent']->timezone;
	}else{
		$tz = '+08';
	}
	// 轉換時區所要用的 sql timezone 參數
	$tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
	$tzone = runSQLALL($tzsql);
	// var_dump($tzone);
	if($tzone[0]==1){
		$tzonename = $tzone[1]->name;
	}else{
		$tzonename = 'posix/Etc/GMT-8';
	}

	// 取得現在時間
	date_default_timezone_set($tzonename);
	$now = date('Y-m-d H:i:s');

	// 列出系統中所有的公告資訊
	// -------------------------------------
	$announcement_list_sql = "SELECT id,title,name,status,to_char((effecttime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS')  as effecttime,to_char((endtime AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS' ) as endtime,operator FROM root_announcement WHERE status != 2 ORDER BY id LIMIT 100;";
  //var_dump($announcement_list_sql);

	$announcement_list_sql_result = runSQLall($announcement_list_sql);

	// -----------------------------------------------------------------------------------------------------------------------------------------------
	//  公告訊息管理 html 組 start
	// -----------------------------------------------------------------------------------------------------------------------------------------------

	// 表格欄位名稱 $tr['name'] = '名稱'; $tr['Starting time'] = '開始時間'; $tr['End time'] = '結束時間'; $tr['State'] = '狀態';$tr['operator'] = '操作人員';$tr['function'] = '功能';
	$table_colname_html = '
  <tr>
    <th class="info text-center">ID</th>
    <th class="info text-center w-25">'.$tr['title'].'</th>
    <th class="info text-center">'.$tr['Starting time'].'</th>
    <th class="info text-center">'.$tr['End time'].'</th>
    <th class="info text-center">'.$tr['State'].'</th>
    <th class="info text-center">'.$tr['operator'].'</th>
    <th class="info text-center">'.$tr['function'].'</th>
  </tr>
  ';

	// 表格內容
	$show_listrow_html = '';

	if($announcement_list_sql_result[0] >= 1) {
		// 列出資料每一行 for loop
		for($i=1;$i<=$announcement_list_sql_result[0];$i++) {

			$announcement_id = $announcement_list_sql_result[$i]->id;
      $announcement_title = $announcement_list_sql_result[$i]->title;
			$announcement_status = $announcement_list_sql_result[$i]->status;
      $announcement_effecttime = $announcement_list_sql_result[$i]->effecttime;
			$announcement_endtime = $announcement_list_sql_result[$i]->endtime;
			$announcement_operator = $announcement_list_sql_result[$i]->operator;

      $announcement_isopen_switch = '';
      if ($announcement_status == '1') {
        $announcement_isopen_switch = 'checked';
      } elseif ($announcement_status == '0') {
        $announcement_isopen_switch = '';
      }


      // 表格 row $tr['edit'] = '編輯';
      $show_listrow_html = $show_listrow_html.'
      <tr>
        <td class="text-center">'.$announcement_id.'</td>
        <td class="text-center">'.$announcement_title.'</td>
        <td class="text-center">'.$announcement_effecttime.'</td>
        <td class="text-center">'.$announcement_endtime.'</td>
        <td class="text-center">
          <div class="col-12 material-switch pull-left">
            <input id="announcement_isopen'.$announcement_id.'" name="announcement_isopen'.$announcement_id.'" class="checkbox_switch" value="'.$announcement_id.'" type="checkbox" '.$announcement_isopen_switch.'/>
            <label for="announcement_isopen'.$announcement_id.'" class="label-success"></label>
          </div>
        </td>
        <td class="text-center">'.$announcement_operator.'</td>
        <td>
          <button type="button" class="btn btn-danger btn-sm delete_btn" id="delete_btn" value="'.$announcement_id.'"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span></button>
          <a class="btn btn-primary btn-sm" href="announcement_editor.php?s='.$announcement_id.'" role="button" title="'.$tr['edit'].'"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
        </td>
      </tr>
      ';

	   }
		// 列出資料每一行 for loop -- end
	}


	// -----------------------------------------------------------------------------------------------------------------------------------------------
	//  公告訊息管理 html 組合 end
	// -----------------------------------------------------------------------------------------------------------------------------------------------

	// -----------------------------------------------------------------------------------------------------------------------------------------------
	//  html 組合 start
	// -----------------------------------------------------------------------------------------------------------------------------------------------


  // enable sort table 啟用可排序的表格
  $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
  // 列出資料, 主表格架構 $tr['Add announcement'] = '新增公告';
  $show_list_html = '
  <table '.$sorttablecss.'>
  <thead>
  <a class="btn btn-success" href="./announcement_editor.php?s=add" role="button" style="float: right;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp;'.$tr['Add announcement'].'</a>
  <br>
  <br>
  '.$table_colname_html.'
  </thead>
  <tfoot>
  '.$table_colname_html.'
  </tfoot>
  <tbody>
  '.$show_listrow_html.'
  </tbody>
  </table>
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
  <script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
      $("#show_list").DataTable( {
          "paging":   true,
          "ordering": true,
          "info":     true,
          "searching": false,
          order: [[ 2, "dec" ]],
          "pageLength": 30
      });
    })
  </script>
  ';

  //刪除js 和 啟用開關js $tr['OK to delete'] = '確定要刪除'; $tr['Illegal test'] = '(x)不合法的測試。';
  $extend_js = $extend_js."
  <script>
  $(document).ready(function(){

    $('#show_list').on('click', '.delete_btn', function() {
      // 使用 ajax 送出 post
      var id = $(this).val();

      if(id != '') {
        if(confirm('".$tr['OK to delete']."?') == true) {
          $.ajax ({
            url: 'announcement_admin_action.php?a=delete',
            type: 'POST',
            data: ({
              id: id
            }),
            success: function(response){
              $('#preview_result').html(response);
            },
            error: function (error) {
              $('#preview_result').html(error);
            },
          });
        }
      }else{
        alert('".$tr['Illegal test']."');
      }
    });

    $('#show_list').on('click', '.checkbox_switch', function() {
      // 使用 ajax 送出 post
      var id = $(this).val();

      if(id != '') {

        if($('#announcement_isopen'+id).prop('checked')) {
          var is_open = 1;
        }else{
          var is_open = 0;
        }

        $.ajax ({
          url: 'announcement_admin_action.php?a=edit_status',
          type: 'POST',
          data: ({
            id: id,
            is_open: is_open
          }),
          success: function(response){
            $('#preview_result').html(response);
          },
          error: function (error) {
            $('#preview_result').html(error);
          },
        });
      }else{
        alert('".$tr['Illegal test']."');
      }
    });
  });
  </script>";


	// 切成 1 欄版面 $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
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

}else{
	// 沒有登入的顯示提示俊息
	$show_transaction_list_html  =$tr['only management and login mamber'];

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
    width: 0px;
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
