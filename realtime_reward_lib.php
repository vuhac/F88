<?php
// ----------------------------------------------------------------------------
// Features：後台 -- 時時反水計算 lib
// File Name：realtime_reward_lib.php
// Author：yaoyuan  2019/03/18
// Editor：Damocles
// Related：root_protalsetting    ->時時反水設定值。
//          root_statisticsbetting->依照日期區間，撈十分鐘報表。
//          root_realtime_reward  ->時時反水資料。
//          root_receivemoney     ->反水打入彩金池db。
// Log：
// ----------------------------------------------------------------------------
// 對應資料表
// 相關的檔案
// 功能說明
// 1.透過每日報表資料, 計算統計出每日的個節點營利損益狀態
// 2.依據分用比例, 從上到下分配營利的盈餘, 以每日為單位。
// 3.加總指定區間的資料, 成為個節點的每日損益狀態.
// 4.每月分配股東的損益到獎金分發的表格
// update   : yyyy.mm.dd

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";


// ---------------------------------------------------------------
// check date format
// ---------------------------------------------------------------
// get example: ?current_datepicker=2017-02-03
// ref: http://php.net/manual/en/function.checkdate.php
function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}
// -----------------------------------------

// 撈出時時反水全站設定值
function realtime_reward_protalsetting()
{
    $sets = [];
    $sql = <<<SQL
        SELECT *
        FROM "root_protalsetting"
        WHERE "name" LIKE '%realtime%'
        ORDER BY "id" ASC
    SQL;
    $sql_result = runSQLall($sql);
    if ($sql_result[0] > 0) {
        unset($sql_result[0]);
        foreach ($sql_result as $val) {
            $sets[$val->name] = $val->value;
        }
    }
    return $sets;
}

// 取出會員、id、總投注、總損益、以遊戲統計資料 sql ->debug=1
// Updated at 2020/07/07 By Damocles
function member_id_bets_profitloss($start_datetime, $end_datetime, $debug=0)
{
    $stmt = <<<SQL
        SELECT "dateok"."member_account",
               max("dateok"."member_id") as "member_id",
               "root_member"."therole",
               "root_member"."favorablerule",
               "dateok"."casino_id",
               SUM("dateok"."account_betvalid") as "account_betvalid",
               SUM("dateok"."account_profit") as "account_profit",
               "dateok"."favorable_game_name" as "favorable_game_name"
        FROM ( -- 組合時間並取得所有資料，時間隔式變成datetime
                SELECT CONCAT("autoenddate"."dailydate", ' ', "dailytime_start") as "start_datetime", -- 美東時間
                       CONCAT("autoenddate"."automathenddate", ' ', "dailytime_end") as "end_datetime", -- 美東時間
                       *
                FROM ( -- 整合時間並取得所有資料，把23:50:00的時間累進到隔天
                        SELECT CASE
                            when "dailytime_start" = '23:50:00' then "dailydate" +1
                            else "dailydate"
                            END  AS "automathenddate",
                            *
                        FROM "root_statisticsbetting"
                ) AS "autoenddate"
        ) AS "dateok"
        LEFT JOIN "root_member"
            ON ("dateok"."member_id" = "root_member"."id")
        WHERE ("dateok"."start_datetime" >= '{$start_datetime}')
            AND "dateok"."end_datetime" <= '{$end_datetime}'
        GROUP BY "member_account",
                 "root_member"."therole",
                 "root_member"."favorablerule",
                 "dateok"."casino_id",
                 "dateok"."favorable_game_name"
        ORDER BY "member_account",
                 "member_id"
    SQL;

    if ($debug == 1) { // 偵錯模式
        die($stmt);
    } else { // 非偵錯模式
        $result = runSQLall($stmt);
        if ($result[0] > 0) { // 有資料
            unset($result[0]); // 去除總計欄位
            foreach ($result as $key=>$val) {
                // 把10分鐘報表內的以遊戲統計內的資料轉成json
                if ( ($val->favorable_game_name != null) && !empty($val->favorable_game_name) ) { // 資料非null(這邊的null不等於參數未設定)也非空白
                    $result[$key]->favorable_game_name = json_decode($val->favorable_game_name);
                }
            }
            return $result;
        } else {
            return null;
        }
    }
}

// 待移除
// 取出會員id對映之會員身份、反水名稱，並結合會員總投注資料  debug=3-> member 反水 身份
function mid_map_favor_therole($member_id_bets_profitloss, $debug=0)
{
    $idmapfavorte = [];
    $member = [];

    // 查詢所有帳號
    $stmt = <<<SQL
        SELECT "id",
               "favorablerule",
               "therole"
        FROM "root_member"
        ORDER BY "id"
    SQL;
    $result = runSQLall($stmt);

    if ($debug == 3) {
        var_dump($result);
        die();
    }

    /*
        [member_id] = [
            'favorablerule' => 'basic',
            'therole' => 'M'
        ]
    */
    $result = array_slice($result, 1);
    foreach ($result as $value) {
        $idmapfavorte[$value->id]["favorablerule"] = $value->favorablerule;
        $idmapfavorte[$value->id]["therole"] = $value->therole;
    }
    // echo '<pre>', var_dump($idmapfavorte), '</pre>'; exit();

    $member_id_bets_profitloss = array_slice($member_id_bets_profitloss, 1);
    // echo '<pre>', var_dump($member_id_bets_profitloss), '</pre>'; exit();

    foreach ($member_id_bets_profitloss as $value_betdata) {
        $member[] = [
            "member_account" => $value_betdata->member_account,
            "member_id" => $value_betdata->member_id,
            "bet_sum" => $value_betdata->account_betvalid,
            "profit_sum" => $value_betdata->account_profit,
            "favorablerule" => $idmapfavorte[$value_betdata->member_id]["favorablerule"],
            "therole" => $idmapfavorte[$value_betdata->member_id]["therole"],
        ];
    }
    // echo '<pre>', var_dump($member), '</pre>'; exit();
    return $member;
}


// 取出反水等級設定並對映會員資料 debug5->所有反水設定
function membermap_favorablerates($mid_map_favor_therole, $get_casino_game_categories, $debug=0)
{
    $member = [];
    $settings = [];
    $catetmp = [];
    $cate_set = [];

    // 取得返水等級設定
    $stmt = <<<SQL
        SELECT *
        FROM "root_favorable"
        WHERE ("status" = '1')
            AND ("deleted" = '0')
        ORDER BY "name" ASC,
                 "wager" DESC
    SQL;
    $result = runSQLall($stmt);

    if ($debug == 5) {
        var_dump($result);
        die();
    }

    if ($result[0] < 1) {
        echo "沒有反水等級設定 !\n";
        die();
    }

    $result = array_slice($result, 1);

    // 結合反水等級設定
    foreach ($result as $k => $v) {
        $catetmp = json_decode($v->favorablerate, true);
        // echo '<pre>', var_dump($catetmp), '</pre>'; exit();
        foreach ($get_casino_game_categories as $key=>$value) {
            // echo '<pre>', var_dump($value), '</pre>'; exit();
            foreach ($value as $value1) {
                // echo '<pre>', var_dump($value1), '</pre>'; exit();
                $cate_set[$key][$value1] = $catetmp[strtoupper($key)][$value1] ?? 0;
            }
        }

        $settings[$v->name][$v->wager] = [
            'wager'         => $v->wager,
            'upperlimit'    => $v->upperlimit,
            'favorablerate' => $cate_set,
            'group_name'    => $v->group_name,
        ];
    }
    // echo '<pre>', var_dump($settings), '</pre>'; exit();


    if ($debug == 9) {
        var_dump($settings);
        die();
    }

    // 解析會員資料，對映反水等級
    foreach ($mid_map_favor_therole as $k_mdata=>$v_mdata){
        $bet_ok=0;
        foreach ($settings[$v_mdata["favorablerule"]] as $bet_lower_limit => $fav_con){
            // 有到達打碼量，則記錄反水設定，並離開反水設定迴圈
            // 未達  打碼量，則往下一個打碼量，並判斷是否符合打碼量，若已到最後設定，仍然沒有符合最低打碼量，則 達成打碼量為f，打碼量等級為-1
            if($v_mdata["bet_sum"]>=$bet_lower_limit){
                $bet_ok=1;
                $member[$v_mdata["member_id"]]=[
                    "member_id"        => $v_mdata["member_id"],
                    "member_account"   => $v_mdata["member_account"],
                    "therole"          => $v_mdata["therole"],
                    "favorablerule"    => $v_mdata["favorablerule"],
                    "reach_bet_amount" => "t",
                    "bet_sum"          => $v_mdata["bet_sum"],
                    "profit_sum"       => $v_mdata["profit_sum"],
                    "wager"            => $bet_lower_limit,
                    "upperlimit"       => $fav_con["upperlimit"],
                    "favorablerates"   => $fav_con["favorablerate"],
                    "group_name"       => $fav_con["group_name"],
                ];
                // echo('總投注額大於等於反水投注下限');
                break;
            }else{
                // echo ('總投注額小於反水投注下限');
                continue;
            }
        }
        // 未到達打碼量，不計算反水
        if($bet_ok==0){
            $member[$v_mdata["member_id"]]=[
                "member_id"        => $v_mdata["member_id"],
                "member_account"   => $v_mdata["member_account"],
                "therole"          => $v_mdata["therole"],
                "favorablerule"    => $v_mdata["favorablerule"],
                "reach_bet_amount" => "f",
                "bet_sum"          => $v_mdata["bet_sum"],
                "profit_sum"       => $v_mdata["profit_sum"],
                "wager"            => '-1',
                "upperlimit"       => $fav_con["upperlimit"],
                "favorablerates"   => $fav_con["favorablerate"],
                "group_name"       => $fav_con["group_name"],
              ];
        }
    }
    return $member;
}

// 取出返水設定所有資料並整合成陣列
// Created at 2020/07/07 By Damocles
function queryFavorableRules()
{
    $stmt = <<<SQL
        SELECT "id",
               "name",
               "wager",
               "upperlimit",
               "audit",
               "favorablerate",
               "group_name"
        FROM "root_favorable"
        WHERE ("status" = '1')
            AND ("deleted" = '0')
        ORDER BY "name" ASC,
                 "wager" DESC;
    SQL;
    $result = runSQLall($stmt);
    if ($result[0] > 0) {
        $result = array_slice($result, 1); // 去除總計欄位
        // 把同返水名稱的資料整合在同一個陣列，方便後續做比對的時候處理
        $favorable_data = [];
        foreach ($result as $key=>$val) { // 把遊戲分類的返水比例資料轉成陣列
            $result[$key]->favorablerate = (array)json_decode($val->favorablerate);
            foreach($result[$key]->favorablerate as $key_casino_id=>$val_category) {
                foreach ($val_category as $key_category=>$val_category_rate) { // 把各遊戲分類的比率各除以100，以利後續計算不須要再從%轉成純數字
                    $result[ $key ]->favorablerate[ $key_casino_id ]->$key_category = ($result[ $key ]->favorablerate[ $key_casino_id ]->$key_category / 100);
                }
            }

            $favorable_name = $val->name; // 返水名稱
            $favorable_wager = $val->wager; // 返水打碼量
            unset($result[$key]->name, $result[$key]->wager); // 移除掉返水名稱、返水打碼量，後續比對後可以直接整合返水資料
            $favorable_data[$favorable_name][$favorable_wager] = $result[$key]; // 不管 $favorable_data 裡面是否已經有設定該返水名稱的資料，就直接整合返水資料
        }
        return $favorable_data;
    } else {
        return null;
    }
}

// 撈出十分鐘報表資料
function statisticsbetting($sdate, $edate,$debug=0)
{
    $sql = <<<SQL
        SELECT
            max(dateok.member_id) AS member_id,
            dateok.member_account,
            dateok.casino_id,
            dateok.category,
            SUM(dateok.bet) as bet,
            SUM(dateok.profitlost) as profitlost
        FROM (
                SELECT
                    CONCAT(autoenddate.dailydate,' ', dailytime_start) as start_datetime,
                    CONCAT(autoenddate.automathenddate,' ', dailytime_end) as end_datetime,
                    *
                FROM(
                        SELECT
                            CASE
                            when dailytime_start='23:50:00' then dailydate +1
                            else dailydate
                            END  AS automathenddate ,
                            (category_detail->>'betfavor') as category,
                            (category_detail->>'betvalid') :: numeric(20,2) as bet,
                            (category_detail->>'betprofit') :: numeric(20,2) as profitlost,
                            *
                        FROM root_statisticsbetting, json_array_elements (root_statisticsbetting.favorable_category :: json) category_detail
                ) AS autoenddate
        ) AS dateok
        WHERE dateok.start_datetime>='$sdate'
        AND dateok.end_datetime<='$edate'
        GROUP BY member_account, casino_id, category
        ORDER BY member_account, casino_id , category ASC
    SQL;
    if($debug==7) {echo($sql);die();}
    return runSQLall($sql);
}


// 加總-->十分鐘報表，投注資料明細資料
function sum_person_bet($statisticsbetting, $membermap_favorablerates, $get_casino_game_categories, $debug = 0)
{
    $conbine_member_data = [];
    $bonus_casino_sum    = 0;
    // echo '<pre>', var_dump($statisticsbetting), '</pre>'; exit();
    unset($statisticsbetting[0]);

    // $j=1;
    // 解析每一筆十分鐘資料表撈出的區間紀錄
    foreach ($statisticsbetting as $sta_val) {
        // 第一次載入會員基本資料
        if (!array_key_exists($sta_val->member_id, $conbine_member_data)) {
            $conbine_member_data[$sta_val->member_id]["member_account"]   = $sta_val->member_account;
            $conbine_member_data[$sta_val->member_id]["therole"]          = $membermap_favorablerates[$sta_val->member_id]["therole"];
            $conbine_member_data[$sta_val->member_id]["favorablerule"]    = $membermap_favorablerates[$sta_val->member_id]["favorablerule"];
            $conbine_member_data[$sta_val->member_id]["reach_bet_amount"] = $membermap_favorablerates[$sta_val->member_id]["reach_bet_amount"];
            $conbine_member_data[$sta_val->member_id]["bet_sum"]          = $membermap_favorablerates[$sta_val->member_id]["bet_sum"];
            $conbine_member_data[$sta_val->member_id]["profit_sum"]       = $membermap_favorablerates[$sta_val->member_id]["profit_sum"];
            $conbine_member_data[$sta_val->member_id]["wager"]            = $membermap_favorablerates[$sta_val->member_id]["wager"];
            $conbine_member_data[$sta_val->member_id]["upperlimit"]       = $membermap_favorablerates[$sta_val->member_id]["upperlimit"];
            $conbine_member_data[$sta_val->member_id]["group_name"]       = $membermap_favorablerates[$sta_val->member_id]["group_name"];
        }
        echo '<pre>', var_dump($membermap_favorablerates), '</pre>'; exit();
        // 算出所有投注明細
        foreach ($membermap_favorablerates[$sta_val->member_id]["favorablerates"] as $casino => $cates) {
            foreach ($cates as $cate_name => $cate_val) {
                $bonus_calculate = 0;
                $bonus_setting = 0;
                if ($membermap_favorablerates[$sta_val->member_id]["reach_bet_amount"] == 't') {
                    $bonus_calculate = number_format(($sta_val->bet * number_format($cate_val / 100, 2)), 2, '.', '');
                    // $bonus_calculate = number_format(($sta_val->bet * $cate_val) / 100, 2, '.', '');
                    $bonus_setting = number_format($cate_val / 100, 2, '.', '');
                }

                if ($casino == strtolower($sta_val->casino_id) and $cate_name == $sta_val->category) {
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_bet']     = $sta_val->bet;
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_setting'] = $bonus_setting;
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_profit']  = $sta_val->profitlost;
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_bonus']   = $bonus_calculate;
                } else {
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_bet']     = $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_bet'] ?? 0;
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_setting'] = $bonus_setting;
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_profit']  = $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_profit'] ?? 0;
                    $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_bonus']   = $conbine_member_data[$sta_val->member_id]["bet_detail"][strtolower($casino) . '_' . $cate_name . '_bonus'] ?? 0;
                }
            }
        }

        // $j++;
        // if($j==35){
        //     break;
        //     var_dump($conbine_member_data);
        //     die();
        //   }
    }
    if ($debug == 10) {var_dump($conbine_member_data);die();}

    foreach ($conbine_member_data as $key => $value) {
        $conbine_member_data[$key]["reward_amount"]      = 0;
        $conbine_member_data[$key]["real_reward_amount"] = 0;

        foreach ($get_casino_game_categories as $key_casino => $va_cates) {
            $conbine_member_data[$key]["casino_sum"][strtolower($key_casino) . '_bet']    = 0;
            $conbine_member_data[$key]["casino_sum"][strtolower($key_casino) . '_profit'] = 0;
            $conbine_member_data[$key]["casino_sum"][strtolower($key_casino) . '_bonus']  = 0;
            foreach ($va_cates as $val_cate) {
                $conbine_member_data[$key]["casino_sum"][strtolower($key_casino) . '_bet'] +=($value["bet_detail"][$key_casino . '_' . $val_cate . '_bet']);
                $conbine_member_data[$key]["casino_sum"][strtolower($key_casino) . '_profit'] += $value["bet_detail"][$key_casino . '_' . $val_cate . '_profit'];
                $conbine_member_data[$key]["casino_sum"][strtolower($key_casino) . '_bonus'] += $value["bet_detail"][$key_casino . '_' . $val_cate . '_bonus'];
            }
            // 各娛樂城反水加總
            $conbine_member_data[$key]["reward_amount"] += $conbine_member_data[$key]["casino_sum"][strtolower($key_casino) . '_bonus'];
        }
        // 實際拿到反水：判斷反水大於反水上限，以反水上限為主
        $conbine_member_data[$key]["real_reward_amount"] = $conbine_member_data[$key]["reward_amount"] >= $conbine_member_data[$key]["upperlimit"] ? $conbine_member_data[$key]["upperlimit"] : $conbine_member_data[$key]["reward_amount"];

    }
    // 將所有浮點數加總欄位，運算到小數後2位
    foreach ($conbine_member_data as $id => $value){
      foreach($value["casino_sum"] as $key => $num_val){
        $conbine_member_data[$id]["casino_sum"][$key]=round($num_val,2);
      }
      $conbine_member_data[$id]["reward_amount"]=round($value["reward_amount"],2);
      $conbine_member_data[$id]["real_reward_amount"]=round($value["real_reward_amount"],2);
    }
    return $conbine_member_data;
}

// 組成時時反水sql字串
function insertRealtimeRewardSql(array $commission_dailyreport_data)
{
    // echo '<pre>', var_dump($commission_dailyreport_data), '</pre>'; exit();

    $attributes = array_keys($commission_dailyreport_data);
    $values = array_values($commission_dailyreport_data);
    $attributes_string = implode(',', $attributes);
    $values_string = "'" . implode("','", $values) . "'";

    $get_set_string_fun = function ($attribute, $value) {
        return "$attribute = '$value'";
    };

    $set_string = implode(',', array_map($get_set_string_fun, $attributes, $values));

    $insert_or_update_sql = <<<SQL
        INSERT INTO root_realtime_reward ($attributes_string)
        VALUES($values_string)
        ON CONFLICT ON CONSTRAINT root_realtime_reward_member_id_start_date_end_date
        DO
        UPDATE
        SET $set_string
        ;
    SQL;

    // echo '<pre>', var_dump($insert_or_update_sql), '</pre>'; // exit();
    return $insert_or_update_sql;
}

// insert_receivemoney_sql
function insert_receivemoney_sql(array $array_column_value)
{
    // print_r($commission_dailyreport_data);

    $attributes = array_keys($array_column_value);
    $values     = array_values($array_column_value);

    $attributes_string = implode(',', $attributes);
    $values_string     = "'" . implode("','", $values) . "'";

    $get_set_string_fun = function ($attribute, $value) {
        return "$attribute = '$value'";
    };

    $set_string = implode(',', array_map($get_set_string_fun, $attributes, $values));

    $insert_or_update_sql = <<<SQL
    INSERT INTO root_receivemoney ($attributes_string)
      VALUES($values_string);
    SQL;
    // echo ($insert_or_update_sql);die();
    // 寫入memberlog
    $msg         = $array_column_value['last_modify_member_account'] . '点选补发时时反水功能，发送至池金池。会员帐号：' . $array_column_value['member_account'] . '。彩金类别：'.$array_column_value['prizecategories'].'。現金：'.$array_column_value['gcash_balance'].'。游戏币：'.$array_column_value['gtoken_balance'].'。时时反水交易批号：'.$array_column_value['reconciliation_reference'].'。'; //客服
    $msg_log     = $msg.'彩金状态：'.$array_column_value['status']; //RD
    $sub_service = 'realtime_reward';
    memberlogtodb($array_column_value['last_modify_member_account'], 'marketing', 'notice', $msg, $array_column_value['member_account'], "$msg_log", 'b', $sub_service);

    return $insert_or_update_sql;
}


// 從db撈出時時反水，準備寫入彩金池
function get_realtime_reward($im_transaction_id)
{
    $stmt = <<<SQL
        SELECT
            "member_id",
            "member_account",
            to_char(("start_date" AT TIME ZONE 'AST'),'YYYY-MM-DD') AS "start_date_ast",
            to_char(("start_date" AT TIME ZONE 'AST'),'HH24:MI') AS "start_time_ast",
            to_char(("end_date" AT TIME ZONE 'AST'),'HH24:MI') AS "end_time_ast",
            to_char(("end_date" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI') as "end_date",
            to_char(("start_date" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI') as start_date,
            "transaction_id",
            "real_reward_amount"
        FROM "root_realtime_reward"
        WHERE "transaction_id" = '{$im_transaction_id}'
            AND "reach_bet_amount" ='t'
            AND "real_reward_amount" > 0
        ORDER BY "member_account"
    SQL;
    return runSQLall($stmt);
}


// 撈出時時反水資料
function get_realtime_reward_sql($where_sql)
{
    $userlist_sql_tmp = <<<SQL
        SELECT
            "member_id",
            "member_account",
            "member_therole",
            "favorable_level",
            "favorable_bet_level",
            "reward_amount",
            "reach_bet_amount",
            "details",
            "casino_sum",
            "is_payout",
            "payout_date",
            "transaction_id",
            "bet_sum",
            "profit_sum",
            "notes",
            "real_reward_amount",
            "favorable_group_name",
            "favorable_upperlimit",
            to_char(("start_date" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS "start_date_ast",
            to_char(("end_date" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS "end_date_ast",
            to_char(("updatetime" AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS') AS "updatetime_ast"
        FROM "root_realtime_reward"
        WHERE $where_sql
    SQL;
    return $userlist_sql_tmp;
}


// 列出左上方menu列表
function menu_realtimereward_list_html(){
    global $tr,$su;
    // 列出系統資料統計月份
    $list_sql = <<<SQL
        SELECT
            SUM(bet_sum) as bet,
            SUM(profit_sum) as profitlost,
            SUM(real_reward_amount) as real_reward_amount,
            COUNT(id) as member_count,
            max(to_char((updatetime AT TIME ZONE 'AST'),'YYYY-MM-DD HH24:MI:SS')) AS updatetime,
            max(to_char((start_date AT TIME ZONE 'AST'),'YYYY-MM-DD')) AS start_date_ast,
            max(to_char((start_date AT TIME ZONE 'AST'),'HH24:MI')) AS start_time_ast,
            max(to_char((end_date AT TIME ZONE 'AST'),'YYYY-MM-DD')) AS end_date_ast,
            max(to_char((end_date AT TIME ZONE 'AST'),'HH24:MI')) AS end_time_ast,
            transaction_id,
            max(CASE when is_payout  = 't' then 'y' else 'n' END) as payout
        FROM root_realtime_reward
        WHERE reach_bet_amount='t'
        GROUP BY transaction_id
        ORDER BY start_date_ast DESC , start_time_ast DESC
        LIMIT 60;
    SQL;

    $list_result = runSQLall($list_sql);
    // var_dump($list_result);die();
    $list_stats_data = '';
    if ($list_result[0] > 0) {

        // 把資料 dump 出來 to table
        for ($i = 1; $i <= $list_result[0]; $i++) {

            // 統計區間
            $date_range = $list_result[$i]->start_date_ast .' ' .$list_result[$i]->start_time_ast.' ~ ' . $list_result[$i]->end_time_ast;

            $get_list_url = 'realtime_reward.php?trans_id=' . $list_result[$i]->transaction_id.'&is_payout='.$list_result[$i]->payout.'&show_start_date='.$list_result[$i]->start_date_ast.' '.$list_result[$i]->start_time_ast.'&show_end_date='.$list_result[$i]->end_date_ast.' '.$list_result[$i]->end_time_ast;

            $date_range_html = '<a href="' . $get_list_url . '" title="观看指定区间">' . $date_range . '</a>';
            // 獲得反水人數
            $member_count_html = number_format($list_result[$i]->member_count, 0, '.', ',');
            // 反水總量
            $comm_sum_html = number_format($list_result[$i]->real_reward_amount, 2, '.', ',');
            // 發送至彩金池
            if($list_result[$i]->payout=='n' AND in_array($_SESSION['agent']->account, $su['superuser'])){
                $payout_html = '<a href="#" onclick="payout_batch_update(\''.$list_result[$i]->transaction_id.'\',\''.$date_range.'\');"  title="更新时间：(美东)'.$list_result[$i]->updatetime.'(点击可以派发至彩金池)">'.$tr[$list_result[$i]->payout].' <button class="glyphicon glyphicon-gift"></button></a>';
            }else{
                $payout_html = $tr[$list_result[$i]->payout];
            }
            // 更新日期
            // $update_time_html = $list_result[$i]->updatetime_ast;

            $list_stats_data = $list_stats_data . '
      <tr>
        <td>' . $date_range_html . '</td>
        <td>' . $member_count_html . '</td>
        <td>' . $comm_sum_html . '</td>
        <td>' . $payout_html . '</td>
      </tr>
      ';
        }

    } else {
        $list_stats_data = $list_stats_data . '
    <tr>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
    </tr>
    ';
    }

    // 統計資料及索引  // <th>更新时间</th>
    $listdata_html = '
    <table class="table table-bordered small">
      <thead>
        <tr class="active">
          <th>' . $tr['Statistical interval'] . '<span class="glyphicon glyphicon-time"></span>(-04)</th>
          <th>' . $tr['Total number of issued'] . '</th>
          <th>' . $tr['total amount of bonus'] . '</th>
          <th>' . $tr['payout'] . '</th>
        </tr>
      </thead>
      <tbody style="background-color:rgba(255,255,255,0.4);">
        ' . $list_stats_data . '
      </tbody>
    </table>';

    return ($listdata_html);
}


// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS
// ---------------------------------------------------------------------------
function indexmenu_stats_switch(){
    global $tr;
    // 历史纪录OFF
    // 历史纪录ON
    // 選單表單
    $indexmenu_list_html = menu_realtimereward_list_html();

    // 加上 on / off開關
    $indexmenu_stats_switch_html = '
  <span style="
  position: fixed;
  top: 5px;
  left: 5px;
  width: 450px;
  height: 20px;
  z-index: 1000;
  ">
  <button class="btn btn-primary btn-xs" style="display: none" id="hide">' . $tr['menu off'] . '</button>
  <button class="btn btn-success btn-xs" id="show">' . $tr['menu on'] . '</button>
  </span>

  <div id="index_menu" style="display:block;
  background-color: #e6e9ed;
  position: fixed;
  top: 30px;
  left: 5px;
  width: 450px;
  height: 600px;
  overflow: auto;
  z-index: 999;
  -webkit-box-shadow: 0px 8px 35px #333;
  -moz-box-shadow: 0px 8px 35px #333;
  box-shadow: 0px 8px 35px #333;
  background: rgba(221, 221, 221, 1);
  ">
  ' . $indexmenu_list_html . '
  </div>
  <script>
  $(document).ready(function(){
      $("#index_menu").fadeOut( "fast" );

      $("#hide").click(function(){
          $("#index_menu").fadeOut( "fast" );
          $("#hide").hide();
          $("#show").show();
      });
      $("#show").click(function(){
          $("#index_menu").fadeIn( "fast" );
          $("#hide").show();
          $("#show").hide();
      });
  });

  </script>
  ';

    return ($indexmenu_stats_switch_html);
}
// ---------------------------------------------------------------------------
// 加上 on / off開關 JS and CSS   END
// ---------------------------------------------------------------------------


// 算出時間區間
function datetime_range($first, $last){
    $dates  = array();
    $period = new DatePeriod(
        new DateTime($first),
        new DateInterval('PT1H'),
        new DateTime($last . '+1 hours')
    );
    foreach ($period as $date) {
        // var_dump($period,$date);
        $dates[] = $date->format('Y-m-d H:i:s');
    }
    return $dates;
}

// 判斷如果有反水資料，且發到彩金池，這樣不可補發
function judge_reward($where_sql){
    $sql_tmp = <<<SQL
            SELECT *
            FROM root_realtime_reward
            WHERE $where_sql
                AND is_payout='t'
    SQL;
    $result=runsql($sql_tmp);
    if($result>1){
        return 0;
    }else{
        return 1;
    }
}



// 要更新時時反水資料之前，先刪除舊有反水資料
// Updated at 2020/07/10 By Damocles
function del_realtime_reward_data($start_datetime, $end_datetime)
{
    $stmt = <<<SQL
        DELETE FROM "root_realtime_reward"
        WHERE "start_date" >= '{$start_datetime}'
            AND "end_date" <= '{$end_datetime}'
    SQL;
    runSQL($stmt);
    return true;
}

// 將反水打至彩金池前，先更新狀態及日期時間
function update_realtime_reward_payout_date($trans_id){
    $sql=<<<SQL
        UPDATE root_realtime_reward
        SET updatetime=NOW(),
            payout_date=NOW(),
            is_payout='t'
        WHERE transaction_id='$trans_id'
SQL;
    runSQLall($sql);
    return true;
}

// 要計算反水前，先依日期區間，判斷是否有資料存在
// Last modifity by Damocles in 2020-06-22 17:30
function reward_data_exist($start_datetime, $end_datetime)
{
    $stmt = <<<SQL
        SELECT *
        FROM "root_realtime_reward"
        WHERE ("start_date" = '{$start_datetime}')
            AND ("end_date" = '{$end_datetime}')
    SQL;
    $result = runSQLall($stmt);
    return ( ($result[0] >= 1) ? true : false );
}

// 測試開發使用-刪除指定日期起訖的返水資料
// Created at 2020/07/07 By Damocles
function deleteRealTimeRewardData($start_datetime, $end_datetime)
{
    $stmt = <<<SQL
        DELETE
        FROM "root_realtime_reward"
        WHERE ("start_date" = '{$start_datetime}')
            AND ("end_date" = '{$end_datetime}');
    SQL;
    $result = runSQLall($stmt);
    return $result;
}

?>
