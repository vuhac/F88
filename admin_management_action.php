<?php
// ----------------------------------------------------------------------------
// Features:    後台--會員操作記錄
// File Name:    admin_management_action.php
// Author:        Damocles
// Related:
// DB table:
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

// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();

// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();

// 子帳號管理--判斷客服帳號是否有被下放客服長的管理功能，如果輸入帳號是維運或是站長的帳號則會回傳false (return boolen) (已測試)
function query_customer_service_management_authority( $account, $su ){
    // 判斷帳號所屬角色是否為R，並且該帳號不在維運、站長的名單內。
    $query_sql = <<<SQL
        SELECT *
        FROM root_member
        WHERE (therole = 'R') AND
              (account = '{$account}')
        LIMIT 1;
    SQL;
    $result_query = runSQLall( $query_sql, 0 );
    if( $result_query[0] == 1 ){
        if( isset($su['ops']) && (count($su['ops']) > 0) ){ // 判斷維運名單是否存在
            if( in_array($account, $su['ops']) ){ // 判斷帳號是否是維運
                return false;
            }
        }

        if( isset($su['master']) && (count($su['master']) > 0) ){ // 判斷站長名單是否存在
            if( in_array($account, $su['master']) ){ // 判斷帳號是否是站長
                return false;
            }
        }

        // 判斷該帳號是否有被下放客服長的管理權限
        $query_sql = <<<SQL
            SELECT *
            FROM root_member_account_unpublic_function_access_premission
            WHERE (account = '{$account}') AND
                  (function_name = 'customer_service_management_authority') AND
                  (premission_status = 't')
            LIMIT 1;
        SQL;
        $result_query = runSQLall( $query_sql, 0 );
        return ( ($result_query[0] == 1) ? true : false );
    }
    else{ // 該帳號角色不是R
        return false;
    }
} // end query_customer_service_management_authority

// 子帳號管理--判斷所輸入的帳號，可以管理哪些帳號的資料，功能相依於query_customer_service_management_authority(回傳帳號array)
$accounting_accounts = []; // 會計帳號
if( isset($gcash_cashier_account) && !empty($gcash_cashier_account) ){
    array_push($accounting_accounts, $gcash_cashier_account);
}
if( isset($gtoken_cashier_account) && !empty($gtoken_cashier_account) ){
    array_push($accounting_accounts, $gtoken_cashier_account);
}
function query_allowed_management_accounts( $login_account, $su, $accounting_accounts ){
    $result = [];
    // 會計帳號，不得被修改的帳號(By Dacmoles In 2019/12/04)
    array_push($accounting_accounts, 'root');

    // 先判斷所登入的帳號是否角色為R
    $query_sql = <<<SQL
        SELECT *
        FROM root_member
        WHERE (account = '{$login_account}') AND
              (therole = 'R')
        LIMIT 1;
    SQL;
    $result_query = runSQLall($query_sql, 0);

    if( $result_query[0] == 1 ){
        if( in_array($login_account, $su['ops']) ){ // 判斷角色是維運，回傳不包含自己的所有角色R帳號
            $query_sql = <<<SQL
                SELECT *
                FROM root_member
                WHERE (therole = 'R') AND
                      (account != '{$login_account}')
            SQL;

            // 剃除掉特別帳號
            if( count($accounting_accounts) > 0 ){
                foreach($accounting_accounts as $val){
                    $query_sql .= <<<SQL
                        AND (account != '{$val}')
                    SQL;
                } // end foreach
            }

            $result_query = runSQLall($query_sql, 0);

            if( $result_query[0] > 0 ){
                unset( $result_query[0] );
                foreach( $result_query as $val ){
                    array_push( $result, $val->account );
                } // end foreach
            }
        }
        else if( in_array($login_account, $su['master']) ){ // 判斷角色是站長，回傳不包含自己與維運的所有角色R帳號
            $query_sql = <<<SQL
                SELECT *
                FROM root_member
                WHERE (therole = 'R') AND
                      (account != '{$login_account}')
            SQL;

            // 剃除掉特別帳號
            if( count($accounting_accounts) > 0 ){
                foreach($accounting_accounts as $val){
                    $query_sql .= <<<SQL
                        AND (account != '{$val}')
                    SQL;
                } // end foreach
            }

            // 剃除掉維運帳號
            if( count($su['ops']) > 0 ){
                foreach( $su['ops'] as $val ){
                    $query_sql .= <<<SQL
                        AND (account != '{$val}')
                    SQL;
                } // end foreach
            }

            $result_query = runSQLall($query_sql, 0);
            if( $result_query[0] > 0 ){
                unset( $result_query[0] );
                foreach( $result_query as $val ){
                    array_push( $result, $val->account );
                } // end foreach
            }
        }
        else{
            // 判斷角色是客服長
            if( $result_query[1]->parent_id == '1' ){
                // 找出所屬下線的客服
                $query_sql = <<<SQL
                    SELECT account
                    FROM root_member
                    WHERE (parent_id = '{$result_query[1]->id}') AND
                          (account != '{$login_account}') AND
                          (therole = 'R')
                SQL;

                // 剃除掉特別帳號
                if( count($accounting_accounts) > 0 ){
                    foreach($accounting_accounts as $val){
                        $query_sql .= <<<SQL
                            AND (account != '{$val}')
                        SQL;
                    } // end foreach
                }

                // 剃除掉維運帳號
                if( count($su['ops']) > 0 ){
                    foreach( $su['ops'] as $val ){
                        $query_sql .= <<<SQL
                            AND (account != '{$val}')
                        SQL;
                    } // end foreach
                }

                // 剃除掉站長帳號
                if( count($su['master']) > 0 ){
                    foreach( $su['master'] as $val ){
                        $query_sql .= <<<SQL
                            AND (account != '{$val}')
                        SQL;
                    } // end foreach
                }

                $result_query =runSQLall( $query_sql, 0 );

                if( $result_query[0] > 0 ){
                    unset( $result_query[0] );
                    foreach( $result_query as $val ){
                        array_push( $result, $val->account );
                    } // end foreach
                }
            }
            // 判斷角色是客服，判斷是否有被下放客服長的管理權限，有的話回傳該客服長旗下所屬客服的帳號，沒有的話則留空
            else if( query_customer_service_management_authority( $login_account, $su ) ){ // 該帳號有被下放客服長的管理權限
                // 找出該帳號所屬上線客服長的編號，以客服長的編號去找到所屬下線客服的帳號，並且不包含該被賦予權限的客服帳號
                $query_sql = <<<SQL
                    SELECT account
                    FROM root_member
                    WHERE (parent_id = (
                        SELECT parent_id
                        FROM root_member
                        WHERE (therole = 'R') AND
                            (account = '{$login_account}')
                        LIMIT 1
                    )) AND
                    (account != '{$login_account}')
                SQL;

                // 剃除掉特別帳號
                if( count($accounting_accounts) > 0 ){
                    foreach($accounting_accounts as $val){
                        $query_sql .= <<<SQL
                            AND (account != '{$val}')
                        SQL;
                    } // end foreach
                }

                // 剃除掉維運帳號
                if( count($su['ops']) > 0 ){
                    foreach( $su['ops'] as $val ){
                        $query_sql .= <<<SQL
                            AND (account != '{$val}')
                        SQL;
                    } // end foreach
                }

                // 剃除掉站長帳號
                if( count($su['master']) > 0 ){
                    foreach( $su['master'] as $val ){
                        $query_sql .= <<<SQL
                            AND (account != '{$val}')
                        SQL;
                    } // end foreach
                }

                $result_query = runSQLall($query_sql, 0);
                if( $result_query[0] > 0 ){
                    unset( $result_query[0] );
                    foreach( $result_query as $val ){
                        array_push( $result, $val->account );
                    } // end foreach
                }
            }
            else{ // 該帳號沒有被下放客服長的管理權限
                // 這邊不做任何事
            }
        }
    }
    return $result;
} // end query_allowed_management_accounts


// 判斷是否有權限可以請求資料
if( update_premission( $_SESSION["agent"]->account, $su ) ){
    // 查詢出該帳號可以管理的帳號名單。
    $allowed_management_accounts = query_allowed_management_accounts( $_SESSION["agent"]->account, $su, $accounting_accounts );
    if( count($allowed_management_accounts) > 0 ){
        // 組合sql查詢式，用以查詢名單內的帳號。
        $query_allowed_sql = '(';
        foreach( $allowed_management_accounts as $key=>$val ){
            if( $key == 0 ){
                $query_allowed_sql .= "'".$val."'";
            }
            else{
                $query_allowed_sql .= ", '".$val."'";
            }
        } // end foreach
        $query_allowed_sql .= ')';

        $data_cloumn = [
            'id',
            'account',
            'status',
            'changetime',
            'notes'
        ]; // 用來排序回傳的欄位資料

        // 查詢所有(可管理的)帳戶資料
        $query_account_data = <<<SQL
            SELECT id,
                   account,
                   status,
                   changetime,
                   notes
            FROM root_member
            WHERE (therole = 'R') AND
                  (account IN {$query_allowed_sql})
        SQL;

        // 有傳來篩選條件
        if( isset($_POST['search']) && !empty($_POST['search']['value']) ){
            $post_search_data = json_decode( $_POST['search']['value'] );

            // 所查詢的帳號狀態、帳號跟前次查詢不一樣時，要重置查詢資料顯示起始點。
            if(
                ( isset($_SESSION['account_management']['search_detail']->account_status) && ($_SESSION['account_management']['search_detail']->account_status != $post_search_data->account_status) ) ||
                ( isset($_SESSION['account_management']['search_detail']->account) && ($_SESSION['account_management']['search_detail']->account != $post_search_data->account) )
              ){
                $_POST['start'] = 0;
            }
            $_SESSION['account_management']['search_detail'] = $post_search_data;
        }

        // 判斷登入帳號角色，維運可以看到站長、客服(維運只有一個)，
        // 站長可以看到客服長、客服(站長只有一個)，
        // 客服長可以看到所屬下階客服，
        // 客服除非有被客服長下放權限，可以看到客服長所屬下階客服，但是不包含自己，如果沒有被下放權限則什麼都看不到。

        // 列出所有的帳號資料，並且使用它總計數量，用以回傳前端DataTable的頁數
        $all_account_count = runSQLall($query_account_data, 0);

        // 帳戶搜尋
        if( isset($post_search_data->account) && !empty($post_search_data->account) ){
            $query_account_data .= <<<SQL
                AND (account LIKE '%{$post_search_data->account}%')
            SQL;
        }

        // 帳戶狀態搜尋
        if( isset($post_search_data->account_status) && ($post_search_data->account_status != 3) ){
            $query_account_data .= <<<SQL
                AND (status='{$post_search_data->account_status}')
            SQL;
        }

        // 列出所有符合搜尋條件的帳號資料，並且使用它總計數量，用以回傳前端DataTable的頁數
        $recordsFiltered = runSQLall($query_account_data, 0);

        // 取得該頁數，應該顯示的資料，用以回傳前端DataTable的資料顯示
        $query_account_data .= <<<SQL
            ORDER BY {$data_cloumn[ $_POST["order"][0]["column"] ]} {$_POST["order"][0]["dir"]}
            LIMIT {$_POST['length']} OFFSET {$_POST['start']};
        SQL;
        $result_query_account_data = runSQLall($query_account_data, 0);
        unset($result_query_account_data[0]); // 所有帳號資料
        $result_data = [];

        // 遍歷上述所取得的資料，修正顯示資料內容，並且判斷該資料內是否有super群組內的帳號，該帳號不可被編輯。
        foreach( $result_query_account_data as $key=>$val ){
            $data = [
                'id'=>$val->id,
                'account'=>$val->account,
                'status'=>( ($val->status=="1") ? $tr['y'] : ( ($val->status=="0") ? $tr['disabled'] : ( ($val->status=="2") ? $tr['freeze'] : ( ($val->status=="3") ? $tr['n'] : 'unknow' ) ) ) ),
                'changetime'=>date("Y/m/d h:i:s", strtotime($val->changetime)),
                'notes'=>$val->notes
            ];

            if( !in_array($val->account, $su['superuser']) ){
                $data['opt'] = <<<HTML
                    <button onclick="location.href='admin_management_edit.php?id={$result_query_account_data[$key]->id}'" class="btn btn-primary">
                        <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
                    </button>
                HTML;
            }
            else{
                $data['opt'] = '';
            }
            array_push($result_data, $data);
        }

        $result = [
            'draw' => (isset($_POST['draw']) ? $_POST['draw'] : 1),
            'recordsTotal' => $all_account_count[0],
            'recordsFiltered' => $recordsFiltered,
            'data' => $result_data
        ];
    }
    else{
        $result = [
            'draw' => (isset($_POST['draw']) ? $_POST['draw'] : 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ];
    }
    echo json_encode($result, JSON_PRETTY_PRINT);
}
else{
    $result = [
        'draw' => (isset($_POST['draw']) ? $_POST['draw'] : 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ];
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>