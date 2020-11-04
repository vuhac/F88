 <?php
// ----------------------------------------------------------------------------
// Features:	後台-- 查看指定會員的帳戶紀錄 for GCASH
// File Name:	member_transactiongcash.php
// Author:		Yuan
// Related:		member_account.php
// Log:
// ----------------------------------------------------------------------------
 /*
主要操作的DB表格：
root_withdrawgcash_review 現金申請審查表
root_member_gcashpassbook 現金存款紀錄

前台
wallets.php 錢包顯示連結--取款、存簿都由這裡進入。
transactiongcash.php 前台現金的存簿
withdrawapplicationgcash.php 現金(GCASH)線上取款前台程式, 操作界面
withdrawapplicationgcash_action.php 現金(GCASH)線上取款前台動作, 會先預扣提款款項

後台
member_transactiongcash.php 後台的會員GCASH轉帳紀錄,預扣款項及回復款項會寫入此紀錄表格
withdrawalgcash_company_audit.php  後台GCASH提款審查列表頁面
withdrawalgcash_company_audit_review.php  後台GCASH提款單筆紀錄審查
withdrawalgcash_company_audit_review_action.php 後台GCASH提款審查用的同意或是轉帳動作SQL操作
*/
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
// 功能標題，放在標題列及meta
$function_title 		= $tr['GCASH transactin history'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['Members and Agents'] . '</a></li>
	<li><a href="member.php">'.$tr['Member inquiry'].'</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';
// ----------------------------------------------------------------------------



// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

	// 使用者所在的時區，sql 依據所在時區顯示 time
	// -------------------------------------
	if(isset($_SESSION['agent']->timezone) AND $_SESSION['agent']->timezone != NULL) {
		$tz = $_SESSION['agent']->timezone;
	}else{
		$tz = '+08';
	}
	// 轉換時區所要用的 sql timezone 參數，目前沒到，不需轉換時區
	/*$tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."'";
	$tzone = runSQLALL($tzsql);
	// var_dump($tzone);
	if($tzone[0]==1){
		$tzonename = $tzone[1]->name;
	}else{
		$tzonename = 'posix/Etc/GMT-8';
	}*/
	// to_char((transaction_time AT TIME ZONE '$tzonename'),'YYYY-MM-DD') as transaction_time_tz


	// 依據 ID 列出對應的帳號資料
	// -------------------------------------


	// 如果是管理員，才從 get 取得 ID. 否則就只能使用
	if($_SESSION['agent']->therole ='R' ){
		if (isset($_GET['a']) AND $_GET['a'] != NULL){
			$account_id = filter_var($_GET['a'], FILTER_SANITIZE_NUMBER_INT);
		}else{
			die('(x)不合法的测试');
		}
  	}else{
    	$account_id = $_SESSION['agent']->id;
  	}
  	// 將 ID 對應的 account , 順便取出所有的 account 資料
	$account_sql = "SELECT *,enrollmentdate FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = '".$account_id."';";
	$account_result = runSQLall($account_sql);
  	// var_dump($account_result);
	if($account_result[0] == 1) {
		$account = $account_result[1]->account;
	}else{
		$account = NULL;
	}

	//實際存提轉中文
	$realarr=array("1"=>$tr['y'],"0"=>$tr['n']);
	// var_dump($realarr);die();

	// SQL
	// $list_sql = "SELECT *, to_char((transaction_time AT TIME ZONE '$tzonename'),'YYYY-MM-DD HH24:MI:SS') as transaction_time_tz FROM root_member_gcashpassbook WHERE source_transferaccount = '$account' ORDER BY id DESC; ";
	$list_sql = "SELECT *, to_char((transaction_time AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as transaction_time_tz FROM root_member_gcashpassbook WHERE source_transferaccount = '$account' ORDER BY id DESC; ";
	// echo $list_sql;die();

	$list = runSQLall($list_sql);
	// var_dump($list);

	$show_listrow_html = '';

	if($list[0] >= 1) {

		// 列出資料每一行 for loop
		for($i=1;$i<=$list[0];$i++) {
    //人名幣格式輸出
		$balance_html = money_format('%i', $list[$i]->balance);

			$real_htm = $realarr[$list[$i]->realcash];
			// 表格 row
			$show_listrow_html = $show_listrow_html.'
			<tr>
				<td><a href="" title="'.$list[$i]->id.'">'.$i.'</a></td>
				<td>'.$list[$i]->transaction_time_tz.'</td>
				<td>'.$list[$i]->deposit.'</td>
				<td>'.$list[$i]->withdrawal.'</td>
				<td>'.$list[$i]->summary.'</td>
				<td>'.$balance_html.'</td>
				<td>'.$real_htm.'</td>
				<td>'.$list[$i]->system_note.'</td>
			</tr>
			';
		}
		// 列出資料每一行 for loop -- end
	}

	// 表格欄位名稱
	$table_colname_html = '
	<tr>
		<th>'.$tr['seq'].'</th>
		<th>'.$tr['Transaction time'].'</th>
		<th>'.$tr['deposit amount'].'</th>
		<th>'.$tr['withdrawal amount'].'</th>
		<th>'.$tr['Summary'].'</th>
		<th>'.$tr['Balance'].'</th>
		<th>'.$tr['Actual deposit'].'</th>
		<th>'.$tr['note'].'</th>
	</tr>
	';

	// 顯示這次查詢的使用者帳號
	$show_account_html = '<p><strong>'.$tr['account'].'：</strong><span class="label label-primary">'.$account.'</span></p>';

	// enable sort table 啟用可排序的表格
	$sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
	//$sorttablecss = ' class="table table-striped" ';
	//入会->註册
	$show_tips_html = '<div class="alert alert-success">
	1. '.$tr['Cash (GCASH) coins do not need to be audited, and withdrawals only require handling fees, and can be withdrawn to a bank account for use at any time.'].'<br>
	2. '.$tr['Cash (GCASH) coins can be directly used by agents to apply for registration, and agents transfer funds to offline.'].'<br>
	3. '.$tr['Cash (GCASH) can be set to transfer to game currency (GTOKEN), you can go to casino for entertainment.'].'<br>
	</div>';

	// 列出資料, 主表格架構
	$show_list_html = $show_account_html.$show_tips_html;
	$show_list_html = $show_list_html.'
	<table '.$sorttablecss.' style="text-align:center;">
	<thead>
	'.$table_colname_html.'
	</thead>
	<tfoot>
	'.$table_colname_html.'
	</tfoot>
	<tbody>
	'.$show_listrow_html.'
	</tbody>
	</table>
	';



	// 參考使用 datatables 顯示
	// https://datatables.net/examples/styling/bootstrap.html
	$extend_head = $extend_head.'
	<link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
	<script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
	<script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
	';

	// DATA tables jquery plugging -- 要放在 head 內 不可以放 body
	$extend_head = $extend_head.'
	<script type="text/javascript" language="javascript" class="init">
		$(document).ready(function() {
			$("#show_list").DataTable( {
					"paging":   true,
					"ordering": true,
					"info":     true,
          order: [[ 1, "desc" ], [ 0, "asc" ]]
			} );
		} )
	</script>
	';


	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
		<div class="col-12 col-md-12">
		'.$show_list_html.'
		</div>
	</div>
	<br>
	<div class="row">
		<div id="preview_result"></div>
	</div>
	';

}else{
	// 沒有登入的顯示提示俊息
	$show_transaction_list_html  = '(x) 只有管​​理员或有权限的会员才可以登入观看。';

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

// 頁面大標題 + 查詢的會員帳戶
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


?>
