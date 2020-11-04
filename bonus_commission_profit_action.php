<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營運利潤獎金, 執行的動作處理
// File Name:	bonus_commission_profit_action.php
// Author:		Barkley Fix By Ian
// Related:   bonus_commission_profit.php
// DB table:  root_statisticsbonusprofit  營運利潤獎金
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
// 取得目前系統的上一個月份的時間, 用來計算上期的欠帳. 如果沒有上一期帳單的話, 回傳為 false
// Usage: pre_dailyrange($dailydate_start,$dailydate_end)
// -------------------------------------------------------------------------
function pre_dailyrange($dailydate_start,$dailydate_end) {

    // 列出系統資料統計月份
    $list_sql = '
    SELECT dailydate_start, dailydate_end,MIN(updatetime) as min , MAX(updatetime) as max,count(member_account) as member_account_count
    , sum(sum_all_profitlost) as sum_sum_all_profitlost, sum(profit_amount) as sum_profit_amount, sum(sum_all_bets) as sum_sum_all_bets, sum(sum_all_count) as sum_sum_all_count
    FROM root_statisticsbonusprofit
    GROUP BY dailydate_end,dailydate_start ORDER BY dailydate_start DESC;
    ';

    $list_result = runSQLall($list_sql);
    //var_dump($list_result);

    $pre_dailydate_start = NULL;
    $pre_dailydate_end = NULL;

    // 預設為失敗 , 如果沒有更新的話
    $r = false;

    if($list_result[0] > 0){
      // 把資料 dump 出來 to table
      for($i=1;$i<=$list_result[0];$i++) {
        // 取得上一個月計算週期的時間
        if($list_result[$i]->dailydate_start == $dailydate_start AND $list_result[$i]->dailydate_end == $dailydate_end ){
          $j = $i+1;
          if(isset($list_result[$j]->dailydate_start)  AND isset($list_result[$j]->dailydate_end)) {
            $r['pre_dailydate_start'] = $list_result[$j]->dailydate_start;
            $r['pre_dailydate_end']   = $list_result[$j]->dailydate_end;
          }
        }
      }
    }

    return($r);
}
// ---------------------------------------------------------------------------
// END 取得目前系統的上一個月份的時間, 用來計算上期的欠帳
// ---------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate( $date, $format='Y-m-d H:i:s' ){
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
//var_dump($_POST);
//var_dump($_GET);

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}

if(isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker'])) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['current_datepicker'], 'Y-m-d')) {
    $current_datepicker = $_GET['current_datepicker'];
  }else{
    // 轉換為美東的時間 date
    $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
    date_timezone_set($date, timezone_open('America/St_Thomas'));
    $current_datepicker = date_format($date, 'Y-m-d');
  }
}else{
  // php 格式的 2017-02-24
  // 轉換為美東的時間 date
  $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
  date_timezone_set($date, timezone_open('America/St_Thomas'));
  $current_datepicker = date_format($date, 'Y-m-d');
}
// 營利獎金結算週期，每月的幾號？ 美東時間 (固定週期為月)
//  $rule['stats_profit_day']    = 10;
// 統計的期間時間 0 ~ 1month = 一個月
//var_dump($rule['stats_profit_day']);

// 如果選擇的日期, 大於設定的月結日期，就以下個月顯示. 如果不是的話就是上個月顯示
$current_date_d = date("d", strtotime( "$current_datepicker"));
$current_date_m = date("m", strtotime( "$current_datepicker"));
$current_date_Y = date("Y", strtotime( "$current_datepicker"));
//var_dump($lastdayofmonth);
//var_dump($current_date_d);
if($current_date_d > $rule['stats_profit_day']) {
  $date_fmt = 'Y-m-'.$rule['stats_profit_day'];
  $current_date_m++;
  $current_datepicker = $current_date_Y.'-'.$current_date_m.'-'.$rule['stats_profit_day'];
  //var_dump($current_datepicker);
  // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
  $lastdayofmonth = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));
  // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
  // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
  if($current_datepicker > $lastdayofmonth){
    $current_datepicker_end = $lastdayofmonth;
  }else{
    $current_datepicker_end = $current_datepicker;
  }
  //var_dump($current_datepicker_end);
  // 計算前一輪的計算日
  $current_date_m--;
  $dayofcurrentstart = $rule['stats_profit_day'] + 1;
  $current_datepicker_start = $current_date_Y.'-'.$current_date_m.'-'.$dayofcurrentstart;
  //var_dump($current_datepicker_start);
  // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
  $lastdayofmonth_lastcycle = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));
  // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
  // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
	if($current_datepicker_start > $lastdayofmonth_lastcycle AND $current_date_m == date("m", strtotime( $current_datepicker_start))){
    if($current_date_m == 2){
      $current_date_m++;
      $current_datepicker_start = $current_date_Y.'-'.$current_date_m.'-1';
    }else{
      $current_datepicker_start = $lastdayofmonth_lastcycle;
    }
  }
}else{
  $date_fmt = 'Y-m-'.$rule['stats_profit_day'];
  $current_datepicker = $current_date_Y.'-'.$current_date_m.'-'.$rule['stats_profit_day'];
  // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
  $lastdayofmonth = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));
  // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
  // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
  if($current_datepicker > $lastdayofmonth){
    $current_datepicker_end = $lastdayofmonth;
  }else{
    $current_datepicker_end = $current_datepicker;
  }
  // 計算前一輪的計算日
  $current_date_m--;
  $dayofcurrentstart = $rule['stats_profit_day'] + 1;
  $current_datepicker_start = date("Y-m-d", strtotime($current_date_Y.'-'.$current_date_m.'-'.$dayofcurrentstart));
  //var_dump($current_datepicker_start);
  // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
  $lastdayofmonth_lastcycle = date("Y-m-t", strtotime($current_date_Y.'-'.$current_date_m.'-1'));
  // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
  // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
	if($current_datepicker_start > $lastdayofmonth_lastcycle AND $current_date_m == date("m", strtotime( $current_datepicker_start))){
    if($current_date_m == 2){
      $current_date_m++;
      $current_datepicker_start = $current_date_Y.'-'.$current_date_m.'-1';
    }else{
      $current_datepicker_start = $lastdayofmonth_lastcycle;
    }
  }
}

//var_dump($date_fmt);
//var_dump($current_datepicker_end);
//var_dump($current_datepicker_start);
// 本月的結束日 = $current_datepicker_end
// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期  END
// -------------------------------------------------------------------------

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

// -------------------------------------------------------------------------
// 動作為會員登入檢查 MAIN
// -------------------------------------------------------------------------
if($action == 'member_profitamount_paid' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -----------------------------------------------------------------------
  // 寫入付款欄位
  // -----------------------------------------------------------------------
    var_dump($_GET);
    $id = $_GET['id'];

    $check_sql = "SELECT * FROM root_statisticsbonusprofit WHERE id = '$id';";
    $check_result = runSQLall($check_sql);
    //var_dump($check_result);
    if($check_result[0] == 1) {
      if($check_result[1]->member_profitamount_paid == NULL OR ($check_result[1]->member_profitamount_paid < $check_result[1]->member_profitamount) ){
        $member_profitamount_paid = $check_result[1]->member_profitamount;
        $notes = "$id";

        $update_sql = "UPDATE root_statisticsbonusprofit SET
        member_profitamount_paid = '$member_profitamount_paid',
        member_profitamount_paidtime = now(),
        notes = '$notes'
        WHERE id = '$id';";
        $r = runSQL($update_sql);
        if($r == 1) {
          $logger = '在時間區間'.$check_result[1]->dailydate_start.'~'.$check_result[1]->dailydate_end.' 帳號'.$check_result[1]->member_account.'已經更新付款金額'.$member_profitamount_paid;
          echo $logger;
        }else{
          $logger = '更新失敗<br>'.'在時間區間'.$check_result[1]->dailydate_start.'~'.$check_result[1]->dailydate_end.' 帳號'.$check_result[1]->member_account.'已經更新付款金額'.$member_profitamount_paid;
          die($logger);
        }

      }else{
        $logger = '已經付款過了!!'.'在時間區間'.$check_result[1]->dailydate_start.'~'.$check_result[1]->dailydate_end.' 帳號'.$check_result[1]->member_account.'應付金額'.$check_result[1]->member_profitamount.'已經付款金額'.$check_result[1]->$member_profitamount_paid;;
        echo $logger;
      }
    }else{
      $logger = "沒有這個 ID = $id 資訊";
      echo $logger;
    }

  $logger = '<p><input onclick="window.close();" value="關閉視窗" type="button"></p>';
  echo $logger;

// ----------------------------------------------------------------------------
}elseif($action == 'reload_profitlist' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

  // -----------------------------------------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -----------------------------------------------------------------------
  // 算 root_member 人數
  $userlist_sql_tmp = "SELECT * FROM root_statisticsbonusprofit
  WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker_end'";
  $userlist_sql = $userlist_sql_tmp.';';
  // var_dump($userlist_sql);
  $userlist_count = runSQL($userlist_sql);

  // -----------------------------------------------------------------------
  // 分頁處理機制
  // -----------------------------------------------------------------------
  // 所有紀錄數量
  $page['all_records']     = $userlist_count;
  // 每頁顯示多少
  $page['per_size']        = $current_per_size;
  // 目前所在頁數
  $page['no']              = $current_page_no;
  // var_dump($page);

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    if($_GET['order'][0]['dir'] == 'asc'){ $sql_order_dir = 'ASC';
    }else{ $sql_order_dir = 'DESC';}
    if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY member_id '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY member_account '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 2){ $sql_order = 'ORDER BY member_therole '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY member_level '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY skip_bonusinfo '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY profitaccount_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY profitaccount_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 7){ $sql_order = 'ORDER BY profitaccount_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 8){ $sql_order = 'ORDER BY profitaccount_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 9){ $sql_order = 'ORDER BY sum_all_bets '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 10){ $sql_order = 'ORDER BY profit_amount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 11){ $sql_order = 'ORDER BY profit_amount_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 12){ $sql_order = 'ORDER BY profit_amount_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 13){ $sql_order = 'ORDER BY profit_amount_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 14){ $sql_order = 'ORDER BY profit_amount_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 15){ $sql_order = 'ORDER BY member_profitamount_count_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 16){ $sql_order = 'ORDER BY member_profitamount_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 17){ $sql_order = 'ORDER BY member_profitamount_count_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 18){ $sql_order = 'ORDER BY member_profitamount_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 19){ $sql_order = 'ORDER BY member_profitamount_count_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 20){ $sql_order = 'ORDER BY member_profitamount_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 21){ $sql_order = 'ORDER BY member_profitamount_count_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 22){ $sql_order = 'ORDER BY member_profitamount_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 23){ $sql_order = 'ORDER BY member_profitamount_count '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 24){ $sql_order = 'ORDER BY member_profitamount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 25){ $sql_order = 'ORDER BY lasttime_stayindebt '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 27){ $sql_order = 'ORDER BY member_profitamount_paidtime '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY member_id ASC';}
  }else{ $sql_order = 'ORDER BY member_id ASC';}
  // 取出 root_member 資料
  $userlist_sql   = $userlist_sql_tmp." ".$sql_order." OFFSET ".$page['no']." LIMIT ".$page['per_size']." ;";
  // var_dump($userlist_sql);
  $userlist = runSQLall($userlist_sql);

    $bonus_commission_profit_sql = $userlist_sql_tmp." ".$sql_order." OFFSET ".$page['no'];
    $_SESSION['bonus_commission_profit_sql'] = $userlist_sql_tmp." ".$sql_order." OFFSET ".$page['no'];
    // $bonus_commission_profit_result = runSQLall( $bonus_commission_profit_sql ); var_dump( $bonus_commission_profit_result ); exit();

  $b['dailydate_start'] = $current_datepicker_start;
  $b['dailydate_end'] = $current_datepicker;

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $userlist[0]; $i++){
      $b['id'] = $userlist[$i]->id;
      $b['updatetime'] = $userlist[$i]->updatetime;
      $b['member_id']         = $userlist[$i]->member_id;
      $b['member_account']    = $userlist[$i]->member_account;
      $b['member_therole']    = $userlist[$i]->member_therole;
      $b['member_parent_id']  = $userlist[$i]->member_parent_id;
      // 預設有會員的 ID , Account, Role
      $b['member_level']  = $userlist[$i]->member_level;
      $b['skip_bonusinfo']  = $userlist[$i]->skip_bonusinfo;
      $skip_bonusinfo_count     = explode(":",$b['skip_bonusinfo']);
      //var_dump($skip_bonusinfo_count);  取得第一個字串，為跳過的代數
      $b['skip_agent_tree_count'] = $skip_bonusinfo_count[0];
      $b['profitaccount_1']  = $userlist[$i]->profitaccount_1;
      $b['profitaccount_2']  = $userlist[$i]->profitaccount_2;
      $b['profitaccount_3']  = $userlist[$i]->profitaccount_3;
      $b['profitaccount_4']  = $userlist[$i]->profitaccount_4;
      $b['profit_amount']  = $userlist[$i]->profit_amount;
      $b['profit_amount_1']  = $userlist[$i]->profit_amount_1;
      $b['profit_amount_2']  = $userlist[$i]->profit_amount_2;
      $b['profit_amount_3']  = $userlist[$i]->profit_amount_3;
      $b['profit_amount_4']  = $userlist[$i]->profit_amount_4;

      // 統計的欄位
      $b['member_profitlost_cashcost']  = $userlist[$i]->member_profitlost_cashcost;
      $b['member_profitlost_marketingcost']  = $userlist[$i]->member_profitlost_marketingcost;
      $b['sum_tokenfavorable']  = $userlist[$i]->sum_tokenfavorable;
      $b['sum_tokenpreferential']  = $userlist[$i]->sum_tokenpreferential;
      $b['member_profitlost_platformcost']  = $userlist[$i]->member_profitlost_platformcost;
      $b['days_count']  = $userlist[$i]->days_count;
      $b['sum_all_count']  = $userlist[$i]->sum_all_count;
      $b['sum_all_bets']  = $userlist[$i]->sum_all_bets;
      $b['sum_all_wins']  = $userlist[$i]->sum_all_wins;
      $b['sum_all_profitlost']  = $userlist[$i]->sum_all_profitlost;

      // 代理商本月分潤
      $b['member_profitamount_1']       = $userlist[$i]->member_profitamount_1;
      $b['member_profitamount_count_1'] = $userlist[$i]->member_profitamount_count_1;
      $b['member_profitamount_2']       = $userlist[$i]->member_profitamount_2;
      $b['member_profitamount_count_2'] = $userlist[$i]->member_profitamount_count_2;
      $b['member_profitamount_3']       = $userlist[$i]->member_profitamount_3;
      $b['member_profitamount_count_3'] = $userlist[$i]->member_profitamount_count_3;
      $b['member_profitamount_4']       = $userlist[$i]->member_profitamount_4;
      $b['member_profitamount_count_4'] = $userlist[$i]->member_profitamount_count_4;
      $b['member_profitamount_count']   = $userlist[$i]->member_profitamount_count;
      $b['member_profitamount']  = $userlist[$i]->member_profitamount;
      $b['member_profitamount_paid']  = $userlist[$i]->member_profitamount_paid;
      $b['member_profitamount_paidtime']  = $userlist[$i]->member_profitamount_paidtime;

      // 上月留抵
      $b['lasttime_stayindebt']  = $userlist[$i]->lasttime_stayindebt;
      // 備註
      $b['notes']  = $userlist[$i]->notes;

      // -----------------------------------------------------------------------
      // 參考用數據 , 不顯示因為畫面塞不下
      // -----------------------------------------------------------------------
      $member_profitlost_ref = '統計天數'.$b['days_count'].',會員注單量'.$b['sum_all_count'].',投注'.$b['sum_all_bets'].',派彩'.$b['sum_all_wins'].
      ',損益(不扣成本)'.$b['sum_all_profitlost'].',平台佔成本比例'.$rule['platformcost_rate'].'%,平台成本'.$b['member_profitlost_platformcost'].
      ',行銷成本'.$b['member_profitlost_marketingcost'].',優惠成本'.$b['sum_tokenfavorable'].',反水成本'.$b['sum_tokenpreferential'].',金流成本'.$b['member_profitlost_cashcost'];
      // -----------------------------------------------------------------------

      // 付款資訊欄位, 包含動作處理 call 另外一個程式
      // 只有查詢模式的時候, 這個變數才會出現. 才讓使用者有所動作選擇.
      $paid_notes = '发放'.$b['member_account'].'帐号'.$b['dailydate_start'].'的營運利潤獎金';
      if(isset($b['id'])) {
        // 付款資訊 , 不處理,不更新. 變數設定但是這個選項不顯示
        // 沒有付過款項, 且款項大於 0
        if($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] > 0 ) {
          // 轉帳
          $member_profitamount_paid_html = '<a href="member_depositgcash.php?a='.$b['member_id'].'&gcash='.round($b['member_profitamount'],2).'&notes='.$paid_notes.'" title="立即进行转帐给'.$b['member_account'].'金额'.round($b['member_profitamount']).'" target="_blank"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></a>';
          // 寫入付款欄位資訊
          $member_profitamount_paid_html = $member_profitamount_paid_html.'&nbsp;&nbsp;
          <a href="bonus_commission_profit_action.php?a=member_profitamount_paid&id='.$b['id'].'"  onclick="return confirm(\'请确认已经汇款完成了,再来更新此栏位 ??\')" title="金额写入这个栏位" target="_blank">
          <span class="glyphicon glyphicon-pencil" aria-hidden="true"></a>';

        // 沒有付過款項, 且款項小於 0
        }elseif($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] < 0 ) {
            $member_profitamount_paid_html = '<a href="#" title="本月營收為負營收，紀錄到下個月留抵"  target="_BLANK"><span class="glyphicon glyphicon-minus-sign" aria-hidden="true"></span></a>';

        // 沒有付過款項, 且款項為 0 , 身份為 Member
        }elseif($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] == 0 AND $b['member_therole'] == 'M' ) {
          $member_profitamount_paid_html = '<a href="#" title="會員身份無組織沒有分紅" target="_BLANK"><span class="glyphicon glyphicon-remove-circle" aria-hidden="true"></span></a>';

        // 沒有付過款項, 且款項為 0
        }elseif($b['member_profitamount_paid'] == NULL AND $b['member_profitamount'] == 0 ) {
          $member_profitamount_paid_html = '<a href="#" title="本月無營收" target="_BLANK"><span class="glyphicon glyphicon-ban-circle" aria-hidden="true"></span></a>';

        }else{
          if($b['member_profitamount_paid'] < 0) {
            $member_profitamount_paid_html = '<a href="#" title="本月累加上月後為負營收,系統自動紀錄於此下月留抵此金額."  target="_BLANK">'.$b['member_profitamount_paid'].'</a>';

          }else{
            $member_profitamount_paid_html = '<a href="#" title="已經有付款紀錄，請確認後再行轉帳轉帳到會員現金帳戶。"  target="_BLANK">'.$b['member_profitamount_paid'].'</a>';

          }
        }
        // var_dump($b);
      }else{
        $member_profitamount_paid_html = '<a href="#" title="只有查詢模式, 才有動作可以選擇"  target="_BLANK">n/a</a>';
      }

      // 把付款更新的時間, 改變一下呈現的格式. 避免太長的欄位
      // date("Y-m-d H:i:s",strtotime($b['member_profitamount_paidtime']))
      if($b['member_profitamount_paidtime'] == NULL) {
        $member_profitamount_paidtime_html = 'n/a';
      }else{
        $member_profitamount_paidtime_html = '<a href="#" title="'.$b['member_profitamount_paidtime'].'">'.date("m-d H:i",strtotime($b['member_profitamount_paidtime'])).'</a>';
      }


      // 顯示的表格資料內容
      $show_listrow_array[] = array(
        'id'=>$b['member_id'],
        'account'=>$b['member_account'],
        'therole'=>$b['member_therole'],
        'member_level'=>$b['member_level'],
        'skip_agent_tree_count'=>$b['skip_agent_tree_count'],
        'skip_bonusinfo'=>$b['skip_bonusinfo'],
        'profitaccount_1'=>$b['profitaccount_1'],
        'profitaccount_2'=>$b['profitaccount_2'],
        'profitaccount_3'=>$b['profitaccount_3'],
        'profitaccount_4'=>$b['profitaccount_4'],
        'sum_all_bets'=>$b['sum_all_bets'],
        'profit_amount'=>$b['profit_amount'],
        'member_profitlost_ref'=>$member_profitlost_ref,
        'profit_amount_1'=>$b['profit_amount_1'],
        'profit_amount_2'=>$b['profit_amount_2'],
        'profit_amount_3'=>$b['profit_amount_3'],
        'profit_amount_4'=>$b['profit_amount_4'],
        'member_profitamount_count_1'=>$b['member_profitamount_count_1'],
        'member_profitamount_1'=>$b['member_profitamount_1'],
        'member_profitamount_count_2'=>$b['member_profitamount_count_2'],
        'member_profitamount_2'=>$b['member_profitamount_2'],
        'member_profitamount_count_3'=>$b['member_profitamount_count_3'],
        'member_profitamount_3'=>$b['member_profitamount_3'],
        'member_profitamount_count_4'=>$b['member_profitamount_count_4'],
        'member_profitamount_4'=>$b['member_profitamount_4'],
        'member_profitamount_count'=>$b['member_profitamount_count'],
        'member_profitamount'=>$b['member_profitamount'],
        'lasttime_stayindebt'=>$b['lasttime_stayindebt'],
        'member_profitamount_paid'=>$member_profitamount_paid_html,
        'member_profitamount_paidtime'=>$member_profitamount_paidtime_html,
        'note'=>$b['notes']);
    }
    // var_dump( $show_listrow_array ); exit();
    $output = array(
      "sEcho" => intval($secho),
      "iTotalRecords" => intval($page['per_size']),
      "iTotalDisplayRecords" => intval($userlist_count),
      "data" => $show_listrow_array
    );
    // --------------------------------------------------------------------
    // 表格資料 row list , end for loop
    // --------------------------------------------------------------------
  }
  else{
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
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------
}elseif($action == 'bonus_update' AND isset($_GET['bonus_update_date'])) {
  if(validateDate($_GET['bonus_update_date'], 'Y-m-d')) {
    $dailydate = $_GET['bonus_update_date'];
    $file_key = sha1('profit_update'.$dailydate);
    $reload_file = dirname(__FILE__) .'/tmp_dl/profit_'.$file_key.'.tmp';
    if(file_exists($reload_file)) {
      die('請勿重覆操作');
    }else{
      $command   = $config['PHPCLI'].' bonus_commission_profit_cmd.php run '.$dailydate.' web > '.$reload_file.' &';
      echo '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){window.location.href="'.$_SERVER['PHP_SELF'].'?a=update_reload&k='.$file_key.'"},1000);</script>';
      $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
      file_put_contents($reload_file,$output_html);
      passthru($command, $return_var);
    }
  }else{
    $output_html  = '日期格式有問題，請確定有且格式正確，需要為 YYYY-MM-DD 的格式';
    echo '<hr><br><br><p align="center">'.$output_html.'</p>';
    echo '<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';
  }
}elseif($action == 'profitbonus_payout_update' AND isset($_GET['profitbonus_payout_date']) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    if(validateDate($_GET['profitbonus_payout_date'], 'Y-m-d')  AND isset($_GET['s']) AND isset($_GET['s1']) AND isset($_GET['s2']) AND isset($_GET['s3']) ){
      // 取得獎金的各設定並生成token傳給 cmd 執行
      $bonus_status = filter_var($_GET['s'],FILTER_VALIDATE_INT);
      $bonus_type = filter_var($_GET['s1'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
      $audit_type = filter_var($_GET['s2'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
      $audit_amount = filter_var($_GET['s3'],FILTER_VALIDATE_INT);

      $bonusstatus_array = array(
        'bonus_status' => $bonus_status,
        'bonus_type' => $bonus_type,
        'audit_type' => $audit_type,
        'audit_amount' => $audit_amount
      );
      //var_dump($bonusstatus_array);
      // 產生 token , salt是檢核密碼預設值為123456 ,需要配合 jwtdec 的解碼, 此範例設定為 123456
      $bonus_token = jwtenc('profitbonuspayout', $bonusstatus_array);

      $dailydate = $_GET['profitbonus_payout_date'];
      $file_key = sha1('profitbonuspayout'.$dailydate);
      $logfile_name = dirname(__FILE__) .'/tmp_dl/profit_'.$file_key.'.tmp';
      if(file_exists($logfile_name)) {
        die('請勿重覆操作');
      }else{
        $command   = $config['PHPCLI'].' bonus_commission_profit_payout_cmd.php run '.$dailydate.' '.$bonus_token.' '.$_SESSION['agent']->account.' web > '.$logfile_name.' &';
        echo '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){window.location.href="'.$_SERVER['PHP_SELF'].'?a=update_reload&k='.$file_key.'"},1000);</script>';
        $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
        file_put_contents($logfile_name,$output_html);
        passthru($command, $return_var);
      }
    }else{
      $output_html  = '日期格式或狀態設定有問題，請確定有日期及狀態設定且格式正確，日期格式需要為 YYYY-MM-DD 的格式';
      echo '<hr><br><br><p align="center">'.$output_html.'</p>';
      echo '<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';
    }
}elseif($action == 'update_reload' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/profit_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      echo file_get_contents($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'profitbonus_del' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/profit_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      unlink($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'test') {
  // -----------------------------------------------------------------------
  // test developer
  // -----------------------------------------------------------------------
  var_dump($_POST);

}elseif(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  $output = array(
    "sEcho" => 0,
    "iTotalRecords" => 0,
    "iTotalDisplayRecords" => 0,
    "data" => ''
  );
  echo json_encode($output);
}else{
  $logger = '(x) 只有管理員或有權限的會員才可以使用。';
  echo $logger;
}



?>
