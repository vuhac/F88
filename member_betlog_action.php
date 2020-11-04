<?php
// ----------------------------------------------------------------------------
// Features:	後台--投注記錄查詢
// File Name:	member_betlog_action.php
// Author:		snowiant@gmail.com
// Related:
//    member_betlog.php member_betlog_lib.php
//    DB table: root_memberlog
//    member_betlog_action：有收到 member_betlog.php 透過ajax 傳來的  _GET 時會將 _GET
//        取得的值進行驗證，並檢查是否為可查詢對象，如果是就直接丟入 $query_sql_array 中再
//        引用 member_betlog_lib.php 中的涵式 show_member_betloginfo() 並將返回
//        的資料放入 table 中給 datatable 處理，再以 ajax 丟給 member_betlog.php 來顯示。
// Log:
// 2020.03.04 Bug #3623 VIP后台>系统管理>佣金设定>新增反水设定;Notice: Undefined index: sports； Letter
//            娱乐城读取ID名称需与娱乐城改名后整合
// 2020.07.30 Bug #4342 VIP站後台，投注紀錄查詢 > 進階搜尋 > 遊戲名稱搜尋結果失敗 Letter
//            修改搜尋遊戲名稱 SQL
// 2020.08.07 Bug #4414 VIP站後台，投注紀錄查詢 > [娛樂城排序]按鈕 > 跳錯誤訊息 Letter
//            增加判斷排序欄位名稱邏輯
// ----------------------------------------------------------------------------

session_start();

// 載入預設lib檔
require_once dirname(__FILE__) . "/member_betlog_lib.php";
require_once dirname(__FILE__) . "/lib_file.php";
require_once dirname(__FILE__) . "/casino_switch_process_lib.php";
require_once dirname(__FILE__) . "/gapi_gamelist_management_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 會員帳號對應遊戲帳號
function get_member_account_map(){
  $query_sql =<<<SQL
    SELECT
      root_member.id,
      account,
      casino_accounts
    FROM root_member
    JOIN root_member_wallets ON root_member.id = root_member_wallets.id
SQL;

  $query_result = runSQLall($query_sql, 0, 'r');

  unset($query_result[0]);

  $member_account_map = [];

  foreach ($query_result as $row) {
    $casino_info = json_decode($row->casino_accounts,'true');
    if(count($casino_info) >= 1){
      $caccount = array();
      foreach($casino_info as $cid => $cinfo){
        $caccount[] = $cinfo['account'];
      }
      foreach(array_unique($caccount) as $game_account) {
        if(empty($game_account)) continue;
        $member_account_map[$game_account] = [
          'id' => $row->id,
          'account' => $row->account,
        ];
      }
    }
  }

  return $member_account_map;
}


// 產生查詢條件
function query_str($query_sql_array){
  global $tr;
  $query_top = 0;
  $show_member_log_sql = '';
  //檢查query的值
  if(isset($query_sql_array['query_date_start_datepicker']) AND $query_sql_array['query_date_start_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"receivetime" >= \''.$query_sql_array['query_date_start_datepicker'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['query_date_end_datepicker']) AND $query_sql_array['query_date_end_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"receivetime" <= \''.$query_sql_array['query_date_end_datepicker'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['query_betdate_start_datepicker']) AND $query_sql_array['query_betdate_start_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"bettime" >= \''.$query_sql_array['query_betdate_start_datepicker'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['query_betdate_end_datepicker']) AND $query_sql_array['query_betdate_end_datepicker'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"bettime" <= \''.$query_sql_array['query_betdate_end_datepicker'].'\'';
    $query_top = 1;
  }


  if(isset($query_sql_array['account_query']) AND $query_sql_array['account_query'] != NULL AND isset($query_sql_array['gameaccount_type']) ) {
    if(count($query_sql_array['gameaccount_type']) >= 1){
      foreach($query_sql_array['gameaccount_type'] as $key => $cid){
        $query_array['gameaccount_json'][$cid] = 'casino_accounts->\''.$cid.'\'->>\'account\' as '.strtolower($cid).'_account';
        $query_array['gameaccount'][$cid] = strtolower($cid).'_account';
      }

      // 依會員錢包，找出該會員之各娛樂城的遊戲帳號
      if (isset($query_sql_array['agent']) AND $query_sql_array['agent'] != NULL) {
        $query_sql = 'SELECT '.implode(",",$query_array['gameaccount_json']).' FROM root_member_wallets JOIN root_member ON root_member.id = root_member_wallets.id WHERE root_member.account = \''.$query_sql_array['account_query'].'\' AND parent_id = (SELECT id FROM root_member WHERE account = \''.$query_sql_array['agent'].'\');';
      } else {
        $query_sql = 'SELECT '.implode(",",$query_array['gameaccount_json']).' FROM root_member_wallets JOIN root_member ON root_member.id = root_member_wallets.id WHERE root_member.account = \''.$query_sql_array['account_query'].'\';';
      }

      $query_result = runSQLall($query_sql, 0, 'r');

      if(isset($query_result) AND $query_result[0] == 1){
        // $account_querry_count = 0;
        foreach($query_array['gameaccount'] as $k => $v){
          if ($query_result[1]->$v != '') {
            $game_account_array[$k]=$query_result[1]->$v;
          }
        }

        if(isset($game_account_array)){
            // 解出帳號下的所有遊戲帳號，不重複--20200121 yaoyuan
            $query_account_ary=[];
            foreach ($game_account_array as $casion_account){
                if(!in_array($casion_account,$query_account_ary)){
                    $query_account_ary[]=$casion_account;
                }
            }
        }else{
          $logger =  $tr['No account'].$tr['betting record detail'];
        }

      }else{
        $logger =  $tr['No account'].'或此帐号所属代理错误';
      }
    }
  } elseif(isset($query_sql_array['agent']) AND $query_sql_array['agent'] != NULL AND isset($query_sql_array['gameaccount_type'])) {
    if(count($query_sql_array['gameaccount_type']) >= 1){
      foreach($query_sql_array['gameaccount_type'] as $key => $cid){
        $query_array['gameaccount_json'][$cid] = 'casino_accounts->\''.$cid.'\'->>\'account\' as '.strtolower($cid).'_account';
        $query_array['gameaccount'][$cid] = strtolower($cid).'_account';
      }

      // 依會員錢包，找出該會員之各娛樂城的遊戲帳號
      $query_sql = 'SELECT '.implode(",",$query_array['gameaccount_json']).' FROM root_member_wallets JOIN root_member ON root_member.id = root_member_wallets.id WHERE root_member.account IN (SELECT account FROM root_member WHERE parent_id = (SELECT id FROM root_member WHERE account = \''.$query_sql_array['agent'].'\'));';
      $query_result = runSQLall($query_sql, 0, 'r');

      if(isset($query_result) AND !empty($query_result[0])){
        unset($query_result[0]);
        foreach ($query_result as $k => $accs) {
          foreach($query_array['gameaccount'] as $i => $n){
            if ($accs->$n != '') {
              $game_account_array[$i][] = $accs->$n;
            }
          }
        }

        if(isset($game_account_array)){
            // 解出所有代理商下的遊戲帳號--20200121 yaoyuan
            $query_account_ary=[];
            foreach ($game_account_array as $casion_account){
                foreach ($casion_account as $game_account_val){
                  if(!in_array($game_account_val,$query_account_ary)){
                    $query_account_ary[]=$game_account_val;
                  }
                }
            }
        }else{
          $logger =  $tr['No account'].$tr['betting record detail'];
        }

      } else {
        $logger = '查无此帐号下线';
      }
    }

  }

    // 判斷查詢之帳號、代理，所轉成的遊戲帳號
    if(isset($query_account_ary) AND $query_account_ary != NULL ) {
      if($query_top == 1){
        $show_member_log_sql = $show_member_log_sql.' AND ';
      }
      $acc_sql_string= ' casino_account IN (\''.implode("','",$query_account_ary).'\')';
      $show_member_log_sql = $show_member_log_sql.$acc_sql_string;
      $query_top = 1;
    }

    // 反水種類全選時，則反水分類不判斷
    if(isset($query_sql_array['casino_favorable']) AND $query_sql_array['casino_favorable'] != NULL AND $query_sql_array['sel_all_bonus']==false) {
      $sql_casino_bonus_show=[];
      if($query_top == 1){
        $show_member_log_sql = $show_member_log_sql.' AND ';
      }

      foreach ($query_sql_array['casino_favorable'] as $casino => $favorable){
          $sql_cob_fav=implode("','",$favorable);
          // 判斷遊戲分類是否全選，全選->則反水分類不判斷
          $sql_casino_bonus_show[]='casinoid=\''.$casino.'\' AND favorable_category IN (\''.$sql_cob_fav.'\')';
      }
      $sql_cob_casino_fav='(('.implode(") or (",$sql_casino_bonus_show).'))';
      $show_member_log_sql = $show_member_log_sql.$sql_cob_casino_fav;
      $query_top = 1;

    }elseif($query_sql_array['sel_all_bonus']==true){
        // 全選不用做任何sql判斷
    }else{$logger = '请选择游戏类型!';}

    // 注單號碼查詢
    if(isset($query_sql_array['betting_slip_num']) AND $query_sql_array['betting_slip_num'] != NULL) {
        if($query_top == 1){
          $show_member_log_sql = $show_member_log_sql.' AND ';
        }
        $show_member_log_sql = $show_member_log_sql.'"rowid" = \''.$query_sql_array['betting_slip_num'].'\'';
        $query_top = 1;
    }

  // 遊戲名稱
  if(isset($query_sql_array['game_name']) AND $query_sql_array['game_name'] != NULL ) {
    $gapiLib = new gapi_gamelist_management_lib();
    $gameName = $gapiLib->translateSpecificChar("'", $query_sql_array['game_name'], 0);
    // 取得遊戲英文名稱及娛樂城 ID
    $sql = 'SELECT DISTINCT "gamename", "casino_id" FROM "casino_gameslist" WHERE display_name->>\''. $_SESSION['lang'] .'\' ILIKE \'%'. $gameName .'%\';';
    $result = runSQLall($sql, 0);
    if ($result[0] > 0) {
      if($query_top == 1){
        $show_member_log_sql .= ' AND (';
      }
      for ($i = 1; $i <= $result[0]; $i++) {
        // 逃逸單引號
        $betGameName = str_replace("'", "''", $gapiLib->translateSpecificChar("'", $result[$i]->gamename, 1));
        if ($i == $result[0]) {
          $show_member_log_sql .= '("game_name" = \''. $betGameName .'\' AND "casinoid" = \''. $result[$i]->casino_id .'\'))';
        } else {
          $show_member_log_sql .= '("game_name" = \''. $betGameName .'\' AND "casinoid" = \''. $result[$i]->casino_id .'\') OR ';
        }
      }
      $query_top = 1;
    } else {
      $show_member_log_sql .= '';
    }

  }

  if(isset($query_sql_array['betamount_lower']) AND $query_sql_array['betamount_lower'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"betamount" >= \''.$query_sql_array['betamount_lower'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['betamount_upper']) AND $query_sql_array['betamount_upper'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"betamount" <= \''.$query_sql_array['betamount_upper'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['betvalid_lower']) AND $query_sql_array['betvalid_lower'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"betvalid" >= \''.$query_sql_array['betvalid_lower'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['betvalid_upper']) AND $query_sql_array['betvalid_upper'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"betvalid" <= \''.$query_sql_array['betvalid_upper'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['receive_lower']) AND $query_sql_array['receive_lower'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"betresult" >= \''.$query_sql_array['receive_lower'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['receive_upper']) AND $query_sql_array['receive_upper'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"betresult" <= \''.$query_sql_array['receive_upper'].'\'';
    $query_top = 1;
  }

  if(isset($query_sql_array['status_query']) AND $query_sql_array['status_query'] != NULL ) {
    if($query_top == 1){
      $show_member_log_sql = $show_member_log_sql.' AND ';
    }
    $show_member_log_sql = $show_member_log_sql.'"status" IN ( '.implode("," ,$query_sql_array['status_query']).')';
    $query_top = 1;
  }

  if($query_top == 1 AND !isset($logger)){
    $return_sql = ' WHERE '.$show_member_log_sql;
  }elseif(isset($logger)){
    $return_sql['logger'] = $logger;
  }else{
    $return_sql = '';
  }
  return $return_sql;
}

/**
 *  產生注單語系遊戲名稱
 *
 * @return stdClass 語系遊戲名稱
 *                  英文遊戲名稱 => 語系遊戲名稱(沒有該語系名稱用英文名稱顯示)
 */
function load_gamelist_i18n() {
  $GameNameI18 = new stdClass();
  $translatetionChn_sql = 'SELECT DISTINCT trim(lower("gamename")) enlowgamename, "gamename_cn", "casino_id", "display_name" FROM "casino_gameslist";';
  $translatetionChn_sql_result = runSQLall($translatetionChn_sql, 0, 'r');
  foreach ($translatetionChn_sql_result as $key => $value) {
    //將索引值給$key，整個英中遊戲名稱給$value
    if ($key != 0) {
      $displayNameArr = json_decode($value->display_name, true);
      $displayName = key_exists($_SESSION['lang'],$displayNameArr) ? $displayNameArr[$_SESSION['lang']] :
          $displayNameArr['en-us'];
      //對照表的遊戲名稱，以中文為主，否則英文
      $GameNameI18->{$value->enlowgamename} = $displayName;
    }
  }

  return $GameNameI18;
}

function create_sql(){
  $sql_str=<<<SQL
  SELECT
         "id",
         "rowid",
         "casino_account" AS log_account,
         "game_name" AS gamename,
         "game_category" AS gamecategory,
         "betvalid" AS totalwager,
         "betamount" AS totalamount,
         "betresult" AS totalpayout,
         "bettime" AS betime,
         to_char(("bettime" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as log_time,
         to_char(("receivetime" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') as receivelogtime,
         "casinoid",
         "favorable_category" AS favorable_category,
         "status",
         "gameid"
  FROM
SQL;
  return $sql_str;
}

function check_select_gametype_isunique($gametype)
{
  // 不做單一遊戲類型檢查，直㧡傳回true
  return true;
  $merge_arr = [];

  foreach ($gametype as $casino => $type) {
    if (count($type) > 1) {
      return false;
    }

    $merge_arr = array_merge($merge_arr, $type);
  }

  if (count(array_unique($merge_arr)) > 1) {
    return false;
  }

  return true;
}

function generate_source_db($sql_date_combination){
    global $config;
    $sql_from_str='(SELECT  * FROM '.$config['projectid'].'_'.implode(' UNION SELECT * FROM '.$config['projectid'].'_',$sql_date_combination).') AS betrecordsremix ';
    return $sql_from_str;
}

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------

global $tr;
global $page_config;
global $config;

$casinoLib = new casino_switch_process_lib();
$debug = 0;
$query_chk = 0;

$current_datepicker = gmdate('Y-m-d H:i:s',time()+8*3600);
// 2020-02-20
$default_min_date = gmdate('Y-m-d H:i:s',strtotime('-2 month') + -4*3600);

if(isset($_GET['get'])){
    $action = filter_var($_GET['get'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{//$tr['Illegal test'] = '(x)不合法的測試。';
    die($tr['Illegal test']);
}

//檢查下載csv檔時，csv帶的長編碼經過解密之後的值
if(isset($_GET['csv'])){
  $CSVquery_sql_array = get_object_vars(jwtdec('downloadbetlog',$_GET['csv']));
  //由於第有三層，所以CSVquery_sql_array['gameaccount_type']還要再解一次，才會得到陣列，否則是物件
  $csvfilename = sha1($_GET['csv']);
}

if(isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 檢查查詢條件是否有提供帳號
$account_chk = 0;

// 帳號查詢
if(isset($_GET['a']) AND $_GET['a'] != NULL  AND $_GET['a'] != '' ) {
  $query_sql_array['account_query'] = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
  $account_chk = 1;
  $account_id_sql = 'SELECT id , account FROM root_member  WHERE account=\''.$query_sql_array['account_query'].'\';';
  $account_id_sql_result = runSQLall($account_id_sql);
}

// 派彩日期
if(isset($_GET['sdate']) AND $_GET['sdate'] != NULL AND $_GET['sdate'] != ''  ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['sdate'], 'Y-m-d H:i:s') AND strtotime($_GET['sdate']) >= strtotime($default_min_date)) {
    // $query_sql_array['query_date_start_datepicker'] = gmdate('Y-m-d H:i:s',strtotime($_GET['sdate'].':00 -04')+8*3600).' +08:00';

    $query_sql_array['query_date_start_datepicker'] = gmdate('Y-m-d H:i:s',strtotime($_GET['sdate'].'-04')+8*3600).' +08:00';
    $query_chk = 1;
  }
}

if(isset($_GET['edate']) AND $_GET['edate'] != NULL AND $_GET['edate'] != '' ) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['edate'], 'Y-m-d H:i:s') AND strtotime($_GET['edate']) <= strtotime($current_datepicker)+100) {
    // $query_sql_array['query_date_end_datepicker'] = gmdate('Y-m-d H:i:s.u',strtotime($_GET['edate'].':00 -04')+8*3600).' +08:00';

    $query_sql_array['query_date_end_datepicker'] = gmdate('Y-m-d H:i:s.u',strtotime($_GET['edate'].'-04')+8*3600).' +08:00';
    $query_chk = 1;
  }
}
// -----------------------------------------

// 遊戲反水分類
if(isset($_GET['casino_favorable_qy']) AND is_array($_GET['casino_favorable_qy']) ){
  $bonus_check = filter_var_array($_GET['casino_favorable_qy'], FILTER_SANITIZE_STRING);

  foreach ($bonus_check as $casino_bonus){
    $pair=explode("_", $casino_bonus);
    $query_sql_array['casino_favorable'][$pair[0]][]=$pair[1];
  }
  $query_chk = 1;
  $query_sql_array['gameaccount_type']=array_keys($query_sql_array['casino_favorable']);
}

// 找出各娛樂城所有的反水分類，再將個數跟前台傳進來的個數比較，相同代表全選，則不要有反水分類的sql where 條件
$bonus_cat_sql = 'SELECT casinoid ,json_array_elements_text ( game_flatform_list)::text as category  FROM casino_list WHERE "open" = 1 ;';
$bonus_cat_all = runsql($bonus_cat_sql);
if(isset($bonus_check) AND count($bonus_check)==$bonus_cat_all){
  $query_sql_array['sel_all_bonus']=true;
}else{
  $query_sql_array['sel_all_bonus'] = false;
}

if (isset($_GET['agent']) AND $_GET['agent'] != NULL) {
  $query_sql_array['agent'] = filter_var($_GET['agent'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

// 投注時間
if(isset($_GET['betdate_start']) AND $_GET['betdate_start'] != NULL ) {
  if(validateDate($_GET['betdate_start'], 'Y-m-d H:i:s') AND strtotime($_GET['betdate_start']) >= strtotime($default_min_date)){
    // $query_sql_array['query_betdate_start_datepicker'] = gmdate('Y-m-d H:i:s.u',strtotime($_GET['betdate_start'].':00 -04')+8*3600).' +08:00';

    $query_sql_array['query_betdate_start_datepicker'] = gmdate('Y-m-d H:i:s',strtotime($_GET['betdate_start'].'-04')+8*3600).' +08:00';

    $query_chk = 1;
  }
}

if(isset($_GET['betdate_end']) AND $_GET['betdate_end'] != NULL ) {
  if(validateDate($_GET['betdate_end'], 'Y-m-d H:i:s') AND strtotime($_GET['betdate_end']) <= strtotime($current_datepicker)+100) {
    // $query_sql_array['query_betdate_end_datepicker'] = gmdate('Y-m-d H:i:s',strtotime($_GET['betdate_end'].':00 -04')+8*3600).' +08:00';
    $query_sql_array['query_betdate_end_datepicker'] = gmdate('Y-m-d H:i:s',strtotime($_GET['betdate_end'].'-04')+8*3600).' +08:00';

    $query_chk = 1;
  }
}

// -----------------------------------------------------------------------------

// 注單號碼
if (isset($_GET['betting_slip_num']) AND $_GET['betting_slip_num'] != NULL) {
  $query_sql_array['betting_slip_num'] = filter_var($_GET['betting_slip_num'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['inning_number']) AND $_GET['inning_number'] != NULL) {
  $query_sql_array['inning_number'] = filter_var($_GET['inning_number'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['game_name']) AND $_GET['game_name'] != NULL) {
  $query_sql_array['game_name'] = filter_var($_GET['game_name'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['betamount_lower']) AND $_GET['betamount_lower'] != NULL) {
  $query_sql_array['betamount_lower'] = filter_var($_GET['betamount_lower'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['betamount_upper']) AND $_GET['betamount_upper'] != NULL) {
  $query_sql_array['betamount_upper'] = filter_var($_GET['betamount_upper'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['betvalid_lower']) AND $_GET['betvalid_lower'] != NULL) {
  $query_sql_array['betvalid_lower'] = filter_var($_GET['betvalid_lower'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['betvalid_upper']) AND $_GET['betvalid_upper'] != NULL) {
  $query_sql_array['betvalid_upper'] = filter_var($_GET['betvalid_upper'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['receive_lower']) AND $_GET['receive_lower'] != NULL) {
  $query_sql_array['receive_lower'] = filter_var($_GET['receive_lower'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

if (isset($_GET['receive_upper']) AND $_GET['receive_upper'] != NULL) {
  $query_sql_array['receive_upper'] = filter_var($_GET['receive_upper'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}


if(isset($_GET['status_qy']) AND $_GET['status_qy'] != NULL ) {
  $query_sql_array['status_query'] = filter_var_array($_GET['status_qy'], FILTER_SANITIZE_STRING);
  $query_chk = 1;
}

// $current_datepicker = gmdate('Y-m-d H:i:s',time()+8*3600);
$current_datepicker = gmdate('Y-m-d H:i:s',time()+ -4*3600);

if(!isset($query_sql_array['query_date_start_datepicker']) AND !isset($query_sql_array['query_date_end_datepicker'])){
    if(!isset($query_sql_array['query_betdate_start_datepicker']) AND !isset($query_sql_array['query_betdate_end_datepicker'])){
      // $default_startdate = gmdate('Y-m-d H:i',strtotime('-30 day',time())+8*3600).':00';
      $default_startdate = gmdate('Y-m-d H:i',strtotime('-30 day',time())+ -4*3600).':00';


      $query_sql_array['query_betdate_start_datepicker'] = $default_startdate.' +08:00';
      $query_sql_array['query_betdate_end_datepicker'] = $current_datepicker.' +08:00';

    }elseif(!isset($query_sql_array['query_betdate_start_datepicker'])){
      $default_startdate = gmdate('Y-m-d H:i:s',strtotime('-30 day',strtotime($query_sql_array['query_betdate_end_datepicker']))+8*3600).' +08:00';
      $query_sql_array['query_betdate_start_datepicker'] = $default_startdate;

    }elseif(!isset($query_sql_array['query_betdate_end_datepicker'])){
      $query_sql_array['query_betdate_end_datepicker'] = $current_datepicker.' +08:00';
    }
}elseif(!isset($query_sql_array['query_date_start_datepicker'])){
      $default_startdate = gmdate('Y-m-d H:i:s',strtotime('-30 day',strtotime($query_sql_array['query_date_end_datepicker']))+8*3600).' +08:00';
      $query_sql_array['query_date_start_datepicker'] = $default_startdate;

}elseif(!isset($query_sql_array['query_date_end_datepicker'])){
      $query_sql_array['query_date_end_datepicker'] = $current_datepicker.' +08:00';
}

// 派彩日期
if(isset($_GET['cid']) AND isset($_GET['bid']) AND $_GET['cid'] != NULL  AND $_GET['bid'] != NULL ) {
  // 判斷格式資料是否正確
  $betdetail = [];
  $betdetail['casinoid'] = strtoupper(filter_var($_GET['cid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH));
  $betdetail['betid'] = filter_var($_GET['bid'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  $sql = 'SELECT id FROM betrecordsremix WHERE casinoid =\''.$betdetail['casinoid'] .'\' AND rowid=\''.$betdetail['betid'] .'\';';
  // var_dump($sql);die();
  $result = runSQL_betlog($sql);
  if($result == 0) {
    $output_msg = array('logger' => '查詢參數錯誤，请檢查查询參數！','url' => '');
    echo json_encode($output_msg);
    die();
  }
}

// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if(isset($_GET['length']) AND $_GET['length'] != NULL ) {
  $current_per_size = filter_var($_GET['length'],FILTER_VALIDATE_INT);
}else{
  $current_per_size = $page_config['datatables_pagelength'];
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
// datatable server process 分頁處理及驗證參數  END
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------
$bet_status=[0=>$tr['unpaid'],1=>$tr['paid'],2=>$tr['edited'],3=>$tr['Cancel']];
// 型別轉換工具 in lib_common.php
$converter = new TypeConverter;
// ----------------------------------
// 動作為會員 action
// ----------------------------------
if($action == 'query_log' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND $query_chk == 1 ) {
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

  if(isset($query_sql_array)){
    // -----------------------------------------------------------------------
    // 列出所有的會員資料及人數 SQL
    // -----------------------------------------------------------------------

    // get gane name for i18n
    $gamename_i18n = load_gamelist_i18n();

    if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
      $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
      if ($_GET['columns'][$_GET['order'][0]['column']]['data'] == 'casino') {
        $sql_order = 'ORDER BY casinoid '.$sql_order_dir;
      } else {
        $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
      }
    }else{ $sql_order = 'ORDER BY id ASC';}

    // 取得查詢條件
    $query_str = query_str($query_sql_array);
    // var_dump($query_str);die();
    // 取得查詢條件中，最大及最小日期
    $sql_datetime=compare_time($query_sql_array);
    // var_dump($sql_datetime);die();
    // 算出查詢最大到最小日期之日期區間
    $sql_date_combination=date_range($sql_datetime['min_date'],$sql_datetime['max_date']);
    // var_dump($sql_date_combination);die();

    if(isset($query_str['logger'])){
      // NO member
      $output = array(
        "sEcho" => 0,
        "iTotalRecords" => 0,
        "iTotalDisplayRecords" => 0,
        "data" => ''
      );
    }else{
      // 算資料總筆數
      $userlist_sql   = "SELECT Count(id) as record_count FROM betrecordsremix ".$query_str." ;";
      // var_dump($userlist_sql);die();
      $userlist_count = (runSQLall_betlog($userlist_sql,0))[1]->record_count;

      // -----------------------------------------------------------------------
      // 分頁處理機制
      // -----------------------------------------------------------------------
      // 所有紀錄數量
      $page['all_records']     = $userlist_count;
      // 每頁顯示多少
      $page['per_size']        = $current_per_size;
      // 目前所在頁數
      $page['no']              = $current_page_no;

      $member_account_map = get_member_account_map();

      // 取出資料
      $userlist_sql = create_sql() .' betrecordsremix '. $query_str ." ". $sql_order .' OFFSET '. $page['no'] .' LIMIT '
          .$page['per_size'].';';

      $userlist = runSQLall_betlog($userlist_sql, $debug);
      // var_dump($userlist);die();
      $show_listrow_html = '';
      // 判斷 root_member count 數量大於 1
      if($userlist[0] >= 1) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for($i = 1 ; $i <= $userlist[0]; $i++){
          $count = $page['no'] + $i;

          // 檢查查詢條件是否有提供帳號，如果沒有，則每筆資料都要查詢其會員帳號，如果有，則不需查詢
          if($account_chk == 0){
            $account_data = $member_account_map[$userlist[$i]->log_account] ?? [];
            $account_data = empty($account_data) ?'<div>不存在</div>':
            '<a href="member_account.php?a='.$account_data['id'].'" target="_BLANK" data-role=\"button\" >'.$account_data['account'].'</a>';
          }else{
            // 保留前台顯示會員遊戲帳號，以供查詢
            // 點選會員帳號，連至會員詳細資料
            $account_data = '<a href="member_account.php?a='.$account_id_sql_result[1]->id.'" target="_BLANK" data-role=\"button\" >'.$query_sql_array['account_query'].'</a>';
          }

          $gamenamekey=game_name_key_helper($userlist[$i]->casinoid,$userlist[$i]->gamename);

          // 如果注單的遊戲名稱，在中英對照表有，就顯示中英對照表的名稱(中文為主，英文為輔)，若沒有在中英，則顯示注單的遊戲名稱
          // 20200730 顯示名稱修改為語系名稱，若該語系沒有名稱，顯示英文名稱
          $gamename_chn = empty($gamename_i18n->{$gamenamekey}) ? $gamenamekey : $gamename_i18n->{$gamenamekey};

          // 假如狀態為0未派彩，2修改過注單
          if($userlist[$i]->status==0){
            $b['receive_time'] ='-';
            $b['totalpayout']    ='-';
          }else{
            $b['receive_time'] = $userlist[$i]->receivelogtime;
            $b['totalpayout']  = $userlist[$i]->totalpayout;
          }

          //遊戲類別英翻中
          $chn_gamecate= $tr[$userlist[$i]->gamecategory] ?? $userlist[$i]->gamecategory;

  $deltail_map = <<<HTML
          <button type="button" class="btn btn-info btn-xs pull-right modal-btn" data-toggle="modal" onclick="load_betdetail('{$userlist[$i]->id}','{$userlist[$i]->casinoid}','{$userlist[$i]->rowid}')">{$tr['detail']}</button>
          <div class="modal fade" id="{$userlist[$i]->id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="true">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                <div class="modal-header">
                  <h2 class="modal-title" id="myModalLabel">{$tr['betting record detail']}</h2>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>

                <div class="modal-body embed-responsive embed-responsive-1by1">
                  <iframe class="embed-responsive-item" id="modal-iframe{$userlist[$i]->id}" src="" frameborder="0" alt="loading..." ></iframe>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          </div>
HTML;

          $b['id']         = $userlist[$i]->rowid;
          $b['account']    = $account_data.'('.$userlist[$i]->log_account.')';
          $b['bettime'] = $userlist[$i]->log_time.' (EDT)';
          $b['logintime']    = $userlist[$i]->receivelogtime.$tr['about'].convert_to_fuzzy_time($userlist[$i]->receivelogtime);
          $b['gamename']      = $gamename_chn;
          $b['totalwager']    = $userlist[$i]->totalwager;
          $b['totalamount']   = $userlist[$i]->totalamount;
          $b['gamecategory']    = $chn_gamecate;
          $b['casino']    = $casinoLib->getCasinoDefaultName($userlist[$i]->casinoid);
          $b['bet_status']    = $bet_status[$userlist[$i]->status];
          $b['bet_statuscode']    = $userlist[$i]->status;
          $b['detail_trans'] = ($userlist[$i]->casinoid != 'IG') ? $deltail_map : '-';
          // 顯示的表格資料內容
          $show_listrow_array[] = array(
          'id'=>$b['id'],
          'account'=>$b['account'],
          'bettime'=>$b['bettime'],
          'logintime'=>$b['logintime'],
          'gamename'=>$b['gamename'],
          'totalwager'=>$b['totalwager'],
          'totalamount'=>$b['totalamount'],
          'totalpayout'=>$b['totalpayout'],
          'gamecategory'=>$b['gamecategory'],
          'casino'=>$b['casino'],
          'bet_status'=>$b['bet_status'],
          'bet_statuscode'=>$b['bet_statuscode'],
          'detail_trans'=> $b['detail_trans']);
        }
        $output = array(
          "sEcho" => intval($secho),
          "iTotalRecords" => intval($page['per_size']),
          "iTotalDisplayRecords" => intval($page['all_records']),
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
    }
  }else{
    // NO member
    $output = array(
      "sEcho" => 0,
      "iTotalRecords" => 0,
      "iTotalDisplayRecords" => 0,
      "data" => ''
    );
  }
  // end member sql
  echo json_encode($output);
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取 END
  // -----------------------------------------------------------------------
}elseif($action == 'query_summary' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($query_sql_array)  AND $query_chk == 1 ) {
// }elseif($action == 'query_summary' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($query_sql_array) ) {


  // 取得查詢條件
  $query_str = query_str($query_sql_array);

  //查到最初組成查詢字串
  $dl_csv_code = jwtenc('downloadbetlog', $query_sql_array);

  // 原版
  if(isset($query_str['logger'])){
    $output = array('logger' => $query_str['logger']);
    echo json_encode($output);
  }else{
    // 查询期间总投注笔数
    $userlist_sql   = 'SELECT casino_account FROM betrecordsremix '.$query_str.";";

    // $userlist_sql   = 'SELECT casino_account FROM '.$sql_from_str.$query_str.";";
    // $combination =['mg_bettingrecords','pt_bettingrecords','mega_bettingrecords','ec_bettingrecords','igsst_bettingrecords','ighkt_bettingrecords'];
    // $a = generate_source_db($combination);

    // echo $a;die();//總筆數
    $show_member_betlog_result_count = runSQL_betlog($userlist_sql,0);
    // echo $show_member_betlog_result_count;die();//總筆數
    
    // 查询期间总投注金额
    $show_member_betlog_betvalidsum_sql = 'SELECT SUM("betvalid") AS totalwager_sum,SUM("betresult") AS totalpayout_sum FROM'.' betrecordsremix '.$query_str.';';

    // 查询期间总損益结果,過濾未派彩
    $show_member_betlog_betresultsum_result = runSQLall_betlog($show_member_betlog_betvalidsum_sql,0);

    // 以下直接套用liu格式  總有效投注
    $show_member_betlog_betvalidsum=$converter->add($show_member_betlog_betresultsum_result[1]->totalwager_sum)->numberFormat()->commit();

    // 以下直接套用liu格式  總損益
    $member_betlog_accumulated=$converter->add($show_member_betlog_betresultsum_result[1]->totalpayout_sum)->numberFormat()->commit();

    $filename = "会员投注记录_".date("Y-m-d_His").'.csv';

    $json_arr = [
      'member_betlog_result_count' => $show_member_betlog_result_count,
      'member_betlog_betvalidsum'  => '$'.$show_member_betlog_betvalidsum,
      // 'member_betlog_betresultsum' => '$'.$show_member_betlog_betresultsum,
      'betlog_accumulated'         => '$'.$member_betlog_accumulated,
      'download_url'               => 'member_betlog_action.php?get=dl_csv&csv='.$dl_csv_code,
      'csv_filename'               => $filename
    ];
    echo json_encode($json_arr);

  }

}elseif($action == 'dl_csv' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND isset($CSVquery_sql_array)){
  $query_str = query_str($CSVquery_sql_array);

  if(isset($query_str['logger'])){
    $output = array('logger' => $query_str['logger']);
    echo json_encode($output);
    die();
  }

  $betrecords_sql   = create_sql().' betrecordsremix '.$query_str." ORDER BY receivetime DESC ";
  $betrecords_paginator = new Paginator($betrecords_sql, 5000, function($sql) {
    $sqlResult = runSQLall_betlog($sql);
    unset($sqlResult[0]);
    return $sqlResult;
  });

  if($betrecords_paginator->total < 1) {
    echo '(405) No Data!!';
    die();
  }

  // get game name for i18n
  $gamename_i18n = load_gamelist_i18n();
  $member_account_map = get_member_account_map();
  $csv_key_title = [
    $tr['bet number'],
    $tr['Betting time'].$tr['EDT(GMT -5)'],
    $tr['Account'],
    $tr['Game account'],
    $tr['game name'],
    $tr['Game category'],
    $tr['Effective bet amount'],
    $tr['bet amount'],
    $tr['profit and loss'],
    $tr['Bonus category_1'],
    $tr['payment time'],
    $tr['Casino'].$tr['ID'],
    $tr['State'],
  ];

  // $csv_key_title = [
  //   '注单号码',
  //   '投注时间(美东时间)',
  //   '会员帐号',
  //   '游戏帐号',
  //   '游戏名称',
  //   '游戏分类',
  //   '有效投注金额',
  //   '派彩',
  //   '反水分类',
  //   '派彩时间',
  //   '娱乐城ID',
  //   '状态',
  // ];
  // -------------------------------------------
  // 將內容輸出到 檔案 , csv format
  // -------------------------------------------
  $filename = "member_betlog_" . date("Y-m-d_His") . '.csv';
  $file_path = dirname(__FILE__) . '/tmp_dl/' .$filename;

  // $csv_stream = new CSVStream($filename); //直接下載csv用
  $csv_stream = new CSVWriter($file_path);//寫入檔案用
  $csv_stream->begin();
  // 將資料輸出到檔案 -- Title
  $csv_stream->writeRow($csv_key_title);

  for(
    $betrecords = $betrecords_paginator->getCurrentPage()->data;
    count($betrecords) > 0;
    $betrecords = $betrecords_paginator->getNextPage()->data
  ) {
    // var_dump($betrecords);die();
    foreach ($betrecords as $record) {
      $game_name_key = game_name_key_helper($record->casinoid, $record->gamename);
      $game_name = empty($gamename_i18n->{$game_name_key}) ? $game_name_key : $gamename_i18n->{$game_name_key};

      $member_account = $member_account_map[$record->log_account]['account'] ?? '不存在';

      // 將資料輸出到檔案 -- data
      $csv_stream->writeRow([
        '="'.$record->rowid.'"',
        $record->log_time,
        $member_account,
        $record->log_account,
        $game_name,
        $tr[$record->gamecategory] ?? $record->gamecategory,
        $record->totalwager,
        $record->totalamount,
        $record->totalpayout,
        $tr[$record->favorable_category] ?? $record->favorable_category,
        $record->receivelogtime,
        $tr[strtoupper($record->casinoid)] ?? $record->casinoid,
        $bet_status[$record->status],
      ]);
    }
  }

  // -------------------------------------------
  // 將資料輸出到檔案 -- Title
  // $csv_stream->writeRow($csv_key_title);
  $csv_stream->end();

  // 清除快取以防亂碼
  ob_end_clean();

  $excel_stream = new csvtoexcel($filename, $file_path);
  $excel_stream->begin();
  if (file_exists($file_path)) {
      unlink($file_path);
  }

  return;

}elseif ($action == 'select_bonus_list'  AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
  $in_cl_bonus_list = filter_var($_POST['sel_in_cl_bonus_list'], FILTER_SANITIZE_STRING);
  // var_dump($in_cl_bonus_list);die();
  if ($in_cl_bonus_list != '') {
    $menu_bonus_cate_item_sql = 'SELECT casinoid ,json_array_elements_text ( game_flatform_list)::text as category  FROM casino_list WHERE "open" = 1 ;';
    $menu_bonus_cate_item = runSQLall($menu_bonus_cate_item_sql, 0, 'r');

    if ($menu_bonus_cate_item[0] >= 1) {
      for ($i = 1; $i <= $menu_bonus_cate_item[0]; $i++) {
        $in_cl_bonus_list_all[$menu_bonus_cate_item[$i]->casinoid.'_'.$menu_bonus_cate_item[$i]->category] = $menu_bonus_cate_item[$i]->casinoid.$tr[$menu_bonus_cate_item[$i]->category];
      }

      // $member_grade_list = $_POST['select_member_grade'];
      $in_cl_bonus_list = str_replace('bns=', '', $in_cl_bonus_list);
      $in_cl_bonus_list = explode("&", $in_cl_bonus_list);
      // var_dump($in_cl_bonus_list);die();

      if ($in_cl_bonus_list[0] == '') {
        $return_string = ' 请选择游戏分类! ';//$tr['Please select a member level'] = '請選擇會員等級';
      } else {

        switch (count($in_cl_bonus_list)) {
        case 1:
          $return_string = $in_cl_bonus_list_all[$in_cl_bonus_list[0]] ;
          break;
        case 2:
          $return_string = $in_cl_bonus_list_all[$in_cl_bonus_list[0]] . '、' . $in_cl_bonus_list_all[$in_cl_bonus_list[1]] ;
          break;
        case count($in_cl_bonus_list_all):
        $return_string = '全选';
          break;
        default: //$tr['Total'] = '共';
          $return_string =  $in_cl_bonus_list_all[$in_cl_bonus_list[0]] . '、' . $in_cl_bonus_list_all[$in_cl_bonus_list[1]] . '......等'.$tr['total']. count($in_cl_bonus_list) . '個';
          break;
        }

      }

    } else {
      $return_string = '游戏分类查询错误';//$tr['Member level query error'] = '會員等級查詢錯誤。';
    }

  } else {//$tr['Please select a member level'] = '請選擇會員等級';
    $return_string = ' 请选择游戏分类 ! ';
  }

  echo $return_string;

}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer test
// ----------------------------------------------------------------------------
  var_dump($_POST);
}elseif($action == 'betdetail' AND $query_chk == 0){
// ----------------------------------------------------------------------------
// test developer test
// ----------------------------------------------------------------------------
  $request = [];
  $sql = 'SELECT artificial_casino FROM casino_list WHERE casinoid=\''.$betdetail['casinoid'].'\' AND open=1;';
  $result = runSQLall($sql);
  if($result['0'] >= 1 ){
    if( $result['1']->artificial_casino == 0 ){
      $casinoid = strtolower($betdetail['casinoid']);
      require_once dirname(__FILE__) . "/casino/casino_switch_lib.php";
      $request['gamehall'] = strtolower($betdetail['casinoid']);
      $request['betID'] = $betdetail['betid'];
      $request['lang'] = (isset($_SESSION['lang'])) ? $_SESSION['lang'] : $config['default_lang'];
      $api_result = getDataByAPI('GetBetDetail',0,$request);
      if ($api_result['errorcode'] == 0 and $api_result['Status'] == 0 and $api_result['count'] > 0) {
        $urlchk = explode(':',$api_result['Result']->playCheck)[0];
        if($betdetail['casinoid'] == 'CQ9') $urlchk = 'http';
        $output_msg = array('logger' => '','url' =>$api_result['Result']->playCheck,'urlchk'=>$urlchk );
      }else{
        $output_msg = array('logger' => $api_result['Result'],'url' =>'' );
      }
    }else{
      $output_msg = array('logger' => $tr['not support'],'url' => '');
    }

  }else{
    $output_msg = array('logger' => '501'.$tr['error message params error'],'url' => '');
  }
  // echo $output_msg['logger'];
  echo json_encode($output_msg);
}elseif($action == 'query_log' AND $query_chk == 0){
  // NO member
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => ''
  );
  // end member sql
  echo json_encode($output);
}elseif(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
}
// -----------------------------------------------------------------------
// MAIN END
// -----------------------------------------------------------------------

?>
