<?php
// ----------------------------------------------------------------------------
// Features:	IP錯誤紀錄管理action
// File Name:	login_attempt_ip_management_action.php
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

function filter_string($var,$type= "string"){
    switch($type){
        case 'string':
            $var = isset($var) ? filter_var($var,FILTER_SANITIZE_STRING) : "";
            break;
        case 'url':
            $var = isset($var) ? filter_var($var,FILTER_SANITIZE_URL) : "";
            break;
        case 'email':
            $var = isset($var) ? filter_var($var,FILTER_SANITIZE_EMAIL) : "";
            break;
        case 'int':
        default:
            $var = isset($var) ? filter_var($var,FILTER_SANITIZE_NUMBER_INT) : "";
            break;
    }
    return $var;
}
function select_protal($setting_attempt){
    $sql=<<<SQL
        SELECT id ,name,value FROM root_protalsetting where name = '{$setting_attempt}' order by id desc
SQL;
    $result = runSQLall($sql);
     // $setting_list = [];
     if($result[0] >= 1){
        for($i = 1; $i <= $result[0]; $i++){
            // $setting_list[$result[$i]->id] = $result[$i]->name;
            // $setting_list[$result[$i]->name] = $result[$i]->value;

            $setting_list[$result[$i]->name]['id']= $result[$i]->id;
            $setting_list[$result[$i]->name]['name'] = $result[$i]->name;
            $setting_list[$result[$i]->name]['value'] = $result[$i]->value;
        }
    }

    return $setting_list;
    // return $result;
}

function insert_sql($set_name,$set_value,$set_des){
    $sql=<<<SQL
        INSERT INTO root_protalsetting(setttingname,name,value,description)
        VALUES ('default','{$set_name}','{$set_value}','{$set_des}')
SQL;
    $result = runSQL($sql);

}

function update_sql($set_name,$set_value,$setting_id){
    $sql=<<<SQL
        UPDATE root_protalsetting
            SET value = '{$set_value}'
            WHERE id = '{$setting_id}' AND name = '{$set_name}'
SQL;
    $result = runSQL($sql);
}

if($_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['superuser'])){
    
    $action = isset($_GET['a']) ? filter_string($_GET['a'],"string") : "";

    // ----------------------------
    // section 1: 
    // id
    // $setting_id = isset($_POST['setting_id']) ? filter_string($_POST['setting_id'],"string") : ""; 
    // A.帳號封鎖設定:
    $acc_status = isset($_POST['status_open']) ? filter_string($_POST['status_open'],"string") : "";
    $acc_err_count = isset($_POST['acc_error_count']) ? filter_string($_POST['acc_error_count'],"string") : "";
    $acc_lock_time = isset($_POST['acc_error_time']) ? filter_string($_POST['acc_error_time'],"string") : "";
    // -------------------------------
    // B.ip封鎖設定:
    $ip_status = isset($_POST['ip_status_open']) ? filter_string($_POST['ip_status_open'],"string") : "";
    $ip_err_count = isset($_POST['ip_error_count']) ? filter_string($_POST['ip_error_count'],"string") : "";
    // -------------------------------
    // section 2: ip datatatable
    $id     = isset($_POST['id']) ? filter_string($_POST['id'],"string") : "";
    $status = isset($_POST['ip_status_open']) ? filter_string($_POST['ip_status_open'],"string") : "";
    // $ip_error = isset($_POST['ip_error']) ? filter_string($_POST['ip_error'],"string"): "";

    // -------------------------------
    
    $des_status= '帳號是否封鎖 value=on/off'; // 帳號封鎖設定
    $des_err= '帳號封鎖錯誤次數'; // 帳號錯誤次數
    $des_err_time = '帳號錯誤封鎖時間';

    // $protal_data = select_protal();

    if(isset($action) AND $action == 'acc_status_setting'){
        $protal_data = select_protal('account_status');

        // 帳號封鎖開關
        // 強制更新前後台memcache資料
        $update_result = memcache_forceupdate();

        if($protal_data['account_status']['id'] == ''){
            // 新增
            // 帳號封鎖開關
            $switch_account = insert_sql('account_status',$acc_status,$des_status);
        }else{
            // 編輯
            $switch_account = update_sql('account_status',$acc_status,$protal_data['account_status']['id']);
        }

 
    }elseif(isset($action) AND $action == 'acc_errcount_setting'){
        $protal_data = select_protal('account_err_count');

        // 帳號錯誤次數
        $update_result = memcache_forceupdate();
        if($protal_data['account_err_count']['id'] == ''){
            // 新增
            $count_account_err = insert_sql('account_err_count',$acc_err_count,$des_err);
        }else{
            // 編輯
            $count_account_err = update_sql('account_err_count',$acc_err_count,$protal_data['account_err_count']['id']);

        }

    }elseif(isset($action) AND $action == 'acc_time_setting'){
        $protal_data = select_protal('account_lock_time');

        // 帳號封鎖時間
        $update_result = memcache_forceupdate();
        if($protal_data['account_lock_time']['id'] == ''){
            // 新增
            $lock_account_time = insert_sql('account_lock_time',$acc_lock_time,$des_err_time);
        }else{
            // 編輯
            $lock_account_time = update_sql('account_lock_time',$acc_lock_time,$protal_data['account_lock_time']['id']);

        }
    }

    // ------------------------------------
    // 封鎖IP設定
    $des_ip = 'IP是否封鎖 value=on/off'; // 開關
    $des_ip_error_count = 'IP登入錯誤次數'; // IP錯誤次數
    
    if(isset($action) AND $action == 'ip_status_setting'){
        $protal_data = select_protal('ip_status');
        // ip開關
        // 強制更新前後台memcache資料
        $update_result = memcache_forceupdate();

        if($protal_data['ip_status']['id'] == ''){
            // 新增
            $switch_ip = insert_sql('ip_status',$ip_status,$des_ip);
        }else{
            // 編輯
            $switch_ip = update_sql('ip_status',$ip_status,$protal_data['ip_status']['id']);
        }

    }elseif(isset($action) AND $action == 'ip_errorcount_setting'){
        $protal_data = select_protal('ip_error_count');

       // 強制更新前後台memcache資料
        $update_result = memcache_forceupdate();

        if($protal_data['ip_error_count']['id'] == ''){
            // 新增
            // ip錯誤次數
            $count_ip_error = insert_sql('ip_error_count',$ip_err_count,$des_ip_error_count);

        }else{
            // 編輯
            $count_ip_error = update_sql('ip_error_count',$ip_err_count,$protal_data['ip_error_count']['id']);
        }
    }
        
    if(isset($action) AND $action == 'edit_status' AND isset($id)){
    // datatable ip

        $sql=<<<SQL
            UPDATE root_attempt_login 
                SET counter = '0',
                    status = '{$status}'
                WHERE id = '{$id}'
SQL;
        $result = runSQL($sql);
    
    }elseif($action == 'test') {
        // ----------------------------------------------------------------------------
        // test developer
        // ----------------------------------------------------------------------------
          var_dump($_POST);
          echo 'ERROR';
    }

    // 原版
    // if(isset($action) AND $action == 'acc_setting'){
    //     // 封鎖帳號設定
    
    //         // 強制更新前後台memcache資料
    //         $update_result = memcache_forceupdate();
    
    //         if($protal_data['account_status']['id'] == ''){
    //             // 新增
    //             // 帳號封鎖開關
    //             $switch_account = insert_sql('account_status',$acc_status,$des_status);
    
    //             // 帳號錯誤次數
    //             $count_account_err = insert_sql('account_err_count',$acc_err_count,$des_err);
    
    //             // 帳號封鎖時間
    //             $lock_account_time = insert_sql('account_lock_time',$acc_lock_time,$des_err_time);
    
    //         }else{
    //             // 編輯
    //             // 帳號封鎖開關
    //             $switch_account = update_sql('account_status',$acc_status,$protal_data['account_status']['id']);
    
    //             // 錯誤次數
    //             $count_account_err = update_sql('account_err_count',$acc_err_count,$protal_data['account_err_count']['id']);
    
    //             // 帳號封鎖時間
    //             $lock_account_time = update_sql('account_lock_time',$acc_lock_time,$protal_data['account_lock_time']['id']);
    
    //         }
    
     
    //     }elseif(isset($action) AND $action == 'ip_setting'){
    //     // 封鎖IP設定
    //         // 強制更新前後台memcache資料
    //         $update_result = memcache_forceupdate();
            
    //         $des_ip = 'IP是否封鎖 value=on/off'; // 開關
    //         $des_ip_error_count = 'IP登入錯誤次數'; // IP錯誤次數
    
    //         if($protal_data['ip_status']['id'] == ''){
    //             // 新增
    //             // ip開關
    //             $switch_ip = insert_sql('ip_status',$ip_status,$des_ip);
    
    //             // ip錯誤次數
    //             $count_ip_error = insert_sql('ip_error_count',$ip_err_count,$des_ip_error_count);
    
    //         }else{
    //             // 編輯
    //             $switch_ip = update_sql('ip_status',$ip_status,$protal_data['ip_status']['id']);
    
    //             $count_ip_error = update_sql('ip_error_count',$ip_err_count,$protal_data['ip_error_count']['id']);
    //         }
    
    
    //     }elseif(isset($action) AND $action == 'edit_status' AND isset($id)){
    //     // datatable ip
    
    //         $sql=<<<SQL
    //             UPDATE root_attempt_login 
    //                 SET counter = '0',
    //                     status = '{$status}'
    //                 WHERE id = '{$id}'
    // SQL;
    //         $result = runSQL($sql);
        
    //     }elseif($action == 'test') {
    //         // ----------------------------------------------------------------------------
    //         // test developer
    //         // ----------------------------------------------------------------------------
    //           var_dump($_POST);
    //           echo 'ERROR';
    //     }

}else{
    die('(x)不合法的測試');
}

?>