<?php
// ----------------------------------------------------------------------------
// Features: 後台-- 會員註冊審查
// File Name: register_review.php
// Author: Ian
// Editor: Damocles
// Related:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__)."/config.php";

// 支援多國語系
require_once dirname(__FILE__)."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__)."/lib.php";

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
//加盟聯營股東申請審查
$function_title = $tr['member_register_review_title'];

// 擴充 head 內的 css or js
$extend_head = '';

// 放在結尾的 js
$extend_js = '';

// body 內的主要內容
$indexbody_content = '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] = '首頁'; $tr['Members and Agents'] = '會員與加盟聯營股東';
$menu_breadcrumbs = <<<HTML
    <ol class="breadcrumb">
        <li><a href="home.php">{$tr['Home']}</a></li>
        <li><a href="#">{$tr['Members and Agents']}</a></li>
        <li class="active">{$function_title}</li>
    </ol>
HTML;
// 預設可查區間限制
$current_date = gmdate('Y-m-d',time() + -4*3600);
$min_date = ' 00:00';
$max_date = ' 23:59';
// ----------------------------------------------------------------------------
function queryStr($query_sql_array)
{
    Global $tr;
    $query_top= 0;
    $show_member_log_sql = '';
    if(isset($query_sql_array['query_date_start_datepicker']) or isset($query_sql_array['query_date_end_datepicker'])){
        if (isset($query_sql_array['query_date_start_datepicker']) and $query_sql_array['query_date_start_datepicker'] != null) {
            if ($query_top == 1) {
                $show_member_log_sql = $show_member_log_sql . ' AND ';
                }
            $show_member_log_sql = $show_member_log_sql . 'applicationtime >= \'' . $query_sql_array['query_date_start_datepicker_gmt'] . '\'';
            $query_top = 1;
            }

        if (isset($query_sql_array['query_date_end_datepicker']) and $query_sql_array['query_date_end_datepicker'] != null) {
            if ($query_top == 1) {
                $show_member_log_sql = $show_member_log_sql . ' AND ';
                }
            $show_member_log_sql = $show_member_log_sql . 'applicationtime <= \'' . $query_sql_array['query_date_end_datepicker_gmt'] . '\'';
            $query_top           = 1;
            }

        if($query_top == 1 AND !isset($logger)){
          $return_sql = 'AND '.$show_member_log_sql;
        }elseif(isset($logger)){
          $return_sql['logger'] = $logger;
        }else{
          $return_sql = '';
        }
    }else{
            switch ($query_sql_array['t']) {
                case '1':
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '24 hours')) ";
                    break;
                case '7':
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '7 days')) ";
                    break;
                case '30':
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '30 days')) ";
                    break;
                case '90':
                    $return_sql     = " AND  (applicationtime >= (current_timestamp - interval '90 days')) ";
                    break;
                default:
                    $return_sql     = " AND (applicationtime >= (current_timestamp - interval '24 hours')) ";
                    break;
            }
           }
    return $return_sql;
}
// -------------------------------------------------------------------------------
function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// ----------------------------------------------------------------------------
function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
// -------------------------------------------------------------------------------
$query_sql_array = [];
// 開始時間
$sd = '';
if ( isset($_POST['sdate']) ) {
    // 判斷格式資料是否正確
    if ( validateDate($_POST['sdate'], 'Y-m-d H:i') ) {
        $query_sql_array['query_date_start_datepicker'] = $_POST['sdate']; //.' 00:00:00';
        $sd = $_POST['sdate'];
        $query_sql_array['query_date_start_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u', strtotime($query_sql_array['query_date_start_datepicker'] . '-04') + 8 * 3600) . '+08:00';
    }
}

// 結束時間
$ed = '';
if ( isset($_POST['edate']) ) {
    // 判斷格式資料是否正確
    if ( validateDate($_POST['edate'], 'Y-m-d H:i') ) {
        $query_sql_array['query_date_end_datepicker'] = $_POST['edate']; // .' 23:59:59';
        $ed = $_POST['edate'];
        $query_sql_array['query_date_end_datepicker_gmt'] = gmdate('Y-m-d H:i:s.u', strtotime($query_sql_array['query_date_end_datepicker'] . '-04') + 8 * 3600) . '+08:00';
    }
}

// 快速查詢
$query_sql_array['t'] = '';
if ( isset($_GET['t']) ) {
    $query_sql_array['t'] = filter_var($_GET['t'], FILTER_SANITIZE_STRING);
}

// 審核未審核filter
if ( isset($_GET['status_filter']) ) {
    switch ( $_GET['status_filter'] ) {
        case 'unreviewed':
            $get_status_filter = 'unreviewed';
            break;
        case 'audited':
            $get_status_filter = 'audited';
            break;
        default:
            $get_status_filter = 'unreviewed';
    }
} else {
    $get_status_filter = 'unreviewed';
}

// 有登入，且身份為管理員 R 才可以使用這個功能。
if ( isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {

    // 判斷代理商自動審查的開關, 目前的狀態
    $switch_chk = ($defaultstatus == '4') ? '' : 'checked';

    $register_review_switch_html = <<< HTML
        <div>
            <span class="material-switch">
                <input id="autoreview_switch" class="checkbox_switch"  onclick="autoaudit_switch();" type="checkbox" {$switch_chk}/>
                <label for="autoreview_switch" class="label-success"></label>
            </span>{$tr['Auto-audit register switch']}
        </div>
    HTML;

    //審核狀態篩選
    $review_status = [
        'audited' => $tr['Qualified'],
        'unreviewed' => $tr['Unreviewed']
    ];
    $time_query = ( isset($query_sql_array['t']) ) ? '&t='.$query_sql_array['t'] : '';
    $status_html = '';
    foreach ($review_status as $status_key => $status_value) {
        $active_status_filter_btn = ($status_key==$get_status_filter) ? 'success' : 'default';
        $status_html .= <<<HTML
            <a class="btn btn-{$active_status_filter_btn} btn-sm" href="?status_filter={$status_key}{$time_query}"role="button">{$status_value}</a>
        HTML;
    }
    $status_filter_html=<<<HTML
        <div class="btn-group mr-3" role="group" align="right">
            {$status_html}
        </div>
    HTML;

    // 使用者所在的時區，sql 依據所在時區顯示 time
    // -------------------------------------
    $tz = ( ( isset($_SESSION['agent']->timezone) ) ? $_SESSION['agent']->timezone : '+08' );

    // 轉換時區所要用的 sql timezone 參數
    $tzsql = <<<SQL
        SELECT *
        FROM "pg_timezone_names"
        WHERE ("name" LIKE '%posix/Etc/GMT%')
            AND ("abbrev" = '{$tz}')
    SQL;
    $tzone = runSQLALL($tzsql);
    $tzonename = ( ($tzone[0] == 1) ? $tzone[1]->name : 'posix/Etc/GMT-8' );

    $query_str = ''; // 取得查詢條件
    // 2-2去query_str($query_sql_array)函數，產生查詢條件
    if ( isset($query_sql_array) ) {
        $query_str = queryStr($query_sql_array);
    } else {
        // 沒有資料的話, default 24 hr
        $query_str = " AND (applicationtime >= (current_timestamp - interval '7 days')) ";
    }

    $salt = generateRandomString();
    $querytoken = jwtenc($salt,$query_sql_array);

    if ( isset($query_sql_array['query_date_start_datepicker']) ) {
        $sdate = $query_sql_array['query_date_start_datepicker'];
    } else if ( isset($query_sql_array['t']) ) {
        switch ( $query_sql_array['t'] ) {
            case '1':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 1 days'));
                break;
            case '7':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 7 days'));
                break;
            case '30':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 30 days'));
                break;
            case '90':
                $sdate = gmdate('Y-m-d 00:00',strtotime('- 90 days'));
                break;
            default:
                $sdate = $current_date.$min_date;
                break;
        }
    } else {
        $sdate = $current_date.$min_date;
    }

    if ( isset($query_sql_array['query_date_end_datepicker']) ) {
        $edate = $query_sql_array['query_date_end_datepicker'];
    } else {
        $edate = $current_date.$max_date;
    }

    $show_transaction_condition_html = '';
    // 自訂搜尋 + 快速查詢
    //快速查詢 1天內 7天內 30天內 90天內 開始日 結束日 查詢
    $show_transaction_condition_html = <<<HTML
        <p>
            <form class="form" action="member_register_review.php?status_filter={$get_status_filter}" method="POST">
                <div class="form-inline">
                    <div class="btn-group mr-1 my-1">
                        <a href="?t=1&status_filter={$get_status_filter}" class="btn btn-default" role="button">{$tr['within 1 days']}</a>
                        <a href="?t=7&status_filter={$get_status_filter}" class="btn btn-default" role="button">{$tr['within 7 days']}</a>
                        <a href="?t=30&status_filter={$get_status_filter}" class="btn btn-default" role="button">{$tr['within 30 days']}</a>
                        <a href="?t=90&status_filter={$get_status_filter}" class="btn btn-default" role="button">{$tr['within 90 days']}</a>
                    </div>
                    &nbsp;
                    <div class="input-group mr-1 my-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">{$tr['start search date']}</span>
                        </div>
                        <input type="text" class="form-control" name="sdate" id="query_date_start_datepicker" value="{$sdate}" placeholder="ex：2018-01-20">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">~</span>
                        </div>
                        <input type="text" class="form-control" name="edate" id="query_date_end_datepicker" value="{$edate}" placeholder="ex：2018-01-20">
                    </div>
                    <button class="btn btn-primary mr-1 my-1" type="submit" role="button">{$tr['search']}</button>
                </div>
            </form>
        </p><hr>
    HTML;

    $extend_head .= <<<HTML
        <!-- jquery datetimepicker js+css -->
        <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
        <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
    HTML;

    $extend_js = <<<HTML
        <script>
            $("#query_date_start_datepicker").datetimepicker({
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                timepicker: true,
                defaultTime: '00:00',
                format: "Y-m-d H:i",
                step:1
            });
            $("#query_date_end_datepicker").datetimepicker({
                showButtonPanel: true,
                changeMonth: true,
                changeYear: true,
                timepicker: true,
                defaultTime: '23:59',
                format: "Y-m-d H:i",
                step:1
            });
        </script>
    HTML;

    // 表格欄位名稱
    //申請單號、會員帳號、目前身份、姓名、電話、審核、申請時的IP、申請的時間、處理資訊
    $table_colname_html = <<<HTML
        <tr>
            <th>{$tr['seq']}</th>
            <th>{$tr['Account']}</th>
            <th>{$tr['registered_name']}</th>
            <th>{$tr['apply_time']}</th>
            <th>{$tr['IP']}</th>
            <th>{$tr['fingerprint']}</th>
            <th>{$tr['audit']}</th>
            <th>{$tr['info']}</th>
        </tr>
    HTML;

    // enable sort table
    $sorttablecss = ' id="show_list"  class="display" cellspacing="0" width="100%" ';

    $show_tips_html = '';

    // 列出 DATA 資料, 主表格架構
    $show_list_html = <<<HTML
        <table {$sorttablecss}>
            <thead>{$table_colname_html}</thead>
            <tfoot>{$table_colname_html}</tfoot>
            <tbody></tbody>
        </table>
    HTML;

    // 參考使用 datatables 顯示
    // https://datatables.net/examples/styling/bootstrap.html
    $extend_head .= <<<HTML
        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
    HTML;

    // DATA tables jquery plugging -- 要放在 head 內 不可以放 body
    $extend_head .= <<<HTML
        <script type="text/javascript" language="javascript" class="init">
            $(document).ready(function(){
                $("#show_list").DataTable({
                    "bProcessing": true,
                    "bServerSide": true,
                    "bRetrieve": true,
                    "searching": false,
                    "order": [
                        [3, "desc"]
                    ],
                    "oLanguage": {
                        "sSearch": "{$tr['Account']}:",
                        "sEmptyTable": "{$tr['Currently no information']}",
                        "sLengthMenu": "{$tr['Every page shows']} _MENU_ {$tr['Count']}",
                        "sZeroRecords": "{$tr['Currently no information']}",
                        "sInfo": "{$tr['Currently in the']} _PAGE_ {$tr['page']}，{$tr['total']} _PAGES_ {$tr['page']}",
                        "sInfoEmpty": "{$tr['Currently no information']}",
                        "sInfoFiltered": "({$tr['From']} _MAX_ {$tr['counts data filtering']})"
                    },
                    "ajax": "member_register_review_action.php?a=register_review_list&s={$salt}&tk={$querytoken}&status_filter={$get_status_filter}",
                    "columns": [
                        {"data": "id", "searchable": false, "orderable": true},
                        {"data": "account", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol)
                            {
                                if ( (oData.status != 4) && (oData.status != 0) ) {
                                    $(nTd).html("<a href='member_account.php?a=" + oData.member_id + "' target='_blank' title='檢查會員的詳細資料'>" + oData.account + "</a>");
                                }
                            }
                        },
                        {"data": "name", "searchable": false, "orderable": true},
                        {"data": "applicationtime", "searchable": false, "orderable": true},
                        {"data": "applicationip", "searchable": false, "orderable": true},
                        {"data": "fingerprinting", "searchable": false, "orderable": true},
                        {"data": "status", "fnCreatedCell": function (nTd, sData, oData, iRow, iCol)
                            {
                                if (oData.status == 4) {
                                    $(nTd).html(
                                        "<select id='review_switch' class='checkbox_switch' value='" + oData.status + "' onchange='audit_switch(\"" + oData.member_id + "\", \"" + oData.account + "\")'>" +
                                            "<option value='0'>{$tr['application reject']}</option>" +
                                            "<option value='1'>{$tr['account valid']}</option>" +
                                            "<option value='2'>{$tr['account freeze']}</option>" +
                                            "<option value='3'>{$tr['blocked']}</option>" +
                                            "<option value='auditing' selected>{$tr['auditing']}</option>" +
                                        "</select>" +
                                        "<a href='member_review_info.php?id=" + oData.member_id + "' class='btn btn-primary btn-xs' style='margin-left:15px;'>{$tr['view']}</a>"
                                    );
                                } else {
                                    $(nTd).html('<center>' + oData.processingtime + '</center>');
                                }
                            }
                        },
                        {"data": "notes", "searchable": false, "orderable": false}
                    ]
                });
            });
            function audit_switch(mid,uaccount){
                var e = event.target;
                var opt = e.options[e.selectedIndex].value;
                var confirm_mesg = "{$tr['audit_confirm']} " + uaccount + "？";
                var req_setting = {
                    "url": "member_register_review_action.php?a=register_review_update",
                    "method": "POST",
                    "dataType": "JSON",
                    "headers": {
                        "content-type": "application/x-www-form-urlencoded",
                        "cache-control": "no-cache",
                    },
                    "data": {
                        "uid": mid,
                        "value": opt
                    }
                };
                var pass = (opt == 'auditing') ? false : true
                if ( pass && confirm(confirm_mesg) ) {
                    $.ajax(req_setting).done(function(response) {
                        $("#show_list").DataTable().ajax.reload(null, false);
                        alert(response.message.description);
                    }).fail(function(error) {
                        console.log(error);
                        alert(error.response.JSON.message.description);
                    });
                } else {
                    e.value = e.dataset.current
                    return false;
                }
            }
            function autoaudit_switch(){
                if ( $("#autoreview_switch").prop("checked") ) {
                    var opt = 1;
                    var status = "{$tr['enable']}";
                } else {
                    var opt = 0;
                    var status = "{$tr['disable']}";
                }
                var confirm_mesg = "{$tr['member_register_review_title']} " + status + "？";
                var req_setting = {
                    "url": "member_register_review_action.php?a=register_review_switch_update",
                    "method": "POST",
                    "dataType": "JSON",
                    "headers": {
                        "content-type": "application/x-www-form-urlencoded",
                        "cache-control": "no-cache",
                    },
                    "data": {
                        "value": opt
                    }
                };
                var pass = true;
                if ( pass && confirm(confirm_mesg) ) {
                    $.ajax(req_setting).done(function(response) {
                        $("#show_list").DataTable().ajax.reload(null, false);
                        alert(response.message.description);
                    }).fail(function(error) {
                        console.log(error);
                        alert(error.response.message.description);
                    });
                } else {
                    e.value = e.dataset.current
                    return false;
                }
            }
        </script>
    HTML;

    // checkbox css
    $extend_head .= <<<HTML
        <style>
            /* 將 checkbox 堆疊成 switch 的 css */
            .material-switch > input[type="checkbox"] {
                visibility:hidden;
            }
            .material-switch > label {
                cursor: pointer;
                height: 0px;
                position: relative;
                margin-right: 1.25em
            }
            .material-switch > label::before {
                background: rgb(0, 0, 0);
                box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
                border-radius: 8px;
                content: '';
                height: 16px;
                margin-top: -8px;
                margin-left: -18px;
                position:absolute;
                opacity: 0.3;
                transition: all 0.4s ease-in-out;
                width: 30px;
            }
            .material-switch > label::after {
                background: rgb(255, 255, 255);
                border-radius: 16px;
                box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
                content: '';
                height: 16px;
                left: -4px;
                margin-top: -8px;
                margin-left: -18px;
                position: absolute;
                top: 0px;
                transition: all 0.3s ease-in-out;
                width: 16px;
            }
            .material-switch > input[type="checkbox"]:checked + label::before {
                background: inherit;
                opacity: 0.5;
            }
            .material-switch > input[type="checkbox"]:checked + label::after {
                background: inherit;
                left: 20px;
            }
        </style>
    HTML;

    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content .= <<<HTML
        <div class="row">
            <div class="col-12 col-md-12">{$show_transaction_condition_html}</div>
            <div class="col-12 col-md-12">{$show_tips_html}</div>
            <div class="col-12 d-flex justify-content-end">{$status_filter_html}{$register_review_switch_html}</div>
            <div class="col-12 col-md-12">{$show_list_html}</div>
        </div><br>
        <div class="row">
            <div id="preview_result"></div>
        </div>
    HTML;

} else {
    // 沒有登入的顯示提示俊息
    //(x) 只有管理員或有權限的會員才可以登入觀看。
    $show_transaction_list_html = $tr['only management and login mamber'];

    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content .= <<<HTML
        <div class="row">
            <div class="col-12 col-md-12">{$show_transaction_list_html}</div>
        </div><br>
        <div class="row">
            <div id="preview_result"></div>
        </div>
    HTML;
}

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;

// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;

// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;

// 主要內容 -- title
$tmpl['paneltitle_content'] = <<<HTML
    <span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>{$function_title}
    <p id="member_review_mq" class="mb-0 ml-auto float-right" style="color: #dc3545; display: none;"></p>
HTML;

// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/beadmin_fluid.tmpl.php";
?>
