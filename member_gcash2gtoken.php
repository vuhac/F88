<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 人工現金轉代幣及設定
// File Name:	member_gcash2gtoken.php
// Author:		Barkley
// Related:
// Permission: 只有站長或是客服才可以執行
// Log:
// 2016.12.14 v0.1
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/member_lib.php";

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
$function_title 		= $tr['GCash transfer GToken set'];
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

if (!isset($_GET['a']) || !check_searchid($_GET['a'])) {
	die($tr['Illegal test']);
}

$id = filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT);


// title and 功能說明文字
$page_title = '<h2><strong>'.$function_title.'</strong></h2><hr>';
// ----------------------------------------------------------------------------
$indexbody_content	= '';

// ----------------------------------------------------------------------------
// 此程式功能說明：
// 以使用者帳號為主軸 , 管理者可以操作各種動作。

// -----------------------
// (1) 判斷帳號是否為管理員 root 帳號 , 只有 root  or agent can show
// -----------------------
if($_SESSION['agent']->therole == 'R') {

	// --------------------------------------
	// 使用特殊帳號，來執行入款及存款的功能紀錄
	// --------------------------------------

	// 查詢來源帳號的餘額
	$g = (object)get_memberdata_byid($id);

	if (!$g->status) {
		$error_mag = $g->result;
		echo '<script>alert("'.$error_mag.'");window.location = "./member.php";</script>';
	}

	$check_therole_result = (object)check_member_therole($g->result);
	if (!$check_therole_result->status) {
		$error_mag = $check_therole_result->result;
		echo '<script>alert("'.$error_mag.'");window.location = "./member.php";</script>';
	}

	$source_transferaccount_input_default = $g->result->account;
	$source_transferaccount_html  = $tr['Balance'].'&nbsp;'.money_format('%i', $g->result->gcash_balance);
	$destination_transferaccount_input_default = $g->result->account;
	$destination_transferaccount_html = $tr['Balance'].'&nbsp;'.money_format('%i', $g->result->gtoken_balance);

	// 功能說明文字
	$page_desc	= '<div class="alert alert-success" role="alert">
	* '.$tr['Members can transfer their own cash (GCASH) to their own game currency (GTOKEN) account.'].'<br>
	* '.$tr['After the member setting is completed, you need to enter your own password to confirm this setting.'].'<br>
	* '.$tr['The administrator can help members operate the above two actions.'].'<br>
	</div>';

	// 功能及操作的說明
	$deposit_body_content = $page_desc;

	// GCASH 額度來源帳號
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>GCASH '.$tr['source account'].'</p></div>
		<div class="col-12 col-md-6">
			<input type="text" class="form-control" id="source_transferaccount_input" placeholder="" value="'.$source_transferaccount_input_default.'"  disabled>
		</div>
		<div class="col-12 col-md-4">'.$source_transferaccount_html.'</div>
	</div>
	<br>
	';

	// 存入 GTOKEN 帐号
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>GTOKEN '.$tr['Deposit account'].'</p></div>
		<div class="col-12 col-md-6">
			<input type="text" class="form-control" id="destination_transferaccount_input" value="'.$destination_transferaccount_input_default.'" disabled>
		</div>
		<div class="col-12 col-md-4">'.$destination_transferaccount_html.'</div>
	</div>
	<br>
	';

	// 儲值代幣金额
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['Manual deposit amount'].'</p></div>
		<div class="col-12 col-md-6">
			<input type="number" class="form-control" id="balance_input" min="1" step="100" max="1000000" placeholder="ex: 10000" required>
		</div>
		<div class="col-12 col-md-4"><div id="balance_result"></div></div>
	</div>
	<br>
	';

	// 类型 -- 現金轉代幣
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2">
		<p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['type'].'</p></div>
			<div class="col-12 col-md-6">
				<select id="summary_input" name="summary_input"  class="form-control">
				  <option value="cashgtoken">'.$transaction_category['cashgtoken'].'</option>
				</select>
			</div>
		<div class="col-12 col-md-4"></div>
	</div><br>
	';

	// 备注
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right">'.$tr['note'].'</p></div>
		<div class="col-12 col-md-6">
			<textarea class="form-control" id="system_note_input" placeholder="'.$tr['note'].'"></textarea>
		</div>
		<div class="col-12 col-md-4"><div id="system_note_result"></div></div>
	</div>
	<br>
	';


	// 管理員密码
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2"><p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>'.$tr['passwd'].'</p></div>
		<div class="col-12 col-md-6">
			<input type="password" class="form-control" id="password_input" placeholder="Password"  required>
		</div>
		<div class="col-12 col-md-4"><div id="password_result"></div></div>
	</div>
	<br>
	';



	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2"></div>
		<div class="col-12 col-md-3">
			<button id="submit_to_gcash2gtoken" class="btn btn-success btn-block" type="submit">'.$tr['Submit'].'</button>
		</div>
		<div class="col-12 col-md-3">
			<button id="submit_to_gcash2gtoken_cancel" class="btn btn-default btn-block" type="submit">'.$tr['Cancel'].'</button>
		</div>
		<div class="col-12 col-md-4"></div>
	</div><br>
	';


	// ---------------------------------
	// 處理完成轉帳號,回應訊息的地方。
	// ---------------------------------
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-1"></div>
		<div class="col-12 col-md-6">
			<div id="submit_to_result"></div>
		</div>
		<div class="col-12 col-md-4"></div>
	</div>';



	// ---------------------------------
	// 針對帳號，設定自動化儲值的預設值。
	// ---------------------------------
if($protalsetting['member_deposit_currency'] == 'gcash'){
  	$deposit_body_content		= $deposit_body_content.'
  	<hr>
  	<div class="row">
  		<div class="col-12 col-md-2">
  			<p class="text-right"><span class="glyphicon glyphicon-star" aria-hidden="true"></span>帳號：'.$g->result->account.'<br>設定自動儲值</p>
  		</div>

  		<div class="col-12 col-md-8">
  			<table class="table table-striped">
  				<thead>
  			  <tr>
  			    <th>自動化儲值開啟(開/關)</th>
  					<th>最低自動轉帳餘額</th>
  					<th>每次儲值金額</th>
  			  </tr>
  				</thead>
  		    <tbody>
  			  <tr align="center">
  			    <td><a href="#" id="autocash2token" data-type="select" data-pk="'.$g->result->id.'" data-title="自動化儲值開啟(開/關)"></a></td>
  					<td><a href="#" id="tokenautostart" data-type="text" data-pk="'.$g->result->id.'" data-title="最低自動轉帳餘額(需要大於1)">'.$g->result->auto_min_gtoken.'</a></td>
  					<td><a href="#" id="tokenoncesave" data-type="text" data-pk="'.$g->result->id.'" data-title="每次儲值金額(不可小於最低轉帳餘額)">'.$g->result->auto_once_gotken.'</a></td>
  			  </tr>
  				</tbody>
  			</table>
  		</div>
  		<div class="col-12 col-md-2">

  		</div>

  		<div class="col-12 col-md-12">
  			<hr><p><div id="preview_area"></div></p>
  		</div>

  	</div>
  	';
  }

	// x-editable 編輯的欄位 JS -- 針對帳號，設定自動化儲值的預設值。
	$auto_gtoken_jsedit = "
		$('#tokenautostart').editable({
			url: 'member_gcash2gtoken_action.php?a=tokenautostart',
			success: function(resultdata){
				$( '#preview_area' ).html(resultdata);
			}
		});

		$('#tokenoncesave' ).editable({
			url: 'member_gcash2gtoken_action.php?a=tokenoncesave',
			success: function(resultdata){
				$( '#preview_area' ).html(resultdata);
			}
		});

		$('#autocash2token').editable({
				url: 'member_gcash2gtoken_action.php?a=autocash2token',
				value: ".$g->result->auto_gtoken.",
				source: [
							{value: 1, text: '開啟'},
							{value: 0, text: '關閉'}
					 ],
				 success: function(resultdata){
					$( '#preview_area' ).html(resultdata);
				}
		});

		//按取消，則彈出視窗，確定是否離開
		$('#submit_to_gcash2gtoken_cancel').click(function(){
		    if(confirm('确定要取消编辑吗')==true){
		      document.location.href=\"member_account.php?a=".$id."\";
		    }
		});
	";




	$panelbody_content		= $deposit_body_content;

	// 轉帳 post data send, 整理所有必要欄位，及選項欄位的資料，送出
	$agent_cashgtoken_js = "
	$('#submit_to_gcash2gtoken').click(function(){
		var source_transferaccount_input = $('#source_transferaccount_input').val();
		var destination_transferaccount_input = $('#destination_transferaccount_input').val();
		var balance_input = $('#balance_input').val();
		var transaction_category_input = $('#summary_input').val();
		var summary_input = $('#summary_input option:selected' ).text();
		var system_note_input = $('#system_note_input').val();

		if(jQuery.trim(source_transferaccount_input) == '' || jQuery.trim(balance_input) == '' || jQuery.trim($('#password_input').val()) == ''){
			alert('請將底下所有 * 欄位資訊填入');
		}else{
			var password_input  = $().crypt({method:'sha1', source:$('#password_input').val()});
			$('#submit_to_gcash2gtoken').attr('disabled', 'disabled');

			if(confirm('确定要进行游戏币转帐的操作？？')) {
				$.post('member_gcash2gtoken_action.php?a=member_gcash2gtoken',
					{
						destination_transferaccount_input: destination_transferaccount_input,
						source_transferaccount_input: source_transferaccount_input,
						balance_input: balance_input,
						password_input: password_input,
						summary_input: summary_input,
						transaction_category_input: transaction_category_input,
						system_note_input: system_note_input
					},
					function(result){
						$('#submit_to_result').html(result);}
				);
			}else{
					window.location.reload();
			}

	  }
	});
	";

	// 必要欄位 處理的 js
	$extend_js				= $extend_js."
	<script>
		$(document).ready(function() {
			".$auto_gtoken_jsedit."
			".$agent_cashgtoken_js."
		});
	</script>
	";


	// 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
	$extend_head = $extend_head.'
	<!-- x-editable (bootstrap version) -->
	<link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
	<script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
	';

	// --------------------------------------
	// jquery post ajax send end.
	// --------------------------------------
} else {
	// --------------------------------------------------------
	// 不合法的訊息
	// --------------------------------------------------------
	$deposit_body_content_stop = '
	<div class="row">
		<div class="col-12 col-md-1"></div>
		<div class="col-12 col-md-6">
			<p>'.$_SESSION['agent']->account.'你好， 人工存入功能，限定管理員、代理商或帳務員才可以操作。請先登入系統。</p>
		</div>
		<div class="col-12 col-md-4"></div>

	</div>
	';

	$panelbody_content		= $deposit_body_content_stop;
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
//include("template/member.tmpl.php");
include("template/beadmin.tmpl.php");



?>
