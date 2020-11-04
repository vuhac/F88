<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營業獎金, 執行的動作處理
// File Name:	bonus_commission_sale_action.php
// Author:		Barkley
// Related:   bonus_commission_profit.php
// DB table:   root_statisticsbonussale  放射線組織獎金計算-營業獎金
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// ----------------------------------
// 本程式使用的 function
// ----------------------------------

// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
		$d = DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) == $date;
}


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

// 預設星期幾為預設 7 天的起始週期
$weekday = 	$rule['stats_weekday'];
// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['current_datepicker'])) {
  // 判斷格式資料是否正確
  if(validateDate($_GET['current_datepicker'], 'Y-m-d')) {
    $current_datepicker = $_GET['current_datepicker'];
    // 日期如果大於今天的話，就以今天當週為日期。
    if(strtotime($current_datepicker) <= strtotime(date("Y-m-d"))) {
      // default 抓取指定日期最近的星期三, 超過指定日期，的星期三.
      $current_datepicker = date('Y-m-d' ,strtotime("$weekday",strtotime($current_datepicker)));
    }else{
      // default 抓取最近的星期三
      $current_datepicker = date('Y-m-d' ,strtotime("$weekday"));
    }
  }else{
    // default 抓取最近的星期三
    $current_datepicker = date('Y-m-d' ,strtotime("$weekday"));
  }
}else{
  // php 格式的 2017-02-24
  // default 抓取本週最近的星期三
  $current_datepicker = date('Y-m-d' ,strtotime("$weekday"));
  // var_dump($current_datepicker);
}

//var_dump($current_datepicker);
//echo date('Y-m-d H:i:sP');
// 統計的期間時間 1-7  $rule['stats_bonus_days']
$stats_bonus_days         = $rule['stats_bonus_days']-1;
$current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -$stats_bonus_days day"));
//var_dump($current_datepicker_start);

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

// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------
if($action == 'member_bonusamount_paid' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
// ----------------------------------------------------------------------------
// 寫入付款欄位
// ----------------------------------------------------------------------------
  //var_dump($_GET);
  $id = $_GET['id'];

  $check_sql = "SELECT * FROM root_statisticsbonussale WHERE id = '$id';";
  $check_result = runSQLall($check_sql);
  //var_dump($check_result);
  if($check_result[0] == 1) {
    if($check_result[1]->member_bonusamount_paid == NULL OR ($check_result[1]->member_bonusamount_paid < $check_result[1]->member_bonusamount) ){
      $member_bonusamount_paid = $check_result[1]->member_bonusamount;
      $notes = "$id";

      $update_sql = "UPDATE root_statisticsbonussale SET
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
}elseif($action == 'reload_salelist' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -----------------------------------------------------------------------
  // datatable server process 用資料讀取
  // -----------------------------------------------------------------------

  // -----------------------------------------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -----------------------------------------------------------------------
  // 算 root_member 人數
  $userlist_sql_tmp = "SELECT * FROM root_statisticsbonussale
  WHERE dailydate_start = '$current_datepicker_start' AND dailydate_end = '$current_datepicker'";
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
    }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY perforaccount_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY perforaccount_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 7){ $sql_order = 'ORDER BY perforaccount_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 8){ $sql_order = 'ORDER BY perforaccount_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 9){ $sql_order = 'ORDER BY all_betsamount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 10){ $sql_order = 'ORDER BY perfor_bounsamount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 11){ $sql_order = 'ORDER BY perforbouns_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 12){ $sql_order = 'ORDER BY perforbouns_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 13){ $sql_order = 'ORDER BY perforbouns_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 14){ $sql_order = 'ORDER BY perforbouns_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 15){ $sql_order = 'ORDER BY perforbouns_root '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 16){ $sql_order = 'ORDER BY member_bonuscount_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 17){ $sql_order = 'ORDER BY member_profitamount_1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 18){ $sql_order = 'ORDER BY member_bonuscount_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 19){ $sql_order = 'ORDER BY member_profitamount_2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 20){ $sql_order = 'ORDER BY member_bonuscount_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 21){ $sql_order = 'ORDER BY member_profitamount_3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 22){ $sql_order = 'ORDER BY member_bonuscount_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 23){ $sql_order = 'ORDER BY member_profitamount_4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 24){ $sql_order = 'ORDER BY member_bonusamount_count '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 25){ $sql_order = 'ORDER BY member_bonusamount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 26){ $sql_order = 'ORDER BY member_bonusamount_paid '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 27){ $sql_order = 'ORDER BY member_bonusamount_paidtime '.$sql_order_dir;
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
			$b['id']                  = $userlist[$i]->id;
			// get data member id
			$b['member_id']           = $userlist[$i]->member_id;
			$b['member_account']      = $userlist[$i]->member_account;
			$b['member_therole']      = $userlist[$i]->member_therole;
			$b['member_parent_id']    = $userlist[$i]->member_parent_id;
			$b['updatetime']          = $userlist[$i]->updatetime;
			$b['member_level']        = $userlist[$i]->member_level;
			$b['skip_bonusinfo']      = $userlist[$i]->skip_bonusinfo;
			$skip_bonusinfo_count     = explode(":",$b['skip_bonusinfo']);
			//var_dump($skip_bonusinfo_count);  取得第一個字串，為跳過的代數
			$b['skip_agent_tree_count'] = $skip_bonusinfo_count[0];
			$b['dailydate_start']     = $userlist[$i]->dailydate_start;
			$b['dailydate_end']       = $userlist[$i]->dailydate_end;
			$b['perforaccount_1']     = $userlist[$i]->perforaccount_1;
			$b['perforaccount_2']     = $userlist[$i]->perforaccount_2;
			$b['perforaccount_3']     = $userlist[$i]->perforaccount_3;
			$b['perforaccount_4']     = $userlist[$i]->perforaccount_4;
			$b['all_betsamount']      = $userlist[$i]->all_betsamount;
			$b['all_betscount']       = $userlist[$i]->all_betscount;
			$b['perfor_bounsamount']  = $userlist[$i]->perfor_bounsamount;
			$b['perforbouns_1']       = $userlist[$i]->perforbouns_1;
			$b['perforbouns_2']       = $userlist[$i]->perforbouns_2;
			$b['perforbouns_3']       = $userlist[$i]->perforbouns_3;
			$b['perforbouns_4']       = $userlist[$i]->perforbouns_4;
			$b['perforbouns_root']    = $userlist[$i]->perforbouns_root;

			  // 個人從四層取得的資訊
			$b['member_bonusamount_1']  = $userlist[$i]->member_bonusamount_1;
			$b['member_bonuscount_1']   = $userlist[$i]->member_bonuscount_1;
			$b['member_bonusamount_2']  = $userlist[$i]->member_bonusamount_2;
			$b['member_bonuscount_2']   = $userlist[$i]->member_bonuscount_2;
			$b['member_bonusamount_3']  = $userlist[$i]->member_bonusamount_3;
			$b['member_bonuscount_3']   = $userlist[$i]->member_bonuscount_3;
			$b['member_bonusamount_4']  = $userlist[$i]->member_bonusamount_4;
			$b['member_bonuscount_4']   = $userlist[$i]->member_bonuscount_4;

			$b['member_bonusamount']            = $userlist[$i]->member_bonusamount;
			$b['member_bonusamount_count']      = $userlist[$i]->member_bonusamount_count;
			$b['member_bonusamount_paid']       = $userlist[$i]->member_bonusamount_paid;
			$b['member_bonusamount_paidtime']   = $userlist[$i]->member_bonusamount_paidtime;
			$b['notes']                         = $userlist[$i]->notes;

      // 付款資訊欄位, 包含動作處理 call 另外一個程式
			// 發放 member_bonusamount_paid
			$paid_notes = '发放'.$b['member_account'].'帐号'.$b['dailydate_start'].'的营业奖金'.round($b['member_bonusamount'],2);
			if($b['member_bonusamount'] > 0 AND $b['member_bonusamount_paid'] == NULL) {
				// 轉帳
				$member_bonusamount_paid_html = '<a href="member_depositgcash.php?a='.$b['member_id'].'&gcash='.round($b['member_bonusamount'],2).'&notes='.$paid_notes.'" title="立即进行转帐给'.$b['member_account'].'金额'.round($b['member_bonusamount'],2).'" target="_blank"><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></a>';
				// 寫入付款欄位資訊
				$member_bonusamount_paid_html = $member_bonusamount_paid_html.'&nbsp;&nbsp;<a href="bonus_commission_sale_action.php?a=member_bonusamount_paid&id='.$b['id'].'"  onclick="return confirm(\'请确认已经汇款完成了,再来更新此栏位 ??\')" title="金额写入这个栏位" target="_blank"><span class="glyphicon glyphicon-pencil" aria-hidden="true"></a>';
			}elseif($b['member_bonusamount'] == 0 AND ($b['member_bonusamount_paid'] == NULL OR $b['member_bonusamount_paid'] == 'n/a')) {
				$member_bonusamount_paid_html = '<a href="#" title="无须转帐">n/a</a>';
			}else{
				$member_bonusamount_paid_html = '<a href="bonus_commission_sale.php" title="已经发放了">'.round($b['member_bonusamount_paid']).'</a>';
			}

			// 把付款更新的時間, 改變一下呈現的格式. 避免太長的欄位
			// date("Y-m-d H:i:s",strtotime($b['member_profitamount_paidtime']))
			//var_dump($b['member_bonusamount_paidtime']);
			if($b['member_bonusamount_paidtime'] == NULL OR $b['member_bonusamount_paidtime'] == 'n/a') {
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
        'skip_agent_tree_count'=>$b['skip_agent_tree_count'],
        'skip_bonusinfo'=>$b['skip_bonusinfo'],
        'perforaccount_1'=>$b['perforaccount_1'],
        'perforaccount_2'=>$b['perforaccount_2'],
        'perforaccount_3'=>$b['perforaccount_3'],
        'perforaccount_4'=>$b['perforaccount_4'],
        'all_betscount'=>$b['all_betscount'],
        'all_betsamount'=>$b['all_betsamount'],
        'perfor_bounsamount'=>$b['perfor_bounsamount'],
        'perforbouns_1'=>$b['perforbouns_1'],
        'perforbouns_2'=>$b['perforbouns_2'],
        'perforbouns_3'=>$b['perforbouns_3'],
        'perforbouns_4'=>$b['perforbouns_4'],
        'perforbouns_root'=>$b['perforbouns_root'],
        'member_bonuscount_1'=>$b['member_bonuscount_1'],
        'member_bonusamount_1'=>$b['member_bonusamount_1'],
        'member_bonuscount_2'=>$b['member_bonuscount_2'],
        'member_bonusamount_2'=>$b['member_bonusamount_2'],
        'member_bonuscount_3'=>$b['member_bonuscount_3'],
        'member_bonusamount_3'=>$b['member_bonusamount_3'],
        'member_bonuscount_4'=>$b['member_bonuscount_4'],
        'member_bonusamount_4'=>$b['member_bonusamount_4'],
        'member_bonusamount_count'=>$b['member_bonusamount_count'],
        'member_bonusamount'=>$b['member_bonusamount'],
        'member_bonusamount_paid'=>$member_bonusamount_paid_html,
        'member_bonusamount_paidtime'=>$member_bonusamount_paidtime_html,
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
}elseif($action == 'bonus_update' AND isset($_GET['bonus_update_date']) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  if(validateDate($_GET['bonus_update_date'], 'Y-m-d')) {
    $dailydate = $_GET['bonus_update_date'];
    $file_key = sha1('salebonus'.$dailydate);
    $logfile_name = dirname(__FILE__) .'/tmp_dl/salebonus_'.$file_key.'.tmp';
    if(file_exists($logfile_name)) {
      die('請勿重覆操作');
    }else{
	    $command   = $config['PHPCLI'].' bonus_commission_sale_cmd.php run '.$dailydate.' web > '.$logfile_name.' &';
	    echo '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){window.location.href="'.$_SERVER['PHP_SELF'].'?a=update_reload&k='.$file_key.'"},1000);</script>';
	    $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},3000);</script>';
	    file_put_contents($logfile_name,$output_html);
	    passthru($command, $return_var);
		}
  }else{
    $output_html  = '日期格式有問題，請確定有且格式正確，需要為 YYYY-MM-DD 的格式';
    echo '<hr><br><br><p align="center">'.$output_html.'</p>';
    echo '<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';
  }
}elseif($action == 'salebonus_payout_update' AND isset($_GET['salebonus_payout_date']) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  if(validateDate($_GET['salebonus_payout_date'], 'Y-m-d') AND isset($_GET['s']) AND isset($_GET['s1']) AND isset($_GET['s2']) AND isset($_GET['s3']) ){
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
    $bonus_token = jwtenc('salebonuspayout', $bonusstatus_array);

    $dailydate = $_GET['salebonus_payout_date'];
    $file_key = sha1('salebonuspayout'.$dailydate);
    $logfile_name = dirname(__FILE__) .'/tmp_dl/salebonus_'.$file_key.'.tmp';
    if(file_exists($logfile_name)) {
      die('請勿重覆操作');
    }else{
      $command   = $config['PHPCLI'].' bonus_commission_sale_payout_cmd.php run '.$dailydate.' '.$bonus_token.' '.$_SESSION['agent']->account.' web > '.$logfile_name.' &';
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
    $reload_file = dirname(__FILE__) .'/tmp_dl/salebonus_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      echo file_get_contents($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'salebonus_del' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/salebonus_'.$logfile_sha.'.tmp';
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
