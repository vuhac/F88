<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 代理加盟金, 執行的動作處理
// File Name:	bonus_commission_agent_action.php
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
//var_dump($_POST);
//var_dump($_GET);$tr['Illegal test'] = '(x)不合法的測試。';

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die($tr['Illegal test']);
}

if(isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker'])) {
  // 判斷格式資料是否正確, 不正確以今天的美東時間為主
  $current_datepicker = validateDate($_GET['current_datepicker'], 'Y-m-d');
  //var_dump($current_datepicker);
  if($current_datepicker) {
    $current_datepicker = $_GET['current_datepicker'];
  }else{
    // 轉換為美東的時間 date
    $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
  }
}else{
  // 轉換為美東的時間 date
  $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
}
//var_dump($current_datepicker);
//echo date('Y-m-d H:i:sP');
// 統計的期間時間 $rule['stats_commission_days'] 參考次變數
$stats_commission_days = $rule['stats_commission_days'] - 1;
$current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -$stats_commission_days day"));


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
//var_dump($_GET);


// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------
if($action == 'member_bonusamount_paid' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// 寫入付款欄位
// ----------------------------------------------------------------------------
  //var_dump($_GET);
  $id = $_GET['id'];

  $check_sql = "SELECT * FROM root_statisticsbonusagent WHERE id = '$id';";
  $check_result = runSQLall($check_sql);
  //var_dump($check_result);
  if($check_result[0] == 1) {
    if($check_result[1]->member_bonusamount_paid == NULL OR ($check_result[1]->member_bonusamount_paid < $check_result[1]->member_bonusamount) ){
      $member_bonusamount_paid = $check_result[1]->member_bonusamount;
      $notes = "$id";

      $update_sql = "UPDATE root_statisticsbonusagent SET
      member_bonusamount_paid = '$member_bonusamount_paid',
      member_bonusamount_paidtime = now(),
      notes = '$notes'
      WHERE id = '$id';";
      $r = runSQL($update_sql);
      if($r == 1) {
        $logger = '在時間區間'.$check_result[1]->dailydate_start.'~'.$check_result[1]->dailydate_end.' 帳號'.$check_result[1]->member_account.'已經更新付款金額'.$member_bonusamount_paid;
        echo $logger;
      }else{
        $logger = '更新失敗<br>'.'在時間區間'.$check_result[1]->dailydate_start.'~'.$check_result[1]->dailydate_end.' 帳號'.$check_result[1]->member_account.'已經更新付款金額'.$member_bonusamount_paid;
        die($logger);
      }

    }else{
      $logger = '已經付款過了!!'.'在時間區間'.$check_result[1]->dailydate_start.'~'.$check_result[1]->dailydate_end.' 帳號'.$check_result[1]->member_account.'應附金額'.$check_result[1]->member_bonusamount.'已經付款金額'.$check_result[1]->member_bonusamount_paid;;
      echo $logger;
    }
  }else{
    $logger = "沒有這個 ID = $id 資訊";
    echo $logger;
  }

$logger = '<p><input onclick="window.close();" value="關閉視窗" type="button"></p>';
echo $logger;

// ----------------------------------------------------------------------------
}elseif($action == 'bonusagent_show' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

  // -----------------------------------------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -----------------------------------------------------------------------
  // 設定基本查詢條件
  $userlist_sql_tmp = "SELECT * FROM root_statisticsbonusagent
  WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'";

  // 處理 datatables 傳來的search需求
  if(isset($_GET['search']['value']) AND $_GET['search']['value'] != ''){
    $userlist_sql_tmp = $userlist_sql_tmp.' AND (
        member_account = \''.$_GET['search']['value'].'\' OR
        level_account_1 = \''.$_GET['search']['value'].'\' OR
        level_account_2 = \''.$_GET['search']['value'].'\' OR
        level_account_3 = \''.$_GET['search']['value'].'\' OR
        level_account_4 = \''.$_GET['search']['value'].'\' )';
  }

  // 算 root_member 人數
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
    }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY level_account_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY level_account_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY level_account_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 7){ $sql_order = 'ORDER BY level_account_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 8){ $sql_order = 'ORDER BY agency_commission '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 9){ $sql_order = 'ORDER BY level_bonus_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 10){ $sql_order = 'ORDER BY level_bonus_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 11){ $sql_order = 'ORDER BY level_bonus_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 12){ $sql_order = 'ORDER BY level_bonus_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 13){ $sql_order = 'ORDER BY company_bonus '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 14){ $sql_order = 'ORDER BY member_bonuscount_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 15){ $sql_order = 'ORDER BY member_bonus_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 16){ $sql_order = 'ORDER BY member_bonuscount_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 17){ $sql_order = 'ORDER BY member_bonus_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 18){ $sql_order = 'ORDER BY member_bonuscount_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 19){ $sql_order = 'ORDER BY member_bonus_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 20){ $sql_order = 'ORDER BY member_bonuscount_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 21){ $sql_order = 'ORDER BY member_bonus_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 22){ $sql_order = 'ORDER BY member_bonuscount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 23){ $sql_order = 'ORDER BY member_bonusamount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 24){ $sql_order = 'ORDER BY member_bonusamount_paid '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 25){ $sql_order = 'ORDER BY member_bonusamount_paidtime '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 26){ $sql_order = 'ORDER BY notes '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY member_id ASC';}
  }else{ $sql_order = 'ORDER BY member_id ASC';}
  // 取出 root_member 資料
  $userlist_sql   = $userlist_sql_tmp." ".$sql_order." OFFSET ".$page['no']." LIMIT ".$page['per_size']." ;";
  // var_dump($userlist_sql);
  $userlist = runSQLall($userlist_sql);

  $b['dailydate_start'] = $current_datepicker_start;
  $b['dailydate_end'] = $current_datepicker;

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $userlist[0]; $i++){
      // 資料庫內的 PK
  		$b['id']                     = $userlist[$i]->id;
  		// 會員的 member ID
  		$b['member_id']              = $userlist[$i]->member_id;
  		$b['member_account']         = $userlist[$i]->member_account;
  		$b['member_therole']         = $userlist[$i]->member_therole;
  		$b['member_parent_id']       = $userlist[$i]->member_parent_id;
  		$b['dailydate_start']        = $userlist[$i]->dailydate_start;
  		$b['dailydate_end']          = $userlist[$i]->dailydate_end;
  		$b['member_level']           = $userlist[$i]->member_level;
  		$b['level_account_1']        = $userlist[$i]->level_account_1;
  		$b['level_account_2']        = $userlist[$i]->level_account_2;
  		$b['level_account_3']        = $userlist[$i]->level_account_3;
  		$b['level_account_4']        = $userlist[$i]->level_account_4;
  		$b['agency_commission']      = $userlist[$i]->agency_commission;
  		$b['level_bonus_1']          = $userlist[$i]->level_bonus_1;
  		$b['level_bonus_2']          = $userlist[$i]->level_bonus_2;
  		$b['level_bonus_3']          = $userlist[$i]->level_bonus_3;
  		$b['level_bonus_4']          = $userlist[$i]->level_bonus_4;
  		$b['company_bonus']          = $userlist[$i]->company_bonus;

  		$b['member_bonuscount_1']     = $userlist[$i]->member_bonuscount_1;
  		$b['member_bonus_1']     			= $userlist[$i]->member_bonus_1;
  		$b['member_bonuscount_2']     = $userlist[$i]->member_bonuscount_2;
  		$b['member_bonus_2']    			= $userlist[$i]->member_bonus_2;
  		$b['member_bonuscount_3']     = $userlist[$i]->member_bonuscount_3;
  		$b['member_bonus_3']     			= $userlist[$i]->member_bonus_3;
  		$b['member_bonuscount_4']     = $userlist[$i]->member_bonuscount_4;
  		$b['member_bonus_4']    			= $userlist[$i]->member_bonus_4;

  		$b['member_bonuscount']     			= $userlist[$i]->member_bonuscount;
  		$b['member_bonusamount']    			= $userlist[$i]->member_bonusamount;
  		$b['member_bonusamount_paid']			= $userlist[$i]->member_bonusamount_paid;
  		$b['member_bonusamount_paidtime'] = $userlist[$i]->member_bonusamount_paidtime;
  		$b['notes']			                  = $userlist[$i]->notes;

      // 發放 member_bonusamount_paid
      $paid_notes = '发放'.$b['member_account'].'帐号'.$b['dailydate_start'].'的加盟佣金';
      if($b['member_bonusamount'] > 0 AND $b['member_bonusamount_paid'] == NULL) {
        // 轉帳
        $member_bonusamount_paid_html = '<a href="member_depositgcash.php?a='.$b['member_id'].'&gcash='.round($b['member_bonusamount']).'&notes='.$paid_notes.'" title="立即进行转帐给'.$b['member_account'].'金额'.round($b['member_bonusamount']).'" target="_blank"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></a>';
        // 寫入付款欄位資訊
        $member_bonusamount_paid_html = $member_bonusamount_paid_html.'&nbsp;&nbsp;<a href="bonus_commission_agent_action.php?a=member_bonusamount_paid&id='.$b['id'].'"  onclick="return confirm(\'请确认已经汇款完成了,再来更新此栏位 ??\')" title="金额写入这个栏位" target="_blank"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></a>';
      }elseif($b['member_bonusamount'] == 0 AND $b['member_bonusamount_paid'] == NULL) {
        $member_bonusamount_paid_html = '<a href="#" title="无须转帐">n/a</a>';
      }else{
        $member_bonusamount_paid_html = '<a href="bonus_commission_agent_action.php" title="已经发放了">'.round($b['member_bonusamount_paid']).'</a>';
      }

      // 把付款更新的時間, 改變一下呈現的格式. 避免太長的欄位
      // date("Y-m-d H:i:s",strtotime($b['member_profitamount_paidtime']))
      if($b['member_bonusamount_paidtime'] == NULL) {
        $member_bonusamount_paidtime_html = 'n/a';
      }else{
        $member_bonusamount_paidtime_html = '<a href="#" title="'.$b['member_bonusamount_paidtime'].'">'.date("m-d H:i",strtotime($b['member_bonusamount_paidtime'])).'</a>';
      }



      // 顯示的表格資料內容
      $show_listrow_array[] = array(
        'id'=>$b['member_id'],
        'account'=>$b['member_account'],
        'therole'=>$b['member_therole'],
        'member_level'=>$b['member_level'],
        'level_account_1'=>$b['level_account_1'],
        'level_account_2'=>$b['level_account_2'],
        'level_account_3'=>$b['level_account_3'],
        'level_account_4'=>$b['level_account_4'],
        'agency_commission'=>$b['agency_commission'],
        'level_bonus_1'=>$b['level_bonus_1'],
        'level_bonus_2'=>$b['level_bonus_2'],
        'level_bonus_3'=>$b['level_bonus_3'],
        'level_bonus_4'=>$b['level_bonus_4'],
        'company_bonus'=>$b['company_bonus'],
        'member_bonuscount_1'=>$b['member_bonuscount_1'],
        'member_bonus_1'=>$b['member_bonus_1'],
        'member_bonuscount_2'=>$b['member_bonuscount_2'],
        'member_bonus_2'=>$b['member_bonus_2'],
        'member_bonuscount_3'=>$b['member_bonuscount_3'],
        'member_bonus_3'=>$b['member_bonus_3'],
        'member_bonuscount_4'=>$b['member_bonuscount_4'],
        'member_bonus_4'=>$b['member_bonus_4'],
        'member_bonuscount'=>$b['member_bonuscount'],
        'member_bonusamount'=>$b['member_bonusamount'],
        'member_bonusamount_paid_html'=>$member_bonusamount_paid_html,
        'member_bonusamount_paidtime_html'=>$member_bonusamount_paidtime_html,
        'note'=>$b['notes']);
    }
    $output = array(
      "sEcho" => intval($secho),
      "iTotalRecords" => intval($page['per_size']),
      "iTotalDisplayRecords" => intval($userlist_count),
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
  // end member sql
  echo json_encode($output);
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

}elseif($action == 'show_summary' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -------------------------------------------
  // 計算系統統合性摘要 - summary
  // -------------------------------------------

  // 此時間範圍
  $csv_data['summary']['時間範圍'] = "$current_datepicker_start ~ $current_datepicker";
  // 此時間範圍的會員人數
  $userlist_sql = "SELECT * FROM root_statisticsbonusagent
  WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker';";
  // var_dump($userlist_sql);
  $userlist_count = runSQL($userlist_sql);
  $csv_data['summary']['時間範圍的會員人數'] = $userlist_count;

  // 組織代理加盟金的總額
  // 組織代理加盟金的筆數
  $summary_agency_commission_sql =  "SELECT sum(agency_commission) as sum_agency_commission, count(agency_commission) as count_agency_commission
  FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND agency_commission > 0;";
  $summary_agency_commission_result = runSQLall($summary_agency_commission_sql);
  $csv_data['summary']['加盟金的總額']  = $summary_agency_commission_result[1]->sum_agency_commission;
  $csv_data['summary']['加盟金的筆數']  = $summary_agency_commission_result[1]->count_agency_commission;

  // 組織代理個人分傭的最多和最少
  // 組織代理分傭的有分配到的個人人數
  // 組織代理分傭的的有分配到的個人餘額合計
  $summary_member_bonusamount_sql = "SELECT sum(member_bonusamount) as sum_member_bonusamount , count(member_bonusamount) as count_member_bonusamount
  , max(member_bonusamount) as max_member_bonusamount, min(member_bonusamount) as min_member_bonusamount, sum(member_bonuscount) as sum_member_bonuscount
  FROM root_statisticsbonusagent
  WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND member_bonusamount > 0;";
  $summary_member_bonusamount_result = runSQLall($summary_member_bonusamount_sql);
  $csv_data['summary']['個人分傭的最多']               = $summary_member_bonusamount_result[1]->max_member_bonusamount;
  $csv_data['summary']['個人分傭的最少']               = $summary_member_bonusamount_result[1]->min_member_bonusamount;
  $csv_data['summary']['有分配到分傭的個人人數']      = $summary_member_bonusamount_result[1]->count_member_bonusamount;
  $csv_data['summary']['有分配到分傭的個人餘額合計']    = $summary_member_bonusamount_result[1]->sum_member_bonusamount;
  $csv_data['summary']['有分配到分傭的個人餘額筆數合計'] = $summary_member_bonusamount_result[1]->sum_member_bonuscount;

  //  組織代理分傭公司分配收入合計
  $sum_company_bonus_sql = "SELECT sum(company_bonus) as sum_company_bonus FROM root_statisticsbonusagent
  WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND company_bonus > 0;";
  $sum_company_bonus_result = runSQLall($sum_company_bonus_sql);
  $csv_data['summary']['公司分傭收入合計'] = $sum_company_bonus_result[1]->sum_company_bonus;

  // 組織代理加盟金的沒有分配到的總和餘額(n/a內容)
  // 組織代理加盟金的沒有分配到的總和筆數(n/a內容)
  $lb_sql[1]     = "SELECT count(level_bonus_1) as count_level_bonus_1 ,sum(level_bonus_1) as sum_level_bonus_1
  FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
  AND level_bonus_1 > 0 AND level_account_1= 'n/a';";
  $lb_result[1]  = runSQLall($lb_sql[1]);
  $lb_sql[2]     = "SELECT count(level_bonus_2) as count_level_bonus_2 ,sum(level_bonus_2) as sum_level_bonus_2
  FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
  AND level_bonus_2 > 0 AND level_account_2= 'n/a';";
  $lb_result[2]  = runSQLall($lb_sql[2]);
  $lb_sql[3]     = "SELECT count(level_bonus_3) as count_level_bonus_3 ,sum(level_bonus_3) as sum_level_bonus_3
  FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
  AND level_bonus_3 > 0 AND level_account_3= 'n/a';";
  $lb_result[3]  = runSQLall($lb_sql[3]);
  $lb_sql[4]     = "SELECT count(level_bonus_4) as count_level_bonus_4 ,sum(level_bonus_4) as sum_level_bonus_4
  FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'
  AND level_bonus_4 > 0 AND level_account_4= 'n/a';";
  $lb_result[4]  = runSQLall($lb_sql[4]);
  //var_dump($lb_sql);
  //var_dump($lb_result);
  if($lb_result[1][0] == 1 AND $lb_result[2][0] == 1 AND $lb_result[3][0] == 1 AND $lb_result[4][0] == 1){
    $csv_data['summary']['沒有分配到的總和餘額'] = $lb_result[1][1]->sum_level_bonus_1+$lb_result[2][1]->sum_level_bonus_2+$lb_result[3][1]->sum_level_bonus_3+$lb_result[4][1]->sum_level_bonus_4;
    $csv_data['summary']['沒有分配到的總和筆數'] = $lb_result[1][1]->count_level_bonus_1+$lb_result[2][1]->count_level_bonus_2+$lb_result[3][1]->count_level_bonus_3+$lb_result[4][1]->count_level_bonus_4;
  }else{
    $csv_data['summary']['沒有分配到的總和餘額'] = NULL;
    $csv_data['summary']['沒有分配到的總和筆數'] = NULL;
  }
  $current_daterange_html = $current_datepicker;
  $sum_member_bonusamount_html = $csv_data['summary']['有分配到分傭的個人餘額合計'];

  $summary_payoutmember_count_sql =  "SELECT member_id
  FROM root_statisticsbonusagent WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker' AND member_bonusamount > '0' AND member_bonusamount_paidtime IS NULL;";
  $payoutmember_count = runSQL($summary_payoutmember_count_sql);

  if($payoutmember_count > 0){
    $batchreleasebutton_html = '<button id="batchpayout_html_btn" class="btn btn-info" onclick="batchpayout_html();">'.$tr['batch sending'].'</button>';
    $batchpayout_html = '
      <div style="display: none;width: 800px;" id="batchpayout">
        <table class="table table-bordered">
          <thead>
            <tr bgcolor="#e6e9ed">
              <th>'.$tr['date'].'</th>
              <th>'.$tr['number of sent'].'</th>
              <th>預計發送的紅利總計</th>
              <th>'.$tr['Bonus category'].'</th>
              <th>'.$tr['sending method'].'</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>'.$current_daterange_html.'</td>
              <td>'.$payoutmember_count.'</td>
              <td>'.$sum_member_bonusamount_html.'</td>
              <td><select class="form-control" name="bonus_type" id="bonus_type"  onchange="auditsetting();"><option value="">--</option><option value="token">'.$tr['Franchise'].'</option><option value="cash">'.$tr['Franchise Fee'].'</option></select></td>
              <td><select class="form-control" name="bonus_defstatus" id="bonus_defstatus" ><option value="0">'.$tr['Cancel'].'</option><option value="1">'.$tr['Can receive'].'</option><option value="2" selected>'.$tr['time out'].'</option></select></td>
            </tr>
            <tr>
              <th bgcolor="#e6e9ed"><center>'.$tr['Audit method'].'</center></th>
              <td><select class="form-control" name="audit_type" id="audit_type" disabled><option value="none" selected="">--</option><option value="freeaudit">'.$tr['freeaudit'].'</option><option value="depositaudit">'.$tr['Deposit audit'].'</option><option value="shippingaudit">優惠存款稽核</option></select></td>
              <th bgcolor="#e6e9ed"><center>'.$tr['audit amount'].'</center></th>
              <td><input class="form-control" name="audit_amount" id="audit_amount" value="0" placeholder="'.$tr['audit amount ex'].'" disabled></td>
              <td><button id="payout_btn" class="btn btn-info" onclick="batchpayout();" disabled>'.$tr['send'].'</button>
                  <button class="btn btn-warning" onclick="batchpayoutpage_close();">'.$tr['Cancel'].'</button></td>
            </tr>
          </tbody>
        </table>
      </div>';
    $batchpayout_html = $batchpayout_html.'
      <script type="text/javascript" language="javascript" class="init">
      function auditsetting(){
        var bonustype = $("#bonus_type").val();
        console.log(bonustype);

        if(bonustype == ""){
          $("#payout_btn").prop(\'disabled\', true);
        }else{
          if(bonustype == \'token\'){
            $("#audit_type").prop(\'disabled\', false);
            $("#audit_amount").prop(\'disabled\', false);
          }else{
            $("#audit_type").prop(\'disabled\', true);
            $("#audit_amount").prop(\'disabled\', true);
          }
          $("#payout_btn").prop(\'disabled\', false);
        }
      }
      function batchpayout_html(){
        $.blockUI(
        {
          message: $(\'#batchpayout\'),
          css: {
           padding: 0,
           margin: 0,
           width: \'800px\',
           top: \'30%\',
           left: \'25%\',
           border: \'none\',
           cursor: \'auto\'
          }
        });
      }
      function batchpayoutpage_close(){
        $.unblockUI();
      }
      function batchpayout(){
        $("#payout_btn").prop(\'disabled\', true);

        var show_text = \'即将發放 '.$current_daterange_html.' 的紅利...\';
        var payout_status = $("#bonus_defstatus").val();
        var bonus_type = $("#bonus_type").val();
        var audit_type = $("#audit_type").val();
        var audit_amount = $("#audit_amount").val();
        var payoutupdatingcodeurl=\'bonus_commission_agent_action.php?a=agentbonus_payout_update&agentbonus_payout_date='.$current_datepicker_start.'&s=\'+payout_status+\'&s1=\'+bonus_type+\'&s2=\'+audit_type+\'&s3=\'+audit_amount;

        if(bonus_type == \'token\' && audit_type == \'none\'){
          alert(\'請選擇獎金的稽核方式！\');
        }else{
          if(confirm(show_text)){
            $.unblockUI();
            $("#batchpayout_html_btn").prop(\'disabled\', true);
            myWindow = window.open(payoutupdatingcodeurl, \'gpk_window\', \'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800\', false);
            myWindow.focus();
          }else{
            $("#payout_btn").prop(\'disabled\', false);
          }
        }
      }
      </script>';
  }else{
    $batchreleasebutton_html = '<button id="batchpayout_html_btn" class="btn btn-info" disabled>批次發送</button>';
    $batchpayout_html = '';
  }

  $table_summary_html = '
  <table class="table table-bordered small">
    <thead>
      <tr class="active">
        <th>時間範圍</th>
        <th>時間範圍的會員人數</th>
        <th>加盟金的總額</th>
        <th>加盟金的筆數</th>
        <th>個人分傭的最多</th>
        <th>個人分傭的最少</th>
        <th>有分配到分傭的會員人數</th>
        <th>有分配到分傭的會員餘額合計</th>
        <th>有分配到分傭的會員餘額筆數合計</th>
        <th>公司分傭收入合計</th>
        <th>沒有分配到的總和餘額</th>
        <th>沒有分配到的總和筆數</th>
        <th></th>
      </tr>
    </thead>
    <tbody style="background-color:rgba(255,255,255,0.4);">
      <tr>
      <td>'.$csv_data['summary']['時間範圍'].'</td>
      <td>'.$csv_data['summary']['時間範圍的會員人數'].'</td>
      <td>'.$csv_data['summary']['加盟金的總額'].'</td>
      <td>'.$csv_data['summary']['加盟金的筆數'].'</td>
        <td>'.$csv_data['summary']['個人分傭的最多'].'</td>
        <td>'.$csv_data['summary']['個人分傭的最少'].'</td>
        <td>'.$csv_data['summary']['有分配到分傭的個人人數'].'</td>
        <td>'.$csv_data['summary']['有分配到分傭的個人餘額合計'].'</td>
        <td>'.$csv_data['summary']['有分配到分傭的個人餘額筆數合計'].'</td>
        <td>'.$csv_data['summary']['公司分傭收入合計'].'</td>
        <td>'.$csv_data['summary']['沒有分配到的總和餘額'].'</td>
        <td>'.$csv_data['summary']['沒有分配到的總和筆數'].'</td>
        <td>'.$batchreleasebutton_html.'</td>
      </tr>
    </tbody>
  </table>
  <hr>
  ';

  $table_summary_html = $table_summary_html.$batchpayout_html;

  // var_dump($csv_data['summary']);
  // -------------------------------------------
  // 計算系統統合性摘要 - summary END
  // -------------------------------------------

  echo $table_summary_html;
}elseif($action == 'bonus_update' AND isset($current_datepicker) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  $file_key = sha1('agentbonus'.$current_datepicker);
  $logfile_name = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$file_key.'.tmp';
  if(file_exists($logfile_name)) {
    die('請勿重覆操作');
  }else{
    $command   = $config['PHPCLI'].' bonus_commission_agent_cmd.php run '.$current_datepicker.' web > '.$logfile_name.'  &';
    echo '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){window.location.href="'.$_SERVER['PHP_SELF'].'?a=update_reload&k='.$file_key.'"},1000);</script>';
    $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
    file_put_contents($logfile_name,$output_html);
    passthru($command, $return_var);
  }
}elseif($action == 'agentbonus_payout_update' AND isset($_GET['agentbonus_payout_date']) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  if(validateDate($_GET['agentbonus_payout_date'], 'Y-m-d') AND isset($_GET['s']) AND isset($_GET['s1']) AND isset($_GET['s2']) AND isset($_GET['s3'])){
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
    $bonus_token = jwtenc('agentbonuspayout', $bonusstatus_array);

    $dailydate = $_GET['agentbonus_payout_date'];
    $file_key = sha1('agentbonuspayout'.$dailydate);
    $logfile_name = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$file_key.'.tmp';
    if(file_exists($logfile_name)) {
      die('請勿重覆操作');
    }else{
      $command   = $config['PHPCLI'].' bonus_commission_agent_payout_cmd.php run '.$dailydate.' '.$bonus_token.' '.$_SESSION['agent']->account.' web > '.$logfile_name.' &';
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
    $reload_file = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      echo file_get_contents($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'agentbonus_del' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/agentbonus_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      unlink($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
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
