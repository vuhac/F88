<?php
// ----------------------------------------------------------------------------
// Features:	後台--娛樂城管理
// File Name:	casino_switch_process.php
// Author:		Ian
// Related:		casino_switch_process_action.php casino_switch_process_cmd.php
// Log:
// 2019.01.31 新增 Gapi 遊戲清單管理頁籤 Letter
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 遊戲管理列表專用函式庫
//require_once dirname(__FILE__) ."/casino_switch_process_lib.php";

// var_dump($_SESSION);

// var_dump(session_id());
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
$function_title 		= $tr['Lottery Management'];
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
  <li><a href="#">' .$tr['System Management'].' </a></li>
  <li class="active">'.$function_title.'</li>
</ol>';

$mct_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ? '<li><a href="maincategory_editor.php" target="_self">'.$tr['MainCategory Management'].'</a></li>' : '';

// 依權限顯示 GAPI 遊戲清單管理頁籤
$gapi_gamelist_management_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ?
	'<li><a href="gapi_gamelist_management.php" target="_self">'.$tr['gapi gamelist management'].'</a></li>' : '';

$casino_casino_switch_html = '<div class="col-12 tab mb-3">
<ul class="nav nav-tabs">
    <li><a href="casino_switch_process.php" target="_self">
    '.$tr['Casino Management'].'</a></li>
    '.$mct_html.'
    <li><a href="game_management.php" target="_self">
    '.$tr['Game Management'].'</a></li>
    <li class="active"><a href="" target="_self">
    '.$tr['Lottery Management'].
	$gapi_gamelist_management_html.'</a></li>
  </ul></div>';

// ----------------------------------------------------------------------------

// check permission
if(!isset($_SESSION['agent']) OR !in_array($_SESSION['agent']->account, $su['superuser'])){
  http_response_code(404);
  die('(x)不合法的測試');
}


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------
if($gamelobby_setting['main_category_info']['Lottery']['open'] == 1) {
  /**
   * IG 后台
   */
  $ig_backoffice = '<div class="mr-2"><a href="'.$IG_CONFIG['backoffice'].'" class="btn btn-secondary" type="button" value="button" target="_new"><img src="'.$cdnrooturl.'casinologo/IG.png" height="60" alt="IG"></a></div>';

  $indexbody_content .= $ig_backoffice;

  /**
   * MEGA 彩票后台
   */
  /*require_once dirname(__FILE__).'/casino/MEGA/casino_switch_lib.php';
  $MEGA_API_result = mega_gpk_api('GetBackOfficeUrl', '0', '');
  $mega_backoffice_url = ($MEGA_API_result['errorcode'] == 0 and $MEGA_API_result['Status'] == 1 and $MEGA_API_result['count'] > 0) ? $MEGA_API_result['Result']->url : '';

  // var_dump($mega_backoffice_url);
  $mega_backoffice = '<div class="mr-2"><a href="'.$mega_backoffice_url.'" class="btn btn-secondary" type="button" value="button" target="_new"><img src="'.$cdnrooturl.'casinologo/MEGA.png" height="60" alt="MEGA"></a></div>';

  $indexbody_content .= $mega_backoffice;*/

  /**
   * RG 彩票後台
   */
  /**
   * 隨意字串生成器
   *
   * @param int $count 需要字串長度
   *
   * @return string 生成字串
   */
  function randomStrGenerator($count = 5)
  {
  	$seed = str_split('abcdefghijklmnopqrstuvwxyz'
  		. 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
  		. '0123456789_');
  	shuffle($seed);
  	$rand = '';
  	foreach (array_rand($seed, $count) as $k) $rand .= $seed[$k];
  	return $rand;
  }


  /**
   * 生成API Key
   *
   * @param string $secret API演算key
   * @param array  $params 參數
   *
   * @return string API Key
   */
  function genApiKey(string $secret, array $params = []): string
  {
  	$head = randomStrGenerator(5);
  	$footer = randomStrGenerator(5);
  	$middle = '';
  	foreach ($params as $key => $value) {
  		if ($key == 'memberBranch') {
  			$middle = $middle . $key . '=' . json_encode($value) . '&';
  		} else {
  			$middle = $middle . $key . '=' . $value . '&';
  		}
  	}
  	return $head . md5($middle . 'Key=' . $secret) . $footer;
  }


  /**
   * 取得會員娛樂城遊戲帳號
   *
   * @param int    $memberId 會員 ID
   * @param string $casinoId 娛樂城 ID
   *
   * @return mixed 娛樂城遊戲帳號查詢結果，無帳號回傳 null
   */
  function getGameAccountByMemberId(int $memberId, string $casinoId)
  {
  	$sql = "SELECT casino_accounts -> '" . $casinoId . "' ->> 'account' FROM root_member_wallets WHERE id = " . $memberId;
  	$result = runSQLall($sql);
  	if ($result[0] == 0) {
  		return null;
  	} else {
  		return get_object_vars($result[1])['?column?'];
  	}
  }


  /**
   * 生成娛樂城遊戲帳號
   *
   * @param int    $memberId 會員ID
   * @param int    $base     基底位數，如遊戲帳號為十位數，填入長度為 10 減去 $prefix 字串長度
   * @param string $prefix   字首
   *
   * @return string 遊戲帳號
   */
  function genGameAccountByMemberId(int $memberId, int $base, string $prefix): string
  {
  	$account = $base + $memberId;
  	return $prefix . $account;
  }


  $projectId = $config['projectid'];
  $masterId = getGameAccountByMemberId($config['system_company_id'], 'RG');
  if (is_null($masterId)) {
  	$masterId = genGameAccountByMemberId($config['system_company_id'], 20000000000, $config['projectid']);
  }
  $RG_API_data = array(
      'masterId' => $masterId
  );
  $url = $RGAPI_CONFIG['api_url'] . $RGAPI_CONFIG['sub_url']['LotteryMasterLogin'];
  $key = genApiKey($RGAPI_CONFIG['apikey'], $RG_API_data);
  $apiUrl = $url . 'Key=' . $key . '&masterId=' . $RG_API_data['masterId'];

  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  $_SERVER['DOCUMENT_ROOT'] .'/cacert.pem');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  //if ($_SESSION['site_mode'] == 'mobile') {
  //	curl_setopt($ch, CURLOPT_USERAGENT, isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'UserAgent');
  //}
  $response = curl_exec($ch);
  $rgBackofficeUrl = json_decode($response)->data;

  $rgBackoffice = '<div class="mr-2">
  <a href="'.$rgBackofficeUrl.'" class="btn btn-secondary" type="button" value="button" target="_new">
  <img src="'.$cdnrooturl.'casinologo/RG.png" height="60" alt="RG"></a>
  </div>';

//  $indexbody_content .= $rgBackoffice;
}else{
  $notify_str = (isset($tr['lottery not enable']))? $tr['lottery not enable'] : 'lottery not enable';
  $indexbody_content .= '<center>'.$notify_str.'</center>';
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
$tmpl['panelbody_content']				= $casino_casino_switch_html.'<div class="d-flex justify-content-center">'.$indexbody_content.'</div>';


// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/dashboard.tmpl.php");
// include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");

?>
