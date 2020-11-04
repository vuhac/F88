<?php
/**
*  Features：後台-- 放射線組織股利計算
*  File Name：bonus_commission_dividendreference.php
*  Author：Ian
*  Modifier：Damocles
*  Last Modified：2019/08/28
*  Related：DB root_dividendreference
*              root_dividendreference_setting
*  Log：
*  參考每日報表, 結算一年時間範圍的股利等級.
*  將會員等級分為 A, B, C 三個等級, 變數參考
*  1.會員第1代的代理商人數
*  2.會員第1代的會員人數
*  3.會員年度累計投注量
*  4.會員年度累計損益貢獻量
*  5.會員第1代的代理商年度累計投注量
*/

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

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// 功能標題，放在標題列及meta
$function_title = '放射线组织奖金计算-股利分级试算';

// 擴充 head 內的 css or js
$extend_head = <<<HTML
    <!-- datepicker css & js -->
    <script src="in/datepicker/dist/datepicker.js"></script>
    <script src="in/datepicker/i18n/datepicker.zh-CN.js"></script>
    <link rel="stylesheet" href="in/datepicker/dist/datepicker.css">
HTML;

// 放在結尾的 js
$extend_js = <<<HTML
    <script>
        // 輸入js
    </script>
HTML;

// body 內的主要內容
$indexbody_content = '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = <<<HTML
    <ol class="breadcrumb">
        <li><a href="home.php">首頁</a></li>
        <li><a href="#">營收與行銷</a></li>
        <li class="active">{$function_title}</li>
    </ol>
HTML;
// ----------------------------------------------------------------------------

// 除錯開關 debug(1:ON / 2:OFF)
$debug = 0;

// === MAIN start ===
$datatables_pagelength = $page_config['datatables_pagelength'];

// 左邊選單內的資料 (加上 on / off開關 JS and CSS)
function indexmenu_stats_switch(){
    // 搜尋已確版的股利分級試算紀錄
    $dividen_day_record_sql = <<<SQL
        SELECT id,
               dailydate_end,
               dailydate_start,
               updatetime
        FROM root_dividendreference_setting
        WHERE (setted = '1')
        ORDER BY id DESC;
    SQL;
    $dividen_day_record_result = runSQLall($dividen_day_record_sql);

    // 建立已確版股利分配的時間區間清單
    if( $dividen_day_record_result[0] >= 1 ){
        $indexmenu_list_data = '';
        for( $i=1; $i<=$dividen_day_record_result[0]; $i++ ){
            $record = [
                'id' => $dividen_day_record_result[$i]->id,
                'date_range' => $dividen_day_record_result[$i]->dailydate_start.' ~ '.$dividen_day_record_result[$i]->dailydate_end,
                'updatetime' => date("Y-m-d H:i:s", strtotime($dividen_day_record_result[$i]->updatetime))
            ];

            // 計算區間內的資料數量
            $data_count_sql = <<<SQL
                SELECT *
                FROM root_dividendreference
                WHERE dividendreference_setting_id='{$record["id"]}';
            SQL;
            $data_count_result = runSQL($data_count_sql);
            $record['data_count'] = $data_count_result; // echo '<pre>', var_dump($record['data_count']), '</pre>'; exit(); //int(426)

            // 已分配人數與股利
            $get_dividen_count_sql = <<<SQL
                SELECT COUNT(id) AS membercount,
                       SUM(member_dividend_assigned) AS member_totaldividen
                FROM root_dividendreference
                WHERE (dividendreference_setting_id = '{$record["id"]}') AND (member_dividend_assigned != '0');
            SQL;
            $get_dividen_count_result = runSQLall($get_dividen_count_sql); // echo '<pre>', var_dump($get_dividen_count_result), '</pre>'; exit();

            if( $get_dividen_count_result[0]==1 ){
                $record['get_dividen_count'] = $get_dividen_count_result[1]->membercount;
                $record['totaldividen'] = $get_dividen_count_result[1]->member_totaldividen;
            }

            $indexmenu_list_data .= <<<HTML
                <tr>
                    <td>
                        <!-- 日期區間 -->
                        <a href="{$_SERVER['PHP_SELF']}?a={$record['id']}">{$record['date_range']}</a>
                    </td>
                    <!-- 資料數量 -->
                    <td>{$record['data_count']}</td>
                    <!-- 發放會員數量 -->
                    <td>{$record['get_dividen_count']}</td>
                    <!-- 發放股利總額 -->
                    <td>{$record['totaldividen']}</td>
                    <!-- 更新時間 -->
                    <td>{$record['updatetime']}</td>
                </tr>
            HTML;
        } // end for
    }
    else{
        $indexmenu_list_data = <<<HTML
            <tr>
                <td colspan="5" style="text-align:center">
                    <h6>No Data</h6>
                </td>
            </tr>
        HTML;
    }

    //
    $indexmenu_list_html = <<<HTML
        <table class="table table-bordered small">
            <thead>
                <tr class="active">
                    <th>日期區間<span class="glyphicon glyphicon-time"></span>(-04)</th>
                    <th>資料數量</th>
                    <th>發放會員數量</th>
                    <th>發放股利總額</th>
                    <th>更新時間</th>
                </tr>
            </thead>
            <tbody style="background-color:rgba(255,255,255,0.9);" id="indexmenu">
                {$indexmenu_list_data}
            </tbody>
        </table>
    HTML;

    // 加上 on / off開關
    $indexmenu_stats_switch_html = <<<HTML
        <span style="position: fixed; top: 5px; left: 5px; width: 420px; height: 20px; z-index: 1000;">
            <button class="btn btn-primary btn-xs" style="display: none" id="hide">選單OFF</button>
            <button class="btn btn-success btn-xs" id="show">選單ON</button>
        </span>
        <div id="index_menu" style="display:none; background-color: #e6e9ed; position: fixed; top: 30px; left: 5px; width: 420px; height: 95%; overflow: auto; z-index: 999; -webkit-box-shadow: 0px 8px 35px #333; -moz-box-shadow: 0px 8px 35px #333; box-shadow: 0px 8px 35px #333;">
            {$indexmenu_list_html}
        </div>
    HTML;

    $indexmenu_stats_switch_html .= <<<HTML
        <script>
            $(function(){
                $('body').on('click', '#hide', function(){
                    $('#index_menu').fadeOut('fast');
                    $('#hide').hide();
                    $('#show').show();
                }); // end on
                $('body').on('click', '#show', function(){
                    $('#index_menu').fadeIn( 'fast' );
                    $('#hide').show();
                    $('#show').hide();
                }); // end on
            }); // END FUNCTION
        </script>
    HTML;

    return($indexmenu_stats_switch_html);
} // end indexmenu_stats_switch


// $_GET 取得日期
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format='Y-m-d H:i:s'){
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
} // end validateDate

// var_dump($_GET);
// 取得 get 傳來的變數，如果有的話就是就是指定的 yy-mm-dd 沒有的話就是今天的日期
if( isset($_GET['current_datepicker']) ){
    // 判斷格式資料是否正確, 不正確以今天的美東時間為主
    $current_datepicker = validateDate($_GET['current_datepicker'], 'Y-m-d');
    if( $current_datepicker ){
        $current_datepicker = $_GET['current_datepicker'];
    }
    else{
        // 轉換為美東的時間 date
        $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
    }
}
else{
    // 轉換為美東的時間 date
    $current_datepicker = gmdate('Y-m-d',time() + -4*3600);
}


// 查詢DB的會員端設定，確認該功能是否有開啟，如已關閉則導向會員端設訂畫面
$protalsetting_sql = <<<SQL
    SELECT *
    FROM root_protalsetting
    WHERE (name='bonus_commision_divdendreference')
    LIMIT 1;
SQL;
$protalsetting_result = runSQLall( $protalsetting_sql );
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

// 統計的期間時間, 從DB中尋找最後已設定的時間，如未設定過，則從dailyreport去取得
$last_dividen_day_sql = <<<SQL
    SELECT MAX(dailydate_end) AS last_dividen_day
    FROM root_dividendreference_setting;
SQL;
$last_dividen_day_result = runSQLall( $last_dividen_day_sql ); // echo '<pre>', var_dump($last_dividen_day_result), '</pre>'; exit();


if( ($last_dividen_day_result[0]==1) && isset($last_dividen_day_result[1]->last_dividen_day) ){
    // 畫面顯示的結束美東時間
    $current_enddate = $current_datepicker;

    // 畫面顯示的結束中原時間
    $current_enddate_gmt = date( "Y-m-d", strtotime( "$current_enddate + 1 day"));

    // 畫面顯示的起始美東時間
    $current_datepicker_lastend = $last_dividen_day_result[1]->last_dividen_day;

    // 畫面顯示的起始中原時間
    $current_datepicker_lastend_gmt = $current_datepicker_lastend;

    // 區間選擇的起始美東時間
    $current_datepicker_startdate = date( "Y-m-d", strtotime( "$current_datepicker_lastend + 1 day"));

    // 區間選擇的結束美東時間
    $current_datepicker_enddate = $current_enddate;
}
else{
    $last_dividen_day_sql = <<<SQL
        SELECT to_char( ( MIN(dailydate) AT TIME ZONE 'AST' ),'YYYY-MM-DD' ) AS last_dividen_day
        FROM root_statisticsdailyreport;
    SQL;
    $last_dividen_day_result = runSQLall( $last_dividen_day_sql );

    // 畫面顯示的結束美東時間
    $current_enddate = $current_datepicker;

    // 畫面顯示的結束中原時間
    $current_enddate_gmt = date( "Y-m-d", strtotime( "$current_enddate + 1 day"));

    // 畫面顯示的起始美東時間
    $current_datepicker_lastend = $last_dividen_day_result[1]->last_dividen_day;

    // 畫面顯示的起始中原時間
    $current_datepicker_lastend_gmt = $current_datepicker_lastend;

    // 畫面顯示的起始美東時間
    $current_datepicker_startdate = date( "Y-m-d", strtotime( "$current_datepicker_lastend + 1 day"));

    // 畫面顯示的結束美東時間
    $current_datepicker_enddate = $current_datepicker;
}

// 例外情況處理(如果開始時間晚於結束時間，則開始時間重置為結束時間)
if( $current_datepicker_startdate > $current_datepicker_enddate ){
    $current_datepicker_enddate = $current_datepicker_startdate;
}


// 預設分類條件設定
$dividendreference_settingarr = [
    'totaldividen'=>'10000000',
    'ratio_a'=>'50',
    'l1_agentcount_a'=>'10',
    'l1_membercount_a'=>'15',
    'l1_agentbetsum_a'=>'0',
    'memberbetsum_a'=>'100000',
    'memberprofsum_a'=>'0',
    'ratio_b'=>'30',
    'l1_agentcount_b'=>'5',
    'l1_membercount_b'=>'5',
    'l1_agentbetsum_b'=>'0',
    'memberbetsum_b'=>'20000',
    'memberprofsum_b'=>'0',
    'ratio_c'=>'20',
    'l1_agentcount_c'=>'1',
    'l1_membercount_c'=>'2',
    'l1_agentbetsum_c'=>'0',
    'memberbetsum_c'=>'10000',
    'memberprofsum_c'=>'0',
    'levela_membercount'=>'',
    'levela_dividen'=>'',
    'levelb_membercount'=>'',
    'levelb_dividen'=>'',
    'levelc_membercount'=>'',
    'levelc_dividen'=>'',
    'remaind_membercount'=>'',
    'remaind_dividen'=>''
];


// -------------------------------------------------------------------------
// $_GET 取得動作
// -------------------------------------------------------------------------
// var_dump($_GET); // $_GET['a']：設定編號
if( isset($_GET['a']) && filter_var($_GET['a'], FILTER_VALIDATE_INT) ){
    // 檢查帶入的值是否在DB中有資料，如有資料則自DB中取出，如沒有則視為無效的資訊略過
    $dividendreference_setting = filter_var($_GET['a'], FILTER_VALIDATE_INT);
    $check_dividen_setting_sql = <<<SQL
        SELECT *
        FROM root_dividendreference_setting
        WHERE (id = '{$dividendreference_setting}')
        LIMIT 1;
    SQL;
    $check_dividen_setting_result = runSQLall($check_dividen_setting_sql);
    if( $check_dividen_setting_result[0] == 1 ){
        $dividen_setting = $check_dividen_setting_result[1];
        $dividendreference_setting_status = $dividen_setting->setted; // setted：是否已設定(0:未試算,1:已確版,2:試算中)

        // === 修改為記錄中的時間 ===
        // 畫面顯示的結束美東時間
        $current_enddate = $dividen_setting->dailydate_end; // 結算時間結束
        // 畫面顯示的結束中原時間
        $current_enddate_gmt = date( "Y-m-d", strtotime( "$current_enddate + 1 day"));
        // 畫面顯示的起始美東時間
        $current_datepicker_lastend = $dividen_setting->dailydate_start; // 結算時間開始
        // 畫面顯示的起始中原時間
        $current_datepicker_lastend_gmt = $current_datepicker_lastend;

        // 設定股利顯示格式
        $levela_dividen = money_format('%i', $dividen_setting->levela_dividen);
        $levelb_dividen = money_format('%i', $dividen_setting->levelb_dividen);
        $levelc_dividen = money_format('%i', $dividen_setting->levelc_dividen);
        $dividen_remaind = money_format('%i', $dividen_setting->dividen_remaind);

        // 檢查如是有試算過或是已定版的，顯示其試算參數
        if( ($dividendreference_setting_status == 1) || ($dividendreference_setting_status == 2) ){
            $dividen_setting_keys = array_keys( (array)$dividen_setting ); // 取出物件索引
            $isset_keys = ['levela_dividen', 'levelb_dividen', 'levelc_dividen', 'dividen_remaind']; // 已設定的值，不是從物件裡取值
            $isset_values = [$levela_dividen, $levelb_dividen, $levelc_dividen, $dividen_remaind];
            foreach( $dividen_setting_keys as $val_outer ){
                foreach( $isset_values as $key_inner=>$val_inner ){
                    if( $val_outer == $val_inner ){
                        $dividendreference_settingarr[$val_outer] = $isset_values[$key_inner];
                    }
                    else{
                        $dividendreference_settingarr[$val_outer] = $dividen_setting->$val_outer;
                    }
                } // end inner foreach
            } // end outer foreach
        }

        // datatable 用的URL
        $datatables_url = 'bonus_commission_dividendreference_action.php?a=reload_memberlist&setting='.$dividendreference_setting;
        // 檢查是否為未試算過的資料，如果是有選時間區間未試算或是有試算未定版，顯示按鍵，其他情況不顯示
        if( ($dividendreference_setting_status == 0) || ($dividendreference_setting_status == 2) ){ // 未試算 or 試算中
            $date_selector_subhtml = <<<HTML
                <div style="display:none" id="summery_updating">
                        <h5 align="center">試算中...<img width="30px" height="30px" src="ui/loading.gif" /></h5>
                    </div>
                    <div id="summery_table">
                        <div id="summery_button">
                            <button id="dividen_count" class="btn btn-primary" onclick="dividen_count();" >分類試算</button>
                            <button id="dividen_confirm" class="btn btn-danger" style="display:none" onclick="dividen_confirm();" >試算確認</button>
                        </div>
                        <br>
            HTML;

            $status_buttonhtml = <<<HTML
                <div id="status_button">
                    <button id="step1" class="btn btn-info" disabled>步驟1.時間區間設定</button> ->
                    <button id="step2" class="btn btn-info">步驟2.試算條件設定及試算</button> ->
                    <button id="step3" class="btn btn-warning" disabled>步驟3.試算結果查詢及匯出</button>
                </div>
            HTML;

            $batchpayout_html = '';
        }
        else if( $dividendreference_setting_status == 1 ){ // 已確版
            $_SESSION['bonus_commission_dividendreference_setting'] = $dividendreference_setting;
            $date_selector_subhtml = <<<HTML
                <div id="summery_table">
                    <a href="in/PHP_Excel/report-bonus_commission_dividendreference.php" class="btn btn-success"  id="excel_link">下載Excel</a>
            HTML;

            // 匯出至出納
            if( $dividen_setting->note=='' ){
                $date_selector_subhtml = <<<HTML
                    {$date_selector_subhtml}<button id="export2cashier" class="btn btn-danger" onclick="batchpayout_html();" >匯出至出納</button>
                HTML;
            }

            $date_selector_subhtml = <<<HTML
                {$date_selector_subhtml}</div><br>
            HTML;

            $status_buttonhtml = <<<HTML
                <div id="status_button">
                    <button id="step1" class="btn btn-info" disabled>步驟1.時間區間設定</button> ->
                    <button id="step2" class="btn btn-info" disabled>步驟2.試算條件設定及試算</button> ->
                    <button id="step3" class="btn btn-warning">步驟3.試算結果查詢及匯出</button>
                </div>
            HTML;

            // 清單
            $list_sql = <<<SQL
                SELECT *
                FROM root_dividendreference
                WHERE (dividendreference_setting_id = '{$dividendreference_setting}') AND
                      (member_dividend_assigned > '0');
            SQL;
            $payoutmember_count = runSQL( $list_sql );

            // 已發總額
            $payout_sum_sql = <<<SQL
                SELECT sum(member_dividend_assigned)
                FROM root_dividendreference
                WHERE (dividendreference_setting_id = '{$dividendreference_setting}') AND
                      (member_dividend_assigned > '0');
            SQL;
            $payout_sum = runSQLall($payout_sum_sql);

            $current_daterange_html = $current_datepicker_lastend.'~'.$current_enddate;
            $sum_member_bonusamount_html = $payout_sum[1]->sum;

            $summary_payout_js = <<<HTML
                <script type="text/javascript" language="javascript" class="init">
                    function auditsetting(){
                        var bonustype = $("#bonus_type").val();
                        console.log(bonustype);
                        if( bonustype=="" ){
                            $("#payout_btn").prop('disabled', true);
                        }
                        else{
                            if( bonustype=='token' ){
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
                        var show_text = "即将發放{$current_daterange_html}的紅利...",
                            payout_status = $("#bonus_defstatus").val(),
                            bonus_type = $("#bonus_type").val(),
                            audit_type = $("#audit_type").val(),
                            audit_amount = $("#audit_amount").val(),
                            payoutupdatingcodeurl = "bonus_commission_dividendreference_action.php?a=dividend_payout_update&setting={$dividendreference_setting}&s=" + payout_status + "&s1=" + bonus_type + "&s2=" + audit_type + "&s3=" + audit_amount;
                        if( (bonus_type=='token') && (audit_type=='none') ){
                            alert('請選擇獎金的稽核方式！');
                        }
                        else{
                            if( confirm(show_text) ){
                                $.unblockUI();
                                $("#export2cashier").prop('disabled', true);
                                myWindow = window.open(payoutupdatingcodeurl, 'gpk_window', 'fullscreen=no,status=no,resizable=yes,top=0,left=0,height=600,width=800', false);
                                myWindow.focus();
                            }
                            else{
                                $("#payout_btn").prop('disabled', false);
                            }
                        }
                    }
                </script>
            HTML;

            $batchpayout_html = <<<HTML
                <div style="display: none;width: 800px;" id="batchpayout">
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
                                <td>{$sum_member_bonusamount_html}</td>
                                <td>
                                    <select class="form-control" name="bonus_type" id="bonus_type"  onchange="auditsetting();">
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
                </div>{$summary_payout_js}
            HTML;
        }
    }
    else{
        // 在DB中無此筆資料，視為未選擇時間
        $dividendreference_setting_status = '3';
        $batchpayout_html = '';
    }
}
else{
    // 未做過任何選擇，直接進新頁面
    $dividendreference_setting_status = '3';
    $batchpayout_html = '';
}



if( $dividendreference_setting_status==3 ){
    $setting_id_html = <<<HTML
        <input type="hidden" id="setting_id" value="">
        <input type="hidden" id="setting_status" value="">
    HTML;
    // datatable 用的URL
    $datatables_url = 'bonus_commission_dividendreference_action.php?a=0';
    // 在DB中無此筆資料，視為未選擇時間，不顯示按鍵
    $date_selector_subhtml = '<div id="summery_table">';
    $status_buttonhtml = <<<HTML
        <div id="status_button">
        <button id="step1" class="btn btn-info">步驟1.時間區間設定</button> ->
        <button id="step2" class="btn btn-info" disabled>步驟2.試算條件設定及試算</button> ->
        <button id="step3" class="btn btn-warning" disabled>步驟3.試算結果查詢及匯出</button></div>
    HTML;
}
else{
    $setting_id_html = <<<HTML
        <input type="hidden" id="setting_id" value="{$dividendreference_setting}">
        <input type="hidden" id="setting_status" value="{$dividendreference_setting_status}">
    HTML;
}

/**
 * MAIN START
 */
// ---------------------------------- END table data get



/**
 * sorttable 的 jquery and plug info
 */
$sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';

// 表格欄位名稱
$table_colname_html = <<<HTML
    <tr>
        <th>會員ID</th>
        <th>帳號</th>
        <th>會員身份</th>
        <th>會員上一層ID</th>
        <th>所在層數</th>
        <th>上層第1代</th>
        <th>上層第2代</th>
        <th>上層第3代</th>
        <th>上層第4代</th>
        <th>會員第1代的代理商人數</th>
        <th>會員第1代代理商區間累計投注量</th>
        <th>會員第1代的會員人數</th>
        <th>會員區間累計投注量</th>
        <th>會員區間累計損益貢獻量</th>
        <th>會員區間累計注單量</th>
        <th>分類等級</th>
        <th>股利分配額</th>
        <th>備註</th>
    </tr>
HTML;

// 列出資料, 主表格架構
$show_list_html = '';

// 列表
$show_list_html = <<<HTML
    {$show_list_html}
    <table {$sorttablecss}>
        <thead>{$table_colname_html}</thead>
        <tfoot>{$table_colname_html}</tfoot>
    </table>
HTML;

// 參考使用 datatables 顯示
// https://datatables.net/examples/styling/bootstrap.html
$extend_head = <<<HTML
    {$extend_head}
    <!-- dataTables -->
    <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
    <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
    <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
HTML;

// DATA tables jquery plugging -- 要放在 head 內 不可以放 body,
// 以 serverside process ，頁面處只做顯示作業
$extend_head = <<<HTML
    {$extend_head}
    <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function(){
            $("#show_list").DataTable({
                "bProcessing": true,
                "bServerSide": true,
                "bRetrieve": true,
                "searching": false,
                "order": [ [15, "asc"] ],
                "oLanguage": {
                    "sSearch": "會員帳號:",
                    "sEmptyTable": "目前沒有資料!",
                    "sLengthMenu": "每頁顯示 _MENU_ 筆",
                    "sZeroRecords": "目前沒有資料",
                    "sInfo": "目前在第 _PAGE_ 頁，共 _PAGES_ 頁",
                    "sInfoEmpty": "目前沒有資料",
                    "sInfoFiltered": "(從 _MAX_ 筆資料中過濾)"
                },
                "ajax": "{$datatables_url}",
                "columns": [
                    {"data": "id", "fnCreatedCell": function(nTd, sData, oData, iRow, iCol){
                        $(nTd).html("<a href=\'member_treemap.php?id="+oData.id+"\'>"+oData.id+"</a>");
                    }},
                    { "data": "account", "fnCreatedCell": function(nTd, sData, oData, iRow, iCol){
                        $(nTd).html("<a href=\'member_account.php?a="+oData.id+"\'>"+oData.account+"</a>");
                    }},
                    { "data": "therole" },
                    { "data": "parent_id" },
                    { "data": "member_level", "searchable": false, "orderable": true },
                    { "data": "member_level_1", "searchable": false, "orderable": true },
                    { "data": "member_level_2", "searchable": false, "orderable": true },
                    { "data": "member_level_3", "searchable": false, "orderable": true },
                    { "data": "member_level_4", "searchable": false, "orderable": true },
                    { "data": "member_1_agent_count", "searchable": false, "orderable": true },
                    { "data": "member_1_agent_all_bets", "searchable": false, "orderable": true },
                    { "data": "member_1_member_count", "searchable": false, "orderable": true },
                    { "data": "sum_all_bets", "searchable": false, "orderable": true },
                    { "data": "sum_all_profitlost", "searchable": false, "orderable": true },
                    { "data": "sum_all_count", "searchable": false, "orderable": true },
                    { "data": "dividend_level", "searchable": false, "orderable": true },
                    { "data": "dividend_assigned", "searchable": false, "orderable": true },
                    { "data": "note", "searchable": false, "orderable": true }
                ]
            });
        }); // $(document).ready
    </script>
HTML;
// -------------------------------------------------------------------------


// -------------------------------------------------------------------------
$show_tips_html = <<<HTML
    {$status_buttonhtml}
    <div class="alert alert-default">
        <h6 style="font-weight: bold;">1.請先點選 "變更區間" 按鈕確認要進行試算的時間區間，確認將進行會員資料驗證</h6>
        <p>* 目前查詢的是年度股利分級報表，本次結算時間範圍為美東時間(UTC -04)，
            <font id="date_start_show">{$current_datepicker_lastend}</font> 00:00:00 -04 ~
            <font id="date_end_show">{$current_enddate}</font> 23:59:59 -04
            <button type="submit" class="btn btn-primary btn-xs" id="query" onclick="datechg();" >變更區間</button>
        </p>
        <p>* 對應的中原時間(UTC +08)範圍為：
            <font id="date_start_show_gmt">{$current_datepicker_lastend_gmt}</font> 13:00:00+08 ~
            <font id="date_end_show_gmt">{$current_enddate_gmt}</font> 12:59:59+08</p>
        <p>{$show_rule_html}</p>
        <h6 style="font-weight: bold;">2.請修改下方表格中的股利分級參數，填寫完畢後再點選 "分類試算" 按鈕確認進行試算。</h6>
        <h6 style="font-weight: bold;">如試算結果確定，請點選認 "試算確認" 按鈕確認試算結果。</h6>
        <p>* 股利分级參數變數</p>
        <ul>
            <li>股利發放比例</li>
            <li>會員第1代的代理商人數</li>
            <li>會員第1代的會員人數</li>
            <li>會員區間累計投注量</li>
            <li>會員區間累計損益貢獻量</li>
            <li>會員第1代的代理商年度累計投注量</li>
        </ul>
    </div>
HTML;

$date_selector_html = <<<HTML
    <div class="table form-group">
        <div class="input-group row">
            <div class="table-cell input-group-addon">預計發放股利總額</div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="totaldividend" id="totaldividend" value="{$dividendreference_settingarr['totaldividen']}">
            </div>
        </div>
        <div class="input-group row">
            <div class="table-cell input-group-addon">會員等級(全部符合且大於這些條件,不填表示不用)</div>
            <div class="table-cell input-group-addon">股利發放比例(單位：%)</div>
            <div class="table-cell input-group-addon">會員第1代的代理商人數</div>
            <div class="table-cell input-group-addon">會員第1代的會員人數</div>
            <div class="table-cell input-group-addon">第1代代理商區間累計投注量</div>
            <div class="table-cell input-group-addon">會員區間累計投注量</div>
            <div class="table-cell input-group-addon">會員區間累計損益貢獻量(單位：代幣)</div>
        </div>
        <div class="input-group row">
            <div class="table-cell input-group-addon">會員等級A<br>(全部符合且大於這些條件,不填表示不用)</div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="dividend" preholder="股利發放比例(單位：%)" id="adividend_ratio" value="{$dividendreference_settingarr['ratio_a']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x1" preholder="會員第1代的代理商人數(單位：人)" id="ax1" value="{$dividendreference_settingarr['l1_agentcount_a']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x2" preholder="會員第1代的會員人數(單位：人)" id="ax2" value="{$dividendreference_settingarr['l1_membercount_a']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x3" preholder="第1代代理商區間累計投注量(單位：代幣)" id="ax3" value="{$dividendreference_settingarr['l1_agentbetsum_a']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x4" preholder="會員區間累計投注量(單位：代幣)" id="ax4" value="{$dividendreference_settingarr['memberbetsum_a']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x5" preholder="會員區間累計損益貢獻量(單位：代幣)" id="ax5" value="{$dividendreference_settingarr['memberprofsum_a']}">
            </div>
        </div>
        <div class="input-group row">
            <div class="table-cell input-group-addon">會員等級B<br>(全部符合且大於這些條件,但小於等級 A)</div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="dividend" preholder="股利發放比例(單位：%)" id="bdividend_ratio" value="{$dividendreference_settingarr['ratio_b']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x1" preholder="會員第1代的代理商人數(單位：人)" id="bx1" value="{$dividendreference_settingarr['l1_agentcount_b']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x2" preholder="會員第1代的會員人數(單位：人)" id="bx2" value="{$dividendreference_settingarr['l1_membercount_b']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x3" preholder="第1代代理商區間累計投注量(單位：代幣)" id="bx3" value="{$dividendreference_settingarr['l1_agentbetsum_b']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x4" preholder="會員區間累計投注量(單位：代幣)" id="bx4" value="{$dividendreference_settingarr['memberbetsum_b']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x5" preholder="會員區間累計損益貢獻量(單位：代幣)" id="bx5" value="{$dividendreference_settingarr['memberprofsum_b']}">
            </div>
        </div>
        <div class="input-group row">
            <div class="table-cell input-group-addon">會員等級C<br>(全部符合且大於這些條件,但小於等級 B)</div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="dividend" preholder="股利發放比例(單位：%)" id="cdividend_ratio" value="{$dividendreference_settingarr['ratio_c']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x1" preholder="會員第1代的代理商人數(單位：人)" id="cx1" value="{$dividendreference_settingarr['l1_agentcount_c']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x2" preholder="會員第1代的會員人數(單位：人)" id="cx2" value="{$dividendreference_settingarr['l1_membercount_c']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x3" preholder="第1代代理商區間累計投注量(單位：代幣)" id="cx3" value="{$dividendreference_settingarr['l1_agentbetsum_c']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x4" preholder="會員區間累計投注量(單位：代幣)" id="cx4" value="{$dividendreference_settingarr['memberbetsum_c']}">
            </div>
            <div class="table-cell input-group-addon">
                <input type="text" class="form-control" name="x5" preholder="會員區間累計損益貢獻量(單位：代幣)" id="cx5" value="{$dividendreference_settingarr['memberprofsum_c']}">
            </div>
        </div>
    </div>
    <hr>
    <p>沒有列入條件的為捨棄的成員，分類為 X ，分類後預設排除非代理商成員(代理商才可以分股利)</p>{$date_selector_subhtml}
    <table class="table table-bordered">
        <thead>
            <tr>
                <th></th>
                <th class="table-cell">等級A符合人數</th>
                <th class="table-cell">等級A每人可得股利</th>
                <th class="table-cell">等級B符合人數</th>
                <th class="table-cell">等級B每人可得股利</th>
                <th class="table-cell">等級C符合人數</th>
                <th class="table-cell">等級C每人可得股利</th>
                <th class="table-cell">未符合人數</th>
                <th class="table-cell">未發出股利</th>
            </tr>
        </thead>
        <tbody id="summary">
            <tr class="success">
                <th>試算結果</th>
                <td class="table-cell" id="levela_membercount"><strong>{$dividendreference_settingarr['levela_membercount']}</td>
                <td class="table-cell" id="levela_dividen"><strong>&nbsp;{$dividendreference_settingarr['levela_dividen']}</td>
                <td class="table-cell" id="levelb_membercount"><strong>{$dividendreference_settingarr['levelb_membercount']}</td>
                <td class="table-cell" id="levelb_dividen"><strong>&nbsp;{$dividendreference_settingarr['levelb_dividen']}</td>
                <td class="table-cell" id="levelc_membercount"><strong>{$dividendreference_settingarr['levelc_membercount']}</td>
                <td class="table-cell" id="levelc_dividen"><strong>&nbsp;{$dividendreference_settingarr['levelc_dividen']}</td>
                <td class="table-cell" id="remaind_membercount"><strong>{$dividendreference_settingarr['remaind_membercount']}</td>
                <td class="table-cell" id="remaind_dividen"><strong>&nbsp;{$dividendreference_settingarr['remaind_dividen']}</td>
            </tr>
        </tbody>
    </table>
    </div>
    <hr>
HTML;

$date_selector_html = $date_selector_html.$batchpayout_html;
// -------------------------------------------------------------------------


// 試算條件表格用的CSS
$extend_head = <<<HTML
    {$extend_head}
    <style>
    /* table */
    .table {
        display: table;
        border-collapse: none;
    }

    /* tr */
    .table .row{
        display: table-row;
        border:solid 0px;
    }
    </style>
HTML;

// --------------------------------------
// 表單, JS 動作 , 按下submit_to_inquiry 透過 jquery 送出 post data 到 url 位址
// --------------------------------------
$extend_head = <<<HTML
    {$extend_head}
    <script>

        // 開啟時間區間選擇
        function datechg(){
            $.get("bonus_commission_dividendreference_action.php?a=date_select",
                function(result){
                    $("#datepicker_selector").html(result.html);
                    $("#datepicker_js").html(result.js);
                }, 'json' );

                $.blockUI({
                    message: $('#datepicker')
                });
        } // end datechg

        // 關閉時間區間選擇
        function query_page_close(){
            $.unblockUI();
        } // end query_page_close

        // 股利試算結果定版
        function dividen_confirm(){
            var setting_id  = $('#setting_id').val();

            if( confirm('试算确认后将无法再次修改，您确定要将目前设定设为确认版吗?') ){
                $('#dividen_count').hide();
                $('#dividen_confirm').hide();
                $('#step2').prop('disabled', true);
                $('#step3').removeAttr('disabled');

                $.get('bonus_commission_dividendreference_action.php?a=dividen_confirm&setting=' + setting_id, function(result){
                    $('#indexmenu').html(result.menu);
                    $('#summery_button').html(result.csv);
                    setTimeout(function(){
                        location.reload();
                    },1);
                }, 'json'  );
            }
        } // end dividen_confirm

        // 股利試算
        function dividen_count(){
            var setting_id  = $('#setting_id').val();
            var query_str = '&setting=' + setting_id;

            // 計算分發比例是否正確，三值總合不可超過100
            var adividend_ratio = $('#adividend_ratio').val(),
                bdividend_ratio = $('#bdividend_ratio').val(),
                cdividend_ratio = $('#cdividend_ratio').val();
                totaldividend_ratio = parseInt(adividend_ratio) + parseInt(bdividend_ratio) + parseInt(cdividend_ratio);

            if( totaldividend_ratio > 100 ){
                alert('股利發放比例設定錯誤，請重新填寫！(三值總和需小於或等於100)');
            }
            else{
                $('#summery_table').hide();
                $('#summery_updating').show();

                $.get('bonus_commission_dividendreference_action.php?a=dividen_count' + query_str, {
                    'x0':$('#totaldividend').val(),
                    'ax0':$('#adividend_ratio').val(),
                    'ax1':$('#ax1').val(),
                    'ax2':$('#ax2').val(),
                    'ax3':$('#ax3').val(),
                    'ax4':$('#ax4').val(),
                    'ax5':$('#ax5').val(),
                    'bx0':$('#bdividend_ratio').val(),
                    'bx1':$('#bx1').val(),
                    'bx2':$('#bx2').val(),
                    'bx3':$('#bx3').val(),
                    'bx4':$('#bx4').val(),
                    'bx5':$('#bx5').val(),
                    'cx0':$('#cdividend_ratio').val(),
                    'cx1':$('#cx1').val(),
                    'cx2':$('#cx2').val(),
                    'cx3':$('#cx3').val(),
                    'cx4':$('#cx4').val(),
                    'cx5':$('#cx5').val()
                }, function(result){
                    // $('#summary').html(result);
                    $('#show_list').DataTable().ajax
                        .url('bonus_commission_dividendreference_action.php?a=reload_memberlist&setting=' + setting_id)
                        .load();
                    $('#summery_updating').hide();
                    $('#summery_table').show();
                    $('#dividen_confirm').show();
                }, 'json'); //end get
            }
        } // end dividen_count
    </script>
HTML;

// 選擇日期 html
$show_dateselector_html = $date_selector_html;
// -------------------------------------------------------------------------


// 生成時間設定區塊 datepicker
// 查詢已建立資料，但未設定發放等級的資料，讓站長能在前次操作未完成後再次操作
$dividen_day_record_sql = <<<SQL
    SELECT id,
           dailydate_end AS dailydate_record_end,
           dailydate_start AS dailydate_record_start
    FROM root_dividendreference_setting
    WHERE (setted != '1');
SQL;
$dividen_day_record_result = runSQLall( $dividen_day_record_sql ); // echo '<pre>', var_dump($dividen_day_record_result), '</pre>';  exit();

$date_record_select_html = '';
$date_record_select_js = '';
if( $dividen_day_record_result[0] >= 1 ){
    $option_str = '';
    $js_switch_option = '';
    for( $i=1; $i<=$dividen_day_record_result[0]; $i++ ){
        // 建立已查詢過但未設定股利分配的時間區間清單
        $option_str = <<<HTML
            {$option_str}<option value="{$i}">{$dividen_day_record_result[$i]->dailydate_record_start}~{$dividen_day_record_result[$i]->dailydate_record_end}</option>
        HTML;

        // 建立已查詢過但未設定股利分配的時間區間清單配合的JS
        $js_switch_option .= <<<JS
            if( date_record_select_var == {$i} ){
                $('#date_start_datepicker').val('{$dividen_day_record_result[$i]->dailydate_record_start}');
                $('#date_end_datepicker').val('{$dividen_day_record_result[$i]->dailydate_record_end}');
                $('#setting_id').val('{$dividen_day_record_result[$i]->id}');
        JS;
    } // end for

    $date_record_select_html = <<<HTML
        {$date_record_select_html}
        <select id="date_record_select" onchange="date_record_select(this.options[this.options.selectedIndex].value);">
            <option value="0" selected>--</option>{$option_str}</select>
    HTML;

    $date_record_select_js = <<<JS
        {$date_record_select_js}
        function date_record_select(date_record_select_var){
            if( date_record_select_var=='' ){
                {$js_switch_option}
            }
        } // end date_record_select
    JS;
}

// 依是否讀取過往設定記錄修改顯示的方式
$mindate_arr =  preg_split("/[\-,]+/", $current_datepicker_startdate); // var_dump($mindate_arr);
$mindate = [
    'year' => $mindate_arr[0],
    'month' => $mindate_arr[1]-1,
    'day' => $mindate_arr[2]
];

// 建立時間區間設定頁面
$datepicker_content = <<<HTML
    <div style="display: none;" id="datepicker">
        <h5>請設定統計的時間區間</h5>
        <div id=datepicker_selector>{$date_record_select_html}</div>

        <div class="row">
            <div class="col-12 col-md-3">
                <p class="text-right">Start Time</p>
            </div>
            <div class="col-12 col-md-9">
                <input type="text" class="form-control" name="sdate" id="date_start_datepicker" value="{$current_datepicker_startdate}">
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-md-3">
                <p class="text-right">End Time</p>
            </div>
            <div class="col-12 col-md-9">
                <input type="text" class="form-control" name="edate" id="date_end_datepicker" value="{$current_datepicker_enddate}">
            </div>
        </div>

        {$setting_id_html}
        <button class="btn btn-success btn-block" id="datepicker_query">{$tr['Inquiry']}</button>
        <button class="btn btn-danger btn-block" onclick="javascript:query_page_close()">取消</button>
        <div id=datepicker_js>
            <!-- <script>{$date_record_select_js}</script> -->
        </div>
    </div>

    <script>
        $(function(){
            // 設定統計的時間區間-查詢
            $('body').on('click', '#datepicker_query', function(){
                update_dividen_memberlist();
            }); // end on

            // 更新會員資料
            function update_dividen_memberlist(){
                // 取得頁面的時間區間
                var query_date_start_datepicker = $('#date_start_datepicker').val();
                var query_date_end_datepicker = $('#date_end_datepicker').val();

                // 比較日期起迄大小，如果開始日期晚於結束日期，alert錯誤訊息
                if( (Date.parse(query_date_start_datepicker)).valueOf() > (Date.parse(query_date_end_datepicker)).valueOf() ){
                    alert('end date can not earlier than start date！');
                    return false;
                }

                // 設定傳送參數
                var query_str = '&sdate=' + query_date_start_datepicker + '&edate=' + query_date_end_datepicker;

                // 設定顯示會員資料更新用的視窗
                var gpk_window = window.open('', 'gpk_window', 'fullscreen=no,status=yes,location=no,resizable=yes,top=0,left=0,height=300,width=400', false);

                $(this).ajaxStart(function(){
                    $.unblockUI();
                }); // end ajaxStart

                $.get('bonus_commission_dividendreference_action.php?a=checkstate' + query_str, function(result){
                    // 有錯誤發生，提示錯誤訊息
                    if( result.setting == 0 ){
                        alert(result.msg);
                    }
                    else{
                        // 已確版
                        if( result.status == 1 ){
                            window.open( "{$_SERVER['PHP_SELF']}?a=" + result.setting, '_self' );
                        }
                        // 未試算、試算中
                        else{
                            gpk_window.location.href = 'bonus_commission_dividendreference_action.php?a=update_reload&k=' + result.k;
                            gpk_window.focus();
                            gotourl = "{$_SERVER['PHP_SELF']}?a=" + result.setting;
                            window.open(gotourl,'_self');
                        }
                    }
                    $.unblockUI();
                }, 'json' );

            } // end update_dividen_memberlist

            /* $('#date_start_datepicker').datepicker({
                showButtonPanel: true,
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                minDate: new Date({$mindate['year']}, {$mindate['month']}, {$mindate['day']}), // '2018-08-14'
                maxDate: 0
            }); // end datepicker */

            /* $('#date_end_datepicker').datepicker({
                showButtonPanel: true,
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                minDate: new Date({$mindate['year']}, {$mindate['month']}, {$mindate['day']}),
                maxDate: 0
            }); // end datepicker */

            // 結束日期最多只能選到昨天，因為日報只會算到昨天。
            var today = new Date();
            var yesterday = today.setDate( today.getDate() - 1 );

            // 開始日期(新版)
            $('#date_start_datepicker').datepicker({
                zIndex: 1012,
                language: 'zh-CN', // 有其他語系檔案
                format: 'yyyy-mm-dd',
                yearFirst: true,
                startDate: new Date({$mindate['year']}, {$mindate['month']}, {$mindate['day']}),
                endDate: yesterday
            }); // end datepicker

            // 結束日期(新版)
            $('#date_end_datepicker').datepicker({
                zIndex: 1012,
                language: 'zh-CN', // 有其他語系檔案
                format: 'yyyy-mm-dd',
                yearFirst: true,
                startDate: new Date({$mindate['year']}, {$mindate['month']}, {$mindate['day']}),
                endDate: yesterday
            }); // end datepicker

            // 選取開始日期後，自動限制結束日期的最早值
            $('#date_start_datepicker').on('pick.datepicker', function(e){
                $(this).datepicker('hide');
                var minDate = e.date;
                $('#date_end_datepicker').datepicker('destroy');
                $('#date_end_datepicker').datepicker({
                    zIndex: 1012,
                    language: 'zh-CN', // 有其他語系檔案
                    format: 'yyyy-mm-dd',
                    yearFirst: true,
                    startDate: minDate,
                    endDate: yesterday
                }); // end datepicker
                $('#date_end_datepicker').datepicker('show');
            }); // end on

            // 選取結束日期後，自動限制開始日期的最晚值
            $('#date_end_datepicker').on('pick.datepicker', function(e){
                $(this).datepicker('hide');
                var maxDate = e.date;
                $('#date_start_datepicker').datepicker('destroy');
                $('#date_start_datepicker').datepicker({
                    zIndex: 1012,
                    language: 'zh-CN', // 有其他語系檔案
                    format: 'yyyy-mm-dd',
                    yearFirst: true,
                    startDate: new Date({$mindate['year']}, {$mindate['month']}, {$mindate['day']}),
                    endDate: maxDate
                }); // end datepicker
            }); // end on


        }); // END FUNCTION
    </script>
HTML;
// end 生成時間設定區塊 datepicker


 // 生成左邊的報表 list index
$indexmenu_stats_switch_html = indexmenu_stats_switch();
// end 生成左邊的報表 list index

// 切成 1 欄版面
$indexbody_content = <<<HTML
    <div class="row">
        <div class="col-12 col-md-12">
            {$show_tips_html}
            {$indexmenu_stats_switch_html}
        </div>
        <div class="col-12 col-md-12">
            {$show_dateselector_html}
        </div>
        <div class="col-12 col-md-12">
            {$show_list_html}
        </div>
    </div>
    </br>
    <div class="row">
        <div id="preview_result"></div>
    </div>
HTML;
// end 切成 1 欄版面


// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl = [
    'html_meta_description' => $tr['host_descript'],
    'html_meta_author' => $tr['host_author'],
    'html_meta_title' => $function_title.'-'.$tr['host_name'],
    'page_title' => $menu_breadcrumbs, // 頁面大標題
    'extend_head' => $extend_head, // 擴充再 head 的內容 可以是 js or css
    'extend_js' => $extend_js, // 擴充於檔案末端的 Javascript
    'paneltitle_content' => '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title, // 主要內容 -- title
    'panelbody_content' => $indexbody_content.$datepicker_content // 主要內容 -- content
];


// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// include("template/beadmin.tmpl.php");
include("template/beadmin_fluid.tmpl.php");
?>
