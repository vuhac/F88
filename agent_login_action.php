<?php
// ----------------------------------------------------------------------------
// Features:	代理商後台， 針對登入、登出，會員重複登入的處理。
// File Name:	agent_login_action.php
// Author:		Barkley
// Related:
// Log:
// ----------------------------------------------------------------------------

session_start();

require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/in/PHPGangsta/GoogleAuthenticator.php";
// 2階段驗證、IP
require_once dirname(__FILE__) ."/agent_login_lib.php";


if(isset($_GET['a'])) {
    $action = filter_var($_GET['a'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}else{
    die('(x)不合法的測試');
}
//var_dump($_SESSION);
//var_dump($_POST);
//var_dump($_GET);

// 宣告兩階段驗證物件
$ga = new PHPGangsta_GoogleAuthenticator();

// ==============================================================================================
// usage: $user_balance = get_member_wallets($userid);
// code and message , code=1 表示正確
// related:
// 2016.11.23 update 預計取代舊的錢包 , 指提供給 agent_login.php 使用.
// ==============================================================================================
function get_member_wallets($userid) {
	// 抓 $user->id 餘額
	// $user_balance_sql = "SELECT * FROM root_member_wallets WHERE id = $userid;";
	$user_balance_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = $userid;";
	//var_dump($user_balance_sql);
	$user_balance_result = runSQLALL($user_balance_sql);
	//var_dump($user_balance_result);

	if($user_balance_result[0] == 1) {
		// 存在，取出餘額
		$_SESSION['agent'] = $user_balance_result[1];
		$r['code'] = '1';
    $r['messages'] = '更新餘額及會員帳號';
	}else{
		// 沒有資料，建立初始資料。
		$member_wallets_addaccount_sql = "INSERT INTO root_member_wallets (id, changetime, gcash_balance, gtoken_balance) VALUES ('".$userid."', 'now()', '0', '0');";
		// var_dump($member_wallets_addaccount_sql);
    $rwallets = runSQL($member_wallets_addaccount_sql);
		if($rwallets == 1){
			$logger = 'member id：'.$userid.',Create root_member_wallets account success!! ';
      //echo $logger;
      $msg         = $logger;
      $msg_log     = $msg;
      $sub_service = 'wallet';
      memberlogtodb($_SESSION['agent']->account, 'member', 'info', "$msg", $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);
			$r['code'] = '1';
			$r['messages'] = $logger;
			// 再取出一次
			// $user_balance_sql = "SELECT * FROM root_member_wallets WHERE id = $userid;";
			$user_balance_sql = "SELECT * FROM root_member JOIN root_member_wallets ON root_member.id=root_member_wallets.id WHERE root_member.id = $userid;";
			$user_balance_result = runSQLALL($user_balance_sql);
			$_SESSION['agent'] = $user_balance_result[1];

		}else {
			$logger =  'member id：'.$userid.',Create root_member_wallets account false!! ';
      // echo $logger;
      $msg         = $logger;
      $msg_log     = $msg;
      $sub_service = 'wallet';
      memberlogtodb($_SESSION['agent']->account, 'member', 'error', "$msg", $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);
			// memberlog2db($_SESSION['agent']->account,'member wallet','error', "$logger");
			$r['code'] = '2';
			$r['messages'] = $logger;
		}
	}
	// 回傳訊息， error=0 is correct, error > 0 is another
	// $r['error'] = '0';
	// $r['errormessages'] = '';
	// $r['wallets']
	return($r);
}
// 回傳整各錢包的狀況



// -----------------------------------
/*
功能: 後台管理員登入檢查的函示 , 需要 lib.php 的函示輔助
使用: agent_login_check($account, $password, $login_force, $debug=0)
必要變數:
$account
$password
$login_force
全域變數:
$system_config
$_POST
$_SESSION
*/
// -----------------------------------
function agent_login_check($account, $password, $login_captcha, $login_force, $debug=0) {
	global $config;
  global $system_config;
	global $redisdb;

  // step 0 檢查資料及驗證碼正確性
  // -----------------------------------
  $i=0;
  $error['success'] = true;
  $error['messages'] = '';
  $logger = '';
  // 2階段驗證，預設關閉
  $error['2fa_factor'] = false;

  // 給測試用的預設驗證碼變數
  $captcha_for_test = ($system_config['captcha_for_test'] != '') ? $system_config['captcha_for_test'] : NULL;

  if($debug ==1) {
    var_dump($_POST);
    var_dump($_SESSION);
    var_dump($account);
    var_dump($password);
    var_dump($login_force);
  }

  // step 1 檢查是否已經登入了, 設為正確離開.
  if(isset($_SESSION['a']) AND ($_SESSION['agent']->therole == 'M' OR $_SESSION['agent']->therole == 'A')) {
    $logger = $_SESSION['agent']->account.' 使用者已經登入系統了';
    $error['success'] = true;
    $error['messages'] = $error['messages'].$logger;
    return($error);
  }


  // step 2 如果驗證碼正確的話 , 且 captch 存在 session 內. 增加一組給 test unit 用的驗證碼 $system_config['captcha_for_test'] ，可以跳過驗證程序。
  // -----------------------------------
  if(isset($_SESSION['captcha']['code']) AND ( $_SESSION['captcha']['code'] == $login_captcha  OR ($captcha_for_test == $login_captcha) )) {
    // 驗證碼正確,取消原本的 captcha 變數 session , 修正登入成功才取消
    // unset($_SESSION['captcha']['code']);
  }else{
      //$logger = $login_captcha.' Authentication code is incorrect.';
			$logger = '验证码错误，请重新输入。 '.$login_captcha.' '.$captcha_for_test;
      $error['success'] = false;
      $error['messages'] = $error['messages'].$logger;
  }

	// 帳號格式 check
	$account          = strtolower(filter_var($account, FILTER_SANITIZE_STRING));
	$account_orig_len = strlen($account);
	$accunt_regexp 	  = '/^[a-z][a-z0-9]{2,12}$/';
	$accunt_regexp_check = filter_var($account, FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"$accunt_regexp")));
	// var_dump($accunt_regexp_check);
	// var_dump($account_orig_len);
	// 長度需要 3-12 各字元
	if($accunt_regexp_check == false OR ($account_orig_len < 3 OR $account_orig_len > 12)) {
		// 帳號格式錯誤
		$logger = $account.' account input format error.';

    $msg         = $logger;
    $msg_log     = $msg.'，帐号长度需介于3~12码。';
    $sub_service = 'login';
    // 原版
    // memberlogtodb("guest", 'member', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);

    // 把後台 登入寫入memberlog的service改寫成administrator
    memberlogtodb("guest", 'administrator', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);


    $logger = '帐号输入有误';
		die($logger);
	}


  // step 4 密碼組合及長度
  // -----------------------------------
	// 檢查密碼格式，需要為  sha1
	$password        = filter_var($password, FILTER_SANITIZE_STRING);
	$pwd_regexp      = '/^[a-fA-F0-9]{40}/';
	$password_regexp_check = filter_var($password, FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"$pwd_regexp")));
	// var_dump($accunt_regexp_check);
	if($accunt_regexp_check == false) {
		// 密碼格式錯誤
		$logger = $account.' of '.$password.' password format is incorrect';
    $msg         = $logger;
    $msg_log     = $msg;
    $sub_service = 'login';
    // 原版
    // memberlogtodb("guest", 'member', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);

    // 把後台 登入寫入memberlog的service改寫成administrator
    memberlogtodb("guest", 'administrator', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);


		// $logger = 'password format is incorrect';
		$logger = '密码输入有误';
		die($logger);
	}

	// 如果有 fingerprint 的話,紀錄
	if(isset($_SESSION['fingertracker'])){
		$fingertracker = $_SESSION['fingertracker'];
	}else{
		$fingertracker = 'NOFINGER';
	}

	// 後台 + 專案站台 + 帳號 , 寫入 redisdb 識別是否單一登入使用
	$value = $config['projectid'].'_back_'.$account;
	// 目前程式所在的 session , 需要加上 phpredis_session
	$session_id = session_id();
	// db 2 自己寫出來的 session, save member session data,
	$sid = sha1($value).':'.$session_id;
	// db 0 系統的 php session
	$phpredis_sid = 'PHPREDIS_SESSION:'.$session_id;
	//var_dump($_SESSION);
	//var_dump($session_id);
	//var_dump($sid);
	//var_dump($phpredis_sid);
	//var_dump($_COOKIE);
	//die();

  // 前面的步驟成功才繼續, 否則就離開
  if($error['success'] == true) {
    // step 5 檢查 SQL 是否有資料, 及是否有重複的使用者在系統內
    // -----------------------------------
    $sql = "select * from root_member where account = '".$account."' and passwd = '".$password."' and therole = 'R' ;";
    $r = runSQLall($sql);
    //var_dump($r);

    // agent 認證正確
    if($r[0] == 1 and $error['success'] == true) {
  		// member login session record in redisdb 2
  		// 檢查系統中使用者是否已經存在，如果已經存在的話就不允許登入。或是讓使用者選擇刪除這個
  		// $checkuser_result = Member_CheckLoginUser($value);
      $checkuser_result = Agent_CheckLoginUser($value);
  		//var_dump($checkuser_result);
  		// 強迫登入的話，就可以跳過這段。
  		if($checkuser_result['count'] == 0 ) {
  			// $logger = '同帳號'.$account.'沒有其他人登入系統，可以繼續工作';
  		}elseif($checkuser_result['count'] == 1 ){
				// 只有一個人在系統內 , 判斷是否和當前 session id 一致, 不同的話就砍除 session and db sid
  			$online_users = array();
  			$online_users = explode(",",$checkuser_result['value']);
				// example: gp01_front_mtchang
				$online_user_account = explode("_", $online_users[0]);
  			$online_user_details['site'] = $online_user_account[0];
				$online_user_details['type'] = $online_user_account[1];
				$online_user_details['account'] = $online_user_account[2];
        $online_user_details['time'] = date('Y-m-d H:i:s',$online_users[1]);

  			$online_user_details['page'] = $online_users[2];
  			$online_user_details['ip'] = $online_users[3];
				$expiretime_chk = time() - strtotime($online_user_details['time']);
				//var_dump($expiretime_chk);
				//var_dump($online_user_details);
  			//var_dump($checkuser_result);

        $phpsid = explode(':', $checkuser_result['key']);
        $alive_phpsession_sid = 'PHPREDIS_SESSION:'.$phpsid[1];
        //var_dump($phpsid);
        //var_dump($alive_phpsession_sid);

        // 確認這個使用者, 登入的 browser 是否為同一個, 不同的話就刪除另一個。
        if($sid != $checkuser_result['key']) {

          // 有強制裁刪除
          if($login_force == 1) {
            // 取得使用者的 session 資訊
            $alive_phpsession_sid = 'PHPREDIS_SESSION:'.$phpsid[1];
            $rrdel[0] = Agent_runRedisDEL($alive_phpsession_sid, 0);
            $rrdel[1] = Agent_runRedisDEL($checkuser_result['key'], $redisdb['db']);
            //var_dump($rrdel);
            // 刪除系統中的存在的 key
            // $logger = '删除系统中已经登入的帐号 key 並強行登入系統'.$checkuser_result['key'].' *PHP SESSION: '.$rrdel[0].' *BACK: '.$rrdel[1];

            // 2019/12/23
            $logger = '删除系统中已经登入的帐号 key 並強行登入系統。';//.$rrdel[1];

            //var_dump($logger);
            $error['success'] = true;
            $error['messages'] = $error['messages'].$logger;
          }else{
            $logger = '帐号'.$online_user_details['account'].', 在时间'.$online_user_details['time'].', 於IP: '.$online_user_details['ip'].'登入。';
            //var_dump($logger);
            $error['success'] = false;
            $error['messages'] = $error['messages'].$logger;
          }
        }

  		}else{
  			// 很多使用者再系統內的處理。
				$logger = '有重複的使用者'.$checkuser_result['count'].'人以上登入，全體退出系統後重新登入。';
        $error['success'] = false;
        $error['messages'] = $error['messages'].$logger;

  		}
  		// 檢查系統同帳號，是否已經有人在別的地方登入。 end

			// 帳號狀態, 0=會員停權disable 1= 會員啟用enable 2=會員錢包凍結
      // 帳號密碼正確, 但是帳戶已經被鎖定
      if($r[1]->status == 0 ) {
        $logger = '你的帐号已经被锁定，联络客服人员处理。';
				// $logger = 'Your account has been locked, please contact customer service.';
        $error['success'] = false;
        $error['messages'] = $error['messages'].$logger;
      }

			if($r[1]->status == 2 ) {
        $logger = '你的帐号暫時被凍結，请联络客服人员处理。';
        $error['success'] = false;
        $error['messages'] = $error['messages'].$logger;
      }

    }else{
      //$logger = $account.'帐号或密码错误, 或是使用者不存在.';
      $_SESSION['captcha']['account_password_errorcount']++;
			$logger = '帐号或密码错误或是使用者不存在 '.$_SESSION['captcha']['account_password_errorcount'].' 次.';
      $error['success'] = false;
      $error['messages'] = $error['messages'].$logger;
      // 如果超過次數, 凍結這個 browser fingerprint
      if($_SESSION['captcha']['account_password_errorcount'] >= 5) {
        $_SESSION['freeze_fingerprint'] = $_SESSION['fingertracker'];
      }
    }

  }else{
    // 前面就有錯誤了, 可以先離開. 到這裡中斷.
    return($error);
  }

  // 此為 user 登入成功的處理
  if($error['success'] == true and $r[0] == 1){
    // 2fa檢查
    $auth_sql = check_security_setting($r[1]->id);
    $auth_sql_result = runSQLALL($auth_sql);
    // 2fa 有開啟
    if($auth_sql_result[0] >= 1 AND $auth_sql_result[1]->two_fa_status == '1'){
        $error['2fa_factor'] = true;
        return $error;
        die();
    }


    // 原版
    // 直到登入成功, 才取消 cpatcha session
    unset($_SESSION['captcha']['code']);
    unset($_SESSION['captcha']['account_password_errorcount']);
    unset($_SESSION['captcha']['errorcount']);

   // 將使用者資訊存到 session
   $_SESSION['agent'] = $r[1];
    // 檢查是否建立了錢包資料，如果有資料的話，把錢包帶進來。沒有的話建立錢包。
   $rcode = get_member_wallets($r[1]->id);
   if($rcode['code'] == '1') {
     // 同時更新了 $_SESSION['member'] 變數

		 // 將這個會員 or 代理商註冊在 redis db 內, 避免重複登入的問題
		 // 寫入 redis server 的資訊
		 // 從哪裡點擊來的 , 後台呈現資料使用
     if (!empty($_SERVER['HTTP_REFERER'])) { $http_referer = $_SERVER['HTTP_REFERER']; }else{ $http_referer = "No HTTP_REFERER";	}
     // 原版
    //  $value = $value.','.time().','.$_SERVER["SCRIPT_NAME"].','.$_SERVER["REMOTE_ADDR"].','.$fingertracker.','.$http_referer.','.$_SERVER["HTTP_USER_AGENT"];
     $value = $value.','.time().','.$_SERVER["SCRIPT_NAME"].','.explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'].','.$fingertracker.','.$http_referer.','.$_SERVER["HTTP_USER_AGENT"];
		 $rrset = Agent_runRedisSET($sid, $value, $redisdb['db']);

     // 取得使用者預設的語系
     $_SESSION['lang'] = $r[1]->lang;

     //$logger =  $account.'登入成功';
		 $logger = $account.' sign in suceesfully';
     $error['messages'] = $error['messages'].$logger;
     // 紀錄這次的登入
    //  $logger2db = json_encode($error);
    $msg         = $error['messages'];
    $msg_log     = $msg;
    $sub_service = 'login';
    // 原版
    // memberlogtodb($account, 'member', 'info', "$msg", $account, "$msg_log", 'b', $sub_service);

    // 把後台 登入寫入memberlog的service改寫成administrator
    memberlogtodb($account, 'administrator', 'info', "$msg", $account, "$msg_log", 'b', $sub_service);

     //echo '登入成功, 會員資料已經載入.';
   }else{
     //$logger = '登入後重新取得钱包资料异常'.$rcode['messages'];
		 $logger = 'Re-obtain the wallet information exception '.$rcode['messages'];
     $error['success'] = false;
     $error['messages'] = $error['messages'].$logger;
   }
  }else{
   // 紀錄這次的登入所有的錯誤
  //  $logger2db = json_encode($error);

    $msg         = $error['messages'];
    $msg_log     = $msg;
    $sub_service = 'login';
    // 原版
    // memberlogtodb('guest', 'member', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);

    // 把後台 登入寫入memberlog的service改寫成administrator
    memberlogtodb('guest', 'administrator', 'error', "$msg", $account, "$msg_log", 'b', $sub_service);

  }

  return($error);
}
// -----------------------------------
// end of login_check()
// -----------------------------------




// ------------------------------------

$debug = 0;
// ----------------------------------
// 動作為會員登入檢查
// ----------------------------------

if($action == 'login_check') {
  // var_dump($_SESSION);
  // var_dump($_POST);die();
  // 給測試用的預設驗證碼變數
  $captcha_for_test = ($system_config['captcha_for_test'] != '') ? $system_config['captcha_for_test'] : NULL;
  $login_captcha = $_POST['captcha'];

  if(isset($_SESSION['freeze_fingerprint'])) {
    $logger = '错误太多次，暂时被冻结。';
    die($logger);
  }

  // 檢查驗證碼變數是否存在, 並比對是否驗證碼正確
  if(isset($_SESSION['captcha']['code']) ) {
    if( $login_captcha !== $_SESSION['captcha']['code'] AND $captcha_for_test !== $login_captcha ) {
      // 兩次驗整碼失敗的話,重新產生驗證碼
      if($_SESSION['captcha']['errorcount'] >= 5) {
        $logger = '验证码错误 '.$_SESSION['captcha']['errorcount'].'次，请重新输入验证码。';
        // 凍結
        $_SESSION['freeze_fingerprint'] = $_SESSION['fingertracker'];
        $msg         = $logger;
        $msg_log     = $msg;
        $sub_service = 'login';
        // 原版
        // memberlogtodb('guest', 'member', 'error', "$msg",$_POST['account'], "$msg_log", 'b', $sub_service);

        // 把後台 登入寫入memberlog的service改寫成administrator
        memberlogtodb('guest', 'administrator', 'error', "$msg",$_POST['account'], "$msg_log", 'b', $sub_service);


        echo '<script>window.location.reload();</script>';
        die($logger);
      }else{
        $logger = '验证码错误'.$_SESSION['captcha']['errorcount'].'次，请重新输入验证码。';
        $_SESSION['captcha']['errorcount'] = $_SESSION['captcha']['errorcount']+1;
        // var_dump($_SESSION['captcha']['errorcount']);
        $msg         = $logger;
        $msg_log     = $msg;
        $sub_service = 'login';
        // 原版
        // memberlogtodb('guest', 'member', 'error', "$msg", $_POST['account'], "$msg_log", 'b', $sub_service);

        // 把後台 登入寫入memberlog的service改寫成administrator
        memberlogtodb('guest', 'administrator', 'error', "$msg", $_POST['account'], "$msg_log", 'b', $sub_service);

        die($logger);
      }
    }
  }else{
    $logger = 'Session TimeOut 請重新整理後登入';
    $msg         = $logger;
    $msg_log     = $msg;
    $sub_service = 'login';
    // 原版
    // memberlogtodb('guest', 'member', 'error', "$msg", $_POST['account'], "$msg_log", 'b', $sub_service);

    // 把後台 登入寫入memberlog的service改寫成administrator
    memberlogtodb('guest', 'administrator', 'error', "$msg", $_POST['account'], "$msg_log", 'b', $sub_service);


    //echo '<script>window.location.reload();</script>';
    die($logger);
  }

  // 用來計算密碼錯誤的次數
  if(!isset($_SESSION['captcha']['account_password_errorcount'])){
    $_SESSION['captcha']['account_password_errorcount'] = 0;
  }


  // 取得傳入的值
  $account        = strtolower(filter_var($_POST['account'], FILTER_SANITIZE_STRING));
  $password       = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
  // 強迫登入的設定
  if(isset($_POST['login_force']) AND ($_POST['login_force'] == '1' OR $_POST['login_force'] == '0')) {
    $login_force   = intval($_POST['login_force']);
  }else{
    $login_force   = 0;
  }

  // 檢查ip 白名單
  $check_whitelist = check_whitelist_ip($account,$debug);
  // var_dump($check_whitelist);
  if($check_whitelist['success'] == false){
     // 登入失敗, 顯示失敗的訊息原因
    echo $check_whitelist['messages'];
    die();
  }

  // 呼叫會員登入檢查函示
  $error = agent_login_check($account, $password, $login_captcha, $login_force, $debug);
  if($error['success'] == true) {
    // 登入成功後, 引導到預設的頁面位置
    if($debug == 1){
      var_dump($error);
      $return_html = 'login success';

    }else{
      if($error['2fa_factor'] == true){
        // 2fa有開啟 導到2階段驗證頁面
        $a_ccount_factor = sha1(date('d_H').$_SESSION['fingertracker']); // date、fingertracker sha1
        $to_encode_account = base64_encode($account); // 帳號

        $return_html = '<script>window.location="agent_factor_check.php?ref='.$to_encode_account.'_'.$a_ccount_factor.'";</script>';
        echo $return_html;
        die();
      }
      // 到home
      $return_html = '<script>window.location="home.php";</script>';
      echo $return_html;
    }

  }else{
    // 登入失敗, 顯示失敗的訊息原因
    echo $error['messages'];
  }

  // ------------------------------------
  // 原版
  // $error = agent_login_check($account, $password, $login_captcha, $login_force, $debug);
  // if($error['success'] == true) {
  //   // 登入成功後, 引導到預設的頁面位置
  //   if($debug == 1){
  //     var_dump($error);
  //     $return_html = 'login success';
  //   }else{
  //     $return_html = '<script>window.location="home.php";</script>';
  //   }
  //   echo $return_html;
  // }else{
  //   // 登入失敗, 顯示失敗的訊息原因
  //   echo $error['messages'];
  // }


/*
  // 檢查驗證碼變數是否存在
  if(isset($_SESSION['captcha']['code']) ) {
    if( $_POST['cpatcha'] != $_SESSION['captcha']['code'] ) {
      // 兩次驗整碼失敗的話,重新產生驗證碼
      if($_SESSION['captcha']['errorcount'] >= 3) {
        $logger = '驗證碼'.$_POST['cpatcha'].'錯誤 '.$_SESSION['captcha']['errorcount'].'次，請重新輸入驗証碼。';
        memberlog2db('guest','login','error', "$logger");
        echo '<script>window.location.reload();</script>';
        die($logger);
      }else{
        $logger = '驗證碼'.$_POST['cpatcha'].'錯誤'.$_SESSION['captcha']['errorcount'].'次，請重新輸入。';
        $_SESSION['captcha']['errorcount'] = $_SESSION['captcha']['errorcount']+1;
        // var_dump($_SESSION['captcha']['errorcount']);
        memberlog2db('guest','login','error', "$logger");
        die($logger);
      }
    }
  }else{
    $logger = 'Session TimeOut 請重新整理後登入';
    memberlog2db('guest','login','error', "$logger");
    echo '<script>window.location.reload();</script>';
    die($logger);
  }

	//$u['account']       = filter_var($_POST['account'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
	//$u['password']      = filter_var($_POST['password'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);

  // 帳號格式
  $u['account']        = strtolower(filter_var($_POST['account'], FILTER_SANITIZE_STRING));
  $accunt_regexp 	  = '/^[a-z][a-z0-9]{2,12}$/';
  $accunt_regexp_check = filter_var($u['account'], FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"$accunt_regexp")));
  // var_dump($accunt_regexp_check);
  if($accunt_regexp_check == false) {
    // 帳號格式錯誤
    $logger = $u['account'].'account format error';
    memberlog2db('guest','login','error', "$logger");
    die($logger);
  }

  // 檢查密碼格式，需要為  sha1
  $u['password']        = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
  $password_regexp      = '/^[a-fA-F0-9]{40}/';
  $password_regexp_check = filter_var($u['password'], FILTER_VALIDATE_REGEXP,array("options"=>array("regexp"=>"$password_regexp")));
  // var_dump($accunt_regexp_check);
  if($accunt_regexp_check == false) {
    // 密碼格式錯誤
    $logger = $u['account'].' of '.$u['password'].' password format is incorrect';
    memberlog2db('guest','login','error', "$logger");
    die($logger);
  }


  $u['login_force']   = $_POST['login_force'];
  $u['cpatcha']       = $_POST['cpatcha'];
	// var_dump($u);
  // 結果放在這裡
  $error['success']   = false;
  $error['messages']  = '';

	// 如果帳號超過 16 char 表示有問題
	if(strlen($u['account']) >= 16 ) {
			// $logger = $u['account'].'帳號太長，請重新輸入。';
			$logger = $tr['Account is too long, please try again.'];
			// syslog2db('guest','login','error', "$logger");
      $error['success']   = false;
      $error['messages']  = $logger;
      echo $logger;
			die();
	}

  // 如果密碼超過 41 char 表示有問題
	if(strlen($u['password']) >= 50 ) {
			// $logger = $u['account'].'帳號太長，請重新輸入。';
			$logger = 'password is too long';
			// syslog2db('guest','login','error', "$logger");
      $error['success']   = false;
      $error['messages']  = $logger;
      echo $logger;
			die();
	}


	// 只有代理商或是管理員  R , 鎖定,啟用, 關閉 的都可以登入
	$sql = "SELECT * FROM root_member WHERE (therole = 'R' OR therole = 'A') AND (status = '1' OR status = '0' OR status = '2') AND account = '".$u['account']."' AND passwd = '".$u['password']."';";
	//var_dump($sql);

	$r = runSQLALL($sql);
	//var_dump($r);

	// agent 認證正確
	if($r[0] == 1) {
    // 檢查系統中使用者是否已經存在，如果已經存在的話就不允許登入。或是讓使用者選擇刪除這個
    $checkuser_result = Agent_CheckLoginUser($u['account']);
    // 強迫登入的話，就可以跳過這段。
    if($checkuser_result['count'] == 0 ) {
      // 沒有其他人登入系統，可以繼續工作
      $logger = '帳號密碼認證正確, 且沒有其他已經先行登入的使用者。';
      $error['success']   = true;
      $error['messages']  = $logger;

    }else{
      // 有人已經登入了。詢問使用者，是否確定要登入。登入後會刪除該使用者。
      $logger = '<p>'.$tr['A user has to sign in the system. If you want to login, please check the above mandatory login option.'].'</p>';
      echo $logger;
      $online_users = array();
      $online_users = explode(",",$checkuser_result['value']);
      $online_user_details['account'] = $online_users[0];
      $online_user_details['time'] = date('Y-m-d H:i:s',$online_users[1]);
      $online_user_details['page'] = $online_users[2];
      $online_user_details['ip'] = $online_users[3];
      //var_dump($online_users);
      //var_dump($checkuser_result);
      echo '<p class="online_user_details">';
      echo 'Account'.':'.$online_user_details['account'].'<br>';
      echo 'Last time'.':'.$online_user_details['time'].'<br>';
      echo 'From IP'.':'.$online_user_details['ip'].'<br>';
      echo 'In Page'.':'.$online_user_details['page'].'<br>';
      echo '</p>';
      $error['success']   = false;
      $error['messages']  = $logger;

      // 如果使用者強制登入的話，先刪除在系統中的使用者。
      if($u['login_force'] == 1) {
        // 刪除指定的 db1 中的 key
        $rr = Agent_runRedisDEL($checkuser_result['key'],1);
        $logger = $tr['Delete existing users forced to sign.'];
        echo $logger;
        // var_dump($rr);
        $error['success']   = true;
        $error['messages']  = $logger;

      }else{
        // 不存在的狀況, 中斷.
        $logger = '不存在的狀況, 中斷.';
        $error['success']   = false;
        $error['messages']  = $logger;
        //die();
      }
    }
  }else{
      // DB no data
			$logger = $tr['Account number or password may be a problem, there are only agents can log in.'];
			// $logger = '帳號或密碼可能有問題，這裡只有管理員及代理商可以登入。';
      $error['success']   = false;
      $error['messages']  = $logger;
	}

  //var_dump($r);
  //var_dump($error);
	// 帳戶沒有被鎖定或是停權 , 且上面的資料庫檢查是正確的。 就登入巴!!!
	if(isset($r[1]->status) AND $r[1]->status == 1 AND $error['success'] == true ) {
    // 此為 user 登入成功的處理

    // 將使用者 agent 資訊存到 session --被取代
    $_SESSION['agent'] = $r[1];

    // 取得使用者預設的語系
    $_SESSION['lang'] = $r[1]->lang;

    // 因為把額度帳戶拆分為另外一個表，所以需要下面這一段。
    // 檢查表內有無此代理商帳號，沒有就建立。有的話帶入 balance 餘額欄位到 session
    // $_SESSION['agent']->balance = get_gpk2_member_wallet_account_balance($r[1]->id, $r[1]->account);
    // 檢查是否建立了錢包資料，如果有資料的話，把錢包帶進來。沒有的話建立錢包。
    $rcode = get_member_wallets($r[1]->id);
    if($rcode['code'] == '1') {
      // 同時更新了 $_SESSION['agent'] 變數
      $error['success']   = true;
      $error['messages']  = $logger;
    }else{
      // 重新取得錢包資料異常
      $logger = $tr['Regain wallet data anomalies'].$rcode['messages'];
      $error['success']   = false;
      $error['messages']  = $logger;
    }


    // 上面已經珊除了，為了確保沒有意外，在檢查刪除一次.
    // 判斷並刪除，確保所有的 session 只有一個登入者 , delete other user
    //Agent_runRedisKeepOneUser();
    // 要有登入的 session 才可以砍除其他 session


	}else{
    // $logger = '你的帳號已經被鎖定，請聯絡客服人員處理。';
		$logger = $tr['Your account has been locked, please contact the customer service staff.'];
		$logger = $u['account'].','.$u['password'].',login error';
    $error['success']   = false;
    $error['messages']  = $logger;
		// die();
  }


  // 依據上面的狀態, 顯示結果並寫入資料庫
  if($error['success'] == true) {
    // show and http 302
    $logger = $u['account'].' '.$tr['Login succesful'].'...';
    echo $logger;
    memberlog2db($u['account'],'agent login','info', $error['messages']);

    // 正確的話, unset 掉 captcha 變數
    unset($_SESSION['captcha']);

    // 轉址到正式登入頁面
    echo '<script>document.location.href="home_daily.php";</script>';
  }else{

    if($_SESSION['captcha']['errorcount'] >= 2) {
      $logger = 'Agent login error '.$_SESSION['captcha']['errorcount'].' times.';
      $_SESSION['captcha']['errorcount'] = $_SESSION['captcha']['errorcount'] + 1;
      echo '<script>window.location.reload();</script>';
      die($logger);
    }else{
      // $logger = $u['account'].', agent login error!';
      $_SESSION['captcha']['errorcount'] = $_SESSION['captcha']['errorcount']+1;
      $logger = 'Agent login error '.$_SESSION['captcha']['errorcount'].' times.';
      echo $logger;
      memberlog2db($u['account'],'agent login','notice', $error['messages']);
    }
  }


*/

}elseif($action == 'logout') {
// ----------------------------------------------------------------------------
// 會員登出，並清除 session
// ----------------------------------------------------------------------------

  // 登出註銷 redis server record
  if(isset($_SESSION['agent']) OR isset($_SESSION['member']) ) {

    // 如果是 agent 存在的話
    if(isset($_SESSION['agent'])){
      // logout 紀錄到 DB
      $logger = $_SESSION['agent']->account.','.$tr['Logout Account'];

      $msg         = $logger;
      $msg_log     = $msg;
      $sub_service = 'logout';
      // 原版
      // memberlogtodb($_SESSION['agent']->account, 'member', 'info', "$msg", $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);

      // 把後台 登入寫入memberlog的service改寫成administrator
      memberlogtodb($_SESSION['agent']->account, 'administrator', 'info', "$msg", $_SESSION['agent']->account, "$msg_log", 'b', $sub_service);



      // ------------------------------------
      // session_name().':'.session_id();
      // 刪除存在的 session in redis DB 1
      $value = $config['projectid'].'_back_'.$_SESSION['agent']->account;
      $session_id = session_id();
      // db 1 自己寫出來的 session, save member session data
      $sid = sha1($value).':'.$session_id;
      // var_dump($sid);
      $phpredis_sid = 'PHPREDIS_SESSION:'.$session_id;
      // 刪除 redisdb db1 sid
			$r[0] = Agent_runRedisDEL($phpredis_sid,0);
			// 從 $db 中刪除指定的 sid
			$r[1] = Agent_runRedisDEL($sid,$redisdb['db']);
    }

    // ------------------------------------
    // 全部 session 清光光
    // Unset all of the session variables.
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time()+7200, '/');
    }

    // Finally, destroy the session.
    session_destroy();

    // 清除 POST and URL reset
    echo '<script>document.location.href="index.php";</script>';
    // die('stop');


  }else{  // 根本沒有登入過,所以直接回到首頁
  	echo ' <script>document.location.href="index.php";</script>';
  }


}elseif($action == 'factor_check'){
  // -----------------------------------------------------------
  // 2FA檢查
  // 必須要2FA的驗證碼符合
  // 才做insert_indb() 寫進DB
  // 成功登入->home.php

  // user輸入的驗證碼
  $verify = isset($_POST['verify_code']) ? my_filter($_POST['verify_code'],"string") : "";
  // 帳號
  $a_account = isset($_POST['agent_account']) ? my_filter($_POST['agent_account'], "string") : "";

  // 取登入者帳號ID
  $to_get_login_acc = get_agent_id($a_account);
  $acc_id_result = runSQLall($to_get_login_acc);

  // 撈二階段驗證資料->金鑰
  $get_factor_data = check_security_setting($acc_id_result[1]->id);
  $get_factor_sql_result = runSQLall($get_factor_data);

  // 檢查驗證碼是否正確
  // 金鑰、user驗證碼
  $factor = check_factor_auth($get_factor_sql_result[1]->two_fa_secret,$verify,$ga);

  if($factor){
    // 2fa 檢查通過
    // 要Insert
    $fa_inserto_db = insert_indb($a_account);

    if($fa_inserto_db['success'] == true){
      // 成功，確定可以登入
      $msg         = $a_account.'两阶段验证，成功!';
      $msg_log     = $msg;
      $sub_service = 'login';
      memberlogtodb($a_account, 'member', 'info', "$msg",$a_account, "$msg_log", 'b', $sub_service);

      // 寫進DB
      $return_html = '<script>window.location="home.php";</script>';
      echo $return_html;
    }

  }else{
    // $tr['Verification code error'] = 驗證碼錯誤
    $logger = $tr['Verification code error'];

    $msg         = $a_account.'验证码错误，两阶段验证，失败!';
    $msg_log     = $msg;
    $sub_service = 'login';
    memberlogtodb($a_account, 'member', 'error', "$msg", $a_account, "$msg_log", 'b', $sub_service);


    $return_html = '<script>alert("'.$logger.'");window.location.reload();</script>';
    echo $return_html;
  }

  // -----------------------------------------------------------------------

}elseif($action == 'KeepOneUser') {
// ----------------------------------------------------------------------------
// user 有登入, 下只保留當下登入的使用者的 phpsession key
// ----------------------------------------------------------------------------
    runRedisKeepOneUser();



// ----------------------------------------------------------------------------
}elseif($action == 'test') {
// ----------------------------------------------------------------------------
// test developer
// ----------------------------------------------------------------------------
    var_dump($_POST);

}



?>
