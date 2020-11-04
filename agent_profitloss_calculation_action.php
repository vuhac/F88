<?php
// ----------------------------------------------------------------------------
// Features:    後台--娱乐城佣金计算行為
// File Name:    agent_profitloss_calculation_action.php
// Author:        Barkley Fix By Ian
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

require_once dirname(__FILE__) . "/agent_profitloss_calculation_lib.php";

require_once dirname(__FILE__) . "/lib_proccessing.php";

// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------

function get_summary($current_datepicker_start, $current_datepicker_end)
{
    global $config;

    $r = [
        'current_daterange_html' => '',
        'agent_count_html' => '',
        'member_count_html' => '',
        'sum_sum_all_bets_html' => '',
        'sum_all_profitlost_html' => '',
        'sum_member_profitamount_pos_html' => '',
        'sum_member_profitamount_negitive_html' => '',
        'sum_member_profitamount_html' => '',
        'sum_all_profitlost_ratio_html' => '',
        'sum_member_profit_radio_html' => '',
        'root_commission_html' => '',
        'root_commission_ratio' => '',
        'agent_commission_ratio' => '',
    ];

    // 統計區間
    $r['current_daterange_html'] = "$current_datepicker_start~$current_datepicker_end";

    $list_sql = <<<SQL
        SELECT SUM("dailyreport"."agent_recursive_sumbets") as "agent_recursive_sumbets",
               SUM("dailyreport"."agent_recursive_sumbetsprofit") as "agent_recursive_sumbetsprofit",
               MAX("dailyreport"."agent_recursive_agent_count") as "agent_recursive_agent_count",
               MAX("dailyreport"."agent_recursives_count") as "agent_recursives_count"
        FROM "root_commission_dailyreport" AS "dailyreport"
        WHERE ("member_account" = 'root')
            AND ('{$current_datepicker_start}' <= "dailydate")
            AND ("end_date" <= '{$current_datepicker_end}')
        GROUP BY "member_account";
    SQL;
    $list_result = runSQLall($list_sql);

    // 分佣合計正值
    $commission_sql=commission_sql($current_datepicker_start,$current_datepicker_end);
    // echo $commission_sql;die();
    $commission_result = runSQLall($commission_sql);

    // 分佣合計負值
    $commission_sql_negitive = commission_sql_negitive($current_datepicker_start, $current_datepicker_end);
    // echo $commission_sql_negitive;die();
    $commission_negitive_result = runSQLall($commission_sql_negitive);

    if ($list_result[0] > 0 && $commission_result[0] > 0) {

        // 供應商總計
        $r['agent_count_html'] = $list_result[1]->agent_recursive_agent_count;

        // 會員總計
        $r['member_count_html'] = $list_result[1]->agent_recursives_count;

        setlocale(LC_MONETARY, $config['default_locate']);

        // 總投注量
        $r['sum_sum_all_bets_html'] = money_format('%.2i', $list_result[1]->agent_recursive_sumbets);

        // 總損益值
        $r['sum_all_profitlost_html'] = money_format('%.2i', $list_result[1]->agent_recursive_sumbetsprofit);

        // 分佣合计(只计算正值)
        $profitamount_pos = $commission_result[1]->agent_commission;
        // 累计到下次分佣的总计(负值)
        $profitamount_negitive = $commission_negitive_result[1]->agent_commission;
        // 分佣总计(正 + 负)
        $profitamount = $profitamount_pos + $profitamount_negitive;

        $r['sum_member_profitamount_pos_html'] = money_format('%.2i', $profitamount_pos);
        $r['sum_member_profitamount_negitive_html'] = money_format('%.2i', $profitamount_negitive);
        $r['sum_member_profitamount_html'] = money_format('%.2i', $profitamount);

        $r['sum_all_profitlost_ratio_html'] = '0.00 %';
        $r['sum_member_profit_radio_html'] = '0.00 %';

        if ($list_result[1]->agent_recursive_sumbets > 0) {
            $r['sum_all_profitlost_ratio_html'] = number_format(100 * $list_result[1]->agent_recursive_sumbetsprofit / $list_result[1]->agent_recursive_sumbets, 2, '.', '') . ' %';
            $r['sum_member_profit_radio_html'] = number_format(100 * $profitamount / $list_result[1]->agent_recursive_sumbets, 2, '.', '') . ' %';
        }

        $root_commission = $list_result[1]->agent_recursive_sumbetsprofit - $profitamount;
        $r['root_commission_html'] = money_format('%.2i', $root_commission);

        $r['root_commission_ratio'] = '0.00 %';
        $r['agent_commission_ratio'] = '0.00 %';
        if ($list_result[1]->agent_recursive_sumbets > 0) {
            $r['root_commission_ratio'] = number_format(100 * $root_commission / $list_result[1]->agent_recursive_sumbetsprofit, 2, '.', '') . ' %';
            $r['agent_commission_ratio'] = number_format(100 * $profitamount / $list_result[1]->agent_recursive_sumbetsprofit, 2, '.', '') . ' %';
        }
    }

    return $r;

}

// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------

// var_dump($_SESSION);
// var_dump($_GET);
// var_dump($_POST);
// die();
if (isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    die('(x)不合法的測試');
}

if (isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if (isset($_GET['current_datepicker'])) {
    // 判斷格式資料是否正確
    if (validateDate($_GET['current_datepicker'], 'Y-m-d')) {
        $current_datepicker = $_GET['current_datepicker'];
    } else {
        // 轉換為美東的時間 date
        $date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
        date_timezone_set($date, timezone_open('America/St_Thomas'));
        $current_datepicker = date_format($date, 'Y-m-d');
    }
} else {
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
$current_date_d = date("d", strtotime("$current_datepicker"));
$current_date_m = date("m", strtotime("$current_datepicker"));
$current_date_Y = date("Y", strtotime("$current_datepicker"));
//var_dump($lastdayofmonth);
//var_dump($current_date_d);
if ($current_date_d > $rule['stats_profit_day']) {
    $date_fmt = 'Y-m-' . $rule['stats_profit_day'];
    $current_date_m++;
    $current_datepicker = $current_date_Y . '-' . $current_date_m . '-' . $rule['stats_profit_day'];
    //var_dump($current_datepicker);
    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth = date("Y-m-t", strtotime($current_date_Y . '-' . $current_date_m . '-1'));
    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if ($current_datepicker > $lastdayofmonth) {
        $current_datepicker_end = $lastdayofmonth;
    } else {
        $current_datepicker_end = $current_datepicker;
    }
    //var_dump($current_datepicker_end);
    // 計算前一輪的計算日
    $current_date_m--;
    $dayofcurrentstart = $rule['stats_profit_day'] + 1;
    $current_datepicker_start = $current_date_Y . '-' . $current_date_m . '-' . $dayofcurrentstart;
    //var_dump($current_datepicker_start);
    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth_lastcycle = date("Y-m-t", strtotime($current_date_Y . '-' . $current_date_m . '-1'));
    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if ($current_datepicker_start > $lastdayofmonth_lastcycle and $current_date_m == date("m", strtotime($current_datepicker_start))) {
        if ($current_date_m == 2) {
            $current_date_m++;
            $current_datepicker_start = $current_date_Y . '-' . $current_date_m . '-1';
        } else {
            $current_datepicker_start = $lastdayofmonth_lastcycle;
        }
    }
} else {
    $date_fmt = 'Y-m-' . $rule['stats_profit_day'];
    $current_datepicker = $current_date_Y . '-' . $current_date_m . '-' . $rule['stats_profit_day'];
    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth = date("Y-m-t", strtotime($current_date_Y . '-' . $current_date_m . '-1'));
    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if ($current_datepicker > $lastdayofmonth) {
        $current_datepicker_end = $lastdayofmonth;
    } else {
        $current_datepicker_end = $current_datepicker;
    }
    // 計算前一輪的計算日
    $current_date_m--;
    $dayofcurrentstart = $rule['stats_profit_day'] + 1;
    $current_datepicker_start = date("Y-m-d", strtotime($current_date_Y . '-' . $current_date_m . '-' . $dayofcurrentstart));
    //var_dump($current_datepicker_start);
    // 取得當月的最後一天，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    $lastdayofmonth_lastcycle = date("Y-m-t", strtotime($current_date_Y . '-' . $current_date_m . '-1'));
    // 判斷是否大於當月的最後一天，如是，則以當月最後一天計算，
    // 此判斷主要作用於當$rule['stats_profit_day'] > 28 時，以免因設定造成日期取超出當月最後一天而無法自DB取資料
    if ($current_datepicker_start > $lastdayofmonth_lastcycle and $current_date_m == date("m", strtotime($current_datepicker_start))) {
        if ($current_date_m == 2) {
            $current_date_m++;
            $current_datepicker_start = $current_date_Y . '-' . $current_date_m . '-1';
        } else {
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

$filter_empty = false;
if (isset($_GET['filter_empty']) && ($_GET['filter_empty'] == 'true' || $_GET['filter_empty'] == '1')) {
    $filter_empty = true;
}

// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if (isset($_GET['_'])) {
    $secho = $_GET['_'];
} else {
    $secho = '1';
}

// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 動作為會員登入檢查 MAIN
// -------------------------------------------------------------------------

if ( ($action == 'reload_profitlist') && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {
    // -----------------------------------------------------------------------
    // datatable server process 用資料讀取
    // -----------------------------------------------------------------------

    // -----------------------------------------------------------------------
    // 列出所有的會員資料及人數 SQL
    // -----------------------------------------------------------------------
    // 算 root_member 人數
    $current_datepicker_start = date("Y-m-d", strtotime($_GET['current_datepicker_start']));
    $current_datepicker_end = date("Y-m-d", strtotime($_GET['current_datepicker_end']));

    $total_count_sql = <<<SQL
        SELECT COUNT("dailyreport"."id") as "list_count"
        FROM "root_commission_dailyreport" AS "dailyreport"
        JOIN "root_member"
            ON ("root_member"."id" = "dailyreport"."member_parent_id")
        WHERE ('{$current_datepicker_start}' <= "dailyreport"."dailydate")
            AND ("dailyreport"."end_date" <= '{$current_datepicker_end}')
            AND ("dailyreport"."member_therole" = 'A')
    SQL;

    $userlist_sql_tmp = <<<SQL
        SELECT "dailyreport"."id" AS "detail_id",
               "dailyreport"."member_id",
               "dailyreport"."member_account",
               "dailyreport"."member_therole",
               "dailyreport"."member_parent_id",
               "dailyreport"."member_level",
               "dailyreport"."agent_bets",
               "dailyreport"."agent_betsprofit",
               "dailyreport"."agent_recursive_sumbets",
               "dailyreport"."agent_recursive_sumbetsprofit",
               "dailyreport"."agent_profitlost",
               "dailyreport"."agent_sumwithdrawals",
               "dailyreport"."agent_sumdeposit",
               "dailyreport"."agent_memberbet_count",
               "dailyreport"."agent_memberrecursivebets_count",
               "dailyreport"."agent_agent_count",
               "dailyreport"."agent_recursive_agent_count",
               "dailyreport"."agent_member_count",
               "dailyreport"."agent_recursives_count",
               "dailyreport"."agent_commission_upper",
               "dailyreport"."agent_commission_lower",
               "dailyreport"."agent_commission",
               "dailyreport"."agent_valid_member_recursives_count",
               "root_member"."account" AS "parent_account"
        FROM "root_commission_dailyreport" AS "dailyreport"
        JOIN "root_member"
            ON ("root_member"."id" = "dailyreport"."member_parent_id")
        WHERE ('{$current_datepicker_start}' <= "dailyreport"."dailydate")
            AND ("dailyreport"."end_date" <= '{$current_datepicker_end}')
            AND ("dailyreport"."member_therole" = 'A')
    SQL;

    $list_count_sql = $total_count_sql;

    if ($filter_empty) {
        $userlist_sql_tmp .= <<<SQL
             AND ( ("dailyreport"."agent_commission" != '0') OR ("dailyreport"."agent_recursive_sumbets" != '0') )
        SQL;
        $list_count_sql = <<<SQL
            {$total_count_sql} AND ( ("dailyreport"."agent_commission" != '0') OR ("dailyreport"."agent_recursive_sumbets" != '0') )
        SQL;
    }
    $userlist_sql = $userlist_sql_tmp.' ';
    $total_count = (runSQLAll($total_count_sql)[1])->list_count;// 資料筆數

    if ($total_count == 0) {
        // create or update profitloss calculation
        $command = $config['PHPCLI'].' agent_profitloss_calculation_cmd.php run ' . $current_datepicker_start . ' ' . $current_datepicker_end . ' web';
        $last_line = exec($command, $return_var);
    }

    $userlist_count = (runSQLAll($list_count_sql)[1])->list_count;

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
        if ($_GET['order'][0]['column'] == 0) {$sql_order = 'ORDER BY member_id ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 1) {$sql_order = 'ORDER BY member_account ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 2) {$sql_order = 'ORDER BY member_therole ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 3) {$sql_order = 'ORDER BY parent_account ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 4) {$sql_order = 'ORDER BY agent_valid_member_recursives_count ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 5) {$sql_order = 'ORDER BY agent_recursive_sumbets ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 6) {$sql_order = 'ORDER BY agent_recursive_sumbetsprofit ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 7) {$sql_order = 'ORDER BY agent_commission ' . $sql_order_dir;

        } else { $sql_order = 'ORDER BY member_id ASC';}
    } else { $sql_order = 'ORDER BY member_id ASC';}
    // 取出 root_member 資料
    $userlist_sql = $userlist_sql_tmp . " " . $sql_order . " OFFSET " . $page['no'] . " LIMIT " . $page['per_size'] . " ;";
    // echo $userlist_sql;die();
    $userlist = runSQLall($userlist_sql);

    // print_r($userlist);

    $b['dailydate_start'] = $current_datepicker_start;
    $b['dailydate_end'] = $current_datepicker_end;

    // 存放列表的 html -- 表格 row -- tables DATA
    $show_listrow_html = '';
    // 判斷 root_member count 數量大於 1
    if ($userlist[0] >= 1) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for ($i = 1; $i <= $userlist[0]; $i++) {
            $b['id'] = $i;
            $b['detail_id'] = $userlist[$i]->detail_id;
            $b['member_id'] = $userlist[$i]->member_id;
            $b['member_account'] = $userlist[$i]->member_account;
            $b['member_therole'] = $userlist[$i]->member_therole;
            $b['member_parent_id'] = $userlist[$i]->member_parent_id;
            // 預設有會員的 ID , Account, Role
            $b['member_level'] = $userlist[$i]->member_level;
            //var_dump($skip_bonusinfo_count);  取得第一個字串，為跳過的代數
            $b['agent_bets'] = $userlist[$i]->agent_bets;
            $b['agent_betsprofit'] = $userlist[$i]->agent_betsprofit;
            $b['agent_recursive_sumbets'] = $userlist[$i]->agent_recursive_sumbets;
            $b['agent_recursive_sumbetsprofit'] = $userlist[$i]->agent_recursive_sumbetsprofit;
            $b['agent_profitlost'] = $userlist[$i]->agent_profitlost;
            $b['agent_sumwithdrawals'] = $userlist[$i]->agent_sumwithdrawals;
            $b['agent_sumdeposit'] = $userlist[$i]->agent_sumdeposit;
            $b['agent_memberbet_count'] = $userlist[$i]->agent_memberbet_count;
            $b['agent_memberrecursivebets_count'] = $userlist[$i]->agent_memberrecursivebets_count;

            // 統計的欄位
            $b['agent_agent_count'] = $userlist[$i]->agent_agent_count;
            $b['agent_recursive_agent_count'] = $userlist[$i]->agent_recursive_agent_count;
            $b['agent_member_count'] = $userlist[$i]->agent_member_count;
            $b['agent_recursives_count'] = $userlist[$i]->agent_recursives_count;
            $b['agent_commission_upper'] = $userlist[$i]->agent_commission_upper;
            $b['agent_commission_lower'] = $userlist[$i]->agent_commission_upper - $userlist[$i]->agent_commission;
            $b['agent_commission'] = $userlist[$i]->agent_commission;

            $b['agent_commission_ratio'] = '0.00';

            if ($b['agent_recursive_sumbets'] > 0) {
                $b['agent_commission_ratio'] = number_format($b['agent_commission'] / $b['agent_recursive_sumbets'], 2, '.', '');
            }

            // 顯示的表格資料內容
            $show_listrow_array[] = [
                'detail_id' => $b['detail_id'],
                'member_id' => $b['member_id'],
                'member_account' => $b['member_account'],
                'member_therole' => $b['member_therole'],
                'member_parent_id' => $b['member_parent_id'],
                'member_level' => $b['member_level'],
                'agent_bets' => '$' . $b['agent_bets'],
                'agent_betsprofit' => '$' . $b['agent_betsprofit'],
                'agent_recursive_sumbets' => '$' . $b['agent_recursive_sumbets'],
                'agent_recursive_sumbetsprofit' => '$' . $b['agent_recursive_sumbetsprofit'],
                'agent_profitlost' => '$' . $b['agent_profitlost'],
                'agent_sumwithdrawals' => '$' . $b['agent_sumwithdrawals'],
                'agent_sumdeposit' => '$' . $b['agent_sumdeposit'],
                'agent_memberbet_count' => $b['agent_memberbet_count'],
                'agent_memberrecursivebets_count' => $b['agent_memberrecursivebets_count'],
                'agent_agent_count' => $b['agent_agent_count'],
                'agent_recursive_agent_count' => $b['agent_recursive_agent_count'],
                'agent_member_count' => $b['agent_member_count'],
                'agent_recursives_count' => $b['agent_recursives_count'],
                'agent_commission_upper' => '$' . $b['agent_commission_upper'],
                'agent_commission_lower' => '$' . $b['agent_commission_lower'],
                'agent_commission' => '$' . $b['agent_commission'],
                'agent_commission_ratio' => $b['agent_commission_ratio'],
                'detail_url' => 'agent_profitloss_calculation_detail.php?member_account=' . $b['member_account'] . '&dailydate_start=' . $b['dailydate_start'] . '&dailydate_end=' . $b['dailydate_end'],
                'note' => '',
                'agent_valid_member_recursives_count' => $userlist[$i]->agent_valid_member_recursives_count,
                'first_agent' => '',
                'parent_account' => ($userlist[$i]->parent_account == $config['system_company_account'])?'-':$userlist[$i]->parent_account,
            ];

        }
        $output = array(
            "sEcho" => intval($secho),
            "iTotalRecords" => intval($page['per_size']),
            "iTotalDisplayRecords" => intval($userlist_count),
            "data" => [
                "summary" => get_summary($current_datepicker_start, $current_datepicker_end),
                "list" => $show_listrow_array,
            ],
        );

        // --------------------------------------------------------------------
        // 表格資料 row list , end for loop
        // --------------------------------------------------------------------
    } else {
        // NO member
        $output = array(
            "sEcho" => 0,
            "iTotalRecords" => 0,
            "iTotalDisplayRecords" => 0,
            "data" => [
                "summary" => [],
                "list" => [],
            ]
        );
    }
    // end member sql
    echo json_encode($output);
    // -----------------------------------------------------------------------
    // datatable server process 用資料讀取
    // -----------------------------------------------------------------------
} elseif ($action == 'download_csv' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {

    // var_dump($_GET);
    // var_dump($_POST);
    // die();

    $current_datepicker_start = $_GET['current_datepicker_start'];
    $current_datepicker_end = $_GET['current_datepicker_end'];

    $casino_game_categories = get_casino_game_categories();


    $parsing_casino_cate_sql = parsing_casino_cate_sql($casino_game_categories);
    // print($parsing_casino_cate_sql);
    // die();
    // print("<pre>" . print_r($parsing_casino_cate_sql, true) . "</pre>");die();

    $userlist_sql_tmp = <<<SQL
    SELECT
        MAX(member_id) as member_id,
        member_account,
        MAX(member_therole) as member_therole,
        MAX(member_parent_id) as member_parent_id,
        MAX(root_member.account) as member_parent_account,
        MAX(member_level) as member_level,
        SUM(agent_bets) as agent_bets,
        SUM(agent_betsprofit) as agent_betsprofit,
        SUM(agent_recursive_sumbets) as agent_recursive_sumbets,
        SUM(agent_recursive_sumbetsprofit) as agent_recursive_sumbetsprofit,
        SUM(agent_profitlost) as agent_profitlost,
        SUM(agent_sumwithdrawals) as agent_sumwithdrawals,
        SUM(agent_sumdeposit) as agent_sumdeposit,
        MAX(agent_memberbet_count) as agent_memberbet_count,
        MAX(agent_memberrecursivebets_count) as agent_memberrecursivebets_count,
        MAX(agent_agent_count) as agent_agent_count,
        MAX(agent_recursive_agent_count) as agent_recursive_agent_count,
        MAX(agent_member_count) as agent_member_count,
        MAX(agent_recursives_count) as agent_recursives_count,
        {$parsing_casino_cate_sql}
        SUM(agent_commission_upper) as agent_commission_upper,
        SUM(agent_commission_lower) as agent_commission_lower,
        SUM(agent_commission) as agent_commission
    FROM root_commission_dailyreport
    LEFT JOIN root_member ON root_member.id = root_commission_dailyreport.member_parent_id
    WHERE dailydate = '$current_datepicker_start' AND end_date  = '$current_datepicker_end'
    GROUP BY member_account
    ORDER BY member_id
SQL;

    $userlist_sql = $userlist_sql_tmp . ' ';

    if ($filter_empty) {
        $userlist_sql_tmp .= "AND (agent_commission != '0' OR agent_recursive_sumbets != '0') ";
    }

    // echo($userlist_sql);die();
    $userlist_result = runSQLAll($userlist_sql);
    // print("<pre>" . print_r($userlist_result, true) . "</pre>");die();
    // die();



    // 分佣合計正值
    $commission_sql = commission_sql($current_datepicker_start, $current_datepicker_end);
    // echo $commission_sql;die();
    $commission_result = runSQLall($commission_sql);
    // 分佣合計負值
    $commission_sql_negitive = commission_sql_negitive($current_datepicker_start, $current_datepicker_end);
    // echo $commission_sql_negitive;die();
    $commission_negitive_result = runSQLall($commission_sql_negitive);
    if($commission_result[0] > 0){
        // 分佣合计(只计算正值)
        $profitamount_pos = $commission_result[1]->agent_commission;
        // 累计到下次分佣的总计(负值)
        $profitamount_negitive = $commission_negitive_result[1]->agent_commission;
        // 分佣总计(正 + 负)
        $profitamount = $profitamount_pos + $profitamount_negitive;

        $total_commission['sum_member_profitamount_pos_html']      = number_format($profitamount_pos, 2, '.', '');
        $total_commission['sum_member_profitamount_negitive_html'] = number_format($profitamount_negitive, 2, '.', '');
        $total_commission['sum_member_profitamount_html']          = number_format($profitamount, 2, '.', '');
    }else{
        $total_commission['sum_member_profitamount_pos_html']      = number_format(0, 2, '.', '');
        $total_commission['sum_member_profitamount_negitive_html'] = number_format(0, 2, '.', '');
        $total_commission['sum_member_profitamount_html']          = number_format(0, 2, '.', '');
    }
    // var_dump($total_commission);die();

    export_agent_profitlost_to_csv(
        $current_datepicker_start,
        $current_datepicker_end,
        $casino_game_categories,
        $userlist_result,
        $total_commission
    );

} elseif ($action == 'profitloss_payout' and isset($_GET['payout_date']) and isset($_GET['payout_end_date']) and isset($_GET['s']) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    if (validateDate($_GET['payout_date'], 'Y-m-d') and validateDate($_GET['payout_end_date'], 'Y-m-d') and isset($_GET['s']) and isset($_GET['s1']) and isset($_GET['s2']) and isset($_GET['s3'])) {
        // 取得獎金的各設定並生成token傳給 cmd 執行
        $bonus_status = filter_var($_GET['s'], FILTER_VALIDATE_INT);
        $bonus_type = filter_var($_GET['s1'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $audit_type = filter_var($_GET['s2'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
        $audit_amount = filter_var($_GET['s3'], FILTER_VALIDATE_INT);
        $audit_ratio = filter_var($_GET['s4'], FILTER_VALIDATE_FLOAT);
        $audit_calculate_type = filter_var($_GET['s5'], FILTER_SANITIZE_STRING);

        $bonusstatus_array = [
            'bonus_status' => $bonus_status,
            'bonus_type' => $bonus_type,
            'audit_type' => $audit_type,
            'audit_amount' => $audit_amount,
            'audit_ratio' => $audit_ratio,
            'audit_calculate_type' => $audit_calculate_type,
        ];
        //var_dump($bonusstatus_array);
        // 產生 token , salt是檢核密碼預設值為123456 ,需要配合 jwtdec 的解碼, 此範例設定為 123456
        $bonus_token = jwtenc('profitlosspayout', $bonusstatus_array);

        $dailydate = $_GET['payout_date'];
        $dailydate_end = $_GET['payout_end_date'];
        $file_key = sha1('profitlosspayout' . $dailydate . $dailydate_end);
        $logfile_name = dirname(__FILE__) . '/tmp_dl/profitloss_' . $file_key . '.tmp';
        if (file_exists($logfile_name)) {
            die('請勿重覆操作');
        } else {
            $command = $config['PHPCLI'].' agent_profitloss_payout_cmd.php run ' . $dailydate . ' ' . $dailydate_end . ' ' . $bonus_token . ' ' . $_SESSION['agent']->account . ' web > ' . $logfile_name . ' &';
            // echo nl2br($command);die();

            // dispatch command and show loading view
            dispatch_proccessing(
                $command,
                '更新中...',
                $_SERVER['PHP_SELF'] . '?a=profitloss_payout_reload&k=' . $file_key,
                $logfile_name
            );
            // 寫入memberlogtodb
            $msg         = $_SESSION['agent']->account.'在娱乐城佣金计算，点选批次发送。日期区间：' . $dailydate . '~' . $dailydate_end . '。'; //客服
            $msg_log     = $msg; //RD
            $sub_service = 'agent_profitloss_calculation';
            memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

        }
    } else {
        $output_html = '日期格式或狀態設定有問題，請確定有日期及狀態設定且格式正確，日期格式需要為 YYYY-MM-DD 的格式';
        echo '<hr><br><br><p align="center">' . $output_html . '</p>';
        echo '<br><br><p align="center"><button type="button" onclick="window.close();">關閉視窗</button></p>';
    }
} elseif ($action == 'profitloss_payout_reload' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/profitloss_' . $logfile_sha . '.tmp';
    if (file_exists($reload_file)) {
        echo file_get_contents($reload_file);
    } else {
        die('(x)不合法的測試');
    }
} elseif ($action == 'profitloss_del' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/profitloss_' . $logfile_sha . '.tmp';
    if (file_exists($reload_file)) {
        unlink($reload_file);
    } else {
        die('(x)不合法的測試');
    }

} elseif ($action == 'test') {
    // -----------------------------------------------------------------------
    // test developer
    // -----------------------------------------------------------------------
    var_dump($_POST);

} elseif (isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $output = array(
        "sEcho" => 0,
        "iTotalRecords" => 0,
        "iTotalDisplayRecords" => 0,
        "data" => '',
    );
    echo json_encode($output);
} else {
    $logger = '(x) 只有管理員或有權限的會員才可以使用。';
    echo $logger;
}
