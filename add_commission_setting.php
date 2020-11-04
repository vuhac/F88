<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 新增反水設定
// File Name:	add_commission_setting.php
// Author:		Neil
// Related:		對應 commission_setting.php
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

require_once dirname(__FILE__) ."/commission_lib.php";

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
$function_title 		= $tr['Added commission setting'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['System Management'] = '系統管理';
// $tr['Commission setting'] = '佣金設定';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['homepage'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li><a href="commission_setting.php">'.$tr['Commission setting'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $show_list_html = '';

  $show_list_html = $show_list_html.'
  <div id="preview_area" class="alert alert-info" role="alert">'.$tr['Promotions, returns, payouts, valid members'].'</div>';

  // $tr['Commission Set Name'] = '佣金設定名稱';
  // $tr['Please enter a commission setting name'] = '請輸入佣金設定名稱'
  $show_list_html = $show_list_html.'
  <div class="row">
    <div class="col-12 col-md-12">
      <span class="label label-default">'.$tr['General Settings'].'</span>
    </div>
  </div>
  <hr>
  ';

  $show_list_html = $show_list_html.'
  <div class="row">
    <div class="col-12 col-md-2">
      <strong>'.$tr['Commission Set Name'].'</strong>
    </div>
    <div class="col-12 col-md-3">
      <input type="text" class="form-control validate[required,maxSize[50]]" maxlength="50" id="name" placeholder="'.$tr['Please enter a commission setting name'].'('.$tr['max'].'50'.$tr['word'].')" value="">
    </div>
  </div>
  <br>
  ';



  // $tr['Enabled or not'] = '是否啟用';
  $show_list_html = $show_list_html.'
  <div class="row">
    <div class="col-12 col-md-2">
      <strong>'.$tr['Enabled or not'].'</strong>
    </div>
    <div class="col-12 col-md-10">
      <td>
        <div class="col-12 col-md-12 status-switch pull-left">
          <input id="status" name="status" class="ststus_checkbox_switch" type="checkbox" />
          <label for="status" class="label-success"></label>
        </div>
        <div class="col-12 col-md-8"></div>
      </td>
    </div>
  </div>
  <br>
  ';
  // $tr['Please enter the minimum bet amount'] = '請輸入最低投注額';
  $show_list_html = $show_list_html.'
  <div class="row">
    <div class="col-12 col-md-2">
      <strong>'.$tr['effective member minimum bet amount'].'</strong>
    </div>
    <div class="col-12 col-md-3">
      <input type="number" min="0" class="form-control integercheck" id="lowest_bet" placeholder="'.$tr['Please enter the minimum bet amount'].'" value="">
    </div>
  </div>
  <br>';

  // $tr['Please enter the minimum deposit amount'] = '請輸入最低存款金額';
  $show_list_html = $show_list_html.'
  <div class="row">
    <div class="col-12 col-md-2">
      <strong>'.$tr['effective member minimum deposit amount'].'</strong>
    </div>
    <div class="col-12 col-md-3">
      <input type="number" min="0" class="form-control integercheck" id="lowest_deposit" placeholder="'.$tr['Please enter the minimum deposit amount'].'" value="">
    </div>
  </div>
  <br><br>';

  $show_list_html = $show_list_html.'
  <hr>
  <div class="row">
    <div class="col-12 col-md-12">
      <span class="label label-default">'.$tr['Commission setting'].'</span>
    </div>
  </div>
  <br>
  ';

  $casino_gametype_list = get_casinolist();
  // $tr['bonus ratio'] = '退佣比';
  // $tr['Casino and Game Category Query Error.'] = '娛樂城與遊戲類別查詢錯誤。';
  if ($casino_gametype_list != null) {
    $commission_content_html = get_commission_list($casino_gametype_list['casino'], $casino_gametype_list['game_flatform'], '', 'add', $casino_gametype_list['casinoNames']);
    $commission_html = '
    <table class="table table-hover">
      <thead>
        <th width="15%" class="text-center">'.$tr['Casino'].'</th>
        <th width="75%" class="text-center">'.$tr['bonus ratio'].'</th>
      </thead>
      '.$commission_content_html.'
    </table>
    ';
  } else {
    $commission_html = '<div class="text-danger">(x) '.$tr['Casino and Game Category Query Error.'].'</div>';
  }
  // $tr['Payout'] = '派彩';
  // $tr['Please enter payout'] = '請輸入派彩';
  // $tr['valid member'] = '有效會員';
  // $tr['Please enter a valid member'] = '請輸入有效會員';
  // $tr['discount'] = '優惠';
  // $tr['Please enter discount'] = '請輸入優惠';
  // $tr['Bonus'] = '反水';
  // $tr['Please enter the Bonus'] = '請輸入反水';
  $commission_setting_table_html = '
  <div class="row">
    <div class="col-12 col-md-2">
      <strong>'.$tr['Payout'].'</strong>
    </div>
    <div class="col-12 col-md-3">
      <input type="number" min="0" class="form-control integercheck" id="payoff" placeholder="'.$tr['Please enter payout'].'" value="">
    </div>
  </div>
  <br>

  <div class="row">
    <div class="col-12 col-md-2">
      <strong>'.$tr['valid member'].'</strong>
    </div>
    <div class="col-12 col-md-3">
      <input type="number" min="0" class="form-control integercheck" id="effective_member" placeholder="'.$tr['Please enter a valid member'].'" value="">
    </div>
  </div>
  <br>

';

// 會員端 存款投注佣金
// 如果 關閉 = 隱藏存款投注佣金設定:下線全有效會員最低投注額、下線全有效會員忖款退傭比
  $show_html = '';
  $depositbet_switch = get_depositbet(); // 取存款投注佣金開關值

  if($depositbet_switch['status'] == 'on'){
    $show_html = depositbet_html_on($depositbet_switch);
  }else{
    // 隱藏
    $show_html = depositbet_html_off($depositbet_switch);
  }

  // 原版
// $commission_setting_table_html .= '
//   <div class="row">
//     <div class="col-12 col-md-3">
//       <strong>'.$tr['Offline full effective member minimum bet amount'].'</strong>
//     </div>
//     <div class="col-12 col-md-3">
//       <input type="number" min="0" class="form-control" id="downline_effective_bet" placeholder="'.$tr['Offline full effective member minimum bet amount'].'" value="">
//     </div>
//   </div>
//   <br>

//   <div class="row">
//     <div class="col-12 col-md-3">
//       <strong>'.$tr['Downline full effective member deposit rebate ratio (%)'].'</strong>
//     </div>
//     <div class="col-12 col-md-3">
//       <input type="number" min="0" class="form-control" id="downline_deposit" placeholder="'.$tr['Downline full effective member deposit rebate ratio (%)'].'" value="">
//     </div>
//   </div>
//   <br>
// ';

$commission_setting_table_html .= '

'.$show_html.'
  <div>
    '.$commission_html.'
  </div>
  <br>
  <div class="row">
  <div class="col-12 col-md-2">
    <strong>'.$tr['discount'].'</strong>
  </div>
  <div class="col-12 col-md-3">
    <input type="number" min="0" class="form-control integercheck" id="offer" placeholder="'.$tr['Please enter discount'].'" value="">
  </div>
</div>
<br>

<div class="row">
  <div class="col-12 col-md-2">
    <strong>'.$tr['Bonus'].'</strong>
  </div>
  <div class="col-12 col-md-3">
    <input type="number" step=".01" min="0" class="form-control integercheck" id="favorable" placeholder="'.$tr['Please enter the Bonus'].'" value="">
  </div>
</div>
<br><br>
  ';


  $show_list_html = $show_list_html.'
  <div class="row">
    <div class="col-12 col-md-12">
      '.$commission_setting_table_html.'
    </div>
  </div>
  ';

  // $tr['Cancel'] = '取消';
  $btn_html = '
  <p align="right">
    <button id="submit_commission_setting" class="btn btn-success">'.$tr['Save'].'</button>
    <button id="submit_change_member_data" class="btn btn-danger" onclick="javascript:location.href=\'./commission_setting.php\'">'.$tr['Cancel'].'</button>
  </p>';


  $indexbody_content = $indexbody_content.'
  <form id="commission_form"
    <div class="row">
      <div class="col-12 col-md-12">
      '.$show_list_html.'
      </div>
    </div>
    <hr>
    '.$btn_html.'
    <br>
    <div class="row">
      <div id="preview_result"></div>
    </div>
  </form>
  ';


  // 將 checkbox 堆疊成 switch 的 css
  $extend_head = $extend_head. <<<HTML
  <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
  <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
  <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

  <script type="text/javascript" language="javascript" class="init">
      $(document).ready(function () {
          $("#commission_form").validationEngine();
      });
  </script>
HTML;    

  $extend_head = $extend_head. "
  <style>

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

    $('#submit_commission_setting').click(function() {
      var name = $('#name').val();

      if($('#status').prop('checked')) {
        var status = 1;
      } else {
        var status = 0;
      }

      var lowest_bet = $('#lowest_bet').val();
      var lowest_deposit = $('#lowest_deposit').val();
      var payoff = $('#payoff').val();
      var effective_member = $('#effective_member').val();
      var offer = $('#offer').val();
      var favorable = $('#favorable').val();
      var downline_effective_bet = $('#downline_effective_bet').val();
      var downline_deposit = $('#downline_deposit').val();

      var inputArray=$(\"input[class='form-control commission']\");
      var m = new Map();
      commission_arr = {};
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
        keys.reduce((r, a) => {}, commission_arr)[last] = value;
      });

      var commission_json = JSON.stringify(commission_arr);
      if( $('input[type=number]').hasClass('negative_number') == true ){
        alert('设定值不可为负数');
      }else if( $('input[type=number]').hasClass('not_integer') == true ){
          alert('优惠、反水、派彩、有效会员、有效会员最低投注额及最低存款金额只可为正整数');
      }else{
      $.post('commission_setting_deltail_action.php?a=add_commission_setting',
      {
        name: name,
        status: status,
        lowest_bet: lowest_bet,
        lowest_deposit: lowest_deposit,
        payoff: payoff,
        effective_member: effective_member,
        offer: offer,
        favorable: favorable,
        commission_json: commission_json,
        downline_effective_bet:downline_effective_bet,
        downline_deposit:downline_deposit
      },
      function(result){
        $('#preview_result').html(result);
      });
    }
    });
  });

  </script>
  ";

} else {
  // 沒有登入的顯示提示俊息
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
include("template/beadmin.tmpl.php");
