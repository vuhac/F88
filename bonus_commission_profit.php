<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 放射線組織加盟金計算 -- 營運利潤獎金
// File Name:	bonus_commission_profit.php
// Author:    Bakley Fix By Ian
// Modifier：Damocles
// Related:
// DB table: root_statisticsbonusprofit  營運利潤獎金
// Log:
// 將營運日報的資料，整理成為會員獎金分紅的報表，並且輸出成為資料表存放。
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


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title     = '放射线组织奖金计算-营运利润奖金';

// 擴充 head 內的 css or js
$extend_head        = '';

// 放在結尾的 js
$extend_js          = '';

// body 內的主要內容
$indexbody_content	= '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = <<<HTML
    <ol class="breadcrumb">
        <li><a href="home.php">首頁</a></li>
        <li><a href="#">營收與行銷</a></li>
        <li class="active">{$function_title}</li>
    </ol>
HTML;
// ----------------------------------------------------------------------------


// debug on = 1 , off =0
$debug = 0;

// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// 此報表的摘要函式, 指定查詢月份的統計結算列表
// ---------------------------------------------------------------------------
function summary_report( $current_datepicker_start, $current_datepicker_end ){
    global $rule;

    // 統計區間
    $current_daterange_html = $current_datepicker_start.'~'.$current_datepicker_end;

    // 列出系統資料統計月份 , 分紅利潤不分盈虧
    $list_sql = <<<SQL
        SELECT dailydate_start,
               dailydate_end,
               MIN(updatetime) as min,
               MAX(updatetime) as max,
               count(member_account) as member_account_count,
               sum(sum_all_profitlost) as sum_sum_all_profitlost,
               sum(profit_amount) as sum_profit_amount,
               sum(sum_all_bets) as sum_sum_all_bets,
               sum(sum_all_count) as sum_sum_all_count,
               sum(member_profitlost_platformcost) as sum_member_profitlost_platformcost,
               sum(member_profitlost_cashcost) as sum_member_profitlost_cashcost,
               sum(member_profitlost_marketingcost) as sum_member_profitlost_marketingcost,
               sum(member_profitamount) as sum_member_profitamount
        FROM root_statisticsbonusprofit
        WHERE dailydate_start = '{$current_datepicker_start}' AND
              dailydate_end = '{$current_datepicker_end}'
        GROUP BY dailydate_end,
                 dailydate_start
        ORDER BY dailydate_start DESC;
    SQL; // echo '<p>列出系統資料統計月份 , 分紅利潤不分盈虧</p><pre>', var_dump($list_sql), '<pre>'; exit();
    $list_result = runSQLall($list_sql); // echo '<pre>', var_dump($list_result), '</pre>'; exit();
    /*
        ["dailydate_start"]=>
        string(10) "2019-08-31"
        ["dailydate_end"]=>
        string(10) "2019-09-30"
        ["min"]=>
        string(29) "2019-09-01 13:47:21.242082+08"
        ["max"]=>
        string(29) "2019-09-01 13:51:37.154735+08"
        ["member_account_count"]=>
        int(1249)
        ["sum_sum_all_profitlost"]=>
        string(9) "201684.87"
        ["sum_profit_amount"]=>
        string(9) "201684.87"
        ["sum_sum_all_bets"]=>
        string(10) "5918571.00"
        ["sum_sum_all_count"]=>
        string(4) "5159"
        ["sum_member_profitlost_platformcost"]=>
        string(4) "0.00"
        ["sum_member_profitlost_cashcost"]=>
        string(4) "0.00"
        ["sum_member_profitlost_marketingcost"]=>
        string(4) "0.00"
        ["sum_member_profitamount"]=>
        string(8) "16085.72"
    */


    // 列出系統資料統計月份, 分紅利潤只列 > 0 的數據
    $profit_sql = <<<SQL
        SELECT dailydate_start,
                dailydate_end,
                MIN(updatetime) as min,
                MAX(updatetime) as max,
                count(member_account) as member_account_count,
                sum(sum_all_profitlost) as sum_sum_all_profitlost,
                sum(profit_amount) as sum_profit_amount,
                sum(sum_all_bets) as sum_sum_all_bets,
                sum(sum_all_count) as sum_sum_all_count,
                sum(member_profitlost_platformcost) as sum_member_profitlost_platformcost,
                sum(member_profitlost_cashcost) as sum_member_profitlost_cashcost,
                sum(member_profitlost_marketingcost) as sum_member_profitlost_marketingcost,
                sum(member_profitamount) as sum_member_profitamount
        FROM root_statisticsbonusprofit
        WHERE dailydate_start = '{$current_datepicker_start}' AND
                dailydate_end = '{$current_datepicker_end}'  AND
                member_profitamount > 0
        GROUP BY dailydate_end,
                    dailydate_start
        ORDER BY dailydate_start DESC;
    SQL; // echo '<p>列出系統資料統計月份, 分紅利潤只列 > 0 的數據</p><pre>', var_dump($profit_sql), '<pre>'; exit();
    $profit_result = runSQLall($profit_sql); // echo '<pre>', var_dump($profit_result), '</pre>'; exit();
    /*
        ["dailydate_start"]=>
        string(10) "2019-08-31"
        ["dailydate_end"]=>
        string(10) "2019-09-30"
        ["min"]=>
        string(29) "2019-09-01 13:47:26.256977+08"
        ["max"]=>
        string(29) "2019-09-01 13:50:37.560927+08"
        ["member_account_count"]=>
        int(4)
        ["sum_sum_all_profitlost"]=>
        string(8) "75567.94"
        ["sum_profit_amount"]=>
        string(8) "75567.94"
        ["sum_sum_all_bets"]=>
        string(9) "632086.00"
        ["sum_sum_all_count"]=>
        string(3) "594"
        ["sum_member_profitlost_platformcost"]=>
        string(4) "0.00"
        ["sum_member_profitlost_cashcost"]=>
        string(4) "0.00"
        ["sum_member_profitlost_marketingcost"]=>
        string(4) "0.00"
        ["sum_member_profitamount"]=>
        string(8) "18590.77"
    */


    // 從日報表得到的損益值 -- 驗算合計
    $dailydate_sql = <<<SQL
        SELECT sum(mg_totalwager) as sum_mg_totalwager,
               sum(mg_totalpayout) as sum_mg_totalpayout,
               sum(mg_profitlost) as sum_mg_profitlost,
               sum(tokenfavorable) as sum_tokenfavorable,
               sum(tokenpreferential) as sum_tokenpreferential,
               sum(all_bets) as sum_all_bets,
               sum(all_wins) as sum_all_wins,
               sum(all_profitlost) as sum_all_profitlost,
               count(all_profitlost) as days_count,
               sum(all_count) as sum_all_count,
               sum(cashadministrationfees) as cashadministrationfees_sum,
               sum(tokenadministrationfees) as tokenadministrationfees_sum,
               sum(tokenadministration) as tokenadministration_sum,
               sum(payonlinedeposit) as payonlinedeposit_sum,
               sum(tokendeposit) as tokendeposit_sum
        FROM root_statisticsdailyreport
        WHERE dailydate >= '{$current_datepicker_start}' AND
              dailydate <= '{$current_datepicker_end}';
    SQL; // echo '<p>從日報表得到的損益值 -- 驗算合計</p><pre>', var_dump($dailydate_sql), '<pre>'; exit();
    $dailydate_result = runSQLall($dailydate_sql); // echo '<pre>', var_dump($dailydate_result), '</pre>'; exit();
    /*
        ["sum_mg_totalwager"]=>
        string(4) "0.00"
        ["sum_mg_totalpayout"]=>
        string(4) "0.00"
        ["sum_mg_profitlost"]=>
        string(4) "0.00"
        ["sum_tokenfavorable"]=>
        string(4) "0.00"
        ["sum_tokenpreferential"]=>
        string(4) "0.00"
        ["sum_all_bets"]=>
        string(12) "135452066.80"
        ["sum_all_wins"]=>
        string(12) "130246851.68"
        ["sum_all_profitlost"]=>
        string(10) "5205215.12"
        ["days_count"]=>
        int(30523)
        ["sum_all_count"]=>
        string(6) "116255"
        ["cashadministrationfees_sum"]=>
        string(4) "0.00"
        ["tokenadministrationfees_sum"]=>
        string(4) "0.00"
        ["tokenadministration_sum"]=>
        string(4) "0.00"
        ["payonlinedeposit_sum"]=>
        string(4) "0.00"
        ["tokendeposit_sum"]=>
        string(4) "0.00"
    */


    // 全部發出的使用者的分潤總計
    $all_profit_amount_sql = <<<SQL
        SELECT sum(sum_profit_amount) as sum_sum_profit_amount,
               sum(count_profit_amount) as count_profit_amount
        FROM (
            (
                SELECT '1' as no,
                       sum(profit_amount_1) as sum_profit_amount,
                       count(profit_amount_1) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}'
            )
            union
            (
                SELECT '2' as no,
                       sum(profit_amount_2) as sum_profit_amount,
                       count(profit_amount_2) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}'
            )
            union
            (
                SELECT '3' as no,
                       sum(profit_amount_3) as sum_profit_amount,
                       count(profit_amount_3) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}'
            )
            union
            (
                SELECT '4' as no,
                       sum(profit_amount_4) as sum_profit_amount,
                       count(profit_amount_4) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}'
            )
        ) as profit_amount;
    SQL; // echo '<p>全部發出的使用者的分潤總計</p><pre>', var_dump($all_profit_amount_sql), '<pre>'; exit();
    $all_profit_amount_result = runSQLall($all_profit_amount_sql); // echo '<pre>', var_dump($all_profit_amount_result), '</pre>'; exit();
    /*
        ["sum_sum_profit_amount"]=>
        string(9) "161347.92"
        ["count_profit_amount"]=>
        string(4) "4996"
    */


    // 未發出的分潤總計
    $na_profit_amount_sql = <<<SQL
        SELECT sum(sum_profit_amount) as sum_sum_profit_amount,
               sum(count_profit_amount) as count_profit_amount
        FROM (
            (
                SELECT '1' as no,
                       sum(profit_amount_1) as sum_profit_amount,
                       count(profit_amount_1) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}' AND
                      profitaccount_1= 'n/a'
            )
            union
            (
                SELECT '2' as no,
                       sum(profit_amount_2) as sum_profit_amount,
                       count(profit_amount_2) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}' AND
                      profitaccount_2= 'n/a'
            )
            union
            (
                SELECT '3' as no,
                       sum(profit_amount_3) as sum_profit_amount,
                       count(profit_amount_3) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}' AND
                      profitaccount_3= 'n/a'
            )
            union
            (
                SELECT '4' as no,
                       sum(profit_amount_4) as sum_profit_amount,
                       count(profit_amount_4) as count_profit_amount
                FROM root_statisticsbonusprofit
                WHERE dailydate_start = '{$current_datepicker_start}' AND
                      dailydate_end = '{$current_datepicker_end}' AND
                      profitaccount_4= 'n/a'
            )
        ) as profit_amount;
    SQL; // echo '<p>未發出的分潤總計</p><pre>', var_dump($na_profit_amount_sql), '<pre>'; exit();
    $na_profit_amount_result = runSQLall($na_profit_amount_sql); // echo '<pre>', var_dump($na_profit_amount_result), '</pre>'; exit();
    /*
        ["sum_sum_profit_amount"]=>
        string(9) "145262.20"
        ["count_profit_amount"]=>
        string(4) "3704"
    */


    $list_sql = <<<SQL
        SELECT *
        FROM root_statisticsbonusprofit
        WHERE dailydate_start = '{$current_datepicker_start}' AND
              dailydate_end = '{$current_datepicker_end}' AND
              member_profitamount > '0' AND
              member_profitamount_paidtime IS NULL;
    SQL; // echo '<pre>', var_dump($list_sql), '</pre>'; exit();
    $payoutmember_count = runSQL($list_sql); // echo '<pre>', var_dump($payoutmember_count), '</pre>'; exit();
    /*
        4
    */


    $list_stats_data = '';
    if( ($list_result[0] > 0) && ($profit_result[0] > 0) && ($dailydate_result[0] > 0) ){

        // 總投注量
        $sum_sum_all_bets_html = number_format( $list_result[1]->sum_sum_all_bets, 2, '.', ',' );

        // 總注單量
        $sum_sum_all_count_html = number_format( $list_result[1]->sum_sum_all_count, 0, '.', ',' );

        // 從日報表得到的損益值 -- 驗算合計
        $sum_all_profitlost_html = number_format( $dailydate_result[1]->sum_all_profitlost, 2, '.', ',' );

        // 娛樂城平台成本總計
        $sum_member_profitlost_platformcost_html = number_format( $list_result[1]->sum_member_profitlost_platformcost, 2, '.', ',' );

        // 金流成本總計
        $sum_member_profitlost_cashcost_html = number_format( $list_result[1]->sum_member_profitlost_cashcost, 2, '.', ',' );

        // 行銷成本總計
        $sum_member_profitlost_marketingcost_html = number_format( $list_result[1]->sum_member_profitlost_marketingcost, 2, '.', ',' );

        // 個人貢獻平台的損益總計
        $sum_profit_amount_html = number_format( $list_result[1]->sum_profit_amount, 2, '.', ',' );

        // 系統平台分潤
        $profit_amount_system_html = number_format( ($list_result[1]->sum_profit_amount * $rule['commission_root_rate']/100), 2, '.', ',' );

        // all 發出的分潤總計
        $all_profit_amount_html = number_format( $all_profit_amount_result[1]->sum_sum_profit_amount, 2, '.', ',' );

        // 未發出的分潤總計
        $na_profit_amount_html = number_format( $na_profit_amount_result[1]->sum_sum_profit_amount, 2, '.', ',' );

        // 使用者分潤總計
        $sum_member_profitamount_html = number_format( $list_result[1]->sum_member_profitamount, 2, '.', ',' );

        // 分潤的總計(只計算正值)
        $sum_member_profitamount_pos_html = number_format( $profit_result[1]->sum_member_profitamount, 2, '.', ',' );

        if( $payoutmember_count > 0 ){

            $batchreleasebutton_html = <<<HTML
                <button id="batchpayout_html_btn" class="btn btn-info" onclick="batchpayout_html();">批次發送</button>
            HTML;

            $batchpayout_html = <<<HTML
                <div style="display:none; width:800px;" id="batchpayout">
                    <table class="table table-bordered">
                        <thead>
                            <tr bgcolor="#e6e9ed">
                                <th>日期</th>
                                <th>發送筆數</th>
                                <th>預計發送的紅利總計</th>
                                <th>獎金類別</th>
                                <th>發送方式</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{$current_daterange_html}</td>
                                <td>{$payoutmember_count}</td>
                                <td>{$sum_member_profitamount_html}</td>
                                <td>
                                    <select class="form-control" name="bonus_type" id="bonus_type" onchange="auditsetting();">
                                        <option value="">--</option>
                                        <option value="token">現金</option>
                                        <option value="cash">加盟金</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-control" name="bonus_defstatus" id="bonus_defstatus" >
                                        <option value="0">取消</option>
                                        <option value="1">可領取</option>
                                        <option value="2" selected>暫停</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th bgcolor="#e6e9ed">
                                    <center>稽核方式</center>
                                </th>
                                <td>
                                    <select class="form-control" name="audit_type" id="audit_type" disabled>
                                        <option value="none" selected="">--</option>
                                        <option value="freeaudit">免稽核</option>
                                        <option value="depositaudit">存款稽核</option>
                                        <option value="shippingaudit">優惠存款稽核</option>
                                    </select>
                                </td>
                                <th bgcolor="#e6e9ed">
                                    <center>稽核金額</center>
                                </th>
                                <td>
                                    <input class="form-control" name="audit_amount" id="audit_amount" value="0" placeholder="稽核金額，EX：100" disabled>
                                </td>
                                <td>
                                    <button id="payout_btn" class="btn btn-info" onclick="batchpayout();" disabled>發送</button>
                                    <button class="btn btn-warning" onclick="batchpayoutpage_close();">取消</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            HTML;
        }
        else{
            $batchreleasebutton_html = <<<HTML
                <button id="batchpayout_html_btn" class="btn btn-info" disabled>批次發送</button>
            HTML;
            $batchpayout_html = '';
        }

        $summary_payout_js = <<<HTML
            <script type="text/javascript" language="javascript" class="init">
            function auditsetting(){
                var bonustype = $("#bonus_type").val();
                console.log(bonustype);
                if( bonustype == "" ){
                    $("#payout_btn").prop('disabled', true);
                }
                else{
                    if( bonustype == 'token' ){
                        $("#audit_type").prop('disabled', false);
                        $("#audit_amount").prop('disabled', false);
                    }
                    else{
                        $("#audit_type").prop('disabled', true);
                        $("#audit_amount").prop('disabled', true);
                    }
                    $("#payout_btn").prop('disabled', false);
                }
            } // end auditsetting
            function batchpayout_html(){
                $.blockUI({
                    message: $('#batchpayout'),
                    css: {
                       padding: 0,
                        margin: 0,
                        width: '800px',
                        top: '30%',
                        left: '25%',
                        border: 'none',
                        cursor: 'auto'
                    }
                });
            } // end batchpayout_html
            function batchpayoutpage_close(){
                $.unblockUI();
            } // end batchpayoutpage_close
            function batchpayout(){
                $("#payout_btn").prop('disabled', true);
                var show_text = '即将發放 {$current_daterange_html} 的紅利...';
                var payout_status = $("#bonus_defstatus").val();
                var bonus_type = $("#bonus_type").val();
                var audit_type = $("#audit_type").val();
                var audit_amount = $("#audit_amount").val();
                var payoutupdatingcodeurl = 'bonus_commission_profit_action.php?a=profitbonus_payout_update&profitbonus_payout_date={$current_datepicker_start}&s=' + payout_status + '&s1=' + bonus_type + '&s2=' + audit_type + '&s3=' + audit_amount;
                if( (bonus_type == 'token') && (audit_type == 'none') ){
                    alert('請選擇獎金的稽核方式！');
                }
                else{
                    if( confirm(show_text) ){
                        $.unblockUI();
                        $("#batchpayout_html_btn").prop('disabled', true);
                        myWindow = window.open(payoutupdatingcodeurl, 'gpk_window', 'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800', false);
                        myWindow.focus();
                    }
                    else{
                        $("#payout_btn").prop('disabled', false);
                    }
                }
            } // end batchpayout
        </script>
        HTML;


        $summary_report_data_html = <<<HTML
            <tr>
                <td>{$current_daterange_html}</td>
                <td>{$list_result[1]->member_account_count}</td>
                <td>{$sum_sum_all_bets_html}</td>
                <td>{$sum_sum_all_count_html}</td>
                <td>{$sum_all_profitlost_html}</td>
                <td>{$sum_member_profitlost_platformcost_html}</td>
                <td>{$sum_member_profitlost_cashcost_html}</td>
                <td>{$sum_member_profitlost_marketingcost_html}</td>
                <td>{$sum_profit_amount_html}</td>
                <td>{$profit_amount_system_html}</td>
                <td>{$all_profit_amount_html}</td>
                <td>{$na_profit_amount_html}</td>
                <td>{$sum_member_profitamount_html}</td>
                <td>{$sum_member_profitamount_pos_html}</td>
                <td>{$batchreleasebutton_html}</td>
            </tr>
        HTML;

        $summary_report_html = <<<HTML
            <hr>
            <table class="table table-bordered small">
                <thead>
                <tr class="active">
                    <th>統計區間</th>
                    <th>資料數量</th>
                    <th>總投注量</th>
                    <th>總注單量</th>
                    <th>娛樂城損益(日報)</th>
                    <th>娛樂城平台成本總計</th>
                    <th>金流成本總計</th>
                    <th>行銷成本總計</th>
                    <th>個人貢獻平台的損益總計</th>
                    <th>系統平台分潤</th>
                    <th>會員的損益四層分潤</th>
                    <th>未發出的分潤總計</th>
                    <th>使用者分潤總計</th>
                    <th>分潤的總計(只計算正值)</th>
                    <th></th>
                </tr>
                </thead>
                <tbody style="background-color:rgba(255,255,255,0.4);">
                    {$summary_report_data_html}
                </tbody>
            </table>
            <hr>
        HTML
        .$batchpayout_html.$summary_payout_js;

    }
    else{
        $summary_report_html = '';
    }

    return($summary_report_html);
} // end summary_report
// ---------------------------------------------------------------------------
// 此報表的摘要函式, 指定查詢月份的統計結算列表
// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
// 搭配 indexmenu_stats_switch 使用
// Usage: menu_profit_list_html()
// ---------------------------------------------------------------------------
function menu_profit_list_html(){
    // 左邊選單的列出系統資料統計月份
    $list_sql = <<<SQL
        SELECT dailydate_start,
               dailydate_end,MIN(updatetime) as min ,
               MAX(updatetime) as max,
               count(member_account) as member_account_count,
               sum(sum_all_profitlost) as sum_sum_all_profitlost,
               sum(profit_amount) as sum_profit_amount,
               sum(sum_all_bets) as sum_sum_all_bets,
               sum(sum_all_count) as sum_sum_all_count
        FROM root_statisticsbonusprofit
        GROUP BY dailydate_end,
                 dailydate_start
        ORDER BY dailydate_start DESC;
    SQL;
    $list_result = runSQLall($list_sql); // echo '<pre>', var_dump($list_result), '</pre>';

    $list_stats_data = '';
    if( $list_result[0]>0 ){
        // 把資料 dump 出來 to table
        for( $i=1; $i<=$list_result[0]; $i++ ) {

            // 統計區間
            $date_range = $list_result[$i]->dailydate_start.'~'.$list_result[$i]->dailydate_end;
            $date_range_html = <<<HTML
                <a href="?current_datepicker={$list_result[$i]->dailydate_start}" title="觀看指定區間">{$list_result[$i]->dailydate_start} ~ {$list_result[$i]->dailydate_end}</a>
                <a href="#" onclick="bonus_update('{$list_result[$i]->dailydate_start}', '{$date_range}')" title="資料更新">
                    <button class="glyphicon glyphicon-refresh"></button>
                </a>
            HTML;

            // 資料數量
            $member_account_count_html = <<<HTML
                <a href="#" title="統計資料更新的時間區間{$list_result[$i]->min} ~ {$list_result[$i]->max}">{$list_result[$i]->member_account_count}</a>
            HTML;

            // 總投注量(娛樂城投注量)
            $sum_sum_all_bets_html = number_format($list_result[$i]->sum_sum_all_bets, 2, '.' ,',');

            // 總損益(未扣除成本)
            $sum_sum_all_profitlost_html = number_format($list_result[$i]->sum_sum_all_profitlost, 2, '.' ,',');

            // 總損益(已扣除成本)
            $sum_profit_amount_html = number_format($list_result[$i]->sum_profit_amount, 2, '.' ,',');

            // table
            $list_stats_data = $list_stats_data.<<<HTML
                <tr>
                    <td>{$date_range_html}</td>
                    <td>{$member_account_count_html}</td>
                    <td>{$sum_sum_all_bets_html}</td>
                    <td>{$sum_profit_amount_html}</td>
                </tr>
            HTML;
        } // end for
    }
    else{
        $list_stats_data = $list_stats_data.<<<HTML
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        HTML;
    }

    // 統計資料及索引
    $listdata_html = <<<HTML
        <table class="table table-bordered small">
        <thead>
            <tr class="active">
            <th>統計區間<span class="glyphicon glyphicon-time"></span>(-04)</th>
            <th>資料數量</th>
            <th>總投注量</th>
            <th>總損益<br>(已扣除成本)</th>
            </tr>
        </thead>
        <tbody style="background-color:rgba(255,255,255,0.4);">
            {$list_stats_data}
        </tbody>
        </table>
    HTML;

    return($listdata_html);
} // end menu_profit_list_html
// ---------------------------------------------------------------------------
// END -- 檢查系統資料庫中 table root_statisticsbonusprofit 表格(放射線組織獎金計算-營運利潤獎金)有多少資料被生成了, 建立索引檔及提供可以更新的資訊
// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS
// ---------------------------------------------------------------------------
function indexmenu_stats_switch() {
    // 選單表單
    $indexmenu_list_html = menu_profit_list_html();

    // 加上 on / off開關
    $indexmenu_stats_switch_html = <<<HTML
        <span style="position: fixed; top: 5px; left: 5px; width: 450px; height: 20px; z-index: 1000;">
            <button class="btn btn-primary btn-xs" style="display: none" id="hide">選單OFF</button>
            <button class="btn btn-success btn-xs" id="show">選單ON</button>
        </span>
        <div id="index_menu" style="display:block;
            background-color: #e6e9ed;
            position: fixed;
            top: 30px;
            left: 5px;
            width: 450px;
            height: 600px;
            overflow: auto;
            z-index: 999;
            -webkit-box-shadow: 0px 8px 35px #333;
            -moz-box-shadow: 0px 8px 35px #333;
            box-shadow: 0px 8px 35px #333;
            background: rgba(221, 221, 221, 1);
        ">
        {$indexmenu_list_html}
        </div>
        <script>
        $(document).ready(function(){
            $("#index_menu").fadeOut( "fast" );
            $("#hide").click(function(){
                $("#index_menu").fadeOut( "fast" );
                $("#hide").hide();
                $("#show").show();
            });
            $("#show").click(function(){
                $("#index_menu").fadeIn( "fast" );
                $("#hide").show();
                $("#show").hide();
            });
        });
        function bonus_update(query_datas, date_range){
            var show_text = '即将更新 ' + String(date_range) + ' 的獎金記錄...';
            var updating_img = '更新中...<img width="20px" height="20px" src="ui/loading.gif" />';
            var updatingcodeurl = 'bonus_commission_profit_action.php?a=bonus_update&bonus_update_date=' + query_datas;
            if(confirm(show_text)){
            $("#update_"+query_datas).html(updating_img);
            myWindow = window.open(updatingcodeurl, 'gpk_window', 'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800', false);
                myWindow.focus();
            setTimeout(function(){location.reload();},3000);
            }
        }
        </script>
    HTML;


    return($indexmenu_stats_switch_html);
} // end indexmenu_stats_switch
// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS   END
// ---------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate( $date, $format='Y-m-d H:i:s' ){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
} // end validateDate
// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------


// 查詢DB的會員端設定，確認該功能是否有開啟，如已關閉則導向會員端設訂畫面
$protalsetting_sql = <<<SQL
    SELECT *
    FROM "root_protalsetting"
    WHERE ("name"='bonus_commision_profit');
SQL;
$protalsetting_result = runSQLall($protalsetting_sql);
if( $protalsetting_result[0]==1 ){
    if( $protalsetting_result[1]->value=='off' ){
        echo <<<HTML
            <script>
                alert('该功能已被关闭，如需使用请先开启！');
                location.replace('protal_setting_deltail.php?sn=default');
            </script>
        HTML;
        exit();
    }
}
else{
    die('資料庫設定值內並無該項設定值！');
}


// -------------------------------------------------------------------------
// GET / POST 傳值處理
// -------------------------------------------------------------------------

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
//var_dump($current_datepicker);

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

// -------------------------------------------------------------------------
// GET / POST 傳值處理 END
// -------------------------------------------------------------------------

// ---------------------------------------------------------------
// MAIN start
// ---------------------------------------------------------------

// 表格欄位名稱
$table_colname_html = '
  <tr>
    <th>會員ID</th>
    <th>會員帳號</th>
    <th>會員身份</th>
    <th>所在層數</th>
    <th>被跳過的代理</th>
    <th>達成第1代</th>
    <th>達成第2代</th>
    <th>達成第3代</th>
    <th>達成第4代</th>
    <th>總投注量</td>
    <th>個人貢獻平台的損益</th>
    <th>第1代分紅</th>
    <th>第2代分紅</th>
    <th>第3代分紅</th>
    <th>第4代分紅</th>
    <th title="代理商本月第1代分潤來源筆數">第1代分潤筆數</th>
    <th title="代理商本月第1代分潤">第1代分潤</th>
    <th title="代理商本月第2代分潤來源筆數">第2代分潤筆數</th>
    <th title="代理商本月第2代分潤">第2代分潤</th>
    <th title="代理商本月第3代分潤來源筆數">第3代分潤筆數</th>
    <th title="代理商本月第3代分潤">第3代分潤</th>
    <th title="代理商本月第4代分潤來源筆數">第4代分潤筆數</th>
    <th title="代理商本月第4代分潤">第4代分潤</th>
    <th title="代理商本月分潤來源總筆數">分潤來源總筆數</th>
    <th title="代理商本月分潤總和">分潤總和</th>
    <th title="代理商上月留抵">上月留抵</th>
    <th title="代理商本月分潤付款金額">本月付款金額</th>
    <th title="代理商本月分潤付款時間">本月付款時間</th>
    <th>備註</th>
  </tr>
  ';

// var_dump($b);
// -------------------------------------------
// CSV下載按鈕
// -------------------------------------------
$csv_download_url_html = <<<HTML
    ／<a href="in/PHP_Excel/report-bonus_commission_profit.php" class="btn btn-success" style="margin:0 5px;">下載Excel</a>
HTML;
// -------------------------------------------
// CSV下載按鈕 END
// -------------------------------------------

// -------------------------------------------------------------------------
// sorttable 的 jquery and plug info
// -------------------------------------------------------------------------
$sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';
// $sorttablecss = ' class="table table-striped" ';

// 列出資料, 主表格架構
$show_list_html = '';
// 列表
$show_list_html = $show_list_html.'
  <table '.$sorttablecss.'>
  <thead>
  '.$table_colname_html.'
  </thead>
  <tfoot>
  '.$table_colname_html.'
  </tfoot>
  </table>
  ';

// 參考使用 datatables 顯示
// https://datatables.net/examples/styling/bootstrap.html
$extend_head = $extend_head.'
  <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
  <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
  <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
  ';
  // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
$extend_head = $extend_head.'
  <script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
      $("#show_list").DataTable( {
          "bProcessing": true,
          "bServerSide": true,
          "bRetrieve": true,
          "searching": false,
          "oLanguage": {
            "sSearch": "會員帳號:",
            "sEmptyTable": "目前沒有資料!",
            "sLengthMenu": "每頁顯示 _MENU_ 筆",
            "sZeroRecords": "目前沒有資料",
            "sInfo": "目前在第 _PAGE_ 頁，共 _PAGES_ 頁",
            "sInfoEmpty": "目前沒有資料",
            "sInfoFiltered": "(從 _MAX_ 筆資料中過濾)"
          },
          "ajax": "bonus_commission_profit_action.php?a=reload_profitlist&current_datepicker='.$current_datepicker_end.'",
          "columns": [
            { "data": "id", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"\' target=\"_BLANK\" title=\"會員的組織結構狀態\">"+oData.id+"</a>");}},
            { "data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'member_account.php?a="+oData.id+"\' target=\"_BLANK\" title=\"檢查會員的詳細資料\">"+oData.account+"</a>");}},
            { "data": "therole", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\"會員身份 R=管理員 A=代理商 M=會員\">"+oData.therole+"</a>");}},
            { "data": "member_level", "searchable": false, "orderable": true },
            { "data": "skip_agent_tree_count", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\""+oData.skip_bonusinfo+"\">"+oData.skip_agent_tree_count+"</a>");}},
            { "data": "profitaccount_1", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:blue;\">"+oData.profitaccount_1+"</span>");}},
            { "data": "profitaccount_2", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:red;\">"+oData.profitaccount_2+"</span>");}},
            { "data": "profitaccount_3", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:green;\">"+oData.profitaccount_3+"</span>");}},
            { "data": "profitaccount_4", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.profitaccount_4+"</span>");}},
            { "data": "sum_all_bets", "searchable": false, "orderable": true },
            { "data": "profit_amount", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<a href=\'#\' title=\""+oData.member_profitlost_ref+"\">"+oData.profit_amount+"</a>");}},
            { "data": "profit_amount_1", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:blue;\">"+oData.profit_amount_1+"</span>");}},
            { "data": "profit_amount_2", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:red;\">"+oData.profit_amount_2+"</span>");}},
            { "data": "profit_amount_3", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:green;\">"+oData.profit_amount_3+"</span>");}},
            { "data": "profit_amount_4", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.profit_amount_4+"</span>");}},
            { "data": "member_profitamount_count_1", "searchable": false, "orderable": true },
            { "data": "member_profitamount_1", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:blue;\">"+oData.member_profitamount_1+"</span>");}},
            { "data": "member_profitamount_count_2", "searchable": false, "orderable": true },
            { "data": "member_profitamount_2", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:red;\">"+oData.member_profitamount_2+"</span>");}},
            { "data": "member_profitamount_count_3", "searchable": false, "orderable": true },
            { "data": "member_profitamount_3", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:green;\">"+oData.member_profitamount_3+"</span>");}},
            { "data": "member_profitamount_count_4", "searchable": false, "orderable": true },
            { "data": "member_profitamount_4", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.member_profitamount_4+"</span>");}},
            { "data": "member_profitamount_count", "searchable": false, "orderable": true },
            { "data": "member_profitamount", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
                $(nTd).html("<span style=\"color:#ff00aa;\">"+oData.member_profitamount+"</span>");}},
            { "data": "lasttime_stayindebt", "searchable": false, "orderable": true },
            { "data": "member_profitamount_paid", "searchable": false, "orderable": true },
            { "data": "member_profitamount_paidtime", "searchable": false, "orderable": true },
            { "data": "note", "searchable": false, "orderable": true }
            ]
      } );
    } )
  </script>
  ';
// -------------------------------------------------------------------------
// sorttable 的 jquery and plug info END
// -------------------------------------------------------------------------



// -------------------------------------------------------------------------
$show_tips_html = '<div class="alert alert-info">
  <p>* 目前查詢的日期為 '.$current_datepicker_start.' ~ '.$current_datepicker_end.' 的彩金報表，為美東時間(UTC -04)，每日結算時間範圍為 '.$current_datepicker_end.' 00:00:00 -04 ~ '.$current_datepicker_end.' 23:59:59 -04 </p>
  <p>* 對應的中原時間(UTC +08)範圍為：'.date( "Y-m-d", strtotime( "$current_datepicker_end -1 day")).' 13:00:00+08 ~ '.$current_datepicker_end.' 12:59:59+08</p>
  <p>'.$show_rule_html.'</p>
  </div>';


// -------------------------------------------------------------------------
// 加盟金計算報表 -- 選擇日期 -- FORM
// -------------------------------------------------------------------------
$date_selector_html = '
  <form class="form-inline" method="get">
    <div class="form-group">
      <div class="input-group">
        <div class="input-group-addon">指定查詢年份與月份</div>
        <div class="input-group-addon"><input type="text" class="form-control" name="current_datepicker" id="current_datepicker" placeholder="ex:2017-01-22" value="'.$current_datepicker_end.'"></div>
      </div>
    </div>
    <button class="btn btn-primary" onclick="gotoindex();" style="margin:0 5px;">查詢</button>
    ／<button class="btn btn-primary" onclick="bonus_now_update();" style="margin:0 5px;">更新</button>
    '.$csv_download_url_html.'
  </form>
  <hr>';

// 日期 jquery 選單 , 預設選取的日期範圍
// default date
$dateyearrange_start 	= date("Y");
$dateyearrange_end 		= date("Y");
$dateyearrange = $dateyearrange_start.':'.$dateyearrange_end;
// ref: http://api.jqueryui.com/datepicker/#entry-examples
$date_selector_js = '
  <script>
    $(document).ready(function() {
      $( "#current_datepicker" ).datepicker({
        yearRange: "'.$dateyearrange_start.':'.$dateyearrange_end.'",
        maxDate: "+0d",
        minDate: "-13w",
        showButtonPanel: true,
      	dateFormat: "yy-mm-dd",
      	changeMonth: true,
      	changeYear: true
      });
    } );
  </script>
  ';

// 選擇日期 html
$show_dateselector_html = $date_selector_html.$date_selector_js;
// -------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 顯示資料的參考資訊
$show_datainfo_html = '<div class="alert alert-success">
  <p>* 日期 <span class="label label-default">'.$current_datepicker_start.'~'.$current_datepicker_end.'</span> 的營業利潤統計資料，每月'.$rule['stats_profit_day'].'日為結算日。 </p>
  <p>* 此次分紅代理商符合發放資格業績量(總投注量)大於 '.$config['currency_sign'].$rule['amountperformance_month'].'列入統計分潤。</p>
  <p>* 此分紅代理商只要營利結算為正值，即發放現金派彩。如為虧損狀態則保留到下月結算盈餘時扣除虧損後發放。
  </div>
  ';
// -------------------------------------------------------------------------
$show_datainfo_right_html = '<div class="alert alert-success">
  * 收入(3)-公司的營利獎金, 利潤成本計算公式：<br>
  時間區間：月結 <br>
  個人貢獻平台的損益 = 個人娛樂城損益 - 平台成本 - 行銷成本 - 金流成本 <br>
  平台成本 = (個人娛樂城損益 * 平台成本比例)  (平台成本比例 5% ~ 17%, 目前設定 12%) <br>
  行銷成本 = (優惠金額 + 反水金額) <br>
  金流成本 = (提款成本 + 出款成本) , 金流成本比例 0.8 ~ 2% <br>
  如果代理商損益經過四層分紅計算後，該月負值，則累積到下個月分潤盈餘扣儲上月留底後為正值後發放，如為負值則繼續累計。<br>
  </div>
  ';
// -------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// 生成左邊的報表 list index
// ---------------------------------------------------------------------------
$indexmenu_stats_switch_html = indexmenu_stats_switch();
// ---------------------------------------------------------------------------
// 生成左邊的報表 list index END
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// 指定查詢月份的統計結算列表 -- 摘要
// ---------------------------------------------------------------------------
$summary_report_html = summary_report($current_datepicker_start, $current_datepicker_end);
// ---------------------------------------------------------------------------
// 指定查詢月份的統計結算列表 -- 摘要 end
// ---------------------------------------------------------------------------

// -------------------------------------------------------------------------
// 切成 1 欄版面的排版
// -------------------------------------------------------------------------
$indexbody_content = '';
$indexbody_content = $indexbody_content.'
	<div class="row">

    <div class="col-12 col-md-12">
    '.$indexmenu_stats_switch_html.'
    '.$show_tips_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$show_dateselector_html.'
    </div>

    <div class="col-12 col-md-6">
    '.$show_datainfo_html.'
    </div>
    <div class="col-12 col-md-6">
    '.$show_datainfo_right_html.'
    </div>

    <div class="col-12 col-md-12">
    '.$summary_report_html.'
    </div>

  	<div class="col-12 col-md-12">
      '.$show_list_html.'
  	</div>

	</div>
	<br>
  	<div class="row">
		<div id="preview_result"></div>
	</div>
	';
// -------------------------------------------------------------------------




// -------------------------------------------------------------------------
// 轉換頁面時候的黑畫面
// -------------------------------------------------------------------------
// blockui ref: http://malsup.com/jquery/block/#page
$extend_head = $extend_head.'<script src="./in/jquery.blockUI.js"></script>';
//  onclick="blockscreengotoindex();" 在每個 href 轉換 page 的元件中加上 onclick 就可以呼叫
// wait blockui
$extend_head = $extend_head."
<script>
    function gotoindex() {
      var datepicker = $(\"#current_datepicker\").val();
      var goto_url = '".$_SERVER['PHP_SELF']."?current_datepicker='+datepicker;
      window.location.replace(goto_url);
    };
    function bonus_now_update(){
      var datepicker = $(\"#current_datepicker\").val();
      var show_text = '即将更新獎金記錄...';
      var goto_url = '".$_SERVER['PHP_SELF']."?current_datepicker='+datepicker;
      var updatingcodeurl = 'bonus_commission_profit_action.php?a=bonus_update&bonus_update_date='+datepicker;

      if(confirm(show_text)){
        myWindow = window.open(updatingcodeurl, 'gpk_window', 'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800', false);
    		myWindow.focus();
        window.location.replace(goto_url);
      }
    }
</script>
";
// -------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");
?>
