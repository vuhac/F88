<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織股利計算 -- action
// File Name:	bonus_commission_dividendreference_action.php
// Author:    Barkley
// Modifier：Damocles
// Related:   DB root_statisticsdailypreferential
// Log:
// 參考每日報表, 結算一年時間範圍的股利等級.
// 將會員等級分為 A, B, C 三個等級, 變數參考
// 1.會員第1代的代理商人數
// 2.會員第1代的會員人數
// 3.會員年度累計投注量
// 4.會員年度累計損益貢獻量
// 5.其他為定義
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

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------
// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);

$debug = 0;

if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}
else{
    die('(x)不合法的測試');
}

if(isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if(isset($_GET['sdate']) AND isset($_GET['edate'])) {
  // 轉換為美東的時間 date
  $current_datepicker = gmdate('Y-m-d',time() + -4*3600);

  // 判斷格式資料是否正確, 不正確以今天的美東時間為主
  $current_sdatepicker = validateDate($_GET['sdate'], 'Y-m-d');
  $current_edatepicker = validateDate($_GET['edate'], 'Y-m-d');
  //var_dump($current_datepicker);
  if($current_sdatepicker AND $current_edatepicker) {
    $current_datepicker_start = $_GET['sdate'];
    $current_datepicker_end = $_GET['edate'];
    if($current_datepicker_end < $current_datepicker){
      $current_datepicker = $current_datepicker_end;
    }
  }else{
    // 統計的期間時間 $rule['stats_commission_days'] 參考次變數
    $stats_commission_days = $rule['stats_commission_days'] - 1;
    $current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -$stats_commission_days day"));
  }
}else{
  // 轉換為美東的時間 date
  $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
  // 統計的期間時間 $rule['stats_commission_days'] 參考次變數
  $stats_commission_days = $rule['stats_commission_days'] - 1;
  $current_datepicker_start = date( "Y-m-d", strtotime( "$current_datepicker -$stats_commission_days day"));
}
//var_dump($current_datepicker);
//echo date('Y-m-d H:i:sP');


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

if(isset($_GET['setting'])){
  $dividendreference_setting = filter_var($_GET['setting'],FILTER_VALIDATE_INT);
}else{
  $dividendreference_setting = '';
}
// ----------------------------------
// 本程式使用的 function
// ----------------------------------

// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS
// ---------------------------------------------------------------------------
function indexmenu_stats_switch() { // 僅選擇已確版的紀錄
    // 選單表單
    $indexmenu_list_data = '';
    $dividen_day_record_sql = <<<SQL
        SELECT id,
               totaldividen,
               dailydate_end,
               dailydate_start,
               updatetime
        FROM root_dividendreference_setting
        WHERE (setted = '1')
        ORDER BY id DESC;
    SQL;
    $dividen_day_record_result = runSQLall($dividen_day_record_sql);

    // 建立已設定股利分配的時間區間清單
    if($dividen_day_record_result[0] >= 1){
        for($i=1; $i <= $dividen_day_record_result[0]; $i++){
            $record['id'] = $dividen_day_record_result[$i]->id;
            $record['date_range'] = $dividen_day_record_result[$i]->dailydate_start.' ~ '.$dividen_day_record_result[$i]->dailydate_end;
            $record['updatetime'] = $dividen_day_record_result[$i]->updatetime;
            $record['totaldividen'] = $dividen_day_record_result[$i]->totaldividen;

            $data_count_sql = <<<SQL
                SELECT *
                FROM root_dividendreference
                WHERE (dividendreference_setting_id = '{$record["id"]}');
            SQL;
            $data_count_result = runSQL($data_count_sql);
            $record['data_count'] = $data_count_result;

            $get_dividen_count_sql = <<<SQL
                SELECT *
                FROM root_dividendreference
                WHERE (dividendreference_setting_id = '{$record["id"]}') AND
                      (member_dividend_assigned != '0');
            SQL;
            $get_dividen_count_result = runSQL($get_dividen_count_sql);
            $record['get_dividen_count'] = $get_dividen_count_result;

            $indexmenu_list_data .= <<<HTML
                <tr>
                    <td>
                        <a href="bonus_commission_dividendreference.php?a={$record['id']}">{$record['date_range']}</a>
                    </td>
                    <td>{$record['data_count']}</td>
                    <td>{$record['get_dividen_count']}</td>
                    <td>{$record['totaldividen']}</td>
                    <td>{$record['updatetime']}</td>
                </tr>
            HTML;
        } // end for
    }

    return($indexmenu_list_data);
}

// ----------------------------------
// 更新股利發放試算結果(使用資料表-root_dividendreference，要等執行過root_dividendreference_cmd.php過後才會有資料)
// ----------------------------------
function update_dividen($totaldividen, $dividendreference_setting, $filtera, $debug=0){
    $query_sql = <<<SQL
        WHERE (dividendreference_setting_id = '{$dividendreference_setting}') AND
              (member_dividend_level = 'N/A')
    SQL;
    $filtera_array = [
        'x1' => 'member_l1_agentcount',
        'x2' => 'member_l1_membercount',
        'x3' => 'member_l1_agentsum_allbets',
        'x4' => 'member_sum_all_bets',
        'x5' => 'member_sum_all_profitlost'
    ];
    foreach( $filtera_array as $key=>$val ){
        if( isset($filtera[$key]) && !empty($filtera[$key]) ){
            $query_sql .= <<<SQL
                AND {$val} >= '{$filtera[$key]}'
            SQL;
        }
    } // end foreach

    // 查詢有多少會員符合資格
    $dividen_member_count_sql = <<<SQL
        SELECT *
        FROM root_dividendreference
        {$query_sql};
    SQL;
    $dividen_member_count_result = runSQL( $dividen_member_count_sql );

    if( $dividen_member_count_result > 0 ){
        $return_arr['membercount'] = $dividen_member_count_result;

        // 依會員數及發放比例計算發放額度
        $return_arr['dividen'] = floor(($totaldividen*$filtera['dividend']) / 100 / $return_arr['membercount']);

        // 進行更新
        $update_dividen_sql = <<<SQL
            UPDATE root_dividendreference
            SET member_dividend_level = '{$filtera["dividend_level"]}',
                member_dividend_assigned = '{$return_arr["dividen"]}',
                updatetime = now()
            WHERE id IN (
                SELECT id
                FROM root_dividendreference
                {$query_sql}
            );
        SQL;
        $update_dividen_result = runSQLall($update_dividen_sql);
    }
    else{
        $return_arr['membercount'] = 0;
        // 依會員數及發放比例計算發放額度
        $return_arr['dividen'] = 0;
    }

    if($debug == 1){
        var_dump($dividen_member_count_sql);
        var_dump($dividen_member_count_result);
        var_dump($update_dividen_sql);
        var_dump($update_dividen_result);
        var_dump($return_arr);
    }
    return $return_arr;
} // end update_dividen


// -------------------------------------------------------------------------
// $_GET 取得日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
} // end validateDate

// ----------------------------------
// 動作為會員登入檢查 MAIN
// ----------------------------------


if($action == 'reload_memberlist' AND $dividendreference_setting != '' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // -------------------------------------
  // 列出所有的會員資料及人數 SQL
  // -------------------------------------
  // 算 root_member 人數
  $userlist_sql   = "SELECT * FROM root_dividendreference
  WHERE dividendreference_setting_id = '".$dividendreference_setting."';";
  // var_dump($userlist_sql);
  $userlist_count = runSQL($userlist_sql);

  // -------------------------------------
  // 分頁處理機制
  // -------------------------------------
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
    if($_GET['order'][0]['column'] == 0){ $sql_order = 'ORDER BY memberid '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 1){ $sql_order = 'ORDER BY member_account '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 3){ $sql_order = 'ORDER BY member_parentid '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 4){ $sql_order = 'ORDER BY member_level '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 5){ $sql_order = 'ORDER BY member_level1 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 6){ $sql_order = 'ORDER BY member_level2 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 7){ $sql_order = 'ORDER BY member_level3 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 8){ $sql_order = 'ORDER BY member_level4 '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 9){ $sql_order = 'ORDER BY member_l1_agentcount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 10){ $sql_order = 'ORDER BY member_l1_agentsum_allbets '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 11){ $sql_order = 'ORDER BY member_l1_membercount '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 12){ $sql_order = 'ORDER BY member_sum_all_bets '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 13){ $sql_order = 'ORDER BY member_sum_all_profitlost '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 14){ $sql_order = 'ORDER BY member_sum_all_count '.$sql_order_dir;
    }elseif($_GET['order'][0]['column'] == 15 OR $_GET['order'][0]['column'] == 16){ $sql_order = 'ORDER BY member_dividend_level '.$sql_order_dir;
    }else{ $sql_order = 'ORDER BY memberid ASC';}
  }else{ $sql_order = 'ORDER BY memberid ASC';}
  // 取出 root_member 資料
  $userlist_sql   = "SELECT * FROM root_dividendreference
  WHERE dividendreference_setting_id = '".$dividendreference_setting."' ".$sql_order."
   OFFSET ".$page['no']." LIMIT ".$page['per_size']."  ;";
  // var_dump($userlist_sql);
  $userlist = runSQLall($userlist_sql);

  // 存放列表的 html -- 表格 row -- tables DATA
  $show_listrow_html = '';
  // 判斷 root_member count 數量大於 1
  if($userlist[0] >= 1) {
    // 以會員為主要 key 依序列出每個會員的貢獻金額
    for($i = 1 ; $i <= $userlist[0]; $i++){
      // 抓出其中一個人的資料一筆
      $member_dividend = money_format('%i', $userlist[$i]->member_dividend_assigned);

      $b['member_id']         = $userlist[$i]->memberid;
      $b['member_account']    = $userlist[$i]->member_account;
      $b['member_therole']    = $userlist[$i]->member_therole;
      $b['member_parent_id']  = $userlist[$i]->member_parentid;
      $b['member_level']      = $userlist[$i]->member_level; // 會員的所在層數
      $b['member_level_1']    = $userlist[$i]->member_level1; // 上 1 層
      $b['member_level_2']    = $userlist[$i]->member_level2; // 上 2 層
      $b['member_level_3']    = $userlist[$i]->member_level3; // 上 3 層
      $b['member_level_4']    = $userlist[$i]->member_level4; // 上 4 層
      $b['member_1_agent_count'] = $userlist[$i]->member_l1_agentcount; // 會員的下線代理商有多少人
      $b['member_1_agentsum_allbets'] = $userlist[$i]->member_l2_agentsum_allbets; // 會員的下線代理商有多少人
      $b['member_1_member_count'] = $userlist[$i]->member_l1_membercount; // 會員的下線會員有多少人
      $b['sum_all_bets'] = $userlist[$i]->member_sum_all_bets; // 本年度投注量
      $b['sum_all_profitlost'] = $userlist[$i]->member_sum_all_profitlost; // 本年度盈虧
      $b['sum_all_count'] = $userlist[$i]->member_sum_all_count; // 本年度注單量
      $b['dividend_level'] = $userlist[$i]->member_dividend_level; // 本年度注單量
      $b['dividend_assigned'] = $member_dividend; // 本年度注單量
      $b['note'] = ( ($userlist[$i]->member_dividend_level=='X') ? '无' : $userlist[$i]->note); // 本年度注單量

      // 顯示的表格資料內容
      $show_listrow_array[] = array(
      'id'=>$b['member_id'],
      'account'=>$b['member_account'],
      'therole'=>$b['member_therole'],
      'parent_id'=>$b['member_parent_id'],
      'member_level'=>$b['member_level'],
      'member_level_1'=>$b['member_level_1'],
      'member_level_2'=>$b['member_level_2'],
      'member_level_3'=>$b['member_level_3'],
      'member_level_4'=>$b['member_level_4'],
      'member_1_agent_count'=>$b['member_1_agent_count'],
      'member_1_agent_all_bets'=>$b['member_1_agentsum_allbets'],
      'member_1_member_count'=>$b['member_1_member_count'],
      'sum_all_bets'=>$b['sum_all_bets'],
      'sum_all_profitlost'=>$b['sum_all_profitlost'],
      'sum_all_count'=>$b['sum_all_count'],
      'dividend_level'=>$b['dividend_level'],
      'dividend_assigned'=>$b['dividend_assigned'],
      'note'=>$b['note']);
    }
    $output = array(
      "sEcho" => intval($secho),
      "iTotalRecords" => intval($page['per_size']),
      "iTotalDisplayRecords" => intval($userlist_count),
      "data" => $show_listrow_array
    );
    // ------------------------------------------------------
    // 表格資料 row list , end for loop
    // ------------------------------------------------------
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
// ----------------------------------------------------------------------------
}
// 送出日期區間後，執行的操作
else if( ($action == 'checkstate') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ){
    setlocale(LC_MONETARY, 'en_US');
    // 並將建立後的ID取出丟給 bonus_commission_dividendreference_cmd.php 去建立分級用資料
    // 以日期區間去檢查是否已有資料
    $check_dividendreference_setting_id_sql = <<<SQL
        SELECT id,
               setted
        FROM root_dividendreference_setting
        WHERE (dailydate_start = '{$current_datepicker_start}') AND
              (dailydate_end = '{$current_datepicker}')
        LIMIT 1;
    SQL;
    $check_dividendreference_setting_id_result = runSQLall( $check_dividendreference_setting_id_sql );

    // 沒有該筆資料則新增一筆
    if( $check_dividendreference_setting_id_result[0] == 0 ){
        $create_dividendreference_setting_sql = <<<SQL
            INSERT INTO root_dividendreference_setting ( dailydate_start, dailydate_end, updatetime ) VALUES
            ( '{$current_datepicker_start}', '{$current_datepicker}', now() );
        SQL;
        $create_dividendreference_setting_result = runSQLall( $create_dividendreference_setting_sql );

        // 新增完後再查詢該筆資料
        $get_dividendreference_setting_id_sql = <<<SQL
            SELECT id,
                   setted
            FROM root_dividendreference_setting
            WHERE (dailydate_start = '{$current_datepicker_start}') AND
                  (dailydate_end = '{$current_datepicker}')
            LIMIT 1;
        SQL;
        $get_dividendreference_setting_id_result = runSQLall( $get_dividendreference_setting_id_sql );

        // 資料新增成功，回傳該筆資料編號與資料狀態碼
        if( $get_dividendreference_setting_id_result[0] == 1 ){
            $dividendreference_setting = $get_dividendreference_setting_id_result[1]->id;
            $dividendreference_status = $get_dividendreference_setting_id_result[1]->setted;
            // echo '<pre>', var_dump( $get_dividendreference_setting_id_result ), '</pre>';
            $return_arr = [
                'status' => $dividendreference_status,
                'setting' => $dividendreference_setting
            ];
        }
        // 資料新增錯誤，如持續發生請聯絡網站管理員！
        else{
            @$return_arr = [
                'setting' => 0,
                'msg' => '資料新增錯誤，如持續發生請聯絡網站管理員！'
            ];
            die( json_encode($return_arr) );
        } // echo '<pre>', var_dump( $return_arr ), '</pre>';
    }
    // 有該筆資料則回傳該筆資料
    else if( $check_dividendreference_setting_id_result[0] == 1 ){
        $dividendreference_setting = $check_dividendreference_setting_id_result[1]->id;
        $dividendreference_status = $check_dividendreference_setting_id_result[1]->setted;
        $return_arr = [
            'status' => $dividendreference_status,
            'setting' => $dividendreference_setting
        ];
    }
    // 發生預期之外的錯誤，如持續發生請聯絡網站管理員！
    else{
        $return_arr = [
            'setting' => 0,
            'msg' => '發生預期之外的錯誤，如持續發生請聯絡網站管理員！'
        ];
        die( json_encode($return_arr) );
    }

    // 該筆資料不是已確版也不是錯誤發生時，產生相對應的tmp檔案
    if( $dividendreference_status != 1 ){
        // 檔案名稱
        $file_key = sha1('dividend'.$dividendreference_setting);

        // 存放檔案的路徑
        $reload_file = dirname(__FILE__) .'/tmp_dl/dividend_'.$file_key.'.tmp';

        // 該檔案不存在的情況，透過 bonus_commission_dividendreference_cmd.php 產生資料與檔案
        if( !file_exists($reload_file) ){
           // 頁面取得時間區間，並取得ID後再更新會員資料
           $command = $config['PHPCLI'].' bonus_commission_dividendreference_cmd.php run '.$current_datepicker_start.' '.$current_datepicker.' '.$dividendreference_setting.' web > '.$reload_file.' 2>&1 &';
           passthru($command, $return_var);
           $output_html  = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},1000);</script>';
           file_put_contents($reload_file, $output_html);
        }

        $return_arr['k'] = $file_key;
    }

    // 返回ID讓頁面記錄，以供後續操作分級用
    echo json_encode($return_arr);
}


elseif($action == 'dividend_payout_update' AND $dividendreference_setting != '' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  $get_dividendreference_setting_id_sql = 'SELECT * FROM root_dividendreference_setting WHERE id=\''.$dividendreference_setting.'\';';
  //var_dump($get_dividendreference_setting_id_sql);
  $get_dividendreference_setting_id_result = runSQLall($get_dividendreference_setting_id_sql);
  if($get_dividendreference_setting_id_result[0] == 1 AND isset($_GET['s']) AND isset($_GET['s1']) AND isset($_GET['s2']) AND isset($_GET['s3']) ){
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
    $bonus_token = jwtenc('dividendpayout', $bonusstatus_array);

    $file_key = sha1('dividendpayout'.$dividendreference_setting);
    $logfile_name = dirname(__FILE__) .'/tmp_dl/dividend_'.$file_key.'.tmp';
    if(file_exists($logfile_name)) {
      die('請勿重覆操作');
    }else{
      $command   = $config['PHPCLI'].' bonus_commission_dividendreference_payout_cmd.php run '.$dividendreference_setting.' '.$bonus_token.' '.$_SESSION['agent']->account.' web > '.$logfile_name.' &';
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
    $reload_file = dirname(__FILE__) .'/tmp_dl/dividend_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      echo file_get_contents($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'dividendbonus_del' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/dividend_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      unlink($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}
else if( ($action == 'dividen_count') && ($dividendreference_setting != '') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {
    $dividendreference_setting_update_sql = ''; // 更新 root_dividendreference_setting 用的
    // 點選試算後取得頁面所給的資訊再丟入 update_dividen() 更新
    if(isset($_GET['x0']) AND filter_var($_GET['x0'],FILTER_VALIDATE_FLOAT)){ // 股利發放總額
        $totaldividen = filter_var($_GET['x0'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',totaldividen=\''.$totaldividen.'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',totaldividen=\'0\'';
    }
    // A級條件
    $a['dividend_level'] = 'A'; // 分類等級
    if(isset($_GET['ax0']) AND $_GET['ax0'] != '' AND filter_var($_GET['ax0'],FILTER_VALIDATE_INT)){ // 股利發放比例
        $a['dividend'] = filter_var($_GET['ax0'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',dividend_ratio_a=\''.$a['dividend'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',dividend_ratio_a=\'0\'';
    }
    if(isset($_GET['ax1']) AND $_GET['ax1'] != '' AND filter_var($_GET['ax1'],FILTER_VALIDATE_INT)){ // 會員第1代的代理商人數
        $a['x1'] = filter_var($_GET['ax1'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentcount_a=\''.$a['x1'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentcount_a=\'0\'';
    }
    if(isset($_GET['ax2']) AND $_GET['ax2'] != '' AND filter_var($_GET['ax2'],FILTER_VALIDATE_INT)){ // 會員第1代的會員人數
        $a['x2'] = filter_var($_GET['ax2'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_membercount_a=\''.$a['x2'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_membercount_a=\'0\'';
    }
    if(isset($_GET['ax3']) AND $_GET['ax3'] != '' AND filter_var($_GET['ax3'],FILTER_VALIDATE_FLOAT)){ // 會員區間累計投注量
        $a['x3'] = filter_var($_GET['ax3'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentsum_allbets_a=\''.$a['x3'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentsum_allbets_a=\'0\'';
    }
    if(isset($_GET['ax4']) AND $_GET['ax4'] != '' AND filter_var($_GET['ax4'],FILTER_VALIDATE_FLOAT)){ // 會員區間累計損益貢獻量
        $a['x4'] = filter_var($_GET['ax4'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_bets_a=\''.$a['x4'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_bets_a=\'0\'';
    }
    if(isset($_GET['ax5']) AND $_GET['ax5'] != '' AND filter_var($_GET['ax5'],FILTER_VALIDATE_FLOAT)){ // 第1代代理商區間累計投注量
        $a['x5'] = filter_var($_GET['ax5'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_profitlost_a=\''.$a['x5'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_profitlost_a=\'0\'';
    }
    // B級條件
    $b['dividend_level'] = 'B'; // 分類等級
    if(isset($_GET['bx0']) AND $_GET['bx0'] != '' AND filter_var($_GET['bx0'],FILTER_VALIDATE_INT)){ // 股利發放比例
        $b['dividend'] = filter_var($_GET['bx0'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',dividend_ratio_b=\''.$b['dividend'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',dividend_ratio_b=\'0\'';
    }
    if(isset($_GET['bx1']) AND $_GET['bx1'] != '' AND filter_var($_GET['bx1'],FILTER_VALIDATE_INT)){ // 會員第1代的代理商人數
        $b['x1'] = filter_var($_GET['bx1'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentcount_b=\''.$b['x1'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentcount_b=\'0\'';
    }
    if(isset($_GET['bx2']) AND $_GET['bx2'] != '' AND filter_var($_GET['bx2'],FILTER_VALIDATE_INT)){ // 會員第1代的會員人數
        $b['x2'] = filter_var($_GET['bx2'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_membercount_b=\''.$b['x2'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_membercount_b=\'0\'';
    }
    if(isset($_GET['bx3']) AND $_GET['bx3'] != '' AND filter_var($_GET['bx3'],FILTER_VALIDATE_FLOAT)){ // 會員區間累計投注量
        $b['x3'] = filter_var($_GET['bx3'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentsum_allbets_b=\''.$b['x3'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentsum_allbets_b=\'0\'';
    }
    if(isset($_GET['bx4']) AND $_GET['bx4'] != '' AND filter_var($_GET['bx4'],FILTER_VALIDATE_FLOAT)){ // 會員區間累計損益貢獻量
        $b['x4'] = filter_var($_GET['bx4'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_bets_b=\''.$b['x4'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_bets_b=\'0\'';
    }
    if(isset($_GET['bx5']) AND $_GET['bx5'] != '' AND filter_var($_GET['bx5'],FILTER_VALIDATE_FLOAT)){ // 第1代代理商區間累計投注量
        $b['x5'] = filter_var($_GET['bx5'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_profitlost_b=\''.$b['x5'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_profitlost_b=\'0\'';
    }
    // C級條件
    $c['dividend_level'] = 'C'; // 分類等級
    if(isset($_GET['cx0']) AND $_GET['cx0'] != '' AND filter_var($_GET['cx0'],FILTER_VALIDATE_INT)){ // 股利發放比例
        $c['dividend'] = filter_var($_GET['cx0'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',dividend_ratio_c=\''.$c['dividend'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',dividend_ratio_c=\'0\'';
    }
    if(isset($_GET['cx1']) AND $_GET['cx1'] != '' AND filter_var($_GET['cx1'],FILTER_VALIDATE_INT)){ // 會員第1代的代理商人數
        $c['x1'] = filter_var($_GET['cx1'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentcount_c=\''.$c['x1'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentcount_c=\'0\'';
    }
    if(isset($_GET['cx2']) AND $_GET['cx2'] != '' AND filter_var($_GET['cx2'],FILTER_VALIDATE_INT)){ // 會員第1代的會員人數
        $c['x2'] = filter_var($_GET['cx2'],FILTER_VALIDATE_INT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_membercount_c=\''.$c['x2'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_membercount_c=\'0\'';
    }
    if(isset($_GET['cx3']) AND $_GET['cx3'] != '' AND filter_var($_GET['cx3'],FILTER_VALIDATE_FLOAT)){ // 會員區間累計投注量
        $c['x3'] = filter_var($_GET['cx3'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentsum_allbets_c=\''.$c['x3'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_l1_agentsum_allbets_c=\'0\'';
    }
    if(isset($_GET['cx4']) AND $_GET['cx4'] != '' AND filter_var($_GET['cx4'],FILTER_VALIDATE_FLOAT)){ // 會員區間累計損益貢獻量
        $c['x4'] = filter_var($_GET['cx4'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_bets_c=\''.$c['x4'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_bets_c=\'0\'';
    }
    if(isset($_GET['cx5']) AND $_GET['cx5'] != '' AND filter_var($_GET['cx5'],FILTER_VALIDATE_FLOAT)){ // 第1代代理商區間累計投注量
        $c['x5'] = filter_var($_GET['cx5'],FILTER_VALIDATE_FLOAT);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_profitlost_c=\''.$c['x5'].'\'';
    }else{
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',member_sum_all_profitlost_c=\'0\'';
    }

    // 更新前先把所有會員分級都還原為 N/A
    $clear_dividen_sql = <<<SQL
        UPDATE root_dividendreference
        SET member_dividend_level = 'N/A',
            member_dividend_assigned = '0',
            updatetime = now()
        WHERE (dividendreference_setting_id = '{$dividendreference_setting}');
    SQL;
    $clear_dividen_result = runSQLall( $clear_dividen_sql );

    // 返回試算summery
    $return_summary = '<tr class="success"><th>試算結果</th>';
    // 更新股利分配結果
    // 如果"股利發放比例"沒設定的就不做分配
    if(isset($a['dividend'])){
        $dividen_update_a = update_dividen($totaldividen, $dividendreference_setting, $a);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',levela_membercount=\''.$dividen_update_a['membercount'].'\',levela_dividen=\''.$dividen_update_a['dividen'].'\'';
        $dividend_a = money_format('%i', $dividen_update_a['dividen']);
        $return_summary = $return_summary.'<td class="table-cell"><strong>'.$dividen_update_a['membercount'].'</strong></td><td class="table-cell"><strong>&nbsp;'.$dividend_a.'</strong></td>';
        $dividen_total_a = $dividen_update_a['membercount']*$dividen_update_a['dividen'];
    }else{
        $return_summary = $return_summary.'<td class="table-cell"><strong>0</strong></td><td class="table-cell"><strong>&nbsp;'.$config['currency_sign'].' 0</strong></td>';
        $dividen_total_a = 0;
    }
    if(isset($b['dividend'])){
        $dividen_update_b = update_dividen($totaldividen, $dividendreference_setting, $b);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',levelb_membercount=\''.$dividen_update_b['membercount'].'\',levelb_dividen=\''.$dividen_update_b['dividen'].'\'';
        $dividend_b = money_format('%i', $dividen_update_b['dividen']);
        $return_summary = $return_summary.'<td class="table-cell"><strong>'.$dividen_update_b['membercount'].'</strong></td><td class="table-cell"><strong>&nbsp;'.$dividend_b.'</strong></td>';
        $dividen_total_b = $dividen_update_b['membercount']*$dividen_update_b['dividen'];
    }else{
        $return_summary = $return_summary.'<td class="table-cell"><strong>0</strong></td><td class="table-cell"><strong>&nbsp;'.$config['currency_sign'].' 0</strong></td>';
        $dividen_total_b = 0;
    }
    if(isset($c['dividend'])){
        $dividen_update_c = update_dividen($totaldividen, $dividendreference_setting, $c);
        $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',levelc_membercount=\''.$dividen_update_c['membercount'].'\',levelc_dividen=\''.$dividen_update_c['dividen'].'\'';
        $dividend_c = money_format('%i', $dividen_update_c['dividen']);
        $return_summary = $return_summary.'<td class="table-cell"><strong>'.$dividen_update_c['membercount'].'</strong></td><td class="table-cell"><strong>&nbsp;'.$dividend_c.'</strong></td>';
        $dividen_total_c = $dividen_update_c['membercount']*$dividen_update_c['dividen'];
    }else{
        $return_summary = $return_summary.'<td class="table-cell"><strong>0</strong></td><td class="table-cell"><strong>&nbsp;'.$config['currency_sign'].' 0</strong></td>';
        $dividen_total_c = 0;
    }

    $dividen_remaind = $totaldividen - $dividen_total_a - $dividen_total_b - $dividen_total_c;
    $dividen_remaind_fix = money_format('%i', $dividen_remaind);

        // 不符以上條件的都是X級
    $update_dividen_sql = 'UPDATE root_dividendreference
    SET member_dividend_level = \'X\',member_dividend_assigned = \'0\',updatetime=now()
    WHERE member_dividend_level = \'N/A\' AND dividendreference_setting_id = \''.$dividendreference_setting.'\';';

    $update_dividen_result = runSQLall($update_dividen_sql);

    $membercount_remaind = $update_dividen_result[0];

    $return_summary = $return_summary.'<td class="table-cell"><strong>'.$membercount_remaind.'</strong></td><td class="table-cell"><strong>&nbsp;'.$dividen_remaind_fix.'</strong></td></tr>';
    $dividendreference_setting_update_sql = $dividendreference_setting_update_sql.',membercount_remaind=\''.$membercount_remaind.'\',dividen_remaind=\''.$dividen_remaind.'\'';

    // 更新 股利分级參數 到 root_dividendreference_setting 上
    $update_updatetime_to_dividendreference_setting_sql = 'UPDATE "root_dividendreference_setting" SET "updatetime" = NOW(),setted=\'2\''.$dividendreference_setting_update_sql.' WHERE id = \''.$dividendreference_setting.'\';';
    //echo $update_updatetime_to_dividendreference_setting_sql;
    $update_updatetime_to_dividendreference_setting_result = runSQL($update_updatetime_to_dividendreference_setting_sql);

    echo json_encode($return_summary);
}
// 變更區間日期選單
else if( ($action == 'date_select') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ){
    // 查詢已建立資料，但未設定發放等級的資料，讓站長能在前次操作未完成後再次操作
    $dividen_day_record_sql = <<<SQL
        SELECT id,
               setted,
               dailydate_end AS dailydate_record_end,
               dailydate_start AS dailydate_record_start
        FROM root_dividendreference_setting
        WHERE (setted != '1');
    SQL;
    $dividen_day_record_result = runSQLall( $dividen_day_record_sql );

    $date_record_select_html = '';
    $date_record_select_js = '';
    /* if($dividen_day_record_result[0] >= 1){
        $option_str = '';
        $js_switch_option = '';
        for( $i=1; $i<=$dividen_day_record_result[0]; $i++ ){
        // 建立已查詢過但未設定股利分配的時間區間清單
        $option_str = $option_str.'<option value="'.$i.'">'.$dividen_day_record_result[$i]->dailydate_record_start.'~'.$dividen_day_record_result[$i]->dailydate_record_end.'</option>';
        // 建立已查詢過但未設定股利分配的時間區間清單配合的JS
        $js_switch_option = $js_switch_option.'else if(date_record_select_var == '.$i.'){
                                $("#date_start_datepicker").val("'.$dividen_day_record_result[$i]->dailydate_record_start.'");
                                $("#date_end_datepicker").val("'.$dividen_day_record_result[$i]->dailydate_record_end.'");
                                $("#setting_id").val("'.$dividen_day_record_result[$i]->id.'");
                                $("#setting_status").val("'.$dividen_day_record_result[$i]->setted.'");
                            }';
        } // end for
        $date_record_select_html = $date_record_select_html.'<select id="date_record_select" onchange="date_record_select(this.options[this.options.selectedIndex].value);"><option value="0" selected>--</option>'.$option_str.'</select>';
        $date_record_select_js = $date_record_select_js.'
        function date_record_select(date_record_select_var){
            if(date_record_select_var == \'\'){
            }'.$js_switch_option.'
        }';
    } */

    $js = <<<HTML
        <script>
            {$date_record_select_js}
        </script>
    HTML;
    $return_arr = [
        'html' => $date_record_select_html,
        'js' => $js
    ];
    echo json_encode( $return_arr );
}
elseif($action == 'dividen_confirm' AND $dividendreference_setting != '' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' )  {
  // 更新 股利分级參數 到 root_dividendreference_setting 上
  $update_updatetime_to_dividendreference_setting_sql = 'UPDATE "root_dividendreference_setting" SET "updatetime" = NOW(),setted=\'1\' WHERE id = \''.$dividendreference_setting.'\';';
  //echo $update_updatetime_to_dividendreference_setting_sql;
  $update_updatetime_to_dividendreference_setting_result = runSQL($update_updatetime_to_dividendreference_setting_sql);

  // ----------------------------------------------------------------------------
  // 匯出 csv 檔案
  // ----------------------------------------------------------------------------

  $daterange_sql   = "SELECT * FROM root_dividendreference_setting WHERE id = '".$dividendreference_setting."' ORDER BY id;";
  // var_dump($userlist_sql);
  $daterange_result = runSQLall($daterange_sql);
  if($daterange_result[0] == 1) {
    $daterange = $daterange_result[1]->dailydate_start.'.'.$daterange_result[1]->dailydate_end;

    // ----------------------------------------------------------------------------
    // 取得 csv 內容的資料
    // ----------------------------------------------------------------------------
    $userlist_sql   = "SELECT * FROM root_dividendreference
    WHERE dividendreference_setting_id = '".$dividendreference_setting."' ORDER BY id;";
    // var_dump($userlist_sql);
    $userlist = runSQLall($userlist_sql);

    // 寫入 CSV 檔案前, 先產生一組 key 來處理
    $csv_key = 'dividendreference_'.$daterange;
    $csv_key_sha1 = sha1($csv_key);
    // 判斷 root_member count 數量大於 1
    if($userlist[0] >= 1) {
      // 以會員為主要 key 依序列出每個會員的貢獻金額
      for($i = 1 ; $i <= $userlist[0]; $i++){
        // 抓出其中一個人的資料一筆
        $member_dividend = money_format('%i', $userlist[$i]->member_dividend_assigned);
        $v = 1;
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->memberid;
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_account;
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_therole;
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_parentid;
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_level; // 會員的所在層數
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_level1; // 上 1 層
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_level2; // 上 2 層
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_level3; // 上 3 層
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_level4; // 上 4 層
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_l1_agentcount; // 會員的下線代理商有多少人
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_l2_agentsum_allbets; // 會員的下線代理商有多少人
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_l1_membercount; // 會員的下線會員有多少人
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_sum_all_bets; // 本年度投注量
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_sum_all_profitlost; // 本年度盈虧
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_sum_all_count; // 本年度注單量
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_dividend_level; // 本年度注單量
        $csv_array[$csv_key_sha1][$i][$v++] = $member_dividend; // 本年度注單量
        $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->note; // 本年度注單量
      }
      // ------------------------------------------------------
      // 表格資料 row list , end for loop
      // ------------------------------------------------------

      // -------------------------------------------
      // 寫入 CSV 檔案的抬頭
      // -------------------------------------------
      $v = 1;
      $csv_key_title[$csv_key_sha1][$v++] = '會員ID';
      $csv_key_title[$csv_key_sha1][$v++] = '帳號';
      $csv_key_title[$csv_key_sha1][$v++] = '會員身份';
      $csv_key_title[$csv_key_sha1][$v++] = '會員上一層ID';
      $csv_key_title[$csv_key_sha1][$v++] = '所在層數';
      $csv_key_title[$csv_key_sha1][$v++] = '上層第1代';
      $csv_key_title[$csv_key_sha1][$v++] = '上層第2代';
      $csv_key_title[$csv_key_sha1][$v++] = '上層第3代';
      $csv_key_title[$csv_key_sha1][$v++] = '上層第4代';
      $csv_key_title[$csv_key_sha1][$v++] = '會員第1代的代理商人數';
      $csv_key_title[$csv_key_sha1][$v++] = '會員第1代代理商區間累計投注量';
      $csv_key_title[$csv_key_sha1][$v++] = '會員第1代的會員人數';
      $csv_key_title[$csv_key_sha1][$v++] = '會員區間累計投注量';
      $csv_key_title[$csv_key_sha1][$v++] = '會員區間累計損益貢獻量';
      $csv_key_title[$csv_key_sha1][$v++] = '會員區間累計注單量';
      $csv_key_title[$csv_key_sha1][$v++] = '分類等級';
      $csv_key_title[$csv_key_sha1][$v++] = '股利分配額';
      $csv_key_title[$csv_key_sha1][$v++] = '備註';
      // -------------------------------------------

      // -------------------------------------------
      // 將內容輸出到 檔案 , csv format
      // -------------------------------------------
      $filename      = "dividenreference_result_".$daterange.'.csv';
      $absfilename   = dirname(__FILE__)."/tmp_dl/$filename";
      $filehandle    = fopen("$absfilename","w");
      if($filehandle!=FALSE) {

        // Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
        fwrite($filehandle,chr(0xEF).chr(0xBB).chr(0xBF));
        // -------------------------------------------
        // 將資料輸出到檔案 -- Title
        foreach ($csv_key_title as $wline) {
          fputcsv($filehandle, $wline);
        }
        // 將資料輸出到檔案 -- data
        foreach ($csv_array as $wline) {
          foreach ($wline as $line) {
            fputcsv($filehandle, $line);
          }
        }
        // 將資料輸出到檔案 -- Title
        foreach ($csv_key_title as $wline) {
          fputcsv($filehandle, $wline);
        }
        fclose($filehandle);
        // -------------------------------------------
        // 下載按鈕
        // -------------------------------------------
        if(file_exists($absfilename)) {
          $csv_download_url_html = '<a href="./tmp_dl/'.$filename.'" class="btn btn-success" >下載CSV</a>
                                    <button id="export2cashier" class="btn btn-danger" onclick="export2cashier();" >匯出至出納</button>';
        }else{
          $csv_download_url_html = '';
        }
        #echo $csv_download_url_html;
      }
    }else{
      echo '(405) No Data!!';
    }
  }else{
    echo '(404) No Data!!';
  }

  // 返回更新後的歷史記錄選單
  $return_menu = indexmenu_stats_switch();
  $return_arr = array("menu"=>$return_menu,"csv"=>$csv_download_url_html);

  echo json_encode($return_arr);
}elseif($action == 'export2cashier' AND $dividendreference_setting != '' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
  // ----------------------------------------------------------------------------
  // 匯出股利資料至出納表
  // ----------------------------------------------------------------------------
  $daterange_sql   = "SELECT dailydate_start,dailydate_end FROM root_dividendreference_setting WHERE id = '".$dividendreference_setting."' AND note = '';";
   //var_dump($daterange_sql);
  $daterange_result = runSQLall($daterange_sql);
  if($daterange_result[0] == 1) {
    $cashier_log = $daterange_result[1]->dailydate_start.'~'.$daterange_result[1]->dailydate_end.'期間股利所得,ID=';

    // ----------------------------------------------------------------------------
    // 取得 csv 內容的資料
    // ----------------------------------------------------------------------------
    $userlist_sql   = "SELECT id,member_account,member_dividend_assigned FROM root_dividendreference
    WHERE dividendreference_setting_id = '".$dividendreference_setting."' AND note = '' ORDER BY id;";
    // var_dump($userlist_sql);
    $userlist = runSQLall($userlist_sql);

    // 判斷 root_member count 數量大於 1
    if($userlist[0] >= 1) {
      // 以會員為主要 key 依序列出每個會員的貢獻金額
      for($i = 1 ; $i <= $userlist[0]; $i++){
        // 新增股利發放資料至出納表
        $cashiertable_update_sql = 'INSERT INTO cashier ("member_account","cash","note") VALUES (\''.$userlist[$i]->member_account.'\',\''.$userlist[$i]->member_dividend_assigned.'\',\''.$cashier_log.$userlist[$i]->id.'\');';
        #$cashiertable_update_result = runSQLall($cashiertable_update_sql);
        $cashiertable_update_result[0] = 1;
        if($cashiertable_update_result[0] == 1){
          $dividendreference_update_sql = 'UPDATE "root_dividendreference" SET "updatetime" = NOW(),"note" = \'已匯出至出納\' WHERE id = \''.$userlist[$i]->id.'\';';
          $dividendreference_update_result = runSQLall($dividendreference_update_sql);
        }else{
          die('(344)資料新增錯誤！！');
        }
      }
      $dividendreference_setting_update_sql = 'UPDATE "root_dividendreference_setting" SET "updatetime" = NOW(),"note" = \''.$current_datepicker.':已匯出至出納\' WHERE id = \''.$dividendreference_setting.'\';';
      //echo $dividendreference_setting_update_sql;
      $dividendreference_setting_update_result = runSQLall($dividendreference_setting_update_sql);
    }else{
      die('(305) No Data!!');
    }
  }else{
    die('(304) No Data!!');
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
