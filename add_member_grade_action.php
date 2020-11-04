<?php
// ----------------------------------------------------------------------------
// Features:	後台-- 會員等級管理-新增會員等級
// File Name:	add_member_grade_action.php
// Author:		Neil
// Related:   服務 add_member_grade.php
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

} else {
  die($tr['Illegal test']);
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
  global $tr;
  if ($upper != '' AND $lower != '') {
    if (value_is_integer($upper) == '1' AND value_is_integer($lower) == '1') {
      if ($upper > $lower) {
        $upper_and_lower['upper'] = filter_var($upper, FILTER_SANITIZE_NUMBER_INT);
        $upper_and_lower['lower'] = filter_var($lower, FILTER_SANITIZE_NUMBER_INT);

      } else {
        echo "<script>alert('".$text.$tr['The upper limit can not be less than the lower limit, please re-enter.']."');</script>";
        die();
      }

    } else {
      echo "<script>alert('".$tr['Input format error,'].$text."');</script>";
      die();
    }

  } else {
    echo "<script>alert('".$tr['Please enter the correct'].$text.$tr['Upper and lower limits.']."');</script>";
    die();
  }

  return $upper_and_lower;
}
// 驗證可輸入浮點數的欄位資料是否正確
function check_integer_data($integer_data, $text) {
  global $tr;
  if ($integer_data != '') {
    if (value_is_integer($integer_data) == '1') {
      $data = filter_var($integer_data, FILTER_SANITIZE_NUMBER_INT);

    } else {
      echo "<script>alert('".$tr['Input format error,'].$text.$tr['Only positive integers can be entered.']."');</script>";
      die();
    }

  } else {
    echo "<script>alert('".$tr['Please enter the correct'] .$text."。');</script>";
    die();
  }

  return $data;
}

// 驗證只可輸整數的欄位資料是否正確
function check_float_data($float_data, $text) {
  global $tr;
  if ($float_data != '') {
    if (value_is_float($float_data) == '1') {
      $data = filter_var($float_data, FILTER_VALIDATE_FLOAT);

    } else {
      echo "<script>alert('".$tr['Input format error,'].$text.$tr['Only positive or negative decimals can be entered for two positive floating points.']."');</script>";
      die();
    }

  } else {
    echo "<script>alert('".$tr['Please enter the correct'] .$text."。');</script>";
    die();
  }

  return $data;
}


if($action == 'add_member_grade_setting' AND isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R') {

  // 一般設定
  $gradename = filter_var($_POST['gradename'], FILTER_SANITIZE_STRING);

  if ($gradename != '') {
    /* 先檢查是否有這個會員等級存在，如果有就回應錯誤訊息，如果沒有不作任何事，程式繼續執行 */
    $exist_gradename = runSQL("SELECT gradename FROM root_member_grade WHERE gradename = '".$gradename."'");
    if (isset($exist_gradename) && $exist_gradename != 0) {
      echo "<script>alert('".$tr['Member level gradename repeated do nothing'] ."');</script>";
      die();
    } 
    

    // 一般設定
    $grade_alert_status = filter_var($_POST['grade_alert_status'], FILTER_SANITIZE_STRING);
    $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
    $deposit_rate = filter_var($_POST['deposit_rate'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['deposit_rate'], FILTER_SANITIZE_STRING), $tr['Franchise to cash deposit audit ratio']) : NULL;
    $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);


    // 存款設定
    $deposit_allow = filter_var($_POST['deposit_allow'], FILTER_SANITIZE_STRING);
    $depositlimits_upper = filter_var($_POST['depositlimits_upper'], FILTER_SANITIZE_STRING);
    $depositlimits_lower = filter_var($_POST['depositlimits_lower'], FILTER_SANITIZE_STRING);

    if ($depositlimits_upper != '' AND $depositlimits_lower != '') {
      $text = $tr['company deposit value'];
      $depositlimits_upper_lower = check_upper_lower($depositlimits_upper, $depositlimits_lower, $text);
      $depositlimits_upper = $depositlimits_upper_lower['upper'];
      $depositlimits_lower = $depositlimits_upper_lower['lower'];
    } else {
      $depositlimits_upper = NULL;
      $depositlimits_lower = NULL;
    }

    if ($deposit_allow == '1') {
      if ($depositlimits_upper == NULL OR $depositlimits_lower == NULL) {
        echo "<script>alert(".$tr['Company deposits stored value is enabled, the upper and lower limits can not be empty.'].");</script>";
        die();
      }
    }


    $apifastpay_allow = filter_var($_POST['apifastpay_allow'], FILTER_SANITIZE_STRING);
    $apifastpaylimits_upper = filter_var($_POST['apifastpaylimits_upper'], FILTER_SANITIZE_STRING);
    $apifastpaylimits_lower = filter_var($_POST['apifastpaylimits_lower'], FILTER_SANITIZE_STRING);
    $pointcardfee_member_rate = filter_var($_POST['apifastpayfee_member_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['apifastpayfee_member_rate'], FILTER_SANITIZE_STRING), $tr['Point card payment fees ratio']) : NULL;

    if ($apifastpaylimits_upper != '' AND $apifastpaylimits_lower != '') {
      $text = $tr['online payment stored value'];
      $apifastpaylimits_upper_lower = check_upper_lower($apifastpaylimits_upper, $apifastpaylimits_lower, $text);
      $apifastpaylimits_upper = $apifastpaylimits_upper_lower['upper'];
      $apifastpaylimits_lower = $apifastpaylimits_upper_lower['lower'];
    } else {
      $apifastpaylimits_upper = NULL;
      $apifastpaylimits_lower = NULL;
    }

    if ($apifastpay_allow == '1') {
      if ($apifastpaylimits_upper == NULL OR $apifastpaylimits_lower == NULL) {
        echo "<script>alert('".$tr['Online payment stored value is enabled, the upper and lower limits can not be empty.']."');</script>";
        die();
      }
    }

    if ($pointcardfee_member_rate != '') {
      $text = $tr['Point card payment fees ratio'];
      $pointcardfee_member_rate = check_float_data($pointcardfee_member_rate, $text);
    } else {
      $pointcardfee_member_rate = NULL;
    }

    // $pointcard_allow = filter_var($_POST['pointcard_allow'], FILTER_SANITIZE_STRING);
    // $pointcard_limits_upper = filter_var($_POST['pointcard_limits_upper'], FILTER_SANITIZE_STRING);
    // $pointcard_limits_lower = filter_var($_POST['pointcard_limits_lower'], FILTER_SANITIZE_STRING);

    // if ($pointcard_limits_upper != '' AND $pointcard_limits_lower != '') {
    //   // $tr['Point card payment stored value'] = '點卡支付儲值';
    //   $text = $tr['Point card payment stored value'];
    //   $pointcard_limits_upper_lower = check_upper_lower($pointcard_limits_upper, $pointcard_limits_lower, $text);
    //   $pointcard_limits_upper = $pointcard_limits_upper_lower['upper'];
    //   $pointcard_limits_lower = $pointcard_limits_upper_lower['lower'];
    // } else {
    //   $pointcard_limits_upper = NULL;
    //   $pointcard_limits_lower = NULL;
    // }

    // if ($pointcard_allow == '1') {
    //   if ($pointcard_limits_upper == NULL OR $pointcard_limits_lower == NULL) {
    //     // $tr['Point card payment stored value is enabled, the upper and lower limits can not be empty.'] = '點卡支付儲值為啟用狀態，上下限額不可為空。';
    //     echo "<script>alert('".$tr['Point card payment stored value is enabled, the upper and lower limits can not be empty.']."');</script>";
    //     die();
    //   }
    // }

    // $tr['Point card payment fees ratio'] = '點卡支付手續費用比例';
    // $pointcardfee_member_rate_enable = filter_var($_POST['pointcardfee_member_rate_enable'], FILTER_SANITIZE_STRING);
    // $pointcardfee_member_rate = filter_var($_POST['pointcardfee_member_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['pointcardfee_member_rate'], FILTER_SANITIZE_STRING), $tr['Point card payment fees ratio']) : NULL;

    // if ($pointcardfee_member_rate != '') {
    //   $text = $tr['Point card payment fees ratio'];
    //   $pointcardfee_member_rate = check_float_data($pointcardfee_member_rate, $text);
    // } else {
    //   $pointcardfee_member_rate = NULL;
    // }

    // if ($pointcardfee_member_rate_enable == '1') {
    //   if ($pointcardfee_member_rate == NULL) {
    //     $tr['Dianka payment fee is enabled, the proportion of the cost can not be empty.'] = '點卡支付手續費為啟用狀態，費用比例不可為空。';
    //     echo "<script>alert('". $tr['Dianka payment fee is enabled, the proportion of the cost can not be empty.']."');</script>";
    //     die();
    //   }
    // }



    // 取款設定
    $withdrawallimits_cash_upper = filter_var($_POST['withdrawallimits_cash_upper'], FILTER_SANITIZE_STRING);
    $withdrawallimits_cash_lower = filter_var($_POST['withdrawallimits_cash_lower'], FILTER_SANITIZE_STRING);

    if ($withdrawallimits_cash_upper != '' AND $withdrawallimits_cash_lower != '') {

      $text = $tr['Join the gold withdrawals'];
      $withdrawallimits_cash_upper_lower = check_upper_lower($withdrawallimits_cash_upper, $withdrawallimits_cash_lower, $text);
      $withdrawallimits_cash_upper = $withdrawallimits_cash_upper_lower['upper'];
      $withdrawallimits_cash_lower = $withdrawallimits_cash_upper_lower['lower'];
    } else {
      $withdrawallimits_cash_upper = NULL;
      $withdrawallimits_cash_lower = NULL;
    }

    $withdrawalcash_allow = filter_var($_POST['withdrawalcash_allow'], FILTER_SANITIZE_STRING);
    $withdrawalfee_cash = filter_var($_POST['withdrawalfee_cash'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['withdrawalfee_cash'], FILTER_SANITIZE_STRING), $tr['Franchise withdrawal fee']) : NULL;
    $withdrawalfee_max_cash = filter_var($_POST['withdrawalfee_max_cash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_max_cash'], FILTER_SANITIZE_STRING), $tr['Affiliate payment withdrawal fee limit'] ) : NULL;
    $withdrawalfee_method_cash = filter_var($_POST['withdrawalfee_method_cash'], FILTER_SANITIZE_STRING);
    $withdrawalfee_free_hour_cash = NULL;
    $withdrawalfee_free_times_cash = NULL;

    //$withdrawalfee_free_hour_cash = filter_var($_POST['withdrawalfee_free_hour_cash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_hour_cash'], FILTER_SANITIZE_STRING), $tr['Franchise withdrawal time']) : NULL;
    //$withdrawalfee_free_times_cash = filter_var($_POST['withdrawalfee_free_times_cash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_times_cash'], FILTER_SANITIZE_STRING), $tr['Union gold withdrawal number']) : NULL;
    if ($withdrawalcash_allow == '1') {
      if ($withdrawallimits_cash_upper == NULL OR $withdrawallimits_cash_lower == NULL OR $withdrawalfee_cash == NULL OR $withdrawalfee_max_cash == NULL) {
        echo "<script>alert('". $tr['Joined the gold withdrawals for the opening of the state, the upper and lower limits, fees and fees ceiling can not be empty.']."');</script>";
        die();
      } elseif ($withdrawalfee_method_cash == '2') {
        /*
        if ($withdrawalfee_free_hour_cash == NULL OR $withdrawalfee_free_times_cash == NULL) {
          echo "<script>alert('".$tr['Franchise withdrawal fee charged for the withdrawal of X hours within the withdrawal of Y, please enter the correct time and frequency.']."');</script>";
          die();
        }
        */
      }
    }
    
    $withdrawallimits_upper = filter_var($_POST['withdrawallimits_upper'], FILTER_SANITIZE_STRING);
    $withdrawallimits_lower = filter_var($_POST['withdrawallimits_lower'], FILTER_SANITIZE_STRING);

    if ($withdrawallimits_upper != '' AND $withdrawallimits_lower != '') {
      $text = $tr['Cash withdrawals'];
      $withdrawallimits_upper_lower = check_upper_lower($withdrawallimits_upper, $withdrawallimits_lower, $text);
      $withdrawallimits_upper = $withdrawallimits_upper_lower['upper'];
      $withdrawallimits_lower = $withdrawallimits_upper_lower['lower'];
    } else {
      $withdrawallimits_upper = NULL;
      $withdrawallimits_lower = NULL;
    }

    $withdrawal_allow = filter_var($_POST['withdrawal_allow'], FILTER_SANITIZE_STRING);
    $withdrawalfee = filter_var($_POST['withdrawalfee'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['withdrawalfee'], FILTER_SANITIZE_STRING),$tr['Cash withdrawal fee'] ) : NULL;
    $withdrawalfee_max = filter_var($_POST['withdrawalfee_max'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_max'], FILTER_SANITIZE_STRING),  $tr['Cash withdrawal fee limit']) : NULL;
    $withdrawalfee_method = filter_var($_POST['withdrawalfee_method'], FILTER_SANITIZE_STRING);
    $withdrawalfee_free_hour = NULL;
    $withdrawalfee_free_times = NULL;

    //$withdrawalfee_free_hour = filter_var($_POST['withdrawalfee_free_hour'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_hour'], FILTER_SANITIZE_STRING), $tr['Cash withdrawal time'] ) : NULL;
    //$withdrawalfee_free_times = filter_var($_POST['withdrawalfee_free_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawalfee_free_times'], FILTER_SANITIZE_STRING), $tr['Cash withdrawal count']) : NULL;
    if ($withdrawal_allow == '1') {
      if ($withdrawallimits_upper == NULL OR $withdrawallimits_lower == NULL OR $withdrawalfee == NULL OR $withdrawalfee_max == NULL) {
        echo "<script>alert('". $tr['Cash withdrawal is enabled, upper and lower limits, fees and handling fees ceiling is not empty.']."');</script>";
        die();
      } elseif ($withdrawalfee_method == '2') {
        /*
        if ($withdrawalfee_free_hour == NULL OR $withdrawalfee_free_times == NULL) {
          echo "<script>alert('".$tr['Cash withdrawal fee charged for the withdrawal of withdrawals within X hours Y free, please enter the correct time and frequency.']."');</script>";
          die();
        }
        */
      }
    }
    
    $withdrawal_limitstime_gcash = filter_var($_POST['withdrawal_limitstime_gcash'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawal_limitstime_gcash'], FILTER_SANITIZE_STRING), $tr['Joining the cash withdrawal limit account time']) : NULL;
    $withdrawal_limitstime_gtoken = filter_var($_POST['withdrawal_limitstime_gtoken'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['withdrawal_limitstime_gtoken'], FILTER_SANITIZE_STRING),  $tr['Cash withdrawal limit account time']) : NULL;
    $administrative_cost_ratio = filter_var($_POST['administrative_cost_ratio'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['administrative_cost_ratio'], FILTER_SANITIZE_STRING), $tr['Cash withdrawal auditing administrative expenses ratio']) : NULL;


    // 優惠設定
    // $tr['The first deposit-taking company deposit deposit amount'] = '首次儲值公司入款優惠存款金額';
    // $tr['The first time the value of the company deposit preferential ratio'] = '首次儲值公司入款優惠比例';
    // $tr['The first time the value of the company deposit preferential tax multiples'] = '首次儲值公司入款優惠稽核倍數';
    // $tr['The first deposit company deposit ceiling limit'] = '首次儲值公司入款優惠上限';
    // $activity_first_deposit_enable = filter_var($_POST['activity_first_deposit_enable'], FILTER_SANITIZE_STRING);
    // $activity_first_deposit_amount = filter_var($_POST['activity_first_deposit_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_deposit_amount'], FILTER_SANITIZE_STRING), $tr['The first deposit-taking company deposit deposit amount']) : NULL;
    // $activity_first_deposit_rate = filter_var($_POST['activity_first_deposit_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_deposit_rate'], FILTER_SANITIZE_STRING), $tr['The first time the value of the company deposit preferential ratio']) : NULL;
    // $activity_first_deposit_times = filter_var($_POST['activity_first_deposit_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_deposit_times'], FILTER_SANITIZE_STRING), $tr['The first time the value of the company deposit preferential tax multiples']) : NULL;
    // $activity_first_deposit_upper = filter_var($_POST['activity_first_deposit_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_deposit_upper'], FILTER_SANITIZE_STRING),  $tr['The first deposit company deposit ceiling limit']) : NULL;

    // $tr['The first deposit-taking company deposit offer is enabled, the deposit amount, the preferential ratio, the multiple of audit and the discount ceiling can not be empty.'] = '首次儲值公司入款優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。';
    // if ($activity_first_deposit_enable == '1') {
    //   if ($activity_first_deposit_amount == NULL OR $activity_first_deposit_rate == NULL OR $activity_first_deposit_times == NULL OR $activity_first_deposit_upper == NULL) {
    //     echo "<script>alert('".$tr['The first deposit-taking company deposit offer is enabled, the deposit amount, the preferential ratio, the multiple of audit and the discount ceiling can not be empty.']."');</script>";
    //     die();
    //   }
    // }

    // $tr['The first deposit line payment discount deposit amount'] = '首次儲值線上支付優惠存款金額';
    // $tr['The first stored value online payment discount ratio'] = '首次儲值線上支付優惠比例';
    // $tr['The first stored value online payment discount audit times'] = '首次儲值線上支付優惠稽核倍數';
    // $tr['The first deposit line payment discount ceiling'] = '首次儲值線上支付優惠上限';
    // $activity_first_onlinepayment_enable = filter_var($_POST['activity_first_onlinepayment_enable'], FILTER_SANITIZE_STRING);
    // $activity_first_onlinepayment_amount = filter_var($_POST['activity_first_onlinepayment_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_onlinepayment_amount'], FILTER_SANITIZE_STRING), $tr['The first deposit line payment discount deposit amount']) : NULL;
    // $activity_first_onlinepayment_rate = filter_var($_POST['activity_first_onlinepayment_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_first_onlinepayment_rate'], FILTER_SANITIZE_STRING),  $tr['The first stored value online payment discount ratio']) : NULL;
    // $activity_first_onlinepayment_times = filter_var($_POST['activity_first_onlinepayment_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_onlinepayment_times'], FILTER_SANITIZE_STRING), $tr['The first stored value online payment discount audit times']) : NULL;
    // $activity_first_onlinepayment_upper = filter_var($_POST['activity_first_onlinepayment_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_first_onlinepayment_upper'], FILTER_SANITIZE_STRING), $tr['The first deposit line payment discount ceiling']) : NULL;

    // $tr['The first time the stored value online payment is enabled, the deposit amount, the preferential ratio, the multiple of audit and the discount ceiling can not be empty.'] = '首次儲值線上支付優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。';
    // if ($activity_first_onlinepayment_enable == '1') {
    //   if ($activity_first_onlinepayment_amount == NULL OR $activity_first_onlinepayment_rate == NULL OR $activity_first_onlinepayment_times == NULL OR $activity_first_onlinepayment_upper == NULL) {
    //     echo "<script>alert('".$tr['The first time the stored value online payment is enabled, the deposit amount, the preferential ratio, the multiple of audit and the discount ceiling can not be empty.']."');</script>";
    //     die();
    //   }
    // }

    // $tr['Company deposit deposit amount'] = '公司入款優惠存款金額';
    // $tr['Company deposit preferential ratio'] = '公司入款優惠比例';
    // $tr['Company deposit discount multiple audit'] = '公司入款優惠稽核倍數';
    // $tr['Company deposit concessions ceiling'] = '公司入款優惠上限';
    // $activity_deposit_preferential_enable = filter_var($_POST['activity_deposit_preferential_enable'], FILTER_SANITIZE_STRING);
    // $activity_deposit_preferential_amount = filter_var($_POST['activity_deposit_preferential_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_deposit_preferential_amount'], FILTER_SANITIZE_STRING), $tr['Company deposit deposit amount']) : NULL;
    // $activity_deposit_preferential_rate = filter_var($_POST['activity_deposit_preferential_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_deposit_preferential_rate'], FILTER_SANITIZE_STRING), $tr['Company deposit preferential ratio']) : NULL;
    // $activity_deposit_preferential_times = filter_var($_POST['activity_deposit_preferential_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_deposit_preferential_times'], FILTER_SANITIZE_STRING), $tr['Company deposit discount multiple audit']) : NULL;
    // $activity_deposit_preferential_upper = filter_var($_POST['activity_deposit_preferential_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_deposit_preferential_upper'], FILTER_SANITIZE_STRING),  $tr['Company deposit concessions ceiling']) : NULL;

    // $tr['Company deposit offer is enabled, deposit amount, discount rate, multiple of audit and discount ceiling can not be empty.'] = '公司入款優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。';
    // if ($activity_deposit_preferential_enable == '1') {
    //   if ($activity_deposit_preferential_amount == NULL OR $activity_deposit_preferential_rate == NULL OR $activity_deposit_preferential_times == NULL OR $activity_deposit_preferential_upper == NULL) {
    //     echo "<script>alert('".$tr['Company deposit offer is enabled, deposit amount, discount rate, multiple of audit and discount ceiling can not be empty.']."');</script>";
    //     die();
    //   }
    // }

    // $tr['Online payment discount deposit amount'] = '線上支付優惠存款金額';
    // $tr['Online payment discount ratio'] = '線上支付優惠比例';
    // $tr['Online payment discount audit times'] = '線上支付優惠稽核倍數';
    // $tr['Online payment discount ceiling'] = '線上支付優惠上限';
    // $activity_onlinepayment_preferential_enable = filter_var($_POST['activity_onlinepayment_preferential_enable'], FILTER_SANITIZE_STRING);
    // $activity_onlinepayment_preferential_amount = filter_var($_POST['activity_onlinepayment_preferential_amount'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_onlinepayment_preferential_amount'], FILTER_SANITIZE_STRING), $tr['Online payment discount deposit amount']) : NULL;
    // $activity_onlinepayment_preferential_rate = filter_var($_POST['activity_onlinepayment_preferential_rate'], FILTER_SANITIZE_STRING) != '' ? check_float_data(filter_var($_POST['activity_onlinepayment_preferential_rate'], FILTER_SANITIZE_STRING), $tr['Online payment discount ratio']) : NULL;
    // $activity_onlinepayment_preferential_times = filter_var($_POST['activity_onlinepayment_preferential_times'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_onlinepayment_preferential_times'], FILTER_SANITIZE_STRING), $tr['Online payment discount audit times'] ) : NULL;
    // $activity_onlinepayment_preferential_upper = filter_var($_POST['activity_onlinepayment_preferential_upper'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_onlinepayment_preferential_upper'], FILTER_SANITIZE_STRING), $tr['Online payment discount ceiling'] ) : NULL;

    // $tr['Online payment offer is enabled, deposit amount, discount rate, audit multiples, and discount ceilings must not be left blank.'] = '線上支付優惠為啟用狀態，存款金額、優惠比例、稽核倍數及優惠上限不可為空。';
    // if ($activity_onlinepayment_preferential_enable == '1') {
    //   if ($activity_onlinepayment_preferential_amount == NULL OR $activity_onlinepayment_preferential_rate == NULL OR $activity_onlinepayment_preferential_times == NULL OR $activity_onlinepayment_preferential_upper == NULL) {
    //     echo "<script>alert('".$tr['Online payment offer is enabled, deposit amount, discount rate, audit multiples, and discount ceilings must not be left blank.']."');</script>";
    //     die();
    //   }
    // }

    $activity_register_preferential_enable = filter_var($_POST['activity_register_preferential_enable'], FILTER_SANITIZE_STRING);
    $activity_register_preferential_adminadd = filter_var($_POST['activity_register_preferential_adminadd'], FILTER_SANITIZE_STRING);
    //$activity_register_preferential_amount = filter_var($_POST['activity_register_preferential_amount'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_register_preferential_amount'], FILTER_SANITIZE_STRING),  $tr['Registration to send the amount of donated money'] ) : NULL;
    //$activity_register_preferential_audited = filter_var($_POST['activity_register_preferential_audited'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_register_preferential_audited'], FILTER_SANITIZE_STRING), $tr['Registration to send a lottery gold audit amount']) : NULL;


    // if ($activity_register_preferential_enable == '1' OR $activity_register_preferential_adminadd == '1') {
    //   if ($activity_register_preferential_amount == NULL OR $activity_register_preferential_audited == NULL) {
    //     echo "<script>alert('".$tr['Register to send money or pipe end to enable the new state, the amount of donations and audit amount can not be empty.'] ."');</script>";
    //     die();
    //   }
    // }


    // $activity_daily_checkin_enable = filter_var($_POST['activity_daily_checkin_enable'], FILTER_SANITIZE_STRING);
    // $activity_daily_checkin_days = filter_var($_POST['activity_daily_checkin_days'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_daily_checkin_days'], FILTER_SANITIZE_STRING),  $tr['Continuous on-line discount days']) : NULL;
    // $activity_daily_checkin_amount = filter_var($_POST['activity_daily_checkin_amount'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_daily_checkin_amount'], FILTER_SANITIZE_STRING), $tr['Continuous on-line discount gift amount']) : NULL;
    // $activity_daily_checkin_rate = filter_var($_POST['activity_daily_checkin_rate'], FILTER_SANITIZE_STRING) != '' ? check_integer_data(filter_var($_POST['activity_daily_checkin_rate'], FILTER_SANITIZE_STRING), $tr['Continuous on-line discount audit times']) : NULL;


    // if ($activity_daily_checkin_enable == '1') {
    //   if ($activity_daily_checkin_days == NULL OR $activity_daily_checkin_amount == NULL OR $activity_daily_checkin_rate == NULL) {
    //     echo "<script>alert('".$tr['Continuous on-line offers to enable status, days, gift amount and audit multiples can not be empty.']."');</script>";
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

    // $tr['Level status is enabled, all fields except the remark field can not be empty.'] = '等級狀態為啟用狀態，除備註欄位外其餘欄位皆不可為空。';
    if ($status == '1') {
      if ($gradename == NULL OR $deposit_rate == NULL OR $withdrawal_limitstime_gcash == NULL OR $withdrawal_limitstime_gtoken == NULL OR $administrative_cost_ratio == NULL) {
        echo "<script>alert('".$tr['Level status is enabled, all fields except the remark field can not be empty.']."');</script>";
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
      'apifastpay_allow', 'apifastpaylimits_upper','apifastpaylimits_lower', 'pointcardfee_member_rate',
      'withdrawallimits_cash_upper', 'withdrawallimits_cash_lower', 'withdrawalcash_allow', 'withdrawalfee_cash',
      'withdrawalfee_max_cash', 'withdrawalfee_method_cash', 'withdrawalfee_free_hour_cash', 'withdrawalfee_free_times_cash', 'withdrawallimits_upper',
      'withdrawallimits_lower', 'withdrawal_allow', 'withdrawalfee', 'withdrawalfee_max', 'withdrawalfee_method',
      'withdrawalfee_free_hour', 'withdrawalfee_free_times', 'withdrawal_limitstime_gcash', 'withdrawal_limitstime_gtoken', 'administrative_cost_ratio',
      'activity_register_preferential'
    ];

    // 組合 inster sql values
    $sql_value = '';
    $sql_col = '';
    foreach ($column_list as $key => $value) {
      $sql_col .= "\"".$value."\",";

      if (${$value} != NULL) {
        $sql_value = $sql_value."'".${$value}."',";
      } else {
        $sql_value = $sql_value."NULL,";
      }
    }

    // 去除最後一個逗號
    // $sql_value = substr($sql_value,0,-1);
    $sql_value = substr($sql_value,0,strlen(',')*-1);
    $sql_col = substr($sql_col,0,strlen(',')*-1);

    // $inster_sql = 'INSERT INTO "root_member_grade" ("gradename", "grade_alert_status", "status", "deposit_rate", "notes", "deposit_allow", "depositlimits_upper", "depositlimits_lower", "onlinepayment_allow", "onlinepaymentlimits_upper", "onlinepaymentlimits_lower", "pointcard_allow", "pointcard_limits_upper", "pointcard_limits_lower", "pointcardfee_member_rate_enable", "pointcardfee_member_rate", "withdrawallimits_cash_upper", "withdrawallimits_cash_lower", "withdrawalcash_allow", "withdrawalfee_cash", "withdrawalfee_max_cash", "withdrawalfee_method_cash", "withdrawalfee_free_hour_cash", "withdrawalfee_free_times_cash", "withdrawallimits_upper", "withdrawallimits_lower", "withdrawal_allow", "withdrawalfee", "withdrawalfee_max", "withdrawalfee_method", "withdrawalfee_free_hour", "withdrawalfee_free_times", "withdrawal_limitstime_gcash", "withdrawal_limitstime_gtoken", "administrative_cost_ratio", "activity_first_deposit", "activity_first_onlinepayment", "activity_deposit_preferential", "activity_onlinepayment_preferential", "activity_register_preferential", "activity_daily_checkin")'." VALUES (".$sql_value.");";
    $inster_sql = 'INSERT INTO "root_member_grade" ('.$sql_col.')'." VALUES (".$sql_value.");";
    $inster_sql_result = runSQL($inster_sql);

    if ($inster_sql_result) {
      echo "<script>alert('".$tr['Member level new success.']."');location.href = './member_grade_config.php';</script>";
    } else {
      echo "<script>alert('".$tr['Member level new failed.'] ."');</script>";
    }
    

  } else {
    echo "<script>alert('".$tr['Please enter the level name.']."');</script>";
  }
  
  

} elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
  var_dump($_POST);

}