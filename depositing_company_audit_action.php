<?php
// ----------------------------------------------------------------------------
// Features: 銀行入款審核後台 - depositing_company_audit.php 的處理
// File Name: depositing_company_audit_action.php
// Author: Barkley
// Editor: Damocles
// Related: 後台的 depositing_company_audit.php
// Log: ※後台審核銀行入款對應前台的 deposit_company.php檔案
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";

// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";

// 通用的自訂函式庫
// require_once dirname(__FILE__) ."/lib.php";

// gcash lib 現金轉帳函式庫
require_once dirname(__FILE__) ."/gcash_lib.php";

// gtoken lib 代幣轉帳函式庫
require_once dirname(__FILE__) ."/gtoken_lib.php";

require_once dirname(__FILE__) ."/deposit_withdrawal_company_audit_lib.php";

if ( isset($_GET['a']) ) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
} else {
    die('(x)Invalid tests');
}

function validateDate($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// 交易代號
if ( isset($_GET['transaction_id']) && ($_GET['transaction_id'] != null) ) {
    $query_sql_array['transaction_id'] = filter_var($_GET['transaction_id'], FILTER_SANITIZE_STRING);
}

// 帳號
if ( isset($_GET['account']) && ($_GET['account'] != null) ) {
    $query_sql_array['account'] = filter_var($_GET['account'], FILTER_SANITIZE_STRING);
}

// 代理商
if ( isset($_GET['agent']) && ($_GET['agent'] != null) ) {
    $query_sql_array['agent'] = filter_var($_GET['agent'],FILTER_SANITIZE_STRING);
}

// 申請開始日期
if ( isset($_GET['sdate']) && ($_GET['sdate'] != null) ) {
    // 判斷格式資料是否正確
    if ( validateDate($_GET['sdate'], 'Y-m-d H:i') ) {
        $query_sql_array['query_date_start_datepicker'] = $_GET['sdate'];//.' 00:00:00';
        $query_sql_array['query_date_start_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u',strtotime($query_sql_array['query_date_start_datepicker'].'-04')+8*3600).'+08:00';
    }
}

// 申請結束日期
if ( isset($_GET['edate']) && ($_GET['edate'] != null) ) {
    // 判斷格式資料是否正確
    if ( validateDate($_GET['edate'], 'Y-m-d H:i') ) {
        $query_sql_array['query_date_end_datepicker'] = $_GET['edate'];//.' 23:59:59';
        $query_sql_array['query_date_end_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u',strtotime($query_sql_array['query_date_end_datepicker'].'-04')+8*3600).'+08:00';
    }
}

// 金額
if ( isset($_GET['amount_lower']) && ($_GET['amount_lower'] != null) ) {
    $query_sql_array['amount_lower'] = filter_var($_GET['amount_lower'], FILTER_SANITIZE_STRING);
}

if ( isset($_GET['amount_upper']) && ($_GET['amount_upper'] != null) ) {
    $query_sql_array['amount_upper'] = filter_var($_GET['amount_upper'], FILTER_SANITIZE_STRING);
}

// 審核狀態
if ( isset($_GET['status_qy']) && ($_GET['status_qy'] != null) ) {
    $query_sql_array['status_query'] = filter_var_array($_GET['status_qy'], FILTER_SANITIZE_STRING);
}

// IP
if ( isset($_GET['ip']) && ($_GET['ip'] != null) ) {
    if ( filter_var($_GET['ip'], FILTER_VALIDATE_IP) ) {
        $query_sql_array['ip_query'] = $_GET['ip'];
    }
}

// -------------------------------------------------------------------------
// datatable server process 分頁處理及驗證參數
// -------------------------------------------------------------------------
// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if ( isset($_GET['length']) && ($_GET['length'] != null) ) {
    $current_per_size = filter_var($_GET['length'], FILTER_VALIDATE_INT);
} else {
    $current_per_size = $page_config['datatables_pagelength'];
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if ( isset($_GET['start']) && ($_GET['start'] != null) ) {
    $current_page_no = filter_var($_GET['start'],FILTER_VALIDATE_INT);
} else {
    $current_page_no = 0;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if ( isset($_GET['_']) ) {
    $secho = $_GET['_'];
} else {
    $secho = '1';
}

// 產生查詢條件
function query_str($query_sql_array)
{
    $query_top = 0;
    $show_sql = '';

    // 今天
    $current_date_s = gmdate('Y-m-d',time() + -4*3600).' 00:00';
    $current_date_e = gmdate('Y-m-d',time() + -4*3600).' 23:59';

    // 代理商
    if( isset($query_sql_array['agent']) && ($query_sql_array['agent'] != null) ) {
        $agent_id_result = runSQLall(<<<SQL
            SELECT "id",
                   "account"
            FROM "root_member"
            WHERE ("therole" ='A' OR "therole" ='R')
            AND "account" = '{$query_sql_array["agent"]}'
        SQL);

        if ( isset($agent_id_result) && ($agent_id_result[0] == 1) ) {
            $show_sql .= "p_id = '{$agent_id_result[1]->id}'";
            $query_top = 1;
        } else {
            $logger = '无此帐号，错误代码：1907221501754。';
        }
    }

    // 帳號
    if ( isset($query_sql_array['account']) && ($query_sql_array['account'] != null) ) {
        if ($query_top == 1) {
            $show_sql .= 'AND';
        }
        $show_sql .= "account = '{$query_sql_array['account']}'";
        $query_top = 1;
    }

    // 交易單號
    if ( isset($query_sql_array['transaction_id']) && ($query_sql_array['transaction_id'] != null) ) {
        if ($query_top == 1) {
            $show_sql .= ' AND ';
        }
        $show_sql .= "transaction_id = '{$query_sql_array['transaction_id']}'";
        $query_top = 1;
    }

    // 開始時間
    if ( isset($query_sql_array['query_date_start_datepicker']) && ($query_sql_array['query_date_start_datepicker'] != null) ) {
        if ($query_top == 1) {
            $show_sql .= ' AND ';
        }
        $show_sql .= "changetime >= '{$query_sql_array['query_date_start_datepicker']} -04'";        
        $query_top = 1;
    } else {
        // 沒填，預設今日
        if ($query_top == 1) {
            $show_sql .= ' AND ';
        }
        $show_sql .= "changetime >= '{$current_date_s} -04'";        
        $query_top = 1;
    }

    // 結束時間
    if ( isset($query_sql_array['query_date_end_datepicker']) && ($query_sql_array['query_date_end_datepicker'] != null) ) {
        if ($query_top == 1) {
            $show_sql .= ' AND ';
        }
        $show_sql .= "changetime <= '{$query_sql_array['query_date_end_datepicker']} -04'";        
        $query_top = 1;

    } else {
        // 沒填，預設今日
        if ($query_top == 1) {
            $show_sql .= ' AND ';
        }
        $show_sql .= "changetime <= '{$current_date_e} -04'";        
        $query_top = 1;
    }

    // 金額
    if ( isset($query_sql_array['amount_lower']) && ($query_sql_array['amount_lower'] != null) ) {
        if ($query_top == 1) {
            $show_sql .= ' AND ';
        }
        $show_sql .= "amount >= '{$query_sql_array['amount_lower']}'";
    }
    if ( isset($query_sql_array['amount_upper']) && ($query_sql_array['amount_upper'] != null) ) {
        if ($query_top == 1) {
            $show_sql .= ' AND ';
        }
        $show_sql .= "amount <= '{$query_sql_array['amount_upper']}'";
    }

    // ip
    // if ( isset($query_sql_array['ip_query']) && ($query_sql_array['ip_query'] != null) ) {
    //     if ($query_top == 1) {
    //         $show_sql .= ' AND ';
    //     }
    //     $show_sql .= "applicationip = '{$query_sql_array['ip_query']}'";
    //     $query_top = 1;
    // }

    // 審核狀態
    // if ( isset($query_sql_array['status_query']) && ($query_sql_array['status_query'] != null) ) {
    //     if ($query_top == 1) {
    //         $show_sql .= ' AND ';
    //     } 
    //     $show_sql = 'status IN ( '.implode("," ,$query_sql_array['status_query']).')';
    //     // var_dump(implode("," ,$query_sql_array['status_query']));die();
    //     $query_top = 1;
    // }

    if ( isset($query_sql_array['status_query']) && ($query_sql_array['status_query'] != null) ) {
      if ($query_top == 1) {
          $show_sql .= ' AND ';
      } 
      $show_sql .= 'status IN ( '.implode("," ,$query_sql_array['status_query']).')';
      // var_dump(implode("," ,$query_sql_array['status_query']));die();
      $query_top = 1;
    }
    
    if ( ($query_top == 1) && !isset($logger) ) {
        $return_sql = ' AND '.$show_sql;
    } else if ( isset($logger) ) {
        $return_sql['logger'] = $logger;
    } else {
        $return_sql = '';
    }

    return $return_sql;
}

// 公司入款搜尋用
function sql_query(){
  $sql =<<<SQL
    select * from(
        select a.*,age(now(),a.changetime) as intervaltime,
        to_char((transfertime AT TIME ZONE 'posix/Etc/GMT-8'), 'YYYY-MM-DD HH24:MI:SS' ) as "transfertime_tz",
        to_char((a.changetime AT TIME ZONE 'posix/Etc/GMT+4'), 'YYYY-MM-DD HH24:MI:SS' ) as "changetime_tz",
        b.id as account_id,
        b.parent_id as p_id
        FROM root_deposit_review as a
        left join root_member as b
        on a.account = b.account) as t
        WHERE intervaltime < interval '2 years'
    SQL;
    return $sql;
}
// 計算差距計分鐘
function minDiff($startTime, $endTime) {
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    $timeDiff = $end - $start;
    // 天
    $hours = $timeDiff/(60*60);
    if($hours < 30) {
      $r = round($hours,1).'Hours';
    }else{
      // 大於 1 天,加入日
      $r  = round(($timeDiff/60/60/24),1).'days';
    }

    return $r;
}
// 時間差
function convert_to_fuzzy_time($times){
  global $tr;
  date_default_timezone_set('America/St_Thomas');
  $unix   = strtotime($times);
  $now    = time();
  $diff_sec   = $now - $unix;

  if($diff_sec < 60){
      $time   = $diff_sec;
      $unit   = $tr['Seconds ago'];
  }
  elseif($diff_sec < 3600){
      $time   = $diff_sec/60;
      $unit   = $tr['minutes ago'];
  }
  elseif($diff_sec < 86400){
      $time   = $diff_sec/3600;
      $unit   = $tr['hours ago'];
  }
  elseif($diff_sec < 2764800){
      $time   = $diff_sec/86400;
      $unit   = $tr['days ago'];
  }
  elseif($diff_sec < 31536000){
      $time   = $diff_sec/2592000;
      $unit   = $tr['months ago'];
  }
  else{
      $time   = $diff_sec/31536000;
      $unit   = $tr['years ago'];
  }

  return (int)$time .$unit;
}

// 初始化sql
function all_data()
{
    $list_sql = <<<SQL
        SELECT *
        FROM (
            SELECT "a".*,
                   age(now(),transfertime) as "intervaltime",
                   to_char((transfertime AT TIME ZONE 'posix/Etc/GMT-8'), 'YYYY-MM-DD HH24:MI:SS' ) as "transfertime_tz",
                   to_char((a.changetime AT TIME ZONE 'posix/Etc/GMT+4'), 'YYYY-MM-DD HH24:MI:SS' ) as "changetime_tz",
                   "b"."id" as "account_id",
                   "b"."parent_id" as "p_id"
            FROM "root_deposit_review" as "a"
            LEFT JOIN "root_member" as "b"
                ON "a"."account" = "b"."account"
        ) as "tt"
        WHERE "intervaltime" < interval '8 days'
    SQL;

    return $list_sql;
}

if ( isset($_SESSION['agent']) ) {
    $processing_note = "客服{$_SESSION['agent']->account}审核操作中...";
}

// ----------------------------------
// 動作為會員登入檢查, 只有 管理員 可以維護。
// ----------------------------------

// datatable初始化
if($action == 'get_init' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){

  $show_listrow_html = '';
  $show_list_array = array();
  // $tr['Cancel'] = '取消';
  // $tr['delete'] = '刪除';
  // $tr['agree'] = '同意';
  $review_agent_status[null]     = $tr['delete'];
  $review_agent_status[0]     = $tr['Cancel'];
  $review_agent_status[1]     = $tr['agree'];
  // 单号审核中
  // $tr['select function'] = '選擇功能';
  $review_agent_status[2]     = $tr['select function'];

  // 處理 datatables 傳來的排序需求
  if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
    $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
  }else{ $sql_order = 'ORDER BY id ASC';}

  //預設審核狀態為未審核
  $list_sql = all_data()." AND status IN ( 2) ".$sql_order;

   // 算資料總筆數
  $userlist_sql   = $list_sql.';';
   // echo $userlist_sql;die();
  $userlist_count = runSQL($userlist_sql);

  if($userlist_count != 0){
    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    // 所有紀錄數量
    $page['all_records']     = $userlist_count;
    // 每頁顯示多少
    $page['per_size']        = $current_per_size;
    // 目前所在頁數
    $page['no']              = $current_page_no;


    // 取出資料
    $user_sql  = $list_sql.' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
    $list = runSQLall($user_sql);

    // 從list內找出parent_id、depositcompanyid
    $to_get_data = to_array($list);

    foreach($to_get_data as $a1 => $v1){
      // 會員帳號查驗連結
      // $tr['Check membership details'] = '檢查會員的詳細資料';
      $member_check_html = '<a href="member_account.php?a='. $v1['account_id'].'" target="_BLANK" title="'.$tr['Check membership details'] .'">'.$v1['account'].'</a>';

      if($v1['status'] == '2'){
        // 单号审核中
        // $tr['agree'] = '同意';
        // $tr['Cancel'] = '取消';
        $confirm_status_html = '
        <button id="agreen_ok'.$v1['id'].'" class="btn btn-primary agreen_ok  mr-1 mb-1 btn-sm" role="button" value="'.$v1['id'].'">'.$tr['agree'].'</button>
        <button id="agreen_cancel'.$v1['id'].'" class="btn btn-danger  agreen_cancel btn-sm" role="button" value="'.$v1['id'].'">'.$tr['disagree'].'</button>
        ';
      }else if ($v1['status'] == '1'){
        // $tr['Approved'] = '已审核通过';
        $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-success btn-xs">'.$tr['Approved'].'</a>';
      }else if ($v1['status'] == '0'){
        // $tr['Cancel order number'] = '取消單號';
        $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs">'.$tr['Cancel order number'].'</a>';
      }else if ($v1['status'] == '3'){
        // 鎖定單號;
        $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-lock"><span></a>';
      }else{
        // $tr['delete a single number'] = '刪除單號';
        $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs">'.$tr['delete a single number'].'</a>';
      }

      // 對帳資資訊
      $reconciliation_notes_info = $v1['reconciliation_notes'];

      // 存款人對帳資資訊
      // $reconciliation_notes = $list[$i]->account.','.$list[$i]->depositoraccountname.','.$deposit_method.'<span class="glyphicon glyphicon-eye-open">';
      // IP 及指紋資訊
      $ip_fingerprint_info = <<<HTML
        <form target="_blank" action="member_log.php" method="post">
        <button class="btn btn-default btn-xs" data-id="check_{$v1['account']}" title="{$tr['check member login status']}">{$v1['applicationip']}</button>
          <input type="hidden" name="ip_query" value="{$v1['applicationip']}">
        </form>
HTML;
      // 人民幣格式
      // $amount_money = money_format('%i', $list[$i]->amount);
      // 加上  ＄ 格式, 小數點 2 位 number_format
      $amount_money = '$'.number_format(round($v1['amount'], 2),2);
      // 入款時間 , 計算相差幾分鐘
      // $time_text_desc = minDiff($v1['transfertime_tz'], date(DATE_RFC2822) );
      $deposit_time_text_desc = convert_to_fuzzy_time($v1['transfertime_tz']);
      $deposit_time_html = $v1['transfertime_tz'].'('.$deposit_time_text_desc.')';

      // 申請時間
      $application_time_text_desc = convert_to_fuzzy_time($v1['changetime_tz']);
      $application_time_html = $v1['changetime_tz'].'('.$application_time_text_desc.')';
      // $application_time_html = gmdate('Y-m-d H:i:s',strtotime($v1['changetime_tz'].'-12hours')-4*3600) .'('.$application_time_text_desc.')';

      // 入款 - 申請單號
      $depositing_id = '<a class="p-3 text-muted" href="./depositing_company_audit_review.php?id='.$v1['id'].'" role="button" target="_SELF" title="'.$tr['go to number'].$v1['id'].'"><i class="fas fa-angle-right"></i></a>';

      // -----------------------------------------------------------------------

      $deposit_type = explode('_', $v1['type'])[0];
        // 存款方式
        switch($deposit_type){
          case 'bank':
            $to_deposit_method = '<span class="label label-info">'.$tr['banking transfer'].'</span>';
            break;
          case 'wechat':
            $to_deposit_method = '<span class="label label-info">'.$tr['scan code payment'].'</span>';
            break;
          case 'virtualmoney':
            $to_deposit_method = '<span class="label label-info">'.$tr['virtual money payment'].'</span>';
            break;
          default:
            $to_deposit_method = '<span class="label label-info">'.$tr['error payment'].'</span>';
            break;
        }

        // 存款方式
        $deposit_type = <<<HTML
        <td id="{$v1['id']}">
          <p class="mb-1">{$to_deposit_method}</p>
          <p class="mb-1">{$v1['companyname']}</p>
          <p class="mb-0">{$v1['accountnumber']}</p>
        </td>
HTML;
        // 對帳資訊
        $bank_info_html=<<<HTML
          <td id="reconciliation_{$v1['id']}">
            <p class="mb-1"><span class="text-muted">{$tr['name']}</span> : {$v1['deposit_companyname']}</p>
            <p class="mb-0"><span class="text-muted">{$tr['For account name/ID']}</span> : {$v1['deposit_account_name']}</p>
          </td>
HTML;
        $show_list_array[] = array(
              'agent'=>$v1['parent'],
              'account'=>$member_check_html,
              'transaction_id'=>$v1['transaction_id'],
              'amount'=>$amount_money,
              'transfertime_tz'=>$application_time_html,
              'changetime_tz'=>$deposit_time_html,
              'deposit_type'=>$deposit_type,
              'bank_info'=> $bank_info_html,
              'reconciliation_notes'=>$reconciliation_notes_info,
              // 'applicationip'=>$ip_fingerprint_info,
              'status'=>$confirm_status_html,
              'detail'=>$depositing_id
        );
     }
     $output = array(
       "sEcho"                                 => intval($secho),
       "iTotalRecords"                 => intval($page['per_size']),
       "iTotalDisplayRecords"     => intval($page['all_records']),
       "data"                                     => $show_list_array
     );
   }else{
     // 搜尋區間沒資料
     $output = array(
      "sEcho"                                     => 0,
      "iTotalRecords"                     => 0,
      "iTotalDisplayRecords"         => 0,
      "data"                                         => ''
    );
   }
    echo json_encode($output);
} else if ( ($action == 'depositing_company_audit_submit') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {
    // 銀行入款審核頁面 -- 同意的處理動作 in depositing_company_audit_action.php
    global $config;

    // 取得 root_deposit_review 的 ID
    $depositing_id = filter_var($_POST['depositing_id'], FILTER_SANITIZE_NUMBER_INT);

    // 查詢 root_deposit_review 資料表, 找出銀行入款審核單
    $depositing_company_result = runSQLall(<<<SQL
        SELECT *
        FROM "root_deposit_review"
        WHERE "id" = '{$depositing_id}'
        AND "status" = '2';
    SQL);

    // 判斷單號審核資料
    if ($depositing_company_result[0] == 1) {
        // 鎖定銀行入款審核單
        $depositing_company_lock = runSQL(<<<SQL
            UPDATE "root_deposit_review"
            SET "status" = '3',
                "notes" = '{$processing_note}'
            WHERE "id" = '{$depositing_id}'
            AND "status" = '2';
        SQL);

        // 交易的類別分類：應用在所有的錢包相關程式內 , 定義在 system_config.php 內.
        // global $transaction_category;
        // 轉帳摘要 -- 現金存款(cashdeposit)
        $transaction_category_index = 'company_deposits';

        // 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
        $summary = $transaction_category[$transaction_category_index];

        // 操作者 ID
        $member_id = $_SESSION['agent']->id;

        // 轉帳來源帳號 -- 現金出納帳號 $gcash_cashier_account
        $source_transferaccount = $gcash_cashier_account;

        // 轉帳目標帳號
        $destination_transferaccount = $depositing_company_result[1]->account;

        // 來源帳號提款密碼 or 管理員登入的密碼
        // $withdrawal_pwd        = 'tran5566'; // 移到config

        // 轉帳金額
        $transaction_money = $depositing_company_result[1]->amount;

        // 實際存提
        $realcash = 1;

        // 系統轉帳文字資訊(補充)
        $system_note = $depositing_company_result[1]->type;

        // $debug = 1 --> 進入除錯模式 , debug = 0 --> 關閉除錯
        $debug = 0;

        // 公司入款交易單號，預設以 (w/d)20180515_useraccount_亂數3碼 為單號，其中 W 代表提款 D 代表存款
        $d_transaction_id = $depositing_company_result[1]->transaction_id;

        if ($protalsetting['member_deposit_currency'] == 'gtoken') {
            // 轉帳摘要 -- 現金存款(cashdeposit)
            $transaction_category_index = 'company_deposits';

            // 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
            $summary = $transaction_category[$transaction_category_index];

            // 轉帳來源帳號 -- 現金出納帳號 $gcash_cashier_account
            $source_transferaccount = $gtoken_cashier_account;

            $auditmode_select = 'depositaudit';

            $deposit_rate = function() use ($destination_transferaccount) {
                global $tr;

                $m_sql_result = runSQLall(<<<SQL
                    SELECT *
                    FROM "root_member"
                    WHERE ("account" = '{$destination_transferaccount}')
                    AND ("status" = '1');
                SQL);

                if ($m_sql_result[0] == 1) {
                    $grade_setting_result = runSQLall(<<<SQL
                        SELECT *
                        FROM "root_member_grade"
                        WHERE ("id" = '{$m_sql_result[1]->grade}');
                    SQL);

                    if ($grade_setting_result[0] != 1) {
                        // $tr['cash trnasfer token audit failure'] = '現金轉代幣稽核比查詢失敗。';
                        $logger = $tr['cash trnasfer token audit failure'];
                        $depositing_id = filter_var($_POST['depositing_id'], FILTER_SANITIZE_NUMBER_INT);
                        // 設回審察中
                        runSQL(<<<SQL
                            UPDATE root_deposit_review SET status='2',notes='".$logger."' WHERE id = ".$depositing_id." AND status='3';
                        SQL);

                        echo '<script>alert("'.$logger.'");</script>';
                        return;
                    }

                    $deposit_rate = $grade_setting_result[1]->deposit_rate;
                } else {
                  $m_sql_result = runSQLall(<<<SQL
                    SELECT *
                    FROM "root_member"
                    WHERE ("account" = '{$destination_transferaccount}')
                    AND ("status" = '2');
                SQL);
                    if ($m_sql_result[0] == 1) {
                      $logger = $tr['This account has been frozen, you need to unlock the wallet first to operate deposits and withdrawals'];
                    } else {
                      $tr['Member information query failed'] = '會員資訊查詢失敗。';
                      $logger = $tr['Member information query failed'] = $tr['this feature is currently unavailable for member status'];
                    }
                    $depositing_id = filter_var($_POST['depositing_id'], FILTER_SANITIZE_NUMBER_INT);
                    // 設回審察中
                    runSQL(<<<SQL
                        UPDATE "root_deposit_review"
                        SET "status" = '2',
                            "notes" = '{$logger}'
                        WHERE "id" = '{$depositing_id}'
                        AND "status" = '3';
                    SQL);

                    // echo '<script>alert("'.$logger.'");</script>';
                    return;
                }
                return $deposit_rate;
            };

            $auditmode_amount = round($transaction_money * ($deposit_rate() / 100), 2);

            // 執行轉帳 (member_gtoken_transfer在gtoken_lib.php內，會判斷轉入帳號的會員是否為首儲，並寫回跟root_member_gtokenpassbook同樣交易時間的首儲時間，這邊會跟首儲報表有關連)
            $error = member_gtoken_transfer(
                $member_id,
                $source_transferaccount,
                $destination_transferaccount,
                $transaction_money,
                $config['withdrawal_pwd'],
                $summary,
                $transaction_category_index,
                $realcash,
                $auditmode_select,
                $auditmode_amount,
                $system_note,
                $debug,
                $d_transaction_id,
                $_SESSION['agent']->account
            );
        } else {
            // 執行轉帳
            $error = member_gcash_transfer(
                $transaction_category_index,
                $summary,
                $member_id,
                $source_transferaccount,
                $destination_transferaccount,
                $config['withdrawal_pwd'],
                $transaction_money,
                $realcash,
                $system_note,
                $debug,
                $d_transaction_id,
                $_SESSION['agent']->account
            );
        }

        // 更新轉帳的單號狀態
        if ($error['code'] == 1) {
            $error["messages"] = $tr['Update Company Entry Number'].$tr['Success.'];
            $update_result = runSQLall(<<<SQL
                UPDATE "root_deposit_review"
                SET "status" = '1',
                    "processingaccount" = '{$_SESSION["agent"]->account}',
                    "processingtime" = NOW(),
                    "notes" = '{$error["messages"]}'
                WHERE "id" = '{$depositing_company_result[1]->id}'
                AND "status" = '3';
            SQL);

            if($update_result[0] == 1) {
                update_gcash_log_exist($depositing_company_result[1]->account);
                $error['code'] = '1';
                // $tr['Success.'] = '成功。';
                // $tr['Update Company Entry Number'] = '更新公司入款單號';
                $error['messages'] = $tr['Update Company Entry Number'].$depositing_company_result[1]->id.$tr['Success.'];
            } else {
                $error['code'] = '505';
                // $tr['error, please contact the developer for processing.'] = '錯誤,請聯絡開發人員處理。';
                $error['messages'] =  $tr['Update Company Entry Number'].$depositing_company_result[1]->id.$tr['error, please contact the developer for processing.'];
            }
        } else {
            // 不用處理, $error 回傳資訊已有除錯資訊
            // 設回審察中
            runSQL(<<<SQL
                UPDATE "root_deposit_review"
                SET "status" = '2',
                    "notes" = '{$error["messages"]}',
                    "processingaccount" = "{$_SESSION['agent']->account}", 
                    "processingtime" = now()
                WHERE "id" = '{$depositing_id}'
                AND "status" = '3';
            SQL);
        }
    } else {
        $error['code'] = '9';
        // $tr['The money order number is wrong, please check again.'] = '入款單號錯誤, 請重新檢查。';
        $error['messages'] =  $tr['The money order number is wrong, please check again.'];
    }

    // echo '<script>alert("'.$error['messages'].'");</script>';


    echo <<<HTML
        <script>
            alert("{$error['messages']}");
            location.reload();
        </script>
    HTML;

    // 結果
    if ($debug == 1) {
        var_dump($error);
    }
// -----------------------------------------------------------------------------
} else if ($action == 'depositing_company_audit_cancel' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  global $config;
// -----------------------------------------------------------------------------
  // 取得 root_deposit_review 的 ID
  $depositing_id = filter_var($_POST['depositing_id'], FILTER_SANITIZE_NUMBER_INT);
  // var_dump($depositing_id);die();

  // 查詢 root_deposit_review 資料表
  $depositing_company_sql = "
  SELECT *
  FROM root_deposit_review
  WHERE id = ".$depositing_id." AND status='2'; ";
  // var_dump($depositing_company_sql);exit;

  // 返回 depositing_array 資訊
  $depositing_company_result = runSQLall($depositing_company_sql);
  // var_dump($depositing_company_result);die();
  // 判斷單號審核資料
  if($depositing_company_result[0] == 1){
    // 鎖定銀行入款審核單
    $depositing_company_lock = runSQL("UPDATE root_deposit_review SET status='3',notes='".$processing_note."' WHERE id = ".$depositing_id." AND status='2';");
    // 交易的類別分類：應用在所有的錢包相關程式內 , 定義再 system_config.php 內.
    switch($protalsetting['member_deposit_currency'])
      {
        case 'gcash' :
            // 轉帳摘要 -- 現金存款(cashdeposit)
            $transaction_category_index   = 'reject_company_deposits';
            // 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
            $summary                      = $transaction_category[$transaction_category_index];
            // 操作者 ID
            $member_id                    = $_SESSION['agent']->id;
            // 轉帳來源帳號 -- 現金出納帳號 $gcash_cashier_account
            $source_transferaccount       = $gcash_cashier_account;
            // 轉帳目標帳號
            $destination_transferaccount  = $depositing_company_result[1]->account;
            // 來源帳號提款密碼 or 管理員登入的密碼
            // $withdrawal_pwd         = 'tran5566';  // 移到config

            // 轉帳金額
            $transaction_money            = '0';
            // 實際存提
            $realcash                     = 0;
            // 系統轉帳文字資訊(補充)
            $system_note                  = $depositing_company_result[1]->type;
            // $debug = 1 --> 進入除錯模式 , debug = 0 --> 關閉除錯
            $debug                        = 0;
            // 公司入款交易單號，預設以 (w/d)20180515_useraccount_亂數3碼 為單號，其中 W 代表提款 D 代表存款
            $d_transaction_id             = $depositing_company_result[1]->transaction_id;

            // 原本
            // $error = member_gcash_cancel_notice($transaction_category_index, $summary, $member_id, $source_transferaccount, $destination_transferaccount, $withdrawal_pwd , $transaction_money, $realcash, $system_note, $debug, $d_transaction_id,$_SESSION['agent']->account);

            // 20191017
            $error = member_gcash_cancel_notice($transaction_category_index, $summary, $member_id, $source_transferaccount, $destination_transferaccount, $config['withdrawal_pwd'] , $transaction_money, $realcash, $system_note, $debug, $d_transaction_id,$_SESSION['agent']->account);
            break;

        case 'gtoken' :
            // 轉帳摘要 -- $transaction_category['tokendeposit']  = '游戏币存款'
            $transaction_category_index   = 'reject_company_deposits';
            // 交易類別 , 定義再 system_config.php 內, 不可以隨意變更.
            $summary                      = $transaction_category[$transaction_category_index];
            // 操作者 ID
            $member_id                    = $_SESSION['agent']->id;
            // 轉帳來源帳號 -- gtoken出納帳號 $gtoken_cashier_account
            $source_transferaccount       = $gtoken_cashier_account;
            // 轉帳目標帳號
            $destination_transferaccount  = $depositing_company_result[1]->account;
            // 來源帳號提款密碼 or 管理員登入的密碼
            // $withdrawal_pwd         = 'tran5566';  // 移到config

            // 轉帳金額
            $transaction_money            = '0';
            // 實際存提
            $realcash                     = 0;
            // 系統轉帳文字資訊(補充)
            $system_note                  = $depositing_company_result[1]->type;
            // $debug = 1 --> 進入除錯模式 , debug = 0 --> 關閉除錯
            $debug                        = 0;
            // 公司入款交易單號，預設以 (w/d)20180515_useraccount_亂數3碼 為單號，其中 W 代表提款 D 代表存款
            $d_transaction_id            = $depositing_company_result[1]->transaction_id;

            // 原本
            // $error = member_gtoken_cancel_notice($transaction_category_index, $summary, $member_id, $source_transferaccount, $destination_transferaccount, $withdrawal_pwd , $transaction_money, $realcash, $system_note, $debug, $d_transaction_id,$_SESSION['agent']->account);

            // 20191017
            $error = member_gtoken_cancel_notice($transaction_category_index, $summary, $member_id, $source_transferaccount, $destination_transferaccount, $config['withdrawal_pwd'] , $transaction_money, $realcash, $system_note, $debug, $d_transaction_id,$_SESSION['agent']->account);
            break;
        default:
            echo '<script>alert("'.$tr['Background member deposit account: cash, game currency, setting error'].'");</script>';
            // echo '<script>alert("后台会员入款帐户：现金、游戏币，设定错误");</script>';
            break;
      }




    //取消存款寫到哪個資料表gcash or gtoken
    // $deposit_datatable='member_'.$protalsetting['member_deposit_currency'].'_cancel_notice';
    // var_dump($deposit_datatable);die();



    // 更新轉帳的單號狀態
    if($error['code'] == 1) {
      $error['messages'] = $tr['Cancel Deposit Requisition Number'].$tr['Success.'];
      $update_sql = "UPDATE root_deposit_review SET status = '0', processingaccount = '".$_SESSION['agent']->account."', processingtime = now(),notes='".$error['messages']."' WHERE id = '".$depositing_company_result[1]->id."' AND status='3'; ";
      $update_result = runSQLall($update_sql);

      if($update_result[0] == 1) {
        $error['code'] = '1';
        // $tr['Cancel Deposit Requisition Number'] = '取消入款申請單號';
        // $tr['Success.'] = '成功。';
        $error['messages'] = $tr['Cancel Deposit Requisition Number'].$depositing_company_result[1]->id.$tr['Success.'];
        // echo '<p align="center"><button type="button" class="btn btn-success" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
      }else{
        $error['code'] = '505';
        // $tr['error, please contact the developer for processing.'] = '錯誤, 請聯絡開發人員處理。';
        $error['messages'] =  $tr['Cancel Deposit Requisition Number'].$depositing_company_result[1]->id.$tr['error, please contact the developer for processing.'];
        // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
      }

      // echo '<script>alert("'.$error['messages'].'");</script>';
    }else{
      // 設回審察中
      runSQL("UPDATE root_deposit_review SET status='2',notes='".$error['messages']."' WHERE id = ".$depositing_id." AND status='3';");

      // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$error['messages'].'</button></p>';
    }

    echo <<<HTML
        <script>
            alert("{$error['messages']}");
            location.reload();
        </script>
    HTML;
  }else{
    // $tr['This is the current entry number'] = '目前此入款單號';
    // $tr['has been dealt with, do not re-operation.'] = '已處理過，請勿重新操作處理。';
    $logger = '(x)'.$tr['This is the current entry number'].''.$depositing_company_result[1]->id. $tr['has been dealt with, do not re-operation.'];
    memberlog2db($_SESSION['agent']->account,'withdrawal','notice', "$logger");
    // echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$logger.'</button></p>';
    echo '<script>alert("'.$logger.'");</script>';
  }


// -----------------------------------------------------------------------------
}else if($action == 'notes_common_update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // -----------------------------------------------------------------------------
//  var_dump($_POST);

  //更新处理资讯的 notes
  $depositing_company_review_id = filter_var($_POST['depositing_company_review_id'], FILTER_SANITIZE_NUMBER_INT);
  $depositing_company_notes = filter_var($_POST['depositing_company_notes'], FILTER_SANITIZE_STRING);

  // 更新 root_agent_review 「notes」
  $review_sql = "UPDATE root_deposit_review SET processingaccount = '".$_SESSION['agent']->account."', notes = '".$depositing_company_notes."', processingtime = NOW() WHERE id = '".$depositing_company_review_id."';";
//  var_dump($review_sql);
  $review_result = runSQL($review_sql);
//  var_dump($review_result);

  if($review_result == 1){
    // 更新 notes
    // $tr['The content is done'] = '內容完成。';
    // $tr['Update Processing Ticket Number'] = '更新處理資訊單號';
    $logger = $tr['Update Processing Ticket Number'].$depositing_company_review_id.$tr['The content is done'] ;
    echo '<p align="center"><button type="button" class="btn btn-success" onclick="window.location.reload();">'.$logger.'</button></p>';
  }else{
    // 系統錯誤
    // $tr['error, please contact the developer for processing.'] = '錯誤,請聯絡開發人員處理。';
    $logger = $tr['Update Processing Ticket Number'].$depositing_company_review_id.$tr['error, please contact the developer for processing.'] ;
    echo '<p align="center"><button type="button" class="btn btn-danger" onclick="window.location.reload();">'.$logger.'</button></p>';
  }



// -----------------------------------------------------------------------------
}elseif($action == 'get_result' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R'){
  // 搜尋

  if(isset($query_sql_array)){
    // 處理 datatables 傳來的排序需求
    // if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
    //   if($_GET['order'][0]['dir'] == 'asc'){ $sql_order_dir = 'ASC';
    //   }else{ $sql_order_dir = 'DESC';}
    //   if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY id '.$sql_order_dir;
    //   }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY account '.$sql_order_dir;
    //   }elseif($_GET['order'][0]['column'] == 2){ $sql_order = 'ORDER BY transaction_id '.$sql_order_dir;
    //   }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY transfertime '.$sql_order_dir;
    //   }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY amount '.$sql_order_dir;
    //   // }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY fingerprinting_id '.$sql_order_dir;
    //   }else{ $sql_order = 'ORDER BY id ASC';}
    // }else{ $sql_order = 'ORDER BY id ASC';}

    // 處理 datatables 傳來的排序需求
    if(isset($_GET['order'][0]) AND $_GET['order'][0]['column'] != ''){
      $sql_order_dir = ($_GET['order'][0]['dir'] == 'asc')? 'ASC':'DESC';
      $sql_order = 'ORDER BY '.$_GET['columns'][$_GET['order'][0]['column']]['data'].' '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY id ASC';}

    // 取得查詢條件
    $query_str = query_str($query_sql_array);
    // echo $query_str;die();
    if(isset($query_str['logger'])){
      // $output = array('logger' => $query_str['logger']);
      $output = array(
        "sEcho"                                     => 0,
        "iTotalRecords"                     => 0,
        "iTotalDisplayRecords"         => 0,
        "data"                                         => ''
      );
      echo json_encode($output);die();
      // echo '<script>alert("'.$query_str['logger'].'");</script>';die();
    }

    $sql_tmp  = sql_query().$query_str." ".$sql_order;//.$sql_order;
    //echo($sql_tmp);die();

    // 算資料總筆數
    $userlist_sql   = $sql_tmp.';';
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

    if($userlist_count != 0){

      // 取出資料
      $userlist_sql   = $sql_tmp.' OFFSET '.$page['no'].' LIMIT '.$page['per_size'].';';
      $userlist = runSQLall($userlist_sql);
      // var_dump($userlist_sql);die();

      $to_get_data = to_array($userlist);
      // var_dump($to_get_data);die();

      $show_listrow_html = '';
      $show = '';
      foreach($to_get_data as $a1 => $v1){
        // 會員帳號查驗連結
        // $tr['Check membership details'] = '檢查會員的詳細資料';
        $member_check_html = '<a href="member_account.php?a='. $v1['account_id'].'" target="_BLANK" title="'.$tr['Check membership details'] .'">'.$v1['account'].'</a>';

        if($v1['status'] == '2'){
          // 单号审核中
          $confirm_status_html = '
          <button id="agreen_ok'.$v1['id'].'" class="btn btn-primary agreen_ok  mr-1 mb-1 btn-sm" role="button" value="'.$v1['id'].'">'.$tr['agree'].'</button>
          <button id="agreen_cancel'.$v1['id'].'" class="btn btn-danger  agreen_cancel btn-sm" role="button" value="'.$v1['id'].'">'.$tr['disagree'].'</button>
          ';
        }else if ($v1['status'] == '1'){
          // $tr['Approved'] = '已审核通过';
          $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-success btn-xs">'.$tr['Approved'].'</a>';
        }else if ($v1['status'] == '0'){
          // $tr['Cancel order number'] = '取消單號';
          $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs">'.$tr['Cancel order number'].'</a>';
        }else if ($v1['status'] == '3'){
          // 鎖定單號;
          $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-lock"><span></a>';
        }else{
          // $tr['delete a single number'] = '刪除單號';
          $confirm_status_html = '<a href="./depositing_company_audit_review.php?id='.$v1['id'].'"" class="btn btn-danger btn-xs">'.$tr['delete a single number'].'</a>';
        }

        // 對帳資資訊
        $reconciliation_notes_info = $v1['reconciliation_notes'];

        // 存款人對帳資資訊
        // $reconciliation_notes = $list[$i]->account.','.$list[$i]->depositoraccountname.','.$deposit_method.'<span class="glyphicon glyphicon-eye-open">';
        // IP 及指紋資訊
        $ip_fingerprint_info = <<<HTML
          <form target="_blank" action="member_log.php" method="post">
          <button class="btn btn-default btn-xs" data-id="check_{$v1['account']}" title="{$tr['check member login status']}">{$v1['applicationip']}</button>
            <input type="hidden" name="ip_query" value="{$v1['applicationip']}">
          </form>
    HTML;
        // 人民幣格式
        // $amount_money = money_format('%i', $list[$i]->amount);
        // 加上  ＄ 格式, 小數點 2 位 number_format
        $amount_money = '$'.number_format(round($v1['amount'], 2),2);
        // 入款時間 , 計算相差幾分鐘
        // $time_text_desc = minDiff($v1['transfertime_tz'], date(DATE_RFC2822) );
        $deposit_time_text_desc = convert_to_fuzzy_time($v1['transfertime_tz']);
        $deposit_time_html = $v1['transfertime_tz'].'('.$deposit_time_text_desc.')';
        
        // 申請時間
        $application_time_text_desc = convert_to_fuzzy_time($v1['changetime_tz']);
        $application_time_html = $v1['changetime_tz'].'('.$application_time_text_desc.')';
        // $application_time_html = gmdate('Y-m-d H:i:s',strtotime($v1['changetime_tz'].'-12hours')+4*3600) .'('.$application_time_text_desc.')';
        
        // 入款 - 申請單號
        $depositing_id = '<a class="p-3 text-muted" href="./depositing_company_audit_review.php?id='.$v1['id'].'" role="button" target="_SELF" title="'.$tr['go to number'].$v1['id'].'"><i class="fas fa-angle-right"></i></a>';

        // -----------------------------------------------------------------------

        $deposit_type = explode('_', $v1['type'])[0];
          // 存款方式
          switch($deposit_type){
            case 'bank':
              $to_deposit_method = '<span class="label label-info">'.$tr['banking transfer'].'</span>';
              break;
            case 'wechat':
              $to_deposit_method = '<span class="label label-info">'.$tr['scan code payment'].'</span>';
              break;
            case 'virtualmoney':
              $to_deposit_method = '<span class="label label-info">'.$tr['virtual money payment'].'</span>';
              break;
            default:
              $to_deposit_method = '<span class="label label-info">'.$tr['error payment'].'</span>';
              break;
          }

          // 存款方式
          $deposit_type = <<<HTML
            <td id="{$v1['id']}">
              <p class="mb-1">{$to_deposit_method}</p>
              <p class="mb-1">{$v1['companyname']}</p>
              <p class="mb-0">{$v1['accountnumber']}</p>
            </td>
    HTML;
         // 對帳資訊
          $bank_info_html=<<<HTML
            <td id="reconciliation_{$v1['id']}">
              <p class="mb-1"><span class="text-muted">{$tr['name']}</span> : {$v1['deposit_companyname']}</p>
              <p class="mb-0"><span class="text-muted">{$tr['For account name/ID']}</span> : {$v1['deposit_account_name']}</p>
            </td>
    HTML;

          $show_list_array[] = array(
            'agent'=>$v1['parent'],
            'account'=>$member_check_html,
            'transaction_id'=>$v1['transaction_id'],
            'amount'=>$amount_money,
            'transfertime_tz'=>$application_time_html,
            'changetime_tz'=>$deposit_time_html,
            'deposit_type'=>$deposit_type,
            'bank_info'=> $bank_info_html,
            'reconciliation_notes'=>$reconciliation_notes_info,
            // 'applicationip'=>$ip_fingerprint_info,
            'status'=>$confirm_status_html,
            'detail'=>$depositing_id
          );
        };
        $output = array(
          "sEcho" => intval($secho),
          "iTotalRecords" => intval($page['per_size']),
          "iTotalDisplayRecords" => intval($page['all_records']),
          "data" => $show_list_array
        );

    }else{
      // 搜尋區間沒資料
      $output = array(
        "sEcho"                                     => 0,
        "iTotalRecords"                     => 0,
        "iTotalDisplayRecords"         => 0,
        "data"                                         => ''
      );
    }
  }else{
    // 搜尋區間沒資料
    $output = array(
      "sEcho"                                     => 0,
      "iTotalRecords"                     => 0,
      "iTotalDisplayRecords"         => 0,
      "data"                                         => ''
    );
  }

  echo json_encode($output);
}else{
// -----------------------------------------------------------------------------
// $tr['only management and login mamber'] = '(x) 只有管理員或有權限的會員才可以登入觀看。';
  $logger = $tr['only management and login mamber'];
//  echo '<script type="text/javascript">alert("'.$logger.'");location.href="./depositing_company_audit.php"</script>';die();
}
// -----------------------------------------------------------------------------
?>
