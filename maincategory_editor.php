<?php
// ----------------------------------------------------------------------------
// Features:	後台--遊戲大分類管理
// File Name:	maincategory_editor.php
// Author:		Ian
// Related:		mct_switch_process_action.php
// Log:
// 2019.01.31 新增 Gapi 遊戲清單管理頁籤 Letter
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// var_dump($_SESSION);
// var_dump($gamelobby_setting['main_category_info']);
// var_dump($protalsetting['main_category_info']);

// var_dump(session_id());
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// check permission
if(!isset($_SESSION['agent']) OR !in_array($_SESSION['agent']->account, $su['ops'])){
  http_response_code(404);
  die('(x)不合法的測試');
}

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= $tr['MainCategory Management'];
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
  <li><a href="#">' .$tr['System Management'].' </a></li>
  <li class="active">'.$function_title.'</li>
</ol>';

$lottery_backoffice_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['superuser'])) ? '<li><a href="casino_backoffice.php" target="_self">'.$tr['Lottery Management'].'</a></li>' : '';

// 依權限顯示 GAPI 遊戲清單管理頁籤
$gapi_gamelist_management_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ?
	'<li><a href="gapi_gamelist_management.php" target="_self">'.$tr['gapi gamelist management'].'</a></li>' : '';

$mct_switch_html = '<div class="col-12 tab mb-3">
<ul class="nav nav-tabs">
    <li><a href="casino_switch_process.php" target="_self">
    '.$tr['Casino Management'].'</a></li>
    <li class="active"><a href="" target="_self">
    '.$tr['MainCategory Management'].'</a></li>
    <li><a href="game_management.php" target="_self">
    '.$tr['Game Management'].'</a></li>
    '.$lottery_backoffice_html.
	$gapi_gamelist_management_html.'
  </ul></div>';

// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// 擴充 head 內的 css or js
$extend_head				= $extend_head.'<!-- Jquery UI js+css  -->
                        <script src="in/jquery-ui.js"></script>
                        <link rel="stylesheet"  href="in/jquery-ui.css" >
                        ';

// -----------------------------------------------------------------------------
// 將在遊戲中的會員資料以表格方式呈現 -- from maincategory_editor
// -----------------------------------------------------------------------------


  $mct_switch_html = $mct_switch_html.'<div class="mctswitch col-12">
  <div class="row">
    <div class="col-md-3 switch_div"><b class="switch_font">' . $tr['MainCategory']. '</b></div>
    <div class="col-md-3 switch_div"><b class="switch_font">' . $tr['MainCategory on off']. '<span class="glyphicon glyphicon-info-sign" title="啟/停用主分类"></span></b></div>
    <div class="col-md-3 switch_div"><b class="switch_font">自订排序<span class="glyphicon glyphicon-info-sign" title="主分类在选单上的排序"></span></b></div></div><hr>';

  $mct_item_arr = array();
  foreach($gamelobby_setting['main_category_info'] as $mctid => $mct_arr){
  		if(isset($tr['menu_'.strtolower($mctid)])){
  			$mct_name = $tr['menu_'.strtolower($mctid)];
  		}elseif(isset($tr[$mct_arr['name']])){
  			$mct_name = $tr[$mct_arr['name']];
  		}else{
  			$mct_name = $mct_arr['name'];
  		}
  		// var_dump($mct_name);

      // switch button status
      $mct_btnstatus = ($mct_arr['open'] == '1') ? 'checked' : '';
      // 排序選單
      $order_option = '';
      for($l=1;$l<=count($gamelobby_setting['main_category_info']);$l++){
        $selected = ($mct_arr['order'] == $l)? 'selected':'';
      	$order_option = $order_option.'<option value="'.$l.'" '.$selected.'>'.$l.'</option>';
      }

  		$mct_item_arr[$mct_arr['order']] = <<< HTML
      <div class="row">
        <div class="col-md-3 switch_div"><b class="switch_font">$mct_name</b></div>
        <div class="col-md-3 switch_div">
            <label class="onoffswitch">
              <input type="checkbox" name="{$mctid}" class="onoffswitch-checkbox" id="{$mctid}_switch" onclick="mctswitch('{$mctid}','{$mct_name}');" $mct_btnstatus >
              <div class="slider round"></div></label></div>
        <div class="col-md-3 switch_div">
        <select class="form-control" id="{$mctid}_order" style="width:100px;" value="{$mct_arr['order']}" onchange="mct_orderchg('{$mctid}');">$order_option</select></div></div>
HTML;
  }
    ksort($mct_item_arr);
    $mct_item = implode("\n",$mct_item_arr);

    $mct_switch_js = '
    <script type="text/javascript" language="javascript" class="init">
     function mctswitch(mct_id,mct_name){
       switch_checkbox = document.getElementById(mct_id+\'_switch\');
       if(switch_checkbox.checked)
       {
         if (confirm(mct_name+\'将要启用\'))
         {
     		  $.get("maincategory_editor_action.php",
     		  		{ a: "mct_switch",
                mctstate: 1,
     		        mctid: mct_id},
     		  		function(result){
     		        // replace with the new menu
     		        $("#show_mct_switch").html(result);
                $("#"+mct_id+"_emgswitchicon").css(\'color\',\'white\');
                $("#"+mct_id+"_emgswitch").prop("disabled", true);
     		      }
     		  );
         }else{
           $("#"+mct_id+"_switch").prop("checked", false);
         }
       }
       else
       {
         if (confirm(mct_name+\'将要停用\'))
         {
     		  $.get("maincategory_editor_action.php",
     		  		{ a: "mct_switch",
                mctstate: 0,
                emgsign: 0,
     		        mctid: mct_id},
     		  		function(result){
     		        // replace with the new menu
     		        $("#show_mct_switch").html(result);
                $("#"+mct_id+"_emgswitch").prop("disabled", true);
     		      }
     		  );
         }else{
           $("#"+mct_id+"_switch").prop("checked", true);
         }
       }
     }
     function mct_orderchg(mct_id){
       order = $("#"+mct_id+"_order").val();
       $.get("maincategory_editor_action.php",
          { a: \'mctorder_switch\',
            mctorder: order,
       		  mctid: mct_id},
       		function(result){
       		   // replace with the new menu
       		   location.reload();
       		}
       );
     }
   	</script>';

$switch_css = '
<style type="text/css">
.mctswitch {
  line-height:18px;
}

.switch_div {
  display:inline-block;
  text-align:center;
  margin:5px 0px;
}

.switch_font {
  font-size:18px;
  margin:0px 10px 0px 10px;
}

/* The switch - the box around the slider */
.onoffswitch {
 position: relative;
 display: inline-block;
 width: 30px;
 height: 17px;
 margin:0px 0px 0px 0px;
}

/* Hide default HTML checkbox */
.onoffswitch input {display:none;}

/* The slider */
.slider {
 position: absolute;
 cursor: pointer;
 top: 0;
 left: 0;
 right: 0;
 bottom: 0;
 background-color: #ccc;
 -webkit-transition: .4s;
 transition: .4s;
}

.slider:before {
 position: absolute;
 content: "";
 height: 13px;
 width: 13px;
 left: 2px;
 bottom: 2px;
 background-color: white;
 -webkit-transition: .4s;
 transition: .4s;
}

input:checked + .slider {
 background-color: #2196F3;
}

input:focus + .slider {
 box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
 -webkit-transform: translateX(13px);
 -ms-transform: translateX(13px);
 transform: translateX(13px);
}

/* Rounded sliders */
.slider.round {
 border-radius: 17px;
}

.slider.round:before {
 border-radius: 50%;
}

.processingimg {
 border: 2px solid #f3f3f3; /* Light grey */
 border-top: 2px solid #3498db; /* Blue */
 border-radius: 50%;
 width: 20px;
 height: 20px;
 animation: spin 2s linear infinite;
}

@keyframes spin {
 0% { transform: rotate(0deg); }
 100% { transform: rotate(360deg); }
}
</style>';

// -----------------------------------------------------------------------------
// END -- 將資料以表格方式呈現 -- from maincategory_editor_cmd
// -----------------------------------------------------------------------------

$extend_head = $extend_head.$switch_css.$mct_switch_js;

$mct_switch_html = $mct_switch_html.$mct_item.'</div>';

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
$tmpl['panelbody_content']				= $mct_switch_html;


// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/dashboard.tmpl.php");
//include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");

?>
