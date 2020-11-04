<?php
// ----------------------------------------------------------------------------
// Features:	IP錯誤紀錄管理
// File Name:	login_attempt_ip_management.php
// Author:		Mavis
// Related:   
// Log:
// ----------------------------------------------------------------------------
session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

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
$function_title 		= $tr['login error log management'];
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs =<<<HTML
<ol class="breadcrumb">
  <li><a href="home.php">{$tr['Home']}</a></li>
  <li><a href="#">{$tr['maintenance']}</a></li>
  <!-- <li><a href="#">{$tr['profit and promotion']}</a></li> -->
  <li class="active">{$function_title}</li>
</ol>
HTML;
// ----------------------------------------------------------------------------

// 該頁面權限設定
if(!(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
    echo '<script>alert("您无帐号验证管理权限!");history.go(-1);</script>';die();
}else{
    $extend_head=<<<HTML
        <!-- Jquery UI js+css  -->
        <script src="in/jquery-ui.js"></script>
        <link rel="stylesheet"  href="in/jquery-ui.css" >
        <!-- Jquery blockUI js  -->
        <script src="./in/jquery.blockUI.js"></script>
        <!-- Datatables js+css  -->
        <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
        <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
        <script type="text/javascript" language="javascript" class="init">
            $(document).ready(function() {
                $("#systeminfo").DataTable( {
                    "paging":   true,
                    "ordering": true,
                    "info":     true,
                    "searching": false,
                    "order": [[ 0, "desc" ]],
                    "pageLength": 30
                } );
            } )
        </script>
HTML;
    // ip datatable
    $sql=<<<SQL
        SELECT * FROM root_attempt_login
SQL;
    $result = runSQLall($sql);  
    
    // protalsetting
    $protalsetting_sql =<<<SQL
        SELECT *
        FROM root_protalsetting 
            WHERE setttingname ='default' 
                AND status = '1'
                ORDER BY id
SQL;

    $protalsetting_result = runSQLall($protalsetting_sql);

    // 欄位名稱
    $table_colname =<<<HTML
    <tr>
        <th class="info text-center">{$tr['ID']}</th>
        <th class="info text-center">IP</th>
        <th class="info text-center">{$tr['IP error times']}</th>
        <th class="info text-center">{$tr['State']}</th>
    </tr>
HTML;

    if($protalsetting_result[0] >= 1){
        for($i = 1; $i <= $protalsetting_result[0]; $i++){
            $setting_list[$protalsetting_result[$i]->name] = $protalsetting_result[$i]->value;

        }
    }

    $lock_account = isset($setting_list['account_status']) ? $setting_list['account_status'] : 'off'; // 帳號封鎖
    $count_lock_acc = isset($setting_list['account_err_count']) ? $setting_list['account_err_count'] : '5'; // 帳號錯誤次數
    $acc_lock_time = isset($setting_list['account_lock_time']) ? $setting_list['account_lock_time'] : '15'; // 封鎖帳號時間
    
    $lock_ip = isset($setting_list['ip_status']) ? $setting_list['ip_status'] : 'off'; // 封鎖IP
    $count_lock_ip = isset($setting_list['ip_error_count']) ? $setting_list['ip_error_count'] : '20'; // IP錯誤次數

    // 帳號封鎖設定開關
    if($lock_account == 'on'){
        $check_acc = 'checked';
    }else{
        $check_acc = '';
    }
    // ip 封鎖設定開關
    if($lock_ip == 'on'){
        $check_ip = 'checked';
    }else{
        $check_ip = '';
    }

    $section_one = <<<HTML
        <div class='row'>
            <div class="col-12 col-md-12">
                <div class="well">

                    <div class="row">
                        <div class="col-xs-2 col-md-6"><span class="glyphicon glyphicon-cog"></span><strong>{$tr['account banned setting']} </strong></div>
                        <!-- <div class="col-12 col-md-10">
                            <p class="text-right"></p>
                        </div> -->
                    </div>
                    <table class="table table-striped">
                        <thead></thead>
                        <tbody>
                            <tr>
                                <td class="text-right" width="25%"><strong>{$tr['account banned']}</strong></td>
                                <td class="acc_setting">
                                    <div class="col-12 material-switch pull-left">
                                        <input id="account_lock_status" name="account_lock_status" class="checkbox_switch" value="{$lock_account}" type="checkbox" {$check_acc}/>
                                        <label for="account_lock_status" class="label-success"></label>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td class="text-right" width="25%"><strong>{$tr['error number']}</strong></td>
                                <td>
                                    <input type="text" id="acc_err_count" name="points" min="0" max="20" value="{$count_lock_acc}"  onkeyup="value=value.replace(/[^\d]/g,'') " >{$tr['times']}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-right" width="25%"><strong>{$tr['banned time']}</strong></td>
                                <td>
                                    <input type="text" id="acc_lock_time" name="points" min="0" max="60" value="{$acc_lock_time}" onkeyup="value=value.replace(/[^\d]/g,'') " >{$tr['minutes']}
                                    <div><small>{$tr['When the account locked is on,within 15 minutes the user login errors']}{$count_lock_acc}{$tr['times']}{$tr['and the account will be banned for']}{$acc_lock_time}{$tr['minutes']}。</small></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="well">
                    <div class="row">
                        <div class="col-xs-2 col-md-6"><span class="glyphicon glyphicon-cog"></span><strong>{$tr['IP banned setting']}</strong></div>
                        <!-- <div class="col-12 col-md-10">
                            <p class="text-right">
                            </p>
                        </div> -->
                    </div>
                    <table class="table table-striped">
                        <thead></thead>
                        <tbody>
                            <tr>
                                <td class="text-right" width="25%"><strong>{$tr['IP banned']}</strong></td>
                                <td class="ip_setting">
                                    <div class="col-12 material-switch pull-left">
                                        <input id="ip_lock_status" name="ip_lock_status" class="checkbox_switch" value="{$lock_ip}" type="checkbox" {$check_ip}/>
                                        <label for="ip_lock_status" class="label-success"></label>
                                    </div>     
                                </td>
                            </tr>

                            <tr>
                                <td class="text-right" width="25%"><strong>{$tr['error number']}</strong></td>
                                <td>
                                    <input type="text" id="ip_err_count" name="points" min="0" max="35" value="{$count_lock_ip}" onkeyup="value=value.replace(/[^\d]/g,'') " >{$tr['times']}
                                    <div><small>{$tr['When IP locked is on, within 15 minutes the user login error']}{$count_lock_ip}{$tr['times']}{$tr[', and IP will be banned and must be opened by the customer service.']}</small></div>
                                </td>
                            </tr>

                        </tbody>
                        </table>
                </div>
            </div>
        </div>
HTML;

    $list_data = '';

    if($result[0] >= 1){
        for($i=1;$i<=$result[0];$i++){
            $ip_id = $result[$i]->id; // id
            $ip_address = $result[$i]->ip_address; // ip位址
            $ip_error_count = $result[$i]->counter; // 錯誤次數
            $ip_atatus = $result[$i]->status; // ip狀態
        
            $isopen_switch = '';

            // 狀態
            if($ip_atatus == '1') {
              $isopen_switch = 'checked';
            } elseif($ip_atatus == '0') {
              $isopen_switch = '';
            }

            $list_data .=<<<HTML
                <tr>
                    <td class="text-center">{$ip_id}</td>
                    <td class="text-center">{$ip_address}</td>
                    <td class="text-center">{$ip_error_count}</td>
                    <td class="text-center">
                        <div class="col-12 material-switch pull-left">
                            <input id="ip_status_open_{$ip_id}" name="ip_status_open_{$ip_id}" class="checkbox_switch" value="{$ip_id}" type="checkbox" {$isopen_switch} />
                            <label for="ip_status_open_{$ip_id}" class="label-success"></label>
                        </div>
                    </td>
                </tr>
HTML;
        }
    }

    // 列出資料
    $show_list_html =<<<HTML
    {$section_one}
    <table id="systeminfo"  class="display" cellspacing="0" width="100%">
        <thead>
        {$table_colname}
        </thead>
        <tfoot>
        {$table_colname}
        </tfoot>
        <tbody>
        {$list_data}
        </tbody>
    </table>
HTML;

    $extend_js =<<<HTML
    <script>
        $(document).ready(function(){

            // datatable ip 修改狀態
            $('#systeminfo').on('click', '.checkbox_switch', function() {

                var id = $(this).val();

                if($('#ip_status_open_'+id).prop('checked')){
                    // 關閉 -> 開啟
                    var ip_status_open = 1;
                    if(!confirm('该IP即将開啟，错误次数会归0。')){
                        return false;
                    }

                }else{
                    // 開啟 -> 關閉
                    var ip_status_open = 0;
                    if(!confirm('该IP即将關閉，错误次数会归0。')){
                        return false;
                    }
                }

                if(id != '') {
                    $.ajax ({
                        url: 'login_attempt_ip_management_action.php?a=edit_status',
                        type: 'POST',
                        data: ({
                            id: id,
                            ip_status_open: ip_status_open
                        }),
                        success: function(response){
                            // $('#preview_result').html(response);
                            window.location.href = 'login_attempt_ip_management.php';
                        },
                        error: function (error) {
                            $('#preview_result').html(error);
                        },
                    });       
                }else{
                    alert('{$tr['Illegal test']}');
                }
            });

            // ----------------------------------------------------
            // 帳號封鎖
            $('#account_lock_status').click(function(e){
                e.preventDefault();

                if($('#account_lock_status').prop('checked')){
                    var status_open = 'on'; // on
                } else{
                    var status_open = 'off'; // off
                };

                $.ajax({
                    url: 'login_attempt_ip_management_action.php?a=acc_status_setting',
                    type: 'POST',
                    data: ({
                        status_open: status_open
                    }),
                    success: function(response){
                        // $('#preview_result').html(response);
                        window.location.href = 'login_attempt_ip_management.php';
                    },
                    error: function(error){
                        $('#preview_result').html(error);
                    }
                })
            })

             // 錯誤次數
            $('#acc_err_count').change(function(e){
                e.preventDefault();

                var acc_error_count = $('#acc_err_count').val();

                $.ajax({
                    url: 'login_attempt_ip_management_action.php?a=acc_errcount_setting',
                    type: 'POST',
                    data: ({
                        acc_error_count: acc_error_count
                    }),
                    success: function(response){
                        // $('#preview_result').html(response);
                        window.location.href = 'login_attempt_ip_management.php';
                    },
                    error: function(error){
                        $('#preview_result').html(error);
                    }
                })
            })
            // 封鎖時間
            $('#acc_lock_time').change(function(e){
                e.preventDefault();

                // 被封鎖時間
                var acc_error_time = $('#acc_lock_time').val();

                $.ajax({
                    url: 'login_attempt_ip_management_action.php?a=acc_time_setting',
                    type: 'POST',
                    data: ({
                        acc_error_time: acc_error_time
                    }),
                    success: function(response){
                        // $('#preview_result').html(response);
                        window.location.href = 'login_attempt_ip_management.php';
                    },
                    error: function(error){
                        $('#preview_result').html(error);
                    }
                })
            })
            // --------------------------------------------
            // ip狀態
            $('#ip_lock_status').click(function(e){
                e.preventDefault();

                if($('#ip_lock_status').prop('checked')){
                    var ip_status_open = 'on'; // on
                } else{
                    var ip_status_open = 'off'; // off
                };

                $.ajax({
                    url: 'login_attempt_ip_management_action.php?a=ip_status_setting',
                    type: 'POST',
                    data: ({
                        ip_status_open: ip_status_open
                    }),
                    success: function(response){
                        // $('#preview_result').html(response);
                        window.location.href = 'login_attempt_ip_management.php';
                    },
                    error: function(error){
                        $('#preview_result').html(error);
                    }
                })
            })
            // IP錯誤次數
            $('#ip_err_count').change(function(e){
                e.preventDefault();

                var ip_error_count = $('#ip_err_count').val();

                $.ajax({
                    url: 'login_attempt_ip_management_action.php?a=ip_errorcount_setting',
                    type: 'POST',
                    data: ({
                        ip_error_count: ip_error_count
                    }),
                    success: function(response){
                        // $('#preview_result').html(response);
                        window.location.href = 'login_attempt_ip_management.php';
                    },
                    error: function(error){
                        $('#preview_result').html(error);
                    }
                })
            })

        });
    </script>
HTML;


}
    // 切成 1 欄版面
    $indexbody_content =<<<HTML
    <div class="row">
        <div class="col-12 col-md-12">
        {$show_list_html}
        </div>
    </div>
    <br>
    <div class="row">
        <div id="preview_result"></div>
    </div>
HTML;

// 將 checkbox 堆疊成 switch 的 css
$extend_head = $extend_head. "
<style>

.material-switch > input[type=\"checkbox\"] {
    visibility:hidden;
}

.material-switch > label {
    cursor: pointer;
    height: 0px;
    position: relative;
    width: 0px;
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
.material-switch > input[type=\"checkbox\"]:checked + label::before {
    background: inherit;
    opacity: 0.5;
}
.material-switch > input[type=\"checkbox\"]:checked + label::after {
    background: inherit;
    left: 20px;
}
</style>
";


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
include("template/beadmin.tmpl.php");

?>