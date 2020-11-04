<?php
// ----------------------------------------------------------------------------
// Features: 後台 -- 首儲統計報表功能
// File Name: first_store_report_lib.php
// Author: Damocles
// Related: first_store_report_*.php
// Log:
// ----------------------------------------------------------------------------

    // 查詢指定帳號的資料
    function queryAccountDatas($account)
    {
        $stmt = <<<SQL
            SELECT "id",
                   "account",
                   "therole",
                   "status",
                   "parent_id",
                   "first_deposite_date"
            FROM "root_member"
            WHERE ("account" = '{$account}')
            LIMIT 1;
        SQL;
        $result = runSQLall($stmt);
        return ( ($result[0] == 1) ? $result[1] : null );
    }

    // 取得代理商資料(沒資料回傳null)
    function getAgentData($account=null, $start=null, $length=null)
    {
        $stmt = <<<SQL
            SELECT "id",
                   "account",
                   "therole",
                   "status",
                   "parent_id",
                   "first_deposite_date"
            FROM "root_member"
            WHERE ("therole" = 'A') AND ("status" = '1')
        SQL;

        // 是否有指定帳號
        if ($account != null) {
            $account = (string)$account;
            $stmt .= <<<SQL
                AND ("account" = '{$account}')
            SQL;
        }

        // 設定資料長度
        if ( ($start != null) && ($length != null) ) {
            $stmt .= <<<SQL
                LIMIT {$length} OFFSET {$start}
            SQL;
        }

        $result = runSQLall($stmt);
        if ($result[0] == 0) {
            return null;
        } else {
            unset($result[0]);
            return $result;
        }
    }

    // 取得所有有效帳號(只有therole是A、M)
    function getMemberDatas($account=null)
    {
        $stmt = <<<SQL
            SELECT "main"."id",
                   "main"."account",
                   "main"."therole",
                   "parent"."id" AS "parent_id",
                   "parent"."account" AS "parent_account"
            FROM "root_member" AS "main"
            LEFT JOIN (
                SELECT "id",
                       "account"
                FROM "root_member"
                WHERE ("therole" IN ('A', 'M'))
            ) AS "parent"
                ON ("parent"."id" = "main"."parent_id")
            WHERE ("main"."status" = '1') AND
                ("main"."therole" IN ('A', 'M'))
        SQL;

        if ( !is_null($account) ) {
            $stmt .= <<<SQL
                AND ("main"."account" = '{$account}')
                LIMIT 1
            SQL;

            $result = runSQLall($stmt);
            return ( ($result[0] == 1) ? $result[1] : null );
        } else {
            $stmt .= <<<SQL
                ORDER BY "main"."first_deposite_date" DESC
            SQL;
            $result = runSQLall($stmt);
            unset($result[0]);
            return $result;
        }
    }

    // 找出指定會員的一級代理
    function queryFirstAgent($account)
    {
        $parent_id = ''; // 暫存該輪的上級id
        $parent_account = ''; // 暫存該輪的上級帳號
        $root_id = ''; // 第一級的id
        $root_account = ''; // 第一級的帳號

        // 取出所有會員的資料
        $order_account_datas = getMemberDatas($account);
        $all_account_datas = getMemberDatas();

        if ($order_account_datas->parent_id == 1) { // 判斷指定帳號的parent_id是否為一級代理(parent_id = 1)
            $root_account = null;
            $root_account_id = null;
        } else if ( is_null($order_account_datas) ) { // 查無此帳號
            $root_account = '';
            $root_account_id = '';
        } else { // 取得上一代代理商的id、account
            $parent_id = $order_account_datas->parent_id;
            $parent_account = $order_account_datas->parent_account;

            // 資料全部搬回後台處理
            do {
                foreach ($all_account_datas as $key=>$val) { // 遍歷所有會員資料
                    if ($val->id == $parent_id) { // 比對會員編號跟目前記錄的parent_id是否符合
                        if ( is_null($val->parent_id) ) { // 如果比對到的帳號是最上層的會員，則紀錄下編號跟帳號
                            $root_id = $val->id;
                            $root_account = $val->account;
                        }
                        $parent_id = $val->parent_id; // 更新parent_id
                        break;
                    }
                }
            } while ( !is_null($parent_id) );
        }
        return [
            'root_id' => $root_id,
            'root_account' => $root_account
        ];
    }

    // timestamptz轉換成當前時區的UNIX時間戳
    function exchangeUnixTimestamp($timestamptz, $current_zone=null)
    {
        // 沒設定UTC時區碼，給予當前的時區碼
        if ( is_null($current_zone) ) {
            $current_zone = (date("Z") / 3600);
        }

        // 切割 timestamptz，把+-UTC時區取出
        $unix_timestamp;
        $explode_add_sign = explode("+", $timestamptz);
        $explode_reduce_sign = explode("-", $timestamptz);
        if (count($explode_add_sign) == 2) {
            $zone_differ = $current_zone - $explode_add_sign[1];
            if ($zone_differ > 0) {
                $unix_timestamp = strtotime($explode_add_sign[0].'+'.$zone_differ.' hour');
            } else {
                $unix_timestamp = strtotime($explode_add_sign[0].'-'.$zone_differ.' hour');
            }
        } else if (count($explode_reduce_sign) == 2) {
            $zone_differ = $current_zone - (-1 * $explode_add_sign[1]);
            if ($zone_differ > 0) {
                $unix_timestamp = strtotime($explode_add_sign[0].'+'.$zone_differ.' hour');
            } else {
                $unix_timestamp = strtotime($explode_add_sign[0].'-'.$zone_differ.' hour');
            }
        } else {
            return null;
        }
        return $unix_timestamp;
    }

    // 製造假的儲值資料(僅限開發測試DB使用)
    /* function generateFakeDepositDatas()
    {
        // 先查詢沒有儲值紀錄的會員首儲資料
        $sql = <<<SQL
            SELECT "member"."id" AS "id",
                   "member"."account" AS "account",
                   "member"."first_deposite_date" AS "first_deposite_date",
                   "token"."deposit" AS "amount"
            FROM "root_member" AS "member"
            LEFT JOIN "root_member_gtokenpassbook" AS "token"
                ON ("token"."transaction_time" = "member"."first_deposite_date") AND ("token"."member_id" = "member"."id")
            WHERE "member"."first_deposite_date" IS NOT NULL
            ORDER BY "member"."id" DESC
        SQL;
        $no_deposit_datas = runSQLall($sql);
        unset($no_deposit_datas[0]);

        // 判斷金額欄位裡面是否NULL，是就插入一筆假的存款紀錄
        foreach ($no_deposit_datas as $val) {
            if ( is_null($val->amount) ) {
                $insert_fake_deposit_record = <<<SQL
                    INSERT INTO "root_member_gtokenpassbook" (
                        transaction_time,
                        deposit,
                        withdrawal,
                        system_note,
                        member_id,
                        currency,
                        summary,
                        source_transferaccount,
                        destination_transferaccount,
                        balance,
                        realcash,
                        transaction_category
                    ) VALUES (
                        '$val->first_deposite_date',
                        '1.00',
                        '0.00',
                        '這是假資料',
                        '$val->id',
                        'CNY',
                        '现金存款',
                        'gcashcashier',
                        '$val->account',
                        '1.00',
                        '1',
                        'cashdeposit'
                    )
                SQL;
                echo (string)runSQL($insert_fake_deposit_record).'<br>';
            }
        }
    } */

    // 查詢指定帳號的直屬下線
    function queryLowerChild($account)
    {
        $lower_child = []; // 裝直屬下線
        $result = queryAccountDatas($account); // 查詢指定帳號的資料

        if ($result == null) { // 沒有找到指定帳號
            return [];
        } else {
            $order_account_data = $result;
            $all_account_datas = getMemberDatas(); // 所有帳號的資料
            foreach($all_account_datas as $key=>$val) {
                if ($order_account_data->id == $val->parent_id) {
                    array_push($lower_child, $all_account_datas[$key]);
                }
            }
            return $lower_child;
        }
    }

    // 以父層帳號的id，找下一階代理商的所有帳號
    function queryLowerAgentChildData($parent_id)
    {
        $stmt = <<<SQL
            SELECT "main"."id",
                   "main"."account",
                   "main"."parent_id",
                   "main"."therole",
                   "main"."enrollmentdate",
                   "main"."first_deposite_date",
                   "passbook"."deposit"
            FROM "root_member" AS "main"
            LEFT JOIN "root_member_gtokenpassbook" AS "passbook"
                ON ("passbook"."transaction_time" = "main"."first_deposite_date") AND ("passbook"."source_transferaccount" =  "main"."account")
            WHERE ("status" = '1')
                AND ("therole" = 'A')
                AND ("parent_id" = '{$parent_id}')
        SQL;
        $result = runSQLall($stmt);
        if ($result[0] == 0) {
            return null;
        } else {
            unset($result[0]);
            return $result;
        }
    }

    // 以父層帳號的id、時區、首儲時間，找下一階會員的所有帳號
    function queryLowerMemberChildData($timezone, $parent_id, $start_datetime=null, $end_datetime=null)
    {
        // 以時間去篩選會員
        $stmt = <<<SQL
            SELECT "main"."id",
                   "main"."account",
                   "main"."parent_id",
                   "main"."therole",
                   "main"."enrollmentdate",
                   "main"."first_deposite_date",
                   "passbook"."deposit"
            FROM "root_member" AS "main"
            LEFT JOIN "root_member_gtokenpassbook" AS "passbook"
                ON ("passbook"."transaction_time" = "main"."first_deposite_date")
                AND ("passbook"."source_transferaccount" =  "main"."account")
            WHERE ("status" = '1')
                AND ("therole" = 'M')
                AND ("parent_id" = '{$parent_id}')
                AND ("passbook"."deposit" > 0)
        SQL;

        // 依照"搜尋時間條件"加入查詢式
        if ( ($start_datetime != null) || ($end_datetime != null) ) {
            if ( ($start_datetime != null) && ($end_datetime != null) ) { // 有起訖2個時間
                // 格式化時間
                $start_datetime = date("Y-m-d H:i:s", strtotime($start_datetime));
                $end_datetime = date("Y-m-d H:i:s", strtotime($end_datetime));

                // 組合時間的查詢式
                $stmt .= <<<SQL
                    /* 沒有時區判斷 */
                    /* AND ("first_deposite_date" BETWEEN '{$start_datetime}' AND '{$end_datetime}') */
                    /* 時區判斷 */
                    AND (timezone('{$timezone}', "first_deposite_date") BETWEEN '{$start_datetime}' AND '{$end_datetime}')
                SQL;
            } else if ($start_datetime != null) { // 只有開始時間
                // 格式化時間
                $start_datetime = date("Y-m-d H:i:s", strtotime($start_datetime));

                // 組合時間的查詢式
                $stmt = <<<SQL
                    /* 沒有時區判斷 */
                    /* AND ("first_deposite_date" >= '{$start_datetime}') */
                    /* 時區判斷 */
                    AND (timezone('{$timezone}', "first_deposite_date") >= '{$start_datetime}')
                SQL;
            } else if ($end_datetime != null) { // 只有結束時間
                // 格式化時間
                $end_datetime = date("Y-m-d H:i:s", strtotime($end_datetime));

                // 組合時間的查詢式
                $stmt = <<<SQL
                    /* 沒有時區判斷 */
                    /* AND ("first_deposite_date" <= '{$end_datetime}') */
                    /* 時區判斷 */
                    AND (timezone('{$timezone}', "first_deposite_date") <= '{$end_datetime}')
                SQL;
            }
        }

        // 執行搜尋
        $result = runSQLall($stmt);
        if ($result[0] == 0) {
            return null;
        } else {
            unset($result[0]);
            return $result;
        }
    }

    // 組合以父層帳號的id，所搜尋的下一階代理商、會員的帳號
    function queryLowerChildData($timezone, $parent_id, $start_datetime=null, $end_datetime=null)
    {
        $lower_child = [];
        $agents = queryLowerAgentChildData($parent_id);
        $members = queryLowerMemberChildData($timezone, $parent_id, $start_datetime, $end_datetime);

        if ($agents != null) {
            foreach ($agents as $key_agents=>$val_agents) {
                array_push($lower_child, (array)$agents[$key_agents]);
            }
        }

        if ($members != null) {
            foreach ($members as $key_members=>$val_members) {
                array_push($lower_child, (array)$members[$key_members]);
            }
        }

        return $lower_child;
    }

    // 將指定帳號的下線寫入陣列
    function insertLowerChildrenData($timezone, $parent_id, $start_datetime=null, $end_datetime=null)
    {
        $lower_child = queryLowerChildData($timezone, $parent_id, $start_datetime, $end_datetime); // 找到指定帳號的下線
        if (count($lower_child) > 0) {
            foreach ($lower_child as $val) {
                array_push($GLOBALS['lower_children'], $val);

                if ($val['therole'] == 'A') {
                    insertLowerChildrenData($timezone, $val['id'], $start_datetime, $end_datetime);
                }
            }
        }
    }

    // 以分類別產生資料
    function generateData($search_details, $system_attr)
    {
        // 傳入參數範本
        /*
            $search_details = [
                'type',
                'search_store_account',
                'search_agent',
                'store_min_value',
                'store_max_value',
                'start_datetime',
                'end_datedatetime',
                'start',
                'length'
            ];

            $system_attr = [
                'default_timezone' => (string)$config['default_timezone'], // $timezone
                'default_locate' => (string)$config['default_locate'], // $default_locate
                'currency_sign' => (string)$config['currency_sign'], // $currency_sign
                'member_deposit_currency' => (string)$protalsetting['member_deposit_currency'] // $currency
            ];
        */

        // 拆解與宣告系統參數
        $timezone = $system_attr['default_timezone'];
        $default_locate = $system_attr['default_locate'];
        $currency_sign = $system_attr['currency_sign'];
        $currency = $system_attr['member_deposit_currency'];

        if ( isset($search_details['type']) && !empty($search_details['type']) && ($search_details['type'] != null) ) {
            if ($search_details['type'] == 'member') {
                $stmt = <<<SQL
                    SELECT "main"."id",
                        "main"."account",
                        "main"."therole",
                        "parent"."id" AS "parent_id",
                        "parent"."account" AS "parent_account",
                        timezone('{$timezone}', "main"."first_deposite_date") AS "first_deposite_date",
                        timezone('{$timezone}', "main"."enrollmentdate") AS "enrollmentdate",
                        "passbook"."deposit" /* 幣別 */
                    FROM "root_member" AS "main"
                    LEFT JOIN (
                        SELECT "id",
                            "account"
                        FROM "root_member"
                        WHERE ("therole" IN ('A', 'M'))
                    ) AS "parent"
                        ON ("parent"."id" = "main"."parent_id")
                SQL;

                // 依幣別決定要LEFT JOIN哪張表 (限定gtoken、gcash這兩個選項，在first_store_report_action.php時已經過濾好)
                // 注意：passbook的交易時間必須跟root_member的首儲時間完全相同(這點要在前台-線上支付與公司入款確認!!)
                if ($currency == 'gtoken') {
                    $stmt .= <<<SQL
                        LEFT JOIN "root_member_gtokenpassbook" AS "passbook"
                            ON ("passbook"."transaction_time" = "main"."first_deposite_date") AND ("passbook"."source_transferaccount" = "main"."account")
                    SQL;
                } else if ($currency == 'gcash'){
                    $stmt .= <<<SQL
                        LEFT JOIN "root_member_gcashpassbook" AS "passbook"
                            ON ("passbook"."transaction_time" = "main"."first_deposite_date") AND ("passbook"."source_transferaccount" = "main"."account")
                    SQL;
                }

                $stmt .= <<<SQL
                    WHERE ("main"."status" = '1')
                        AND ("main"."therole" IN ('A', 'M'))
                        AND ("passbook"."deposit" IS NOT NULL) /* 未有首儲紀錄不列入計算 */
                SQL;

                // 如果有設定欲搜尋的首儲帳號
                if ( !is_null($search_details['search_store_account']) && !empty($search_details['search_store_account']) ) {
                    $stmt .= <<<SQL
                        AND ("main"."account" LIKE '%{$search_details["search_store_account"]}%')
                    SQL;
                }

                // 起訖日期(必填)
                $start_datetime = date("Y-m-d H:i:s", strtotime($search_details['start_datetime']));
                $end_datetime = date("Y-m-d H:i:s", strtotime($search_details['end_datedatetime']));
                $stmt .= <<<SQL
                    AND (timezone('{$timezone}', "main"."first_deposite_date") BETWEEN '{$start_datetime}' AND '{$end_datetime}')
                SQL;

                // 如果有設定欲搜尋的最小金額或最大金額
                if ( !is_null($search_details['store_min_value']) || !is_null($search_details['store_max_value']) ) {
                    if ( !is_null($search_details['store_min_value']) && !is_null($search_details['store_max_value']) ) { // 最小金額或最大金額都有設定
                        $stmt .= <<<SQL
                            AND ( ({$search_details['store_min_value']} <= "passbook"."deposit") AND ("passbook"."deposit" <= {$search_details['store_max_value']}) )
                        SQL;
                    } else if ( !is_null($search_details['store_min_value']) ) { // 只有設定最小金額
                        $stmt .= <<<SQL
                            AND ( {$search_details['store_min_value']} <= "passbook"."deposit" )
                        SQL;
                    } else { // 只有設定最大金額
                        $stmt .= <<<SQL
                            AND ( "passbook"."deposit" <= {$search_details['store_max_value']} )
                        SQL;
                    }
                }

                $stmt .= <<<SQL
                    ORDER BY "main"."first_deposite_date" DESC
                SQL;

                // 取得符合搜尋條件的資料
                $data = runSQLall($stmt);

                unset($data[0]);
                if (count($data) > 0) {
                    foreach ($data as $key=>$val) {
                        // 幫符合的資料加上第一代上線
                        $root_data = queryFirstAgent($val->account);
                        $data[$key]->root_id = $root_data['root_id'];
                        $data[$key]->root_account = $root_data['root_account'];

                        // 如果有設定欲搜尋的一級代理 或 直屬代理
                        if ( !is_null($search_details['search_agent']) ) {
                            if ( ($val->root_account != $search_details['search_agent']) && ($val->parent_account != $search_details['search_agent']) ) {
                                unset($data[$key]);
                            }
                        }
                    }
                }

                // 整理成DataTable格式
                $start = (int)$search_details['start'];
                $length = (int)$search_details['length'];
                $round = 0;
                $deposit_count = 0;
                $output_data = [];
                $filter_data_count = count($data);

                if ($filter_data_count > 0) {
                    foreach ($data as $val) {
                        $deposit_count += (float)$val->deposit; // 累計首儲金額
                        $round++;
                        if ( ($start < $round) && ($round <= ($start+$length)) ) {
                            array_push($output_data, [
                                'account' => $val->account,
                                'therole' => $val->therole,
                                'root_account' => $val->root_account,
                                'parent_account' => $val->parent_account,
                                'first_deposite_date' => date("Y-m-d H:i", strtotime($val->first_deposite_date)),
                                'enrollmentdate' => date("Y-m-d H:i", strtotime($val->enrollmentdate)),
                                'deposit' => transCurrencySign($val->deposit, $default_locate, $currency_sign)
                            ]);
                        }
                    }
                }

                $result = [
                    'draw' => (isset($_GET['draw']) ? $_GET['draw'] : 1),
                    'recordsTotal' => $filter_data_count,
                    'recordsFiltered' => $filter_data_count,
                    'data' => $output_data,
                    'deposit_count' => transCurrencySign($deposit_count, $default_locate, $currency_sign)
                ];
                echo json_encode($result, JSON_PRETTY_PRINT);
            }
        }
    }


    // === 代理商統計方式專用函式 ===
    // 查詢指定id的代理商統計資料
    function queryAgentFirstStoreReport($id)
    {
        $stmt = <<<SQL
            SELECT *
            FROM "cmd_agent_first_store"
            WHERE ("id" = '{$id}')
            LIMIT 1;
        SQL;
        $reslut = runSQLall($stmt);
        return ( ($reslut[0] == 1) ? $reslut[1] : null );
    }

    // 插入一筆新的代理商統計資料
    function insertAgentFirstStoreReport($data, $return_insert_id=false)
    {
        $allow_columns = ["search_detail", "search_result", "status"];
        $stmt = <<<SQL
            INSERT INTO "cmd_agent_first_store" (
        SQL;

        // 判斷傳來的欄位是否有設定，以組合查詢式的插入欄位
        $is_first = true;
        foreach ($allow_columns as $val) {
            if ( isset($data[$val]) ) {
                if ($is_first) {
                    $is_first = false;
                    $stmt .= <<<SQL
                        "{$val}"
                    SQL;
                } else {
                    $stmt .= <<<SQL
                        , "{$val}"
                    SQL;
                }
            }
        }
        $stmt .= <<<SQL
            ) VALUES (
        SQL;

        // 組合查詢式的插入值
        $is_first = true;
        foreach ($allow_columns as $val) {
            if ( isset($data[$val]) ) {
                if ($is_first) {
                    $is_first = false;
                    $stmt .= <<<SQL
                        '{$data[$val]}'
                    SQL;
                } else {
                    $stmt .= <<<SQL
                        , '{$data[$val]}'
                    SQL;
                }
            }
        }

        // 組合查詢式結尾與判斷是否要加入回傳id的查詢式
        if ($return_insert_id) {
            $stmt .= <<<SQL
                ) RETURNING "id";
            SQL;
        } else {
            $stmt .= <<<SQL
                );
            SQL;
        }

        $result = runSQLall($stmt);
        if ($result[0] == 1) {
            if ($return_insert_id) { // 回傳插入資料的id
                return $result[1]->id;
            } else { // 回傳插入資料的狀態
                return true;
            }
        } else {
            return false;
        }
    }

    // 更新指定id的代理商統計資料
    function updateAgentFirstStoreReport($id, $data)
    {
        $allow_columns = ["search_detail", "search_result", "status"];
        $stmt = <<<SQL
            UPDATE "cmd_agent_first_store" SET
        SQL;

        // 組合要更新的欄位查詢式
        $is_first = true;
        foreach ($allow_columns as $val) {
            if ( isset($data[$val]) ) {
                if ($is_first) {
                    $is_first = false;
                    $stmt .= <<<SQL
                        "{$val}" = '{$data[$val]}'
                    SQL;
                } else {
                    $stmt .= <<<SQL
                        , "{$val}" = '{$data[$val]}'
                    SQL;
                }
            }
        }

        // 加上限制指定id
        $stmt .= <<<SQL
            WHERE ("id" = '{$id}')
        SQL;

        $result = runSQLall($stmt);
        return ( ($result[0] == 1) ? true : false );
    }


    // === 以下為新的首儲代理統計方式部分 ===

    // 主函式
    function main($currency, $timezone, $search_details=[])
    {
        // 判斷搜尋條件是否都有設定
        $allow_columns = [ // 允許的參數名稱
            'agent',
            'min_store_amount',
            'max_store_amount',
            'start_datetime',
            'end_datetime'
        ];

        if ( count($search_details) > 0 ) {
            foreach ($allow_columns as $val) {
                if ( !isset($search_details[$val]) ) {
                    return '搜尋條件參數未完全設定';
                }
            }
        } else {
            return '搜尋條件參數未設定';
        }

        // 查詢儲值紀錄
        $passbook_record = queryPassbook($currency, $timezone, $search_details);

        // 判斷查詢是否有錯誤訊息產生
        if ( !is_array($passbook_record) ) {
            return $passbook_record;
        }

        // 判斷是否有紀錄
        if($passbook_record[0] > 0) {
            unset($passbook_record[0]);

            // 查詢所有代理商帳號，用於後面做查詢使用
            $agents = [];
            $stmt = <<<SQL
                SELECT "id",
                       "account",
                       "parent_id"
                FROM "root_member"
                WHERE ("therole" = 'A')
                ORDER BY "id" ASC
            SQL;
            $result_agent_data = runSQLall($stmt);
            if ($result_agent_data[0] > 0) {
                // 把查詢的資料重組成陣列型態，索引值是帳號的id，後續做比對搜尋比較快
                unset($result_agent_data[0]);
                foreach ($result_agent_data as $val) {
                    $agents[$val->id] = [
                        'account' => $val->account,
                        'parent_id' => $val->parent_id
                    ];
                }
            } else { // 查詢不到代理商的資料
                return [];
            }

            // 遍歷紀錄，在紀錄中加上所有上級代理商的資料
            foreach ($passbook_record as $key=>$val) {
                $round_agent_id = ''; // 裝載每個迴圈中上級代理商的id
                $passbook_record[$key]->upper_agent = []; // 用來裝載上級代理商的帳號
                if ($val->parent_id != 1) { // 過濾掉上級代理商為root的情況
                    $round_agent_id = $val->parent_id;
                    // $is_fit = ( (!empty($search_details['agent'])) ? false : true ); // 用來過濾是否符合搜尋的代理商帳號條件

                    do {
                        // 把上級代理商的帳號儲存進陣列裡面
                        array_push($passbook_record[$key]->upper_agent, $agents[$round_agent_id]['account']);

                        // 如果符合搜尋的代理商條件
                        // if ($search_details['agent'] == $agents[$round_agent_id]['account']) {
                        //     $is_fit = true;
                        // }

                        // 更新暫存的上級代理商id
                        $round_agent_id = $agents[$round_agent_id]['parent_id'];
                    } while ($round_agent_id != 1);

                    // if (!$is_fit) {
                    //     unset($passbook_record[$key]);
                    // }
                }
            }

            // 合併計算各代理商的直屬首儲人數、首儲金額，代理線首儲人數、首儲金額
            $result = [];
            foreach ($passbook_record as $key_outer=>$val_outer) {
                if (count($val_outer->upper_agent) > 0) {
                    $is_first = true; // 判斷是否為直屬
                    foreach ($val_outer->upper_agent as $val_inner) {
                        if ($is_first) { // 是直屬
                            $is_first = false;
                            if ( empty($result[$val_inner]) ) { // 未設定過代理商統計
                                $result[$val_inner] = [
                                    'under_line_people_count' => 1,
                                    'under_line_amount_total' => $val_outer->amount,
                                    'agent_line_people_count' => 0,
                                    'agent_line_amount_total' => 0
                                ];
                            } else { // 已設定過代理商統計
                                $result[$val_inner]['under_line_people_count'] += 1;
                                $result[$val_inner]['under_line_amount_total'] += $val_outer->amount;
                            }
                        } else { // 是代理線
                            if ( !isset($result[$val_inner]) ) { // 未設定過代理商統計
                                $result[$val_inner] = [
                                    'under_line_people_count' => 0,
                                    'under_line_amount_total' => 0,
                                    'agent_line_people_count' => 1,
                                    'agent_line_amount_total' => $val_outer->amount
                                ];
                            } else { // 已設定過代理商統計
                                $result[$val_inner]['agent_line_people_count'] += 1;
                                $result[$val_inner]['agent_line_amount_total'] += $val_outer->amount;
                            }
                        }
                    }
                }
            }

            // 回傳合併計算的結果
            return $result;
        } else { // 沒有符合條件的儲值紀錄
            return [];
        }
    }

    // 依搜尋條件與系統設定幣別去搜尋符合條件的資料
    function queryPassbook($currency, $timezone, $search_details)
    {
        // 參數範例格式
        /*
            $search_details = [
                'min_store_amount' => '', // 選填
                'max_store_amount' => '', // 選填
                'start_datetime' => '', // 必填
                'end_datetime' => '' // 必填
            ];
        */

        // 依照幣別、首儲時間起迄(含時區)，產生查詢式
        switch ($currency) {
            case 'gcash':
                $stmt = <<<SQL
                    SELECT "member"."id",
                           "member"."account",
                           "member"."therole",
                           timezone('{$timezone}', "member"."first_deposite_date") AS "first_deposite_date",
                           "passbook"."deposit" AS "amount",
                           "parent_id"
                    FROM "root_member" AS "member"
                    LEFT JOIN "root_member_gtokenpassbook" AS "passbook"
                        ON ("passbook"."transaction_time" = "member"."first_deposite_date") AND ("passbook"."source_transferaccount" = "member"."account")
                    WHERE (timezone('{$timezone}', "transaction_time") BETWEEN '{$search_details["start_datetime"]}' AND '{$search_details["start_datetime"]}')
                SQL;
                break;
            case 'gtoken':
                $stmt = <<<SQL
                    SELECT "member"."id",
                           "member"."account",
                           "member"."therole",
                           timezone('{$timezone}', "member"."first_deposite_date") AS "first_deposite_date",
                           "passbook"."deposit" AS "amount",
                           "parent_id"
                    FROM "root_member" AS "member"
                    LEFT JOIN "root_member_gtokenpassbook" AS "passbook"
                        ON ("passbook"."transaction_time" = "member"."first_deposite_date") AND ("passbook"."source_transferaccount" = "member"."account")
                    WHERE (timezone('{$timezone}', "transaction_time") BETWEEN '{$search_details["start_datetime"]}' AND '{$search_details["end_datetime"]}')
                        AND ("therole" IN ('A', 'M'))
                SQL;
                break;
            default:
                return '未定義的幣別';
        }

        // 首儲金額判斷
        $min_store_amount_is_filled = !empty($search_details['min_store_amount']);
        $max_store_amount_is_filled = !empty($search_details['max_store_amount']);

        if ( $min_store_amount_is_filled || $max_store_amount_is_filled ) {
            if ( $min_store_amount_is_filled && $max_store_amount_is_filled ) {
                $stmt .= <<<SQL
                    AND ( ({$search_details['min_store_amount']} <= "passbook"."deposit") AND ("passbook"."deposit" <= {$search_details['max_store_amount']}) )
                SQL;
            } else if ($min_store_amount_is_filled) {
                $stmt .= <<<SQL
                    AND ({$search_details['min_store_amount']} <= "passbook"."deposit")
                SQL;
            } else {
                $stmt .= <<<SQL
                    AND ("passbook"."deposit" <= {$search_details['max_store_amount']})
                SQL;
            }
        }

        // 取得首儲紀錄
        $store_records = runSQLall($stmt);
        return $store_records;
    }

?>