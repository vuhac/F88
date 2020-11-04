<?php
// ----------------------------------------------------------------------------
// Features:	後台--子帳號操作(新增/新更)
// File Name:	admin_management_edit_action.php
// Author:		Damocles
// Related:

// Log:
// ----------------------------------------------------------------------------

@session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";

// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

// 自訂函式庫
require_once dirname(__FILE__) ."/lib_common.php";
require_once dirname(__FILE__) ."/actor_management_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------


// 更新指定帳號對function的權限
function update_unpublic_function_premission( $account, $function_premission ){
    if( count($function_premission) > 0 ){
        foreach( $function_premission as $val ){
            $unpublic_function_premission_data = query_unpublic_function_premission( $account, $val['function_name'] );

            // 賦予權限
            if( $val['status']=='t' ){
                // 沒有紀錄，要賦予權限
                if( $unpublic_function_premission_data[0] == 0 ){
                    $insert_sql = <<<SQL
                        INSERT INTO root_member_account_unpublic_function_access_premission (
                            account,
                            function_name
                        ) VALUES (
                            '{$account}',
                            '{$val["function_name"]}'
                        );
                    SQL;
                    $insert_result = runSQLall( $insert_sql, 0 );
                }
                // 有紀錄但沒權限，更新為有權限

                else if( ($unpublic_function_premission_data[0] == 1) && (!$unpublic_function_premission_data[1]->premission_status) ){
                    $update_sql = <<<SQL
                        UPDATE root_member_account_unpublic_function_access_premission
                        SET premission_status = 't'
                        WHERE (account = '{$account}') AND
                              (function_name = '{$val["function_name"]}');
                    SQL;
                    $update_result = runSQLall( $update_sql, 0 );
                }
            }
            // 取消權限
            else if( $val['status'] == 'f' ){
                if( ($unpublic_function_premission_data[0] == 1) && ($unpublic_function_premission_data[1]->premission_status) ){
                    $update_sql = <<<SQL
                        UPDATE root_member_account_unpublic_function_access_premission
                        SET premission_status = 'f'
                        WHERE (account = '{$account}') AND
                              (function_name = '{$val["function_name"]}')
                    SQL;
                    $update_result = runSQLall( $update_sql, 0 );
                }
            }
        } // end foreach
    }
} // end update_unpublic_function_premission

// 查詢目前子帳號的編號( 2019/11/28跟宜恩討論管理員帳號編號從500(含500)編至5000(含5000) )
function generate_admin_account_num(){
    $min_num = 500;
    $max_num = 5000;

    // 查詢目前最新一筆在編制區間內的編號
    $query = <<<SQL
        SELECT id
        FROM root_member
        WHERE (id BETWEEN {$min_num} AND {$max_num})
        ORDER BY id DESC
        LIMIT 1;
    SQL;
    $result = runSQLall($query, 0);

    // 從未設定管理員帳號，開始從第500號編制
    if( $result[0] == 0 ){
        $new_admin_account_num = $min_num;
    }
    else{
        $new_admin_account_num = ($result[1]->id + 1);
        if( $new_admin_account_num > $max_num ){
            // 管理員帳號的編號區間已用盡
            die($tr['The number range of the administrator account has been exhausted']);
        }
    }

    return $new_admin_account_num;
} // end generate_admin_account_num

// 比較2組資料，找出不同的地方
function compare_datas($old_data, $new_data){
    // -- Sample(等同於runSQLall後移除索引值0) --
    // $old_data = $new_data = [object{
    //     'key'=> 'value',
    //     'key'=> 'value'
    // }];

    $old_data_key_array = []; // 第2層資料列的索引值
    $new_data_key_array = []; // 第2層資料列的索引值

    // 把object轉成array
    foreach($old_data as $key=>$val){
        // 把第2層資料的索引值裝入array，用於後續比較
        $key2_array = [];
        foreach($val as $key2=>$val2){
            array_push($key2_array, $key2);
        } // end foreach
        array_push($old_data_key_array, $key2_array);

        if( is_object($val) ){
            $old_data[$key] = (array)$val;
        }
    } // end foreach

    // 把object轉成array
    foreach($new_data as $key=>$val){
        // 把第2層資料的索引值裝入array，用於後續比較
        $key2_array = [];
        foreach($val as $key2=>$val2){
            array_push($key2_array, $key2);
        } // end foreach
        array_push($new_data_key_array, $key2_array);

        if( is_object($val) ){
            $new_data[$key] = (array)$val;
        }
    } // end foreach

    // 判斷$old_data跟$new_data第1層資料長度是否一樣
    if( count($old_data) != count($new_data) ){
        return json_encode([
            'status' => 'fail',
            'title' => $tr['The length of the first layer of the 2 sets of data is inconsistent'], // 2組資料第1層長度不一致
            'content' => ''
        ]);
    }

    // 比對2組data第2層的索引值
    $exist = true;
    $key_diff_column_num = []; // 用來裝載第幾筆資料的key比對不一樣
    foreach($old_data_key_array as $key=>$val){
        if( isset($new_data_key_array[$key]) ){
            $key_diff = array_diff($old_data_key_array[$key], $new_data_key_array[$key]);
            if( count($key_diff) > 0 ){
                array_push($key_diff_column_num, [
                    'key_diff' => $key_diff
                ]);
            }
        }
        else{
            $exist = false;
            break;
        }
    } // end foreach

    if( (count($key_diff_column_num) > 0) || (!$exist) ){
        return json_encode([
            'status' => 'fail',
            'title' => $tr['The index value of layer 2 of the 2 groups of data is inconsistent'], // 2組資料第2層索引值不一致
            'content' => $key_diff_column_num
        ]);
    }

    // 開始比對資料
    $diff_key_val = [];
    for($i=1; $i<=count($old_data); $i++){
        foreach($old_data_key_array[$i-1] as $key_inner=>$val_inner){
            if( $old_data[$i][$val_inner] != $new_data[$i][$val_inner] ){
                array_push($diff_key_val, [
                    $i => [
                        $val_inner => [
                            'before' => $old_data[$i][$val_inner],
                            'after' => $new_data[$i][$val_inner]
                        ]
                    ]
                ]);
            }
        } // end foreach
    } // end for
    return $diff_key_val;
} // end compare_datas
/*----------------------------------------------------------------------------------------------------------------------------*/

// return-查詢帳號是否有重複 (使用lib.php的query_account_data)
if( isset($_POST['method']) && isset($_POST['account']) && ($_POST['method']=='query') ){
    die(json_encode([
        'rowCount' => query_account_data('account', $_POST['account'])[0]
    ]));
}

// stop-判斷必要參數是否有post
if( !isset($_POST['account_data']) || !isset($_POST['account_setting']) ){
    echo <<<HTML
        <script>
            alert('{$tr["Parameter error"]}'); // 參數錯誤！
            history.go(-1);
        </script>
    HTML;
    exit();
}

// 判斷是否有$_POST['account_data']['id']且不為空值，來判斷進行 新增子帳號 or 更新子帳號
// *** 更新子帳號 ***
if( isset($_POST['account_data']['id']) && !empty($_POST['account_data']['id']) && ($_POST['account_data']['id'] != null) ){
    $account_data = $_POST['account_data'];
    $id = $account_data['id'];
    unset($account_data['id']);

    // 沒有更新密碼的話，就把密碼欄位移除
    if( empty($account_data['passwd']) || ($account_data['passwd'] == null) ){
        unset($account_data['passwd']);
    }
    // === 更新帳號資料 ===
    // 1-1.更新前先查詢該帳號的資料
    $query_result = query_account_data('id', $id)[1];
    $account = $query_result->account;

    // stop-判斷是否有權限操作(因為前台傳來的ID有可能會被串改過，故更新前要多加確認登入帳號是否有權限執行此操作)
    if( !heighter_premission($_SESSION['agent']->account, $account, $su) ){
        return json_encode([
            'status' => 'fail',
            'title' => $tr['No permission to perform this operation'], // 無權限執行此操作
            'content' => $tr['The logged-in account does not have permission to operate this account'] // 登入帳號無權限對此帳號操作
        ]);
    }

    // 1-2.更新帳號資料(return boolen)
    $update_account_data_result = update_account_data($account, $account_data);

    // 1-3.比較新舊資料差異，差異的新舊值放入$record
    $record = [];
    if( $query_result->realname != $account_data['realname'] ){
        array_push($record, [
            'realname' => [
                'before' => $query_result->realname,
                'after' => $account_data['realname']
            ]
        ]);
    }

    if( isset($account_data['passwd']) ){
        array_push($record, [
            'passwd' => [
                'before' => '',
                'after' => $account_data['passwd']
            ]
        ]);
    }

    if( $query_result->mobilenumber != $account_data['mobilenumber'] ){
        array_push($record, [
            'mobilenumber' => [
                'before' => $query_result->mobilenumber,
                'after' => $account_data['mobilenumber']
            ]
        ]);
    }

    if( $query_result->email != $account_data['email'] ){
        array_push($record, [
            'email' => [
                'before' => $query_result->email,
                'after' => $account_data['email']
            ]
        ]);
    }

    if( $query_result->status != $account_data['status'] ){
        array_push($record, [
            'status' => [
                'before' => $query_result->status,
                'after' => $account_data['status']
            ]
        ]);
    }

    if( $query_result->notes != $account_data['notes'] ){
        array_push($record, [
            'notes' => [
                'before' => $query_result->notes,
                'after' => $account_data['notes']
            ]
        ]);
    }

    // 1-4.把$record寫入root_memeberlog內
    if( count($record) > 0 ){
        $log = json_encode([
            'status' => ( ($update_account_data_result) ? 'success' : 'fail' ),
            'record' => $record
        ]);
        // 修改寫入root_memberlog的message
        $msg_log = $tr['Modify administrator information']; // 修改管理員資料
        memberlogtodb(
            $_SESSION['agent']->account,
            'admin_management_edit',
            'info',
            $msg_log,
            $account,
            $log
        );
    }

    // === 更新帳號設定值 ===
    // 2-1.取得帳號的設定值
    $old_account_setting = query_account_setting('account', $account);
    if( $old_account_setting[0] == 1 ){
        unset($old_account_setting[0]);
        $old_account_setting = (array)$old_account_setting[1];
    }
    // stop-沒有帳號的設定值
    else{
        return json_encode([
            'status' => 'fail',
            'title' => 'operate failed',
            'content'=> $tr['An exception occurred, and no account setting data was found'] // 發生異常，查無帳號的設定資料
        ]);
    }

    // 2-2.更新帳號設定值
    $new_account_setting = $_POST['account_setting']['content'];
    $update_account_setting_result = update_account_setting($account, $new_account_setting);

    // 2-3.比對帳號設定值的差異
    $record = [];
    foreach($new_account_setting as $key=>$val){
        if( $new_account_setting[$key] != $old_account_setting[$key] ){
            array_push($record, [
                $key =>[
                    'before' => $old_account_setting[$key],
                    'after' => $new_account_setting[$key]
                ]
            ]);
        }
    } // end foreach

    // 2-4.把$record寫入root_memeberlog內
    if( count($record) > 0 ){
        $log = json_encode([
            'status' => ( ($update_account_setting_result) ? 'success' : 'fail' ),
            'record' => $record
        ]);
        // 修改寫入root_memberlog的message
        $msg_log = $tr['Modify administrator information']; // 修改管理員資料
        memberlogtodb(
            $_SESSION['agent']->account,
            'admin_management_edit',
            'info',
            $msg_log,
            $account,
            $log
        );
    }

    // === 更新帳號對function的權限 ===
    $update_account_unpublic_function_premission_result = true;
    if( isset($_POST['function_premission']) && (count($_POST['function_premission']) > 0) ){
        $record = [];
        foreach( $_POST['function_premission'] as $key=>$val ){
            // 先查詢是否已經有紀錄
            $unpublic_function_data = query_unpublic_function_data($account, $val['function_name']);
            // echo '<pre>', var_dump( $unpublic_function_data[1] ), '</pre>'; exit();
            // 有紀錄->執行更新
            if( $unpublic_function_data[0] == 1 ){
                // 更新失敗時，把更新狀態改成false
                if( !update_unpublic_function_data($account, $val['function_name'], $val['status']) ){
                    $update_account_unpublic_function_premission_result = false;
                }
                // 更新成功時，寫入紀錄
                else{
                    array_push($record, [
                        $val['function_name'] => [
                            'before' => $unpublic_function_data[1]->premission_status,
                            'after' => $val['status']
                        ]
                    ]);
                }
            }
            // 沒紀錄->執行新增
            else{
                // 新增失敗時，把更新狀態改成false
                if( !insert_unpublic_function_data($account, $val['function_name'], $val['status']) ){
                    $update_account_unpublic_function_premission_result = false;
                }
                // 新增成功時，寫入紀錄
                else{
                    array_push($record, [
                        $val['function_name'] => [
                            'before' => '',
                            'after' => $val['status']
                        ]
                    ]);
                }
            }
        } // end foreach

        // 把$record寫入root_memeberlog內
        if( count($record) > 0 ){
            $log = json_encode([
                'status' => 'success',
                'record' => $record
            ]);

            // 修改寫入root_memberlog的message
            $msg_log = '修改管理员资料';
            memberlogtodb(
                $_SESSION['agent']->account,
                'admin_management_edit',
                'info',
                $msg_log,$account,$log
            );
        }
    }
    // ------------------------------------------------------------------------------------------

    // 回傳更新狀態到前台
    if($update_account_data_result && $update_account_setting_result && $update_account_unpublic_function_premission_result){
        echo json_encode(['status'=>'success']);
    }
    else{
        echo json_encode(['status'=>'fail', 'msg'=>$tr['Something wrong happened！Please try again later！']]); // 發生錯誤！請稍後再試！
    }
}
// *** 新增子帳號 ***
else{
    // 建立子帳號(記得加上parent_id)
    $account_data = $_POST['account_data'];
    // echo '<pre>' , var_dump($account_data), '</pre>'; exit();
    $account_data['enrollmentdate'] = 'now()';
    unset($account_data['id']);
    $account_data['parent_id'] = $_SESSION['agent']->id;
    $account = $account_data['account'];
    $generate_admin_account = insert_account_data($account_data); // boolen

    // stop-建立子帳號失敗就停下
    if( !$generate_admin_account ){
        return json_encode([
            'status' => 'fail',
            'msg' => $tr['Generate new account failed'] // 生成新帳戶失敗
        ]);
    }
    // 寫入記錄-建立子帳號
    else{
        $record = [];
        foreach($account_data as $key=>$val){
            array_push($record, [
                $key => [
                    'before' => 'NULL',
                    'after' => $val
                ]
            ]);
        } // end foreach

        $log = json_encode([
            'status' => ( ($generate_admin_account) ? 'success' : 'fail' ),
            'record' => $record
        ]);

        memberlogtodb(
            $_SESSION['agent']->account,
            'admin_management_edit',
            'info',
            '子帐号修改',
            $account,
            $log
        );
    }


    // 建立子帳號設定值
    $account_setting = $_POST['account_setting']['content'];
    $account_setting['account'] = $account_data['account'];
    $result_insert_account_setting = insert_account_setting($account_setting); // boolen

    // stop-建立子帳號設定值失敗就停下
    if(!$result_insert_account_setting){
        return json_encode([
            'status' => 'fail',
            'msg' => $tr['Generate new account setting failed'] // 生成新帳戶設置失敗
        ]);
    }
    // 寫入記錄
    else{
        unset($account_setting['account']);
        $record = [];
        foreach($account_setting as $key=>$val){
            array_push($record, [
                $key => [
                    'before' => 'NULL',
                    'after' => $val
                ]
            ]);
        } // end foreach

        $log = json_encode([
            'status' => ( ($result_insert_account_setting) ? 'success' : 'fail' ),
            'record' => $record
        ]);
        memberlogtodb(
            $_SESSION['agent']->account,
            'admin_management_edit',
            'info',
            '子帐号修改',
            $account,
            $log
        );
    }


    // 寫入子帳號權限
    $function_premission = ( isset($_POST['function_premission']) ? $_POST['function_premission'] : [] );
    if( count($function_premission) > 0 ){
        $record = [];
        foreach($function_premission as $val){
            // 寫入root_member_account_unpublic_function_access_premission
            $generate_result = insert_unpublic_function_data($account, $val['function_name'], $val['status']); // return boolen

            // 把$record寫入root_memeberlog內
            memberlogtodb(
                $_SESSION['agent']->account,
                'admin_management_edit',
                'info',
                '子帐号修改',
                $account,
                json_encode([
                    'status' => ( ($generate_result) ? 'success' : 'fail' ),
                    'record' => [
                        $val['function_name'] => [
                            'before' => 'NULL',
                            'after' => $val['status']
                        ]
                    ]
                ])
            );
        } // end foreach
    }
    echo json_encode(['status' => 'success', 'msg' => $tr['Generate new account Success'] ]); // 生成新帳戶成功
}
?>