<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 針對 protal_setting_deltail.php 執行對應動作
// File Name:	protal_setting_deltail_action.php
// Author:		Yuan
// Related:		服務 protal_setting_deltail.php
// DB Table:  root_protalsetting
// Log:
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/protal_setting_lib.php";

// var_dump($_SESSION);
// var_dump($_POST);die();
// var_dump($_GET);


if (!isset($_SESSION['agent']) AND $_SESSION['agent']->therole != 'R') {
  die('帳號權限不合法');
}

$action = '';
if ( isset($_GET['a']) ) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
}

//-------------------------------------------
// 20200428

if(isset($_POST['title']) AND $_POST['title'] != '' AND isset($_POST['input_value']) AND $_POST['input_value'] != ''){
  $custom_data_title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);

  $user_link = filter_var($_POST['input_value'], FILTER_SANITIZE_STRING);
  // 開關(暫時隱藏)
  $custom_data_status = filter_var($_POST['value'], FILTER_SANITIZE_STRING);

  $to_jsondata['contact'] = $user_link;
  // $to_jsondata['status'] = $custom_data_status;
  $to_jsondata['status'] = 'on';
  $all_encode = json_encode($to_jsondata);

}elseif(isset($_POST['title']) AND $_POST['title'] != '' AND $_POST['input_value'] == ''){
  // 把input值刪除後，直接按存檔，預設的值
  $custom_data_title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);

  $user_link = '';

  if($custom_data_title == 'customer_service_online_weblink'){
    // $user_link = 'wx.qq.com';
    $user_link = '';

  }elseif($custom_data_title == 'customer_service_email'){
    // $user_link = '654321@gmail.com';
    $user_link = '';

  }elseif($custom_data_title == 'customer_service_mobile_tel'){
    // $user_link = '098765432';
    $user_link = '';
  }

  $to_jsondata['contact'] = $user_link;
  // $to_jsondata['status'] = $custom_data_status;
  $to_jsondata['status'] = 'on';
  $all_encode = json_encode($to_jsondata);

}elseif(isset($_POST['custom_data']) AND $_POST['custom_data'] != ''){
  // 社群1
  $custom_data = filter_var_array($_POST['custom_data'],FILTER_SANITIZE_STRING);
  $custom_data_title = 'customer_service_customization_setting_1';
  // 開關(暫時隱藏)
  $custom_data_status = filter_var($_POST['value_c'], FILTER_SANITIZE_STRING);

  foreach($custom_data as $key => $value){
    $customs[] = $value;
  }
  $to_jsondata['contact_app_name'] = $customs[0];
  $to_jsondata['contact_app_id'] = $customs[1];
  // $to_jsondata['status'] = $custom_data_status;
  $to_jsondata['status'] = 'on';

  $all_encode = json_encode($to_jsondata);
}elseif(isset($_POST['custom_data_2']) AND $_POST['custom_data_2'] != ''){
  // 社群2
  $custom_data = filter_var_array($_POST['custom_data_2'],FILTER_SANITIZE_STRING);
  $custom_data_title = 'customer_service_customization_setting_2';
  // 開關(暫時隱藏)
  $custom_data_status = filter_var($_POST['value_c'], FILTER_SANITIZE_STRING);

  foreach($custom_data as $key => $value){
    $customs[] = $value;
  }
  $to_jsondata['contact_app_name'] = $customs[0];
  $to_jsondata['contact_app_id'] = $customs[1];
  // $to_jsondata['status'] = $custom_data_status;
  $to_jsondata['status'] = 'on';

  $all_encode = json_encode($to_jsondata);
}
//----------------------------------------

// 關閉全民代理開關
function close_national_agent()
{
    $sql = <<<SQL
        UPDATE "root_protalsetting" SET "value" = 'off'
        WHERE ("name" = 'national_agent_isopen')
    SQL;
    return runSQL($sql);
}

function getAgentRegisterReviewGcashSetting()
{
    global $protalsetting;
    $setting = [];

    $sql = <<<SQL
        SELECT *
        FROM "root_protalsetting"
        WHERE "name" IN (
            'national_agent_isopen',
            'agency_registration_gcash',
            'agent_register_switch',
            'agent_review_switch'
        )
        AND ("status" = '1');
    SQL;

    $result = runSQLall($sql);

    if ( empty($result[0]) ) {
        return $protalsetting;
    }

    unset($result[0]);

    foreach ($result as $v) {
        $setting[$v->name] = $v->value;
    }

    return $setting;
}


switch ($action) {
  // 20200424 彈性設定 客服資訊名稱、ID
  case 'custom_setting_edit':

    $sql_result = update_insert_sql($custom_data_title,$all_encode);

    $result_msg = '更新成功';
    if (!$sql_result['status']) {
      $result_msg = $sql_result['result'];
    }else{
      echo "<script>alert('".$result_msg."');</script>";
    }

    break;
  //--------------------------

    case 'edit':
        // 主要動作
        $result = verification_post($_POST); // 這邊校驗參數

        if ( !$result['status'] ) {
            die(<<<HTML
                <script>
                    alert("{$result['result']}");
                </script>
            HTML);
        }

        $setting = getAgentRegisterReviewGcashSetting();

        $col_name = $result['result']['col_name'];
        $col_val = $result['result']['input_value'];

        switch ($col_name) {
            case 'agent_register_switch':
                // 代理註冊功能關閉時，如果全民代理功能為開啟，則回饋錯誤訊息
                if ( ($col_val === 'off') && ($setting['national_agent_isopen'] === 'on') ) {
                    // 需要先關閉全民代理功能才可以修改該項設定。
                    die( json_encode([
                        'status' => 'fail',
                        'msg' => $tr['turn off national_agent first']
                    ]) );
                }
                break;
            case 'national_agent_isopen': // 全民代理功能開關
                // 在設定為打開的時候，需判斷 "代理商申請設定" 是否開啟 與 "代理商申請自動審核" 與 "申請成為代理商的費用"是否為0
                if ($col_val === 'on') {
                    if ( ($setting['agent_register_switch'] !== 'on') || ($setting['agent_review_switch'] !== 'automatic') || ($setting['agency_registration_gcash'] > 0) ) {
                        die( json_encode([
                            'status' => 'fail',
                            'msg' => $tr['national_agent_isopen on']
                        ]) );
                    }
                }
                break;
            case 'agent_review_switch':
                // 會員申請成為代理時審核開關不為自動時，如果全民代理功能為開啟，則回饋錯誤訊息
                if ( ($col_val !== 'automatic') && ($setting['national_agent_isopen'] === 'on') ) {
                    // 需要先關閉全民代理功能才可以修改該項設定。
                    die( json_encode([
                        'status' => 'fail',
                        'msg' => $tr['turn off national_agent first']
                    ]) );
                }
                break;
            case 'agency_registration_gcash':
                // 申請成為代理商的費用不等於0時，如果全民代理功能為開啟，則回饋錯誤訊息
                if ( ($col_val > 0) && ($setting['national_agent_isopen'] === 'on') ) {
                    // 需要先關閉全民代理功能才可以修改該項設定。
                    die( json_encode([
                        'status' => 'fail',
                        'msg' => $tr['turn off national_agent first']
                    ]) );
                }
                break;
            default:
                // do nothing
        }

        // 執行sql
        $sql_result = update_insert_sql_action($col_name, $col_val); // echo '<pre>', var_dump($sql_result), '</pre>'; exit();
        $result_msg = ( ($sql_result['status']) ? $tr['upload protal setting success'] : $sql_result['result'] );

        // 整合設定值key
        /* $settings = array_merge(
            $entire_website_close_textarea,
            $limit_count_fee,
            $reward_table_setting,
            $customer_service_setting,
            $realtime_reward_number_setting,
            $qr_code_service_1,
            $qr_code_service_2,
            $custom_sns_rservice_setting
        );
        array_push($settings, 'agent_review_switch');
        $status = ( ( in_array($col_name, $settings) ) ? true : false ); */ // echo '<pre>', var_dump($status), '</pre>'; exit();

        // if ($status) {
            echo json_encode([
                'status' => ( ($sql_result['status']) ? 'success' : 'fail' ),
                'msg' => $result_msg
            ]);
        // }
        break;
  case 'del_qrcode':
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);

    // 原版
    // if (empty($name) || !in_array($name, $qrcode)) {
    //   $result_msg = $tr['no this QR code field'];
    //   echo "<script>alert('".$result_msg."');</script>";
    //   die();
    // }

    // ---------------
    // 20200428
    $sql=<<<SQL
    SELECT name,value FROM root_protalsetting
    WHERE name = '{$name}'
SQL;
    $result = runSQLall($sql);

    if($result[0] == 0){
      $result_msg = $tr['no this QR code field'];
      echo "<script>alert('".$result_msg."');</script>";
      die();
    }else{
      for($i= 1;$i<=$result[0];$i++){
        if($result[$i]->value == ''){
          $result_msg = $tr['no data in this field'];
          echo "<script>alert('".$result_msg."');</script>";
          die();
        }
      }
    }
    // -------------------

    // 原版
    // if (!array_key_exists($name, $protalsetting) || empty($protalsetting[$name])) {
    //   $result_msg = $tr['no data in this field'];
    //   echo "<script>alert('".$result_msg."');</script>";
    //   die();
    // }

    $sql_result = del_sql_action($name);

    $result_msg = $tr['Delete successfully'];
    if (!$sql_result['status']) {
      $result_msg = $sql_result['result'];
      echo "<script>alert('".$result_msg."');</script>";
      die();
    }

    echo "<script>alert('".$result_msg."');location.reload();</script>";

    break;
  default:
    die('(x)不合法的測試');
    break;
}


function verification_post($post)
{
    global $tr;
    global $protalsetting;
    global $protalsetting_input_upper_lower_limits;

    // 沒有索引值
    if ( empty($post['name']) ) {
        return [
            'status' => false,
            'result' => $tr['no such field,please recheck']
        ];
    } else {
        $col_name = filter_var($post['name'], FILTER_SANITIZE_STRING);
        if ($col_name === false) {
            return [
                'status' => false,
                'result' => $tr['no such field,please recheck']
            ];
        }
    }


    // 沒有值
    if ( ($post['value'] != "0") && empty($post['value']) ) {
        return [
            'status' => false,
            'result' => $tr['please enter correct content']
        ];
    } else {
        $input_value = filter_var($post['value'], FILTER_SANITIZE_STRING);
        if ($input_value === false) {
            return [
                'status' => false,
                'result' => $tr['please enter correct content']
            ];
        }
    }

    if ( array_key_exists($col_name, $protalsetting_input_upper_lower_limits) ) {
        $check_value_type_result = check_value_type($input_value, $protalsetting_input_upper_lower_limits->$col_name->type);
        if (!$check_value_type_result['status']) {
            return [
                'status' => false,
                'result' => $check_value_type_result['result']
            ];
        }

        if ( ($input_value < $protalsetting_input_upper_lower_limits->$col_name->lower) || ($input_value > $protalsetting_input_upper_lower_limits->$col_name->upper) ) {
            return [
                'status' => false,
                'result' => "{$tr['protal_setting_effective_limit']}{$protalsetting_input_upper_lower_limits->$col_name->lower} ~ {$protalsetting_input_upper_lower_limits->$col_name->upper}"
            ];
        }
    }

    switch ($col_name) {
        case 'customer_service_email': // E-mail格式錯誤
            if ( filter_var($input_value, FILTER_VALIDATE_EMAIL) === false ) {
                return [
                    'status' => false,
                    'result' => $tr['wrong format of email plase re enter']
                ];
            }
            break;
        case 'commission_1_rate':
            $commission_rate_sum = get_commission_rate_sum($col_name);
            if (($commission_rate_sum + $input_value) > 100) {
                return [
                    'status' => false,
                    'result' => $tr['The upstream four-layer percentage and the companys cost percentage need to be 100.'] // 上游四層百分比與公司成本百分比加總後需為100
                ];
            }
            break;
        case 'commission_2_rate':
            $commission_rate_sum = get_commission_rate_sum($col_name);
            if (($commission_rate_sum + $input_value) > 100) {
                return [
                    'status' => false,
                    'result' => $tr['The upstream four-layer percentage and the companys cost percentage need to be 100.'] // 上游四層百分比與公司成本百分比加總後需為100
                ];
            }
            break;
        case 'commission_3_rate':
            $commission_rate_sum = get_commission_rate_sum($col_name);
            if (($commission_rate_sum + $input_value) > 100) {
                return [
                    'status' => false,
                    'result' => $tr['The upstream four-layer percentage and the companys cost percentage need to be 100.'] // 上游四層百分比與公司成本百分比加總後需為100
                ];
            }
            break;
        case 'commission_4_rate':
            $commission_rate_sum = get_commission_rate_sum($col_name);
            if (($commission_rate_sum + $input_value) > 100) {
                return [
                    'status' => false,
                    'result' => $tr['The upstream four-layer percentage and the companys cost percentage need to be 100.'] // 上游四層百分比與公司成本百分比加總後需為100
                ];
            }
            break;
        case 'commission_root_rate':
            $commission_rate_sum = get_commission_rate_sum($col_name);
            if (($commission_rate_sum + $input_value) > 100) {
                return [
                    'status' => false,
                    'result' => $tr['The upstream four-layer percentage and the companys cost percentage need to be 100.'] // 上游四層百分比與公司成本百分比加總後需為100
                ];
            }
            break;
        case 'registrationmoney_member_grade':
            $member_grade = get_member_grade($input_value);

            if (!$member_grade['status']) {
                return [
                    'status' => false,
                    'result' => $member_grade['result']
                ];
            }

            $activity_register_preferential = json_decode($member_grade['result']->activity_register_preferential, false);
            if ( empty($activity_register_preferential->activity_register_preferential_enable) ) {
                return [
                    'status' => false,
                    'result' => $tr['The registration fee for this level is currently closed. To open it, please go to [System Management -> Member Level Management].'] //該等級註冊送彩金目前關閉中，如需開啟請至【系統管理->會員等級管理】
                ];
            }
            break;
        default:
            // do nothing
    }

    return [
        'status' => true,
        'result' => [
            'col_name' => $col_name,
            'input_value' => $input_value
        ]
    ];
}

function check_value_type($value, $type)
{
  global $tr;
  $type_errormsg = [
    'float' => $tr['Please enter a positive integer or a two-digit positive floating point number below the decimal.'], // '請輸入正整數或小數以下兩位正浮點數。'
    'integer' => $tr['Please enter a positive integer'] // '請輸入正整數。'
  ];

  $regular_expression = ($type == 'float') ? "/^[0-9]+(.[0-9]{1,2})?$/" : "/^[0-9]*[0-9][0-9]*$/";

  if (!preg_match($regular_expression, $value)) {
    return array('status' => false, 'result' => $type_errormsg[$type]);
  }

  return array('status' => true, 'result' =>'type ok');
}

function get_commission_rate_sum($commission_col)
{
  global $protalsetting;

  $commission_rate = 0;
  $check_item = ['commission_1_rate', 'commission_2_rate', 'commission_3_rate', 'commission_4_rate', 'commission_root_rate'];

  foreach ($check_item as $item) {

    if ($item != $commission_col) {
      $commission_rate += $protalsetting[$item];
    }
  }

  return $commission_rate;
}

function get_member_grade($id)
{
    global $tr;
    $sql = <<<SQL
        SELECT "id",
               "gradename",
               "activity_register_preferential"
        FROM "root_member_grade"
        WHERE ("id" = '{$id}')
    SQL;
    $result = runSQLall($sql);
    if ( empty($result[0]) ) {
        return [
            'status' => false,
            'result' => $tr['member level inquire fail'] // 會員等及查詢失敗
        ];
    } else {
        return [
            'status' => true,
            'result' => $result[1]
        ];
    }
}

// 彈性設定 客服資訊名稱、ID，要json_encode存在value
function update_insert_sql($name,$value){
  global $protalsetting;
  global $colname_arr;
  global $instructions_arr;

  $sql = <<<SQL
    SELECT * FROM root_protalsetting
    WHERE name = '{$name}'
SQL;
  $result = runSQL($sql);
  // var_dump($result);die();
  if($result != 0){
    $sql = <<<SQL
      UPDATE root_protalsetting SET value = '{$value}'
      WHERE name = '{$name}';
SQL;
  }else{
    $description = (array_key_exists($name, $instructions_arr)) ? $instructions_arr[$name] : $colname_arr[$name];

    $sql = <<<SQL
    INSERT INTO root_protalsetting (setttingname,name,value,status,description)
    VALUES('default','{$name}','{$value}',1,'{$description}')
    ON CONFLICT ON CONSTRAINT "root_protalsetting_setttingname_name"
    DO UPDATE SET value = '{$value}';
SQL;
  }
  // var_dump($sql);die();
  $result = runSQL($sql);

  // 強制更新前後台memcache資料
  $update_result = memcache_forceupdate();
  // var_dump($result,$update_result);die();

  if (!$result) {
    return array('status' => false, 'result' => '更新失敗，請重新嘗試。');
  }

  return array('status' => true, 'result' => '更新成功。');

}
// ------------------------
function update_insert_sql_action($name, $value)
{
    global $protalsetting;
    global $colname_arr;
    global $instructions_arr;

    $sql = <<<SQL
        UPDATE "root_protalsetting" SET "value" = '{$value}'
        WHERE "name" = '{$name}';
    SQL;

    if ( !array_key_exists($name, $protalsetting) ) {
        $description = (array_key_exists($name, $instructions_arr)) ? $instructions_arr[$name] : $colname_arr[$name];

        $sql = <<<SQL
            INSERT INTO "root_protalsetting" (
                "setttingname",
                "name",
                "value",
                "status",
                "description"
            ) VALUES (
                'default',
                '{$name}',
                '{$value}',
                1,
                '{$description}'
            ) ON CONFLICT ON CONSTRAINT "root_protalsetting_setttingname_name"
            DO UPDATE SET value = '{$value}';
        SQL;
    }
    $result = runSQL($sql);

    // 強制更新前後台memcache資料
    $update_result = memcache_forceupdate();

    if (!$result) {
        return [
            'status' => false,
            'result' => '更新失敗，請重新嘗試。'
        ];
    } else {
        return [
            'status' => true,
            'result' => '更新成功。'
        ];
    }
}

function del_sql_action($name)
{
  global $protalsetting;

  $sql = <<<SQL
    UPDATE root_protalsetting SET value = '' WHERE name = '$name'
SQL;

  $result = runSQL($sql);

  // 強制更新前後台memcache資料
  $update_result = memcache_forceupdate();

  if (!$result) {
    return array('status' => false, 'result' => 'QRCode刪除失敗。');
  }

  return array('status' => true, 'result' => 'QRCode刪除成功。');

}

?>
