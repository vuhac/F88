<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 佣金設定
// File Name:	commission_setting.php
// Author:		Neil
// Related:		各項佣金設定
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
$function_title 		= $tr['Commission setting'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['System Management'].'</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

function get_commission_html($sel_setting=null) {
  $commission_html = '';
	global $tr;
	$commission_name_list = get_all_commission_setting_name();

	if ($commission_name_list != null) {
		for ($i=1; $i <= $commission_name_list[0]; $i++) {
			$name = $commission_name_list[$i]->name;
			$group_name = $commission_name_list[$i]->group_name;
			$commission_setting = get_specifyname_commission_setting($name,$sel_setting);

			if ($commission_setting != null) {
				for ($j=1; $j <= $commission_setting[0]; $j++) {
					$id = $commission_setting[$j]->id;
					$status = $commission_setting[$j]->status;
					if($sel_setting=='deposit_bet'){
						$payoff='';
						if(isset($commission_setting[$j]->downline_effective_bet)){
							$payoff = $commission_setting[$j]->downline_effective_bet;
						}
					}else{
						$payoff = $commission_setting[$j]->payoff;
					}
					$lowest_bet = $commission_setting[$j]->lowest_bet;
					$lowest_deposit = $commission_setting[$j]->lowest_deposit;

					$agent_count = get_agent_count($name);

					$status_html = get_commission_isopen_html($id, $status);

					if ($commission_setting[0] > 1) {
						$rowspan = 'rowspan="'.$commission_setting[0].'"';
						if ($j == 1) {
							$commission_html = $commission_html.'
							<tr>
								<td class="text-left" '.$rowspan.'>'.$group_name.'</td>
								<td>'.$status_html.'</td>
								<td>$'.$payoff.'</td>
								<td>$'.$lowest_bet.'</td>
								<td>$'.$lowest_deposit.'</td>
								<td>'.$agent_count.'</td>
								<td>
								<a class="btn btn-primary btn-sm" href="commission_setting_deltail.php?i='.$id.'" role="button" title="'.$tr['edit'].'"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
								<a class="btn btn-primary btn-sm" href="commission_setting_deltail.php?i='.$id.'&a=copy" role="button" title="'.$tr['copy'].'"><span class="glyphicon glyphicon-copy" aria-hidden="true"></span></a>
								</td>
							</tr>
							';
						} else {
							$commission_html = $commission_html.'
							<tr>
								<td>'.$status_html.'</td>
								<td>$'.$payoff.'</td>
								<td>$'.$lowest_bet.'</td>
								<td>$'.$lowest_deposit.'</td>
								<td>'.$agent_count.'</td>
								<td>
									<a class="btn btn-primary btn-sm" href="commission_setting_deltail.php?i='.$id.'" role="button" title="'.$tr['edit'].'"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
									<a class="btn btn-primary btn-sm" href="commission_setting_deltail.php?i='.$id.'&a=copy" role="button" title="'.$tr['copy'].'"><span class="glyphicon glyphicon-copy" aria-hidden="true"></span></a>
								</td>
							</tr>
							';
						}
					} else {
						$commission_html = $commission_html.'
						<tr>
							<td class="text-left">'.$group_name.'</td>
							<td>'.$status_html.'</td>
							<td>$'.$payoff.'</td>
							<td>$'.$lowest_bet.'</td>
							<td>$'.$lowest_deposit.'</td>
							<td>'.$agent_count.'</td>
							<td>
								<a class="btn btn-primary btn-sm" href="commission_setting_deltail.php?i='.$id.'" role="button" title="'.$tr['edit'].'"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
								<a class="btn btn-primary btn-sm" href="commission_setting_deltail.php?i='.$id.'&a=copy" role="button" title="'.$tr['copy'].'"><span class="glyphicon glyphicon-copy" aria-hidden="true"></span></a>
							</td>
						</tr>
						';
					}
				}
			} else {
				$commission_html = '尚未佣金设定列表，或资料查询错误。';
			}
		}
	} else {
		$commission_html = '尚未佣金设定列表，或资料查询错误。';
	}

  return $commission_html;
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

	$commission_html = get_commission_html();
	$commission_html_dpb = get_commission_html('deposit_bet');

	// 表格欄位名稱
	$table_colname_html = '
	<tr>
		<th class="w-25">'.$tr['name'].'</th>
		<th>'.$tr['status'].'</th>
		<th>'.$tr['Payout'].'</th>
		<th>'.$tr['effective member minimum bet amount'].'</th>
		<th>'.$tr['effective member minimum deposit amount'].'</th>
		<th>'.$tr['agent'].'</th>
		<th>'.$tr['function'].'</th>
	</tr>
	';

	$show_list_html = '
	<div class="tab-content col-12 col-md-12">
	<br>
	<div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
		<table id="inbox_transaction_list" class="table" cellspacing="0" width="100%">
			<a class="btn btn-success" href="./add_commission_setting.php" role="button" style="float: right;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp;' . $tr['Added commission setting'] . '</a><br><br>
			<thead>
				' . $table_colname_html . '
			</thead>
			<tbody>
				' . $commission_html . '
			</tbody>
		</table>
	</div>
	</div>
	';


	// 存款投注表格欄位名稱
	$table_colname_html_dpb = '
	<tr>
		<th class="w-25">'.$tr['name'].'</th>
		<th>'.$tr['status'].'</th>
		<th>'.$tr['Downline minimum total bet'].'</th>
		<th>'.$tr['effective member minimum bet amount'].'</th>
		<th>'.$tr['effective member minimum deposit amount'].'</th>
		<th>'.$tr['agent'].'</th>
		<th>'.$tr['function'].'</th>
	</tr>
	';

	$show_list_html_dpb = '
	<div class="tab-content col-12 col-md-12">
	<br>
	<div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
		<table id="inbox_transaction_list" class="table" cellspacing="0" width="100%">
			<a class="btn btn-success" href="./add_commission_setting.php" role="button" style="float: right;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp;' . $tr['Added commission setting'] . '</a><br><br>
			<thead>
				' . $table_colname_html_dpb . '
			</thead>
			<tbody>
				' . $commission_html_dpb . '
			</tbody>
		</table>
	</div>
	</div>
	';


	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="col-12 tab">
		<ul class="nav nav-tabs" id="myTab" role="tablist">
			<li class="nav-item">
				<a class="nav-link" id="payout-tab" data-toggle="tab" href="#payout_sort" role="tab" aria-controls="payout" aria-selected="true">'.$tr['payout spacing'].'</a>
			</li>
			<li class="nav-item">
				<a class="nav-link active" id="depobet-tab" data-toggle="tab" href="#depobet_sort" role="tab" aria-controls="depobet_sort" aria-selected="false">'.$tr['effective bet amount spacing'].'</a>
			</li>
		</ul>
	</div>

	<div class="tab-content" id="myTabContent">
		<div class="tab-pane fade" id="payout_sort" role="tabpanel" aria-labelledby="payout-tab">
			<div class="row">
				<div class="col-12 col-md-12">'.$show_list_html.'</div>
			</div>
			<br>
			<div class="row">
				<div id="preview_result"></div>
			</div>

		</div>
		<div class="tab-pane fade show active" id="depobet_sort" role="tabpanel" aria-labelledby="depobet-tab">
			<div class="row">
				<div class="col-12 col-md-12">'.$show_list_html_dpb.'</div>
			</div>
			<br>
			<div class="row">
				<div id="preview_result"></div>
			</div>

		</div>
	</div>
	';


} else {
	// 沒有登入的顯示提示俊息
	$show_html  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_html.'
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
//include("template/beadmin_fluid.tmpl.php");

?>
