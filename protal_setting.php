<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 會員端設定管理
// File Name:	protal_setting.php
// Author:		Yuan
// Related:
// DB Table:  root_protalsetting
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
$function_title 		= $tr['Member system management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['System Management'] . '</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  $register_setting_url = 'protal_setting_deltail.php';

  // 取出 DB 內所有的 setttingname
  $setttingname_list_sql = "SELECT DISTINCT(setttingname) as setttingname FROM root_protalsetting WHERE status = '1';";
//  var_dump($setttingname_list_sql);
  $setttingname_list_sql_result = runSQLall($setttingname_list_sql);
//  var_dump($setttingname_list_sql_result);

  if ($setttingname_list_sql_result[0] >= 1) {
    for ($i=1;$i<=$setttingname_list_sql_result[0];$i++) {

      $setttingname = $setttingname_list_sql_result[$i]->setttingname;

      // 根據 setttingname 取出相關會員管理的設定詳細
      $protal_setting_list_sql = "SELECT name, value, id FROM root_protalsetting WHERE status = '1' AND setttingname = '$setttingname' ORDER BY id;";
//      var_dump($protal_setting_list_sql);
      $protal_setting_list_sql_result = runSQLall($protal_setting_list_sql);
//      var_dump($protal_setting_list_sql_result);

      // 表格欄位名稱
      $table_colname_html = '
      <tr>
        <th width="10%">' . $tr['name'] . '</th>
        <th width="10%">' . $tr['company deposits'] . '</th>
        <th width="10%">' . $tr['withdraw application'] . ' </th>
        <th width="15%">' . $tr['member register setting'] . '</th>
        <th width="15%">' . $tr['agent register setting'] . '</th>
        <th width="40%">' . $tr['note'] . '</th>
      </tr>
      ';


      // 表格內容
      $show_listrow_html = '';
      if($protal_setting_list_sql_result[0] >= 1) {
  //    for($i=1;$i<=$depository_company_list_sql_result[0];$i++) {

        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  判斷各項checkbox是否開啟 start
        // -----------------------------------------------------------------------------------------------------------------------------------------------


        // 判斷公司入款功能是否開啟
        $companydeposit_switch = '';
        if ($protal_setting_list_sql_result[1]->name == 'companydeposit_switch' AND $protal_setting_list_sql_result[1]->value == 'on') {
          $companydeposit_switch = 'checked';
        } elseif ($protal_setting_list_sql_result[1]->name == 'companydeposit_switch' AND $protal_setting_list_sql_result[1]->value == 'off') {
          $companydeposit_switch = '';
        }

        // 判斷取款申請功能是否開啟
        $withdrawalapply_switch = '';
        if ($protal_setting_list_sql_result[2]->name == 'withdrawalapply_switch' AND $protal_setting_list_sql_result[2]->value == 'on') {
          $withdrawalapply_switch = 'checked';
        } elseif ($protal_setting_list_sql_result[2]->name == 'withdrawalapply_switch' AND $protal_setting_list_sql_result[2]->value == 'off') {
          $withdrawalapply_switch = '';
        }

        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  判斷各項checkbox是否開啟 end
        // -----------------------------------------------------------------------------------------------------------------------------------------------


        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  表格內容 html 組合 start
        // -----------------------------------------------------------------------------------------------------------------------------------------------


        /*
         * bootstrap 沒有 switch元件可用
         * 必需使用 checkbox 及 label 加上 css 去堆疊出來
         *
         * 參考 : http://bootsnipp.com/snippets/featured/material-design-switch
         */

        $show_listrow_html = $show_listrow_html . '
        <tr>
          <td class="text-left">
            <a href="'.$register_setting_url.'?sn='.$setttingname.'">'.$setttingname.'</a>
          </td>
          <td class="text-left">
            <div class="col-12 col-md-12 material-switch pull-left">
              <input id="companydeposit_switch" name="companydeposit_switch" class="checkbox_switch" value="companydeposit_switch" type="checkbox" '.$companydeposit_switch.'/>
              <label for="companydeposit_switch" class="label-success"></label>
            </div>
          </td>
          <td class="text-left">
            <div class="col-12 col-md-12 material-switch pull-left">
              <input id="withdrawalapply_switch" name="withdrawalapply_switch" class="checkbox_switch" value="withdrawalapply_switch" type="checkbox" '.$withdrawalapply_switch.'/>
              <label for="withdrawalapply_switch" class="label-success"></label>
            </div>
          </td>
          <td class="text-left">
            <a href="'.$register_setting_url.'?sn='.$setttingname.'#member_register_switch">' . $tr['view member register setting'] . '</a>
          </td>
          <td class="text-left">
            <a href="'.$register_setting_url.'?sn='.$setttingname.'#agent_register_switch">' . $tr['view agent register setting'] . '</a>
          </td>
          <td class="text-left">'.$protal_setting_list_sql_result[46]->value.'</td>
        </tr>
        ';


        // -----------------------------------------------------------------------------------------------------------------------------------------------
        //  表格內容 html 組合 end
        // -----------------------------------------------------------------------------------------------------------------------------------------------
      }
    }
  }


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  擴充在 head 的 js 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


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


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  擴充在 head 的 js 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  檔案末端 js start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // switch 修改開關狀態 js
  $extend_js = $extend_js . "
  <script>
	$(document).ready(function() {
		$('.checkbox_switch').click(function() {
      var name = $(this).val();

      if(jQuery.trim(name) != '') {
        $.post('protal_setting_action.php?a=edit_checkbox_switch',
        {
          name: name,
        },
        function(result) {
          $('#preview_result').html(result);
        });
      } else {
        alert('(x)不合法的測試。');
      }
		});
	});
  </script>
  ";


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  檔案末端 js end
  // -----------------------------------------------------------------------------------------------------------------------------------------------



  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  $show_list_html = '';
  $show_list_html = $show_list_html . '
	<div class="tab-content col-12 col-md-12">
	<br>
		<div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
			<table id="inbox_transaction_list" class="table" cellspacing="0" width="100%">
				<thead>
					'.$table_colname_html.'
				</thead>
				<tbody>
					'.$show_listrow_html.'
				</tbody>
			</table>
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


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------

} else {
  // 沒有登入的顯示提示俊息
  $show_transaction_list_html  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

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
