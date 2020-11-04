<?php
// ----------------------------------------------------------------------------
// Features:	後台--遊戲管理列表
// File Name:   game_management_action.php
// Author:      Barkley
// Related:     game_management.php
// Log:
// 2019.06.17 區分權限顯示管理介面 Letter
// 2020.02.26 #3540 【後台】娛樂城、遊戲多語系欄位實作 - 修改娛樂城顯示名稱
// 2020.05.13 Bug #3907 VIP後台娛樂城管理 遊戲混在不對的娛樂城分類裏，前台分類應該是正確。(前後台不一致) 、查詢錯誤 Letter
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// betlog 專用的 DB lib
require_once dirname(__FILE__) ."/config_betlog.php";
// cdn上傳
require_once dirname(__FILE__) ."/lib_cdnupload.php";
// 娛樂城函式庫
require_once dirname(__FILE__) . "/casino_switch_process_lib.php";
// 遊戲函式庫
require_once dirname(__FILE__) . "/game_management_lib.php";
require_once dirname(__FILE__) . "/gapi_gamelist_management_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

$debug = 0;
global $su;
global $tr;
global $page_config;
global $config;
// 娛樂城函式庫
$casinoLib = new casino_switch_process_lib();
// 遊戲函式庫
$gameLib = new game_management_lib();
// GAPI遊戲函式庫
$gapiLib = new gapi_gamelist_management_lib();

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------
if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的测试');
}

if(isset($_GET['cid']) AND $_GET['cid'] != 'all' AND filter_var($_GET['cid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)) {
  $cid_chk = runSQL('SELECT id FROM casino_list WHERE casinoid=\''.filter_var($_GET['cid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH).'\';');
  if($cid_chk == 1){
    $cid = filter_var($_GET['cid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  }else{
      die('(x)不合法的测试');
  }
}

// ----------- datatables -------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
  $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
  $current_per_size = $page_config['datatables_pagelength'];
  //$current_per_size = 10;
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if(isset($_GET['start']) AND $_GET['start'] != NULL ) {
  $current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
}else{
  $current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if(isset($_GET['_'])){
  $secho = $_GET['_'];
}else{
  $secho = '1';
}
// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------

// ----------------------------------
// 本程式使用的 function
// ----------------------------------

// -------------------------------------------------------------------------
// $_GET 取得日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}


	/**
	 * 取得最新提醒
	 *
	 * @param $notifyTime
	 *
	 * @return int
	 * @throws Exception
	 */
	function getNewAlert($notifyTime)
	{
		$now = new DateTime();
		if (is_null($notifyTime)) {
			return -1;
		}
		$nt = new DateTime($notifyTime);
		$result = -1;
		if ($nt >= $now) {
			$result = 1;
		} elseif ($nt < $now) {
			$result = 0;
		}
		return $result;
	}
// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------


if($action == 'reload_gamelist' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

  // -----------------------------------------------------------------------
  // 列出所有的遊戲 SQL
  // -----------------------------------------------------------------------
  // 設定基本查詢條件
  // $gameslist_sql_tmp = "SELECT * FROM casino_gameslist";

  // --------------------------
  // 2019/12/13
  $gameslist_sql_tmp = "SELECT *  ,to_char((notify_datetime at time zone 'AST'),'YYYY-MM-DD HH24:MI:SS') as notify_datetime_char
  FROM casino_gameslist";
  // ---------------------------

  if(isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])
      AND (isset($_GET['deprecated']) AND filter_var($_GET['deprecated'], FILTER_SANITIZE_STRING) == 'show')) {
    $sub_sql = ' WHERE open <= \'2\'';
  } else {
    $sub_sql = ' WHERE open <= \'1\'';
  }

  // 處理選擇娛樂城
  if(isset($cid)) {
    $sub_sql .= ' AND casino_id = \''.$cid.'\'';
  } else {
    $cids = $casinoLib->getOpenCasinoIds($debug);
    if (count($cids) > 0) {
      $sub_sql .= ' AND casino_id IN (';

      for ($i = 0; $i < count($cids); $i++) {
        if ($i == count($cids) - 1) {
          $sub_sql .= '\''. $cids[$i] .'\') ';
        } else {
          $sub_sql .= '\''. $cids[$i] .'\', ';
        }
      }
    }
  }

  // 處理 datatables 傳來的search需求
  if(isset($_GET['search']['value']) AND $_GET['search']['value'] != ''){
    $sub_sql .= <<< SQL
     AND ( display_name->>'en-us' ILIKE '%{$gapiLib->translateSpecificChar("'", $_GET['search']['value'], 0)}%' OR
        display_name->>'zh-cn' ILIKE '%{$_GET['search']['value']}%' OR
        display_name->>'{$_SESSION['lang']}' ILIKE '%{$gapiLib->translateSpecificChar("'", $_GET['search']['value'], 0)}%'
     )
SQL;
  }

  if (isset($_GET['notify']) AND $_GET['notify'] == 'new') {
    $sub_sql .= ' AND "notify_datetime" > now() AND ("open" = 1)';
  }

  $gameslist_sql_tmp = $gameslist_sql_tmp.$sub_sql;

  // 計算遊戲數量
  $gameslist_sql = $gameslist_sql_tmp.';';
  $gameslist_count = runSQL($gameslist_sql);

  // -----------------------------------------------------------------------
  // 分頁處理機制
  // -----------------------------------------------------------------------
  // 所有紀錄數量
  $page['all_records']     = $gameslist_count;
  // 每頁顯示多少
  $page['per_size']        = $current_per_size;
  // 目前所在頁數
  $page['no']              = $current_page_no;

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    // 取得遞增遞減
    if($_GET['order'][0]['dir'] == 'asc'){ $sql_order_dir = 'ASC';
    }else{ $sql_order_dir = 'DESC';}

    // 取得權限
    $ops = (isset($_SESSION['agent']) and in_array($_SESSION['agent']->account, $su['ops']));
    $master = (isset($_SESSION['agent']) and in_array($_SESSION['agent']->account, $su['master']));
    $cs = false;
    if (!$ops and !$master) {
      $cs = true;
    }

    // 取得排序欄位
    if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY display_name->> \'zh-cn\' '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){ $sql_order = 'ORDER BY display_name->> \'en-us\' '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY display_name->> \''. $_SESSION['lang'] .'\' '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY casino_id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY category '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY gameplatform '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 7){
      if ($cs) {
        $sql_order = 'ORDER BY marketing_strategy->> \'hotgame\' '.$sql_order_dir;
      } else {
        $sql_order = 'ORDER BY custom_order '.$sql_order_dir;
      }
    }elseif($_GET['order'][0]['column'] == 8){
      if ($cs) {
        $sql_order = 'ORDER BY open '.$sql_order_dir;
      } else {
        $sql_order = 'ORDER BY notify_datetime '.$sql_order_dir;
      }
    }elseif($_GET['order'][0]['column'] == 9 and !$cs){ $sql_order = 'ORDER BY marketing_strategy->> \'hotgame\' '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 10 and !$cs){ $sql_order = 'ORDER BY open '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 11 and $ops){ $sql_order = 'ORDER BY open '.$sql_order_dir;
    }else{$sql_order = 'ORDER BY custom_order ASC';}

  }else{ $sql_order = 'ORDER BY custom_order ASC';}

//  if($_GET['order'][0]['column'] != 5) $sql_order = $sql_order.',gamename ASC';
  // 取出 casino_gameslist 資料
  $gameslist_sql   = $gameslist_sql_tmp." ".$sql_order." OFFSET ".$page['no']." LIMIT ".$page['per_size']." ;";

  $gameslist = runSQLall($gameslist_sql,$debug);

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 casino_gameslist count 數量大於 1
  if ($gameslist[0] >= 1) {
    for ($i = 1 ; $i <= $gameslist[0]; $i++) {
      // 取得娛樂城
      $casino = $casinoLib->getCasinoByCasinoId($gameslist[$i]->casino_id, $debug);
      // 行銷想關設定
      $ms = json_decode($gameslist[$i]->marketing_strategy,'true');

      // cdn 上的預設 gameicon
      if(strtolower($gameslist[$i]->casino_id) == 'mg' && strtolower($gameslist[$i]->gameplatform) == 'html5') {
        $gameicon_orign = $config['cdn_login']['url'].$config['cdn_login']['base_path'].'uic/gamesicon/'.strtolower($gameslist[$i]->casino_id).strtolower($gameslist[$i]->gameplatform).'/'.$gameslist[$i]->imagefilename.'.png';
      } else {
        $gameicon_orign = $config['cdn_login']['url'].$config['cdn_login']['base_path'].'uic/gamesicon/'.strtolower($gameslist[$i]->casino_id).'/'.$gameslist[$i]->imagefilename.'.png';
      }

      // 取得顯示資料
  	  $b['id'] = $gameslist[$i]->id;
      $b['casino_id'] = $gameslist[$i]->casino_id;
      $b['favorable_type'] = $gameslist[$i]->favorable_type; // 反水
      // 英文名
      $enName = $gapiLib->translateSpecificChar("'", $gameLib->getDisplayNameByLanguage($gameslist[$i]->id, 'en-us',
          $debug), 1);
      $b['gamename'] = $enName;
      $b['gamename_fix'] = $gameslist[$i]->gamename;
      // 簡中名
      $b['gamename_cn'] = $gameLib->getDisplayNameByLanguage($gameslist[$i]->id, 'zh-cn', $debug);
      $b['gamename_cn_fix'] = $gameslist[$i]->gamename_cn;
      // 顯示名稱
      $showName = $gapiLib->translateSpecificChar("'", $gameLib->getDisplayNameByLanguage($gameslist[$i]->id, $_SESSION['lang'], $debug), 1);
      $b['game_display_name'] = $showName;
      $b['gameid'] = $gameslist[$i]->gameid;
      $b['gameplatform'] = $gameslist[$i]->gameplatform;
      $b['custom_order'] = $gameslist[$i]->custom_order;
      $b['open'] = $gameslist[$i]->open;
      $b['gameicon'] = ($ms['image'])? $ms['image']: $gameicon_orign;
      $b['casino_short_name'] = $casinoLib->getCasinoDefaultName($gameslist[$i]->casino_id, $debug);
      $b['notify_datetime'] = $gameslist[$i]->notify_datetime_char; // 原版
      $b['casino_display_name'] = $casino->getDisplayName();

      if(!is_null($gameslist[$i]->category)){
        if(isset($tr[$gameslist[$i]->category])){
      	  $b['category']        = $tr[$gameslist[$i]->category];
        }else{
          $b['category']         = $gameslist[$i]->category;
        }
      }else{
        $b['category']         = '';
      }
      if(isset($tr[$gameslist[$i]->sub_category])){
        $b['sub_category']        = $tr[$gameslist[$i]->sub_category];
      }else{
        $b['sub_category']       = $gameslist[$i]->sub_category;
      }
      $hotgame_tag = '';
      if($ms['hotgame'] == '1'){
        $hotgame_tag = 'checked';
      }
      $open_tag = '';
      if($b['open'] == '1'){
        $open_tag = 'checked';
      }

      // 顯示的表格資料內容
      $show_listrow_array[] = array(
        'id'=>$b['id'],
        'casinoid'=>$b['casino_id'],
        'favorable'=>$b['favorable_type'],
        'mct_tag'=>$gapiLib->getMCTCategoryName($ms['mct']),
        'category'=>$b['category'],
        'category2nd'=>$ms['category_2nd'],
        'sub_category'=>$b['sub_category'],
        'gamename'=>$b['gamename'],
        'gamename_cn'=>$b['gamename_cn'],
        'gamename_fix'=>$b['gamename_fix'],
        'gamename_cn_fix'=>$b['gamename_cn_fix'],
        'game_display_name'=>$b['game_display_name'],
        'gameid'=>$b['gameid'],
        'gameplatform'=>$b['gameplatform'],
        'hotgame_tag'=>$hotgame_tag,
        'marketing_tag'=>$ms['marketing_tag'],
        'custom_order'=>$b['custom_order'],
        'open_tag'=>$open_tag,
        'open'=>$b['open'],
        'gameicon'=>$b['gameicon'],
	    'notify_datetime' => $b['notify_datetime'],
	    'casino_short_name' => $b['casino_short_name'],
        'notify' => getNewAlert($b['notify_datetime']),
        'casino_name' => $b['casino_display_name']
      );
    }

	// 轉換日期格式
	for ($i = 0; $i < count($show_listrow_array); $i++) {
	  $ndTimestamp = $show_listrow_array[$i]['notify_datetime'];
	  if (!is_null($ndTimestamp)) {
		  $nd = new DateTime($ndTimestamp);
		  $nd = $nd->format('Y-m-d H:i');
	  } else {
		  $nd = '';
	  }
		$show_listrow_array[$i]['notify_datetime'] = $nd;
	}

    $output = array(
      "sEcho" => intval($secho),
      "iTotalRecords" => intval($page['per_size']),
      "iTotalDisplayRecords" => intval($gameslist_count),
      "data" => $show_listrow_array
    );

    // --------------------------------------------------------------------
    // 表格資料 row list , end for loop
    // --------------------------------------------------------------------
  }else{
    // NO member
    $output = array(
      "sEcho" => 0,
      "iTotalRecords" => 0,
      "iTotalDisplayRecords" => 0,
      "data" => ''
    );
  }

  echo json_encode($output);
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------
} elseif ($action == 'edit_status' AND isset($_POST['id']) AND isset($_POST['is_open']) AND isset($_SESSION['agent'])
    AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']) ) {

  $id = filter_var($_POST['id'],FILTER_VALIDATE_INT);
  $open = filter_var($_POST['is_open'],FILTER_VALIDATE_INT);
  $chk_result = runSQLall('SELECT open FROM casino_gameslist WHERE id = \''.$id.'\';');

  if($chk_result[0] == 1){
    if($open <= 2 AND $open >= 0){
      if(($open <= 1 AND $chk_result[1]->open <= 1 AND in_array($_SESSION['agent']->account, $su['master'])) OR ($open <= 2 AND in_array($_SESSION['agent']->account, $su['ops']))){
        $sql = 'UPDATE casino_gameslist SET open = \''.$open.'\' WHERE id = \''.$id.'\';';
        //echo $sql;
        $return = runSQL($sql);
      }else{
        $return['logger'] = '(x)不合法的测试';
      }
    }else{
      $return['logger'] = '(x)不合法的测试';
    }
  }else{
    $return['logger'] = '(x)不合法的测试';
  }

  echo json_encode($return);
}elseif($action == 'edit_hotgame' AND isset($_POST['id']) AND isset($_POST['is_open']) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']) ) {
  $id = filter_var($_POST['id'],FILTER_VALIDATE_INT);
  $open = filter_var($_POST['is_open'],FILTER_VALIDATE_INT);
  $chk_sql = 'SELECT marketing_strategy FROM casino_gameslist WHERE id = \''.$id.'\';';
  $chk_result = runSQLall($chk_sql);

  if($chk_result[0] == 1){
    $sql = 'UPDATE casino_gameslist SET marketing_strategy=marketing_strategy || \'{"hotgame" : "'.$open.'"}\' WHERE id = \''.$id.'\';';
    // echo $sql;
    $return = runSQL($sql);
  }else{
    $return['logger'] = '(x)不合法的测试';
  }

  echo json_encode($return);
}elseif($action == 'edit_gamelist' AND isset($_GET['gid']) AND filter_var($_GET['gid'], FILTER_VALIDATE_INT) AND isset($_POST) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['ops']) ) {
  $game['id'] = filter_var($_GET['gid'], FILTER_VALIDATE_INT);
  $game['order'] = filter_var($_POST['gorder'], FILTER_VALIDATE_INT);
  if(isset($_POST['gename'])) $game['ename'] = urldecode(filter_var($_POST['gename'], FILTER_SANITIZE_ENCODED));
  if(isset($_POST['gcname'])) $game['cname'] = urldecode(filter_var($_POST['gcname'], FILTER_SANITIZE_ENCODED));
//  if(isset($_POST['gcname'])) $game['cname'] = preg_replace('/([^A-Za-z0-9\p{Han}])/ui', '',urldecode($_POST['gcname']));
  if(isset($_POST['cate2'])) $game['cate2'] = filter_var($_POST['cate2'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  if(isset($_POST['gmtag'])) $game['mtag'] = filter_var($_POST['gmtag'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  $game['ishot'] = filter_var($_POST['ishot'], FILTER_VALIDATE_INT);
  $game['isopen'] = filter_var($_POST['isopen'], FILTER_VALIDATE_INT);
  $game['notify_datetime'] = filter_var($_POST['notify'], FILTER_SANITIZE_STRING);
  if (empty($game['notify_datetime'])) {
    $game['notify_datetime'] = 'NULL';
  } else {
    $game['notify_datetime'] = '\''. $game['notify_datetime'] .'\'';
  }
  $game['favorable'] = filter_var($_POST['favorable'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  $game['i18n'] = filter_var($_POST['i18n'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  $game['display'] = urldecode(filter_var($_POST['display'], FILTER_SANITIZE_ENCODED));

  $game_chk = runSQLall('SELECT marketing_strategy FROM casino_gameslist WHERE id=\''.$game['id'].'\';');

  if($game_chk[0] == 1 AND $game['ishot'] <= 1 AND $game['isopen'] <= 2){
    $gamechk = json_decode($game_chk[1]->marketing_strategy,'true');
    $marketing_data = array();

    //CDN上傳
    if(isset($_FILES['gameicon'])){
      $cdn = new CDNConnection($_FILES['gameicon']);
      //檔案類型確認
      if ($cdn->CheckFile(array('jpg', 'png', 'bmp')) != true) {
        $return['logger'] = $tr['upload failed Format error']."jpg,png,bmp";
        echo json_encode($return);
        die();
      }
      //上傳檔案
      $img_upload_res = $cdn->UploatFile('upload/gameicon/');
      if ($img_upload_res['res'] != 1) {
          $return['logger'] = $tr['upload failed'];
          echo json_encode($return);
          die();
      }
      $del_res = DeleteCDNFile('upload/gameicon/',$gamechk['image']);
    }

    if(isset($img_upload_res['file'])){
      $marketing_data[] = '"image" : "' .$img_upload_res['url'].'"';
    }

    //圖片URL方式
    if(isset($_POST['gameicon']) && $_POST['gameicon']!='undefined'){
      if (preg_match('~'.$config['cdn_login']['url'].'~', $_POST['gameicon'] )) {
          $return['logger'] = $tr['enter image url'].$tr['Data is error'];
          echo json_encode($return);
          die();
      }
      $chkimg = filter_var($_POST['gameicon'], FILTER_VALIDATE_URL);
      if($chkimg==false){
        $return['logger'] = $tr['enter image url'].$tr['Data is error'];
        echo json_encode($return);
        die();
      }
      $marketing_data[] = '"image" : "'.$chkimg.'"';
      $del_res = DeleteCDNFile('upload/gameicon/',$gamechk['image']);
    }
    
    //重置遊戲icon
    if(isset($_POST['cdnact']) && $_POST['cdnact'] == 'clear'){
      $marketing_data[] = '"image" : ""';
      $del_res = DeleteCDNFile('upload/gameicon/',$gamechk['image']);
    }
    //圖片處理結束

    if($gamechk['hotgame'] != $game['ishot']){
      $marketing_data[] = '"hotgame" : "'.$game['ishot'].'"';
    }
    if($game['cate2'] != '' AND $gamechk['category_2nd'] != $game['cate2']){
      $marketing_data[] = '"category_2nd" : "'.$game['cate2'].'"';
    }
    if($game['mtag'] != '' AND $gamechk['marketing_tag'] != $game['mtag']){
      $marketing_data[] = '"marketing_tag" : "'.$game['mtag'].'"';
    }

    $update_sql = (count($marketing_data) > 0)? 'marketing_strategy=marketing_strategy || \'{'.implode(',',$marketing_data).'}\',' : '';

    $enName = $gapiLib->translateSpecificChar("'", $game['ename'], 0);
    $gameLib->updateGameNameByLanguage($game['id'], 'en-us', $enName) == -1 ? $return['result'] = -1 : $return['result'] = 0;
    $cnNAme = $gapiLib->translateSpecificChar("'", $game['cname'], 0);
    if ($return['result'] != -1) {
      $gameLib->updateGameNameByLanguage($game['id'], 'zh-cn', $cnNAme) == -1 ? $return['result'] = -1 : $return['result'] = 0;
    }
    $showName = $gapiLib->translateSpecificChar("'", $game['display'], 0);
    if ($return['result'] != -1) {
      $gameLib->updateGameNameByLanguage($game['id'],  $game['i18n'], $showName) == -1 ? $return['result'] = -1 : $return['result'] = 0;
    }

    if ($return['result'] != -1) {
      $sql = <<<SQL
    UPDATE casino_gameslist SET {$update_sql}
                                custom_order ='{$game['order']}',
                                open ='{$game['isopen']}',
                                notify_datetime = {$game['notify_datetime']},
                                favorable_type = '{$game['favorable']}'
                            WHERE id='{$game['id']}';
SQL;
      $return['result'] = runSQL($sql);
    }

  }else{
    $return['logger'] = $tr['Illegal test'];
  }

  echo json_encode($return);
} elseif ($action == 'gameorderchg' AND isset($_GET['gid']) AND isset($_POST['gorder']) ) {
// ----------------------------------------------------------------------------
// 修改遊戲列表的排序
// ----------------------------------------------------------------------------
  $game_id = filter_var($_GET['gid'], FILTER_VALIDATE_INT);
  $game_order = filter_var($_POST['gorder'], FILTER_VALIDATE_INT);
  $game_chk = runSQLall('SELECT id FROM casino_gameslist WHERE id=\''.$game_id.'\';');
  $sql = 'UPDATE casino_gameslist SET custom_order=\''.$game_order.'\' WHERE id=\''.$game_id.'\';';

  if($game_chk[0] == 1) $return['result'] = runSQL($sql, $debug);
  else $return['logger'] = $tr['Illegal test'];

  echo json_encode($return);
} elseif ($action == 'sort' AND isset($_GET['id']) AND isset($_GET['order'])) {
  $game_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
  $order = filter_var($_POST['order'], FILTER_VALIDATE_INT);

  $sql = 'UPDATE casino_gameslist SET "custom_order" = \''. $order .'\' WHERE "id" = \''. $game_id .'\';';
  $result = runSQL($sql, $debug);
  if ($result > 0) {
    echo json_encode($result);
  } else {
    echo json_encode($tr['Illegal test']);
  }
} elseif ($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
} elseif ($action = 'notify' AND isset($_GET['gid']) AND filter_var($_GET['gid'], FILTER_VALIDATE_INT) AND
    isset($_GET['datetime'])) {
  $game_id = filter_var($_GET['gid'], FILTER_VALIDATE_INT);
  $datetime = filter_var($_GET['datetime'], FILTER_SANITIZE_STRING);
  if ($datetime == '') {
    $sql = 'UPDATE casino_gameslist SET notify_datetime = NULL WHERE "id" = \''. $game_id .'\';';
  } else {
    $sql = 'UPDATE casino_gameslist SET notify_datetime = \''. $datetime .'\' WHERE "id" = \''. $game_id .'\';';
  }

  $result = runSQL($sql, $debug);

  if ($result > 0) {
    echo json_encode($result);
  } else {
    echo json_encode($tr['Illegal test']);
  }

} elseif ($action = 'switch' AND isset($_GET['gid']) AND isset($_GET['open'])) {
  $game_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
  $open = filter_var($_POST['is_open'], FILTER_VALIDATE_INT);

  $sql = 'UPDATE casino_gameslist SET "open" = '. $open .' WHERE "id" = '. $game_id;
  $result = runSQL($sql, $debug);

  if ($result > 0) {
    echo json_encode($result);
  } else {
    echo json_encode($tr['Illegal test']);
  }

} elseif (isset($_GET['a']) AND $_GET['a'] == 'recheck') { // 永久關閉再確認
  $password = $_SESSION['agent']->passwd;
  $checkPassword = filter_var($_POST['pw'], FILTER_SANITIZE_STRING);
  if (sha1($checkPassword) == $password) {
    echo json_encode(array('result' => 1));
  } else {
    echo json_encode(array('result' => 0));
  }
} elseif (isset($_GET['a']) AND $_GET['a'] == 'i18nGameNames') { // 取得語系顯示名稱
  $gid = filter_var($_GET['gid'], FILTER_SANITIZE_NUMBER_INT);

  // 取得遊戲所有語系名稱
  $names = $gameLib->getGameNameLanguage($gid, $debug);

  echo $gapiLib->translateSpecificChar("'", json_encode($names), 1);
} elseif (isset($_GET['a']) AND $_GET['a'] == 'updateName') { // 更新語系名稱
  $gid = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
  $i18n = filter_var($_POST['i18n'], FILTER_SANITIZE_STRING);
  $name = '';
  if (isset($_POST['name'])) {
    $name = $gapiLib->translateSpecificChar("'", urldecode(filter_var($_POST['name'], FILTER_SANITIZE_ENCODED)), 0);
  }

  echo json_encode(
      array(
          'result' => $gameLib->updateGameNameByLanguage($gid, $i18n, $name, $debug),
          'gid' => $gid
          )
  );
} else {
  // NO member
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => ''
  );
  echo json_encode($output);
}



?>
