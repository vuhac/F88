<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 時時反水查詢
// File Name:    realtime_reward.php
// Author:    yaoyuan
// Related  :root_protalsetting    ->時時反水設定值。
//           root_statisticsbetting->依照日期區間，撈十分鐘報表。
//           root_realtime_reward  ->時時反水資料。
//           root_receivemoney     ->反水打入彩金池db。
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/realtime_reward_lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$error_msg='';
if (isset($_REQUEST['a'])) {
    $action = filter_var($_REQUEST['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    die('(x)不合法的測試!!!');
}

//檢查下載csv檔時，csv帶的長編碼經過解密之後的值
if (isset($_REQUEST['csv'])) {
    $CSVquery_sql_array = get_object_vars(jwtdec('rewardrealtime', $_REQUEST['csv']));
}

//檢查下載個人明細檔時，url帶的長編碼經過解密之後的值
if (isset($_REQUEST['detail_xls'])) {
    $xls_detail_ary = get_object_vars(jwtdec('person_detail', $_REQUEST['detail_xls']));
}

if (isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

$query_ary          = [];
$where_sql='';
$current_datepicker_end     = gmdate('Y-m-d H:i:s', time()+8 * 3600). ' +08:00';
$query_ary['sdate']     = $query_ary['edate']     = '';
if (isset($_REQUEST['sdate']) and $_REQUEST['sdate'] != null) {
    if (validateDate($_REQUEST['sdate'], 'Y-m-d H:i')) {
        $query_ary['sdate'] = gmdate('Y-m-d H:i:s', strtotime($_REQUEST['sdate'] . ':00 -04') + 8 * 3600) . ' +08:00';
    }
}
if (isset($_REQUEST['edate']) and $_REQUEST['edate'] != null) {
    if (validateDate($_REQUEST['edate'], 'Y-m-d H:i')) {
        $query_ary['edate'] = gmdate('Y-m-d H:i:s', strtotime($_REQUEST['edate'] . ':00 -04') + 8 * 3600) . ' +08:00';
        if ($query_ary['edate'] > $current_datepicker_end) {
            $query_ary['edate'] = $current_datepicker_end;
        }
    }
}
if($query_ary['sdate'] >$query_ary['edate']){
    $error_msg='(x)开始日期大于结束日期!!!';
}

if (isset($_REQUEST['trans_id'])) {
    $trans_id = filter_var($_REQUEST['trans_id'], FILTER_SANITIZE_STRING);
} else {
    $trans_id = '';
}


if($query_ary['sdate']!='' AND $query_ary['edate']!=''){
  $where_sql=' start_date >= \''.$query_ary['sdate'] .'\' AND end_date <= \''.$query_ary['edate'].'\'';
}elseif($trans_id!=''){
  $where_sql=' transaction_id=\''.$trans_id.'\'';
}
// var_dump($where_sql);die();

if ($error_msg!='' AND $action != 'update_data'){
    $output = array(
    "sEcho"                => 0,
    "iTotalRecords"        => 0,
    "iTotalDisplayRecords" => 0,
    "data"                 => [
        "download_url" => '#',
        "list"         => [],
        "is_error"     => $error_msg,
    ]);
    echo json_encode($output);
    die();
}elseif($error_msg!='' AND $action == 'update_data' ){
    echo '<script>alert("' . $error_msg . '");window.close();</script>';die();
}

// var_dump($query_ary['sdate']);var_dump($query_ary['edate']); var_dump($current_datepicker_end);die();

// 程式每次的處理量 -- 當資料量太大時，可以分段處理。 透過 GET 傳遞依序處理。
if (isset($_GET['length']) and $_GET['length'] != null) {
    $current_per_size = filter_var($_GET['length'], FILTER_VALIDATE_INT);
} else {
    $current_per_size = $page_config['datatables_pagelength'];
    //$current_per_size = 10;
}

// 起始頁面, 搭配 current_per_size 決定起始點位置
if (isset($_GET['start']) and $_GET['start'] != null) {
    $current_page_no = filter_var($_GET['start'], FILTER_VALIDATE_INT);
} else {
    $current_page_no = 0;
}
// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if (isset($_GET['_'])) {
    $secho = $_GET['_'];
} else {
    $secho = '1';
}

$agent_therole_map = ['R' => $tr['R'], 'A' => $tr['A'], 'M' => $tr['M']];
$yno= ['t' => 'y', 'f' => 'n'];

// -------------------------------------------------------------------------
// 動作為會員登入檢查 MAIN
// -------------------------------------------------------------------------
if ($action == 'reload_rewardlist' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    // if ($query_ary['sdate'] == '' or $query_ary['edate'] == '') {
    if ($where_sql== '') {
        $output = array(
            "sEcho"                => 0,
            "iTotalRecords"        => 0,
            "iTotalDisplayRecords" => 0,
            "data"                 => [
                "download_url" => '#',
                "list"         => [],
            ]
        );
        echo json_encode($output);
        die();
    }

    // 佣金總表，依開始、結束日期，所有人數sql
    // $userlist_sql_tmp = get_realtime_reward_sql($query_ary['sdate'], $query_ary['edate']);
    $userlist_sql_tmp = get_realtime_reward_sql($where_sql);
    // echo($userlist_sql_tmp);die();
    $userlist_count = runsql($userlist_sql_tmp);
    // var_dump($userlist_count);die();

    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    $page['all_records'] = $userlist_count; // 所有紀錄數量
    $page['per_size'] = $current_per_size; // 每頁顯示多少
    $page['no'] = $current_page_no; // 目前 所在頁數

    // 處理 datatables 傳來的排序需求
    if ( isset($_GET['order'][0]) && !empty($_GET['order'][0]['column']) ) {
        $sql_order_dir = ( (strtolower($_GET['order'][0]['dir']) == 'asc') ? 'ASC' : 'DESC' );

        $column = [ // 以前端DataTable欄位排序
            'member_id',
            'member_account',
            'member_therole',
            'real_reward_amount',
            'bet_sum',
            'reach_bet_amount',
            'start_date',
            'end_date',
            'payout_date',
            'is_payout'
        ];
        $order_column = $_GET['order'][0]['column'];
        $sql_order = <<<SQL
            ORDER BY {$column[6]} {$sql_order_dir}
        SQL; // 預設order方式
        if ( !empty($column[ $order_column ]) ) {
            $sql_order = <<<SQL
                ORDER BY {$column[ $order_column ]} {$sql_order_dir}
            SQL;
        }
    } else {
        $sql_order = 'ORDER BY member_account ASC';
    }

    if ($_GET['order'][0]['column'] != 1) {
        $sql_order .= <<<SQL
            , "member_account" ASC
        SQL;
    }

    // 取出 root_member 資料
    $userlist_sql = <<<SQL
        {$userlist_sql_tmp} {$sql_order}
        OFFSET {$page['no']}
        LIMIT {$page['per_size']};
    SQL;
    $userlist = runSQLall($userlist_sql);
    // echo '<pre>', var_dump($userlist), '</pre>'; exit();

    // 存放列表的 html -- 表格 row -- tables DATA
    $show_listrow_html = '';
    // 判斷 root_member count 數量大於 1
    if ($userlist[0] >= 1) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for ($i = 1; $i <= $userlist[0]; $i++) {
            $b = [
                'id' => $i,
                'member_id' => $userlist[$i]->member_id,
                'member_account' => $userlist[$i]->member_account,
                'member_therole' => $agent_therole_map[$userlist[$i]->member_therole],
                'real_reward_amount' => $userlist[$i]->real_reward_amount,
                'bet_sum' => $userlist[$i]->bet_sum,
                'reach_bet_amount' => $tr[$yno[$userlist[$i]->reach_bet_amount]],
                'start_date' => $userlist[$i]->start_date_ast,
                'end_date' => $userlist[$i]->end_date_ast,
                'is_payout' => $tr[$yno[$userlist[$i]->is_payout]],
                'payout_date' => ( empty($userlist[$i]->payout_date) ? '----' : date("Y-m-d H:i:s", strtotime($userlist[$i]->payout_date)) )
            ];

            // 顯示的表格資料內容
            $show_listrow_array[] = [
                'id' => $b['id'],
                'member_id' => $b['member_id'],
                'member_account' => $b['member_account'],
                'member_therole' => $b['member_therole'],
                'real_reward_amount' => transCurrencySign($b['real_reward_amount']),
                'bet_sum' => transCurrencySign($b['bet_sum']),
                'reach_bet_amount' => $b['reach_bet_amount'],
                'start_date' => $b['start_date'],
                'end_date' => $b['end_date'],
                'is_payout' => $b['is_payout'],
                'payout_date' => $b['payout_date']
            ];
        }

        // 組成最初的查詢條件字串
        $query_ary['trans_id'] = $trans_id;
        $dl_csv_code = jwtenc('rewardrealtime', $query_ary);
        unset($userlist);

        $output = [
            "sEcho" => intval($secho),
            "iTotalRecords" => intval($page['per_size']),
            "iTotalDisplayRecords" => intval($userlist_count),
            "data" => [
                "download_url" => 'realtime_reward_action.php?a=download_csv&csv='.$dl_csv_code,
                "list" => $show_listrow_array,
            ],
        ];
    } else { // 沒有資料
        $output = [
            'sEcho' => 0,
            'iTotalRecords' => 0,
            'iTotalDisplayRecords' => 0,
            'data' => [
                'download_url' => '#',
                'list' => []
            ]
        ];
    }

    echo json_encode($output);

} else if ( ($action == 'download_csv') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') && isset($CSVquery_sql_array) ) { // 下載Excel報表
    /*
        $CSVquery_sql_array = [
            'edate' => '',
            'sdate' => '',
            'trans_id' => ''
        ];
    */

    $where_sql = '';
    if ( ($CSVquery_sql_array['sdate'] != '') && ($CSVquery_sql_array['edate'] != '') ) { // 有設定時間起迄，以時間起迄為搜尋條件
        $where_sql = <<<SQL
            "start_date" >= '{$CSVquery_sql_array["sdate"]}'
                AND "end_date" <= '{$CSVquery_sql_array["edate"]}'
        SQL;
    } else if ($CSVquery_sql_array['trans_id'] != '') { // 有設定交易序號，取得該交易序號
        $where_sql = <<<SQL
            "transaction_id" = '{$CSVquery_sql_array["trans_id"]}'
        SQL;
    } else {
        die('Wrong parmter.');
    }

    $reward_sum_sql_tmp = '';
    // 撈出時時反水總表，日期區間或交易序號為搜尋條件，撈出資料
    $reward_sum_sql_tmp = get_realtime_reward_sql($where_sql);
    $reward_sum_sql_tmp .= <<<SQL
         ORDER BY "member_therole" ASC,
                  "member_account" ASC,
                  "start_date" DESC,
                  "end_date" DESC,
                  "reach_bet_amount" DESC,
                  "reward_amount" DESC;
    SQL;
    $reward_sum = runSQLall($reward_sum_sql_tmp);

    if ($reward_sum[0] >= 1) {
        $j_summary = 1; // summary直向列編號
        $j_detail = 1; // detail直向列編號
        $v = 1; // 橫向列編號

        // 總表欄位名稱
        $csv_summary[0][$v++] = '编号';
        $csv_summary[0][$v++] = '身份';
        $csv_summary[0][$v++] = '帐号';
        $csv_summary[0][$v++] = '计算开始时间';
        $csv_summary[0][$v++] = '计算结束时间';
        $csv_summary[0][$v++] = '最后更新时间';
        $csv_summary[0][$v++] = '适用返水等级';
        $csv_summary[0][$v++] = '是否达成打码量';
        $csv_summary[0][$v++] = '级距打码量標準金额';
        $csv_summary[0][$v++] = '实际打码量金額';
        $csv_summary[0][$v++] = '总损益金额';
        $csv_summary[0][$v++] = '预期返水金额';
        $csv_summary[0][$v++] = '返水上限金额';
        $csv_summary[0][$v++] = '实际返水金额';
        $csv_summary[0][$v++] = '派彩日期';

        // 明細欄位名稱
        $csv_detail[0][$v++] = '编号';
        $csv_detail[0][$v++] = '身分';
        $csv_detail[0][$v++] = '帐号';
        $csv_detail[0][$v++] = '计算开始时间';
        $csv_detail[0][$v++] = '计算结束时间';
        $csv_detail[0][$v++] = '最后更新时间';
        $csv_detail[0][$v++] = '娱乐城';
        $csv_detail[0][$v++] = '游戏分类';
        $csv_detail[0][$v++] = '游戏名称';
        $csv_detail[0][$v++] = '有效投注金額';
        $csv_detail[0][$v++] = '损益金额';
        $csv_detail[0][$v++] = '实际返水金额';
        $csv_detail[0][$v++] = '返水比例';
        $csv_detail[0][$v++] = '备注';


        for ($i=1; $i<=$reward_sum[0]; $i++) {
            $v = 1;

            // 共通資料
            $start_datetime = date("Y/m/d H:i:s", strtotime($reward_sum[$i]->start_date_ast));
            $end_datetime = date("Y/m/d H:i:s", strtotime($reward_sum[$i]->end_date_ast));
            $update_datetime = date("Y/m/d H:i:s", strtotime($reward_sum[$i]->updatetime_ast));
            $therole = '';
            switch ($reward_sum[$i]->member_therole) {
                case 'A':
                    $therole = '代理商';
                    break;
                case 'M':
                    $therole = '会员';
                    break;
                default:
                    $therole = '未定义';
            }

            // 總表資料
            $csv_summary[$i][$v++] = $j_summary; // 編號
            $csv_summary[$i][$v++] = $therole; // 身分
            $csv_summary[$i][$v++] = $reward_sum[$i]->member_account; // 帳號
            $csv_summary[$i][$v++] = $start_datetime; // 計算開始時間
            $csv_summary[$i][$v++] = $end_datetime; // 計算結束時間
            $csv_summary[$i][$v++] = $update_datetime; // 最後更新時間
            $csv_summary[$i][$v++] = $reward_sum[$i]->favorable_level; // 適用返水等級
            $csv_summary[$i][$v++] = ( ($reward_sum[$i]->reach_bet_amount == 't') ? '是' : '否' ); // 是否達成打碼量
            $csv_summary[$i][$v++] = transCurrencySign($reward_sum[$i]->favorable_bet_level); // 級距打碼量標準金額
            $csv_summary[$i][$v++] = transCurrencySign($reward_sum[$i]->bet_sum); // 實際打碼量
            $csv_summary[$i][$v++] = transCurrencySign($reward_sum[$i]->profit_sum); // 總損益金額
            $csv_summary[$i][$v++] = transCurrencySign($reward_sum[$i]->reward_amount); // 預期返水金額
            $csv_summary[$i][$v++] = transCurrencySign($reward_sum[$i]->favorable_upperlimit); // 返水上限金額
            $csv_summary[$i][$v++] = transCurrencySign($reward_sum[$i]->real_reward_amount); // 實際返水金額
            $csv_summary[$i][$v++] = ( !empty($reward_sum[$i]->payout_date) ? date("Y/m/d H:i:s", strtotime($reward_sum[$i]->payout_date)) : '----' ); // 派彩日期
            $j_summary++;

            // 明細資料
            $favorate_detail = json_decode($reward_sum[$i]->details);
            foreach ($favorate_detail as $casino_id=>$category_detail) { // 娛樂城
                foreach ($category_detail as $category=>$game_detail) { //遊戲分類
                    foreach ($game_detail as $game=>$detail) { // 遊戲
                        $csv_detail[$j_detail][$v++] = $j_detail; // 編號
                        $csv_detail[$j_detail][$v++] = $therole; // 身分
                        $csv_detail[$j_detail][$v++] = $reward_sum[$i]->member_account; // 帳號
                        $csv_detail[$j_detail][$v++] = $start_datetime; // 計算開始時間
                        $csv_detail[$j_detail][$v++] = $end_datetime; // 計算結束時間
                        $csv_detail[$j_detail][$v++] = $update_datetime; // 最後更新時間
                        $csv_detail[$j_detail][$v++] = $casino_id; // 娛樂城
                        $csv_detail[$j_detail][$v++] = $category; // 遊戲分類
                        $csv_detail[$j_detail][$v++] = $game; // 遊戲名稱
                        $csv_detail[$j_detail][$v++] = ( empty($detail->betvalid) ? transCurrencySign('0') : transCurrencySign($detail->betvalid) ); // 有效投注
                        $csv_detail[$j_detail][$v++] = ( empty($detail->betprofit) ? transCurrencySign('0') : transCurrencySign($detail->betprofit) ); // 損益金額
                        $csv_detail[$j_detail][$v++] = ( empty($detail->reward_amount) ? transCurrencySign('0') : transCurrencySign($detail->reward_amount) ); // 實際返水
                        $csv_detail[$j_detail][$v++] = ( empty($detail->favorable_rate) ? '0' : $detail->favorable_rate ); // 返水比例
                        $csv_detail[$j_detail][$v++] = ( isset($detail->detail) ? $detail->detail : '----' ); // 備註(當實際返水金額  如果有的話)
                        $j_detail++;
                    }
                }
            }
        }

    } else { // 沒有查到時時返水資料
        echo <<<HTML
            <script>
                alert('无时时返水资料!');
                history.go(-1);
            </script>
        HTML;
    }

    // var_dump($csv_detail);die();
    // 清除快取以防亂碼
    ob_end_clean();

    //---------------phpspreadsheet----------------------------
    $spreadsheet = new Spreadsheet();

    // Create a new worksheet called "My Data"
    $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, '总表');

    // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
    $spreadsheet->addSheet($myWorkSheet, 0);

    // 總表索引標籤開始寫入資料
    $sheet = $spreadsheet->setActiveSheetIndex(0);

    // 寫入總表資料陣列
    $sheet->fromArray($csv_summary, null, 'A1');

    // 定義欄寬
    $worksheet = $spreadsheet->getActiveSheet();
    $column_width = [10, 10, 15, 25, 25, 25, 15, 20, 15, 15, 15, 15, 15, 15, 25];
    foreach (range('A', $worksheet->getHighestColumn()) as $key=>$column) {
        // $worksheet->getColumnDimension($column)->setAutoSize(true); // 自動欄寬
        $worksheet->getColumnDimension($column)->setWidth($column_width[$key]); // 自定義欄位寬度
    }
    //--------------------------------------------------------------------
    // 明細索引標籤開始寫入資料
    $sheet_detail = $spreadsheet->setActiveSheetIndex(1);

    // Rename worksheet
    $spreadsheet->getActiveSheet()->setTitle('明细');

    // 寫入明細資料陣列
    $sheet_detail->fromArray($csv_detail, null, 'A1');

    // 定義欄寬
    $worksheet = $spreadsheet->getActiveSheet();
    $column_width = [10, 10, 15, 25, 25, 25, 15, 15, 15, 15, 15, 15, 15, 15, 25];
    foreach (range('A', $worksheet->getHighestColumn()) as $key=>$column) {
        // $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true); // 自動欄寬
        $worksheet->getColumnDimension($column)->setWidth($column_width[$key]); // 自定義欄位寬度
    }


    // xlsx
    $file_name = 'realtime_reward_sum' . date('ymd_His', time());
    // var_dump($file_name);die();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $file_name . '.xlsx"');
    header('Cache-Control: max-age=0');

    // 直接匯出，不存於disk
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');

} elseif ($action == 'update_data' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R' AND isset($query_ary['sdate']) AND isset($query_ary['edate'])) {
    if ($query_ary['sdate'] == '' or $query_ary['edate'] == '') {
        $logger = $tr['Please select the correct date range!'];
        echo "<script>alert('" . $logger . "');window.close();</script>";
        die();
    }

    // 台灣時區->美東，才能執行cmd
    $sdate_est=$edate_est='';
    $sdate_est = gmdate('Y-m-d H:i:s', strtotime($query_ary['sdate'] . ' +08') - 4 * 3600);
    $edate_est = gmdate('Y-m-d H:i:s', strtotime($query_ary['edate'] . ' +08') - 4 * 3600);

    $file_key = sha1('reward' . $sdate_est.$edate_est);
    $logfile_name = dirname(__FILE__) . '/tmp_dl/reward_' . $file_key . '.tmp';
    if (file_exists($logfile_name)) {
        die('更新中...请勿重复操作');
    } else {
        $command = $config['PHPCLI'] . ' realtime_reward_cmd.php run web 0 ' . $sdate_est . ' '.$edate_est.' > ' . $logfile_name . ' &';
        // echo nl2br($command);die();
        // dispatch command and show loading view
        dispatch_proccessing(
            $command,
            '更新中...',
            // '#',
            $_SERVER['PHP_SELF'] . '?a=reward_update_reload&k=' . $file_key,
            $logfile_name
        );
    }
    // $sdate_est_min = date('Y-m-d H:i', strtotime($sdate_est));
    // $edate_est_min = date('Y-m-d H:i', strtotime($edate_est));
    // echo "<script>location.href='realtime_reward.php?sdate=" . $sdate_est_min . "&edate=" . $edate_est_min . "';</script>";
}elseif($action == 'reward_update_reload' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/reward_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      echo file_get_contents($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'reward_del' AND isset($logfile_sha) AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $reload_file = dirname(__FILE__) .'/tmp_dl/reward_'.$logfile_sha.'.tmp';
    if(file_exists($reload_file)) {
      unlink($reload_file);
    }else{
      die('(x)不合法的測試');
    }
}elseif($action == 'query_existing_data' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ) {
    $judge_reward=judge_reward($where_sql);
    echo(json_decode($judge_reward));
    die();
}elseif($action == 'payout_batch' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND $trans_id!='' ) {
    // 發送至彩金池前，更新：派彩日期、更新日期、是否派彩
    update_realtime_reward_payout_date($trans_id);
    $get_realtime_reward=get_realtime_reward($trans_id);

    if($get_realtime_reward[0]<1){
        $output = array(
            "data" => [
                "errormsg"     => '没有时时反水资料可打入彩金池!',
                "successmsg"   => '',
                "download_url" => '#',
            ]
        );
        echo json_encode($output);

        // 寫入memberlog
        $msg         = $output['data']['errormsg'].'交易批号：'.$trans_id.'。'; //客服
        $msg_log     = $msg; //RD
        $sub_service = 'realtime_reward';
        memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

        die();
    }
    unset($get_realtime_reward[0]);
    // 寫入彩金池資料表
    $now_datetime = gmdate("Y-m-d H:i:s", time()+8*3600) . ' +08:00';
    // var_dump($now_datetime);die();
    $receivedeadlinetime = gmdate("Y-m-d H:i:s", strtotime('+1 month', time()+8*3600)) . ' +08:00';
    $prizecategories     = $get_realtime_reward[1]->start_date_ast . ' ' . $get_realtime_reward[1]->start_time_ast . '-' . $get_realtime_reward[1]->end_time_ast . '时时反水';
    $summary             = $get_realtime_reward[1]->start_date_ast . ' ' . $get_realtime_reward[1]->start_time_ast .' ~ ' . $get_realtime_reward[1]->end_time_ast . '期间时时反水';

    // 返回重新整理網頁的時間
    $get_list_url = 'realtime_reward.php?trans_id=' . $get_realtime_reward[1]->transaction_id.'&show_start_date='.$get_realtime_reward[1]->start_date.'&show_end_date='.$get_realtime_reward[1]->end_date;
    // var_dump($get_list_url);die();
    // $date_range_html = '<a href="' . $get_list_url . '" title="观看指定区间">' . $date_range . '</a>';

    // 撈出時時反水全站設定值
    $sets = realtime_reward_protalsetting();
    // init sql execor
    $batched_sql_executor = new BatchedSqlExecutor(100);


    foreach ($get_realtime_reward as $member_data) {
        // 由 稽核倍數 來計算 稽核金額
        if ($sets['realtime_reward_audit_type'] == 'audit_ratio') {
            $audit_amount = round($member_data->real_reward_amount * $sets['realtime_reward_audit_amount_ratio'], 2);
        } else {
            $audit_amount = $sets['realtime_reward_audit_amount_ratio'];
        }
        // 判斷獎金是以gcash or gtoken發放，如是gtoken則需設定稽核，gcash則不用
        if ($sets['realtime_reward_bonus_type'] == 'gtoken') {
            $sets['bonus_cash']  = '0';
            $sets['bonus_token'] = $member_data->real_reward_amount;
        } elseif ($sets['realtime_reward_bonus_type'] == 'gcash') {
            $sets['bonus_cash']                 = $member_data->real_reward_amount;
            $sets['bonus_token']                = '0';
            $sets['realtime_reward_audit_name'] = 'freeaudit';
            $audit_amount                       = '0';
        } else {
            $output = array(
                "data" => [
                    "errormsg"     => 'Error(500):獎金類別錯誤！!!',
                    "successmsg"   => '',
                    "download_url" => '#',
                ]
            );
            echo json_encode($output);
            die();
        }

        $batched_sql_executor->push(
            insert_receivemoney_sql([
                // $insert_detail_buffer[] = insert_receivemoney_sql([
                'member_id'                  => $member_data->member_id,
                'member_account'             => $member_data->member_account,
                'gcash_balance'              => $sets['bonus_cash'],
                'gtoken_balance'             => $sets['bonus_token'],
                'givemoneytime'              => $now_datetime,
                'receivedeadlinetime'        => $receivedeadlinetime,
                'prizecategories'            => $prizecategories,
                'updatetime'                 => $now_datetime,
                'auditmode'                  => $sets['realtime_reward_audit_name'],
                'auditmodeamount'            => $audit_amount,
                'summary'                    => $summary,
                'transaction_category'       => 'tokenpreferential',
                'system_note'                => '时时反水交易号码:'.$member_data->transaction_id,
                'reconciliation_reference'   => $member_data->transaction_id,
                'givemoney_member_account'   => $_SESSION["agent"]->account,
                'status'                     => $sets['realtime_reward_bonus_status'],
                'last_modify_member_account' => $_SESSION["agent"]->account,
            ])
        )
        ;
    }
    $batched_sql_executor->execute();
    // 釋放打入彩金池資料
    unset($get_realtime_reward);

    $output = array(
        "data" => [
            "errormsg"     => '',
            "successmsg"   => '发送至彩金池，完毕。',
            "download_url" => $get_list_url,
        ]
    );
    echo json_encode($output);




// } elseif ($action == 'profitloss_payout' and isset($_GET['payout_date']) and isset($_GET['payout_end_date']) and isset($_GET['s']) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
//     if (validateDate($_GET['payout_date'], 'Y-m-d') and validateDate($_GET['payout_end_date'], 'Y-m-d') and isset($_GET['s']) and isset($_GET['s1']) and isset($_GET['s2']) and isset($_GET['s3'])) {
//         // 取得獎金的各設定並生成token傳給 cmd 執行
//         $bonus_status         = filter_var($_GET['s'], FILTER_VALIDATE_INT);
//         $bonus_type           = filter_var($_GET['s1'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
//         $audit_type           = filter_var($_GET['s2'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
//         $audit_amount         = filter_var($_GET['s3'], FILTER_VALIDATE_INT);
//         $audit_ratio          = filter_var($_GET['s4'], FILTER_VALIDATE_FLOAT);
//         $audit_calculate_type = filter_var($_GET['s5'], FILTER_SANITIZE_STRING);
//         // $transaction_id = filter_var($_GET['transaction_id'], FILTER_SANITIZE_STRING);

//         $bonusstatus_array = [
//             'bonus_status'         => $bonus_status,
//             'bonus_type'           => $bonus_type,
//             'audit_type'           => $audit_type,
//             'audit_amount'         => $audit_amount,
//             'audit_ratio'          => $audit_ratio,
//             'audit_calculate_type' => $audit_calculate_type,
//             // 'transaction_id'       => $transaction_id,
//         ];
//         // var_dump($bonusstatus_array);die();
//         // 產生 token , salt是檢核密碼預設值為123456 ,需要配合 jwtdec 的解碼, 此範例設定為 123456
//         $bonus_token = jwtenc('sendpayoutpool', $bonusstatus_array);

//         $dailydate     = $_GET['payout_date'];
//         $dailydate_end = $_GET['payout_end_date'];
//         $file_key      = sha1('sendpayoutpool' . $dailydate . $dailydate_end);
//         $logfile_name  = dirname(__FILE__) . '/tmp_dl/payoutpool_' . $file_key . '.tmp';
//         if (file_exists($logfile_name)) {
//             die('請勿重覆操作');
//         } else {
//             $command = $config['PHPCLI'] . ' agent_depositbet_payout_cmd.php run ' . $dailydate . ' ' . $dailydate_end . ' ' . $bonus_token . ' ' . $_SESSION['agent']->account . ' web > ' . $logfile_name . ' &';
//             // echo nl2br($command);die();

//             // dispatch command and show loading view
//             dispatch_proccessing(
//                 $command,
//                 '更新中...',
//                 $_SERVER['PHP_SELF'] . '?a=profitloss_payout_reload&k=' . $file_key,
//                 $logfile_name
//             );
//         }
//     } else {
//         $output_html = $tr['There is a problem with the date format or status setting.'];
//         echo '<hr><br><br><p align="center">' . $output_html . '</p>';
//         echo '<br><br><p align="center"><button type="button" onclick="window.close();">' . $tr['close the window'] . '</button></p>';
//     }
// } elseif ($action == 'profitloss_payout_reload' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
//     $reload_file = dirname(__FILE__) . '/tmp_dl/payoutpool_' . $logfile_sha . '.tmp';
//     if (file_exists($reload_file)) {
//         echo file_get_contents($reload_file);
//         // die();
//     } else {
//         die('(x)不合法的測試!');
//     }
// } elseif ($action == 'profitloss_del' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
//     $reload_file = dirname(__FILE__) . '/tmp_dl/payoutpool_' . $logfile_sha . '.tmp';
//     // var_dump($reload_file);die();
//     if (file_exists($reload_file)) {
//         // echo('asfsadfa');die();
//         unlink($reload_file);
//     } else {
//         die('(x)不合法的測試!!');
//     }
} elseif ($action == 'test') {
    // -----------------------------------------------------------------------
    // test developer
    // -----------------------------------------------------------------------
    var_dump($_POST);

} elseif (isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $output = array(
        "sEcho"                => 0,
        "iTotalRecords"        => 0,
        "iTotalDisplayRecords" => 0,
        "data"                 => [
            "download_url" => '#',
            "list"         => [],
        ]
    );
    echo json_encode($output);
    die();
} else {
    $logger = '(x) 只有管理員或有權限的會員才可以使用。';
    echo $logger;
}