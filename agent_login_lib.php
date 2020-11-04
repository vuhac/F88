<?php

function my_filter($var, $type = "string"){
  switch ($type) {
      case 'string':
          $var = isset($var) ? filter_var($var, FILTER_SANITIZE_STRING) : '';
          break;
      case 'url':
          $var = isset($var) ? filter_var($var, FILTER_SANITIZE_URL) : '';
          break;
      case 'email':
          $var = isset($var) ? filter_var($var, FILTER_SANITIZE_EMAIL) : '';
          break;
      case 'int':
      default:
          $var = isset($var) ? filter_var($var, FILTER_SANITIZE_NUMBER_INT) : '';
          break;
  }
  return $var;
}

// IP白名單
// --------------------------------------
function check_whitelist_ip($account,$debug=0){

  if($debug ==1) {
    var_dump($_POST);
    var_dump($_SESSION);
    var_dump($account);
    // var_dump($password);
    var_dump($login_force);
  }

  // -----------------------------------
  $i = 0;
  $check_whitelist['success'] = true;
  $check_whitelist['messages'] = '';

  $check_whitelist['factor_result'] = '';

  $logger = '';

  // 先撈出登入者帳號、ID
  $get_a_id = get_agent_id($account);
  $result = runSQLall($get_a_id);
  if($result[0] >= 1){
    for($i=1;$i<= $result[0];$i++){
      $m_id = $result[$i]->id; // 會員id

      // 2fa、IP設定檢查
      $check_auth = check_security_setting($m_id);
      $auth_result = runSQLall($check_auth);

      // 有資料
      if($auth_result[0] >= 1){

        for($i=1;$i<= $auth_result[0];$i++){
          // ip 狀態
          $auth_whitelist_status = $auth_result[1]->whitelis_status;
          // ip
          $auth_whitelist = $auth_result[1]->whitelis_ip;
          $decode_whitelist = json_decode($auth_whitelist,true);

          // IP狀態 開啟
          if($auth_whitelist_status == 1){

            $user_ip_now = explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0']; // 使用者目前使用的ip位址

            $get_user_ip = get_user_ip($user_ip_now,$decode_whitelist);// 找ip

            if($get_user_ip == true){
              // IP符合，可以登入
              // 寫進memberlog2db
              $logger = '您的IP在白名单内，通過!';
              $check_whitelist['success'] = true;
              $check_whitelist['messages'] = $check_whitelist['messages'].$logger;
              $msg         = $logger;
              $msg_log     = $msg.$user_ip_now;
              $sub_service = 'login';
              // 原版
              // memberlogtodb("$account", 'member', 'info', "$msg", $account, "$msg_log", 'b', $sub_service);

              // 把後台 登入寫入memberlog的service改寫成administrator
              memberlogtodb("$account", 'administrator', 'info', "$msg", $account, "$msg_log", 'b', $sub_service);

            }else{
              $logger = '您的IP不在白名单内，請洽客服人員。您的IP:'.$user_ip_now;
              $check_whitelist['success'] = false;
              $check_whitelist['messages'] = $check_whitelist['messages'].$logger;

              $msg         = $logger;
              $msg_log     = $msg;
              $sub_service = 'login';
              // 原版
              // memberlogtodb("$account", 'member', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);

              // 把後台 登入寫入memberlog的service改寫成administrator
              memberlogtodb("$account", 'administrator', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);

              die($logger);
            }
          }
        }

      }else{
        // 沒資料 或 沒開啟
        $check_whitelist['success'] = true;

      }
    }

  }else{
    // 如果帳號亂打
    $logger = '错误，查无会员帐号：'.$account.'。';
    $check_whitelist['success'] = false;
    $check_whitelist['messages'] = $check_whitelist['messages'].$logger;

    $msg         = $logger;
    $msg_log     = $msg;
    $sub_service = 'login';
    // 原版
    // memberlogtodb("guest", 'member', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);

    // 把後台 登入寫入memberlog的service改寫成administrator
    memberlogtodb("guest", 'administrator', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);



    die($logger);
  }

  return($check_whitelist);
}

// 先撈出登入者帳號、ID
function get_agent_id($account){
  $sql=<<<SQL
    SELECT * FROM root_member
      WHERE account = '{$account}'
      AND therole = 'R'
SQL;
  return $sql;
}

// 先撈出all登入者帳號、ID
function get_all_agent_id(){
  $sql=<<<SQL
    SELECT id,account FROM root_member
    WHERE status = '1'
SQL;

  $result = runSQLall($sql);
  unset($result[0]);

  foreach ($result as $val){
		$return[$val->account]['id']=$val->id;
		$return[$val->account]['account']=$val->account;
	}
  return $return;
  // return $result;
}

// 2fa、IP設定檢查
function check_security_setting($m_id){

  $auth_sql =<<<SQL
    SELECT * FROM root_member_authentication
      WHERE id = '{$m_id}'
SQL;

  // $auth_result = runSQLall($auth_sql);
// $auth_sql =<<<SQL
// SELECT * FROM root_member_authentication WHERE id = '{$m_id}' AND whitelis_status = '1'
// SQL;
  return $auth_sql;
}

// 找ip
function get_user_ip($user_ip_now,$decode_whitelist){

foreach($decode_whitelist as $v){
  $str_ip = implode(' ',$v);

  // 算ip range
  $get_iplist = ipCIDRCheck($user_ip_now,$str_ip);

  if($get_iplist == true){
    return true;
  }

}
return false;

}

// 算IP range
function ipCIDRCheck($user_ip_now,$str_ip){

    if(strpos($str_ip,'/') == false){
        $bits = 32;
    }
    // var_dump($str_ip);
    // $cidr = explode('/',$str_ip);
    $cidr = array_pad(explode('/',$str_ip),2,32);
    // $cidr = array_pad(explode('/',$str_ip),2,'/');
    list($str_ip,$bits) = $cidr;
    // var_dump($cidr);

    $range_decimal = ip2long($str_ip); //  user 安全設定的IP
    $ip_decimal = ip2long($user_ip_now); // user現在使用的IP

    if($cidr[1] == 32){
      $wildcard_decimal = pow( 2, ( 32 - $bits )) - 1;
    }else{
      $wildcard_decimal = - 1 << ( 32 - $bits);
    }

    $netmask_decimal = ~ $wildcard_decimal;

    // range
    // ip2long: ip -> int
    // long2ip: int -> ip
    $ip_range = array();
    $ip_range[0] = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1]))));
    $ip_range[1] = long2ip((ip2long($ip_range[0])) + pow(2, (32 - (int)$cidr[1])) - 1);
    // var_dump($ip_range);die();

    // if(( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal )){
    //   $the_result = 'true';

    // }else{
    //   $the_result = 'false';
    // }
    // var_dump($the_result);die();
    // return $the_result;
    return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
}

// 2fa 驗證碼檢查
function check_factor_auth($secret_id,$verify,$ga){
  // 驗證碼大約1分30秒換新的
  $check_factor = $ga->verifyCode($secret_id,$verify,3);

  return $check_factor;
}


// 確定有開啟2FA
// 2FA驗證通過
// 在factor_check call這個function
// 寫進DB
// -> home.php
function insert_indb($account_name){

  global $config;
  global $system_config;
  global $redisdb;

  $alert['success'] = true;
  $alert['messages'] = '';
  // 2fa開啟
  $alert['2fa_factor'] = true;

  // 從 get_agent_id()撈出登入者資料
  $to_get_login_account = get_agent_id($account_name);
  $account_result = runSQLall($to_get_login_account);


	// 如果有 fingerprint 的話,紀錄
	if(isset($_SESSION['fingertracker'])){
		$fingertracker = $_SESSION['fingertracker'];
	}else{
		$fingertracker = 'NOFINGER';
  }

	// 後台 + 專案站台 + 帳號 , 寫入 redisdb 識別是否單一登入使用
	$value = $config['projectid'].'_back_'.$account_result[1]->account;
	// 目前程式所在的 session , 需要加上 phpredis_session
	$session_id = session_id();
	// db 2 自己寫出來的 session, save member session data,
  $sid = sha1($value).':'.$session_id;

  // 此為 user 登入成功的處理
  if($alert['success'] == true and $account_result[0] == 1) {
    // 直到登入成功, 才取消 cpatcha session
    unset($_SESSION['captcha']['code']);
    unset($_SESSION['captcha']['account_password_errorcount']);
    unset($_SESSION['captcha']['errorcount']);

   // 將使用者資訊存到 session
   $_SESSION['agent'] = $account_result[1];
    // 檢查是否建立了錢包資料，如果有資料的話，把錢包帶進來。沒有的話建立錢包。
   $rcode = get_member_wallets($account_result[1]->id);
   if($rcode['code'] == '1') {
      // 同時更新了 $_SESSION['member'] 變數

      // 將這個會員 or 代理商註冊在 redis db 內, 避免重複登入的問題
      // 寫入 redis server 的資訊
      // 從哪裡點擊來的 , 後台呈現資料使用
      if (!empty($_SERVER['HTTP_REFERER'])) { $http_referer = $_SERVER['HTTP_REFERER']; }else{ $http_referer = "No HTTP_REFERER";	}
      // 原版
      // $value = $value.','.time().','.$_SERVER["SCRIPT_NAME"].','.$_SERVER["REMOTE_ADDR"].','.$fingertracker.','.$http_referer.','.$_SERVER["HTTP_USER_AGENT"];
      $value = $value.','.time().','.$_SERVER["SCRIPT_NAME"].','.explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'].','.$fingertracker.','.$http_referer.','.$_SERVER["HTTP_USER_AGENT"];
      $rrset = Agent_runRedisSET($sid, $value, $redisdb['db']);

      // 取得使用者預設的語系
      $_SESSION['lang'] = $account_result[1]->lang;

      //$logger =  $account.'登入成功';
      $logger = $account_result[1]->account.' sign in suceesfully';
      $alert['messages'] = $alert['messages'].$logger;
      // 紀錄這次的登入
      // $logger2db = json_encode($alert);
      // memberlog2db($account_result[1]->account,'login','info', "$logger2db");

      $msg         = $alert['messages'];
      $msg_log     = $msg;
      $sub_service = 'login';
      // 原版
      // memberlogtodb($account_name, 'member', 'info', "$msg", $account_name, "$msg_log", 'b', $sub_service);

      // 把後台 登入寫入memberlog的service改寫成administrator
      memberlogtodb($account_name, 'administrator', 'info', "$msg", $account_name, "$msg_log", 'b', $sub_service);

      //echo '登入成功, 會員資料已經載入.';
   }else{
      //$logger = '登入後重新取得钱包资料异常'.$rcode['messages'];
      $logger = 'Re-obtain the wallet information exception '.$rcode['messages'];
      $alert['success'] = false;
      $alert['messages'] = $alert['messages'].$logger;
   }
  }else{
      // 紀錄這次的登入所有的錯誤
      // $logger2db = json_encode($alert);
      // memberlog2db($account_result[1]->account,'login','error', "$logger2db");

      $msg         = $alert['messages'];
      $msg_log     = $msg;
      $sub_service = 'login';
      // 原版
      // memberlogtodb($account_result[1]->account, 'member', 'error', "$msg", $account_result[1]->account, "$msg_log", 'b', $sub_service);

      // 把後台 登入寫入memberlog的service改寫成administrator
      memberlogtodb($account_result[1]->account, 'administrator', 'error', "$msg", $account_result[1]->account, "$msg_log", 'b', $sub_service);

  }

  return($alert);
}

?>
