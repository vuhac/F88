
<?php
// ----------------------------------------------------------------------------
// Features:	會員填完個人資料後，就可以提出申請成為代理。
// File Name:	member_edit.php
// Author:		Barkley
// Related:
// Log:
// ----------------------
// 1. 個人資料維護
// 2. 修改登入密碼、取款密碼
// ----------------------

// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) ."/member_lib.php";

// var_dump($_SESSION);
//var_dump(session_id());

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
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
//會員詳細資料
// $tr['member edit'] = '修改會員資料';
$function_title = $tr['member edit'];
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = '';
// body 內的主要內容
$indexbody_content = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $menu_breadcrumbs = '
// <ol class="breadcrumb">
//   <li><a href="home.php">首頁</a></li>
//   <li><a href="#">會員與加盟聯營股東</a></li>
//   <li><a href="member.php">會員查詢</a></li>
//   <li class="active">' . $function_title . '</li>
// </ol>';

$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
  <li><a href="member.php">' . $tr['Member inquiry'] . '</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// ----------------------------------------------------------------------------
// $tr['Illegal test'] = '(x)不合法的測試。';
if (!isset($_GET['i']) || !check_searchid($_GET['i'])) {
	die($tr['Illegal test']);
}

$member_id = $_GET['i'];

/**
 * Timezones list with GMT offset
 *
 * @return array
 * @link http://stackoverflow.com/a/9328760
 */
function tz_list() {
	$zones_array = array();
	$timestamp = time();
	foreach (timezone_identifiers_list() as $key => $zone) {
		date_default_timezone_set($zone);
		$zones_array[$key]['zone'] = $zone;
		$zones_array[$key]['diff_from_GMT'] = 'UTC/GMT ' . date('P', $timestamp);
		$zones_array[$key]['GMT'] = date('P', $timestamp);
	}
	return $zones_array;
}
// 全部的時區列表
$timezone_list = tz_list();

function get_bankaccountdata_persondata_html($member_data, $input_id, $placeholder_text, $col_name) {
	$td_html = '<input type="text" class="form-control" id="' . $input_id . '" value="'.$member_data.'" style="width:40%">';

	$column_name = $col_name;
	$data = '
  <tr>
    <td>' . $column_name . '</td>
    <td>' . $td_html . '</td>
  </tr>';

	return $data;
}

function get_sex_html($member_data, $col_name) {
	global $tr;
	global $config;

	$options = [
		'0' => $tr['Gender Male'],
		'1' => $tr['Gender Female'],
		'2' => $tr['Not known'],
	];
	
	$options_html = '';

	foreach ($options as $gender_val => $gender_title) {
		$selected = $gender_val == $member_data ? 'selected' : '';
		$options_html .= sprintf('<option value="%1$s" %3$s>&nbsp;%2$s&nbsp;</option>', $gender_val, $gender_title, $selected);
	}

	$td_html = '<select id="sex" name="sex" class="form-control" style="width:40%">' . $options_html . '</select>';

	$html = '
		<tr>
		<td>' . $col_name . '</td>
		<td>' . $td_html . '</td>
		</tr>
	';

	return $html;
}

// ----------------------
// 功能1：個人資料維護
// ----------------------
// 返回會員詳細資料 btn
// $tr['back Member detail'] = '返回會員詳細資料';
$goback_btn = '<a href="member_account.php?a=' . $member_id . '" target="_SELF" title="' . $tr['back Member detail'] . '" class="btn btn-primary" role="button">' . $tr['back Member detail'] . '</a><hr>';
//點選欄位內容，就可直接編輯內容。
//$tr['directl edit field'] = '點選欄位內容，就可直接編輯內容。';
//$tr['edit randomly generate password'] = '一鍵修改密碼功能將隨機產生8位數字密碼，並進行修改該使用者密碼。';
$preview_status_html = '
<div class="alert alert-success">
* ' . $tr['directl edit field'] . '<br>
* ' . $tr['edit randomly generate password'] . '
</div>';
$member_persondata_html = $goback_btn . $preview_status_html;
$member_accountdata_col = '';
$member_persondata_col = '';
$member_bank_account_data_col = '';
// 有會員資料 不論身份 therole 為何，都可以修改個人資料。但是除了 therole = T 除外。
if (isset($_SESSION['agent']) AND $_SESSION['agent']->therole != 'T') {

	$m = (object)get_memberdata_byid($member_id, true);

	// 只有 $m[0] == 1 才工作, 因為會員只有有一個編號或是 ID
	// $tr['Membership data error'] = '(x)m11 會員資料有問題，可能是 BUG 請聯絡管理人員。';
	if (!$m->status) {
		$logger = $m->result;
		// syslog2db($_SESSION['member']->account,'member','error', "$logger");
		$member_persondata_html = $logger;
	} else {
		$check_therole_result = check_member_therole($m->result);
		if (!$check_therole_result['status']) {
			$error_mag = $check_therole_result['result'];
			echo '<script>alert("'.$error_mag.'");window.location = "./member.php";</script>';
		}

		// 顯示會員資料，會員資料可以透過 ajax 即時修改
		//帳號
		$member_persondata_col_name = $tr['Account'];
		$member_accountdata_col = $member_accountdata_col . '
  	<tr><td>' . $member_persondata_col_name . '</td>
  	<td>' . $m->result->account . '</td></tr>';

		// 身份
		//會員類型
		// $tr['Membership type'] = '會員類型';
		// $member_persondata_col_name = $tr['membership type'];
		$member_persondata_col_name = $tr['identity'];
		if ($m->result->therole == 'M') {
			//會員
			// $therole_html = $tr['member'];
			// $tr['Member'] = '會員';
			$therole_html = $tr['member'];
		} elseif ($m->result->therole == 'A') {
			//代理商
			// $therole_html = $tr['agent'];
			// $tr['agent'] = '代理商';
			$therole_html = $tr['agent'];
		} elseif ($m->result->therole == 'R') {
			//管理員
			// $therole_html = $tr['management'];
			// $tr['management'] = '管理員';
			$therole_html = $tr['management'];
		} else {
			//會員身份有問題，請聯絡管理人員。
			// $logger = $tr['member identity error'];
			// $tr['member id error'] = '會員身份有問題，請聯絡管理人員。';
			$logger = $tr['member id error'];
			die($logger);
		}
		$member_accountdata_col = $member_accountdata_col . '
  	<tr><td>' . $member_persondata_col_name . '</td>
		<td>' . $therole_html . '</td></tr>';

		//修改會員登入密碼
		//*現在密碼 *預計修改的密碼  *再次驗證修改密碼 變更密碼
		// $member_persondata_col_name = $tr['change login password'];
		// $tr['edit member login pwd'] = '修改會員登入密碼 : ';
		// $tr['current password'] = '*現在密碼';
		// $tr['Expected to modify the password'] = '*預計修改的密碼';
		// $tr['verify password'] = '*再次驗證修改密碼';
		// $tr['one key to change password'] = '一鍵修改密碼';
		$member_persondata_col_name = $tr['edit member login pwd'];


		$member_accountdata_col = $member_accountdata_col . '
  	<tr><td>' . $member_persondata_col_name . '</td>
  	<td>
  	  <div class="form-inline">
		<!--<input type="password" class="form-control mr-2" id="current_password" placeholder="' . $tr['current password'] . '">
			<input type="password" class="form-control mr-2" id="change_password_valid1" placeholder="' . $tr['Expected to modify the password'] . '">
			<input type="password" class="form-control mr-2" id="change_password_valid2" placeholder="' . $tr['verify password'] . '">
			<button type="submit" id="submit_change_password" class="btn btn-default mr-2"><span class="glyphicon glyphicon-ok" aria-hidden="true"></button>
		-->
        <button class="btn btn-default" type="submit" id="one_btn_change_passwd">' . $tr['one key to change password'] . '</button>
      </div>
		</td></tr>';

		// $tr['Modify withdrawal password'] = '修改提款密碼';
		// $tr['current withdraws password'] = '*現在提款密碼';
		// $tr['Estimated withdrawal password'] = '*預計修改的提款密碼';
		// $tr['Verify the revise password again'] = '*再次驗證修改提款密碼';$default_withdrawal_password = $system_config['withdrawal_default_password'];
		$member_persondata_col_name = $tr['Modify withdrawal password'];
		$member_accountdata_col = $member_accountdata_col . '
  	<tr><td>' . $member_persondata_col_name . '</td>
  	<td>
  	  <div class="form-inline">
  	    <!-- <input type="password" class="form-control mr-2" id="withdrawal_password" placeholder="' . $tr['current withdraws password'] . '">
  	    <input type="password" class="form-control mr-2" id="change_withdrawalpassword_valid1" placeholder="' . $tr['Estimated withdrawal password'] . '">
  	    <input type="password" class="form-control mr-2" id="change_withdrawalpassword_valid2" placeholder="' . $tr['Verify the revise password again'] . '">
		<button id="send_change_withdrawalpassword" class="btn btn-default mr-2"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span></button>
		-->
		<button class="btn btn-default" type="submit" id="one_btn_change_withdrawalspassword">' . $tr['one key to change password'] . '</button>
  	  </div>
  	</td></tr>';

		// 顯示註冊日期
		// $member_persondata_col_name = $tr['registration date'];
		// $tr['Registration date'] =      '註冊日期';
		$member_persondata_col_name = $tr['Registration date'];
		$member_accountdata_col = $member_accountdata_col . '
  	<tr><td>' . $member_persondata_col_name . '</td>
  	<td>' . $m->result->enrollmentdate . '</td></tr>';

		$persondata_arr = [
			'nickname' => [
				'col_name' => $tr['nickname'],
				'placeholder_text' => $tr['current nickname'] . $m->result->nickname,
				'member_data' => $m->result->nickname
			],
			'realname' => [
				'col_name' => $tr['realname'],
				'placeholder_text' => $tr['current Real name'] . $m->result->realname,
				'member_data' => $m->result->realname
			],
			'mobilenumber' => [
				'col_name' => $tr['Cell phone'],
				'placeholder_text' => $tr['current cell phone'] . $m->result->mobilenumber,
				'member_data' => $m->result->mobilenumber
			],
			'email' => [
				'col_name' => $tr['Email'],
				'placeholder_text' => $tr['current email'] . $m->result->email,
				'member_data' => $m->result->email
			],
			'birthday' => [
				'col_name' => $tr['Birth'],
				'placeholder_text' => $tr['current Birth'] . $m->result->birthday,
				'member_data' => $m->result->birthday
			],
			'sex' => [
				'col_name' => $tr['Gender'],
				'placeholder_text' => $tr['Gender'] . $m->result->sex,
				'member_data' => $m->result->sex
			],
			'wechat' => [
				'col_name' => $protalsetting["custom_sns_rservice_1"]??$tr['sns1'],
				'placeholder_text' => $tr['current sns1'] . $m->result->wechat,
				'member_data' => $m->result->wechat
			],
			'qq' => [
				'col_name' => $protalsetting["custom_sns_rservice_2"]??$tr['sns2'],
				'placeholder_text' => $tr['current sns2'] . $m->result->qq,
				'member_data' => $m->result->qq
			]
		];

		foreach ($persondata_arr as $colname => $content) {
			$table_data = ($colname != 'sex') ? get_bankaccountdata_persondata_html($content['member_data'], $colname, $content['placeholder_text'], $content['col_name']) : get_sex_html($content['member_data'], $content['col_name']);
			$member_persondata_col = $member_persondata_col . $table_data;
		}

		// 提款的資訊, 需要填寫才可以提款。
		// ------------------------------------------------------------------------

		$bankaccountdata_arr = [
			'bankname' => [
				'member_bank_account_data_col_name' => $tr['bank name'],
				'placeholder_text' => $tr['current bank name'] . $m->result->bankname,
				'bank_data' => $m->result->bankname
			],
			'bankaccount' => [
				'member_bank_account_data_col_name' => $tr['bank number'],
				'placeholder_text' => $tr['current bank number'] . $m->result->bankaccount,
				'bank_data' => $m->result->bankaccount
			],
			'bankprovince' => [
				'member_bank_account_data_col_name' => $tr['bank province'],
				'placeholder_text' => $tr['current bank province'] . $m->result->bankprovince,
				'bank_data' => $m->result->bankprovince
			],
			'bankcounty' => [
				'member_bank_account_data_col_name' => $tr['bank city'],
				'placeholder_text' => $tr['current bank city'] . $m->result->bankcounty,
				'bank_data' => $m->result->bankcounty
			]
		];

		foreach ($bankaccountdata_arr as $colname => $content) {
			$table_data = get_bankaccountdata_persondata_html($content['bank_data'], $colname, $content['placeholder_text'], $content['member_bank_account_data_col_name']);
			$member_bank_account_data_col = $member_bank_account_data_col . $table_data;
		}

		// ------------------------------
		// 主表格框架 -- 帳號資料
		// ------------------------------
		// $tr['Personal information and account setting'] = '個人資料及帳務設定';
		$member_persondata_html = $member_persondata_html . '
  	<h4><strong><span class="glyphicon glyphicon-user" aria-hidden="true"></span>&nbsp;'.$tr['Account information settings'].'</strong></h4>
  	<table class="table table-bordered">
  		<tr class="active">
  		<td>' . $tr['field'] . '</td>
  		<td>' . $tr['content'] . '</td>
  		</tr>
  		'.$member_accountdata_col.'
  	</table>
		';


		// ------------------------------
		// 主表格框架 -- 個人資料
		// ------------------------------
		// $tr['Personal information and account setting'] = '個人資料及帳務設定';
		$member_persondata_html = $member_persondata_html . '
  	<h4><strong><span class="glyphicon glyphicon-user" aria-hidden="true"></span>&nbsp;' . $tr['Personal information and account setting'] . '</strong></h4>
  	<table class="table table-bordered">
  		<tr class="active">
  		<td>' . $tr['field'] . '</td>
  		<td>' . $tr['content'] . '</td>
  		</tr>
  		' . $member_persondata_col . '
  	</table>
  	';

		// ------------------------------
		// 主表格框架 -- 帳務資料
		// ------------------------------
		// 帳務設定
		// $tr['Account setting'] = '帳務設定';
		$member_persondata_html = $member_persondata_html . '
  	<h4><strong><span class="glyphicon glyphicon-credit-card" aria-hidden="true"></span>&nbsp;' . $tr['Account setting'] . '</strong></h4>
  	<table class="table table-bordered">
  		<tr class="active">
  		<td>' . $tr['field'] . '</td>
  		<td>' . $tr['content'] . '</td>
  		</tr>

      ' . $member_bank_account_data_col . '
  	</table>
  	';
		// $tr['Store personal and account information'] = '儲存個人及帳務資訊';
		$member_persondata_html = $member_persondata_html . '
    <p align="right"><button id="submit_change_member_data" class="btn btn-success"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>&nbsp;' . $tr['Store personal and account information'] . '</button></p>
    <hr>';

		// ------------------------------
		// 主表格框架 -- 上層代理商資訊以及提供申請成為代理商的資訊
		// ------------------------------

		$find_result = (object)get_memberdata_byid($m->result->parent_id);

		if ($find_result->status) {
			// $tr['Identity Member'] = '會員';
			// $tr['Referee information'] = '推薦人資訊';
			// $tr['Referral account number'] = '推薦人帳號';
			// $tr['Referrer name'] = '推薦人姓名';
			// $tr['Recommended date of registration'] = '推薦人註冊日期';
			$member_persondata_html = $member_persondata_html . '
    	<h4><strong><span class="glyphicon glyphicon-user" aria-hidden="true"></span>&nbsp;' . $m->result->account . ' ' . $tr['Referee information'] . '</strong></h4>
    	<table class="table table-bordered">
    		<tr class="active">
    		<td>' . $tr['Referral account number'] . '</td>
    		<td>' . $tr['Referrer name'] . '</td>
        <td>' . $tr['Recommended date of registration'] . '</td>
    		</tr>

        <tr>
    		<td>' . $find_result->result->account . '</td>
    		<td>' . $find_result->result->realname . '</td>
        <td>' . $find_result->result->enrollmentdate . '</td>
    		</tr>
    	</table>
    	';
		}

		// ------------------------------
		// 時區列表
		// ------------------------------
		// var_dump($timezone_list);
		/*
	      0 =>
	        array (size=3)
	          'zone' => string 'Africa/Abidjan' (length=14)
	          'diff_from_GMT' => string 'UTC/GMT +00:00' (length=14)
	          'GMT' => string '+00:00' (length=6)
	      1 =>
	        array (size=3)
	          'zone' => string 'Africa/Accra' (length=12)
	          'diff_from_GMT' => string 'UTC/GMT +00:00' (length=14)
	          'GMT' => string '+00:00' (length=6)
*/
		/*
			    $gmt_js_select = '';
			    $t=0;
			    foreach ($timezone_list as &$value) {
			    	// var_dump($value);
			    	if($t == 0){
			    		$gmt_js_select = $gmt_js_select."{value: '".$value['GMT']."', text: '".$value['zone'].$value['diff_from_GMT']."'}";
			    	}else{
			    		$gmt_js_select = $gmt_js_select.",
			    		{value: '".$value['GMT']."', text: '".$value['zone'].$value['diff_from_GMT']."'}";
			    	}
			    	$t++;
			    }

			    // 時區修改
			    $gmt_js_html = "
			        $('#gmt').editable({
			            source: [
			    			".$gmt_js_select."
			               ]
			        });
			    ";
		*/
		// ------------------------------

		// ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
		// 取得日期的 jquery datetime picker -- for birthday
		$extend_head = $extend_head . '<link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>';
		$extend_js = $extend_js . '<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>';

		// date 選擇器 https://jqueryui.com/datepicker/
		// http://api.jqueryui.com/datepicker/
		// 14 - 100 歲為年齡範圍， 25-55 為主流客戶。
		$dateyearrange_start = date("Y") - 100;
		$dateyearrange_end = date("Y/m/d");
		$datedefauleyear = date("Y/m/d");

		// 加密函式密碼
		// var password_input  = $().crypt({method:'sha1', source:$('#password_input').val()});
		// $extend_js = $extend_js.'<script src="in/jquery.crypt.js"></script>';
		// member 編輯的欄位 JS
		//訊息1 : 請將底下所有 * 欄位資訊填入
		//訊息2 : 前後密碼不一致
		// $tr['New password is inconsistent'] = '新密碼前後輸入不一樣，請重新輸入。';
		// $tr['The function Randomly generate an 8-digit password to make sure to change the user password'] = '此功能會隨機生成8位數字密碼，確定要修改該使用者密碼嗎？';
		// $tr['Please input star fields'] = '請將底下所有 * 欄位資訊填入';
		$extend_js = $extend_js . "
  	<script>
  	$(document).ready(function() {

      // for birthday
      $('#birthday').datetimepicker({
        defaultDate:'" . $datedefauleyear . "',
        minDate: '" . $dateyearrange_start . "/01/01',
        maxDate: '" . $dateyearrange_end . "',
        timepicker:false,
        format:'Y/m/d',
        lang:'en'
      });

      // one btn change password
      $('#one_btn_change_passwd, #one_btn_change_withdrawalspassword').click(function(){
				var pwtype = $(this).attr('id');
  			var message = '".$tr['The function Randomly generate an 8-digit password to make sure to change the user password']."';
        if(confirm(message) == true){
          $.post('member_edit_action.php?a=one_btn_change_password',
            {
							pk: " . $m->result->id . ",
							pwtype: pwtype
            },
            function(result){
              $('#preview_area').html(result);}
          );
        }else{
          window.location.reload();
        }
  		});

      // for password 1
      $('#submit_change_password').click(function(){
  			var current_password =  $().crypt({method:'sha1', source:jQuery.trim($('#current_password').val()) });
        var change_password_valid1 = $().crypt({method:'sha1', source:jQuery.trim($('#change_password_valid1').val()) });
        var change_password_valid2 = $().crypt({method:'sha1', source:jQuery.trim($('#change_password_valid2').val()) });

  			if((current_password) == '' || (change_password_valid1) == '' || (change_password_valid2) == ''  ){
          alert('" . $tr['Please input star fields'] . "');
        }else{
          if(change_password_valid1 == change_password_valid2 ){
            $('#submit_change_password').attr('disabled', 'disabled');
    				$.post('member_edit_action.php?a=member_editpersondata_passwordm',
    					{
                pk: " . $m->result->id . ",
                current_password: current_password,
    						change_password_valid1: change_password_valid1,
                change_password_valid2: change_password_valid2
    					},
    					function(result){
    						$('#preview_area').html(result);}
    				);

          }else{
    				alert('" . $tr['New password is inconsistent'] . "');
          }
        }
  		});

      // for password 2
      $('#send_change_withdrawalpassword').click(function(){
  			var withdrawal_password = $().crypt({method:'sha1', source:jQuery.trim($('#withdrawal_password').val()) });
        var change_withdrawalpassword_valid1 = $().crypt({method:'sha1', source:jQuery.trim($('#change_withdrawalpassword_valid1').val()) });
        var change_withdrawalpassword_valid2 = $().crypt({method:'sha1', source:jQuery.trim($('#change_withdrawalpassword_valid2').val()) });

  			if((withdrawal_password) == '' || (change_withdrawalpassword_valid1) == '' || (change_withdrawalpassword_valid2) == ''  ){
          alert('" . $tr['Please input star fields'] . "');
        }else{
          if(change_withdrawalpassword_valid1 == change_withdrawalpassword_valid2 ){
            $('#send_change_withdrawalpassword').attr('disabled', 'disabled');
    				$.post('member_edit_action.php?a=member_editpersondata_passwordw',
    					{
                pk: " . $m->result->id . ",
                withdrawal_password: withdrawal_password,
    						change_withdrawalpassword_valid1: change_withdrawalpassword_valid1,
                change_withdrawalpassword_valid2: change_withdrawalpassword_valid2
    					},
    					function(result){
    						$('#preview_area').html(result);}
    				);

          }else{
    				alert('" . $tr['New password is inconsistent'] . "');
          }
        }
  		});

      $('#submit_change_member_data').click(function(){
        var nickname = $('#nickname').val();
  			var realname = $('#realname').val();
        var mobilenumber = $('#mobilenumber').val();
        var email = $('#email').val();
		var birthday = $('#birthday').val();
		var sex = $('#sex').val();
        var wechat = $('#wechat').val();
        var qq = $('#qq').val();
        var bankname = $('#bankname').val();
        var bankaccount = $('#bankaccount').val();
        var bankprovince = $('#bankprovince').val();
        var bankcounty = $('#bankcounty').val();
        console.log(birthday);

  			$.post('member_edit_action.php?a=member_editpersondata',
          {
            pk: " . $m->result->id . ",
            nickname: nickname,
            realname: realname,
            mobilenumber: mobilenumber,
            email: email,
			birthday: birthday,
			sex: sex,
            wechat: wechat,
            qq: qq,
            bankname: bankname,
            bankaccount: bankaccount,
            bankprovince: bankprovince,
            bankcounty: bankcounty
          },
          function(result){
            $('#preview_area').html(result);}
        );
  		});

  	});
  	</script>
  	";

		// 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
		$extend_head = $extend_head . '
    <!-- x-editable (bootstrap version) -->
    <link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
    <script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
    ';

	}
	// end of 會員資料存在 db 內 sql

} else {
	$member_persondata_html = '(x)你沒有權限，請登入系統。';
	$logger = $member_persondata_html;
	memberlog2db('guest', 'member', 'notice', "$logger");
	// 回到首頁
	echo '<script>window.location="/";</script>';
}

// 切成 3 欄版面 3:8:1
$indexbody_content = '';
//功能選單(美工)  功能選單(廣告)
$indexbody_content = $indexbody_content . '
<div class="row">
  <div class="col-xs-1 col-md-1">
  </div>
  <div class="col-xs-10 col-md-10">
  ' . $member_persondata_html . '
  </div>
  <div class="col-xs-1 col-md-1">
  </div>
</div>
<hr>
<div class="col-12 col-md-12">
  <div id="preview_area"></div>
</div>
<br>
';

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>' . $function_title;
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
//include("template/member.tmpl.php");
include "template/beadmin.tmpl.php";

?>
