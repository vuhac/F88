<?php
// ----------------------------------------------------------------------------
// Features:    後台-- 系統彩金發放管理 -- 詳細設定
// File Name:    receivemoney_management_detail.php
// Author:    Barkley Fix by Ian. 2019-12-13 by yaoyuan
// Related:   對應 receivemoney_management.php、receivemoney_management_action.php
//            DB root_receivemoney
// Log:
// Update Time:2019-12-13
// ----------------------------------------------------------------------------
session_start();

require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// -------------------------------------------------------------------------
// 本程式使用的 function
// -------------------------------------------------------------------------

// 將特殊字元加上跳脫
function escape_specialcharator($str)
{
    $reture_str = urldecode($str);
    $reture_str = preg_replace('/([\'])/ui', '&#146;', $reture_str);
    $reture_str = preg_replace('/([""])/ui', '&#148;', $reture_str);
    $reture_str = preg_replace('/([^A-Za-z0-9\p{Han}\s])/ui', '\\\\$1', $reture_str);
    //$reture_str = preg_replace('/([\'])/ui', '\'\'',$reture_str);

    return $reture_str;
}

// -------------------------------------------------------------------------
// 取得日期 - 決定開始用份的範圍日期
// -------------------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

// query ReceiveMoney Record
function query_receivemoney($id){
    $query_sql=<<<SQL
        SELECT *,
        to_char((givemoneytime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as givemoneytime_fix,
        to_char((receivetime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as receivetime_fix,
        to_char((receivedeadlinetime AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as receivedeadlinetime_fix
        FROM root_receivemoney
        WHERE id='{$id}';
    SQL;
        $query_result = runSQLall($query_sql);
        return $query_result;
}

// PayOut status
function status_html($status=2,$status_option){
    global $tr;
    //$tr['Cancel'] = '取消';  $tr['Can receive'] = '可領取';   $tr['time out'] = '暫停'; $tr['received'] ='已領取';
    $status_html = '';
    for ($i = 0; $i <= (count($status_option)-1); $i++) {
        if ($i == $status) {
            $status_html = $status_html . '<option value="' . $i . '" selected>' . $status_option[$i] . '</option>';
        } else {
            $status_html = $status_html . '<option value="' . $i . '">' . $status_option[$i] . '</option>';
        }
    }
    return $status_html;
}

// generate auditmode
function auditmode_html($auditmode='shippingaudit'){
    global $tr;
    // 稽核選項 免稽核freeaudit、存款稽核depositaudit、優惠存款稽核 shippingaudit  $tr['freeaudit'] = '免稽核';  $tr['Deposit audit'] = '存款稽核';  $tr['Preferential deposit audit'] = '優惠存款稽核';
    $auditmode_html = '';
    $auditmode_option = array('freeaudit' => $tr['freeaudit'], 'depositaudit' => $tr['Deposit audit'], 'shippingaudit' => $tr['Preferential deposit audit']);
    foreach ($auditmode_option as $auditmodekey => $auditmodetext) {
        if ($auditmodekey == $auditmode) {
            $auditmode_html = $auditmode_html . '<option value="' . $auditmodekey . '" selected>' . $auditmodetext . '</option>';
        } else {
            $auditmode_html = $auditmode_html . '<option value="' . $auditmodekey . '">' . $auditmodetext . '</option>';
        }
    }
    return $auditmode_html;
}

function ip_fingerprinting_html($member_ip,$member_fingerprinting,$receivetime_fix){
        global $tr;
        $return_html='';
        if ($receivetime_fix != '') {
            $return_html = <<<HTML
                <tr class="row mb-2">
                    <div class="form-group">
                        <td class="col-sm-3">
                            <label class="control-label" for="ip">{$tr['Beneficiary']}IP{$tr['record']}:</label>
                        </td>
                        <td class="col-sm-7">
                            <div>
                                <a href="member_log.php?ip={$member_ip}" target="_BLANK">
                                <input type="text" class="form-control" id="ip" value="{$member_ip}" readonly></a>
                            </div>
                        </td>
                        <td class="col-sm-2"></td>
                    </div>
                </tr>

                <tr class="row mb-2">
                    <div class="form-group">
                        <td class="col-sm-3">
                            <label class="control-label" for="fingerprinting">{$tr['Beneficiary']}Fingerprint{$tr['record']}:</label>
                        </td>
                        <td class="col-sm-7">
                            <div>
                                <a href="member_log.php?fp={$member_fingerprinting}" target="_BLANK">
                                <input type="text" class="form-control" id="fingerprinting" value="{$member_fingerprinting}" readonly></a>
                            </div>
                        </td>
                        <td class="col-sm-2"></td>
                    </div>
                </tr>
HTML;
        return $return_html;
        }
}


// -------------------------------------------------------------------------
// END function lib
// -------------------------------------------------------------------------






//var_dump($_POST);
//var_dump($_GET);
if (isset($_GET['a']) and $_SESSION['agent']->therole == 'R') {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    die($tr['Illegal test']);
}

// 彩金ID
$function_title_show = $tr['add'].$tr['Individual bonus detailed settings'];
if (isset($_GET['d']) and filter_var($_GET['d'], FILTER_VALIDATE_INT)) {
    $bonus_id = filter_var($_GET['d'], FILTER_VALIDATE_INT);
    $function_title_show = $tr['Individual bonus detailed settings'];
}

// 彩金類別
if (isset($_GET['c'])) {
    $prizecategories_get = filter_var($_GET['c'], FILTER_SANITIZE_STRING);
    // $tr['payout to receive the conditions set batch'] = '彩金領取條件批次設定'
    $function_title_show = $tr['payout to receive the conditions set batch'];
    // $prizecategories_get = preg_replace('/([^A-Za-z0-9\p{Han}\s-_@.])/ui', '', urldecode($_GET['c']));
}


// 轉換為美東的時間 date
$date = date_create(date('Y-m-d H:i:sP'), timezone_open('America/St_Thomas'));
date_timezone_set($date, timezone_open('America/St_Thomas'));
$current_datepicker = date_format($date, 'Y-m-d');
$current_datetimepicker = date_format($date, 'Y-m-d H:i:s');


// ----------------------------------------------------------------------------
// Main 初始化樣版革
// ----------------------------------------------------------------------------
// 功能標題，放在標題列及meta $tr['Individual bonus detailed settings'] = '個別彩金詳細設定';
$function_title = $function_title_show;
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = '';
// body 內的主要內容
$indexbody_content = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置 $tr['Home'] ='首頁'; $tr['profit and promotion'] = '營收與行銷';$tr['System bonus payment management'] = '系統獎金發放管理';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['profit and promotion'] . '</a></li>
  <li><a href="receivemoney_management.php">' . $tr['System bonus payment management'] . '</a></li>
  <li class="active">' . $function_title . '</li>
</ol>';


if ($action == 'bonus_edit' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R') {
    $readonly_html = '';
    $select_disable_html='';
    $status_option = array('0' => $tr['Cancel'], '1' => $tr['Can receive'], '2' => $tr['time out']);

    if (isset($bonus_id)) {
        $query_result=query_receivemoney($bonus_id);

        if ($query_result[0] == 1) {
            // 預設不可編輯欄位
            $readonly_defhtml = 'readonly';
            // 更新/新增資料用JS
            $bonus_btn = 'update_record();';
            // 彩金資料
            $query_valarr = $query_result[1];

            if ($query_valarr->receivetime_fix != '') {
                $readonly_html = 'readonly';
                $select_disable_html='disabled';
                $status_option = array('0' => $tr['Cancel'], '1' => $tr['Can receive'], '2' => $tr['time out'],'3'=> $tr['received']);
            }

            // 狀態選項
            $status_html=status_html($query_valarr->status,$status_option);

            // 稽核選項
            $auditmode_html=auditmode_html($query_valarr->auditmode);


            // 截止日期大於現在日期，截止日期的最小值為現在時間。若截止日期小於現在時間，截止日期的最小值就是截止日期
            $receivedeadlinetime = $query_valarr->receivedeadlinetime_fix;
            if ($receivedeadlinetime >= $current_datetimepicker) {
                $mindate['date'] = date("Y-m-d", strtotime($current_datetimepicker));
                $mindate['time'] = date("H:i:s", strtotime($current_datetimepicker));
            } else {
                $mindate['date'] = date("Y-m-d", strtotime($receivedeadlinetime));
                $mindate['time'] = date("H:i:s", strtotime($receivedeadlinetime));
            }

            // var_dump($mindate);
            // die();
            // print("<pre>" . print_r($mindate, true) . "</pre>");die();
        }else{
            die('(X)' .$tr['payout']. $tr['Data error'] . '！error code:201912131618262'); //$tr['Data error'] = '資料錯誤';
        }

    }else{
        $bonus_id = '';
        $query_valarr = new stdClass();
        $query_valarr->id = '';
        $query_valarr->member_id = '';
        $query_valarr->member_account = '';
        $query_valarr->gcash_balance = '0';
        $query_valarr->gtoken_balance = '0';
        $query_valarr->givemoneytime_fix = $current_datetimepicker;
        $query_valarr->receivedeadlinetime_fix = date("Y-m-d H:i:s", strtotime('+1 month', strtotime($current_datetimepicker)));
        $query_valarr->receivetime_fix = '';
        $query_valarr->prizecategories = '';
        $query_valarr->auditmode = 'shippingaudit';
        $query_valarr->auditmodeamount = '0';
        $query_valarr->summary = '';
        $query_valarr->currency = '';
        $query_valarr->transaction_category = '';
        $query_valarr->system_note = '';
        $query_valarr->reconciliation_reference = '';
        $query_valarr->givemoney_member_account = '';
        $query_valarr->status = '2';
        $query_valarr->status_description = '';
        $query_valarr->member_ip = '';
        $query_valarr->member_fingerprinting = '';

       // 更新/新增資料用JS
        $bonus_btn = 'add_record();';

        // 預設不可編輯欄位
        $readonly_defhtml = '';

        // 狀態選項
        $status_html=status_html($query_valarr->status,$status_option);
        // 稽核選項
        $auditmode_html=auditmode_html($query_valarr->auditmode);


        // 依是否讀取過往設定記錄修改顯示的方式
        $mindate['date'] = date("Y-m-d", strtotime($current_datetimepicker));
        $mindate['time'] = date("H:i:s", strtotime($current_datetimepicker));

    }


    $sumary_val= preg_replace('/([\\\\])/ui', '', $query_valarr->summary) ;
    $system_note_val= preg_replace('/([\\\\])/ui', '', $query_valarr->system_note) ;

    // 有領取時間才有 "领取者IP纪录"  "领取者Fingerprint纪录:"
    $ip_fingerprinting_html=ip_fingerprinting_html($query_valarr->member_ip,$query_valarr->member_fingerprinting,$query_valarr->receivetime_fix);

    // print("<pre>" . print_r($ip_fingerprinting_html, true) . "</pre>");die();



    // 主要 html 內容樣版生成
    // $tr['current status'] = '目前狀態為'; $tr['Account'] = '會員帳號';$tr['audit amount'] = '稽核金額';
    // $tr['audit amount'] = '稽核金額';$tr['Description of the source of the prize money'] = '彩金來源說明';
    // $tr['record'] = '紀錄';$tr['Save'] = '儲存';$tr['off'] = '關閉';
    // $tr['Individual bonus detailed settings'] = '個別彩金詳細設定'; $tr['payout category'] = '彩金類別';
    // $tr['Limited to Chinese, English case and number'] = '限中文、英文大小寫及數字'; $tr['payout'] = '彩金';
    // $tr['Release franchise payments'] = '發放加盟金'; $tr['Give cash'] = '發放現金';
    // $tr['Bonus delivery time (US East Time)'] = '獎金送出時間 (美東時間)';
    // $tr['Receive deadline (US East Time)'] = '領取截止時間 (美東時間)';
    // $tr['Receive time (US East time)'] = '領取時間 (美東時間)'; $tr['Audit method'] = '稽核方式';
    // $tr['Summary'] = '摘要'; $tr['Beneficiary'] = '領取者';$tr['record'] = '紀錄';
    // $tr['Save'] = '儲存';$tr['off'] = '關閉';

$indexbody_content = $indexbody_content . <<<HTML
    <form class="form-horizontald" role="form" id="user_form">
        <table class="container-fluid">
            <!-- 狀態 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="status">{$tr['current status']}：</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <select id="status" class="form-control" name="status" {$readonly_html} {$select_disable_html}> {$status_html}</select>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 帳號 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="account"><span style="color:red" class="glyphicon glyphicon-star"></span> {$tr['Account']}：</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" class="form-control" id="account" preholder="EX:aaa" value="{$query_valarr->member_account}" {$readonly_defhtml}>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 彩金類別 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="prizecategories"><span style="color:red" class="glyphicon glyphicon-star"></span>{$tr['payout category'] }：</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" maxlength="100" class="form-control validate[required,maxSize[100]]" id="prizecategories" placeholder="{$tr['Limited to Chinese, English case and number']}，EX:19880101 {$tr['payout']}({$tr['max']}100{$tr['word']})" value="{$query_valarr->prizecategories }" { $readonly_defhtml }>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 發放現金 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="gcash">{$tr['Release franchise payments']}：</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="number" class="form-control" id="gcash" value="{$query_valarr->gcash_balance}" {$readonly_html} onchange="checkvalue();">
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 发放游戏币 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="gtoken">{$tr['Give cash'] }：</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="number" class="form-control" id="gtoken" value="{$query_valarr->gtoken_balance}" {$readonly_html} onchange="checkvalue();">
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 奖金送出时间 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="givemoneytime">{$tr['Bonus delivery time (US East Time)'] }:</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" class="form-control" id="givemoneytime" value="{$query_valarr->givemoneytime_fix}" readonly>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 领取截止时间 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="receivedeadline"><span style="color:red" class="glyphicon glyphicon-star"></span>{$tr['Receive deadline (US East Time)'] }:</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" class="form-control" id="receivedeadline" value="{$query_valarr->receivedeadlinetime_fix}"  {$readonly_html}>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 领取时间 -->
            <tr class="row mb-2 receivetime_td">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="receivetime">{$tr['Receive time (US East time)'] }:</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" class="form-control" id="receivetime" value="{$query_valarr->receivetime_fix}" readonly>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 稽核方式 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="auditmode">{$tr['Audit method'] }:</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <select id="auditmode" class="form-control" name="auditmode" {$readonly_html} disabled>
                            {$auditmode_html} </select>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 稽核金额 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="auditmodeamount">{$tr['audit amount']}:</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" class="form-control" id="auditmodeamount" value="{$query_valarr->auditmodeamount}" {$readonly_html} onchange="checkvalue();" disabled>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 摘要 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="summary">{$tr['Summary'] }:</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" class="form-control validate[maxSize[500]]" maxlength="500" id="summary" placeholder="({$tr['max']}500{$tr['word']})" value="{$sumary_val}" {$readonly_html}>
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            <!-- 彩金来源说明 -->
            <tr class="row mb-2">
                <div class="form-group">
                    <td class="col-sm-3">
                        <label class="control-label" for="note">{$tr['Description of the source of the prize money']}:</label>
                    </td>
                    <td class="col-sm-7">
                        <div>
                            <input type="text" class="form-control validate[maxSize[500]]" maxlength="500" id="note" placeholder="({$tr['max']}500{$tr['word']})" value="{$system_note_val}">
                        </div>
                    </td>
                    <td class="col-sm-2"></td>
                </div>
            </tr>

            {$ip_fingerprinting_html}

            <tr class="row"><td class="col-sm-3"></td>
                <td class="col-sm-7">
                    <button type="button" id="datesave" class="btn btn-info" onclick="{$bonus_btn}">{$tr['Save']}</button>
                    <a href="receivemoney_management.php" class="btn btn-danger">{$tr['off']}</a>
                </td>
                <td class="col-sm-2"></td>
            </tr>

        </table>
    </form>

HTML;



$extend_head = $extend_head . <<<HTML
    <!-- Jquery blockUI js  -->
    <script src="./in/jquery.blockUI.js"></script>
    <!-- jquery datetimepicker js+css -->
    <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
    <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
    <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
    <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

    <script type="text/javascript" language="javascript" class="init">


        function update_record(){
            var status  = $("#status").val();
            // var summary  = encodeURIComponent($("#summary").val());
            var summary  = $("#summary").val();
            var gcash  = $("#gcash").val();
            var gtoken  = $("#gtoken").val();
            var prizecategories  = $("#prizecategories").val();
            var receivedeadline  = $("#receivedeadline").val();
            var auditmode  = $("#auditmode").val();
            var auditmodeamount  = $("#auditmodeamount").val();
            var note  = encodeURIComponent($("#note").val());

            if(gcash == 0 && gtoken == 0){
                alert("{$tr['You have not filled in the amount of bonus to be issued']}！");
            }else if(gcash > 0 && gtoken > 0){
                alert("{$tr['Do not release franchise and cash at the same time, if necessary, please pay separately']}。");
            }else if(account == "" | prizecategories == "" | receivedeadline == ""){
                alert("{$tr['You have not filled out the account number, bonus category or deadline for payment']}！");
            }else if(receivedeadline < '{$current_datetimepicker}'){
                alert("{$tr['Deadline date is expired']}");
            }else{
                blockscreengotoindex();
                $.post("receivemoney_management_action.php?a=bonus_edit&d={$bonus_id}",{
                    status : status,
                    summary : summary,
                    gcash : gcash,
                    gtoken : gtoken,
                    receivedeadline : receivedeadline,
                    auditmode : auditmode,
                    auditmodeamount : auditmodeamount,
                    note : note
                    },
                    function(result){
                    alert(result);
                    $.unblockUI();
                    });
                }
        }

        // 新增彩金發放資料
        function add_record(){
            var status  = $("#status").val();
            var account  = $("#account").val();
            var prizecategories  = encodeURIComponent($("#prizecategories").val());
            var gcash  = $("#gcash").val();
            var gtoken  = $("#gtoken").val();
            var givemoneytime  = $("#givemoneytime").val();
            var receivedeadline  = $("#receivedeadline").val();
            var auditmode  = $("#auditmode").val();
            var auditmodeamount  = $("#auditmodeamount").val();
            // var summary  = encodeURIComponent($("#summary").val());
            var summary  = $("#summary").val();
            var note  = encodeURIComponent($("#note").val());

            //$("#datesave").attr(\'disabled\', \'disabled\');
            if(gcash == 0 && gtoken == 0){
                alert("{$tr['You have not filled in the amount of bonus to be issued']}！");
            }else if(gcash > 0 && gtoken > 0){
                alert("{$tr['Do not release franchise and cash at the same time, if necessary, please pay separately']}。");
            }else if(account == "" | prizecategories == "" | receivedeadline == ""){
                alert("{$tr['You have not filled out the account number, bonus category or deadline for payment']}！");
            }else if(receivedeadline < '{$current_datetimepicker}'){
                alert("{$tr['Deadline date is expired']}");
            }else{
                blockscreengotoindex();

                $.post("receivemoney_management_action.php?a=bonus_add",{
                    status : status,
                    account : account,
                    prizecategories : prizecategories,
                    gcash : gcash,
                    gtoken : gtoken,
                    givemoneytime : givemoneytime,
                    receivedeadline : receivedeadline,
                    auditmode : auditmode,
                    auditmodeamount : auditmodeamount,
                    summary : summary,
                    note : note
                    },
                    function(result){
                        alert(result.logger);
                        $.unblockUI();
                        if(result.id){
                            window.location.href="{$_SERVER['PHP_SELF']}?a=bonus_edit&d="+result.id;
                        }
                    }, 'json');
                }
        };

        function checkvalue(){
            var gcash = $("#gcash").val();
            var gtoken = $("#gtoken").val();
            var auditmodeamount = $("#auditmodeamount").val();
            if(gcash == "" ){
                $("#gcash").val("0");
            }
            if(gtoken == "" ){
                $("#gtoken").val("0");
            }
            if(auditmodeamount == "" ){
                $("#auditmodeamount").val("0");
            }
            if(gtoken > 0 && gcash == 0 ){
                $("#auditmode").prop('disabled', false);
                $("#auditmodeamount").prop('disabled', false);
            }else if(gtoken > 0 && gcash > 0 ){
                alert("{$tr['Do not release franchise and cash at the same time, if necessary, please pay separately']}。");
            }else{
                $("#auditmode").prop('disabled', true);
                $("#auditmodeamount").prop('disabled', true);
            }
        }


        $(document).ready(function () {
            $("#user_form").validationEngine();
            $( "#receivedeadline" ).datetimepicker({
                showButtonPanel: true,
                formatTime: "H:i",
                format: "Y-m-d H:i:s",
                changeMonth: true,
                changeYear: true,
                // minDate:'-1970/01/01',
                minDate:'{$mindate['date']}',
                minTime:'{$mindate['time']}',
                step:10
            });

						//领取时间 (美东时间):
						var linkurl = location.search;
						var link =  linkurl.split('&');
						if ( link.length < 2 ){
							$('.receivetime_td').addClass('d-none');
						}
        });
    </script>


HTML;

} elseif ($action == 'bonus_batchedit' and isset($_SESSION['agent']) and $_SESSION['agent']->therole == 'R' and isset($prizecategories_get)) {
    // die($tr['Illegal test']);

    // -----------------------------------------------------------------------
    // 彩金領取條件批次設定用資料讀取
    // -----------------------------------------------------------------------

    // 檢查傳入的類別值是否存在
    $chk_sql = 'SELECT prizecategories FROM root_receivemoney GROUP BY prizecategories;';
    $chk_result = runSQLall($chk_sql);

    // print("<pre>" . print_r($chk_result, true) . "</pre>");die();

    if ($chk_result[0] >= 1) {
        for ($i = 1; $i <= $chk_result[0]; $i++) {
            if (strval($prizecategories_get) == strval($chk_result[$i]->prizecategories)) {
                $prizecategories = $chk_result[$i]->prizecategories;
            }
        }
    }
    // $tr['category'] = '類別';$tr['EDT(GMT -5)'] = '美東時間';$tr['franchise sum'] = '加盟金總和'; $tr['Sum of cash'] = '現金總和';$tr['Receive the deadline'] = '領取期限'; $tr['The total number of'] = '總筆數'; $tr['Canceled'] = '已取消筆數'; $tr['Can receive pen counts'] = '可領取筆數';$tr['Paused count'] = '已暫停筆數';$tr['Can receive and'] = '可領取且';$tr['Have received count'] = '已領取筆數';$tr['But can receive'] = '可領取但';$tr['Not issued'] = '未發放';
    if (isset($prizecategories)) {
        $data_html = '<table class="table table-striped"><tr>
              <th>' . $tr['category'] . '</th>
              <th>' . $tr['Receive the deadline'] . '<br>(' . $tr['EDT(GMT -5)'] . ')</th>
              <th>' . $tr['The total number of'] . '</th>
              <th>' . $tr['Canceled'] . '</th>
              <th>' . $tr['Can receive pen counts'] . '</th>
              <th>' . $tr['Paused count'] . '</th>
              <th>' . $tr['Can receive and'] . '<br>' . $tr['Have received count'] . '</th>
              <th>' . $tr['But can receive'] . '<br>' . $tr['Did not receive the pen count'] . '</th>
              <th>' . $tr['franchise sum'] . '</th>
              <th>' . $tr['Not issued'] . '<br>' . $tr['franchise sum'] . '</th>
              <th>' . $tr['Sum of cash'] . '</th>
              <th>' . $tr['Not issued'] . '<br>' . $tr['Sum of cash'] . '</th></tr>';

        // 取得領取期限及總量
        $query_sql_1 = "SELECT to_char((receivedeadlinetime  AT TIME ZONE 'AST'), 'YYYY-MM-DD HH24:MI:SS' ) as receivedeadlinetime_fix,prizecategories,count(id),sum(gcash_balance) AS gcash_sum,sum(gtoken_balance) AS gtoken_sum FROM root_receivemoney WHERE prizecategories='$prizecategories' GROUP BY receivedeadlinetime,prizecategories;";
        // echo $query_sql_1;
        $query_1_result = runSQLall($query_sql_1);
        // print("<pre>" . print_r($query_1_result, true) . "</pre>");die();

        $total_count = $query_1_result[1]->count;
        // 將領取期限及總量的值放入table中 $tr['Receive the deadline'] = '領取期限';
        $data_html = $data_html . '<tr><td>' . $query_1_result[1]->prizecategories . '</td>
        <td><a href="#" id="receivedeadlinetime" class="text-left edit_text" data-type="datetime" data-pk="' . $query_1_result[1]->prizecategories . '" data-title="' . $tr['Receive the deadline'] . '" onclick="update_datetimepicker();">' . $query_1_result[1]->receivedeadlinetime_fix . '</a></td>
        <td>' . $query_1_result[1]->count . '</td>';

        // 取得此批彩金的狀態及總筆數
        $query_sql_2 = "SELECT status,count(status) FROM root_receivemoney WHERE prizecategories='$prizecategories' GROUP BY status;";
        //echo $query_sql_2;
        $query_2_result = runSQLall($query_sql_2);
        // print("<pre>" . print_r($query_2_result, true) . "</pre>");die();

        // 將狀態放複陣列
        $status = array();
        for ($i = 1; $i <= $query_2_result[0]; $i++) {
            $status[$query_2_result[$i]->status] = $query_2_result[$i]->count;
        }
        // 將狀態陣列的值放入table中
        for ($i = 0; $i <= 2; $i++) {
            if (isset($status[$i])) {
                $data_html = $data_html . '<td>' . $status[$i] . '</td>';
            } else {
                $data_html = $data_html . '<td> 0 </td>';
            }
        }
        // print("<pre>" . print_r($data_html, true) . "</pre>");die();

        // 取得領取期限
        $query_sql_3 = "SELECT count(receivetime),sum(gcash_balance) AS gcash_sum,sum(gtoken_balance) AS gtoken_sum FROM root_receivemoney WHERE prizecategories='$prizecategories' AND receivetime IS NOT NULL;";
        //echo $query_sql_3;
        $query_3_result = runSQLall($query_sql_3);
        if ($query_3_result[1]->count == 0) {
            $query_3['gcash_sum'] = 0;
            $query_3['gtoken_sum'] = 0;
        } else {
            $query_3['gcash_sum'] = $query_3_result[1]->gcash_sum;
            $query_3['gtoken_sum'] = $query_3_result[1]->gtoken_sum;
        }
        if (isset($status[1])) {
            $notreceive_count = $status[1] - $query_3_result[1]->count;
        } else {
            $notreceive_count = 0;
        }

        $data_html = $data_html . '<td>' . $query_3_result[1]->count . '</td><td>' . $notreceive_count . '</td>';

        $gcash_unrelease = $query_1_result[1]->gcash_sum - $query_3['gcash_sum'];
        $gtoken_unrelease = $query_1_result[1]->gtoken_sum - $query_3['gtoken_sum'];

        // 加盟金及現金的總和及未發總和
        $data_html = $data_html . '<td>' . $query_1_result[1]->gcash_sum . '</td><td>' . $gcash_unrelease . '</td><td>' . $query_1_result[1]->gtoken_sum . '</td><td>' . $gtoken_unrelease . '</td></tr></table>';

        // 主要內容 html 生成
        // $tr['Can receive'] = '可領取';  $tr['all']= '全部';
        // $tr['All set to'] = '全部設為';
        // $tr['time out'] = '暫停';
        // $tr['Cancel'] = '取消';
        // $tr['payout to receive the conditions set batch'] = '彩金領取條件批次設定';
        $indexbody_content = $indexbody_content . '
      <div class="well well-sm col-sm-12">
        <strong>' . $tr['payout to receive the conditions set batch'] . ' - "' . $prizecategories . '"</strong>
      </div>
      <div id="button_area" style="float:right;">
        <button class="btn btn-success btn-xs" id="show" onclick="bonus_batchedit(\'allenable\',\'' . $prizecategories . '\');">' . $tr['All set to'] . '"' . $tr['Can receive'] . '"</button>
        <button class="btn btn-warning btn-xs" id="show" onclick="bonus_batchedit(\'allstop\',\'' . $prizecategories . '\');">' . $tr['All set to'] . '"' . $tr['time out'] . '"</button>
        <button class="btn btn-danger btn-xs" id="show" onclick="bonus_batchedit(\'allcancel\',\'' . $prizecategories . '\');">' . $tr['All set to'] . '"' . $tr['Cancel'] . '"</button>
        <button class="btn btn-info btn-xs" id="show" onclick="bonus_batchedit(\'allstop2en\',\'' . $prizecategories . '\');">' . $tr['all'] . ' "' . $tr['time out'] . '" ' . $tr['set to'] . ' "' . $tr['Can receive'] . '"</button>
      </div>
      <div id="data_table">
      ' . $data_html . '
      </div>
      ';

        // ---------------------------------------------------------------------------
        // 生成時間設定區塊 datepicker
        // ---------------------------------------------------------------------------
        // 依是否讀取過往設定記錄修改顯示的方式
        $receivedeadlinetime = $query_1_result[1]->receivedeadlinetime_fix;
        if ($receivedeadlinetime >= $current_datetimepicker) {
            $mindate['date'] = date("Y-m-d", strtotime($current_datetimepicker));
            $mindate['time'] = date("H:i:s", strtotime($current_datetimepicker));
        } else {
            $mindate['date'] = date("Y-m-d", strtotime($receivedeadlinetime));
            $mindate['time'] = date("H:i:s", strtotime($receivedeadlinetime));
        }
        // var_dump($mindate);die();
        // 建立時間區間設定頁面 $tr['Please set the collection deadline'] = '请设定领取期限'; $tr['set up'] = '設定';
        $indexbody_content = $indexbody_content . '
      <div style="display: none;" id="datepicker">
      <h5 align="center">' . $tr['Please set the collection deadline'] . '</h5>
          <div class="col-12 col-md-9">
            <input type="text" class="form-control" name="update_receivedeadlinetime" id="update_receivedeadlinetime" value="' . $receivedeadlinetime . '">
          </div>
          <div style="float:right;">
            <button class="btn btn-success" onclick="update_receivedeadlinetime();">' . $tr['set up'] . '</button>
            <button class="btn btn-danger" onclick="query_page_close();">' . $tr['Cancel'] . '</button>
          </div>
      <script type="text/javascript" language="javascript" class="init">
          // 更新會員資料
          function update_receivedeadlinetime(){
            $.unblockUI();
            blockscreengotoindex();
            // 取得頁面的時間區間
            var update_receivedeadlinetime  = $("#update_receivedeadlinetime").val();
            // 設定傳送參數
            var query_str = "&a2=update_receivedeadlinetime&c=' . $prizecategories . '&t="+update_receivedeadlinetime;

            $.get("receivemoney_management_action.php?a=bonus_batchedit"+query_str,
              function(result){
                alert(result);
                $("#receivedeadlinetime").html(update_receivedeadlinetime);
                $.unblockUI();
              });
          }
          $( "#update_receivedeadlinetime" ).datetimepicker({
            showButtonPanel: true,
            formatTime: "H:i:s",
            format: "Y-m-d H:i:s",
            changeMonth: true,
            changeYear: true,
            minDate:"' . $mindate['date'] . '",
            minTime:"' . $mindate['time'] . '",
            step:1
          });
      </script>
      </div>
      ';
        // ---------------------------------------------------------------------------
        // 生成時間設定區塊 datepicker END
        // ---------------------------------------------------------------------------

        // 即時編輯工具 ref: https://vitalets.github.io/x-editable/docs.html#gettingstarted
        $extend_head = $extend_head . '
                              <!-- Jquery blockUI js  -->
                              <script src="./in/jquery.blockUI.js"></script>
                              <!-- jquery datetimepicker js+css -->
                              <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
                              <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
                            	';

        // 即時編輯工具 - 線上存款是否開啟 js
        $extend_head = $extend_head . '
      <script>
        function query_page_close(){ // 關閉時間區間選擇
          $.unblockUI();
        }
        function update_datetimepicker(){ // 開啟時間區間選擇
            $.blockUI(
            {
              message: $(\'#datepicker\')
            });
        }
        function bonus_batchedit(action,key){
          blockscreengotoindex();
          var url_str = "&a2="+action+"&c="+key;

          $.get("receivemoney_management_action.php?a=bonus_batchedit"+url_str,
            function(result){
              alert(result);
              $.unblockUI();
            });
        }

      </script>
      ';
    } else {
        die('(401)' . $tr['Data error'] . '！'); //$tr['Data error'] = '資料錯誤';
    }

    // -----------------------------------------------------------------------
    // 彩金領取條件批次設定用資料讀取 END
    // -----------------------------------------------------------------------
} else { //$tr['Illegal test'] = '(x)不合法的測試。';
    die($tr['Illegal test']);
}












// ----------------------------------------------------------------------------












// ----------------------------------------------------------------------------
// Main END
// ----------------------------------------------------------------------------
















$back_btn = '<a href="receivemoney_management.php" type="button" class="btn btn-outline-secondary btn-sm back_btn ml-auto"><i class="fas fa-reply mr-1"></i>'.$tr['return'].'</a>';

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>' . $function_title . $back_btn;
// 主要內容 -- content
$tmpl['panelbody_content'] = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include "template/beadmin.tmpl.php";