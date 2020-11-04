<?php
// ----------------------------------------------------------------------------
// Features:	後台--會員操作記錄
// File Name:	member_betlog.php
// Author:		snowiant@gmail.com
// Related:
//    member_betlog_action.php member_betlog_lib.php
//    DB table: root_memberlog
//    member_betlog：有收到 _GET 時會將 _GET 取得的值進行驗證，並檢查是否為可查詢對象，如果是
//        就直接丟入 $query_sql_array 中再引用 member_betlog_lib.php 中的涵式
//        show_member_betloginfo($query_sql_array) 並將返回的資料放入 table 中給
//        datatable 處理。如果沒收到 _GET 值就顯示無資料的查詢介面，並在使用者按下"查詢"時
//        以 ajax 丟給 member_betlog_action.php 來查詢並將返回資料顯示出來。
// Log:
// 2020.03.04 Bug #3623 VIP后台>系统管理>佣金设定>新增反水设定;Notice: Undefined index: sports； Letter
//            娱乐城读取ID名称需与娱乐城改名后整合
// 2020.08.07 Bug #4414 VIP站後台，投注紀錄查詢 > [娛樂城排序]按鈕 > 跳錯誤訊息 Letter
//            增加判斷排序欄位名稱邏輯
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
require_once dirname(__FILE__) ."/config_betlog.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_view.php";


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

global $tr;

// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -----------------------------------------


/**
 *  取得平台反水分類
 *
 * @param int $debug 除錯模式，預設 0 為非除錯模式
 *
 * @return array 反水類別與對應值
 */
function getFavorableTypes($debug = 0)
{
	$sql = 'SELECT * FROM root_protalsetting WHERE "name" = \'favorable_types\';';
	$result = runSQLall($sql, $debug);
	$types = [];
	if ($result[0] > 0) {
		$types = json_decode($result[1]->value, true);
	}
	return $types;
}


/**
 *  取得反水分類對應語言檔名稱
 *
 * @param int $debug 除錯模式，預設 0 為非除錯模式
 *
 * @return array 反水分類與對應名稱
 */
function getFavorableTypeToNameArray($debug = 0)
{
	global $tr;
	$types = getFavorableTypes($debug);
	$typeKeys = array_keys($types);
	$names = array();
	for ($i = 0; $i < count($typeKeys); $i++) {
		$subtypes = $types[$typeKeys[$i]];
		foreach ($subtypes as $k => $v) {
			$names[$v] = isset($tr[$v]) ? $tr[$v] : $v;
		}
	}
	return $names;
}


/**
 *  取得開啟娛樂城內反水類別
 *
 * @param int $debug 除錯模式，預設 0 為非除錯模式
 *
 * @return array 反水分類與對應名稱
 */
function getFlatformListByOpenCasinos($debug = 0)
{
	global $tr;
	$sql = 'SELECT DISTINCT (json_array_elements_text (game_flatform_list))::text as category FROM casino_list WHERE "open" = 1;';
	$result = runSQLall($sql, $debug);
	$names = array();
	if ($result[0] > 0) {
		for ($i = 1; $i <= $result[0]; $i++) {
			$category = $result[$i]->category;
			$names[$category] = isset($tr[$category]) ? $tr[$category] : $category;
		}
	}

	return $names;
}


$debug = 0;

// --------------------------------------------------------------------------
// 取得 get 傳來的變數
// --------------------------------------------------------------------------
$query_sql = '';
$query_chk = 0;
$account_query = null;
if(isset($_GET)){
  if(isset($_GET['a'])) {
    $query_sql = $query_sql.'&a='.filter_var($_GET['a'], FILTER_SANITIZE_STRING);
    $account_query = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
    $query_chk = 1;
  }
  if(isset($_GET['bet_sdate']) AND $_GET['bet_sdate'] != NULL ) {
      // 判斷格式資料是否正確
    if(validateDate($_GET['bet_sdate'], 'Y-m-d H:i:s') || validateDate($_GET['bet_sdate'], 'Y-m-d H:i')) {
      $query_sql = $query_sql.'&betdate_start='.$_GET['bet_sdate'];
      $sdate_query = $_GET['bet_sdate'];
      $query_chk = 1;
    }
  }
  if(isset($_GET['bet_edate']) AND $_GET['bet_edate'] != NULL ) {
      // 判斷格式資料是否正確
    if(validateDate($_GET['bet_edate'], 'Y-m-d H:i:s') || validateDate($_GET['bet_edate'], 'Y-m-d H:i')) {
      $query_sql = $query_sql.'&betdate_end='.$_GET['bet_edate'];
      $edate_query = $_GET['bet_edate'];
      $query_chk = 1;
    }
  }

  // 預設區間為一天
  if(!isset($sdate_query) AND !isset($edate_query)){
    // 轉換為美東的時間 date
    // $current_datepicker = gmdate('Y-m-d H:i',time() + -4*3600);
    // $default_startdate = gmdate('Y-m-d ',time() + -4*3600).'00:00';
    // $default_enddate = gmdate('Y-m-d H:i',time() + -4*3600);

    $current_datepicker = gmdate('Y-m-d',time() + -4*3600).' 23:59:59';
    $default_startdate = gmdate('Y-m-d ',strtotime('- 7 days')).'00:00:00';
    // $default_enddate = gmdate('Y-m-d H:i:s',time() + -4*3600);

    $sdate_query = $default_startdate;
    // $edate_query = $default_enddate;
    $edate_query = $current_datepicker;

    $query_sql .= '&betdate_start='.$sdate_query;
    $query_sql .= '&betdate_end='.$edate_query;

  }elseif(!isset($sdate_query)){

    $default_startdate = gmdate('Y-m-d',strtotime('-7 day')).'00:00:00';


    $sdate_query = $default_startdate;
    $query_sql = $query_sql.'&betdate_start='.$sdate_query;

  }elseif(!isset($edate_query)){
    $current_datepicker = gmdate('Y-m-d',time() + -4*3600).' 23:59:59';
    // $current_datepicker = gmdate('Y-m-d H:i:s',time() + -4*3600);

    $edate_query = $current_datepicker;
    $query_sql = $query_sql.'&betdate_end='.$edate_query;
  }
  $query_chk = 1;
}

if( $query_chk == 0){
  $query_sql = '';
}

// 查詢條件 - 娛樂城列表
$menu_casinolist_item_sql = 'SELECT * FROM casino_list WHERE "open" = 1 ORDER BY id;';
$menu_casinolist_items = runSQLall($menu_casinolist_item_sql, $debug, 'r');
unset($menu_casinolist_items[0]);
foreach($menu_casinolist_items as $menu_casinolist_items_value){
	foreach(json_decode($menu_casinolist_items_value->game_flatform_list) as $bonus_list){
        $query_sql.='&casino_favorable_qy[]='.$menu_casinolist_items_value->casinoid.'_'.$bonus_list ;
    }
}

//反水種類
$menu_bonus_cate_item = getFlatformListByOpenCasinos($debug);

//查詢條件 - 注單狀態
$menu_bet_status=[0=>$tr['unpaid'],1=> $tr['paid'],2=>$tr['edited']];
// render view
$function_title 		= $tr['Member Betting Record Inquiry'];
// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];
$member_overview_mode = isset($_GET['m']);

if($member_overview_mode) {
  $page = 'member_betlog';
  $template_laout='member_overview_history.tmpl';
}else{ 
  $page = '';
  $template_laout='s2col.tmpl';
}
return render(
  __DIR__ . '/member_betlog.view.php',
  compact(
    'page',
    'member_overview_mode',
    'template_laout',
    'function_title',
    'query_sql',
    'sdate_query',
    'edate_query',
    'menu_casinolist_items',
    'account_query',
    'menu_bet_status',
    'menu_bonus_cate_item'
  )
);