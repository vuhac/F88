<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 會員等級管理
// File Name:	member_grade_config.php
// Author:		Yuan
// Related:   對應前台各項功能會員等級相關資訊管理
// DB Table:  root_member_grade
// Log:
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
$function_title 		= $tr['Member level management'];
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
  <li><a href="#">' . $tr['System Management'] . '</a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------


/*
  *抓取預設會員等級設定資訊
*/
function default_member_grade_setting(){
  $sql = "SELECT * FROM root_member_grade WHERE id='1';";
  $result = runSQLall($sql);
  
  return $result[1];
}


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  表格內容 html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // 取出 DB 會員等級資訊
  $default_member_grade_setting_to_first = "CASE WHEN id='1' THEN 0 ELSE 1 END";//將"預設反水設定"置頂
  $member_grade_list_sql = "SELECT * FROM root_member_grade ORDER BY $default_member_grade_setting_to_first ASC, id LIMIT 100;";
 	// var_dump($member_grade_list_sql);
  $member_grade_list_sql_result = runSQLall($member_grade_list_sql);
 	// var_dump($member_grade_list_sql_result);
  $default_member_grade = default_member_grade_setting();

	$table_colname_html = <<<HTML
  <tr>
    <th rowspan="2">ID</th>
    <th rowspan="2">{$tr['name']}</th>
    <th rowspan="2">{$tr['status']}</th>
    <th rowspan="2">{$tr['Level Status']}</th>
    <th rowspan="2">{$tr['Company deposit limit']}</th>
    <th rowspan="1" colspan="2">{$tr['onlinepay']}</th>
    <th colspan="2">{$tr['Single withdrawal limit']}</th>
    <th rowspan="2">{$tr['withdrawal fee']}</th>
    <th rowspan="2">{$tr['method of withdrawal fee']}</th>
    <th rowspan="2">{$tr['number of members']}</th>
  </tr>
  <tr>
    <th>{$tr['Limit']}</th>
    <th>{$tr['Fee']}</th>
    <th>{$tr['wallet']}</th>
    <th>{$tr['per amount upper bound']}</th>
  </tr>
  HTML;


  // 表格內容
  $show_listrow_html = '';
  if($member_grade_list_sql_result[0] >= 1) {
    for($i=1;$i<=$member_grade_list_sql_result[0];$i++) {

			$member_grade_count_sql = "SELECT COUNT(grade) AS grade_count FROM root_member WHERE grade = '".$member_grade_list_sql_result[$i]->id."';";
			// var_dump($member_grade_count_sql);
			$member_grade_count_sql_result = runSQLall($member_grade_count_sql);
			// var_dump($member_grade_count_sql_result);

			$id = $member_grade_list_sql_result[$i]->id;
			$grade_name = $member_grade_list_sql_result[$i]->gradename;

			//開關狀態
			$grade_status = $member_grade_list_sql_result[$i]->status;
			// 會員等級狀態
			$grade_alert_status = $member_grade_list_sql_result[$i]->grade_alert_status;
			// <span class="label label-danger">Danger</span>

			// 公司入款
			$depositlimits_upper = $member_grade_list_sql_result[$i]->depositlimits_upper;
			$depositlimits_lower = $member_grade_list_sql_result[$i]->depositlimits_lower;

			// 線上支付入款
			$onlinepaymentlimits_upper = $member_grade_list_sql_result[$i]->apifastpaylimits_upper;
			$onlinepaymentlimits_lower = $member_grade_list_sql_result[$i]->apifastpaylimits_lower;
			$onlinepayfee_member_rate = $member_grade_list_sql_result[$i]->pointcardfee_member_rate;

			// 现金取款
			$withdrawallimits_cash_upper = $member_grade_list_sql_result[$i]->withdrawallimits_cash_upper;
			$withdrawallimits_cash_lower = $member_grade_list_sql_result[$i]->withdrawallimits_cash_lower;
			$withdrawalfee_cash = $member_grade_list_sql_result[$i]->withdrawalfee_cash;
			$withdrawalfee_method_cash = $member_grade_list_sql_result[$i]->withdrawalfee_method_cash;

			// 游戏币取款
			$withdrawallimits_upper = $member_grade_list_sql_result[$i]->withdrawallimits_upper;
			$withdrawallimits_lower = $member_grade_list_sql_result[$i]->withdrawallimits_lower;
			$withdrawalfee = $member_grade_list_sql_result[$i]->withdrawalfee;
			$withdrawalfee_method = $member_grade_list_sql_result[$i]->withdrawalfee_method;

			// 點卡手續費
			// $pointcardfee_member_rate = $member_grade_list_sql_result[$i]->pointcardfee_member_rate;

			// 會員數
			$member_count = $member_grade_count_sql_result[1]->grade_count;

			//開關狀態
			switch($grade_status){
			  case '0':
				$grade_status_color = 'danger';
				$grade_status_text = $tr['off'];
			  	break;
			  case '1':
				$grade_status_color = 'success';
				$grade_status_text = $tr['open'];
			  	break;
			}

			// 會員等級狀態
			switch ($grade_alert_status) {
	  case 'primary':
		$grade_alert_status_color = 'primary';
		$grade_alert_status_text = $tr['grade primary'];
		break;
	  case 'normal':
		if($id == $default_member_grade->id){
		  $grade_alert_status_color = 'info';
		  $grade_alert_status_text = $tr['grade default'];
		}else{
		  $grade_alert_status_color = 'success';
		  $grade_alert_status_text = $tr['grade normal'];
		}
		break;	
      case 'warning':
		$grade_alert_status_color = 'warning';
		$grade_alert_status_text = $tr['grade warning'];
        break;
      case 'danger':
		$grade_alert_status_color = 'danger';
		$grade_alert_status_text = $tr['grade danger'];
        break;
      default:
		$grade_alert_status_color = 'info';
		$grade_alert_status_text = $tr['grade default'];
        break;
    }

			// 公司入款儲值
			if ($depositlimits_upper == '' AND $depositlimits_lower == '') {
				$depositlimits_upper_lower_msg = $tr['Not yet set'];
			} else {
				$depositlimits_upper_lower_msg = '$'.$depositlimits_lower.'~'.$depositlimits_upper;
			}

			// 線上支付儲值
			if ($onlinepaymentlimits_upper == '' AND $onlinepaymentlimits_lower == '') {
				$onlinepaymentlimits_upper_lower_msg = $tr['Not yet set'];
			} else {
				$onlinepaymentlimits_upper_lower_msg = '$'.$onlinepaymentlimits_lower.'~'.$onlinepaymentlimits_upper;
      }

      // 線上支付儲值手續費
			if ($onlinepayfee_member_rate == '') {
				$onlinepayfee_member_rate_msg = '尚未设定';
			} else {
				$onlinepayfee_member_rate_msg = $onlinepayfee_member_rate.'%';
			}

			// 加盟金取款設定上下限額
			if ($withdrawallimits_cash_upper == '' AND $withdrawallimits_cash_lower == '') {
				$withdrawallimits_cash_upper_lower_msg = $tr['Not yet set'];
			} else {
				$withdrawallimits_cash_upper_lower_msg = '$'.$withdrawallimits_cash_lower.'~'.$withdrawallimits_cash_upper;
			}

			// 加盟金取款手續費
			if ($withdrawalfee_cash == '') {
				$withdrawalfee_cash_msg = $tr['Not yet set'];
			} else {
				$withdrawalfee_cash_msg = $withdrawalfee_cash.'%';
			}


			// 加盟金取款設定手續費收取方式
			switch ($withdrawalfee_method_cash) {
				case '1':
					$withdrawalfee_method_cash_msg = $tr['Not yet set'];
					break;
				case '2':
					$withdrawalfee_method_cash_msg = $member_grade_list_sql_result[$i]->withdrawalfee_free_hour_cash.' 小時內取款 '.$member_grade_list_sql_result[$i]->withdrawalfee_free_times_cash.' 次免收';
					break;
				default:
					$withdrawalfee_method_cash_msg = $tr['Each charge'] ;
					break;
			}

			// 現金取款設定上下限額
			if ($withdrawallimits_upper == '' AND $withdrawallimits_lower == '') {
				$withdrawallimits_upper_lower_msg = $tr['Not yet set'];
			} else {
				$withdrawallimits_upper_lower_msg = '$'.$withdrawallimits_lower.'~'.$withdrawallimits_upper;
			}

			// 現金取款手續費
			if ($withdrawalfee == '') {
				$withdrawalfee_msg = $tr['Not yet set'];
			} else {
				$withdrawalfee_msg = $withdrawalfee.'%';
			}


			// 現金取款設定手續費收取方式
			switch ($withdrawalfee_method) {
				case '1':
					$withdrawalfee_method_msg = $tr['no fee'];
					break;
				case '2':
					$withdrawalfee_method_msg = $member_grade_list_sql_result[$i]->withdrawalfee_free_hour.' 小時內取款 '.$member_grade_list_sql_result[$i]->withdrawalfee_free_times.' 次免收';
					break;
				default:
					$withdrawalfee_method_msg = $tr['Each charge'] ;
					break;
			}

			// 點卡手續費
			// if ($pointcardfee_member_rate == '') {
			// 	$pointcardfee_member_rate_msg = '尚未设定';
			// } else {
			// 	$pointcardfee_member_rate_msg = $pointcardfee_member_rate.'%';
			// }

// 			$show_listrow_html .= <<<HTML
//       <tr>
// 				<td class="text-left" id="grade_id_td">{$id}</td>
// 				<td class="text-left" id="grade_name_td"><a href="member_grade_config_detail.php?a='.$id.'">{$grade_name}</a></td>
// 				<td class="text-left grade_alert_status_td"><span class="label label-'.$grade_alert_status_color.'">{$grade_alert_status}</span></td>
// 				<td class="text-left depositlimits_upper_lower_td">{$depositlimits_upper_lower_msg}</td>
// 				<td class="text-left onlinepaymentlimits_upper_lower_td">{$onlinepaymentlimits_upper_lower_msg}</td>
// 				<td class="text-left cash_token_title_td">
// 					<div id="cash_title_div">
// 						现金
// 					</div>
// 					<div id="token_title_div">
// 						游戏币
// 					</div>
// 				</td>
// 				<td class="text-left withdrawallimits_cash_upper_lower_td">
// 					<div id="withdrawallimits_cash_upper_lower_div">
// 						{$withdrawallimits_cash_upper_lower_msg}
// 					</div>
// 					<div id="withdrawallimits_upper_lower_div">
// 						{$withdrawallimits_upper_lower_msg}
// 					</div>
// 				</td>
// 				<td class="text-left withdrawalfee_td">
// 					<div id="withdrawalfee_cash_div">
// 						{$withdrawalfee_cash_msg}
// 					</div>
// 					<div id="withdrawalfee_div">
// 						{$withdrawalfee_msg}
// 					</div>
// 				</td>
// 				<td class="text-left withdrawalfee_method_td">
// 					<div id="withdrawalfee_method_cash_div">
// 						{$withdrawalfee_method_cash_msg}
// 					</div>
// 					<div id="withdrawalfee_method_div">
// 						{$withdrawalfee_method_msg}
// 					</div>
// 				</td>
// 				<td class="text-left pointcardfee_member_rate_td">{$pointcardfee_member_rate_msg}</td>
// 				<td class="text-left member_count_td">{$member_count}</td>
// 			</tr>
// HTML;

			$show_listrow_html .= <<<HTML
      <tr>
				<td class="text-left" id="grade_id_td">{$id}</td>
				<td class="text-left" id="grade_name_td"><a href="member_grade_config_detail.php?a=$id">{$grade_name}</a></td>
				<td class="text-left" id="grade_status"><span class="label label-$grade_status_color">{$grade_status_text}</span></td>
				<td class="text-left grade_alert_status_td"><span class="label label-$grade_alert_status_color">{$grade_alert_status_text}</span></td>
				<td class="text-left depositlimits_upper_lower_td">{$depositlimits_upper_lower_msg}</td>
				<td class="text-left onlinepaymentlimits_upper_lower_td">{$onlinepaymentlimits_upper_lower_msg}</td>
        <td>{$onlinepayfee_member_rate_msg}</td>
				<td class="text-left cash_token_title_td">
					<div id="cash_title_div">
						{$tr['Franchise']}
					</div>
					<div id="token_title_div">
						{$tr['Gtoken']}
					</div>
				</td>
				<td class="text-left withdrawallimits_cash_upper_lower_td">
					<div id="withdrawallimits_cash_upper_lower_div">
						{$withdrawallimits_cash_upper_lower_msg}
					</div>
					<div id="withdrawallimits_upper_lower_div">
						{$withdrawallimits_upper_lower_msg}
					</div>
				</td>
				<td class="text-left withdrawalfee_td">
					<div id="withdrawalfee_cash_div">
						{$withdrawalfee_cash_msg}
					</div>
					<div id="withdrawalfee_div">
						{$withdrawalfee_msg}
					</div>
				</td>
				<td class="text-left withdrawalfee_method_td">
					<div id="withdrawalfee_method_cash_div">
						{$withdrawalfee_method_cash_msg}
					</div>
					<div id="withdrawalfee_method_div">
						{$withdrawalfee_method_msg}
					</div>
				</td>
				<td class="text-left member_count_td">{$member_count}</td>
			</tr>
HTML;

    }
  }


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  表格內容 html 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------


	$add_member_grade_btn = '<a href="./add_member_grade.php"><button type="button" class="btn btn-success" style="display:inline-block;float: right;margin-right: 5px;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>' . $tr['Add membership level'] . '</button></a>';



  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  擴充在 head 的 js 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
  $extend_head = $extend_head.'
	<!-- x-editable (bootstrap version) -->
	<link href="in/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
	<script src="in/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
	';

  // 強迫商户 HashIV .商户 HashKey 自動斷行 css
  // 不換行表格會超出去
//  $extend_head = $extend_head . '
//  <style>
//  #hashiv {
//    word-break: break-all;
//    word-wrap:break-word;
//  }
//  #hashkey {
//    word-break: break-all;
//    word-wrap:break-word;
//  }
//  </style>
//  ';


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  擴充在 head 的 js 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  檔案末端 js start
  // -----------------------------------------------------------------------------------------------------------------------------------------------

  // 即時編輯工具 - 修改第三方支付名稱.第三方支付註冊名稱.商户HashIV.商户HashKey.轉跳Notify URL.轉跳Return URL.單次存款限額.總存款限額 js
  $extend_js = $extend_js."
	<script>
	$(document).ready(function() {
		// for edit
		$('.edit_text').editable({
			url: 'member_grade_config_action.php?a=edit_member_grade_data',
			success: function(result){
				$('#preview_result').html(result);
			}
		});
	});
	</script>
	";

  // 即時編輯工具 - 入款帳戶是否開啟 js
  $extend_js = $extend_js . "
  <script>
  $(document).ready(function() {
    $('.status_select').editable({
      type: 'select',
      source: [{ value: 0, text: '關' }, { value: 1, text: '開' }],
      disabled: false,
      emptytext: '',
      mode: 'popup',
      url: 'member_grade_config_action.php?a=edit_member_grade_data',
      success: function(result){
				$('#preview_result').html(result);
			}
    });
  });
  </script>
  ";

  // 新增線上付商戶 js function
  $extend_js = $extend_js. "
  <script>
	$(document).ready(function() {
		$('.member_grade_send_btn').click(function() {
			var gradename = $('div').find('.member_grade_gradename_input').val();
			var depositlimits_upper = $('div').find('.member_grade_depositlimits_upper_input').val();
			var depositlimits_lower = $('div').find('.member_grade_depositlimits_lower_input').val();
			var onlinepaymentlimits_upper = $('div').find('.member_grade_onlinepaymentlimits_upper_input').val();
			var onlinepaymentlimits_lower = $('div').find('.member_grade_onlinepaymentlimits_lower_input').val();
			var administrative_cost_ratio = $('div').find('.member_grade_administrative_cost_ratio_input').val();
			var notes = $('div').find('.member_grade_notes_input').val();

			if(jQuery.trim(gradename) == '' && jQuery.trim(depositlimits_upper) == '' && jQuery.trim(depositlimits_lower) == '' && jQuery.trim(onlinepaymentlimits_upper) == '' && jQuery.trim(onlinepaymentlimits_lower) == '' && jQuery.trim(administrative_cost_ratio) == '' && jQuery.trim(notes) == '') {
				alert('请确认栏位，不可空白。');
			} else {
				$.post('member_grade_config_action.php?a=new_member_grade',
        {
          gradename: gradename,
          depositlimits_upper: depositlimits_upper,
          depositlimits_lower: depositlimits_lower,
          onlinepaymentlimits_upper: onlinepaymentlimits_upper,
          onlinepaymentlimits_lower: onlinepaymentlimits_lower,
          administrative_cost_ratio: administrative_cost_ratio,
          notes: notes,
        },
        function(result) {
          $('#preview').html(result);
        });
			}
		});
	});
  </script>
  ";


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  檔案末端 js end
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 start
  // -----------------------------------------------------------------------------------------------------------------------------------------------


  $show_list_html = '';
  $show_list_html = $show_list_html . '
	<div class="tab-content col-12 col-md-12">
		<div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
			<table id="inbox_transaction_list" class="table" cellspacing="0" width="100%">
				<thead>
					'.$add_member_grade_btn.'
					<br><br>
					'.$table_colname_html.'
				</thead>
				<tbody>
					'.$show_listrow_html.'
				</tbody>
			</table>
		</div>
	</div>
    '.'
	<div class="row">
		<div class="col-12 col-md-3">
		</div>

		<div class="col-12 col-md-6">
			<div id="preview"></div>
		</div>

		<div class="col-12 col-md-3">

		</div>
	</div>
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


  // -----------------------------------------------------------------------------------------------------------------------------------------------
  //  html 組合 end
  // -----------------------------------------------------------------------------------------------------------------------------------------------

} else {
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
include("template/beadmin_fluid.tmpl.php");
