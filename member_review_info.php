<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 代理商申請審核頁面
// File Name: member_review_info.php
// Author: Damocles
// Related: 對應後台 member_review.php
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__)."/config.php";

// 支援多國語系
require_once dirname(__FILE__)."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__)."/lib.php";

if ( isset($_GET['id']) ) {
    $action = filter_var($_GET['id'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
} else {
    die('(x)不合法的測試');
}

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
$function_title = $tr['Member application for review'];

// 擴充 head 內的 css or js
$extend_head = '';

// 放在結尾的 js
$extend_js = '';

// body 內的主要內容
$indexbody_content = '';

// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = <<<HTML
    <ol class="breadcrumb">
        <li><a href="home.php">{$tr['Home']}</a></li>
        <li><a href="#">{$tr['Members and Agents']}</a></li>
        <li class="active">{$function_title}</li>
    </ol>
HTML;
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if ( isset($action) && isset($_SESSION['agent']) && ($_SESSION['agent']->therole == 'R') ) {

    // 全域變數
    $empty_prefix = '----';

    // 使用者所在的時區，sql 依據所在時區顯示 time
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

    // 搜寻 root_deposit_review 單筆資料
    $member_review_sql = <<<SQL
        SELECT "MAIN"."id",
               "MAIN"."account",
               "MAIN"."nickname",
               "SUB"."realname",
               "MAIN"."mobilenumber",
               "MAIN"."parent_id",
               "MAIN"."sex",
               "MAIN"."birthday",
               "MAIN"."wechat",
               "MAIN"."qq",
               "SUB"."status",
               "SUB"."applicationip",
               "SUB"."processingaccount",
               "SUB"."processingtime",
               to_char( ("SUB"."processingtime" AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as "applicationtime_tz",
               to_char( ("SUB"."applicationtime" AT TIME ZONE '$tzonename'), 'YYYY-MM-DD HH24:MI:SS' ) as "applicationtime_tz",
               "SUB"."notes",
               "SUB"."fingerprinting"
        FROM "root_member_register_review" AS "SUB"
        INNER JOIN "root_member" AS "MAIN"
            ON ("MAIN"."id" = "SUB"."member_id") AND ("MAIN"."account" = "SUB"."account")
        WHERE ("SUB"."member_id" = '{$action}')
    SQL;
    $member_review_result = runSQLALL($member_review_sql);
    // echo '<pre>', var_dump($member_review_result), '</pre>'; exit();

    if ($member_review_result[0] == 1) {
        $member_data = $member_review_result[1];

        // 審核狀態
        $check_status = [
            0 => $tr['application reject'],
            1 => $tr['account valid'],
            2 => $tr['account freeze'],
            3 => $tr['blocked'],
            'auditing' => $tr['auditing']
        ];

        $check_html = '<span>'; // 審核狀態的HTML
        switch ($member_data->status) {
            case 0:
                $check_html .= $check_status[0]; // 會員放棄
                break;
            case 1:
                $check_html .= $check_status[1]; // 通過審查
                break;
            case 2:
                $check_html .= $check_status[2]; // 審核中
                break;
            case 3:
                $check_html .= $check_status[3]; // 管理員處理中
                break;
            case 'auditing':
                $check_html .= $check_status['auditing']; // 審核中
                break;
            default:
                $check_html .= $check_status['auditing'];
        }
        $check_html .= '</span>';

        // 判斷審核的狀態 (待調整)
        /* if($member_data->status == 2){
            $depositing_status_html = "
            <button id=\"agreen_ok\" class=\"btn btn-success btn-sm active\" role=\"button\">{$tr['agree']}</button>
            <button id=\"agreen_cancel\"class=\"btn btn-danger btn-sm active\" role=\"button\">{$tr['disagree']}</button>
            ";
        } else if ($member_data->status == 1){
            $depositing_status_html = "
            <label class=\"label label-warning role=\"label\">{$tr['seq examination passed']}</label>
            ";
        } else {
            $depositing_status_html = "
            <label class=\"label label-danger role=\"label\">{$tr['application reject']}</label>
            ";
        } */

        // 列出資料, 主表格架構
        $show_list_tbody_html = '';

        // 帳號
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['account']}</strong></td>
                <td>{$member_data->account}</td>
                <td></td>
            </tr>
        HTML;

        // 姓名
        $realname = ( !empty($member_data->realname) ? $member_data->realname : $empty_prefix );
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['registered_name']}</strong></td>
                <td>{$realname}</td>
                <td></td>
            </tr>
        HTML;

        // 申請時間
        $applicationtime = gmdate('Y-m-d H:i:s', strtotime($member_data->applicationtime_tz)+-4 * 3600); // 轉成美東時區
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['application time']}</strong></td>
                <td>{$applicationtime}</td>
                <td></td>
            </tr>
        HTML;

        // 社群帳號(標題)
        $sns1 = $protalsetting["custom_sns_rservice_1"] ?? $tr['sns1'];
        $sns2 = $protalsetting["custom_sns_rservice_2"] ?? $tr['sns2'];

        // 聯絡方式
        $contactuser_html = '';
        $mobilenumber = ( !empty($member_data->mobilenumber) ? $member_data->mobilenumber : $empty_prefix );
        $email = ( !empty($member_data->email) ? $member_data->email : $empty_prefix );
        $wechat = ( !empty($member_data->wechat) && !is_null($member_data->wechat) ? $member_data->wechat : $empty_prefix );
        $qq = ( !empty($member_data->qq) ? $member_data->qq : $empty_prefix );
        $contactuser_html .= <<<HTML
                <p>{$tr['Cell Phone']}： {$mobilenumber}</p>
                <p>{$tr['email']}： {$email}</p>
                <p>{$sns1}： {$wechat}</p>
                <p>{$sns2}： {$qq}</p>
        HTML;

        // 聯絡方式
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['contact method']}</strong></td>
                <td>{$contactuser_html}</td>
                <td></td>
            </tr>
        HTML;

        $geoinfo_html = <<<HTML
            <p>{$tr['Browser fingerprint']}：
                <a href="member_log.php?fp={$member_data->fingerprinting}" title="找出曾经在系统内的纪录" target="_blank">{$member_data->fingerprinting}</a>
            </p>
            <p>IP：
                <a href="http://freeapi.ipip.net/{$member_data->applicationip}" target="_blank" title="查询IP来源可能地址位置">{$member_data->applicationip}</a>
            </p>
        HTML;

        // 地理位置及瀏覽器指紋資訊
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['Geographic location and browser fingerprint']}</strong></td>
                <td>{$geoinfo_html}</td>
                <td>{$tr['the data when deposit submit']}</td>
            </tr>
        HTML;

        // 對帳處理人員帳號
        $processingaccount = ( !empty($member_data->processingaccount) ? $member_data->processingaccount : $empty_prefix );
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['Account processing staff account']}</strong></td>
                <td>{$processingaccount}</td>
                <td></td>
            </tr>
        HTML;

        // 對帳完成的時間
        $processingtime = ( !empty($member_data->processingtime) ? gmdate('Y-m-d H:i:s', strtotime($member_data->processingtime)+-4 * 3600) : $empty_prefix );
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['Reconciliation completed time']}</strong></td>
                <td>{$processingtime}</td>
                <td></td>
            </tr>
        HTML;

        // 審核狀態
        $show_list_tbody_html .= <<<HTML
            <tr>
                <td><strong>{$tr['Approval Status']}</strong></td>
                <td>{$check_html}</td>
            </tr>
        HTML;

        // 返回上一頁
        $show_list_return_html = <<<HTML
            <p align="right">
                <a href="#" id="history_go_back" class="btn btn-success btn-sm active" role="button">{$tr['go back to the last page']}</a>
            </p>
            <script>
                $(document).on('click', '#history_go_back', function(){
                    history.go(-1);
                });
            </script>
        HTML;

        // 欄位標題
        $show_list_thead_html = <<<HTML
            <tr>
                <th>{$tr['field']}</th>
                <th>{$tr['content']}</th>
                <th>{$tr['Remark']}</th>
            </tr>
        HTML;

        // 以表格方式呈現
        $show_list_html = <<<HTML
            <table class="table">
                <thead>{$show_list_thead_html}</thead>
                <tbody>{$show_list_tbody_html}</tbody>
            </table>
        HTML;

    } else {
        die($tr['This order number has been processed so far, please do not re-process it.']);
    }


    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content .= <<<HTML
        <div class="row">
            <div class="col-12 col-md-12">{$show_list_html}</div>
        </div><hr>
        {$show_list_return_html}
        <div class="row">
            <div id="preview_result"></div>
        </div>
    HTML;
} else {
    // 切成 1 欄版面
    $indexbody_content = '';
    $indexbody_content .= <<<HTML
        <div class="row">
            <div class="col-12 col-md-12">{$tr['only management and login mamber']}</div>
        </div><br>
        <div class="row">
            <div id="preview_result"></div>
        </div>
    HTML;
}


// 審核狀態按鈕JS
$audit_js = "
$(document).ready(function(){
  // 同意
  $('#agreen_ok').click(function(){
    $('#agreen_ok').attr('disabled', 'disabled');
    var r = confirm('是否确认审核同意?');
    var id = ".$_GET['id'].";
    var notes = $('#notes').val();

    if(r == true){
      $.post('agent_review_action.php?a=agent_review_submit',
        {
          agent_review_id: id,
          agent_notes: notes
        },
        function(result){
          $('#preview_result').html(result);
        }
      )
    }else{
      window.location.reload();
    }
  });
  // 取消
  $('#agreen_cancel').click(function(){
    $('#agreen_cancel').attr('disabled', 'disabled');
    var r = confirm('是否确认审核拒絕?');
    var id = ".$_GET['id'].";
    var notes = $('#notes').val();

    if(r == true){
      $.post('agent_review_action.php?a=agent_review_cancel',
        {
          agent_review_id: id,
          agent_notes: notes
        },
        function(result){
          $('#preview_result').html(result);
        }
      )
    }else{
      window.location.reload();
    }
  });

  // 更新 notes
  $('#agreen_update_notes').click(function(){
    $('#agreen_update_notes').attr('disabled', 'disabled');
    var r = confirm('确定是否更新处理资讯?');
    var id = ".$_GET['id'].";
    var notes = $('#notes').val();
    if(r == true){
      $.post('agent_review_action.php?a=agreen_update_notes',
        {
          agent_review_id: id,
          agent_notes: notes
        },
        function(result){
          $('#preview_result').html(result);
        }
      )
    }else{
      window.location.reload();
    }
  });

});
";
$extend_js = $extend_js."
<script>
".$audit_js."
</script>
";
// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description']         = $tr['host_descript'];
$tmpl['html_meta_author']                     = $tr['host_author'];
$tmpl['html_meta_title']                     = $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']                                = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']                            = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']                                = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content']             = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']                = $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");

?>
