<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 反水設定
// File Name:	preferential_calculation_config.php
// Author:		Yuan
// Related:		各項反水設定
// DB Table:  root_favorable
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
require_once dirname(__FILE__) ."/preferential_calculation_lib.php";

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
$function_title 		= $tr['Preferential setting'];
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

/**
 * 取得所有反水設定名稱
 *
 * @return array
 */
function get_all_favorable_setting_name()
{
  $default_favorable_setting_to_first = "CASE WHEN name='預設反水設定' THEN 0 ELSE 1 END";//將"預設反水設定"置頂
  $sql = "SELECT DISTINCT name, group_name, $default_favorable_setting_to_first FROM root_favorable WHERE deleted = '0' ORDER BY $default_favorable_setting_to_first ASC, name;";
  $result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  return $result;
}

/**
 * 取得指定名稱所有反水設定
 *
 * @param String $favorable_name - 反水設定名稱
 * @return object
 */
function get_specifyname_favorable_setting($favorable_name)
{
  $sql = "SELECT * FROM root_favorable WHERE name = '".$favorable_name."' AND deleted = '0' ORDER BY wager;";
	$result = runSQLall($sql);

  if (empty($result[0])) {
    return false;
  }

  return $result;
}

function get_member_count($name)
{
  $member_count_sql = "SELECT COUNT(account) AS account_count FROM root_member WHERE favorablerule = '".$name."' AND therole != 'R';";
  $member_count_sql_result = runSQLall($member_count_sql);

  if ($member_count_sql_result[0] >= 1) {
    $member_count = $member_count_sql_result[1]->account_count;
  } else {
    $member_count = 'error';
  }

  return $member_count;
}

/**
 * 生成佣金設定啟用狀態html
 *
 * @param $status - 開啟狀態
 * @return string
 */
function get_favorable_isopen_html($id, $status)
{
  global $tr;
  $default_favorable = default_favorable_setting();

  if ($status == 1 && $id == $default_favorable->id){
    $status_html = '<span class="label label-info">'.$tr['grade default'].'</span>';
  } elseif ($status == 1) {
    $status_html = '<span class="label label-success">'.$tr['open'].'</span>';
  } else {
    $status_html = '<span class="label label-danger">'.$tr['off'].'</span>';
  }

  return $status_html;
}

/**
 * 組合反水設定列表html
 *
 * @return string
 */
function get_favorable_html() {
  $commission_html = '';

	$favorable_name_list = get_all_favorable_setting_name();

	if ($favorable_name_list) {
		for ($i=1; $i <= $favorable_name_list[0]; $i++) {
      $name = $favorable_name_list[$i]->name;
      $group_name = $favorable_name_list[$i]->group_name;
      $favorable_setting = get_specifyname_favorable_setting($name);

			if ($favorable_setting) {
				for ($j=1; $j <= $favorable_setting[0]; $j++) {
					$id = $favorable_setting[$j]->id;
					$status = $favorable_setting[$j]->status;
					$wager = $favorable_setting[$j]->wager;

					$member_count = get_member_count($name);

					$status_html = get_favorable_isopen_html($id, $status);

					if ($favorable_setting[0] > 1) {
						$rowspan = 'rowspan="'.$favorable_setting[0].'"';
						if ($j == 1) {
							$commission_html = $commission_html.'
							<tr>
								<td class="text-left" '.$rowspan.'>'.$group_name.'</td>
								<td class="text-left">'.$status_html.'</td>
                <td class="text-left">$'.$wager.'</td>
                <td class="text-left">'.$member_count.'</td>
                <td class="text-left">
                  <a class="btn btn-primary btn-sm" href="preferential_calculation_config_deltail.php?i='.$id.'" role="button" title="編輯"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
                  <a class="btn btn-primary btn-sm" href="preferential_calculation_config_deltail.php?i='.$id.'&a=copy" role="button" title="複製"><span class="glyphicon glyphicon-copy" aria-hidden="true"></span></a>
                </td>
							</tr>
							';
						} else {
							$commission_html = $commission_html.'
							<tr>
                <td class="text-left">'.$status_html.'</td>
                <td class="text-left">$'.$wager.'</td>
                <td class="text-left">'.$member_count.'</td>
                <td class="text-left">
                  <a class="btn btn-primary btn-sm" href="preferential_calculation_config_deltail.php?i='.$id.'" role="button" title="編輯"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
                  <a class="btn btn-primary btn-sm" href="preferential_calculation_config_deltail.php?i='.$id.'&a=copy" role="button" title="複製"><span class="glyphicon glyphicon-copy" aria-hidden="true"></span></a>
                </td>
							</tr>
							';
						}
					} else {
						$commission_html = $commission_html.'
            <tr>
              <td class="text-left">'.$group_name.'</td>
              <td class="text-left">'.$status_html.'</td>
              <td class="text-left">$'.$wager.'</td>
              <td class="text-left">'.$member_count.'</td>
              <td class="text-left">
                <a class="btn btn-primary btn-sm" href="preferential_calculation_config_deltail.php?i='.$id.'" role="button" title="編輯"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></span></a>
                <a class="btn btn-primary btn-sm" href="preferential_calculation_config_deltail.php?i='.$id.'&a=copy" role="button" title="複製"><span class="glyphicon glyphicon-copy" aria-hidden="true"></span></a>
              </td>
            </tr>
						';
					}
				}
			} else {
				$commission_html = '尚未反水設定列表，或資料查詢錯誤。';
			}
		}
	} else {
		$commission_html = '尚未反水設定列表，或資料查詢錯誤。';
	}

  return $commission_html;
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  $favorable_html = get_favorable_html();

  // 表格欄位名稱
  $table_colname_html = '
  <tr>
    <th class="w-50">' . $tr['name'] . '</th>
    <th>' . $tr['status'] . '</th>
    <th>' . $tr['betting amount'] . '</th>
    <th>' . $tr['number of members'] . '</th>
    <th>' . $tr['function'] . '</th>
  </tr>
  ';

  $show_list_html = '';
  $show_list_html = $show_list_html . '
	<div class="tab-content col-12 col-md-12">
		<div role="tabpanel" class="tab-pane active col-12 col-md-12" id="inbox_View">
      <table id="inbox_transaction_list" class="table" cellspacing="0" width="100%">
        <a class="btn btn-success mb-2" href="./add_preferential_calculation.php" role="button" style="float: right;"><span class="glyphicon glyphicon-plus" aria-hidden="true"></span>&nbsp; ' . $tr['add preferential'] . '</a>
        <br>
				<thead>
					'.$table_colname_html.'
				</thead>
				<tbody>
					'.$favorable_html.'
				</tbody>
			</table>
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

} else {
  // 沒有登入的顯示提示俊息
  $show_transaction_list_html  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

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
