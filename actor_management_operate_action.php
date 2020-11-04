<?php
// ----------------------------------------------------------------------------
// Features:  後台-- 新增角色、編輯角色 對應動作
// File Name: actor_management_opt_action.php
// Author: Mavis
// Editor: Damocles
// Related:
// DB Table:
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";

// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";

// 文字檔案
require_once dirname(__FILE__) . "/actor_management_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

// 判斷是否有權限操作
if( update_premission( $_SESSION["agent"]->account, $su ) ){
    // 有傳入function_datas、pages_datas才可以執行更新
	if( isset($_POST['function_datas']) ){
        // === 更新functiondata ===
        // 因為從前端傳來boolen會變成string，這邊要轉回boolen
        $_POST['function_datas']['data']['function_public'] = ( ($_POST['function_datas']['data']['function_public']=="true") ? true : false );
        $_POST['function_datas']['data']['function_status'] = ( ($_POST['function_datas']['data']['function_status']=="true") ? true : false );
        $_POST['function_datas']['data']['function_maintain_status'] = ( ($_POST['function_datas']['data']['function_maintain_status']=="true") ? true : false );
        $result_update_function = update_function( $_POST['function_datas']['function_name'], $_POST['function_datas']['data'] );

        // === 更新page data ===
        if( $result_update_function ){
            echo json_encode(['status'=>'success']);
        }
        else{
            echo json_encode(['status'=>'fail']);
        }
    }
    // 沒有收到完整的function_datas、pages_datas，回傳錯誤訊息
    else{
        die( json_encode(['status'=>'fail', 'msg'=>'parameter error']) );
    }
}
// 沒有權限執行該操作
else{
    die( json_encode(['status'=>'fail', 'msg'=>'No Premission To Request Data']) );
}
?>