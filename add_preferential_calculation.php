<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 新增反水設定
// File Name:	add_preferential_calculation.php
// Author:		Neil
// Related:		對應 preferential_calculation_config.php
// DB Table:  root_favorable.casino_list
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";

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
$casinoLib = new casino_switch_process_lib();
// 功能標題，放在標題列及meta $tr['Add return water setting level setting'] = '新增反水設定等級設定';
$function_title 		= $tr['Add return water setting level setting'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['Home'] = '首頁';
// $tr['System Management'] = '系統管理';$tr['Preferential setting'] = '反水設定';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li><a href="preferential_calculation_config.php">'.$tr['Preferential setting'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

function get_all_favorable_setting_name()
{
  $sql = "SELECT DISTINCT group_name, name FROM root_favorable WHERE deleted = '0' ORDER BY group_name;";
  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  unset($result[0]);

  return $result;
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $casino_gametype_sql = 'SELECT casinoid, game_flatform_list, display_name FROM casino_list WHERE "open" <> 5';
  $casino_gametype_sql_result = runSQLall($casino_gametype_sql);
  // var_dump($casino_gametype_sql_result);
  
  $show_list_html = '';
  if ($casino_gametype_sql_result[0] >= 1) {

    // 生成娛樂城列表與各娛樂城遊戲類別列表
	$casino_names = [];
    for ($i=1; $i <= $casino_gametype_sql_result[0]; $i++) {
      $casino_list[] = $casino_gametype_sql_result[$i]->casinoid;
      $casino_names[$casino_gametype_sql_result[$i]->casinoid] = $casinoLib->getCurrentLanguageCasinoName($casino_gametype_sql_result[$i]->display_name, $_SESSION['lang']);
      $game_flatform_list[$casino_gametype_sql_result[$i]->casinoid] = json_decode($casino_gametype_sql_result[$i]->game_flatform_list, true);
    }
    //$tr['bouns_set_interpret'] = '* 打碼量、反水上限只可為正整數，如有小數點將四捨五入至整數位。<br>* 反水比、稽核倍數只可到小數下兩位，如超過小數下兩位將四捨五入至小數下第二位。<br>* 新增時請確認相同反水設定名稱不可有相同的打碼量。<br>* 新增時，除備註外欄為皆不可留空。<br>';
    // $show_list_html = $show_list_html.'
    // <div id="preview_area" class="alert alert-info" role="alert">'.$tr['bouns_set_interpret'].'</div>';
    $preview_list_html = '
    <div id="preview_area" class="alert alert-info" role="alert">
    * '.$tr['The amount of coding'].'<br>
    * '.$tr['Anti-water ratio'].'<br>
    * '.$tr['The same anti-water setting name'].'<br>
    * '.$tr['The anti-water settings used by existing members and agents cannot be deleted.'].'<br>
    * '.$tr['When modifying, no fields other than remarks can be left blank.'].'<br>
    * '.$tr['Calculation formula'].'：<br>
    # '.$tr['The amount of bonus'].'<br>
    # '.$tr['Responsibility of each agent'] .'<br>
    </div>';

    // $tr['General Settings'] = '一般設定';
    $show_list_html = $show_list_html.'
    <div class="row">
      <div class="col-12 col-md-12">
        <span class="label label-default">' . $tr['setting'] . '</span>
        <hr>
      </div>
    </div>
    ';
    // $tr['bouns_setting name'] = '反水設定名稱'; $tr['Please enter the bouns set name'] = '請輸入反水設定名稱';
    $show_list_html = $show_list_html.'
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>'.$tr['bouns_setting name'].'</strong></td>
      </div>
      <div class="col-12 col-md-3">
        <input type="text" class="form-control validate[required,maxSize[50]]" maxlength="50" id="name" placeholder="'.$tr['Please enter the bouns set name'].'('.$tr['max'].'50'.$tr['word'].')">
      </div>
    </div>
    <br>
    ';
  
    // $tr['Enabled or not'] = '是否啟用';
    $show_list_html = $show_list_html.'
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>'.$tr['Enabled or not'].'</strong></td>
      </div>
      <div class="col-12 col-md-10">
        <td>
          <div class="col-12 col-md-12 status-switch pull-left">
            <input id="status" name="status" class="ststus_checkbox_switch" type="checkbox"/>
            <label for="status" class="label-success"></label>
          </div>
        </td>
      </div>
    </div>
    <br><br>
    ';
  
    // $tr['Preferential setting'] = '反水設定';
    $show_list_html = $show_list_html.'
    <div class="row">
      <div class="col-12 col-md-12">
      <span class="label label-default">' . $tr['Preferential setting'] . '</span>
      <hr>
      </div>
    </div>
    <br>
    ';

    $favorablerate_content_html = '';
    foreach ($casino_list as $casino_key => $casino_value) {
  
      $gametype_html = '';
      $gametype_value_html = '';
  
      foreach ($game_flatform_list[$casino_value] as $game_flatform_key => $game_flatform_value) {

        $gametype_html = $gametype_html.'
        <td>'.$tr[$game_flatform_value].'(%)</td>
        ';
  
        $gametype_value_html = $gametype_value_html.'
        <td>
          <input type="number" step=".01" class="form-control favorablerate" placeholder="" id="'.strtolower($casino_value).'_'.$game_flatform_value.'">
        </td>
        ';
      }

      $favorablerate_content_html = $favorablerate_content_html.'
      <tr>
        <td>
          <strong>'. $casino_names[$casino_value] .'</strong>
        </td>
        <td>
          <table class="table table-bordered">
            <tr class="active text-center">
              '.$gametype_html.'
            </tr>
            <tr>
              '.$gametype_value_html.'
            </tr>
          </table>
        </td>
      </tr>
      ';
    }
  
    // $tr['Casino'] = '娛樂城';$tr['bouns_ratio'] = '反水比';$tr['bouns_max_limit'] = '反水上限';$tr['please input bouns_max_limit'] = '請輸入反水上限';$tr['Please enter the bouns audit times'] = '請輸入反水稽核倍數';
    // $tr['audit multiple'] = '稽核倍數';$tr['Betting amount'] = '打碼量';$tr['please input betting amuount'] = '請輸入打碼量';
    $favorablerate_html = '
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>'.$tr['betting amount'].'</strong></td>
      </div>
      <div class="col-12 col-md-3">
        <input type="number" class="form-control integercheck" id="wager" placeholder="'.$tr['please input betting amuount'].'">
      </div>
    </div>
    <br>
  
    <table class="table table-hover">
      <thead>
        <th width="15%" class="text-center">'.$tr['Casino'].'</th>
        <th width="75%" class="text-center">'.$tr['bouns_ratio'].'</th>
      </thead>
      '.$favorablerate_content_html.'
    </table>
  
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>'.$tr['bouns_max_limit'].'</strong></td>
      </div>
      <div class="col-12 col-md-3">
        <input type="number" class="form-control integercheck" id="upperlimit" placeholder="'.$tr['please input bouns_max_limit'].'">
      </div>
    </div>
    <br>
  
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>'.$tr['audit multiple'].'</strong></td>
      </div>
      <div class="col-12 col-md-3">
        <input type="number" step=".01" class="form-control" id="audit" placeholder="'.$tr['Please enter the bouns audit times'].'">
      </div>
    </div>
    <br><br>
    ';
  
    $show_list_html = $show_list_html.'
    <div class="row">
      <div class="col-12 col-md-12">
        '.$favorablerate_html.'
      </div>
    </div>
    ';
  
    // $tr['other'] = '其他';
    $show_list_html = $show_list_html.'
    <div class="row">
      <div class="col-12 col-md-12">
      <span class="label label-default">' . $tr['other'] .'</span>
      </div>
    </div>
    <hr>
    ';
  // $tr['Remark'] = '備註';
    $show_list_html = $show_list_html.' 
    <div class="row">
      <div class="col-12 col-md-2">
        <td><strong>'.$tr['Remark'].'</strong></td>
      </div>
      <div class="col-12 col-md-6">
        <textarea class="form-control validate[maxSize[500]]" rows="5" id="notes" maxlength="500" placeholder="('.$tr['max'].'500'.$tr['word'].')"></textarea>
      </div>
    </div>
    <br><br>
    ';
  
    // $tr['Save'] = '儲存';$tr['Cancel'] = '取消';
    $btn_html = '
    <p align="right">
      <button id="submit_preferential_setting" class="btn btn-success">'.$tr['Save'].'</button>
      <button class="btn btn-danger" onclick="javascript:location.href=\'./preferential_calculation_config.php\'">'.$tr['Cancel'].'</button>
    </p>';

    $form_list='';
  
    $form_list = $form_list.'
      <form class="form-horizontald" role="form" id="preferential_form">
        '.$show_list_html.'
      </form>
    ';

    $indexbody_content = $indexbody_content.'
    <div class="row">
      <div class="col-12 col-md-12">
      '.$preview_list_html.'  
      '.$form_list.'
      </div>
    </div>
    <hr>
    '.$btn_html.'
    <br>
    <div class="row">
      <div id="preview_result"></div>
    </div>
    ';
  
  
    // 將 checkbox 堆疊成 switch 的 css
    $extend_head = $extend_head. <<<HTML
    <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
    <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

    <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function () {
            $("#preferential_form").validationEngine();
        });
    </script>
HTML;    
$extend_head = $extend_head."<style>
  
    .status-switch > input[type=\"checkbox\"] {
        visibility:hidden;
    }
  
    .status-switch > label {
        cursor: pointer;
        height: 0px;
        position: relative;
        width: 40px;
    }
  
    .status-switch > label::before {
        background: rgb(0, 0, 0);
        box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
        border-radius: 8px;
        content: '';
        height: 16px; 
        margin-top: -8px;
        margin-left: -30px;
        position:absolute;
        opacity: 0.3;
        transition: all 0.4s ease-in-out;
        width: 30px;
    }
    .status-switch > label::after {
        background: rgb(255, 255, 255);
        border-radius: 16px;
        box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
        content: '';
        height: 16px;
        left: -4px;
        margin-top: -8px;
        margin-left: -30px;
        position: absolute;
        top: 0px;
        transition: all 0.3s ease-in-out;
        width: 16px;
    }
    .status-switch > input[type=\"checkbox\"]:checked + label::before {
        background: inherit;
        opacity: 0.5;
    }
    .status-switch > input[type=\"checkbox\"]:checked + label::after {
        background: inherit;
        left: 20px;
    }
  
    </style>
    ";
  
  
    $extend_js = $extend_js."
    <script>
    $(document).ready(function() {
      
      $('input[type=number]').blur(function(){ 
        if ( $(this).val() >= 0 ){
          $(this).removeClass('alert alert-danger negative_number mb-0');
        }else if ( $(this).val() < 0 ){            
          $(this).addClass('alert alert-danger negative_number mb-0');
        }
      });
      //正整數判斷
      var integer = /^[0-9]\d*$/;
      $('.integercheck').blur(function(){ 
        if ( integer.test($(this).val()) == true){
          $(this).removeClass('alert alert-danger not_integer mb-0');
        }else if ( integer.test($(this).val()) == false && $(this).val() != '') {
          $(this).addClass('alert alert-danger not_integer mb-0');
        }             
      });


      $('#submit_preferential_setting').click(function() {
        var name = $('#name').val();
        
        if($('#status').prop('checked')) {
          var status = 1;
        } else {
          var status = 0;
        }
  
        var wager = $('#wager').val();
        var upperlimit = $('#upperlimit').val();
        var audit = $('#audit').val();
        var notes = $('#notes').val();
  
        var inputArray=$(\"input[class='form-control favorablerate']\");
        var m = new Map();
        favorablerate_arr = {};
        inputArray.each (
          function() {
            var input =$(this);
            var id = input.attr('id');
            var val = $('#'+input.attr('id')).val();
            m.set(id, val);
          });
          
          m.forEach((value, key) => {
            var keys = [key];
            var last = keys.pop();
            keys.reduce((r, a) => {}, favorablerate_arr)[last] = value;
          });
  
          var favorablerate_json = JSON.stringify(favorablerate_arr);
          if( $('input[type=number]').hasClass('negative_number') == true ){
              alert('设定值不可为负数');
          }else if( $('input[type=number]').hasClass('not_integer') == true ){
              alert('打码量，反水上限只可为正整数');
          }else{
            $.post('preferential_calculation_config_deltail_action.php?a=add_favorable_setting',
              {
                name: name,
                status: status,
                wager: wager,
                upperlimit: upperlimit,
                audit: audit,
                notes: notes,
                favorablerate_json: favorablerate_json
              },
              function(result){
                $('#preview_result').html(result);}
            );
          }
      });
    });
    </script>
    ";

  } else { //$tr['Casino and Game Category Query Error.'] = '娛樂城與遊戲類別查詢錯誤。';
    $show_transaction_list_html  = '(x) '.$tr['Casino and Game Category Query Error'];
    
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
  
} else {
  // 沒有登入的顯示提示俊息 $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
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
