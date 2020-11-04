<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 會員等級管理 , 顯示詳細會員等級資訊
// File Name:	member_grade_config_detail_action.php
// Author:		Yuan
// Related:   服務 member_grade_config_detail.php
// DB Table:  root_member_grade
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

if(isset($_GET['a']) AND $_SESSION['agent']->therole == 'R') {
  $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING);
//  var_dump($_GET);
} else {
  die('(x)不合法的测试');
}
// var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);


// 更新資料 sql 動作
function update_sql($member_grade_id, $table_column_name, $updata_value)
{
  $member_grade_update_sql = "UPDATE root_member_grade SET $table_column_name = '".$updata_value."' WHERE id = '".$member_grade_id."';";
  // var_dump($member_grade_update_sql);
  $member_grade_update_sql_result = runSQL($member_grade_update_sql);
  // var_dump($member_grade_update_sql_result);
  return $member_grade_update_sql_result;
}

// 更新資料動作及判斷
function update_data_action($member_grade_id, $table_column_name, $updata_value)
{
  if ($updata_value == '') {
    echo "<script>alert('请填入要修改的值。');</script>";
    echo '<script>location.reload();</script>';
  } else {
    $member_grade_update_sql_result = update_sql($member_grade_id, $table_column_name, $updata_value);
//    var_dump($deposit_onlinepayment_update_sql_result);

    // 更新欄位如果是註冊送彩金.連續上線優惠.首次儲值優惠.儲值優惠需要重新整理
    // 以上功能是關閉的話, 其底下的功能要禁用
    if ($member_grade_update_sql_result == '0') {
      echo "<script>alert('修改失敗。');</script>";
      echo '<script>location.reload();</script>';
    } elseif ($table_column_name == 'registrationmoney_enable' OR $table_column_name == 'continuous_checkin_enable' OR $table_column_name == 'first_add_value_enable' OR $table_column_name == 'add_value_enable') {
      echo '<script>location.reload();</script>';
    }
  }
}


// 使用者只能輸入整數或小數以下兩位浮點數
function value_is_float($value)
{
  if (preg_match("/^[0-9]+(.[0-9]{1,2})?$/", $value)) {
    $result = '1';
  } else {
    $result = '0';
  }
  return $result;
}

// 使用者只能輸入整數
function value_is_integer($value)
{
  if (preg_match("/^[0-9]*[1-9][0-9]*$/", $value)) {
    $result = '1';
  } else {
    $result = '0';
  }
  return $result;
}

// 驗證上下限額是否正確
function check_upper_lower($upper, $lower, $text) {
  if ($upper != '' AND $lower != '') {
    if (value_is_integer($upper) == '1' AND value_is_integer($lower) == '1') {
      if ($upper > $lower) {
        $upper_and_lower['upper'] = filter_var($upper, FILTER_SANITIZE_NUMBER_INT);
        $upper_and_lower['lower'] = filter_var($lower, FILTER_SANITIZE_NUMBER_INT);

      } else {
        echo "<script>alert('".$text."限额上限不可小于下限，请重新输入。');</script>";
        die();
      }

    } else {
      echo "<script>alert('输入格式错误，".$text."上下限额只可输入正整数。');</script>";
      // echo '<script>location.reload();</script>';
      die();
    }

  } else {
    echo "<script>alert('请输入正确".$text."上下限额。');</script>";
    die();
  }

  return $upper_and_lower;
}

// 驗證可輸入浮點數的欄位資料是否正確
function check_integer_data($integer_data, $text) {
  if ($integer_data != '') {
    if (value_is_integer($integer_data) == '1') {
      $data = filter_var($integer_data, FILTER_SANITIZE_NUMBER_INT);

    } else {
      echo "<script>alert('输入格式错误，".$text."只可输入正整数。');</script>";
      // echo '<script>location.reload();</script>';
      die();
    }

  } else {
    echo "<script>alert('请输入正确".$text."。');</script>";
    die();
  }

  return $data;
}

// 驗證只可輸整數的欄位資料是否正確
function check_float_data($float_data, $text) {
  if ($float_data != '') {
    if (value_is_float($float_data) == '1') {
      $data = filter_var($float_data, FILTER_VALIDATE_FLOAT);

    } else {
      echo "<script>alert('输入格式错误，".$text."只可输入正整数或小数以下两位正浮点数。');</script>";
      // echo '<script>location.reload();</script>';
      die();
    }

  } else {
    echo "<script>alert('请输入正确".$text."。');</script>";
    die();
  }

  return $data;
}

//抓取root_member_grade->grade原始(未修改前)data
function get_origin_grade_value($id){
  $check_sql = "SELECT gradename as gradename FROM root_member_grade WHERE id='".$id."';";//抓出未修改前的gradename
  $check_sql_result = runSQLall($check_sql);
  $origin_gradename = $check_sql_result[1]->gradename;

  return $origin_gradename;
}

//更新root_deposit_company裡相關資訊
function update_deposit_company($origin_gradename, $new_gradename){
  $deposit_company_sql = "SELECT * FROM root_deposit_company ORDER BY id;";//抓出公司入款帳戶資料
  $deposit_company_sql_result = runSQLall($deposit_company_sql);
  $deposit_company_grade_array = array();//init

  //比對,找出grade欄位包含此修改名稱值的公司帳戶以進行更新
  for($i=1;$i<=$deposit_company_sql_result[0];$i++){
    $deposit_company_grade_array = json_decode($deposit_company_sql_result[$i]->grade, true);//轉換成array做處理

    //判定 修改前之grade值是否存在array index裡,true->更新資料 false->不處理並跳過  
    //因不想改變array內的排序所以採用以下作法
    if(is_array($deposit_company_grade_array) && array_key_exists($origin_gradename, $deposit_company_grade_array) && !is_null($deposit_company_grade_array) ){
      //取出index並存成array
      $deposit_company_grade_array_index = array_keys($deposit_company_grade_array);

      //將修改後的index 存入 同位置未修改前的index
      $deposit_company_grade_array_index[array_search($origin_gradename, $deposit_company_grade_array_index)] = $new_gradename;

      //將Index與其對應value結合
      $deposit_company_grade_array = array_combine($deposit_company_grade_array_index, $deposit_company_grade_array);

      //轉json並防止亂碼
      $update_value = json_encode($deposit_company_grade_array, JSON_UNESCAPED_UNICODE);

      //更新資料 SQL
      $update_sql = "UPDATE root_deposit_company SET grade='".$update_value."' WHERE id='".$deposit_company_sql_result[$i]->id."';";
      $update_sql_result = runSQL($update_sql);
      if(!$update_sql_result){
        echo "<script>alert('公司存款帐户管理> 服务名称: ".$deposit_company_sql_result[$i]->companyname." 会员等级: ".$old_gradename." 更新失败');</script>";
        die();
      }
    }
  }
}

if($action == 'edit_member_grade_data' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {
// var_dump($_POST);

  $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

  // 一般設定
  $gradename = filter_var($_POST['gradename'], FILTER_SANITIZE_STRING);
  $grade_alert_status = filter_var($_POST['grade_alert_status'], FILTER_SANITIZE_STRING);
  $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
  $deposit_rate = filter_var($_POST['deposit_rate'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['deposit_rate'], FILTER_SANITIZE_STRING), '现金转游戏币存款稽核比') : NULL;
  $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);


  // 存款設定
  $deposit_allow = filter_var($_POST['deposit_allow'], FILTER_SANITIZE_STRING);
  $depositlimits_upper = filter_var($_POST['depositlimits_upper'], FILTER_SANITIZE_STRING);
  $depositlimits_lower = filter_var($_POST['depositlimits_lower'], FILTER_SANITIZE_STRING);

  if ($depositlimits_upper != '' AND $depositlimits_lower != '') {
    $text = '公司存款储值';
    $depositlimits_upper_lower = check_upper_lower($depositlimits_upper, $depositlimits_lower, $text);
    $depositlimits_upper = $depositlimits_upper_lower['upper'];
    $depositlimits_lower = $depositlimits_upper_lower['lower'];
  } else {
    $depositlimits_upper = NULL;
    $depositlimits_lower = NULL;
  }

  if ($deposit_allow == '1') {
    if ($depositlimits_upper == NULL OR $depositlimits_lower == NULL) {
      echo "<script>alert('公司存款储值为启用状态，上下限额不可为空。');</script>";
      die();
    }
  }

  $onlinepayment_allow = filter_var($_POST['onlinepayment_allow'] ?? '', FILTER_SANITIZE_STRING);
  $onlinepaymentlimits_upper = filter_var($_POST['onlinepaymentlimits_upper'] ?? '', FILTER_SANITIZE_STRING);
  $onlinepaymentlimits_lower = filter_var($_POST['onlinepaymentlimits_lower'] ?? '', FILTER_SANITIZE_STRING);

  if ($onlinepaymentlimits_upper != '' AND $onlinepaymentlimits_lower != '') {
    $text = '线上支付储值';
    $onlinepaymentlimits_upper_lower = check_upper_lower($onlinepaymentlimits_upper, $onlinepaymentlimits_lower, $text);
    $onlinepaymentlimits_upper = $onlinepaymentlimits_upper_lower['upper'];
    $onlinepaymentlimits_lower = $onlinepaymentlimits_upper_lower['lower'];
  } else {
    $onlinepaymentlimits_upper = NULL;
    $onlinepaymentlimits_lower = NULL;
  }

  if ($onlinepayment_allow == '1') {
    if ($onlinepaymentlimits_upper == NULL OR $onlinepaymentlimits_lower == NULL) {
      echo "<script>alert('线上支付储值为启用状态，上下限额不可为空。');</script>";
      die();
    }
  }

  // $pointcard_allow = filter_var($_POST['pointcard_allow'], FILTER_SANITIZE_STRING);
  // $pointcard_limits_upper = filter_var($_POST['pointcard_limits_upper'], FILTER_SANITIZE_STRING);
  // $pointcard_limits_lower = filter_var($_POST['pointcard_limits_lower'], FILTER_SANITIZE_STRING);

  // if ($pointcard_limits_upper != '' AND $pointcard_limits_lower != '') {
  //   $text = '線上支付儲值';
  //   $pointcard_limits_upper_lower = check_upper_lower($pointcard_limits_upper, $pointcard_limits_lower, $text);
  //   $pointcard_limits_upper = $pointcard_limits_upper_lower['upper'];
  //   $pointcard_limits_lower = $pointcard_limits_upper_lower['lower'];
  // } else {
  //   $pointcard_limits_upper = NULL;
  //   $pointcard_limits_lower = NULL;
  // }

  // if ($pointcard_allow == '1') {
  //   if ($pointcard_limits_upper == NULL OR $pointcard_limits_lower == NULL) {
  //     echo "<script>alert('點卡支付儲值為啟用狀態，上下限額不可為空。');</script>";
  //     die();
  //   }
  // }

  // $pointcardfee_member_rate_enable = filter_var($_POST['pointcardfee_member_rate_enable'], FILTER_SANITIZE_STRING);
  // $pointcardfee_member_rate = filter_var($_POST['pointcardfee_member_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['pointcardfee_member_rate'], FILTER_SANITIZE_STRING), '點卡支付手續費用比例') : NULL;

  // if ($pointcardfee_member_rate != '') {
  //   $text = '點卡支付手續費用比例';
  //   $pointcardfee_member_rate = check_float_data($pointcardfee_member_rate, $text);
  // } else {
  //   $pointcardfee_member_rate = NULL;
  // }

  // if ($pointcardfee_member_rate_enable == '1') {
  //   if ($pointcardfee_member_rate == NULL) {
  //     echo "<script>alert('點卡支付手續費為啟用狀態，費用比例不可為空。');</script>";
  //     die();
  //   }
  // }

  $apifastpay_allow = filter_var($_POST['apifastpay_allow'], FILTER_SANITIZE_STRING);
  $apifastpaylimits_upper = filter_var($_POST['apifastpaylimits_upper'], FILTER_SANITIZE_STRING);
  $apifastpaylimits_lower = filter_var($_POST['apifastpaylimits_lower'], FILTER_SANITIZE_STRING);

  if ($apifastpaylimits_upper != '' AND $apifastpaylimits_lower != '') {
    $text = '线上支付储值';
    $apifastpaylimits_upper_lower = check_upper_lower($apifastpaylimits_upper, $apifastpaylimits_lower, $text);
    $apifastpaylimits_upper = $apifastpaylimits_upper_lower['upper'];
    $apifastpaylimits_lower = $apifastpaylimits_upper_lower['lower'];
  } else {
    $apifastpaylimits_upper = NULL;
    $apifastpaylimits_lower = NULL;
  }

  if ($apifastpay_allow == '1') {
    if ($apifastpaylimits_upper == NULL OR $apifastpaylimits_lower == NULL) {
      echo "<script>alert('线上支付储值为启用状态，上下限额不可为空。');</script>";
      die();
    }
  }
  // 原點卡手續費移作他用: 線上入款手續費
  $pointcardfee_member_rate = filter_var($_POST['apifastpayfee_member_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['apifastpayfee_member_rate'], FILTER_SANITIZE_STRING), '線上存款手續費用比例') : NULL;

  if ($pointcardfee_member_rate != '') {
    $text = '線上存款手續費用比例';
    $pointcardfee_member_rate = check_float_data($pointcardfee_member_rate, $text);
  } else {
    $pointcardfee_member_rate = NULL;
  }

  // 取款設定
  $withdrawallimits_cash_upper = filter_var($_POST['withdrawallimits_cash_upper'], FILTER_SANITIZE_STRING);
  $withdrawallimits_cash_lower = filter_var($_POST['withdrawallimits_cash_lower'], FILTER_SANITIZE_STRING);

  if ($withdrawallimits_cash_upper != '' AND $withdrawallimits_cash_lower != '') {
    $text = '现金取款';
    $withdrawallimits_cash_upper_lower = check_upper_lower($withdrawallimits_cash_upper, $withdrawallimits_cash_lower, $text);
    $withdrawallimits_cash_upper = $withdrawallimits_cash_upper_lower['upper'];
    $withdrawallimits_cash_lower = $withdrawallimits_cash_upper_lower['lower'];
  } else {
    $withdrawallimits_cash_upper = NULL;
    $withdrawallimits_cash_lower = NULL;
  }

  $withdrawalcash_allow = filter_var($_POST['withdrawalcash_allow'], FILTER_SANITIZE_STRING);
  $withdrawalfee_cash = filter_var($_POST['withdrawalfee_cash'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['withdrawalfee_cash'], FILTER_SANITIZE_STRING), '现金取款手续费') : NULL;
  $withdrawalfee_max_cash = filter_var($_POST['withdrawalfee_max_cash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_max_cash'], FILTER_SANITIZE_STRING), '现金取款手续费上限') : NULL;
  $withdrawalfee_method_cash = filter_var($_POST['withdrawalfee_method_cash'], FILTER_SANITIZE_STRING);
  $withdrawalfee_free_hour_cash = NULL;
  $withdrawalfee_free_times_cash = NULL;

  // $withdrawalfee_free_hour_cash = filter_var($_POST['withdrawalfee_free_hour_cash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_hour_cash'], FILTER_SANITIZE_STRING), '现金取款时间') : NULL;
  // $withdrawalfee_free_times_cash = filter_var($_POST['withdrawalfee_free_times_cash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_times_cash'], FILTER_SANITIZE_STRING), '现金取款次数') : NULL;
  if ($withdrawalcash_allow == '1') {
    if ($withdrawallimits_cash_upper == NULL OR $withdrawallimits_cash_lower == NULL OR $withdrawalfee_cash == NULL OR $withdrawalfee_max_cash == NULL) {
      echo "<script>alert('现金取款为启用状态，上下限额、手续费及手续费上限不可为空。');</script>";
      die();
    } elseif ($withdrawalfee_method_cash == '2') {
      // if ($withdrawalfee_free_hour_cash == NULL OR $withdrawalfee_free_times_cash == NULL) {
      //   echo "<script>alert('现金取款手续费收取选择为 X 小时内取款 Y 次免收，请填入正确时间与次数。');</script>";
      //   die();
      // }
    }
  }
  
  $withdrawallimits_upper = filter_var($_POST['withdrawallimits_upper'], FILTER_SANITIZE_STRING);
  $withdrawallimits_lower = filter_var($_POST['withdrawallimits_lower'], FILTER_SANITIZE_STRING);

  if ($withdrawallimits_upper != '' AND $withdrawallimits_lower != '') {
    $text = '游戏币取款';
    $withdrawallimits_upper_lower = check_upper_lower($withdrawallimits_upper, $withdrawallimits_lower, $text);
    $withdrawallimits_upper = $withdrawallimits_upper_lower['upper'];
    $withdrawallimits_lower = $withdrawallimits_upper_lower['lower'];
  } else {
    $withdrawallimits_upper = NULL;
    $withdrawallimits_lower = NULL;
  }

  $withdrawal_allow = filter_var($_POST['withdrawal_allow'], FILTER_SANITIZE_STRING);
  $withdrawalfee = filter_var($_POST['withdrawalfee'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['withdrawalfee'], FILTER_SANITIZE_STRING), '游戏币取款手续费') : NULL;
  $withdrawalfee_max = filter_var($_POST['withdrawalfee_max'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_max'], FILTER_SANITIZE_STRING), '游戏币取款手续费上限') : NULL;
  $withdrawalfee_method = filter_var($_POST['withdrawalfee_method'], FILTER_SANITIZE_STRING);
  $withdrawalfee_free_hour = NULL;
  $withdrawalfee_free_times = NULL;

  // $withdrawalfee_free_hour = filter_var($_POST['withdrawalfee_free_hour'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_hour'], FILTER_SANITIZE_STRING), '游戏币取款时间') : NULL;
  // $withdrawalfee_free_times = filter_var($_POST['withdrawalfee_free_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_times'], FILTER_SANITIZE_STRING), '游戏币取款次数') : NULL;
  if ($withdrawal_allow == '1') {
    if ($withdrawallimits_upper == NULL OR $withdrawallimits_lower == NULL OR $withdrawalfee == NULL OR $withdrawalfee_max == NULL) {
      echo "<script>alert('游戏币取款为启用状态，上下限额、手续费及手续费上限不可为空。');</script>";
      die();
    } elseif ($withdrawalfee_method == '2') {
      // if ($withdrawalfee_free_hour == NULL OR $withdrawalfee_free_times == NULL) {
      //   echo "<script>alert('游戏币取款手续费收取选择为 X 小时内取款 Y 次免收，请填入正确时间与次数。');</script>";
      //   die();
      // }
    }
  }
  
  $withdrawal_limitstime_gcash = filter_var($_POST['withdrawal_limitstime_gcash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawal_limitstime_gcash'], FILTER_SANITIZE_STRING), '现金取款限制帐号时间') : NULL;
  $withdrawal_limitstime_gtoken = filter_var($_POST['withdrawal_limitstime_gtoken'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawal_limitstime_gtoken'], FILTER_SANITIZE_STRING), '游戏币取款限制帐号时间') : NULL;
  $administrative_cost_ratio = filter_var($_POST['administrative_cost_ratio'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['administrative_cost_ratio'], FILTER_SANITIZE_STRING), '+') : NULL;


  // 優惠設定
  // $activity_first_deposit_enable = filter_var($_POST['activity_first_deposit_enable'], FILTER_SANITIZE_STRING);
  // $activity_first_deposit_amount = filter_var($_POST['activity_first_deposit_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_deposit_amount'], FILTER_SANITIZE_STRING), '首次儲值公司入款優惠存款金額') : NULL;
  // $activity_first_deposit_rate = filter_var($_POST['activity_first_deposit_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_deposit_rate'], FILTER_SANITIZE_STRING), '首次儲值公司入款優惠比例') : NULL;
  // $activity_first_deposit_times = filter_var($_POST['activity_first_deposit_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_deposit_times'], FILTER_SANITIZE_STRING), '首次儲值公司入款優惠稽核倍數') : NULL;
  // $activity_first_deposit_upper = filter_var($_POST['activity_first_deposit_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_deposit_upper'], FILTER_SANITIZE_STRING), '首次儲值公司入款優惠上限') : NULL;

  // if ($activity_first_deposit_enable == '1') {
  //   if ($activity_first_deposit_amount == NULL OR $activity_first_deposit_rate == NULL OR $activity_first_deposit_times == NULL OR $activity_first_deposit_upper == NULL) {
  //     echo "<script>alert('首次儲值公司入款優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。');</script>";
  //     die();
  //   }
  // }

  // $activity_first_onlinepayment_enable = filter_var($_POST['activity_first_onlinepayment_enable'], FILTER_SANITIZE_STRING);
  // $activity_first_onlinepayment_amount = filter_var($_POST['activity_first_onlinepayment_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_onlinepayment_amount'], FILTER_SANITIZE_STRING), '首次儲值線上支付優惠存款金額') : NULL;
  // $activity_first_onlinepayment_rate = filter_var($_POST['activity_first_onlinepayment_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_onlinepayment_rate'], FILTER_SANITIZE_STRING), '首次儲值線上支付優惠比例') : NULL;
  // $activity_first_onlinepayment_times = filter_var($_POST['activity_first_onlinepayment_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_onlinepayment_times'], FILTER_SANITIZE_STRING), '首次儲值線上支付優惠稽核倍數') : NULL;
  // $activity_first_onlinepayment_upper = filter_var($_POST['activity_first_onlinepayment_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_onlinepayment_upper'], FILTER_SANITIZE_STRING), '首次儲值線上支付優惠上限') : NULL;

  // if ($activity_first_onlinepayment_enable == '1') {
  //   if ($activity_first_onlinepayment_amount == NULL OR $activity_first_onlinepayment_rate == NULL OR $activity_first_onlinepayment_times == NULL OR $activity_first_onlinepayment_upper == NULL) {
  //     echo "<script>alert('首次儲值線上支付優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。');</script>";
  //     die();
  //   }
  // }

  // $activity_deposit_preferential_enable = filter_var($_POST['activity_deposit_preferential_enable'], FILTER_SANITIZE_STRING);
  // $activity_deposit_preferential_amount = filter_var($_POST['activity_deposit_preferential_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_deposit_preferential_amount'], FILTER_SANITIZE_STRING), '公司入款優惠存款金額') : NULL;
  // $activity_deposit_preferential_rate = filter_var($_POST['activity_deposit_preferential_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_deposit_preferential_rate'], FILTER_SANITIZE_STRING), '公司入款優惠比例') : NULL;
  // $activity_deposit_preferential_times = filter_var($_POST['activity_deposit_preferential_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_deposit_preferential_times'], FILTER_SANITIZE_STRING), '公司入款優惠稽核倍數') : NULL;
  // $activity_deposit_preferential_upper = filter_var($_POST['activity_deposit_preferential_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_deposit_preferential_upper'], FILTER_SANITIZE_STRING), '公司入款優惠上限') : NULL;

  // if ($activity_deposit_preferential_enable == '1') {
  //   if ($activity_deposit_preferential_amount == NULL OR $activity_deposit_preferential_rate == NULL OR $activity_deposit_preferential_times == NULL OR $activity_deposit_preferential_upper == NULL) {
  //     echo "<script>alert('公司入款優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。');</script>";
  //     die();
  //   }
  // }

  // $activity_onlinepayment_preferential_enable = filter_var($_POST['activity_onlinepayment_preferential_enable'], FILTER_SANITIZE_STRING);
  // $activity_onlinepayment_preferential_amount = filter_var($_POST['activity_onlinepayment_preferential_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_onlinepayment_preferential_amount'], FILTER_SANITIZE_STRING), '線上支付優惠存款金額') : NULL;
  // $activity_onlinepayment_preferential_rate = filter_var($_POST['activity_onlinepayment_preferential_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_onlinepayment_preferential_rate'], FILTER_SANITIZE_STRING), '線上支付優惠比例') : NULL;
  // $activity_onlinepayment_preferential_times = filter_var($_POST['activity_onlinepayment_preferential_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_onlinepayment_preferential_times'], FILTER_SANITIZE_STRING), '線上支付優惠稽核倍數') : NULL;
  // $activity_onlinepayment_preferential_upper = filter_var($_POST['activity_onlinepayment_preferential_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_onlinepayment_preferential_upper'], FILTER_SANITIZE_STRING), '線上支付優惠上限') : NULL;

  // if ($activity_onlinepayment_preferential_enable == '1') {
  //   if ($activity_onlinepayment_preferential_amount == NULL OR $activity_onlinepayment_preferential_rate == NULL OR $activity_onlinepayment_preferential_times == NULL OR $activity_onlinepayment_preferential_upper == NULL) {
  //     echo "<script>alert('線上支付優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。');</script>";
  //     die();
  //   }
  // }

  $activity_register_preferential_enable = filter_var($_POST['activity_register_preferential_enable'], FILTER_SANITIZE_STRING);
  $activity_register_preferential_adminadd = filter_var($_POST['activity_register_preferential_adminadd'], FILTER_SANITIZE_STRING);
  //$activity_register_preferential_amount = filter_var($_POST['activity_register_preferential_amount'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_register_preferential_amount'], FILTER_SANITIZE_STRING), '注册送彩金赠送金额') : NULL;
  //$activity_register_preferential_audited = filter_var($_POST['activity_register_preferential_audited'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_register_preferential_audited'], FILTER_SANITIZE_STRING), '注册送彩金稽核金额') : NULL;

  // if ($activity_register_preferential_enable == '1' OR $activity_register_preferential_adminadd == '1') {
  //   if ($activity_register_preferential_amount == NULL OR $activity_register_preferential_audited == NULL) {
  //     echo "<script>alert('注册送彩金或管端新增为启用状态，赠送金额及稽核金额不可为空。');</script>";
  //     die();
  //   }
  // }

  // $activity_daily_checkin_enable = filter_var($_POST['activity_daily_checkin_enable'], FILTER_SANITIZE_STRING);
  // $activity_daily_checkin_days = filter_var($_POST['activity_daily_checkin_days'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_daily_checkin_days'], FILTER_SANITIZE_STRING), '連續上線優惠天數') : NULL;
  // $activity_daily_checkin_amount = filter_var($_POST['activity_daily_checkin_amount'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_daily_checkin_amount'], FILTER_SANITIZE_STRING), '連續上線優惠贈送金額') : NULL;
  // $activity_daily_checkin_rate = filter_var($_POST['activity_daily_checkin_rate'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_daily_checkin_rate'], FILTER_SANITIZE_STRING), '連續上線優惠稽核倍數') : NULL;

  // if ($activity_daily_checkin_enable == '1') {
  //   if ($activity_daily_checkin_days == NULL OR $activity_daily_checkin_amount == NULL OR $activity_daily_checkin_rate == NULL) {
  //     echo "<script>alert('連續上線優惠為啟用狀態，天數、贈送金額及稽核倍數不可為空。');</script>";
  //     die();
  //   }
  // }


  // $activity_first_deposit_array = array(
  //   'activity_first_deposit_enable' => $activity_first_deposit_enable,
  //   'activity_first_deposit_amount' => $activity_first_deposit_amount,
  //   'activity_first_deposit_rate' => $activity_first_deposit_rate,
  //   'activity_first_deposit_times' => $activity_first_deposit_times,
  //   'activity_first_deposit_upper' => $activity_first_deposit_upper
  // );

  // $activity_first_deposit = json_encode($activity_first_deposit_array);

  // $activity_first_onlinepayment_array = array(
  //   'activity_first_onlinepayment_enable' => $activity_first_onlinepayment_enable,
  //   'activity_first_onlinepayment_amount' => $activity_first_onlinepayment_amount,
  //   'activity_first_onlinepayment_rate' => $activity_first_onlinepayment_rate,
  //   'activity_first_onlinepayment_times' => $activity_first_onlinepayment_times,
  //   'activity_first_onlinepayment_upper' => $activity_first_onlinepayment_upper
  // );

  // $activity_first_onlinepayment = json_encode($activity_first_onlinepayment_array);

  // $activity_deposit_preferential_array = array(
  //   'activity_deposit_preferential_enable' => $activity_deposit_preferential_enable,
  //   'activity_deposit_preferential_amount' => $activity_deposit_preferential_amount,
  //   'activity_deposit_preferential_rate' => $activity_deposit_preferential_rate,
  //   'activity_deposit_preferential_times' => $activity_deposit_preferential_times,
  //   'activity_deposit_preferential_upper' => $activity_deposit_preferential_upper
  // );

  // $activity_deposit_preferential = json_encode($activity_deposit_preferential_array);

  // $activity_onlinepayment_preferential_array = array(
  //   'activity_onlinepayment_preferential_enable' => $activity_onlinepayment_preferential_enable,
  //   'activity_onlinepayment_preferential_amount' => $activity_onlinepayment_preferential_amount,
  //   'activity_onlinepayment_preferential_rate' => $activity_onlinepayment_preferential_rate,
  //   'activity_onlinepayment_preferential_times' => $activity_onlinepayment_preferential_times,
  //   'activity_onlinepayment_preferential_upper' => $activity_onlinepayment_preferential_upper
  // );

  // $activity_onlinepayment_preferential = json_encode($activity_onlinepayment_preferential_array);

  $activity_register_preferential_array = array(
    'activity_register_preferential_enable' => $activity_register_preferential_enable,
    'activity_register_preferential_adminadd' => $activity_register_preferential_adminadd,
    //'activity_register_preferential_amount' => $activity_register_preferential_amount,
    //'activity_register_preferential_audited' => $activity_register_preferential_audited
  );

  $activity_register_preferential = json_encode($activity_register_preferential_array);

  // $activity_daily_checkin_array = array(
  //   'activity_daily_checkin_enable' => $activity_daily_checkin_enable,
  //   'activity_daily_checkin_days' => $activity_daily_checkin_days,
  //   'activity_daily_checkin_amount' => $activity_daily_checkin_amount,
  //   'activity_daily_checkin_rate' => $activity_daily_checkin_rate
  // );

  // $activity_daily_checkin = json_encode($activity_daily_checkin_array);


  if ($status == '1') {
    if ($gradename == NULL OR $deposit_rate == NULL OR $withdrawal_limitstime_gcash == NULL OR $withdrawal_limitstime_gtoken == NULL OR $administrative_cost_ratio == NULL) {
      echo "<script>alert('等级状态为启用状态，除备注栏位外其余栏位皆不可为空。');</script>";
      die();
    }
  }

  // $column_list = [
  //   'gradename', 'grade_alert_status', 'status', 'deposit_rate', 'notes',
  //   'deposit_allow', 'depositlimits_upper', 'depositlimits_lower', 'onlinepayment_allow', 'onlinepaymentlimits_upper',
  //   'onlinepaymentlimits_lower', 'pointcard_allow', 'pointcard_limits_upper', 'pointcard_limits_lower', 'pointcardfee_member_rate_enable',
  //   'pointcardfee_member_rate', 'withdrawallimits_cash_upper', 'withdrawallimits_cash_lower', 'withdrawalcash_allow', 'withdrawalfee_cash',
  //   'withdrawalfee_max_cash', 'withdrawalfee_method_cash', 'withdrawalfee_free_hour_cash', 'withdrawalfee_free_times_cash', 'withdrawallimits_upper',
  //   'withdrawallimits_lower', 'withdrawal_allow', 'withdrawalfee', 'withdrawalfee_max', 'withdrawalfee_method',
  //   'withdrawalfee_free_hour', 'withdrawalfee_free_times', 'withdrawal_limitstime_gcash', 'withdrawal_limitstime_gtoken', 'administrative_cost_ratio',
  //   'activity_first_deposit', 'activity_first_onlinepayment', 'activity_deposit_preferential', 'activity_onlinepayment_preferential', 'activity_register_preferential',
  //   'activity_daily_checkin'
  // ];
  $column_list = [
    'gradename', 'grade_alert_status', 'status', 'deposit_rate', 'notes',
    'deposit_allow', 'depositlimits_upper', 'depositlimits_lower',
    // 舊線上支付
    // 'onlinepayment_allow', 'onlinepaymentlimits_upper', 'onlinepaymentlimits_lower',
    // onlinepay 線上支付
    'apifastpay_allow', 'apifastpaylimits_upper', 'apifastpaylimits_lower', 'pointcardfee_member_rate',
    'withdrawallimits_cash_upper', 'withdrawallimits_cash_lower', 'withdrawalcash_allow', 'withdrawalfee_cash',
    'withdrawalfee_max_cash', 'withdrawalfee_method_cash', 'withdrawalfee_free_hour_cash', 'withdrawalfee_free_times_cash', 'withdrawallimits_upper',
    'withdrawallimits_lower', 'withdrawal_allow', 'withdrawalfee', 'withdrawalfee_max', 'withdrawalfee_method',
    'withdrawalfee_free_hour', 'withdrawalfee_free_times', 'withdrawal_limitstime_gcash', 'withdrawal_limitstime_gtoken', 'administrative_cost_ratio',
    'activity_register_preferential'
  ];
  // $column_list_sql = "SELECT column_name FROM information_schema.columns WHERE table_schema = '".$pdo['user']."' AND table_name = 'root_member_grade';";
  // var_dump($column_list_sql);
  // $column_list runSQLall($column_list_sql);

  $origin_gradename = get_origin_grade_value($id);//抓取root_member_grade->grade原始(未修改前)data

  // 組合 inster sql values
  $sql_value = '';
  foreach ($column_list as $key => $value) {
    if (${$value} != NULL) {
      $sql_value = $sql_value."".$value." = '".${$value}."',";
    } else {
      $sql_value = $sql_value."".$value."= NULL,";
    }
  }

  // 去除最後一個逗號
  // $sql_value = substr($sql_value,0,-1);
  $sql_value = substr($sql_value,0,strlen(',')*-1);
  // echo $sql_value;

  $update_sql = "UPDATE root_member_grade SET ".$sql_value." WHERE id = '$id';";
  // echo $update_sql;
  $update_sql_result = runSQL($update_sql);

  if ($update_sql_result) {
    echo "<script>alert('会员等级更新成功。');location.href = './member_grade_config.php';</script>";
    update_deposit_company($origin_gradename, $gradename);
  } else {
    echo "<script>alert('会员等级更新失败。');</script>";
  }

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}
