<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員錢包檢視與互轉帳
// File Name:	member_wallets.php
// Author:		Barkley
// Related:		member_account.php
// Permission: 每個代理商會員都可以執行與查驗
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
// 自訂函式庫
require_once dirname(__FILE__) ."/casino/casino_config.php";

require_once dirname(__FILE__) ."/member_lib.php";
require_once dirname(__FILE__) ."/casino_switch_process_lib.php";

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
global $system_mode;

// 功能標題，放在標題列及meta
$function_title 		= $tr['Transfer and recycle wallet'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';

// 主要內容 -- title
$paneltitle_content 	= $function_title;

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

$id = $_GET['a'];

// title and 功能說明文字
$page_title = '<h2><strong>'.$function_title.'</strong></h2><hr>';
// ----------------------------------------------------------------------------
$indexbody_content	= '';

global $su;
$casinoLib = new casino_switch_process_lib();
// ----------------------------------------------------------------------------
// 此程式功能說明：
// 以使用者帳號為主軸 , 管理者可以操作各種動作。

// var_dump($_SESSION);

// --------------------------------------------------------
// 只有 root 才可以存入 , 不是 root 則顯示錯誤訊息提式。
// --------------------------------------------------------
if($_SESSION['agent']->therole == 'R' ) {

	// 功能說明文字
	$page_desc	='
		<div class="alert alert-success" role="alert">
			<p class="mb-0">* '.$tr['check all status'].'</p>
			<p class="mb-0">* '.$tr['realtime check'].'</p>
			<p class="mb-0">* '.$tr['check casino data'].'</p>
			<p class="mb-0">* '.$tr['can not transfer to casino'].'</p>
		</div>';

	// 功能及操作的說明
	$deposit_body_content = $page_desc;

	// 查詢來源帳號的資料
	$g = (object)get_memberdata_byid($id, true);

	$check_therole_result = (object)check_member_therole($g->result);
	if (!$check_therole_result->status) {
		$error_mag = $check_therole_result->result;
		echo '<script>alert("'.$error_mag.'");window.location = "./member.php";</script>';
	}

	if($g->status) {
		// --------------------------------
		// GPK 現金
		$gcash_table_html = '';
		// $gcash_table_html = '
		//   <input type="hidden" id="member_id" name="member_id" value="'.$g->result->id.'">
		// 	<table class="table table-bordered">
		// 		<thead>
		// 			<tr>
		// 				<th>会员编号</th>
		// 				<th>会员帐号</th>
		// 				<th>会员名称</th>
		// 				<th>点数位置</th>
		// 				<th>余额</th>
		// 			</tr>
		// 		</thead>
		// 		<tr>
		// 			<td>'.$g->result->id.'</td>
		// 			<td>'.$g->result->account.'</td>
		// 			<td>'.$g->result->realname.'</td>
		// 			<td>現金(GCASH)</td>
		// 			<td><strong>'.$g->result->gcash_balance.'</strong></td>
		// 		</tr>
		// 	</table>
		// 	';

		// --------------------------------
		// 代幣呈現
		$cash_row_html = '';
		// 目前錢包所在哪裡？ NULL 等於沒有鎖定
		$gtoken_status_html = '';
		$gtoken_status_now = '';
		if($g->result->gtoken_lock == NULL OR $g->result->gtoken_lock == '') {
			$gtoken_status_html = '<span class="glyphicon glyphicon-lock" aria-hidden="true"></span>';
			$gtoken_status_now = $g->result->gtoken_balance;
		}else{
			// 顯示轉移錢包的按鈕
			$gtoken_status_html = '<button type="button" id="gtokenrecycling" class="btn btn-primary js-disable" onclick="agent_retrieve(\''.$g->result->id.'\');">'.$tr['transfer back all'].'</button>';
			$gtoken_status_now = '<span class="glyphicon glyphicon-lock" aria-hidden="true"></span>';
		}
    $lock_chk = agent_walletscontrol_check($g->result->account);
    if(isset($lock_chk) AND $lock_chk != '' AND in_array($_SESSION['agent']->account, $su['superuser'])){
			// 顯示轉移錢包的按鈕
			$unlock_btn_html = '<button type="button" id="gtokenrecycling" class="btn btn-danger" onclick="unlock_member_wallets(\''.$g->result->id.'\');">'.$tr['unlock wallet proceaa'].'</button>';
      $extend_js = $extend_js.'
  		<script type="text/javascript" language="javascript" class="init">
  		function unlock_member_wallets(id){
  			var url = "member_wallets_action.php?a=agent_unlock_member_wallets&t="+id;
  			$.get(url, function(result) {
  					alert(result);
  					window.location.reload();
  			});
  		}
  		</script>';
    }else{
      $unlock_btn_html = '';
    }
		// GPK 代幣 col name
		$cash_row_html = $cash_row_html.'
		<tr>
			<td>'.$tr['Gtoken'].'(GTOKEN)</td>
			<td>'.$g->result->gtoken_balance.'</td>
			<td align="center">'.$gtoken_status_html.$unlock_btn_html.'</td>
			<td>'.$tr['platform'].'</td>
		</tr>
		';

		// --------------------------------
		// 目前錢包所在哪裡？ MG
		// --------------------------------

		// 取得目前娛樂城的啟用狀態
		$casino_list_sql = 'SELECT * FROM "casino_list" WHERE open=\'1\';';
		$casino_list_result = runSQLall($casino_list_sql);
		//var_dump($casino_list_result);
		if($casino_list_result[0] >= 1){
		  for($i=1;$i<=$casino_list_result[0];$i++){
				$token_status_html = '';
				$casinoid_now = $casino_list_result[$i]->casinoid;
				$casinoname_default = $casinoLib->getCurrentLanguageCasinoName($casino_list_result[$i]->display_name,
					'default');
				$casinoname_now = $casinoLib->getCurrentLanguageCasinoName($casino_list_result[$i]->display_name,
					$_SESSION['lang']);
				$balance_columnname = strtolower($casinoid_now).'_balance';
				$account_columnname = strtolower($casinoid_now).'_account';
				$password_columnname = strtolower($casinoid_now).'_password';

				$btn_display = '';
				if($casino_list_result[$i]->open == 0 OR ($g->result->gtoken_lock != $casinoid_now AND
						(!is_null($g->result->gtoken_lock) OR $g->result->gtoken_lock != ''))){
					$btn_display = 'disabled';
				}

				if($g->result->gtoken_lock == $casinoid_now) {
					$token_status_html = '<span class="glyphicon glyphicon-lock" aria-hidden="true"></span>';
				}elseif($config['businessDemo'] == 1 AND !in_array($casinoid_now,$config['businessDemo_skipCasino'])){
					$token_status_html = $tr['not provide for business demo'];
				}else{
					// 顯示轉移錢包的按鈕,
					// $config['casino_transfer_mode']娛樂城轉帳設定   0: 測試環境，不進行轉帳    1: 正式環境，會正常進行轉帳作業
					if($config['casino_transfer_mode'] == 0 OR $system_mode == 'developer') {
						$token_status_html = '<button type="button" id="gtoken2mg" class="btn btn-success" onclick="alert(\'测试环境不执行转钱动作 '.$casinoname_default.' '.$g->result->id.'\');" '.$btn_display.'>'.$tr['transfer to'].''.$casinoname_default.'</button>';
					}else{
						$token_status_html = '<button type="button" id="gtoken2mg" class="btn btn-success js-disable" onclick="agent_trans(\''.$casinoid_now.'\',\''.$g->result->id.'\');" '.$btn_display.'>'.$tr['transfer to'].''.$casinoname_default.'</button>';
					}

				}
				// 此使用者在娛樂城的帳號及密碼, 只有管理者才看得到。
				if($_SESSION['agent']->therole == 'R') {
					if(!isset($g->result->$account_columnname) OR ($g->result->$account_columnname == NULL AND $g->result->$password_columnname == NULL) ){
						$account_pwd_html = $tr['casino account'].$tr['not created'];
            			$g->result->$balance_columnname = '';
					}else{
						$account_pwd_html = $g->result->$account_columnname;//.'/'.$g->result->$password_columnname;娛樂城密碼
					}
				}else{
					$account_pwd_html = $tr['admin only'];
				}

				// 取得目前娛樂城餘額
				require_once getRequirePath($casinoid_now);
				#$balance_now = getCasinoBalance($casinoid_now)($g->result->id);

				// 設定顯示表單
				$cash_row_html = $cash_row_html.'
				<tr>
					<td>'.$casinoname_now.'</td>
					<td>'.$g->result->$balance_columnname.'</td>
					<td align="center">'.$token_status_html.'</td>
					<td>'.$account_pwd_html.'</td>
				</tr>
				';
		  }
		}
		$Previouspage='
		<div>
            <button id="submit_to_Previouspage" class="btn btn-info btn-block" type="submit">'. $tr['back to previous page'] .'</button>
        </div>';
		$extend_js = $extend_js.'
		<script type="text/javascript" language="javascript" class="init">
		function agent_trans(casinoid,id){
			var url = "member_wallets_action.php?a=agentTransferoutmember_Casino_balance&t="+id+"&cid="+casinoid;
			$(".js-disable").attr("disabled",true);
			$.get(url, function(result) {
					alert(result);
					window.location.reload();
			});
		}
		function agent_retrieve(id){
			var url = "member_wallets_action.php?a=agentRetrievemember_Casino_balance&t="+id;
			$(".js-disable").attr("disabled",true);
			$.get(url, function(result) {
					alert(result);
					window.location.reload();
			});
		}

		//按回上一頁，則彈出視窗，確定是否離開
		$("#submit_to_Previouspage").click(function(){
		    if(confirm("'.$tr['page_management_detail-form-cancel_alert_msg'].'？")==true){
		      document.location.href="member_account.php?a='.$g->result->id.'";
		    }
		});


		</script>';

		// --------------------------------
		// 代幣 列表表格
		$gtoken_table_html = '
		<table class="table table-bordered" style="text-align: center !important;">
		<thead>
			<tr>
				<th style="text-align: center !important;">'.$tr['wallet location'].'</th>
				<th style="text-align: center !important;">'.$tr['Balance'].'</th>
				<th style="text-align: center !important;">'.$tr['wallet status'].'</th>
				<th style="text-align: center !important;">'.$tr['casino account'].'</th>
			</tr>
		</thead>
		<tbody>
			'.$cash_row_html.'
		</tbody>
		</table>
		';
	}
	// end sql

	// 現金
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2">
		</div>
		<div class="col-12 col-md-8">
		'.$gcash_table_html.'
		</div>
		<div class="col-12 col-md-2">
		</div>
	</div>
	';

	// 代幣
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2">
		</div>
		<div class="col-12 col-md-8">
		'.$gtoken_table_html.$Previouspage.'
		</div>
		<div class="col-12 col-md-2">
		</div>
	</div>
	';

	// 動作後的回應訊息
	$deposit_body_content		= $deposit_body_content.'
	<div class="row">
		<div class="col-12 col-md-2">

		</div>
		<div class="col-12 col-md-4">

		</div>
		<div class="col-12 col-md-6">

		</div>
	</div>
	';


	$panelbody_content		= $deposit_body_content;
} else {

	// --------------------------------------------------------
	// 不合法的訊息
	// --------------------------------------------------------
	$deposit_body_content_stop = '
	<div class="row">
		<div class="col-12 col-md-1"></div>
		<div class="col-12 col-md-6">
			<p>'.$tr['permission denial'].'</p>
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
