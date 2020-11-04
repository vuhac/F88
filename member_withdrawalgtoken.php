<?php
// ----------------------------------------------------------------------------
// Features:	GTOKEN後台 -- 人工取款GTOKEN
// File Name:	member_withdrawalgtoken.php
// Author:		Barkley
// Related:
// Permission: 只有站長或是客服才可以執行, 正常提款 GTOKEN 需要稽核，此功能無須稽核。
// Log:
// 2016.12.11 v0.1
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// var_dump($_SESSION);

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
$function_title 		= $tr['member_withdrawalgtoken'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';

// 主要內容 -- title
$paneltitle_content 	= '<span class="glyphicon glyphicon-user" aria-hidden="true"></span>'.$function_title;

// body 內的主要內容
$panelbody_content = '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
	<li><a href="member.php">'.$tr['Member inquiry'].'</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';


// title and 功能說明文字
$page_title = '<h2><strong>'.$function_title.'</strong></h2><hr>';
// ----------------------------------------------------------------------------
$indexbody_content	= '';
$show_list_html = '';


// --------------------------------------------------------
// 只有 root 才可以存入 , 不是 root 則顯示錯誤訊息提式。
// --------------------------------------------------------
if($_SESSION['agent']->therole == 'R' ) {

	// ----------------------------------------------------------------------------
	// 此程式功能說明：
	// 以使用者帳號為主軸 , 管理者可以操作各種動作。

	// var_dump($_SESSION);

	// -----------------------
	// (1) 判斷帳號是否為管理員 root 帳號
	// -----------------------


	// member grade 會員等級的名稱，取得會員等級的詳細資訊。
	// -------------------------------------
	$grade_sql = "SELECT * FROM root_member_grade WHERE status = 1 AND id = '".$_SESSION['agent']->grade."';";
	$graderesult = runSQLALL($grade_sql);
	//var_dump($graderesult);
	if($graderesult[0] == 1) {
		$member_grade = $graderesult[1];
	}else{
		$member_grade = NULL;
	}
	// var_dump($member_grade);

	// -------------------------------------

	// GTOKEN 提出的轉入帳戶。 define in config.php
	// $gtoken_cashier_account = 'gtoken_cashier';

	// 功能說明文字
	$page_desc	= '<div class="alert alert-success" role="alert">
	'.$tr['member_withdrawalgtoken text1'].'<br>
	'.$tr['member_withdrawalgtoken text2'].'<br>
	'.$tr['member_withdrawalgtoken text3'].$gtoken_cashier_account.' '. $tr['Account'].'。<br>
	'.$tr['member_withdrawalgtoken text4'].'
	</div>';

	// 功能及操作的說明
	$body_content = $page_desc;

	// --------------------------------------
	// 使用特殊帳號，來執行入款及存款的功能紀錄
	// --------------------------------------

	// 只有登入者是管裡員時，才可以從 get 取得指定來源帳號。
	if($_SESSION['agent']->therole == 'R') {

		$disabled_var = '';
		// $source_transferaccount_input_default = $_SESSION['agent']->account;
		$source_transferaccount_id = filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT);
		$sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '".$source_transferaccount_id."';";
		$r = runSQLall($sql);
		// var_dump($r);
		// 如果登入者身份，存在系統內的話。
		if($r[0] == 1) {
			$source_transferaccount_input_default 	= $r[1]->account;
			$source_transferaccount_result_html 	= $tr['Account exists'].'，'.$tr['Balance'].' '.money_format('%i', $r[1]->gtoken_balance);
		}else{
			$source_transferaccount_input_default = $_SESSION['agent']->account;
			$source_transferaccount_result_html 	= '';
		}

	}else{
		$disabled_var = 'disabled';
		$source_transferaccount_input_default = $_SESSION['agent']->account;
		$source_transferaccount_result_html 	= '';
	}


	// GTOKEN 提款帳號
	$show_list_html		= $show_list_html.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>GTOKEN '.$tr['source account'].'</p></div>
		<div class="col-12 col-md-4">
			<input type="text" class="form-control" id="source_transferaccount_input" placeholder="" value="'.$source_transferaccount_input_default.'" '.$disabled_var.'>
		</div>
		<div class="col-12 col-md-6"><div id="source_transferaccount_result">'.$source_transferaccount_result_html.'</div></div>
	</div>
	<br>
	';

	// 提款的目的帳號 cash_cashier
	$destination_transferaccount_input_default = $gtoken_cashier_account;
	// 存入帐号 hidden form
	$show_list_html		= $show_list_html.'<input type="hidden" id="destination_transferaccount_input" value="'.$destination_transferaccount_input_default.'"  disabled>';


	// 提款金额
	$show_list_html		= $show_list_html.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['withdrawal amount'].'</p></div>
		<div class="col-12 col-md-4">
			<input type="number" class="form-control" id="balance_input" min="'.$member_grade->withdrawallimits_lower.'" step="100" max="'.$member_grade->withdrawallimits_upper.'" placeholder="ex: 10000" required>
		</div>
		<div class="col-12 col-md-6"><div id="balance_result"> </div></div>
	</div>
	<br>
	<hr>
	';


	// -----------------------------------------------------
	// 交易的類別分類 應用在所有的錢包相關程式內 define in system_config.php
	// -----------------------------------------------------


	// 类型 to summary
	$show_list_html		= $show_list_html.'
	<div class="row">
		<div class="col-12 col-md-2">
		<p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['type'].'</p></div>
			<div class="col-12 col-md-4">
				<select id="summary_input" name="summary_input"  class="form-control">
				  <option value="tokenrecycling">'.$transaction_category['tokenrecycling'].'&nbsp;</option>
					<option value="tokenadministrationfees">'.$transaction_category['tokenadministrationfees'].'&nbsp;</option>
				</select>
			</div>
		<div class="col-12 col-md-6">
			<span id="realcash_desc" class="btn btn-default btn-xs"><input type="checkbox" id="realcash_input" name="realcash_input" value="1">'.$tr['Actual deposit'].'</span>
			<p>
			* '.$tr['Game currency recovery can only be performed by the administrator, and is designed to recover discounted game currency that has errors and does not require audit.'].'<br>
			* '.$tr['The administrative fee for game currency withdrawals is designed to be manually charged for administrative audit failure.'].'
			</p>
		</div>
	</div><br>
	';

	// 前台摘要
	$show_list_html .= <<<HTML
		<div class="row">
			<div class="col-12 col-md-2">
				<p class="text-right">{$tr['Summary']}
					<i class="fas fa-info-circle text-primary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細摘要"></i>
				</p>
			</div>
			<div class="col-12 col-md-4">
				<textarea class="form-control validate[maxSize[500]]" maxlength="500" id="front_system_note" placeholder="{$tr['Summary']}({$tr['max']}500{$tr['word']})"></textarea>
			</div>
			<div class="col-12 col-md-6"><div id="front_system_note_result"></div></div>
		</div>
		<br>
HTML;

	// 备注
	$show_list_html		= $show_list_html.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right">'.$tr['note'].'</p></div>
		<div class="col-12 col-md-4">
			<textarea class="form-control validate[maxSize[500]]" maxlength="500" id="system_note_input" placeholder="'.$tr['note'].'('.$tr['max'].'500'.$tr['word'].')"></textarea>
		</div>
		<div class="col-12 col-md-6"><div id="system_note_result"></div></div>
	</div>
	<br>
	';


	// source account 密码 , 需要知道來源帳號的密碼才可以提款。 除非他是管理員。
	$show_list_html		= $show_list_html.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['passwd'].'</p></div>
		<div class="col-12 col-md-4">
			<input type="password" class="form-control" id="password_input" placeholder="Password"  required>
		</div>
		<div class="col-12 col-md-6"><div id="password_result"></div></div>
	</div>
	<br>
	';


	$show_list_html		= $show_list_html.'
	<div class="row">
		<div class="col-12 col-md-2"></div>
		<div class="col-12 col-md-2">
			<button id="submit_to_action" class="btn btn-success btn-block" type="submit">'.$tr['Submit'].'</button>
		</div>
		<div class="col-12 col-md-2">
			<button id="submit_to_cancel" class="btn btn-default btn-block" type="submit">'.$tr['Cancel'].'</button>
		</div>
		<div class="col-12 col-md-6"></div>
	</div><br>
	';

	$form_list='';
  
    $form_list = $form_list.'
      <form class="form-horizontald" role="form" id="preferential_form">
        '.$body_content.'
        '.$show_list_html.'
      </form>
    ';

    $body_content =     $form_list;

	// 建立帳號後,回應訊息的地方。
	$body_content		= $body_content.'
	<div class="row">
		<div class="col-12 col-md-1"></div>
		<div class="col-12 col-md-6">
			<div id="account_result"></div>
		</div>
		<div class="col-12 col-md-4"></div>

	</div>
	';




	// --------------------------------------
	// (1) 處理上面的表單, JS 動作 , 必要欄位按下 key 透過 jquery 送出 post data 到 url 位址
	// (2) 最下面送出，送出後將整各表單透過 post data 送到後面處理。
	// --------------------------------------
	//  必要欄位 對象 account 帳號 check, 目標帳號不可以為來源帳號。
	$agent_member_js = "
	$('#source_transferaccount_input').click(function(){
		var destination_transferaccount_input = $('#destination_transferaccount_input').val();
		var source_transferaccount_input = $('#source_transferaccount_input').val();
		$.post('member_withdrawalgtoken_action.php?a=member_withdrawalgtoken_check',
			{
				source_transferaccount_input: source_transferaccount_input,
				destination_transferaccount_input: destination_transferaccount_input
			},
			function(result){
				$('#source_transferaccount_result').html(result);}
		);
	});
	";

	// 轉帳 post data send, 整理所有必要欄位，及選項欄位的資料，送出
	$agent_member_js = $agent_member_js."
	$('#submit_to_action').click(function(){
		var source_transferaccount_input = $('#source_transferaccount_input').val();
		var destination_transferaccount_input = $('#destination_transferaccount_input').val();
		var balance_input = $('#balance_input').val();
		var transaction_category_input = $('#summary_input').val();
		var summary_input = $('#summary_input option:selected' ).text();
		var system_note_input = $('#system_note_input').val();
		var front_system_note = $('#front_system_note').val();

		if($('#realcash_input').is(':checked'))
		{
		  var realcash_input = '1';
		}	else{
			var realcash_input = '0';
		}

		if(jQuery.trim(source_transferaccount_input) == '' || jQuery.trim(balance_input) == '' || jQuery.trim($('#password_input').val()) == ''){
			alert('請將底下所有 * 欄位資訊填入');
		}else{
			var password_input  = $().crypt({method:'sha1', source:$('#password_input').val()});
			$('#submit_to_action').attr('disabled', 'disabled');

			if(confirm('确定要进行转帐的操作？？')) {
				$.post('member_withdrawalgtoken_action.php?a=member_withdrawalgtoken',
					{
						destination_transferaccount_input: destination_transferaccount_input,
						source_transferaccount_input: source_transferaccount_input,
						balance_input: balance_input,
						password_input: password_input,
						summary_input: summary_input,
						transaction_category_input: transaction_category_input,
						system_note_input: system_note_input,
                    	front_system_note:front_system_note,
						realcash_input: realcash_input
					},
					function(result){
						$('#account_result').html(result);}
				);
			}else{
					window.location.reload();
			}

	  }
	});
	";


	// ref: http://crazy.molerat.net/learner/cpuroom/net/reading.php?filename=100052121100.dov
	// ref: http://stackoverflow.com/questions/4104158/jquery-keypress-left-right-navigation
	// 必要欄位處理：按下 a-z or 0-9 or enter 後,等於 click 檢查是否存在該帳號.
	$agent_member_keypress_js = "
	$(function() {
		$('#source_transferaccount_input').keyup(function(e) {
			// all key
			if(e.keyCode >= 65 || e.keyCode <= 90) {
				$('#source_transferaccount_input').trigger('click');
			}
		});
	});

	//按取消，則彈出視窗，確定是否離開
	$('#submit_to_cancel').click(function(){
	    if(confirm('确定要取消编辑吗')==true){
	      document.location.href=\"member_account.php?a=".$source_transferaccount_id."\";
	    }
	});
	";

	// 必要欄位 處理的 js
	$agent_member_js_html = "
	<script>
		$(document).ready(function() {
			".$agent_member_js."
		});
		".$agent_member_keypress_js."
	</script>
	";

// JS 開頭
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
	// JS 放在檔尾巴
	$extend_js				= $extend_js.$agent_member_js_html;

	// --------------------------------------
	// jquery post ajax send end.
	// --------------------------------------

	$panelbody_content		= $body_content;

}else{

	// --------------------------------------------------------
	// 不合法的訊息
	// --------------------------------------------------------
	$body_content_stop = '
	<div class="row">
		<div class="col-12 col-md-1"></div>
		<div class="col-12 col-md-6">
			<p>'.$_SESSION['agent']->account.'你好， 人工存入功能，限定管理員、代理商或帳務員才可以操作。請先登入系統。</p>
		</div>
		<div class="col-12 col-md-4"></div>

	</div>
	';

	$panelbody_content		= $body_content_stop;
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
// 填入內容結束。底下為頁面的樣板 。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
//include("template/member.tmpl.php");
include("template/beadmin.tmpl.php");



?>
