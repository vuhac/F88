<?php
// ----------------------------------------------------------------------------
// Features:	後台 -- 會員線上人數函式
// File Name:   online_lib.php
// Author:		Mavis
// Related:
// Log:
// 
// ----------------------------------------------------------------------------

// phpredis連接
function get_redis_connect(){

	global $redisdb;

	if(isset($redisdb['db'])) {
		$db = $redisdb['db'];
	}else{
		die('No select RedisDB');
	}

	$redis = new Redis();
	// 2 秒 timeout
	if($redis->pconnect($redisdb['host'], 6379, 1)) {
		// success
		if($redis->auth($redisdb['auth'])) {
			// echo 'Authentication Success';
		}else{
			echo "Authentication failed";
			return(0);
			// die('Authentication failed');
		}
	}else{
		// error
		echo "Connection Failed";
		return(0);
		// die('Connection Failed');
	}

	// 選擇 DB , Member 使用者自訂的 session 放在 $db
	$redis->select($db);
	// 檢查連線狀態，有連接成功 pong
	// echo "Server is running: ".$redis->ping();

	return($redis);
}

// 前台 強制登出
function front_force_logout($force_logout,$session_id){
	
	global $config;
	global $redisdb;
	
	// // 主機 PHP Session、前台、後台的 DB 的 REDISDB 編號, 此三個變數提供目前控管前後台的 DB 編號。
	$redisdb['db_session'] = 0;
	// $redisdb['db_front'] = 2;
	// $redisdb['db_back']  = 1;

	// 連結redis
	$redis_connect = get_redis_connect();
	// var_dump($redis_connect);
	
	$get_user_session = Agent_runRedisgetkeyvalue('*', $redisdb['db_front']);
	$member_list = array();
		for($i = 1;$i<$get_user_session[0];$i++){
			$member_list[$i] = explode(',',$get_user_session[$i]['value']);

			// ex: kt1_front_testagent 站台代碼_前台_帳號
			$account_value = explode('_', $member_list[$i][0]);
			// var_dump($account_value);die();

			if($account_value[2] == $force_logout){

				// 使用者帳號 sha1
				$sha_account_id = sha1($config['projectid'].'_front_'.$force_logout);

				// delete 符合的session key
				$delete_alive_key= $redis_connect->delete($session_id);
				// var_dump($delete_alive_key);
				
				// db 0 ，刪除自己 php session key
				$redis_connect->select(0);
				// echo "Server is running: ".$redis_connect->ping();
				
				// db 2
				$php_session_userkey = str_replace($sha_account_id,'PHPREDIS_SESSION',$session_id); 
				$delete_session_key = $redis_connect->delete($php_session_userkey);
				// var_dump($delete_session_key);

			}

		}

	return(1);
}

// 為了停用帳號，取會員ID
function get_member_data(){
	$sql = <<<SQL
		SELECT * FROM root_member 
SQL;

	$sql_result = runSQLall($sql);
	unset($sql_result[0]);
	// var_dump($sql_result);die();
	foreach ($sql_result as $val){
		$return[$val->account]['id']=$val->id;
		$return[$val->account]['status']=$val->status;
	}

	return $return;
}

// 停用會員帳號
function freeze_m_account($id){

	$update_sql =<<<SQL
		UPDATE root_member 
			SET status = '0' 
			WHERE id = '{$id}'
SQL;

	$sql_result = runSQLall($update_sql);

	unset($sql_result[0]);

	return $sql_result;
}


?>