<?php

// ----------------------------------------------------------------------------
// Features:	後台--管理員會員建立功能
// File Name:	member_create.php
// Author:		Barkley
// Related:   member_create_action.php
// Log:
//
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

//var_dump($_SESSION);
// var_dump(session_id());

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
// $tr['Add affiliate associates'] = '新增加盟聯營股東';
$extend_head					= '';
$extend_js						= '';
$function_title				= $tr['Add affiliate associates'];
// 主要內容 -- title
$panelbody_content		= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['Members and Agents'] = '會員與加盟聯營股東';
// $tr['Home'] = '首頁';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Members and Agents'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


// 產生顯示的行列資訊
function get_row_html($row_name, $id, $placeholder_text) {
  $html = '
  <div class="row">
  	<div class="col-12 col-md-2"><p class="text-right">'.$row_name.'</p></div>
  		<div class="col-12 col-md-4">
  			<input type="text" class="form-control" id="'.$id.'" placeholder="'.$placeholder_text.'">
  		</div>
  	<div class="col-12 col-md-6"></div>
  </div>
  <div>&nbsp;</div>
  ';

  return $html;
}

/*
  *抓取預設會員等級設定資訊
*/
function default_member_grade_setting(){
  $sql = "SELECT * FROM root_member_grade WHERE id='1';";
  $result = runSQLall($sql);
  
  return $result[1];
}


/*
  *抓取預設反水設定資訊
*/
function default_favorable_setting(){
  $sql = "SELECT * FROM root_favorable WHERE id=(SELECT min(id) FROM root_favorable WHERE name='預設反水設定');";
  $result = runSQLall($sql);

  return $result[1];
}


/*
  *抓取預設佣金設定資訊
*/
function default_commission_setting(){
  $sql = "SELECT * FROM root_commission WHERE id=(SELECT min(id) FROM root_commission WHERE name='預設佣金設定');";
  $result = runSQLall($sql);

  return $result[1];
}


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // -----------------------
  // 新增會員 - 必填
  // -----------------------
  $panelbody_content		= $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-12">
  	<span class="label label-danger btn-lg">
  		<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>'.$tr['required'].'
  	</span>
  	<hr>
  	</div>
  </div>
  ';

  // $tr['Add shareholder account'] = '新增股東帳號';
  $panelbody_content		= $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-2"><p class="text-right">* '.$tr['Add shareholder account'].'</p></div>
  	<div class="col-12 col-md-4">
  		<input type="text" class="form-control" id="account_create_input" placeholder="Account"  required>
  	</div>
  	<div class="col-12 col-md-6"><div id="account_create_result"></div></div>
  </div>
  <br>
  ';

  // $tr['upper shareholder account'] = '上層股東帳號';
  // $tr['Please fill in the upper shareholder account'] = '請填入上層股東帳號';
  // $system_config['default_agent'] = 'bigagent';
  $panelbody_content = $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-2"><p class="text-right">* '.$tr['upper shareholder account'].'</p></div>
  	<div class="col-12 col-md-4">
      <input type="text" class="form-control" id="agent_account_input" placeholder="'.$tr['Please fill in the upper shareholder account'].'" required value="'.$system_config['default_agent'].'">
      <label class="text-ellipsis ng-binding" title="'.$tr['general agent'].'">
        <input type="checkbox" name="lv_one_agent_checkbox" value="1" class="lv_one_agent_checkbox">' . $tr['general agent'] . '
      </label>
  	</div>
  	<div class="col-12 col-md-6"><div id="agent_account_result"></div></div>
  </div>
  <br>
  ';

  $mamber_grade_sql = "SELECT id, gradename FROM root_member_grade WHERE status = '1' ORDER BY id;";
  $mamber_grade_sql_result = runSQLall($mamber_grade_sql);
  $default_member_grade = default_member_grade_setting();

  $grade_select_option = '';
  if ($mamber_grade_sql_result >= 1) {
    for ($i=1; $i <= $mamber_grade_sql_result[0]; $i++) {
      if($mamber_grade_sql_result[$i]->id == $default_member_grade->id){
        $grade_select_option = $grade_select_option.'<option value="'.$mamber_grade_sql_result[$i]->id.'" selected>'.$mamber_grade_sql_result[$i]->gradename.'</option>';
      }else{
        $grade_select_option = $grade_select_option.'<option value="'.$mamber_grade_sql_result[$i]->id.'">'.$mamber_grade_sql_result[$i]->gradename.'</option>';
      }
    }
  } else {
    // $tr['Member Level have not data in database'] = '會員等級資料表格尚未設定。';
    $logger = $tr['Member Level have not data in database'];
    die($logger);
  }
  // $tr['default membership level'] = '預設會員等級';
  $panelbody_content = $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-2"><p class="text-right">* '.$tr['default membership level'].'</p></div>
  		<div class="col-12 col-md-4">
  			<select id="member_grade_select" name="member_grade_select" class="form-control">
  			  '.$grade_select_option.'
  			</select>
  		</div>
  	<div class="col-12 col-md-6"></div>
  </div><br>
  ';

  $preferential_calculation__sql = "SELECT DISTINCT(name) AS name FROM root_favorable WHERE deleted = '0' ORDER BY name;";
 	// var_dump($preferential_calculation__sql);
  $preferential_calculation_sql_result = runSQLall($preferential_calculation__sql);
 	// var_dump($preferential_calculation_sql_result);
  $default_favorable = default_favorable_setting();

	$member_preferential_calculation_optine = '';
  if($preferential_calculation_sql_result[0] >= 1) {
    for($i=1;$i<=$preferential_calculation_sql_result[0];$i++) {
      if($preferential_calculation_sql_result[$i]->name == $default_favorable->name){
        $member_preferential_calculation_optine = $member_preferential_calculation_optine.'
        <option selected>'.$preferential_calculation_sql_result[$i]->name.'</option>
        ';
      }else{
        $member_preferential_calculation_optine = $member_preferential_calculation_optine.'
        <option>'.$preferential_calculation_sql_result[$i]->name.'</option>
        ';
      }
    }
		// $gradelist[NULL] = $graderesult[1];
    // var_dump($gradelist);
  } else {
    // $tr['bonus level information form has not been set'] = '反水等級資料表格尚未設定。';
		$logger = $tr['bonus level information form has not been set'];
    die($logger);
  }
  // $tr['default bonus level'] = '預設反水等級';
  $panelbody_content = $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-2"><p class="text-right">* '.$tr['default bonus level'].'</p></div>
  		<div class="col-12 col-md-4">
  			<select id="favorable_select" name="favorable_select" class="form-control">
  			  '.$member_preferential_calculation_optine.'
  			</select>
  		</div>
  	<div class="col-12 col-md-6"></div>
  </div><br>
  ';

  $commission_setting__sql = "SELECT DISTINCT(name) AS name FROM root_commission WHERE deleted = '0' ORDER BY name;";
  // var_dump($commission_setting__sql);
 $commission_setting__sql_result = runSQLall($commission_setting__sql);
  // var_dump($commission_setting__sql_result);
  $default_commission = default_commission_setting();

 $member_commission_setting_optine = '';
 if($commission_setting__sql_result[0] >= 1) {
   for($i=1;$i<=$commission_setting__sql_result[0];$i++) {
    if($commission_setting__sql_result[$i]->name == $default_commission->name){
      $member_commission_setting_optine = $member_commission_setting_optine.'
      <option selected>'.$commission_setting__sql_result[$i]->name.'</option>
      ';
    } else {
      $member_commission_setting_optine = $member_commission_setting_optine.'
      <option>'.$commission_setting__sql_result[$i]->name.'</option>
      ';
    }
   }
   // $gradelist[NULL] = $graderesult[1];
   // var_dump($gradelist);
 } else {
   // $tr['commission level information form has not been set'] = '佣金等級資料表格尚未設定。';
   $logger = $tr['commission level information form has not been set'];
   die($logger);
 }
 // $tr['default commission level'] = '預設佣金等級';
 $panelbody_content = $panelbody_content.'
 <div class="row">
   <div class="col-12 col-md-2"><p class="text-right">* '.$tr['default commission level'].'</p></div>
     <div class="col-12 col-md-4">
       <select id="commission_select" name="commission_select" class="form-control">
         '.$member_commission_setting_optine.'
       </select>
     </div>
   <div class="col-12 col-md-6"></div>
 </div><br>
 ';

  // 預設密碼
  // $tr['default password and withdrawal password'] = '預設密碼及提款密碼';
  $panelbody_content		= $panelbody_content.'
  <div class="row">
    <div class="col-12 col-md-2"></div>
  	<div class="col-12 col-md-4">
      <div class="alert alert-info" role="alert">
    	'.$tr['default password and withdrawal password'].' <strong>'.$system_config['withdrawal_default_password'].'</strong>
      </div>
  	</div>
    <div class="col-12 col-md-6"></div>
  </div>
  ';


    // -----------------------
    // 新增會員 - 銀行資訊
    // -----------------------

    $panelbody_content = $panelbody_content.'
    <div class="row">
    	<div class="col-12 col-md-12">
    	<span class="label label-primary btn-lg">
    		<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>'.$tr['Optional'].'-'.$tr['Bank Information'].'
    	</span>
    	<hr>
    	</div>
    </div>
    ';

    // $tr['bank name'] = '銀行名稱';
    // $tr['Bank Information'] = '銀行資訊';
    // $tr['Please fill in the bank name'] = '請填寫銀行名稱。';
    $row_name = $tr['bank name'];
    $placeholder_text = $tr['Please fill in the bank name'];
    $bankname_html = get_row_html($row_name, 'bankname_input', $placeholder_text);
    $panelbody_content = $panelbody_content.$bankname_html;
    // $tr['Bank account'] = '銀行帳號';
    // $tr['Please fill in the bank account'] = '請填寫銀行帳號。';
    $row_name = $tr['Bank account'];
    $placeholder_text = $tr['Please fill in the bank account'];
    $bankaccount_html = get_row_html($row_name, 'bankaccount_input', $placeholder_text);
    $panelbody_content = $panelbody_content.$bankaccount_html;
    // $tr['bank province'] = '銀行省份';
    // $tr['Please fill in the bank province'] = '請填寫銀行省份。';
    $row_name = $tr['bank province'];
    $placeholder_text = $tr['Please fill in the bank province'];
    $bankprovince_html = get_row_html($row_name, 'bankprovince_input', $placeholder_text);
    $panelbody_content = $panelbody_content.$bankprovince_html;
    // $tr['bank city'] = '銀行縣市';
    // $tr['Please fill in the bank city'] = '請填寫銀行縣市。';
    $row_name = $tr['bank city'];
    $placeholder_text = $tr['Please fill in the bank city'];
    $bankcounty_html = get_row_html($row_name, 'bankcounty_input', $placeholder_text);
    $panelbody_content = $panelbody_content.$bankcounty_html;


  // -----------------------
  // 新增會員 - 選填
  // -----------------------
  $panelbody_content		= $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-12">
  	<span class="label label-primary btn-lg">
  		<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>'.$tr['Optional'].' - '.$tr['member data'].'
  	</span>
  	<hr>
  	</div>
  </div><br>';

  $row_name = $tr['realname'];
  $placeholder_text = $tr['Real Name Example'];
  $realname_html = get_row_html($row_name, 'realname_input', $placeholder_text);
  $panelbody_content = $panelbody_content.$realname_html;

  $row_name = $tr['Cell Phone'];
  $placeholder_text = 'ex: 15820859791';
  $mobilenumber_html = get_row_html($row_name, 'mobilenumber_input', $placeholder_text);
  $panelbody_content = $panelbody_content.$mobilenumber_html;

  $panelbody_content		= $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-2"><p class="text-right">'.$tr['Gender'].'</p></div>
  		<div class="col-12 col-md-4">
  			<select id="sex_input" name="sex_input" class="form-control">
  			  <option value="1">&nbsp;'.$tr['Gender Male'].'&nbsp;</option>
  			  <option value="0">&nbsp;'.$tr['Gender Female'].'&nbsp;</option>
  			  <option value="2" selected>&nbsp;'.$tr['Not known'].'&nbsp;</option>
  			</select>
  		</div>
  	<div class="col-12 col-md-6"></div>
  </div><br>
  ';

  $panelbody_content		= $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-2"><p class="text-right">Email</p></div>
  		<div class="col-12 col-md-4">
  			<input type="text" class="form-control" id="email_input" placeholder="ex:abcd1234@qq.com">
  		</div>
  	<div class="col-12 col-md-6"></div>
  </div><br>
  ';

  $row_name = $tr['Birth'];
  $placeholder_text = 'ex: 19760101';
  $birthday_html = get_row_html($row_name, 'birthday_input', $placeholder_text);
  $panelbody_content = $panelbody_content.$birthday_html;

  // date 選擇器 https://jqueryui.com/datepicker/
  // http://api.jqueryui.com/datepicker/
  // 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
  $dateyearrange_start 	= date("Y") - 100;
  $dateyearrange_end 		= date("Y") - 14;
  $datedefauleyear		= date("Y") - rand(25,55);
  $dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;
  $panelbody_content		= $panelbody_content.'
  <script>
    $( function() {
      $( "#birthday_input" ).datepicker({
      	defaultDate: "'.$datedefauleyear.'0101",
      	yearRange: "'.$dateyearrange.'",
      	dateFormat: "yymmdd",
      	changeMonth: true,
      	changeYear: true
      });
    } );
  </script>
  ';

  $row_name = $protalsetting["custom_sns_rservice_1"]??$tr['sns1'];
  $placeholder_text = 'sample： line@88401886';
  $wechat_html = get_row_html($row_name, 'wechat_input', $placeholder_text);
  $panelbody_content = $panelbody_content.$wechat_html;

  $row_name = $protalsetting["custom_sns_rservice_2"]??$tr['sns2'];
  $placeholder_text = 'sample： skype：apple520';
  $qq_html = get_row_html($row_name, 'qq_input', $placeholder_text);
  $panelbody_content = $panelbody_content.$qq_html;

  $row_name = $tr['Remark'];
  $placeholder_text = $tr['Remark Info'];
  $notes_html = get_row_html($row_name, 'notes_input', $placeholder_text);
  $panelbody_content = $panelbody_content.$notes_html;

  $panelbody_content		= $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-2"></div>
  	<div class="col-12 col-md-2">
  		<button id="submit_to_member_create" class="btn btn-success btn-block" type="submit">'.$tr['Submit'].'</button>
  	</div>
  	<div class="col-12 col-md-2">
  		<button id="submit_to_member_create_cancel" class="btn btn-default btn-block" type="submit" onClick="window.location.reload();">'.$tr['Cancel'].'</button>
  	</div>
  	<div class="col-12 col-md-6"></div>
  </div><br>
  ';


  // 建立帳號後,回應訊息的地方。
  $panelbody_content		= $panelbody_content.'
  <div class="row">
  	<div class="col-12 col-md-1"></div>
  	<div class="col-12 col-md-6">
  		<div id="submit_to_member_create_result"></div>
  	</div>
  	<div class="col-12 col-md-4"></div>
  </div>
  ';



  // --------------------------------------
  // (1) 處理上面的表單, JS 動作 , 必要欄位按下 key 透過 jquery 送出 post data 到 url 位址
  // (2) 最下面送出，送出後將整各表單透過 post data 送到後面處理。
  // --------------------------------------



  //  必要欄位 會員帳號 欄位
  $agent_inquiry_js = "
  $('#account_create_input').click(function(){
  	var account_create_input = $('#account_create_input').val();
    var csrftoken = '$csrftoken';
    console.log(account_create_input);
  	$.post('agent_create_action.php?a=member_check',
  		{ account_create_input: account_create_input,
        csrftoken: csrftoken
      },
  		function(result){
  			$('#account_create_result').html(result);}
  	);
  });
  ";

  //  必要欄位 代理商帳號 欄位
  $agent_inquiry_js = $agent_inquiry_js."
  $('#agent_account_input').click(function(){
  	var agent_account_input = $('#agent_account_input').val();
    var csrftoken = '$csrftoken';
  	$.post('agent_create_action.php?a=agent_check',
  		{ agent_account_input: agent_account_input,
        csrftoken: csrftoken
      },
  		function(result){
  			$('#agent_account_result').html(result);}
  	);
  });
  ";

  $agent_inquiry_js = $agent_inquiry_js."
  $('.lv_one_agent_checkbox').click(function(){
    if($('.lv_one_agent_checkbox').prop('checked')){
      $('#agent_account_input').attr('disabled', true);
      $('#agent_account_input').val('".$config['system_company_account']."');
      $('#agent_account_result').html('');
    } else {
      $('#agent_account_input').attr('disabled', false);
      $('#agent_account_input').val('');
    }
  });
  ";

  // 整理所有必要欄位，及選項欄位的資料，送出
  $agent_inquiry_js = $agent_inquiry_js."
  $('#submit_to_member_create').click(function(){
  	var submit_to_member_create = 'admincreateaccount';
    var csrftoken = '$csrftoken';

    var agent_account_input = $('#agent_account_input').val();
    var lv_one_agent_select = $('.lv_one_agent_checkbox').prop('checked');
  	var account_create_input = $('#account_create_input').val();
    var member_grade_select = $('#member_grade_select').val();
    var favorable_select = $('#favorable_select').val();
    var commission_select = $('#commission_select').val();

  	var realname_input = $('#realname_input').val();
  	var mobilenumber_input = $('#mobilenumber_input').val();
  	var sex_input = $('#sex_input').val();
  	var email_input = $('#email_input').val();
  	var birthday_input = $('#birthday_input').val();
  	var wechat_input = $('#wechat_input').val();
  	var qq_input = $('#qq_input').val();
  	var notes_input = $('#notes_input').val();

    var bankname_input = $('#bankname_input').val();
    var bankaccount_input = $('#bankaccount_input').val();
    var bankprovince_input = $('#bankprovince_input').val();
    var bankcounty_input = $('#bankcounty_input').val();

  	$.post('agent_create_action.php?a=member_create',
  		{
  			submit_to_member_create: submit_to_member_create,
        csrftoken: csrftoken,

        agent_account_input: agent_account_input,
        lv_one_agent_select: lv_one_agent_select,
  			account_create_input: account_create_input,
        member_grade_select: member_grade_select,
        favorable_select: favorable_select,
        commission_select: commission_select,

  			realname_input: realname_input,
  			mobilenumber_input: mobilenumber_input,
  			sex_input: sex_input,
  			email_input: email_input,
  			birthday_input: birthday_input,
  			wechat_input: wechat_input,
  			qq_input: qq_input,
  			notes_input: notes_input,

        bankname_input: bankname_input,
        bankaccount_input: bankaccount_input,
        bankprovince_input: bankprovince_input,
        bankcounty_input: bankcounty_input
  		},

  		function(result){
  			$('#submit_to_member_create_result').html(result);}
  	);
  });
  ";



  // ref: http://crazy.molerat.net/learner/cpuroom/net/reading.php?filename=100052121100.dov
  // ref: http://stackoverflow.com/questions/4104158/jquery-keypress-left-right-navigation
  // 必要欄位處理：按下 a-z or 0-9 or enter 後,等於 click 檢查是否存在該帳號.
  $agent_inquiry_keypress_js = "
  $(function() {
  	$('#account_create_input').keyup(function(e) {
  		// all key
  		if(e.keyCode >= 65 || e.keyCode <= 90) {
  			$('#account_create_input').trigger('click');
  		}
  	});
  	$('#agent_account_input').keyup(function(e) {
  		// all key
  		if(e.keyCode >= 65 || e.keyCode <= 90) {
  			$('#agent_account_input').trigger('click');
  		}
  	});
  });
  ";



  // 必要欄位 處理的 js
  $agent_inquiry_js_html = "
  <script>
  	$(document).ready(function() {
  		".$agent_inquiry_js."
  	});
  	".$agent_inquiry_keypress_js."
  </script>
  ";

  // JS 放在檔尾巴
  $extend_js				= $extend_js.$agent_inquiry_js_html;
  // --------------------------------------
  // jquery post ajax send end.
  // --------------------------------------


}else{
  // 沒有權限的處理顯示

  // 沒有登入的顯示提示俊息
  // $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
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
$tmpl['panelbody_content']				= $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");





?>
