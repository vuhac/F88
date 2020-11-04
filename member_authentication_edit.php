<?php
// ----------------------------------------------------------------------------
// Features:    後台--站長工具--帳號驗證管理 - 編輯頁面
// File Name:    member_authentication_edit.php
// Author:        Mavis
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
// 傳到view所需引入函式
require_once dirname(__FILE__) ."/lib_view.php";
// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
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
$function_title 		= $tr['User authentication management editor'];
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
  <li><a href="member_authentication.php">{$tr['User authentication management']}</a></li>
  <li class="active">{$function_title}</li>
</ol>
HTML;
// ----------------------------------------------------------------------------
// 將 checkbox 堆疊成 switch 的 css
$extend_head =<<<HTML
<script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
<script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
<link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>
HTML;

$extend_head .= "
<style>

.material-switch > input[type=\"checkbox\"] {
    visibility:hidden;
}

.material-switch > label {
    cursor: pointer;
    height: 0px;
    position: relative;
    width: 40px;
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

if(!(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser']))) {
    echo '<script>alert("您无帐号验证管理权限!");history.go(-1);</script>';die();

}elseif(isset($_GET['id'])){
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT); // 會員id
    // var_dump($id);die();
    $select_sql=<<<SQL
        SELECT
                au.id,
                mb.account,
                "two_fa_status",
                "two_fa_question",
                "two_fa_ans",
                "two_fa_secret",
                whitelis_status,
                to_char((au.changetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS changetime ,
                whitelis_ip
        FROM root_member_authentication AS au
            LEFT JOIN root_member AS mb
                ON au.id = mb.id
                    WHERE mb.id = '{$id}'
                    AND au.id NOT IN('1','2','3')
SQL;
    $result = runSQLall($select_sql);

    if($result[1]->two_fa_status == '1'){
        $is_checked = 'checked';
        // $is_checked = 'success';
        // $fa_text = '啟用';
    }else{
        $is_checked = '';
        // $is_checked = 'danger';
        // $fa_text = '停用';

    }
    if($result[1]->whitelis_status == '1'){
        $ip_status_checked = 'checked';
        // $ip_status_checked = 'success';
        // $ip_text = '啟用';
    }else{
        $ip_status_checked = '';
        // $ip_status_checked = 'danger';
        // $ip_text = '停用';
    }

    $authentication['account'] = $result[1]->account; // 帳號
    $authentication['two_fa_status'] = $is_checked; //2fa 0=停用;1=啟用
    $authentication['whitelis_status'] = $ip_status_checked; // ip白名單 0=停用;1=啟用
    // $authentication['whitelis_ip'] = $result[1]->whitelis_ip; // ip 白名單 jsonb
    
    $ip = '';
    $ip_authentication = '';
    // var_dump($authentication['two_fa_status']);die();
    $show_authentication = '';
    $show_authentication =<<<HTML
         <div class="row">
            <div class="col-12 col-md-12">
                <span class="label label-primary">
                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>{$tr['two-factor authentication']}
                </span>
                <hr>
            </div>
        </div>
HTML;

    $show_authentication .=<<<HTML
        <div class='row'>
            <div class="col-1"></div>
            <div class="col-3"><p class="text-left">{$tr['user account']}</p></div>
            <div class="col-4">
                <input type="text" class="form-control" id="name" value="{$authentication['account']}" disabled>
            </div>
        </div>
      
HTML;

    $show_authentication .=<<<HTML
        <!-- <div class="row">
            <div class="col-12 col-md-12">
                <span class="label label-primary">
                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>验证方式与状态
                </span>
                <hr>
            </div>
        </div> -->
        <div class='row'>
            <div class="col-1"></div>
            <div class="col-3"><p class="text-left">{$tr['two-factor authentication']}</p></div>
            <div class="col-4 material-switch">
                <input id="authentication_2fa_status" name="authentication_2fa_status" class="checkbox_switch" value="0" type="checkbox" {$authentication['two_fa_status']} />
                <label for="authentication_2fa_status" class="label-success"></label>
            </div>
        </div>
HTML;

        // ip白名單 list
        if($authentication['whitelis_status'] == '0'){
            $ip=<<<HTML
                <div class="d-flex mt-1 input-group">
                    <input type="text" class="form-control whitelist_ip validate[required,custom[ipv4]]"  name="whitelist_ip" placeholder="ex.192.168.1.1" value="" >
                    <div class="input-group-append ml-2">
                        <button type="button" class="btn btn-danger delete_btn" title="{$tr['delete']}">
                            <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>   
HTML;
        }else{
            if($result[1]->whitelis_ip != NULL){
                $authentication_decode = json_decode($result[1]->whitelis_ip,true);
                // var_dump($authentication_decode);die();
                foreach($authentication_decode as $key => $value){
                    $ip.=<<<HTML
                    <div class="d-flex mt-1 input-group">
                        <input type="text" class="form-control whitelist_ip validate[required,custom[ipv4]]"  name="whitelist_ip" placeholder="ex.192.168.1.1" value="{$value['ip']}" >
                        <div class="input-group-append ml-2">
                            <button type="button" class="btn btn-danger delete_btn" title="{$tr['delete']}">
                                <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>   
HTML;

                }
            }
        }

    $show_authentication .=<<<HTML
        <div id="ip_option_show">
            <div class="row">
                <div class="col-12 col-md-12">
                    <span class="label label-primary">
                        <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>{$tr['ip whitelisting']}
                    </span>
                    <hr>
                </div>
                </div>
                <div class='row'>
                    <div class="col-1"></div>
                    <div class="col-3"><p class="text-left">{$tr['ip whitelisting']}</p></div>
                    <div class="col-4 material-switch">
                        <input id="authentication_ip_status" name="authentication_ip_status" class="checkbox_switch" value="0" type="checkbox" {$authentication['whitelis_status']} />
                        <label for="authentication_ip_status" class="label-success"></label>
                    </div>
                </div>
                <div class='row'>
                    <div class="col-1"></div>
                    <div class="col-3"><p class="text-left">{$tr['ip address']}</p></div>
                    <!-- <form id="white_list_form" class="form-horizontal"> -->
                        <form class="col-5" id="file_list">
                            {$ip}
                        </form>
                    <!-- </form> -->
                    <div class="col-1">
                        <button type="button" class="btn btn-success" id="add_more" onclick="addItem()" title="{$tr['add'] }">
                            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
        </div>
        <br>
HTML;
    $show_authentication =$show_authentication.'
    <div class="row">
        <div class="col-12 col-md-10">
        <p class="text-right">
            <button id="submit_to_edit" class="btn btn-success"><span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span>&nbsp;'.$tr['Save'].'</button>
            <button id="remove_to_edit" class="btn btn-danger" onclick="javascript:location.href=\'member_authentication.php\'"><span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span>&nbsp;'.$tr['Cancel'].'</button>
        </p>
        </div>
    </div>
';
            
    $extend_js=<<<HTML
    <script>
        // 新增
        function addItem(){
            // console.log('click');
            var str_name = $('input[name="whitelist_ip"]').val();
            var a_ip_status = get_ip_data();
            // <input type="text" class="form-control whitelist_ip validate[required,custom[ipv4]]" name="whitelist_ip" placeholder="ex.192.168.1.1" value="">
            // ip 關
            if(a_ip_status.ip_status == '0'){
                if(str_name != ''){
                    var tmpl = `
                        <div class="d-flex mt-1 input-group">
                        <input type="text" class="form-control whitelist_ip validate[required,custom[ipv4]]" name="whitelist_ip" placeholder="ex.192.168.1.1" value="">
                                <div class="input-group-append ml-2">
                                    <button type="button" class="btn btn-danger delete_btn" title="{$tr['delete']}">
                                        <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                                    </button>
                                </div>
                        </div>
                    `;
                }
                $(tmpl).fadeIn().appendTo("#file_list");

            }else if(a_ip_status.ip_status == '1'){
            // IP 開
                var tmpl = `
                    <div class="d-flex mt-1 input-group">
                         <input type="text" class="form-control whitelist_ip validate[required,custom[ipv4]]" name="whitelist_ip" placeholder="ex.192.168.1.1" value="">
                            <div class="input-group-append ml-2">
                                <button type="button" class="btn btn-danger delete_btn" title="{$tr['delete']}">
                                    <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                                </button>
                            </div>
                        </div>
                    `;
                $(tmpl).fadeIn().appendTo("#file_list");
                
            }
        };

        // 取白名單值
        function get_ip_data(){
            // 啟動白名單欄位驗證
            $("#file_list").validationEngine(); // 不能加遮罩

            // var ip_list = $('input[name="whitelist_ip"]').val();
        
            // if(ip_list.indexOf('/') == false){
            //     var s = '';
            // }

            // ip status
            if($('#authentication_ip_status').prop('checked')){
                var ip_status = '1'; // open
            } else{
                var ip_status = '0'; // close
            };

            // ip 位址
            var white_listip = [];
            $('input[class*="whitelist_ip"]').each(function(){
                white_listip.push({
                    name:'ip',
                    value:$(this).val()
                });
                 //return $(this).val();
            });
            
            // 遮罩
            // var white_mask = [];
            // $('input[class*="whitesubmask_ip"]').each(function(){
            //     white_mask.push({
            //         name:'submask',
            //         value:$(this).val()
            //     });
            //     //return $(this).val();
            // });//.get();

            var data = {
                "ip_address" : white_listip,
                // "ip_mask" : white_mask,
                "ip_status" : ip_status
            }
            // console.log(data);
            return data;

        }

        // function ValidateIPaddress(ipaddress){
        //     if(ipaddress != ''){
        //         if (/^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(ipaddress)){
        //             return true;
        //         }else if(/^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\/\d+$/.test(ipaddress)){
        //             return true;
        //         }
        //         alert('请输入正确的IP位址!');
        //         return false;
        //     }else{
        //         return true;
        //     }
        // }

        // 2FA只能關，開是使用者自己能啟用
        // 停用2FA 
        $('#authentication_2fa_status').click(function(){
            if($(this).prop('checked') == false){
                if(confirm('{$tr['Are you sure you want to disable 2FA verification?']}')){
                    return true; // 按確定，true
                }else{
                    return false; // 取消
                }
            }else{
                $('#authentication_2fa_status').prop('checked',false);
                alert('{$tr['Please go to the security settings to open.']}');
            }
        });

        // 停用IP
        $('#authentication_ip_status').click(function(){
            if($(this).prop('checked')){
                var ip_status = 1; // open
                 if(confirm('{$tr['Are you sure you want to enable IP whitelisting?']}')){
                    $('#authentication_ip_status').prop('checked',true);
                    return true; // 按確定，true
                }else{
                    return false; // 取消
                }
            }else{
                var ip_status = 0; // close
                if(confirm('{$tr['Are you sure you want to disable the IP whitelist?']}')){
                    return true; // 按確定，true
                }else{
                    return false;
                }
            }
        });

        // 刪除ip白名單
        $("#file_list").on('click','.delete_btn',function(e){
            var select = $(e.target).parents(".input-group").remove();
            //console.log(select);
        });

        // 按 儲存時，要檢查IP開關，和內容
        $('#submit_to_edit').click(function(){
            
            if($("#file_list").validationEngine('validate') ){
                var csrftoken = '$csrftoken';
                // 會員id
                var member_id = '$id';
                var get_all_ip_data = get_ip_data();

                // 檢查IP格式
                // if(!ValidateIPaddress($('.whitelist_ip').val())){
                //     console.log('a');
                //     return false;
                // }

                // ip開關開啟且input沒填
                if(get_all_ip_data.ip_status == '1'){
                    if(get_all_ip_data.ip_address == ''){
                        alert('{$tr['Please fill in at least one IP address']}');
                        return false;
                    }
                }


                // ip位址
                // var ip_input_lsit = $('input[name="whitelist_ip"]').map(function(){
                //     return $(this).val();
                // }).get();
                // // console.log(ip_input_lsit);
                // // 遮罩
                // var ip_mask_input_list = $('input[name="whitesubmask_ip"]').map(function(){
                //     return $(this).val();
                // }).get();
                // // console.log(ip_mask_input_list);
            
                // 2fa status
                if($('#authentication_2fa_status').prop('checked')){
                    var fa_status = '1'; // open
                } else{
                    var fa_status = '0'; // close
                };
                // ip status
                if($('#authentication_ip_status').prop('checked')){
                    var ip_status = '1'; // open
                } else{
                    var ip_status = '0'; // close
                };

                var json_whitelist_val = JSON.stringify(get_all_ip_data);
                // console.log(json_whitelist_val);

                $.ajax({
                    url: 'member_authentication_edit_action.php?a=update',
                    type: 'POST',
                    data: {
                        'csrftoken': csrftoken,
                        'member_id': member_id,
                        'json_whitelist_val' : json_whitelist_val,
                        // 'ip_input_lsit': ip_input_lsit,
                        // 'ip_mask_input_list': ip_mask_input_list,
                        'fa_status': fa_status,
                        'ip_status': ip_status
                    },
                    success:function(resp){
                        // console.log('success');
                        window.location.href="member_authentication.php";
                    },
                    error: function(error){
                        console.log('error');
                    }
                })
            }
        })
    </script>
HTML;

}

// 切成 1 欄版面
$indexbody_content = <<<HTML
  <div class="row">
    <div class="col-12 col-md-12">
        {$show_authentication}
    </div>
  </div>
  <br>
  <div class="row">
    <div id="preview_result"></div>
  </div>
HTML;


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