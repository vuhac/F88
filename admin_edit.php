<?php
// ----------------------------------------------------------------------------
// Features:	管理員個人資料修改
// File Name:	admin_edit.php
// Author:		Mavis
// Related:
// Log:
// ----------------------
// 1. 管理員個人資料維護
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
// 準備填入的內容
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
//會員詳細資料
// $tr['member edit'] = '修改會員資料';
$function_title = $tr['Modify administrator information'];
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

$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;
// ----------------------------------------------------------------------------
// $tr['Illegal test'] = '(x)不合法的測試。';
if (!isset($_GET['i']) || !check_searchid($_GET['i'])) {
	die($tr['Illegal test']);
}
 
$member_id = $_GET['i'];

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

$member_persondata_html = '';
$member_accountdata_col = '';
$member_persondata_col = '';
$member_bank_account_data_col = '';

if (isset($_SESSION['agent']) AND $_SESSION['agent']->therole != 'T' AND $_SESSION['agent']->therole == 'R') {

    $m = (object)get_memberdata_byid($member_id);
    if(!$m->status){
        $logger = $m->result;
    }else{
        $check_therole_result = check_member_therole($m->result);
		if (!$check_therole_result['status']) {
			$error_mag = $check_therole_result['result'];
			echo '<script>alert("'.$error_mag.'");window.location = "./member.php";</script>';
        }
        // 顯示會員資料，會員資料可以透過 ajax 即時修改
		//帳號
		$member_persondata_col_name = $tr['Account'];
		$member_accountdata_col .= <<<HTML
        <tr><td>{$member_persondata_col_name}</td>
        <td>{$m->result->account}</td></tr>
HTML;
		// 身份
		//會員類型
		// $tr['Membership type'] = '會員類型';
		// $member_persondata_col_name = $tr['membership type'];
		$member_persondata_col_name = $tr['identity'];
		if ($m->result->therole == 'M') {
			//會員

			$therole_html = $tr['member'];
		} elseif ($m->result->therole == 'A') {
			//代理商
			$therole_html = $tr['agent'];
		} elseif ($m->result->therole == 'R') {
			//管理員
			$therole_html = $tr['management'];
		} else {
			//會員身份有問題，請聯絡管理人員。
			// $logger = $tr['member identity error'];
			// $tr['member id error'] = '會員身份有問題，請聯絡管理人員。';
			$logger = $tr['member id error'];
			die($logger);
        }
        
		$member_accountdata_col .= <<<HTML
  	    <tr>
            <td>{$member_persondata_col_name}</td>
            <td>{$therole_html}</td>
        </tr>
HTML;    
        // 改密碼
        $member_persondata_col_name = $tr['edit member login pwd'];
        $member_accountdata_col .=<<<HTML
        <tr>
            <td>{$member_persondata_col_name}</td>
            <td>
                <div class="form-inline">
                    <button class="btn btn-default" type="submit" id="one_btn_change_passwd">{$tr['one key to change password']}</button>
                </div>
            </td>
        </tr>
HTML;
        // 改提款密碼
//         $member_persondata_col_name = $tr['Modify withdrawal password'];
//         $member_accountdata_col .=<<<HTML
//         <tr>
//             <td>{$member_persondata_col_name}</td>
//             <td>
//                 <div class="form-inline">
//                     <button class="btn btn-default" type="submit" id="one_btn_change_withdrawalspassword">{$tr['one key to change password']}</button>
//                 </div>
//             </td>
//         </tr>
// HTML;
        // 註冊日期
        $member_persondata_col_name = $tr['Registration date'];
        if($m->result->enrollmentdate != NULL){
            $enrollmentdate = gmdate('Y-m-d H:i:s', strtotime($m->result->enrollmentdate)+-4 * 3600);
        }else{
            $enrollmentdate = '';
        }
        $member_accountdata_col .=<<<HTML
        <tr>
            <td>{$member_persondata_col_name}</td>
  	        <td>{$enrollmentdate}</td>
        </tr>
HTML;
        $persondata_arr = [
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
            ]
        ];

        foreach ($persondata_arr as $colname => $content) {
            $table_data = get_bankaccountdata_persondata_html($content['member_data'], $colname, $content['placeholder_text'], $content['col_name']);
            $member_persondata_col = $member_persondata_col . $table_data;
        }
      
		// ------------------------------
		// 主表格框架 -- 帳號資料
		// ------------------------------
        // $tr['Personal information and account setting'] = '個人資料及帳務設定';
        $member_persondata_html .=<<<HTML
        <h4><strong><span class="glyphicon glyphicon-user" aria-hidden="true"></span>&nbsp;{$tr['Account information settings']}</strong></h4>
        <table class="table table-bordered">
            <tr class="active">
                <td>{$tr['field']}</td>
                <td>{$tr['content']}</td>
            </tr>
            {$member_accountdata_col}
        </table>
HTML;
        
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
        
        // $tr['Store personal and account information'] = '儲存個人及帳務資訊';
		$member_persondata_html = $member_persondata_html . '
        <p align="right"><button id="submit_change_member_data" class="btn btn-success"><span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span>&nbsp;' . $tr['Store personal and account information'] . '</button></p>
        ';


        // ref. doc: http://xdsoft.net/jqplugins/datetimepicker/
		// 取得日期的 jquery datetime picker -- for birthday
		$extend_head = $extend_head . '<link rel="stylesheet" type="text/css" href="in/datetimepicker/jquery.datetimepicker.css"/>';
		$extend_js = $extend_js . '<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>';

        $extend_js =<<<HTML
        <script>
        $(document).ready(function() {
            // 一鍵修改密碼
            $('#one_btn_change_passwd, #one_btn_change_withdrawalspassword').click(function(){
                var pwtype = $(this).attr('id');
                var message = "{$tr['The function Randomly generate an 8-digit password to make sure to change the user password']}";
                if(confirm(message) == true){
                    $.post('admin_edit_action.php?a=one_btn_change_password',
                    {
                        pk: "{$m->result->id}",
                        pwtype: pwtype
                    },
                    function(result){
                        $('#preview_area').html(result);
                    });
                }else{
                    window.location.reload();
                }
            });

            // 儲存
            $('#submit_change_member_data').click(function(){
                var realname = $('#realname').val();
                var mobilenumber = $('#mobilenumber').val();
                var email = $('#email').val();
                
                // console.log(email);
                $.post('admin_edit_action.php?a=edit_admin_data',{
                    pk: "{$m->result->id}",
                    realname: realname,
                    mobilenumber: mobilenumber,
                    email: email
                },
                function(result){
                    $('#preview_area').html(result);
                });
            });
        });
        </script>
HTML;
    }
    
}else{
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


// ------------------------------------------------------------------------------
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