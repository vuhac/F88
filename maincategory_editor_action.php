<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 遊戲分類偏輯
// File Name:	maincategory_editor_action.php
// Author:		Barkley
// Related:   bonus_commission_profit.php
// DB table:  root_statisticsbonusagent  放射線組織獎金計算-代理加盟金
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------
// var_dump($_SESSION);
// var_dump($_POST);
// var_dump($_GET);
$debug = 0;

if(!isset($_SESSION['agent']) OR !in_array($_SESSION['agent']->account, $su['ops'])){
  http_response_code(404);
  die('(x)不合法的測試');
}

if(isset($_GET['a'])) {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
  http_response_code(404);
  die('(x)不合法的測試');
}
// 主選單狀態
if(isset($_GET['mctstate']) AND $_GET['mctstate'] <= 1){
  $mctstate = filter_var($_GET['mctstate'],FILTER_VALIDATE_INT);
}
// 主選單排序
if(isset($_GET['mctorder']) AND filter_var($_GET['mctorder'],FILTER_VALIDATE_INT)){
  $mctorder = filter_var($_GET['mctorder'],FILTER_VALIDATE_INT);
}
// 主選單ID
if(isset($_GET['mctid']) AND filter_var($_GET['mctid'], FILTER_SANITIZE_STRING) AND isset($gamelobby_setting['main_category_info'][$_GET['mctid']])){
  $mctid = filter_var($_GET['mctid'], FILTER_SANITIZE_STRING);
}
// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------
 // var_dump($mctstate);
 // var_dump($mctorder);
 // var_dump($mctid);
 // var_dump($gamelobby_setting['main_category_info']);
 // var_dump(filter_var($_GET['mctstate'],FILTER_VALIDATE_INT));

// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------
if($action == 'mct_switch' AND isset($mctid) AND isset($mctstate) ){

  $gamelobby_setting['main_category_info'][$mctid]['open'] = $mctstate;

  $gamelobby_setting_json = json_encode($gamelobby_setting['main_category_info']);
  $gamelobby_setting_chk = runSQL("SELECT id FROM root_protalsetting WHERE name='main_category_info';",'r');
  // var_dump($gamelobby_setting_chk);
  if($gamelobby_setting_chk == 1){
    $gamelobby_setting_sql = runSQL("UPDATE root_protalsetting SET value='$gamelobby_setting_json' WHERE name='main_category_info';",'w');
  }else{
    $gamelobby_setting_sql = runSQL("INSERT INTO root_protalsetting(setttingname,name,value,status,description) VALUES ('default','main_category_info','{$gamelobby_setting_json}','1','遊戲主要大分類設定');",'w');
  }

  // 強制更新前後台memcache資料
  $update_result = memcache_forceupdate();
  echo json_encode($update_result);
  // var_dump($gamelobby_setting_sql);
}elseif($action == 'mctorder_switch' AND isset($mctid) AND isset($mctorder) ){

  // 設定其他分類的order
  if($gamelobby_setting['main_category_info'][$mctid]['order'] > $mctorder){
    foreach($gamelobby_setting['main_category_info'] as $mct_id => $mct_arr){
    	if($mctid != $mct_id AND $mct_arr['order'] >= $mctorder AND $mct_arr['order'] <= $gamelobby_setting['main_category_info'][$mctid]['order']){
        $gamelobby_setting['main_category_info'][$mct_id]['order'] = $mct_arr['order'] + 1;
        // echo '1';
        // var_dump($gamelobby_setting['main_category_info'][$mct_id]);
    	}
    }
  }elseif($gamelobby_setting['main_category_info'][$mctid]['order'] < $mctorder){
    foreach($gamelobby_setting['main_category_info'] as $mct_id => $mct_arr){
    	if($mctid != $mct_id AND $mct_arr['order'] >= $gamelobby_setting['main_category_info'][$mctid]['order'] AND $mct_arr['order'] <= $mctorder){
        $gamelobby_setting['main_category_info'][$mct_id]['order'] = $mct_arr['order'] - 1;
        // echo '2';
        // var_dump($gamelobby_setting['main_category_info'][$mct_id]);
    	}
    }
  }else{
    quit();
  }
  // 設定所選分類的order
  $gamelobby_setting['main_category_info'][$mctid]['order'] = $mctorder;

  $gamelobby_setting_json = json_encode($gamelobby_setting['main_category_info']);
  $gamelobby_setting_chk = runSQL("SELECT id FROM root_protalsetting WHERE name='main_category_info';",'r');
  // var_dump($gamelobby_setting_chk);
  if($gamelobby_setting_chk == 1){
    $gamelobby_setting_sql = runSQL("UPDATE root_protalsetting SET value='$gamelobby_setting_json' WHERE name='main_category_info';",'w');
  }else{
    $gamelobby_setting_sql = runSQL("INSERT INTO root_protalsetting(setttingname,name,value,status,description) VALUES ('default','main_category_info','{$gamelobby_setting_json}','1','遊戲主要大分類設定');",'w');
  }

  // 強制更新前後台memcache資料
  $update_result = memcache_forceupdate();
  echo json_encode($update_result);
  // var_dump($gamelobby_setting_sql);
}else{
  // http_response_code(404);
  // die('(x)不合法的測試');
}


// 刷新memcache資料
$key_alive_pro = sha1('protalsetting_list');
$memcached_timeout = 60;
$protalsetting['main_category_info'] = json_encode($gamelobby_setting['main_category_info']);
$protalsetting = setandget_memcache($key_alive_pro, $protalsetting,$memcached_timeout);
