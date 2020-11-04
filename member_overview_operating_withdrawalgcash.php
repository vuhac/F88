<?php
// ----------------------------------------------------------------------------
// Features:    GCASH後台 -- 人工取款GCASH
// File Name:    member_withdrawalgcash.php
// Author:        Barkley
// Related:
// Permission: 只有站長或是客服才可以執行
// Log:
// 2016.11.20 v0.2
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

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
$function_title         = '人工现金提款(GCASH)';
// 擴充 head 內的 css or js
$extend_head                = '';
// 放在結尾的 js
$extend_js                    = '';
// body 內的主要內容
$panelbody_content = '';
// ----------------------------------------------------------------------------
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="member_overview.php">' . $tr['member overview'] . '</a></li>
  <li class="active">功能操作</li>
</ol>';
// ----------------------------------------------------------------------------

// 检视特定会员 id 的设定
isset($_GET['a']) OR die('NO ID ERROR!!');
$account_id = isset($_GET['a']) ? filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT) : null;
is_numeric($account_id) OR die($logger = $tr['The user ID is error']);

// get use member data
$sql = "SELECT * FROM root_member WHERE root_member.id = :account_id;";
$r = runSQLALL_prepared($sql, $values = ['account_id' => $account_id]);
// 正常只能有一个帐号, 并取得正常的资料。
count($r) == 1 OR die($debug_msg = '资料库系统有问题，请联络开发人员处理。');
$user = $r[0];
// echo '<pre>', var_dump($user->feedbackinfo), '</pre>';  exit();

//代理占比 如果是會員則不可以設定代理占比
if ($user->therole != 'A'){
    $user_therole = '';
  }else {
    $user_therole = '
    <a class="nav-link" href="member_overview_operating_agent_setting.php?a='.$user->id.'" id="agentssetting-tab" role="tab" aria-controls="agentssetting" aria-selected="false">
			'.$tr['agent ratio setting'].'
    </a>
    ';
  }

// ----------------------------------------------------------------------------
// 此程式功能說明：
// 以使用者帳號為主軸 , 管理者可以操作各種動作。

// var_dump($_SESSION);

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


	// member grade 入款會員等級的名稱，取得會員等級的詳細資訊。
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
	// var_dump($_GET['a']);die();

	// 現金提出的轉入帳戶。 已經設定在 system_config.php 內
	// $gcash_cashier_account = 'gcash_cashier';

	// 功能說明文字
	$page_desc	= '<div class="form-group row"><div class="alert alert-success w-75" role="alert">
	* 人工提款 GCASH 功能：此為管理員或允許的客服人員進行人工現金提出的的工作。<br>
	* GCASH 領出現金就是會員的 GCASH 轉帳到 '.$gcash_cashier_account.' 帳號。<br>
	* 如果是「實際存提」，需要將匯款到提款帳戶的銀行帳號內，如果「非實際存提」則無實際提領行為。
	</div></div>';

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
			$source_transferaccount_result_html 	= '帳號存在，餘額 '.money_format('%i', $r[1]->gcash_balance);
		}else{
			$source_transferaccount_input_default = $_SESSION['agent']->account;
			$source_transferaccount_result_html 	= '';
		}

	}else{
		$disabled_var = 'disabled';
		$source_transferaccount_input_default = $_SESSION['agent']->account;
		$source_transferaccount_result_html 	= '';
	}




	// GCASH 提款帳號
	$body_content		= $body_content.'
	<div class="form-group row">
        <label class="col-sm-2 col-form-label"><span class="text-danger">*</span>GCASH來源帳號</label>
		<div class="col-sm-7">
			<input type="text" class="form-control" id="source_transferaccount_input" placeholder="" value="'.$source_transferaccount_input_default.'" '.$disabled_var.'>
		</div>
        <div class="col-sm-3">
            <p class="mb-0">'.$source_transferaccount_result_html.'</p>
        </div>
	</div>
	';

	// 提款的目的帳號  $gcash_cashier_account
	$destination_transferaccount_input_default = $gcash_cashier_account;
	// 存入帐号
	$body_content		= $body_content.'<input type="hidden" id="destination_transferaccount_input" value="'.$destination_transferaccount_input_default.'"  disabled>';


	// 提款金额
	$body_content		= $body_content.'
	<div class="form-group row">
        <label class="col-sm-2 col-form-label"><span class="text-danger">*</span>提款金额</label>
		<div class="col-sm-7">
			<input type="number" class="form-control" id="balance_input" min="'.$member_grade->withdrawallimits_lower.'" step="100" max="'.$member_grade->withdrawallimits_upper.'" placeholder="ex: 10000" required>
		</div>
		<div class="col-sm-3"><div id="balance_result"> </div></div>
	</div>
	<hr>
	';


	// -----------------------------------------------------
	// 交易的類別分類 應用在所有的錢包相關程式內 system_config.php
	// -----------------------------------------------------

	//存款的動作摘要类型
	$summary[1] = '現金提款';

	// 类型 to summary
	$body_content		= $body_content.'
	<div class="form-group row">
		<label class="col-sm-2 col-form-label"><span class="text-danger">*</span>类型</label>
		<div class="col-sm-7">
			<select id="summary_input" name="summary_input"  class="form-control">
			  <option value="cashwithdrawal">'.$summary[1].'&nbsp;</option>
			</select>
		</div>
        <div class="col-sm-3">
            <input type="checkbox" id="realcash_input" name="realcash_input" value="1">
			<label id="realcash_desc" class="form-check-label" for="realcash_input">實際存提</label>
		</div>
	</div>
	';

	// 前台摘要
	$body_content .= <<<HTML
	<div class="form-group row">
        <label class="col-sm-2 col-form-label">
	        前台摘要
            <i class="fas fa-info-circle text-secondary" data-toggle="tooltip" data-placement="bottom" title="显示于前台会员端的交易记录明細摘要"></i>
            </label>
	    <div class="col-sm-7">
	        <textarea class="form-control" id="front_system_note" placeholder="可填入摘要说明"></textarea>
	    </div>
	    <div class="col-sm-3"><div id="front_system_note_result"></div></div>
	</div>
	<br>
HTML;

	// 备注
	$body_content		= $body_content.'
	<div class="form-group row">
        <label class="col-sm-2 col-form-label">备注</label>
		<div class="col-sm-7">
			<textarea class="form-control" id="system_note_input" placeholder="備註或是說明"></textarea>
		</div>
		<div class="col-sm-3"><div id="system_note_result"></div></div>
	</div>
	';


	// source account 密码 , 需要知道來源帳號的密碼才可以提款。 除非他是管理員。
	$body_content		= $body_content.'
	<div class="form-group row">
        <label class="col-sm-2 col-form-label"><span class="text-danger">*</span>來源帳號的密码</label>
		<div class="col-sm-7">
            <input type="password" class="form-control" id="password_input" placeholder="Password"  required>
            <p class="mb-0 text-secondary"><span class="text-danger">*</span>管理员可以输入管理员密码</p>
		</div>
		<div class="col-sm-3"><div id="password_result"></div></div>
	</div>
	';


	$body_content		= $body_content.'
	<div class="form-group row">
		<div class="col-sm-2"></div>
		<div class="col-sm-7 d-flex">
            <button id="submit_to_action" class="btn btn-success w-75" type="submit">提款</button>
            <button id="submit_to_cancel" class="btn bg-light border ml-auto text-muted clear_btn" type="submit">取消</button>
		</div>
		<div class="col-sm-3"></div>
	</div>
	';


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
		$.post('member_withdrawalgcash_action.php?a=member_withdrawalgcash_check',
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

			if(confirm('確定要進行轉帳的操作？？')) {
				$.post('member_withdrawalgcash_action.php?a=member_withdrawalgcash',
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
	    if(confirm('確定要取消編輯嗎')==true){
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
// --------------------------------------
// jquery post ajax send end.
// --------------------------------------

//load 動畫
$load_animate="<div class='load_datatble_animate'><img src='./ui/loading.gif'></div>";

$indexbody_content = <<<HTML
{$load_animate}
<ul class="nav nav-tabs mt-3" id="memberoverviewTab" role="tablist">
  <li class="nav-item">
    <a class="nav-link"  href="member_overview_operating_depositgtoken.php?a=$user->id" id="bethistory-tab" role="tab" aria-controls="bethistory" aria-selected="true">
        {$tr['Manual deposit GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="member_overview_operating_withdrawalgtoken.php?a=$user->id" id="transactionrecord-tab" role="tab" aria-controls="transactionrecord" aria-selected="false">
        {$tr['Manual withdraw GTOKEN']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="member_overview_operating_depositgcash.php?a=$user->id" id="loginhistory-tab" role="tab" aria-controls="loginhistory" aria-selected="false">
		 	{$tr['Manual deposit GCASH']}
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link active" id="auditrecord-tab" data-toggle="tab" href="#" role="tab" aria-controls="auditrecord" aria-selected="false">
			{$tr['Manual withdraw GCASH']}
    </a>
  </li>
  <li class="nav-item">
  	$user_therole
  </li>
</ul>

<!-- tab內容 -->
<div class="tab-content tab_p_overview" id="overviewoperating">
    <div class="row my-3">
        <div class="col-12 col-md-8 mx-auto">
            <div class="tab-pane fade" id="bethistory" role="tabpanel" aria-labelledby="bethistory-tab">  
                <!-- 存入遊戲幣 -->
            </div>

            <div class="tab-pane fade" id="transactionrecord" role="tabpanel" aria-labelledby="transactionrecord-tab">
                <!-- 提出遊戲幣 -->	
            </div>

            <div class="tab-pane fade" id="loginhistory" role="tabpanel" aria-labelledby="loginhistory-tab">
            <!--  存入現金 -->
            </div>

            <div class="tab-pane fade show active" id="auditrecord" role="tabpanel" aria-labelledby="auditrecord-tab">
            <!--  提出現金 -->
                    {$body_content}
            </div>

                <div class="tab-pane fade" id="agentssetting" role="tabpanel" aria-labelledby="agentssetting-tab">
            <!--  代理占比設定 -->
                
            </div>
        </div>
    </div>
</div>
HTML;

$extend_js = $extend_js.<<<HTML
<!-- 參考使用 datatables 顯示 -->
<!-- https://datatables.net/examples/styling/bootstrap.html -->
<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
<link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
<script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
<style>
  /* 需要改CSS 名稱以面控制所有 */
  #transaction_list_paginate{
    display: flex;
    margin-top: 10px;
  }
  #transaction_list_paginate .pagination{
    margin-left: auto;			
	}
	#transaction_list_wrapper{
		margin-left: 0;
		margin-right: 0;
		padding-left: 0;
		padding-right: 0;
	}
  /* 清除按鈕 */
  .clear_btn{
	  width: 20%;
	}
	#transaction_list .bg-primary{
		background-color: #e0ecf9b8!important;
	}
  /* 外部Datatable search border-color */
  #search_agents{
	border-color: #ced4da!important;
	padding: .2rem .75rem;
  }
  #transaction_list_filter{
	  display:none;
  }
  #transaction_list_length {
    margin-top: 10px;
    padding-top: 0.25em;
  }
  .tab_p_overview{
		padding: 15px;
  }
</style>
<script>
		$(document).ready(function() {	
			// locationhash http:// #id
			// split  http:// #id
			var locationtab = location.hash;
			var locationsplit = locationtab.split('_');

			var locationhash = locationsplit[0];
			var locationsearch = location.search;

			if( locationhash != '' ){				
				// tab button show from http:// #id 
				$('#memberoverviewTab a[href="'+locationhash+'"]').tab('show');					
			}
			//tab button has show close load animate
			$('#memberoverviewTab a[href="'+locationhash+'"]').on('shown.bs.tab', function (e) {
				$('.load_datatble_animate').fadeOut();
			});
			//if locationhash = null or locationhash = First one tab , close load animate 
			if( locationhash == '' || locationhash == '#bethistory' ){
				$('.load_datatble_animate').hide();
			}

			//if locationtab = reward_link  load animate hide()
			if( locationtab == '#reward_link' ){	
				$('.load_datatble_animate').hide();
			}
			if( locationtab == '#commission_link' ){	
				$('.load_datatble_animate').hide();
			}
				
			$('[data-toggle="popover"]').popover();
			$('[data-toggle="tooltip"]').tooltip();
			//up open box
			$('.telescopic_btn').click(function(){
				var closeheight = $(this).next().hasClass('closeheight');				
				if( closeheight == false ){
					$(this).next().slideUp();
					$(this).next().addClass('closeheight');
				}else {
					$(this).next().slideDown();
					$(this).next().removeClass('closeheight');
				}
			});
					
		var tl_tabke =	$("#transaction_list").DataTable( {
				// "paging":   true,
				// "ordering": true,
				// "info":     true,
				// "order": [[ 1, "asc" ]],
				// "pageLength": 30,
				// 假資料
				"dom": '<ftlip>',
				"ajax": "https://shiuanlin.jutainet.com/json/historyfour.php",
				"columns": [
					{ "data": "id"},
					{ "data": "account",
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '<a href="member_account.php?a=10041" target="_blank">'+ oData.account +'</a>';
							$(nTd).html(html);
						}
					},
					{ "data": "therole","class": "text-center",
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var data = oData.therole;
							var member_text = "{$tr['member']}";
							var agent_text = "{$tr['Identity Agent']}";
							if ( data == member_text ) {
							var html = '<span class="glyphicon glyphicon-user text-primary" title="'+ member_text +'"></span>';
							}else if ( data == agent_text ) {
							var html = '<span class="glyphicon glyphicon-knight text-primary" title="'+ agent_text +'"></span>';
							}else{
							var html = data;
							}
							$(nTd).html(html);
						}
					},
					{ "data": "enrollmentdate"},
					{ "data": "status"},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					},
					{ "data": null,
						"fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
							var html = '-';
							$(nTd).html(html);
						}
					}
				]
			});

			$('#search_agents').keyup(function(){
				tl_tabke.search($(this).val()).draw();
			});			

		});
	</script>
HTML;
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $tr['member overview'] . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 主要內容 -- title
$tmpl['paneltitle_content'] = $source_transferaccount_input_default.'功能操作';
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/member_tml.php";

?>
