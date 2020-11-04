<?php
// ----------------------------------------------------------------------------
// Features:	後台--子帳號管理/編輯管理員
// File Name:	admin_management_edit.php
// Author:		yaoyuan
// Editor: Damocles
// Related:
  //  系統主程式：admin_management_edit.php
  //  主程式樣版：admin_management_edit_view.php
  //  主程式action：admin_management_edit_action.php
  //  DB table: root_member
// Log:
// ----------------------------------------------------------------------------

@session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";
// 傳到view所需引入函式
require_once dirname(__FILE__) ."/lib_view.php";
require_once dirname(__FILE__) ."/actor_management_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 測試模式 (2019/09/27 By Damocles)
if( !isset($_SESSION['debug_mode']) || ($_SESSION['debug_mode']==false) ){
    if( !( isset($_SESSION['agent']) && ($_SESSION['agent']->therole=='R') && in_array($_SESSION['agent']->account, $su['superuser']) ) ){
        echo <<<HTML
            <script>
                alert("{$tr['You do not have permission to edit the sub-account function!']}");
                history.go(-1);
            </script>
        HTML;
        die();
    }
}

// public-function group data
$function_groups_data = query_function_group();
unset( $function_groups_data[0] );

// 更新 查詢欲編輯的帳號資料
if( isset($_GET['id']) && is_numeric($_GET['id']) ){
    $id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    $account_data = query_account_data('id', $id);

    // 有查詢到該帳號
    if($account_data[0] == 1){
        $operating_account = $account_data[1]->account;
        $account_data = $account_data[1];
        // stop-登入帳號對此帳號沒有管理權限，會在這邊擋下
        if( !heighter_premission($_SESSION["agent"]->account, $operating_account, $su) ){
            die(<<<HTML
                <script>
                    alert("Sorry, you have no premission to access this page.");
                    history.go(-1);
                </script>
            HTML);
        }
    }
    // stop-查詢無此帳號
    else{
        die(<<<HTML
            <script>
                alert("Sorry, I can't find this account.");
                history.go(-1);
            </script>
        HTML);
    }

    // 查詢帳號設定值 (如果沒有帳號設定值的話，以預設值去新增一筆帳號設定值)
    $operating_account_setting_data = query_account_setting('account', $operating_account);
    // 有查詢到欲操作的帳號設定值
    if($operating_account_setting_data[0] == 1){
        $account_setting = $operating_account_setting_data[1];
    }
    // 沒有該帳號的設定值，以預設值去新增一筆帳號設定值
    else{
        if( insert_account_setting(['account'=>$operating_account]) ){
            $account_setting = query_account_setting('account', $operating_account);
            $account_setting = $operating_account_setting_data[1];
        }
        // stop-建立帳號的設定值失敗
        else{
            die(<<<HTML
                <script>
                    alert("Sorry, I can't generate this account setting.");
                    history.go(-1);
                </script>
            HTML);
        }
    }

    // 查詢帳號function權限
    $account_function_premission = function_premission($operating_account);
    // echo '<pre>', var_dump($account_function_premission), '</pre>';exit();

    // 遍歷groups，判斷每個group是否都有底下function的權限
    foreach( $function_groups_data as $key_group=>$val_group ){
        $has_all_function_premission = true; // 該group有底下所有function權限，含unpublic function
        $all_public_function = true; // 該group底下所有function都是public

        // 遍歷function
        foreach( $account_function_premission as $key_function=>$val_function ){
            if( $val_function->group_name == $val_group->group_name ){ // 判斷該funciton屬於這個group
                // 該function不是public
                if( !$val_function->function_public ){
                    $all_public_function = false;
                    // 該function也沒有權限
                    if( !$val_function->has_premission ){
                        $has_all_function_premission = false;
                    }
                }
            }
        } // end function foreach

        // 在function group裡面加上參數
        $function_groups_data[$key_group]->has_all_function_premission = $has_all_function_premission;
        $function_groups_data[$key_group]->all_public_function = $all_public_function;
    } // end group foreach

    $function_title = $tr['editor the administrator'];
    // 將內容塞到 html meta 的關鍵字, SEO 加強使用
    $tmpl['html_meta_title']= $function_title.'-'.$tr['host_name'];

    // echo '<pre>', var_dump($account_data), '</pre>'; exit();
    return render(
    __DIR__ . '/admin_management_edit_view.php',
        compact(
            'function_title',
            'account_data',
            'account_setting',
            'account_function_premission',
            'function_groups_data'
        )
    );
}
// 新增
else{
    $function_title = $tr['add the administrator'];
    // 將內容塞到 html meta 的關鍵字, SEO 加強使用
    $tmpl['html_meta_title']= $function_title.'-'.$tr['host_name'];

    $account_function_premission = function_premission();

    foreach( $function_groups_data as $key_outer=>$val_outer ){
        $all_public_function = true;
        foreach( $account_function_premission as $key_inner=>$val_inner ){
            // 跟安平討論過後要把客服管理員權限移到帳號資料區塊，不放在檔案讀取的權限管理內，故在這邊要先移除掉客服管理員權限。
            // if( $val_inner->function_name == 'customer_service_management_authority' ){
            //     unset( $account_function_premission[$key_inner] );
            // }
            // else{
                if( $val_inner->group_name == $val_outer->group_name ){ // 判斷該funciton屬於這個function group
                    if( !$val_inner->function_public ){
                        $all_public_function = false;
                    }
                }
            // }
        } // end inner foreach

        // 在function group裡面加上參數
        $function_groups_data[$key_outer]->all_public_function = $all_public_function;
        $function_groups_data[$key_outer]->has_all_function_premission = false;
    } // end outer foreach

    return render(
        __DIR__ . '/admin_management_edit_view.php',
        compact(
            'function_title',
            'account_function_premission',
            'function_groups_data'
        )
    );
}