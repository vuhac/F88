<?php
// ----------------------------------------------------------------------------
// Features:    後台 - 每日營收日結報表動作程式
// File Name:    statistics_daily_report_action.php
// Author:        Barkley
// DB table:  root_statisticsdailyreport  每日營收日結報表
// Related:   每日營收報表, 搭配的程式功能說明
// statistics_daily_immediately.php    後台 - 即時統計 - 每日營收日結報表, 要修改下面的程式增加項目的時候，需要先使用這只程式即時測試函式並驗證。
// statistics_daily_report.php         後台 - 每日營收日結報表(讀取已生成資料庫頁面), 透過 php system 功能呼叫 statistics_daily_output_cmd.php 執行, 主要都從這個程式開始呼叫。
// statistics_daily_report_lib.php     後台 - 每日營收日結報表 - 專用函式庫(計算資料使用函式, 每個統計項目的公式都放這裡)
// statistics_daily_report_action.php  後台 - 每日營收日結報表動作程式 - 透過此程式呼叫 php system command 功能, 及其他後續擴充功能.
// statistics_daily_output_cmd.php     後台 - 每日營收日結報表(命令列模式, 主要用來排程生成日報表)
// command example: /usr/bin/php70 /home/testgpk2demo/web/begpk2/statistics_daily_report_output_cmd.php run 2017-02-26
// Log:
// 2017.2.27 改寫,原本的即時計算移除.以資料庫為主,排程定時統計。
// ----------------------------------------------------------------------------
session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
// require_once dirname(__FILE__) ."/lib.php";

// 投注紀錄檔 DB config 及 runSQLall_DB2 lib -- 搭配日結報表函式庫使用
//require_once dirname(__FILE__) ."/config_betlog.php";
// 日結報表函式庫
// require_once dirname(__FILE__) ."/statistics_report_lib.php";

// xlsx
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


// datatable 回傳驗證用參數，收到後不處理直接跟資料一起回傳給 datatable 做驗證
if (isset($_GET['_'])) {
    $secho = $_GET['_'];
} else {
    $secho = '1';
}

if (isset($_GET['k'])) {
    $logfile_sha = $_GET['k'];
}

// 確保這個 script 執行不會因為 user abort 而中斷!!
// Ignore user aborts and allow the script to run forever
ignore_user_abort(true);
// disable php time limit , 60*60 = 3600sec = 1hr
set_time_limit(7200);

// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -----------------------------------------

if (isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
} else {
    die('(x)不合法的測試');
}

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

// if (isset($_GET['current_datepicker'])) {
//     // 判斷格式資料是否正確
//     if (validateDate($_GET['current_datepicker'], 'Y-m-d')) {
//         $current_datepicker = $_GET['current_datepicker'];
//     } else {
//         // 美東時間的日期為當下的日期
//         $est_date = gmdate('Y-m-d', time()+-4 * 3600);
//         $current_datepicker = $est_date;
//     }
// } else {
//     // php 格式的 2017-02-24
//     // 美東時間的日期為當下的日期
//     $est_date = gmdate('Y-m-d', time()+-4 * 3600);
//     $current_datepicker = $est_date;
// }

$account = '';
$edate = gmdate('Y-m-d', strtotime('now -1 days -4 hours'));
$sdate = $edate;
$only_nonzero = false;
isset($_GET['sdate']) and validateDate($_GET['sdate'], 'Y-m-d') and $sdate = $_GET['sdate'];
isset($_GET['edate']) and validateDate($_GET['edate'], 'Y-m-d') and $edate = $_GET['edate'];
isset($_GET['account']) and $account = filter_input(INPUT_GET, 'account');
isset($_GET['only_nonzero']) and $only_nonzero = filter_input(INPUT_GET, 'only_nonzero', FILTER_VALIDATE_BOOLEAN);

// var_dump($_POST);
// ----------------------------------
// 動作為會員登入檢查, 只有 Root 可以維護。
// ----------------------------------
$output_html = '';
// -----------------------------------------------------------------------------
if ($action == 'cmdtest' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
// -----------------------------------------------------------------------------
    if (isset($_GET['d']) and validateDate($_GET['d'], 'Y-m-d')) {
        $dailydate = $_GET['d'];
    } else {
        $output_html = '日期格式有問題，請確定有且格式正確，需要為 YYYY-MM-DD 的格式';
    }
    // $command      = $config['PHPCLI'].' statistics_daily_report_output_cmd.php run '.$dailydate.'';
    $command = $config['PHPCLI'].' statistics_daily_report_output_cmd.php test ' . $dailydate . '';
    $last_line = system($command, $return_var);
    $output_html = $output_html . $command . "\n";
    echo nl2br($output_html);

// -----------------------------------------------------------------------------
} elseif ($action == 'cmdrun' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
// -----------------------------------------------------------------------------
    if (isset($_GET['d']) and validateDate($_GET['d'], 'Y-m-d')) {
        $dailydate = $_GET['d'];
        $file_key = sha1('dailyreport' . $dailydate);
        $reload_file = dirname(__FILE__) . '/tmp_dl/dailyreport_' . $file_key . '.tmp';

        if (file_exists($reload_file)) {
            // 寫入memberlog
            $msg         = $_SESSION['agent']->account . '更新日报：錯誤。日期：' . $dailydate . '。错误讯息：重复操作。'; //客服
            $msg_log     = $_SESSION['agent']->account . '更新日报：錯誤。日期：' . $dailydate . '。版号：' . $file_key . '。已存在相同檔案：'.$reload_file; //RD
            $sub_service = 'daily_report';
            memberlogtodb($_SESSION['agent']->account, 'marketing', 'error', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

            die('请勿重复操作');
        } else {
            $command = $config['PHPCLI'].' statistics_daily_report_output_cmd.php run ' . $dailydate . ' web >> ' . $reload_file . ' 2>&1 &';
            echo '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){window.location.href="' . $_SERVER['PHP_SELF'] . '?a=update_reload&k=' . $file_key . '"},1000);</script>';
            $output_html = '<p align="center">更新中...<img src="ui/loading.gif" /></p><script>setTimeout(function(){location.reload()},2000);</script>';
            file_put_contents($reload_file, $output_html);
            $last_line = system($command, $return_var);
            //var_dump($return_var);
            //var_dump($last_line);
            $output_html = "執行結果：$dailydate 日期 [$return_var] $last_line ";

            // 寫入memberlog
            $msg         = $_SESSION['agent']->account . '按更新日报。日期：' . $dailydate. '。版号：' . $file_key . '。'; //客服
            $msg_log     = $msg; //RD
            $sub_service = 'daily_report';
            memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);
        }
    } else {
        $output_html = '日期格式有问题，请确定有且格式正确，需要为 YYYY-MM-DD 的格式';
        echo nl2br($output_html);
        echo '<p align="center"><input onclick="window.close();" value="關閉視窗" type="button"><p>';

        // 寫入memberlog
        $msg         = $_SESSION['agent']->account . '更新日报：錯誤。错误讯息：'.$output_html.'。'; //客服
        $msg_log     = $msg; //RD
        $sub_service = 'daily_report';
        memberlogtodb($_SESSION['agent']->account, 'marketing', 'error', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

    }

// -----------------------------------------------------------------------------
} elseif ($action == 'update_reload' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/dailyreport_' . $logfile_sha . '.tmp';
    if (file_exists($reload_file)) {
        echo file_get_contents($reload_file);
    } else {
        die('(x)不合法的測試');
    }
} elseif ($action == 'dailyreport_del' and isset($logfile_sha) and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $reload_file = dirname(__FILE__) . '/tmp_dl/dailyreport_' . $logfile_sha . '.tmp';
    if (file_exists($reload_file)) {
        unlink($reload_file);

        $msg         = $_SESSION['agent']->account . '更新日报：成功。版号：' . $logfile_sha . '。'; //客服
        $msg_log     = $msg; //RD
        $sub_service = 'daily_report';
        memberlogtodb($_SESSION['agent']->account, 'marketing', 'notice', $msg, $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);
    } else {
        die('(x)不合法的測試');
    }
} elseif ($action == 'makecsv' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    // var_dump($action);die();
    // -----------------------------------------------------------------------------
    // 生成 CSV 檔案
    // -----------------------------------------------------------------------
    // 列出所有的會員資料及人數 SQL
    // -----------------------------------------------------------------------
    // 算 root_member 人數
    // $userlist_sql = "SELECT * FROM root_statisticsdailyreport WHERE dailydate = '$current_datepicker' ORDER BY member_id ASC;";

    // 2019/12/6
    /* $userlist_sql =<<<SQL
       SELECT * FROM root_statisticsdailyreport
            WHERE member_therole != 'R'
            AND dailydate = '{$current_datepicker}'
            ORDER BY member_id ASC
    SQL; */

    /* 調整為區間查詢 */
    $userlist_sql = <<<SQL
        WITH member_period_report AS (
            SELECT
                array_agg(id) AS id,
                array_agg(updatetime) AS updatetime,
                member_id,
                member_account,
                -- therole 應移除
                member_therole,
                member_parent_id,
                sum(agency_commission) AS agency_commission,
                sum(mg_totalwager) AS mg_totalwager,
                sum(mg_totalpayout) AS mg_totalpayout,
                sum(mg_profitlost) AS mg_profitlost,
                sum(mg_count) AS mg_count,
                sum(pt_bets) AS pt_bets,
                sum(pt_wins) AS pt_wins,
                sum(pt_profitlost) AS pt_profitlost,
                sum(pt_jackpotbets) AS pt_jackpotbets,
                sum(pt_jackpotwins) AS pt_jackpotwins,
                sum(pt_jackpot_profitlost) AS pt_jackpot_profitlost,
                sum(pt_count) AS pt_count,
                sum(all_bets) AS all_bets,
                sum(all_wins) AS all_wins,
                sum(all_profitlost) AS all_profitlost,
                sum(all_count) AS all_count,
                sum(cashdeposit) AS cashdeposit,
                sum(payonlinedeposit) AS payonlinedeposit,
                sum(apicashdeposit) AS apicashdeposit,
                sum(cashtransfer) AS cashtransfer,
                sum(cashwithdrawal) AS cashwithdrawal,
                sum(cashgtoken) AS cashgtoken,
                sum(apitokendeposit) AS apitokendeposit,
                sum(tokendeposit) AS tokendeposit,
                sum(tokenfavorable) AS tokenfavorable,
                sum(tokenpreferential) AS tokenpreferential,
                sum(tokenpay) AS tokenpay,
                sum(tokengcash) AS tokengcash,
                sum(tokenrecycling) AS tokenrecycling,
                sum(cashadministrationfees) AS cashadministrationfees,
                sum(tokenadministrationfees) AS tokenadministrationfees,
                sum(tokenadministration) AS tokenadministration,
                sum(company_deposits) AS company_deposits,
                sum(member_gcash) AS member_gcash,
                sum(member_gtoken) AS member_gtoken,
                array_agg(dailydate) AS dailydate
            FROM root_statisticsdailyreport
            WHERE member_therole != 'R'
            AND dailydate BETWEEN '$sdate' AND '$edate'
            %1\$s
            GROUP BY member_account, member_id, member_therole, member_parent_id
            %2\$s
        )
        --
        SELECT member_daily_report.*,parent_member.account AS parent_account
        FROM member_period_report AS member_daily_report
        JOIN root_member AS parent_member
        ON member_daily_report.member_parent_id = parent_member.id
        ORDER BY member_id ASC
    SQL;

    $nonzero_sql = <<<SQL
        HAVING
            sum(agency_commission) != 0 OR
            sum(mg_totalwager) != 0 OR
            sum(mg_totalpayout) != 0 OR
            sum(mg_profitlost) != 0 OR
            sum(mg_count) != 0 OR
            sum(pt_bets) != 0 OR
            sum(pt_wins) != 0 OR
            sum(pt_profitlost) != 0 OR
            sum(pt_jackpotbets) != 0 OR
            sum(pt_jackpotwins) != 0 OR
            sum(pt_jackpot_profitlost) != 0 OR
            sum(pt_count) != 0 OR
            sum(all_bets) != 0 OR
            sum(all_wins) != 0 OR
            sum(all_profitlost) != 0 OR
            sum(all_count) != 0 OR
            sum(cashdeposit) != 0 OR
            sum(payonlinedeposit) != 0 OR
            sum(apicashdeposit) != 0 OR
            sum(cashtransfer) != 0 OR
            sum(cashwithdrawal) != 0 OR
            sum(cashgtoken) != 0 OR
            sum(apitokendeposit) != 0 OR
            sum(tokendeposit) != 0 OR
            sum(tokenfavorable) != 0 OR
            sum(tokenpreferential) != 0 OR
            sum(tokenpay) != 0 OR
            sum(tokengcash) != 0 OR
            sum(tokenrecycling) != 0 OR
            sum(cashadministrationfees) != 0 OR
            sum(tokenadministrationfees) != 0 OR
            sum(tokenadministration) != 0 OR
            sum(company_deposits) != 0 OR
            sum(member_gcash) != 0 OR
            sum(member_gtoken) != 0
    SQL;

    $userlist_sql = sprintf(
        $userlist_sql,
        !$account ? '' : "AND member_account = '$account'",
        /* 預留，過濾全部為 0 的資料*/
        $only_nonzero ? $nonzero_sql : ''
    );

    // 取出 root_member 資料
    $userlist = runSQLall($userlist_sql);

    // 判斷 root_member count 數量大於 1
    if ($userlist[0] >= 1) {
        // -------------------------
        // 2019/12/2
        // csv轉excel
        $k = $c = 1;

        $xlsx_statistics_dailt_report_query[0][$c++] = 'ID';//'会员ID';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['member upper id'];// '会员上层ID';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['Member Identity'];//'会员身份';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['member'].' '.$tr['Account']; //'会员帐号';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['Franchise Fee'];//'加盟金';

        // $xlsx_statistics_dailt_report_query[0][$c++] = 'MG投注量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'MG派彩量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'MG損益量';

        // $xlsx_statistics_dailt_report_query[0][$c++] = 'PT投注量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'PT派彩量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'PT損益量';

        // $xlsx_statistics_dailt_report_query[0][$c++] = 'MEGA投注量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'MEGA派彩量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'MEGA損益量';

        // $xlsx_statistics_dailt_report_query[0][$c++] = 'IG投注量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'IG派彩量';
        // $xlsx_statistics_dailt_report_query[0][$c++] = 'IG損益量';

        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['total betting'];//'总投注量';
        $xlsx_statistics_dailt_report_query[0][$c++] = '总派彩量';
        $xlsx_statistics_dailt_report_query[0][$c++] = '总损益量';
        $xlsx_statistics_dailt_report_query[0][$c++] = '总注单量';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['cash deposit'];//'现金存款';

        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['electronic payment deposit'];//'电子支付存款';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['cash transfer'];//'现金转帐';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['cash withdrawal'];//'现金提款';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['cash to tokens'];//'现金转代币';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['Token deposit'];//'代币存款';

        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['token discount'];//'代币优惠';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['token bouns'];//'代币反水';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['Token Payout'];//'代币派彩';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['token to cash'];//'代币转现金';
        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['token recovery'];//'代币回收';

        $xlsx_statistics_dailt_report_query[0][$c++] = $tr['cash withdrawal'].$tr['Fee'];// '现金提款手续费';
        $xlsx_statistics_dailt_report_query[0][$c++] = '游戏币取款行政手续费';
        $xlsx_statistics_dailt_report_query[0][$c++] = '行政稽核不通过费用';

        for($i =1;$i <= $userlist[0]; $i++){
            $c = 1;

            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->member_id;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->member_parent_id;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->member_therole;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->member_account;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->agency_commission;

            // mg
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->mg_totalwager;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->mg_totalpayout;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->mg_profitlost;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->mg_count;

            // pt
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->pt_bets;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->pt_wins;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->pt_profitlost;

            // mega
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->mega_bets;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->mega_wins;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->mega_profitlost;

            // ig
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->ig_bets;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->ig_wins;
            // $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->ig_profitlost;

            // CASINO 的統計
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->all_bets;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->all_wins;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->all_profitlost;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->all_count;

            // cashdeposit 現金存款量
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->cashdeposit;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->payonlinedeposit;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->cashtransfer;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->cashwithdrawal;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->cashgtoken;

            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokendeposit;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokenfavorable;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokenpreferential;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokenpay;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokengcash;

            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokenrecycling;

            // 手續費
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->cashadministrationfees;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokenadministrationfees;
            $xlsx_statistics_dailt_report_query[$i][$c++] = $userlist[$i]->tokenadministration;
        };
        // var_dump($xlsx_statistics_dailt_report_query);die();

    }else{
        echo $csv_download_url_html = 'EXCEL建立失败！！';

        die();
    };

     // 清除快取防亂碼
     ob_end_clean();

     $spredsheet = new Spreadsheet();

     $myworksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spredsheet, '每日营收日结报表');

     // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
     $spredsheet->addSheet($myworksheet, 0);

     // 總表索引標籤開始寫入資料
     $sheet = $spredsheet->setActiveSheetIndex(0);
     // 寫入資料陣列
     $sheet->fromArray($xlsx_statistics_dailt_report_query,NULL,'A1',true);

     // 自動欄寬
     $worksheet = $spredsheet->getActiveSheet();

     foreach (range('A', $worksheet->getHighestColumn()) as $column) {
        $spredsheet->getActiveSheet()->getColumnDimension($column)->setAutoSize(true);
     };

     // 檔案名稱
    //  $filename = "daily_report_result_".$current_datepicker;
    $filename = "daily_report_result_{$sdate}_to_{$edate}";
     $absfilename = "./tmp_dl/".$filename.".xlsx";

     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
     header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
     header('Cache-Control: max-age=0');

     // 直接匯出，不存於disk
     $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spredsheet, 'Xlsx');
    //  $writer->save('php://output');
    $writer->save($absfilename);

    // -------------------------------------------
    // 下載按鈕
    // // -------------------------------------------
    if (file_exists($absfilename)) {
        $csv_download_url_html = '<a href="'.$absfilename . '" class="btn btn-success btn-sm" >'.$tr['download'].' '.'EXCEL</a>';
    };
    echo  $csv_download_url_html;

    // die();

    //----------------- 原版------------------------------
    // if ($userlist[0] >= 1) {

    //     // // 以會員為主要 key 依序列出每個會員的貢獻金額
    //     for ($i = 1; $i <= $userlist[0]; $i++) {
    //         // ----------------------------------------------------
    //         // 寫入 CSV 檔案, 先產生一組 key 來處理
    //         $csv_key = 'dailyreport' . $userlist[$i]->member_id . $current_datepicker;
    //         $csv_key_sha1 = sha1($csv_key);
    //         $v = 1;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_id;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_parent_id;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_therole;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->member_account;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->agency_commission;

    //         // MG
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->mg_totalwager;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->mg_totalpayout;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->mg_profitlost;
    //         //$csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->mg_count;

    //         //PT
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->pt_bets;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->pt_wins;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->pt_profitlost;

    //         //MEGA
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->mega_bets;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->mega_wins;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->mega_profitlost;

    //         //IG
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->ig_bets;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->ig_wins;
    //         // $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->ig_profitlost;

    //         // CASINO 的統計
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->all_bets;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->all_wins;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->all_profitlost;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->all_count;

    //         // cashdeposit 現金存款量
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->cashdeposit;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->payonlinedeposit;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->cashtransfer;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->cashwithdrawal;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->cashgtoken;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokendeposit;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokenfavorable;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokenpreferential;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokenpay;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokengcash;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokenrecycling;

    //         // 手續費
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->cashadministrationfees;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokenadministrationfees;
    //         $csv_array[$csv_key_sha1][$i][$v++] = $userlist[$i]->tokenadministration;

    //     }
    //     // // -------------------------------------------
    //     // // 寫入 CSV 檔案的抬頭
    //     // // -------------------------------------------
    //     $v = 1;
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '會員ID';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '會員上層ID';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '會員身份';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '會員帳號';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '加盟金';

    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'MG投注量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'MG派彩量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'MG損益量';

    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'PT投注量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'PT派彩量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'PT損益量';

    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'MEGA投注量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'MEGA派彩量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'MEGA損益量';

    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'IG投注量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'IG派彩量';
    //     // $csv_key_title[$csv_key_sha1][$i][$v++] = 'IG損益量';

    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '總投注量';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '總派彩量';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '總損益量';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '總注單量';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '現金存款';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '電子支付存款';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '現金轉帳';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '現金提款';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '現金轉代幣';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '代幣存款';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '代幣優惠';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '代幣反水';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '代幣派彩';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '代幣轉現金';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '代幣回收';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '現金提款手續費';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '代幣提款行政手續費';
    //     $csv_key_title[$csv_key_sha1][$i][$v++] = '行政稽核不通過費用';
    //     // -------------------------------------------

    //     // -------------------------------------------
    //     // var_dump($bonus_commission_array);
    //     // 將內容輸出到 檔案 , csv format
    //     // -------------------------------------------
    //     $filename = "daily_report_result_" .$current_datepicker.'.csv';
    //     $absfilename = dirname(__FILE__) . "/tmp_dl/".$filename;

    //     $filehandle = fopen("$absfilename", "w");
    //     if ($filehandle != false) {
    //         // Windows下使用BOM来标记文本文件的编码方式, 否則 EXCEL 開啟這個檔案會是亂碼
    //         fwrite($filehandle, chr(0xEF) . chr(0xBB) . chr(0xBF));
    //         // -------------------------------------------
    //         // 將資料輸出到檔案 -- Title
    //         foreach ($csv_key_title as $wline) {
    //             foreach ($wline as $line) {
    //                 fputcsv($filehandle, $line);
    //             }
    //         }
    //         // 將資料輸出到檔案 -- data
    //         foreach ($csv_array as $wline) {
    //             foreach ($wline as $line) {
    //                 fputcsv($filehandle, $line);
    //             }
    //         }
    //         // 將資料輸出到檔案 -- Title
    //         foreach ($csv_key_title as $wline) {
    //             foreach ($wline as $line) {
    //                 fputcsv($filehandle, $line);
    //             }
    //         }
    //     }
    //     fclose($filehandle);

    // }

    // -------------------------------------------
    // 下載按鈕
    // -------------------------------------------
    // $filename = "daily_report_result_" . $current_datepicker . '.csv';
    // $absfilename = dirname(__FILE__) . "/tmp_dl/".$filename;

    // if (file_exists($absfilename)) {
    //     // $csv_download_url_html = '<a href="./tmp_dl/' . $filename . '" class="btn btn-success" >下載CSV</a>';
    //     $csv_download_url_html = '<a href="./tmp_dl/' . $filename . '" class="btn btn-success" >下載EXCEL</a>';


    // } else {
    //     // 原版
    //     // $csv_download_url_html = 'CSV 建立失敗！！';

    //     $csv_download_url_html = 'EXCEL建立失敗！！';
    // }

    // echo $csv_download_url_html;

    // -----------------------------------------------------------------------------
} elseif ($action == 'reload_dailyreport' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    // -----------------------------------------------------------------------
    // datatable server process 用資料讀取
    // -----------------------------------------------------------------------

    // -----------------------------------------------------------------------
    // 列出所有的會員資料及人數 SQL
    // -----------------------------------------------------------------------
    // 算 root_member 人數
//     $userlist_sql_tmp = <<<SQL
//         SELECT
//             member_daily_report.*,
//             parent_member.account AS parent_account
//         FROM root_statisticsdailyreport AS member_daily_report
//         JOIN root_member AS parent_member ON member_daily_report.member_parent_id = parent_member.id
//         WHERE member_daily_report.dailydate = '$current_datepicker'
// SQL;

    // 2019/12/9
    /* $userlist_sql_tmp =<<<SQL
        SELECT member_daily_report.*,parent_member.account AS parent_account
        FROM root_statisticsdailyreport AS member_daily_report
        JOIN root_member AS parent_member
        ON member_daily_report.member_parent_id = parent_member.id
        WHERE member_daily_report.member_therole != 'R'
        -- AND member_daily_report.dailydate = '{current_datepicker}'
        AND member_daily_report.dailydate BETWEEN '$sdate' AND '$edate'
    SQL; */

    /* 調整為區間查詢 */
    $userlist_sql_tmp = <<<SQL
        WITH member_period_report AS (
            SELECT
                array_agg(id) AS id,
                array_agg(updatetime) AS updatetime,
                member_id,
                member_account,
                -- therole 應移除
                member_therole,
                member_parent_id,
                sum(agency_commission) AS agency_commission,
                sum(mg_totalwager) AS mg_totalwager,
                sum(mg_totalpayout) AS mg_totalpayout,
                sum(mg_profitlost) AS mg_profitlost,
                sum(mg_count) AS mg_count,
                sum(pt_bets) AS pt_bets,
                sum(pt_wins) AS pt_wins,
                sum(pt_profitlost) AS pt_profitlost,
                sum(pt_jackpotbets) AS pt_jackpotbets,
                sum(pt_jackpotwins) AS pt_jackpotwins,
                sum(pt_jackpot_profitlost) AS pt_jackpot_profitlost,
                sum(pt_count) AS pt_count,
                sum(all_bets) AS all_bets,
                sum(all_wins) AS all_wins,
                sum(all_profitlost) AS all_profitlost,
                sum(all_count) AS all_count,
                sum(cashdeposit) AS cashdeposit,
                sum(payonlinedeposit) AS payonlinedeposit,
                sum(apicashdeposit) AS apicashdeposit,
                sum(cashtransfer) AS cashtransfer,
                sum(cashwithdrawal) AS cashwithdrawal,
                sum(cashgtoken) AS cashgtoken,
                sum(apitokendeposit) AS apitokendeposit,
                sum(tokendeposit) AS tokendeposit,
                sum(tokenfavorable) AS tokenfavorable,
                sum(tokenpreferential) AS tokenpreferential,
                sum(tokenpay) AS tokenpay,
                sum(tokengcash) AS tokengcash,
                sum(tokenrecycling) AS tokenrecycling,
                sum(cashadministrationfees) AS cashadministrationfees,
                sum(tokenadministrationfees) AS tokenadministrationfees,
                sum(tokenadministration) AS tokenadministration,
                sum(company_deposits) AS company_deposits,
                sum(member_gcash) AS member_gcash,
                sum(member_gtoken) AS member_gtoken,
                array_agg(dailydate) AS dailydate
            FROM root_statisticsdailyreport
            WHERE member_therole != 'R'
            AND dailydate BETWEEN '$sdate' AND '$edate'
            %1\$s
            GROUP BY member_account, member_id, member_therole, member_parent_id
            %2\$s
        )
        --
        SELECT member_daily_report.*,parent_member.account AS parent_account
        FROM member_period_report AS member_daily_report
        JOIN root_member AS parent_member
        ON member_daily_report.member_parent_id = parent_member.id
    SQL;

    $nonzero_sql = <<<SQL
        HAVING
            sum(agency_commission) != 0 OR
            sum(mg_totalwager) != 0 OR
            sum(mg_totalpayout) != 0 OR
            sum(mg_profitlost) != 0 OR
            sum(mg_count) != 0 OR
            sum(pt_bets) != 0 OR
            sum(pt_wins) != 0 OR
            sum(pt_profitlost) != 0 OR
            sum(pt_jackpotbets) != 0 OR
            sum(pt_jackpotwins) != 0 OR
            sum(pt_jackpot_profitlost) != 0 OR
            sum(pt_count) != 0 OR
            sum(all_bets) != 0 OR
            sum(all_wins) != 0 OR
            sum(all_profitlost) != 0 OR
            sum(all_count) != 0 OR
            sum(cashdeposit) != 0 OR
            sum(payonlinedeposit) != 0 OR
            sum(apicashdeposit) != 0 OR
            sum(cashtransfer) != 0 OR
            sum(cashwithdrawal) != 0 OR
            sum(cashgtoken) != 0 OR
            sum(apitokendeposit) != 0 OR
            sum(tokendeposit) != 0 OR
            sum(tokenfavorable) != 0 OR
            sum(tokenpreferential) != 0 OR
            sum(tokenpay) != 0 OR
            sum(tokengcash) != 0 OR
            sum(tokenrecycling) != 0 OR
            sum(cashadministrationfees) != 0 OR
            sum(tokenadministrationfees) != 0 OR
            sum(tokenadministration) != 0 OR
            sum(company_deposits) != 0 OR
            sum(member_gcash) != 0 OR
            sum(member_gtoken) != 0
    SQL;

    $userlist_sql_tmp = sprintf(
        $userlist_sql_tmp,
        !$account ? '' : "AND member_account = '$account'",
        /* 預留，過濾全部為 0 的資料*/
        $only_nonzero ? $nonzero_sql : ''
    );

    $userlist_sql = $userlist_sql_tmp . ';';
    // var_dump($userlist_sql);
    $userlist_count = runSQL($userlist_sql);

    // -----------------------------------------------------------------------
    // 分頁處理機制
    // -----------------------------------------------------------------------
    // 所有紀錄數量
    $page['all_records'] = $userlist_count;
    // 每頁顯示多少
    $page['per_size'] = $current_per_size;
    // 目前所在頁數
    $page['no'] = $current_page_no;
    // var_dump($page);

    // 處理 datatables 傳來的排序需求
    if (isset($_GET['order'][0]) and $_GET['order'][0]['column'] != '') {
        if ($_GET['order'][0]['dir'] == 'asc') {$sql_order_dir = 'ASC';
        } else { $sql_order_dir = 'DESC';}
        if ($_GET['order'][0]['column'] == 0) {$sql_order = 'ORDER BY member_daily_report.member_id ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 1) {$sql_order = 'ORDER BY member_daily_report.member_account ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 2) {$sql_order = 'ORDER BY member_daily_report.member_therole ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 3) {$sql_order = 'ORDER BY parent_account ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 4) {$sql_order = 'ORDER BY member_daily_report.all_bets ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 5) {$sql_order = 'ORDER BY member_daily_report.all_profitlost ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 6) {$sql_order = 'ORDER BY member_daily_report.all_count ' . $sql_order_dir;

        } elseif ($_GET['order'][0]['column'] == 7) {$sql_order = 'ORDER BY member_daily_report.payonlinedeposit ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 8) {$sql_order = 'ORDER BY member_daily_report.company_deposits ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 9) {$sql_order = 'ORDER BY member_daily_report.cashdeposit ' . $sql_order_dir;

        } elseif ($_GET['order'][0]['column'] == 10) {$sql_order = 'ORDER BY member_daily_report.cashwithdrawal ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 11) {$sql_order = 'ORDER BY member_daily_report.tokengcash ' . $sql_order_dir;

        } elseif ($_GET['order'][0]['column'] == 12) {$sql_order = 'ORDER BY member_daily_report.cashtransfer ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 13) {$sql_order = 'ORDER BY member_daily_report.tokenfavorable ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 14) {$sql_order = 'ORDER BY member_daily_report.tokenpreferential ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 15) {$sql_order = 'ORDER BY member_daily_report.tokenpreferential ' . $sql_order_dir;

        } elseif ($_GET['order'][0]['column'] == 16) {$sql_order = 'ORDER BY member_daily_report.tokenadministrationfees ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 17) {$sql_order = 'ORDER BY member_daily_report.tokenadministration ' . $sql_order_dir;

        } elseif ($_GET['order'][0]['column'] == 18) {$sql_order = 'ORDER BY member_daily_report.agency_commission ' . $sql_order_dir;

        } elseif ($_GET['order'][0]['column'] == 19) {$sql_order = 'ORDER BY member_daily_report.member_gcash ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 20) {$sql_order = 'ORDER BY member_daily_report.member_gtoken ' . $sql_order_dir;

        } elseif ($_GET['order'][0]['column'] == 21) {$sql_order = 'ORDER BY member_daily_report.cashgtoken ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 22) {$sql_order = 'ORDER BY member_daily_report.tokendeposit ' . $sql_order_dir;
        } elseif ($_GET['order'][0]['column'] == 23) {$sql_order = 'ORDER BY member_daily_report.tokenrecycling ' . $sql_order_dir;

        } else { $sql_order = 'ORDER BY member_daily_report.member_id ASC';}
    } else { $sql_order = 'ORDER BY member_daily_report.member_id ASC';}
    // 取出 root_member 資料
    $userlist_sql = $userlist_sql_tmp . " " . $sql_order . " OFFSET " . $page['no'] . " LIMIT " . $page['per_size'] . " ;";
    // var_dump($userlist_sql);
    $userlist = runSQLall($userlist_sql);

    // 判斷 root_member count 數量大於 1
    if ($userlist[0] >= 1) {
        // 以會員為主要 key 依序列出每個會員的貢獻金額
        for ($i = 1; $i <= $userlist[0]; $i++) {
            $b['id'] = $userlist[$i]->id;
            $b['updatetime'] = $userlist[$i]->updatetime;
            $b['member_id'] = $userlist[$i]->member_id;
            $b['member_account'] = $userlist[$i]->member_account;
            $b['member_therole'] = $userlist[$i]->member_therole;
            $b['member_parent_id'] = $userlist[$i]->member_parent_id;
            $b['agent_review_reult'] = $userlist[$i]->agency_commission;

            // MG
            $b['mg_totalwager'] = $userlist[$i]->mg_totalwager;
            $b['mg_totalpayout'] = $userlist[$i]->mg_totalpayout;
            $b['mg_profitlost'] = $userlist[$i]->mg_profitlost;
            $b['mg_count'] = $userlist[$i]->mg_count;

            //PT
            $b['pt_bets'] = $userlist[$i]->pt_bets;
            $b['pt_wins'] = $userlist[$i]->pt_wins;
            $b['pt_profitlost'] = $userlist[$i]->pt_profitlost;
            $b['pt_jackpotbets'] = $userlist[$i]->pt_jackpotbets;
            $b['pt_jackpotwins'] = $userlist[$i]->pt_jackpotwins;
            $b['pt_jackpot_profitlost'] = $userlist[$i]->pt_jackpot_profitlost;
            $b['pt_count'] = $userlist[$i]->pt_count;

            // CASINO 的統計
            $b['casino_all_bets'] = $userlist[$i]->all_bets;
            $b['casino_all_wins'] = $userlist[$i]->all_wins;
            $b['casino_all_profitlost'] = $userlist[$i]->all_profitlost;
            $b['casino_all_count'] = $userlist[$i]->all_count;

            // cashdeposit 現金存款量
            $b['gcash_cashdeposit'] = $userlist[$i]->cashdeposit;
            $b['gcash_payonlinedeposit'] = $userlist[$i]->payonlinedeposit;
            $b['gcash_apicashdeposit'] = $userlist[$i]->apicashdeposit;
            $b['gcash_cashtransfer'] = $userlist[$i]->cashtransfer;
            $b['gcash_cashwithdrawal'] = $userlist[$i]->cashwithdrawal;
            $b['gcash_cashgtoken'] = $userlist[$i]->cashgtoken;
            $b['gtoken_apitokendeposit'] = $userlist[$i]->apitokendeposit;
            $b['gtoken_tokendeposit'] = $userlist[$i]->tokendeposit;
            $b['gtoken_tokenfavorable'] = $userlist[$i]->tokenfavorable;
            $b['gtoken_tokenpreferential'] = $userlist[$i]->tokenpreferential;
            $b['gtoken_tokenpay'] = $userlist[$i]->tokenpay;
            $b['gtoken_tokengcash'] = $userlist[$i]->tokengcash;
            $b['gtoken_tokenrecycling'] = $userlist[$i]->tokenrecycling;

            // 手續費
            $b['gtokenpassbook_cashadministrationfees'] = $userlist[$i]->cashadministrationfees;
            $b['gtokenpassbook_tokenadministrationfees'] = $userlist[$i]->tokenadministrationfees;
            $b['gtokenpassbook_tokenadministration'] = $userlist[$i]->tokenadministration;

            // 顯示的表格資料內容
            $show_listrow_array[] = [
                'id' => $b['member_id'],
                'parent' => $b['member_parent_id'] === $config['system_company_id'] ? '-' : $b['member_parent_id'],
                'parent_account' => $userlist[$i]->parent_account === $config['system_company_account'] ? '-' : $userlist[$i]->parent_account,
                'therole' => $b['member_therole'],
                'account' => $b['member_account'],
                'agent_review_reult' => $b['agent_review_reult'],
                'bettingrecords_mg_totalwager' => $b['mg_totalwager'],
                'bettingrecords_mg_totalpayout' => $b['mg_totalpayout'],
                'bettingrecords_mg_profitlost' => $b['mg_profitlost'],
                'casino_all_bets' => $b['casino_all_bets'],
                'casino_all_wins' => $b['casino_all_wins'],
                'casino_all_profitlost' => $b['casino_all_profitlost'],
                'casino_all_count' => $b['casino_all_count'],
                'api_deposits' => sprintf("%.2f", $b['gcash_apicashdeposit'] + $b['gtoken_apitokendeposit']),
                'company_deposits' => $userlist[$i]->company_deposits,
                'gcash_cashdeposit' => $b['gcash_cashdeposit'],
                'gcash_apicashdeposit' => $b['gcash_apicashdeposit'],
                'gcash_payonlinedeposit' => $b['gcash_payonlinedeposit'],
                'gcash_cashtransfer' => $b['gcash_cashtransfer'],
                'gcash_cashwithdrawal' => $b['gcash_cashwithdrawal'],
                'gcash_cashgtoken' => $b['gcash_cashgtoken'],

                'gtoken_apitokendeposit' => $b['gtoken_apitokendeposit'],
                'gtoken_tokendeposit' => $b['gtoken_tokendeposit'],
                'gtoken_tokenfavorable' => $b['gtoken_tokenfavorable'],
                'gtoken_tokenpreferential' => $b['gtoken_tokenpreferential'],
                'gtoken_tokenpay' => $b['gtoken_tokenpay'],
                'gtoken_tokengcash' => $b['gtoken_tokengcash'],
                'gtoken_tokenrecycling' => $b['gtoken_tokenrecycling'],
                'gtokenpassbook_cashadministrationfees' => $b['gtokenpassbook_cashadministrationfees'],
                'gtokenpassbook_tokenadministrationfees' => $b['gtokenpassbook_tokenadministrationfees'],
                'gtokenpassbook_tokenadministration' => $b['gtokenpassbook_tokenadministration'],

                'cash_fee' => 0,
                'member_gcash' => $userlist[$i]->member_gcash,
                'member_gtoken' => $userlist[$i]->member_gtoken,
            ];
        }
        $output = array(
            "sEcho" => intval($secho),
            "iTotalRecords" => intval($page['per_size']),
            "iTotalDisplayRecords" => intval($userlist_count),
            "data" => $show_listrow_array,
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
            "data" => '',
        );
    }
    // end member sql
    echo json_encode($output);
    // -----------------------------------------------------------------------
    // datatable server process 用資料讀取
    // -----------------------------------------------------------------------
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

// -----------------------------------------------------------------------------
