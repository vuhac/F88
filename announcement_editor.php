<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 優惠編輯器
// File Name:	announcement_editor.php
// Author:    Pia
// Related:
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
$function_title 		= $tr['announcement message management'].$tr['detail info'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置  $tr['Home'] = '首頁'; $tr['System Management'] = '系統管理';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['profit and promotion'].'</a></li>
  <li><a href="announcement_admin.php">'.$tr['announcement message management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
  $tz = $_SESSION['agent']->timezone;
}else{
  $tz = '+08';
}

if (isset($_GET['s']) AND $_SESSION['agent']->therole == 'R' AND filter_var($_GET['s'], FILTER_VALIDATE_INT)) {

  $announcement_sql = "SELECT *, to_char((effecttime AT TIME ZONE '$tzonename'),'YYYY/MM/DD') AS effecttime, to_char((endtime AT TIME ZONE '$tzonename'),'YYYY/MM/DD') AS endtime FROM root_announcement WHERE id = '".$_GET['s']."';";
  $announcement_sql_r = runSQLall($announcement_sql);

  $classification = array('1' => '', '2' => '', '3' => '');
  if ($announcement_sql_r[0] == 1) {

    //狀態是否有開啟
    if ($announcement_sql_r[1]->status == '1') {
      $is_show = 'checked';
    } elseif ($announcement_sql_r[1]->status == '0') {
      $is_show = '';
    }

    //站內信件顯示公告--狀態是否有開啟
    if ($announcement_sql_r[1]->showinmessage == '1') {
      $is_showinmessage = 'checked';
    } elseif ($announcement_sql_r[1]->showinmessage == '0') {
      $is_showinmessage = '';
    }


    $announcement['id']                 = $_GET['s'];
    //顯示標題名稱
    $announcement['title']              = $announcement_sql_r[1]->title;
    //內容
    $announcement['content']            = htmlspecialchars_decode($announcement_sql_r[1]->content);
    //狀態
    $announcement['status']             = $is_show;
    //開始時間
    $announcement['effecttime']         = $announcement_sql_r[1]->effecttime;
    //結束時間
    $announcement['endtime']            = $announcement_sql_r[1]->endtime;
    //處理人員
    $announcement['processingaccount']  = $announcement_sql_r[1]->operator;
    //名稱
    $announcement['name']               = $announcement_sql_r[1]->name;
    //站內信件顯示公告
    $announcement['showinmessage']      = $is_showinmessage;

  } else {//$tr['Messages'] = '訊息'; $tr['Query failed'] = '查詢失敗。';
    $logger = $tr['Messages'].$tr['Query failed'];
    echo '<script>alert("'.$logger.'");location.href="/announcement_admin.php";</script>';
  }

} elseif($_GET['s'] == 'add' AND $_SESSION['agent']->therole == 'R'){
  //  新增公告


  $today = gmdate('Y-m-d',time() + $tz * 3600);
  $one_year_later = date('Y-m-d', strtotime("$today +1 year"));

  $announcement['id']='';
  //顯示標題名稱
  $announcement['title']='';
  //內容
  $announcement['content']='';
  //狀態
  $announcement['status']='';
  //開始時間
  $announcement['effecttime']='';
  //結束時間
  $announcement['endtime']='';
  //處理人員
  $announcement['processingaccount']='';
  //名稱
  $announcement['name']='';
  //站內信件顯示公告
  $announcement['showinmessage']='';


}else{
  //錯誤操作
  $logger = $tr['Messages'].$tr['Query failed'];
  echo '<script>alert("'.$logger.'");location.href="/announcement_admin.php";</script>';
}


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
$show_list_html = '';
// -----------------------------------------------------------------------------------------------------------------------------------------------
//  html 組合 start
// -----------------------------------------------------------------------------------------------------------------------------------------------

// $tr['announcement'] = '公告';  $tr['name'] = '名稱'; $tr['Please fill in the announcement name'] = '請填入公告名稱。';
  // $show_list_html	= $show_list_html.'
  // <div class="row">
  // 	<div class="col-12 col-md-1"><p class="text-right">'.$tr['announcement'].$tr['name'].'</p></div>
  // 	<div class="col-12 col-md-4">
  // 		<input type="text" class="form-control" id="announcement_name" placeholder="'.$tr['Please fill in the announcement name'].'" value="'.$announcement['name'].'">
  // 	</div>
  // 	<div class="col-12 col-md-7"></div>
  // </div>
  // <br>
  // ';
// $tr['Announcement title'] = '公告標題'; $tr['Please fill in the display announcement title'] = '請填入顯示公告標題。';
  $show_list_html = $show_list_html.'
  <div class="row">
  	<div class="col-12 col-md-1"><p class="text-right">'.$tr['title'].'</p></div>
      <div class="col-12 col-md-4">
        <form id="title_form">
          <input type="text" class="form-control validate[maxSize[50]]" maxlength="50" id="announcement_title" placeholder="'.$tr['Please fill in the display announcement title'].'('.$tr['max'].'50'.$tr['word'].')" value="'.$announcement['title'].'">
        </form>  
  		</div>
  	<div class="col-12 col-md-7"></div>
  </div><br>
  ';

  // 預設 今天日期 和 今天日期 + 1 年 $tr['Sales time'] = '上架時間';

  $show_list_html	= $show_list_html.'
  <div class="row">
  	<div class="col-12 col-md-1"><p class="text-right">'.$tr['Sales time'].'</p></div>
  	<div class="col-12 col-md-4">
  		<div class="input-group">
        <input type="text" class="form-control" placeholder="start" aria-describedby="basic-addon1" id="start_day" value="'.$announcement['effecttime'].'">
        <span class="input-group-addon" id="basic-addon1">~</span>
        <input type="text" class="form-control" placeholder="end" aria-describedby="basic-addon1" id="end_day" value="'.$announcement['endtime'].'">
      </div>
  	</div>
  	<div class="col-12 col-md-7"></div>
  </div>
  <br>
  ';

  // $tr['Enabled or not'] = '是否啟用';
  $show_list_html	= $show_list_html.'
  <div class="row">
  	<div class="col-12 col-md-1"><p class="text-right">'.$tr['Enabled or not'].'</p></div>
  	<div class="col-12 col-md-4 material-switch">
  		<input id="announcement_isopen" name="announcement_isopen" class="checkbox_switch" value="0" type="checkbox" '.$announcement['status'].'/>
      <label for="announcement_isopen" class="label-success"></label>
  	</div>
  	<div class="col-12 col-md-7"></div>
  </div>
  <br>
  ';
  // //站內信件顯示公告 $tr['Station letter display announcement'] = '站內信件顯示公告';
  // $show_list_html = $show_list_html.'
  // <div class="row">
  //   <div class="col-12 col-md-1"><p class="text-right">'.$tr['Station letter display announcement'].'</p></div>
  //   <div class="col-12 col-md-4 material-switch">
  //     <input id="announcement_showinmessage" name="announcement_showinmessage" class="checkbox_switch" value="0" type="checkbox" '.$announcement['showinmessage'].'/>
  //     <label for="announcement_showinmessage" class="label-success"></label>
  //   </div>
  //   <div class="col-12 col-md-7"></div>
  // </div>
  // <br>
  // ';

  // ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
  // 取得日期的 jquery datetime picker -- for birthday
  $extend_head = $extend_head. <<<HTML
	<script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
	<script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
	<link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

	<script type="text/javascript" language="javascript" class="init">
		$(document).ready(function () {
			$("#title_form").validationEngine();
		});
	</script>
HTML; 
  $extend_head = $extend_head.'<link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>';
  $extend_js = $extend_js.'<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>';

  // date 選擇器 https://jqueryui.com/datepicker/
  // http://api.jqueryui.com/datepicker/
  // 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
  $dateyearrange_start 	= date("Y/m/d");
  $dateyearrange_end 		= date("Y") + 50;
  $datedefauleyear		= date("Y/m/d");

  $extend_js = $extend_js."
  <script>
  // for select day
  $('#start_day, #end_day').datetimepicker({
    defaultDate:'".$datedefauleyear."',
    minDate: '".$dateyearrange_start."',
    maxDate: '".$dateyearrange_end."/01/01',
    timepicker:false,
    format:'Y/m/d',
    lang:'en'
  });
  </script>
  ";

  // 編輯的內文
  // $editor_content_value = '<h1>Hello world!</h1>';
  $editor_content_value = $announcement['content'];

  // 引入 ckeditor editor $tr['Announcement content'] = '公告內容';
  $show_list_html = $show_list_html.'

  <div class="row">
  	<div class="col-12 col-md-1"><p class="text-right">'.$tr['Announcement content'].'</p></div>
  	<div class="col-12 col-md-10 material-switch">
  		<main>
      <div class="adjoined-bottom">
        <div class="grid-container">
          <div class="grid-width-100">
            <div id="editor">
              '.$editor_content_value.'
            </div>
          </div>
        </div>
      </div>
      </main>
      <input type="hidden" id="editor_data_id" value="1122">
  	</div>
  	<div class="col-12 col-md-1"></div>
  </div>
  <br>
  ';
  // $tr['Save'] = '儲存'; $tr['Cancel'] = '取消';
  $show_list_html = $show_list_html.'

  <div class="row">
  	<div class="col-12 col-md-10">
      <p class="text-right">
        <button id="submit_to_edit" class="btn btn-success"><span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span>&nbsp;'.$tr['Save'].'</button>
        <button id="remove_to_edit" class="btn btn-danger" onclick="javascript:location.href=\'announcement_admin.php\'"><span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span>&nbsp;'.$tr['Cancel'].'</button>
      </p>
    </div>
  </div>
  ';


  //引用 ckeditor js
  $extend_js = $extend_js.'
  <script src="in\ckeditor\ckeditor.js"></script>
  ';


  // 引用 ckeditor sdk http://sdk.ckeditor.com/index.html
  $extend_js = $extend_js."
  <script>

  if ( CKEDITOR.env.ie && CKEDITOR.env.version < 9 )
  CKEDITOR.tools.enableHtml5Elements( document );

  // The trick to keep the editor in the sample quite small
  // unless user specified own height.
  CKEDITOR.config.height = 150;
  CKEDITOR.config.width = 'auto';

  var texteditor = ( function() {
    var wysiwygareaAvailable = isWysiwygareaAvailable(),
      isBBCodeBuiltIn = !!CKEDITOR.plugins.get( 'bbcode' );

    return function() {
      var editorElement = CKEDITOR.document.getById( 'editor' );

      // Depending on the wysiwygare plugin availability initialize classic or inline editor.
      if ( wysiwygareaAvailable ) {
        CKEDITOR.replace( 'editor' );
      } else {
        editorElement.setAttribute( 'contenteditable', 'true' );
        CKEDITOR.inline( 'editor' );

        // TODO we can consider displaying some info box that
        // without wysiwygarea the classic editor may not work.
      }
    };

    function isWysiwygareaAvailable() {
      // If in development mode, then the wysiwygarea must be available.
      // Split REV into two strings so builder does not replace it :D.
      if ( CKEDITOR.revision == ( '%RE' + 'V%' ) ) {
        return true;
      }

      return !!CKEDITOR.plugins.get( 'wysiwygarea' );
    }
  } )();

  // 啟動
  texteditor();

  </script>

  ";

  // 送出資料到 $tr['Please confirm the name、title、contents of the announcement are correctly entered'] = '請確認公告名稱、公告標題及公告內容是否正確填入。';
  $extend_js = $extend_js."
  <script>
  $(document).ready(function() {
    $('#submit_to_edit').click(function(){
      var editor_data =  CKEDITOR.instances.editor.getData();
      // if ( CKEDITOR.instances.editor.getData() == '' ){
      //   alert( 'There is no data available.' );
      // }

      var editor_data_id  = $('#editor_data_id').val();
      var announcement_id       = '".$announcement['id']."';
      var announcement_title    = $('#announcement_title').val();
      var start_day             = $('#start_day').val();
      var end_day                = $('#end_day').val();

      if($('#announcement_isopen').prop('checked')) {
        var announcement_isopen = 1;
      }else{
        var announcement_isopen = 0;
      }

      if($('#announcement_showinmessage').prop('checked')) {
        var announcement_showinmessage = 1;
      }else{
        var announcement_showinmessage = 0;
      }

      if(jQuery.trim(announcement_title) != '' && jQuery.trim(editor_data) != '') {
        $.post('announcement_editor_action.php?a=edit_offer',
          {
            editor_data: editor_data ,
            editor_data_id: editor_data_id,

            announcement_id: announcement_id,
            announcement_title: announcement_title,
            announcement_isopen: announcement_isopen,
            announcement_showinmessage: announcement_showinmessage,
            start_day: start_day,
            end_day: end_day
          },
          function(result){
            $('#preview_result').html(result);
          }
        );
      } else {
        alert('".$tr['Please confirm the name、title、contents of the announcement are correctly entered']."');
      }
    });
  })
  </script>
  ";

  // $extend_js = $extend_js."
  // <script>
  // $(document).ready(function() {
  //   $('#submit_to_edit').click(function(){
  //     var editor_data =  CKEDITOR.instances.editor.getData();
  //     // if ( CKEDITOR.instances.editor.getData() == '' ){
  //     //   alert( 'There is no data available.' );
  //     // }

  //     var editor_data_id  = $('#editor_data_id').val();

  //     var announcement_id       = '".$announcement['id']."';
  //     var announcement_name     = $('#announcement_name').val();
  //     var announcement_title    = $('#announcement_title').val();
  //     var start_day             = $('#start_day').val();
  //     var end_day                = $('#end_day').val();

  //     if($('#announcement_isopen').prop('checked')) {
  //       var announcement_isopen = 1;
  //     }else{
  //       var announcement_isopen = 0;
  //     }

  //     if($('#announcement_showinmessage').prop('checked')) {
  //       var announcement_showinmessage = 1;
  //     }else{
  //       var announcement_showinmessage = 0;
  //     }

  //     if(jQuery.trim(announcement_name) != '' && jQuery.trim(announcement_title) != '' && jQuery.trim(editor_data) != '') {
  //       $.post('announcement_editor_action.php?a=edit_offer',
  //         {
  //           editor_data: editor_data ,
  //           editor_data_id: editor_data_id,

  //           announcement_id: announcement_id,
  //           announcement_name: announcement_name,
  //           announcement_title: announcement_title,
  //           announcement_isopen: announcement_isopen,
  //           announcement_showinmessage: announcement_showinmessage,
  //           start_day: start_day,
  //           end_day: end_day
  //         },
  //         function(result){
  //           $('#preview_result').html(result);
  //         }
  //       );
  //     } else {
  //       alert('".$tr['Please confirm the name、title、contents of the announcement are correctly entered']."');
  //     }
  //   });
  // })
  // </script>
  // ";





  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  沒有任何要求 列出系統中所有的入款帳戶資訊 END
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  //都沒有以上的動作 顯示錯誤訊息
  }else{//$tr['Wrong operation'] = '錯誤的操作';
    $show_list_html  = '(x) '.$tr['Wrong operation'].'';
  }

  // 切成 1 欄版面
  $indexbody_content = '';
  $indexbody_content = $indexbody_content.'
	<div class="row">
    <div class="col-12 col-md-1"></div>
		<div class="col-12 col-md-11">
		'.$show_list_html.'
		</div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';


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
$tmpl['paneltitle_content'] 			= '<div class="d-flex align-items-center"><a href="announcement_admin.php" class="btn btn-outline-secondary btn-xs mr-2"><i class="fas fa-reply mr-1"></i>'.$tr['return'].'</a>'.$function_title.'</div>';
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");
?>
