<?php
// ----------------------------------------------------------------------------
// Features:    後台--站長工具--帳號驗證管理
// File Name:   member_authentication_edit_action.php
// Author:      Mavis
// Related:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib_common.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(isset($_GET['a']) AND $_SESSION['agent'] ->therole == 'R'){
    $action = filter_var($_GET['a'],FILTER_SANITIZE_STRING);
} else {
    die('(x)不合法的測試');
}

if(isset($_POST['member_id']) AND $_POST['member_id'] != null){
    $member_id = filter_var($_POST['member_id'],FILTER_VALIDATE_INT);
}

// 2fa 開關
if(isset($_POST['fa_status'])){
    $fa_status_switch = filter_var($_POST['fa_status'],FILTER_SANITIZE_STRING);
}

// ip 開關
if(isset($_POST['ip_status'])){
    $ip_status_switch = filter_var($_POST['ip_status'],FILTER_SANITIZE_STRING);
}


// ip位址與遮罩
if(isset($_POST['json_whitelist_val']) AND $_POST['json_whitelist_val'] != null){
    $csrftoken_ret = csrf_action_check();
    if ($csrftoken_ret['code'] != 1) {
        die($csrftoken_ret['messages']);
    }
    // 解開白名單json，並過濾陣列
    $decode_whitelist_data = json_decode($_POST['json_whitelist_val'],true);
    $whitelist_data_array = filter_var_array($decode_whitelist_data,FILTER_SANITIZE_STRING);  //FILTER_SANITIZE_STRING FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 
     // var_dump($whitelist_data_array);die();
    // if($whitelist_data_array != false){
    //     echo 'mask';die();
    // }
    

}


// 撈出使用者認證資料
function auth_data($member_id){
    $sql = <<<SQL
    SELECT * FROM root_member_authentication WHERE id = '{$member_id}' 
SQL;
    $result = runSQLall($sql);
    return $result;
}


// 新增修改白名單 -- input list
function add_whitelist($ip_encode,$member_id){
    $sql=<<<SQL
        UPDATE root_member_authentication 
        SET changetime = now(),
            whitelis_ip = '{$ip_encode}' 
        WHERE id = '{$member_id}'
SQL;
    // var_dump($sql);die();
    // return runsqlall($sql);
    return $sql;
}

// 改2fa狀態
function update_fa_auth_status($fa_status_switch,$member_id){
    $sql = <<<SQL
    UPDATE root_member_authentication
    SET changetime      = now(),
        two_fa_status = '{$fa_status_switch}'
    WHERE id  			= '{$member_id}';
SQL;
    return $sql;
}

// 改IP白名單狀態
function update_white_auth_status($ip_status_switch,$member_id){
    $sql = <<<SQL
    UPDATE root_member_authentication
    SET changetime      = now(),
        whitelis_status = '{$ip_status_switch}'
    WHERE id  			= '{$member_id}';
SQL;
    return $sql;
}

if($action == 'update' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' ){

    // 原本的單筆資料
    $all_data = auth_data($member_id);
    $fa_status = $all_data[1]->two_fa_status; // 2fa狀態
    $white_list = $all_data[1]->whitelis_status; // ip 狀態
    $white_list_data = $all_data[1]->whitelis_ip; // ip白名單

    $decode_whitelist = json_decode($white_list_data);
    $whitelist_array = filter_var_array($decode_whitelist, FILTER_SANITIZE_STRING);

    $whitelis_ip_ary=[];
    $i = $j =0;
    // ip address
    foreach($whitelist_data_array['ip_address'] as $key=>$value){
        $whitelis_ip_ary[$i][$value['name']] = $value['value'];
        $i++;
        // var_dump($key);die();
    }

    // ip mask
    // foreach($whitelist_data_array['ip_mask'] as $key=>$value){
    //     $whitelis_ip_ary[$j][$value['name']] = $value['value'];
    //     $j++;
    // }

    $ip_encode = json_encode($whitelis_ip_ary);
    // var_dump($ip_encode);die();

    // 如果user把2fa 關閉(只能關)
    if($fa_status_switch != $fa_status){
        $update_switchsql = update_fa_auth_status($fa_status_switch,$member_id);

        $update_switch_result = runSQLall($update_switchsql);

    }elseif($ip_status_switch != $white_list){
        // 如果有改白名單狀態
        $update_switchsql = update_white_auth_status($ip_status_switch,$member_id);

        $update_switch_result = runSQLall($update_switchsql);
    }

    if($ip_encode != null){
        // 改IP
        $update_sql = add_whitelist($ip_encode,$member_id);

        $update_result = runSQLall($update_sql);
    }else{
        $logger = '请新增IP位址。';
        // var_dump($logger);die();
        echo '<script>alert("' .$logger.'");</script>';
    }
    // var_dump($update_sql);die();

}elseif ($action == 'test') {
    var_dump($_POST);
    echo 'ERROR';
}

?>