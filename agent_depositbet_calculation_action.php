<?php
// ----------------------------------------------------------------------------
// Features:    後台--娱乐城佣金计算行為
// File Name:    agent_profitloss_calculation_action.php
// Author:       yaoyuan
// Related:   bonus_commission_profit.php
// DB table:  root_statisticsbonusprofit  營運利潤獎金
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

require_once dirname(__FILE__) . "/agent_depositbet_calculation_lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------

// var_dump($_SESSION);


if (isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}elseif(isset($_POST['a'])) {
    $action = filter_var($_POST['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    die('(x)不合法的測試!!!');
}

//檢查下載csv檔時，csv帶的長編碼經過解密之後的值
if (isset($_GET['csv'])) {
    $CSVquery_sql_array = get_object_vars(jwtdec('dl_comm', $_GET['csv']));
}

//檢查下載個人明細檔時，url帶的長編碼經過解密之後的值
if (isset($_GET['detail_xls'])) {
    $xls_detail_ary = get_object_vars(jwtdec('person_detail', $_GET['detail_xls']));
    // var_dump($xls_detail_ary);die();
}

if (isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}


$query_ary=[];
$current_datepicker       = gmdate('Y-m-d H:i:s', time()+-4 * 3600);
$current_datepicker_end   = date("Y-m-d", strtotime("$current_datepicker -1 day"));//昨天(才有日報統計資料)

// 前面會傳get及更新傳post，故用REQUEST 接
$query_ary['sdate'] = $query_ary['edate'] = '';
if (isset($_REQUEST['sdate']) and $_REQUEST['sdate'] != null) {
    if (validateDate($_REQUEST['sdate'], 'Y-m-d')) {
        $query_ary['sdate'] = $_REQUEST['sdate'];
    } 
}
if (isset($_REQUEST['edate']) and $_REQUEST['edate'] != null) {
    if (validateDate($_REQUEST['edate'], 'Y-m-d')) {
        $query_ary['edate'] =  $_REQUEST['edate'];
        if ($query_ary['edate'] > $current_datepicker_end) {
            $query_ary['edate'] = $current_datepicker_end;
        }
    }
} 


//var_dump($date_fmt);
//var_dump($current_datepicker_end);
//var_dump($current_datepicker_start);
// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期  END
// -------------------------------------------------------------------------

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

$agent_therole_map = ['R' => '管理员', 'A' => '代理商', 'M' => '会员'];
$yorn_map = ['t' => $tr['y'], 'f' => $tr['n']];
// 取得遊戲分類
$casino_game_categories = get_casino_game_categories();

// -------------------------------------------------------------------------
// 動作為會員登入檢查 MAIN
// -------------------------------------------------------------------------
if ($action == 'reload_profitlist' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    if ($query_ary['sdate'] == '' OR $query_ary['edate'] == '') {
        $output = array(
            "sEcho" => 0,
            "iTotalRecords" => 0,
            "iTotalDisplayRecords" => 0,
            "data" => [
                "download_url" => '#',
                "list" => [],
            ]
        );
        echo json_encode($output);
        die();
    }
    
    // 佣金總表，依開始、結束日期，所有人數sql
    $userlist_sql_tmp=commission_depositbet_summary_sql($query_ary['sdate'],$query_ary['edate']);
    // echo($userlist_sql_tmp);die();
    $userlist_count=runsql($userlist_sql_tmp);
    // var_dump($userlist_count);die();


    // 無佣金資料或按下試算，才重新計算。
    if ($userlist_count <= 0 ) {
        $command = $config['PHPCLI'] . ' agent_depositbet_calculation_cmd.php run ' . $query_ary['sdate'] . ' ' . $query_ary['edate'] . ' web';
        // echo($command);die();
        $last_line = exec($command, $return_var);
        $userlist_count = runsql($userlist_sql_tmp);
        // var_dump($userlist_count);die();
    }
    
    // 發到彩金池陣列
    $sent_payoutpool = [
            'current_daterange_html' => '',
            'agent_count_html'       => '',
            'sum_commission_html'    => 0,
            'transaction_id'         => '',
            'is_payout'              => 0,
    ];

    // 發送到彩金池日期區間
    $sent_payoutpool['current_daterange_html'] = $query_ary['sdate'] . '~' . $query_ary['edate'];
    // 發送到彩金池-發送筆數
    $sent_payoutpool['agent_count_html'] = $userlist_count;

    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    // 所有紀錄數量
    $page['all_records'] = $userlist_count;
    // 每頁顯示多少
    $page['per_size'] = $current_per_size;
    // 目前 所在頁數
    $page['no'] = $current_page_no;
    // var_dump($page);

    // 處理 datatables 傳來的排序需求
    if (isset($_GET['order'][0]) and $_GET['order'][0]['column'] != '') {
        if ($_GET['order'][0]['dir'] == 'asc') {$sql_order_dir = 'ASC';
        } else { $sql_order_dir = 'DESC';}
        if ($_GET['order'][0]['column'] == 0) {$sql_order = 'ORDER BY agent_id ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 1) {$sql_order = 'ORDER BY agent_therole ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 2) {$sql_order = 'ORDER BY agent_account ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 3) {$sql_order = 'ORDER BY commission ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 4) {$sql_order = 'ORDER BY valid_member ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 5) {$sql_order = 'ORDER BY effective_membership_pass ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 6) {$sql_order = 'ORDER BY valid_bet_sum ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 7) {$sql_order = 'ORDER BY reach_bet_amount ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 8) {$sql_order = 'ORDER BY profitlost_sum ' . $sql_order_dir;

        } else { $sql_order = 'ORDER BY agent_account ASC';}
    } else { $sql_order = 'ORDER BY agent_account ASC';}
    // 取出 root_member 資料
    $userlist_sql = $userlist_sql_tmp . " " . $sql_order . " OFFSET " . $page['no'] . " LIMIT " . $page['per_size'] . " ;";
    // echo $userlist_sql;
    $userlist = runSQLall($userlist_sql);
    // var_dump($userlist);die();


    // 存放列表的 html -- 表格 row -- tables DATA
    $show_listrow_html = '';
    // 判斷 root_member count 數量大於 1
    if ($userlist[0] >= 1) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for ($i = 1; $i <= $userlist[0]; $i++) {
            $b['id']             = $i;
            $b['agent_id']       = $userlist[$i]->agent_id;
            $b['agent_account']  = $userlist[$i]->agent_account;
            $b['agent_therole']  = $agent_therole_map[$userlist[$i]->agent_therole];
            $b['commission']     = $userlist[$i]->commission;
            $b['valid_member']   = $userlist[$i]->valid_member;
            $b['valid_bet_sum']  = $userlist[$i]->valid_bet_sum;
            $b['profitlost_sum'] = $userlist[$i]->profitlost_sum;
            
            $b['effective_membership_pass'] = $yorn_map[$userlist[$i]->effective_membership_pass];
            $b['reach_bet_amount']          = $yorn_map[$userlist[$i]->reach_bet_amount];
            
            // 下載明細之url
            $dl_detail_url=[];
            $dl_detail_url=['agent_id'=>$userlist[$i]->agent_id,'transaction_id'=>$userlist[$i]->transaction_id];
            $b['dl_detail_code']=jwtenc('person_detail', $dl_detail_url);
            // $b['is_payout']      = $userlist[$i]->is_payout;
            // $b['transaction_id'] = $userlist[$i]->transaction_id;
            // $b['start_date']     = $userlist[$i]->start_date;
            // $b['end_date']       = $userlist[$i]->end_date;
            // $b['updatetime']     = $userlist[$i]->updatetime;
            // $b['payout_date']    = $userlist[$i]->payout_date;
            // $b['note']           = $userlist[$i]->note;

            // 顯示的表格資料內容
            $show_listrow_array[] = [
                'id'             => $b['id'],
                'agent_id'       => $b['agent_id'],
                'agent_account'  => $b['agent_account'],
                'agent_therole'  => $b['agent_therole'],
                'commission'     => '$' . $b['commission'],
                'valid_member'   => $b['valid_member'],
                'valid_bet_sum'  => '$' . $b['valid_bet_sum'],
                'profitlost_sum' => '$' . $b['profitlost_sum'],
                'dl_detail_code' => $b['dl_detail_code'],
                'effective_membership_pass' => $b['effective_membership_pass'],
                'reach_bet_amount'          => $b['reach_bet_amount'],

                // 'is_payout'      => $b['is_payout'],
                // 'transaction_id' => $b['transaction_id'],
                // 'start_date'     => $b['start_date'],
                // 'end_date'       => $b['end_date'],
                // 'updatetime'     => $b['updatetime'],
                // 'payout_date'    => $b['payout_date'],
                // 'note'           => $b['note'],
            ];
        }
        // 計算發送到彩金池的佣金，及是否已發放至彩金池
        $sum_comm_payout=sum_commission_html($query_ary['sdate'],$query_ary['edate']);
        // 發送到彩金池-預計發送佣金量
        $sent_payoutpool['sum_commission_html']=money_format('%.2i',$sum_comm_payout[1]->total_comm);
        // 發送到彩金池-交易序號
        $sent_payoutpool['transaction_id'] = $userlist[1]->transaction_id;
        $sent_payoutpool['is_payout']      = $sum_comm_payout[1]->payout;
        
        // var_dump($sent_payoutpool);die();
        // 組成最初的查詢條件字串
        $dl_csv_code = jwtenc('dl_comm', $query_ary);
        unset($userlist);

        $output = array(
            "sEcho" => intval($secho),
            "iTotalRecords" => intval($page['per_size']),
            "iTotalDisplayRecords" => intval($userlist_count),
            "data" => [
                "download_url" => 'agent_depositbet_calculation_action.php?a=download_csv&csv=' . $dl_csv_code,
                "list" => $show_listrow_array,
                "sent_payoutpool"=>$sent_payoutpool,
            ],
        );
    } else {
        // NO member
        $output = array(
            "sEcho" => 0,
            "iTotalRecords" => 0,
            "iTotalDisplayRecords" => 0,
            "data" => [
                "download_url" => '#',
                "list" => [],
            ]
        );
    }
    // end member sql
    echo json_encode($output);


}elseif ($action == 'download_csv' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R'  AND isset($CSVquery_sql_array)) {
    $agentlist_sql_tmp='';
    //撈出總表，日期區間代理商資料 
    $agentlist_sql_tmp = commission_depositbet_summary_sql($CSVquery_sql_array['sdate'], $CSVquery_sql_array['edate']);
    $agentlist_sql_tmp .=' ORDER BY agent_id;';
    $agent_list = runSQLall($agentlist_sql_tmp);

    if($agent_list[0] >= 1) {
        $j=$v = 1;
        $csv_summary[0][$v++] = '编号';
        $csv_summary[0][$v++] = '身份';
        $csv_summary[0][$v++] = '帐号';
        $csv_summary[0][$v++] = '佣金';
        $csv_summary[0][$v++] = '有效会员';
        $csv_summary[0][$v++] = '有效会员门槛';
        $csv_summary[0][$v++] = '有效会员达成';
        $csv_summary[0][$v++] = '总投注量';
        $csv_summary[0][$v++] = '下线总投注量达成';
        $csv_summary[0][$v++] = '损益';
        $csv_summary[0][$v++] = '开始时间';
        $csv_summary[0][$v++] = '结束时间';
        $csv_summary[0][$v++] = '更新时间';
        $csv_summary[0][$v++] = '佣金名称';
        $csv_summary[0][$v++] = '下线最低总投注级距';

        for ($i = 1; $i <= $agent_list[0]; $i++) {
            $v= 1;
            $csv_summary[$i][$v++] = $j;
            $csv_summary[$i][$v++] = $agent_therole_map[$agent_list[$i]->agent_therole];
            $csv_summary[$i][$v++] = $agent_list[$i]->agent_account;
            $csv_summary[$i][$v++] = $agent_list[$i]->commission;
            $csv_summary[$i][$v++] = $agent_list[$i]->valid_member;
            $csv_summary[$i][$v++] = $agent_list[$i]->effective_member_set;
            $csv_summary[$i][$v++] = $yorn_map[$agent_list[$i]->effective_membership_pass];
            $csv_summary[$i][$v++] = $agent_list[$i]->valid_bet_sum;
            $csv_summary[$i][$v++] = $yorn_map[$agent_list[$i]->reach_bet_amount];
            $csv_summary[$i][$v++] = $agent_list[$i]->profitlost_sum;
            $csv_summary[$i][$v++] = $agent_list[$i]->start_date;
            $csv_summary[$i][$v++] = $agent_list[$i]->end_date;
            $csv_summary[$i][$v++] = $agent_list[$i]->updatetime_ast;
            $csv_summary[$i][$v++] = $agent_list[$i]->agent_commissionrule;
            $csv_summary[$i][$v++] = $agent_list[$i]->downline_effective_bet=='-1'?'未达最低投注级距':$agent_list[$i]->downline_effective_bet;
            $j++;
        }

    }else{echo '(190305) 无佣金总表资料!!';}

    //撈出佣金明細，
    $memberlist_sql_tmp=commission_depositbet_detail_sql($CSVquery_sql_array['sdate'], $CSVquery_sql_array['edate']);
    $member_list=runSQLall($memberlist_sql_tmp);

    // print_r($casino_game_categories);die();
    if ($member_list[0] >= 1) {
        $j=$v = 1;
        $csv_detail[0][$v++] = '编号';
        $csv_detail[0][$v++] = '会员身份';
        $csv_detail[0][$v++] = '会员帐号';
        $csv_detail[0][$v++] = '上层代理帐号';

        $csv_detail[0][$v++] = '上层代理佣金名称';
        $csv_detail[0][$v++] = '佣金有效投注级距';
        $csv_detail[0][$v++] = '有效投注';
        $csv_detail[0][$v++] = '总损益';
        
        $csv_detail[0][$v++] = '总存款';
        $csv_detail[0][$v++] = '存款退佣比';
        $csv_detail[0][$v++] = '存款佣金';

        $csv_detail[0][$v++] = '有效投注佣金总和';

        foreach ($casino_game_categories as $key=> $values) {
            foreach($values as $value){
                $csv_detail[0][$v++]=$key.$tr[$value].$tr['bets'];
                $csv_detail[0][$v++]=$key.$tr[$value].$tr['Commission setting'];
                $csv_detail[0][$v++]=$key.$tr[$value].$tr['commissions'];
            }
        }

        // foreach(json_decode($member_list[$i]->commission_detail,true) as $key => $value){
        //     $csv_detail[0][$v++] = $key;
        // }
        $csv_detail[0][$v++] = '计算佣金开始日期';
        $csv_detail[0][$v++] = '计算佣金结束日期';
        // $csv_detail[0][$v++] = '更新时间';
        // $csv_detail[0][$v++] = '發送至彩金池';
        // $csv_detail[0][$v++] = '總表與明細交易序號';
        // $csv_detail[0][$v++] = '發送至彩金池時間';

        for ($i = 1; $i <= $member_list[0]; $i++) {
            $v                     = 1;
            $csv_detail[$i][$v++] = $j;
            $csv_detail[$i][$v++] = $agent_therole_map[$member_list[$i]->member_therole];
            $csv_detail[$i][$v++] = $member_list[$i]->member_account;
            $csv_detail[$i][$v++] = $member_list[$i]->parent_account;

            $csv_detail[$i][$v++] = $member_list[$i]->agent_commissionrule;
            $csv_detail[$i][$v++] = $member_list[$i]->downline_effective_bet=='-1'?'未达最低投注级距':$member_list[$i]->downline_effective_bet;
            $csv_detail[$i][$v++] = $member_list[$i]->member_bets;
            $csv_detail[$i][$v++] = $member_list[$i]->member_profitlost;

            $csv_detail[$i][$v++] = $member_list[$i]->all_deposit;
            $csv_detail[$i][$v++] = $member_list[$i]->deposit_comsion_set;
            $csv_detail[$i][$v++] = $member_list[$i]->deposit_comsion;
            
            $csv_detail[$i][$v++] = $member_list[$i]->valid_bet_comsion_sum;
            // $m=1; 各娛樂城分類佣金
            foreach (json_decode($member_list[$i]->commission_detail, true) as $key => $value) {
                //   if($m%3==2){
                //     $m++;
                //     $value=$value*100;
                //     $csv_detail[$i][$v++]=$value.'%';
                //     continue;
                //   }
                // $m++;
                $csv_detail[$i][$v++]= $value;
            }

            $csv_detail[$i][$v++] = $member_list[$i]->start_date;
            $csv_detail[$i][$v++] = $member_list[$i]->end_date;
            // $csv_detail[$i][$v++] = $member_list[$i]->updatetime_ast;
            // $csv_detail[$i][$v++] = $member_list[$i]->is_payout;
            // $csv_detail[$i][$v++] = $member_list[$i]->transaction_id;
            // $csv_detail[$i][$v++] = $member_list[$i]->payout_date;
            $j++;
        }

    } else {echo '(190306) 无佣金明細资料!!';}

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
    $sheet->fromArray($csv_summary,NULL,'A1');

    // 設定數字格式
    $sheet->getStyle('D')
            ->getNumberFormat()
            ->setFormatCode('$ #,###,###,##0.00');
    $sheet->getStyle('H')
            ->getNumberFormat()
            ->setFormatCode('$ #,###,###,##0.00');
    $sheet->getStyle('J')
            ->getNumberFormat()
            ->setFormatCode('$ #,###,###,##0.00;[Red]$ -#,###,###,##0.00');

    // 自動欄寬
    $worksheet = $spreadsheet->getActiveSheet();
    foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    }



    // 明細索引標籤開始寫入資料
    $sheet_detail = $spreadsheet->setActiveSheetIndex(1);
    // 寫入明細資料陣列
    $sheet_detail->fromArray($csv_detail, null, 'A1');
    // 轉成百分比格式 第一種
    // $sheet_detail->getStyle('J')
    //     ->getNumberFormat()
    //     ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE);
    // 轉成百分比格式  第二種
    // $sheet_detail->getCellByColumnAndRow(6,1)->getStyle()->getNumberFormat()
        // ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE);

    // Rename worksheet
    $spreadsheet->getActiveSheet()->setTitle('明细');


    $spreadsheet->setActiveSheetIndex(1);

    // xlsx
    $file_name='commission'.date('ymd_His', time());
    // var_dump($file_name);die();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$file_name.'.xlsx"');
    header('Cache-Control: max-age=0');

    // 直接匯出，不存於disk
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}elseif ($action == 'dl_detail' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R'  AND isset($xls_detail_ary)) {
    // 撈出個人總表及明細
    $single_agent_download_summary=single_agent_download_summary($xls_detail_ary['agent_id'],$xls_detail_ary['transaction_id']);
    $single_agent_download_detail=single_agent_download_detail($xls_detail_ary['agent_id'],$xls_detail_ary['transaction_id']);

    if($single_agent_download_summary[0] >= 1) {
        $j=$v = 1;
        $csv_summary[0][$v++] = '编号';
        $csv_summary[0][$v++] = '身份';
        $csv_summary[0][$v++] = '帐号';
        $csv_summary[0][$v++] = '佣金';
        $csv_summary[0][$v++] = '有效会员';
        $csv_summary[0][$v++] = '有效投注';
        $csv_summary[0][$v++] = '损益';
        $csv_summary[0][$v++] = '开始时间';
        $csv_summary[0][$v++] = '结束时间';
        $csv_summary[0][$v++] = '更新时间';
        for ($i = 1; $i <= $single_agent_download_summary[0]; $i++) {
            $v= 1;
            $csv_summary[$i][$v++] = $j;
            $csv_summary[$i][$v++] = $agent_therole_map[$single_agent_download_summary[$i]->agent_therole];
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->agent_account;
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->commission;
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->valid_member;
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->valid_bet_sum;
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->profitlost_sum;
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->start_date;
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->end_date;
            $csv_summary[$i][$v++] = $single_agent_download_summary[$i]->updatetime_ast;
            $j++;
        }
    }else{echo '(405) 无佣金总表资料!!';}

    if ($single_agent_download_detail[0] >= 1) {
        $j=$v = 1;
        $csv_detail[0][$v++] = '编号';
        $csv_detail[0][$v++] = '会员身份';
        $csv_detail[0][$v++] = '会员帐号';
        $csv_detail[0][$v++] = '上层代理帐号';
        $csv_detail[0][$v++] = '上层代理佣金名称';
        $csv_detail[0][$v++] = '佣金有效投注级距';
        $csv_detail[0][$v++] = '有效投注';
        $csv_detail[0][$v++] = '总损益';
        $csv_detail[0][$v++] = '总存款';
        $csv_detail[0][$v++] = '存款退佣比';
        $csv_detail[0][$v++] = '存款佣金';
        $csv_detail[0][$v++] = '有效投注佣金总和';
        foreach ($casino_game_categories as $key=> $values) {
            foreach($values as $value){
                $csv_detail[0][$v++]=$key.$tr[$value].$tr['bets'];
                $csv_detail[0][$v++]=$key.$tr[$value].$tr['Commission setting'];
                $csv_detail[0][$v++]=$key.$tr[$value].$tr['commissions'];
            }
        }
        // foreach(json_decode($single_agent_download_detail[$i]->commission_detail,true) as $key => $value){
        //     $csv_detail[0][$v++] = $key;
        // }
        $csv_detail[0][$v++] = '计算佣金开始日期';
        $csv_detail[0][$v++] = '计算佣金结束日期';
        // $csv_detail[0][$v++] = '更新时间';

        for ($i = 1; $i <= $single_agent_download_detail[0]; $i++) {
            $v                     = 1;
            $csv_detail[$i][$v++] = $j;
            $csv_detail[$i][$v++] = $agent_therole_map[$single_agent_download_detail[$i]->member_therole];
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->member_account;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->parent_account;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->agent_commissionrule;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->downline_effective_bet;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->member_bets;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->member_profitlost;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->all_deposit;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->deposit_comsion_set;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->deposit_comsion;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->valid_bet_comsion_sum;
            foreach (json_decode($single_agent_download_detail[$i]->commission_detail, true) as $key => $value) {
                $csv_detail[$i][$v++]= $value;
            }
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->start_date;
            $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->end_date;
            // $csv_detail[$i][$v++] = $single_agent_download_detail[$i]->updatetime_ast;
            $j++;
        }
    } else {echo '(190306) 无佣金明細资料!!';}


    // 清除快取以防亂碼
    ob_end_clean();

    //---------------phpspreadsheet----------------------------
    $spreadsheet = new Spreadsheet();

    // 總表索引標籤開始寫入資料
    $sheet = $spreadsheet->setActiveSheetIndex(0);

    $sheet->mergeCells('A1:I1');
    $sheet->setCellValue('A1', '总表');

    // 寫入總表資料陣列
    $sheet->fromArray($csv_summary, null, 'A2');

    $sheet->mergeCells('A6:P6');
    $sheet->setCellValue('A6', '明细');

    // 寫入明細資料陣列
    $sheet->fromArray($csv_detail, null, 'A7');

    // 設定數字格式
    $sheet->getStyle('D3')
        ->getNumberFormat()
        ->setFormatCode('$ #,###,###,##0.00');
    $sheet->getStyle('F3')
        ->getNumberFormat()
        ->setFormatCode('$ #,###,###,##0.00');
    $sheet->getStyle('G3')
        ->getNumberFormat()
        ->setFormatCode('$ #,###,###,##0.00;[Red]$ -#,###,###,##0.00');
    $sheet->getStyle('G8:G999')
        ->getNumberFormat()
        ->setFormatCode('$ #,###,###,##0.00;[Red]$ -#,###,###,##0.00');
    $sheet->getStyle('H8:H999')
        ->getNumberFormat()
        ->setFormatCode('$ #,###,###,##0.00;[Red]$ -#,###,###,##0.00');


    // 自動欄寬
    $worksheet = $spreadsheet->getActiveSheet();
    foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spreadsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
    }

    $spreadsheet->getActiveSheet()->setTitle($single_agent_download_summary[1]->agent_account);


    // xlsx
    $file_name = $single_agent_download_summary[1]->agent_account .$single_agent_download_summary[1]->start_date.'_'.$single_agent_download_summary[1]->end_date;
    // var_dump($file_name);die();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $file_name . '.xlsx"');
    header('Cache-Control: max-age=0');

    // 直接匯出，不存於disk
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');


}elseif ($action == 'update_data' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    if ($query_ary['sdate'] == '' or $query_ary['edate'] == '') {
        $logger = $tr['Please select the correct date range!'];
        echo "<script>alert('" . $logger . "');location.href='agent_depositbet_calculation.php'</script>";
        die();
    }

    // 重新計算佣金
    $command   = $config['PHPCLI'] . ' agent_depositbet_calculation_cmd.php run ' . $query_ary['sdate'] . ' ' . $query_ary['edate'] . ' web';
    $last_line = exec($command, $return_var);

    echo "<script>location.href='agent_depositbet_calculation.php?sdate=" . $query_ary['sdate'] . "&edate=" . $query_ary['edate'] ."';</script>";
    die();

}elseif ($action == 'profitloss_payout' and isset($_GET['payout_date']) and isset($_GET['payout_end_date']) and isset($_GET['s']) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    if (validateDate($_GET['payout_date'], 'Y-m-d') and validateDate($_GET['payout_end_date'], 'Y-m-d') and isset($_GET['s']) and isset($_GET['s1']) and isset($_GET['s2']) and isset($_GET['s3'])) {
        // 取得獎金的各設定並生成token傳給 cmd 執行
        $bonus_status = filter_var($_GET['s'], FILTER_VALIDATE_INT);
        $bonus_type = filter_var($_GET['s1'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $audit_type = filter_var($_GET['s2'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $audit_amount = filter_var($_GET['s3'], FILTER_VALIDATE_INT);
        $audit_ratio = filter_var($_GET['s4'], FILTER_VALIDATE_FLOAT);
        $audit_calculate_type = filter_var($_GET['s5'], FILTER_SANITIZE_STRING);
        // $transaction_id = filter_var($_GET['transaction_id'], FILTER_SANITIZE_STRING);

        $bonusstatus_array = [
            'bonus_status'         => $bonus_status,
            'bonus_type'           => $bonus_type,
            'audit_type'           => $audit_type,
            'audit_amount'         => $audit_amount,
            'audit_ratio'          => $audit_ratio,
            'audit_calculate_type' => $audit_calculate_type,
            // 'transaction_id'       => $transaction_id,
        ];
        // var_dump($bonusstatus_array);die();
        // 產生 token , salt是檢核密碼預設值為123456 ,需要配合 jwtdec 的解碼, 此範例設定為 123456
        $bonus_token = jwtenc('sendpayoutpool', $bonusstatus_array);

        $dailydate = $_GET['payout_date'];
        $dailydate_end = $_GET['payout_end_date'];
        $file_key = sha1('sendpayoutpool' . $dailydate . $dailydate_end);
        $logfile_name = dirname(__FILE__) . '/tmp_dl/payoutpool_' . $file_key . '.tmp';
        if (file_exists($logfile_name)) {
            die('請勿重覆操作');
        } else {
            $command = $config['PHPCLI'].' agent_depositbet_payout_cmd.php run ' . $dailydate . ' ' . $dailydate_end . ' ' . $bonus_token . ' ' . $_SESSION['agent']->account . ' web > ' . $logfile_name . ' &';
            // echo nl2br($command);die();

            // dispatch command and show loading view
            dispatch_proccessing(
                $command,
                '更新中...',
                $_SERVER['PHP_SELF'] . '?a=profitloss_payout_reload&k=' . $file_key,
                $logfile_name
            );
        }
    } else {
        $output_html = $tr['There is a problem with the date format or status setting.'];
        echo '<hr><br><br><p align="center">' . $output_html . '</p>';
        echo '<br><br><p align="center"><button type="button" onclick="window.close();">'.$tr['close the window'].'</button></p>';
    }
} elseif ($action == 'profitloss_payout_reload' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/payoutpool_' . $logfile_sha . '.tmp';
    if (file_exists($reload_file)) {
        echo file_get_contents($reload_file);
        // die();
    } else {
        die('(x)不合法的測試!');
    }
} elseif ($action == 'profitloss_del' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/payoutpool_' . $logfile_sha . '.tmp';
    // var_dump($reload_file);die();
    if (file_exists($reload_file)) {
        // echo('asfsadfa');die();
        unlink($reload_file);
    } else {
        die('(x)不合法的測試!!');
    }
}elseif ($action == 'test') {
    // -----------------------------------------------------------------------
    // test developer
    // -----------------------------------------------------------------------
    var_dump($_POST);

} elseif (isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $output = array(
                "sEcho" => 0,
                "iTotalRecords" => 0,
                "iTotalDisplayRecords" => 0,
                "data" => [
                    "download_url" => '#',
                    "list" => [],
                ]
            );
    echo json_encode($output);
    die();
}else {
    $logger = '(x) 只有管理員或有權限的會員才可以使用。';
    echo $logger;
}
