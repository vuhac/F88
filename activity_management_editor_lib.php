<?php

// ---------------------------
// 活動代碼(英文小寫4碼)組合方式:
// id轉16進位，不足4碼補0到左側，再轉成英文
// id:1000 
// 轉16進位:3e8 => 03e8 => qxes

// 1006 = qxxu
// ----------------------------
function get_act_id(){
	global $actid_to_string;
	// 取得id
	$query_max_id_sql = <<<SQL
		SELECT coalesce(MAX(id),0) as max_id FROM root_promotion_activity
SQL;

	$query_max_id_sql_result = runsqlall($query_max_id_sql);
	if ($query_max_id_sql_result[0] > 0){
		if($query_max_id_sql_result[1]->max_id >= 1000){
			// 如果id>=1000
			$activity_id = $query_max_id_sql_result[1]->max_id + 1;
		}else{
			$activity_id = 1000;
		}
	}

	// 活動代碼
	$number_alphabet = array(
		0 => 'q',
		9 => 'r',
		8 => 's',
		7 => 't',
		6 => 'u',
		5 => 'v',
		4 => 'w',
		3 => 'x',
		2 => 'y',
		1 => 'z',

		'a' => 'a',
		'b' => 'b',
		'c' => 'c',
		'd' => 'd',
		'e' => 'e',
		'f' => 'f'
	); 
	
	$to_hex_actid = dechex($activity_id); //  id轉16進位再轉英文
	$the_act_serial = str_pad($to_hex_actid,4,'0',STR_PAD_LEFT); // 不足4碼，填充0到左侧
	$g = str_split($the_act_serial);

	
	preg_match_all('/(.)\1*/', $the_act_serial, $matches); 
	$matches = $matches[0]; 
	
	$a = [];
	foreach($g as $num){
		$a[] = $number_alphabet[$num];

	}
	
	$actid_to_string = implode("",$a);
	return $actid_to_string;
}

// 序號 6碼(小寫英文，數字)
function getRandom(){
    $serial = '';
    $alphabet_serial = 'abcdefghijklmnopqrstuvwxyz0987654321';
    $len = strlen($alphabet_serial);
 
    for($i = 0; $i <= 5; $i++){ 
        $serial .= $alphabet_serial[rand() % $len];
	}
    return $serial;
}

// -----------------------------
// 優惠碼組合:
// 活動代碼4碼 + 序號6碼 + 檢核碼2碼
// 
// 檢核碼組合方式:
// 活動代碼+序號=稽核碼
// 產生方法:
// 加密第一次:sha1 -> 再加密一次: base64_encode -> substr稽核碼(前2碼)
// 
// -----------------------------
function random_serial($promo,$id){
	//global $promo; //優惠碼數量
	global $actid_to_string; //活動代碼
	global $activity_id; //id


	$all_serial = '';
	// 產生指定數量的優惠碼
	for($i = 0; $i < $promo; $i++){
		// 序號
		$merge = getRandom();
		// 活動代碼4 + 序號6
		$all = $actid_to_string.$merge; 

		// 先加密第一次 sha1 ->再加密一次 base64_encode -> substr稽核碼
		$sha1_code = sha1($all); 
		// 加密
		$encode = base64_encode($sha1_code); // 加密sha1_code
		$get_encode_substr = substr(strtolower($encode),0,2); // 得到base64_encode的稽核碼

		// 活動代碼 序號 檢核碼
		$combine_all = $actid_to_string.$merge.$get_encode_substr;
		$all_serial .= "('{$actid_to_string}','{$combine_all}')," ; 

	}
	$all_serial = rtrim($all_serial,','); // 移除逗號
	
	if(empty($id) AND $promo != NULL){	
		$promo_sql=<<<SQL
			INSERT INTO root_promotion_code (activity_id,promo_id) VALUES $all_serial
SQL;
		$sql_result = runSQL($promo_sql);
	}

	return $all_serial;
}


// 網域 子網域
function search_domainname(){
	$sql=<<<SQL
		SELECT domainname,jsonb_pretty(configdata) 
		FROM site_subdomain_setting  
		WHERE open = '1'
SQL;
	$result=runsqlall($sql);
	unset($result[0]);
	foreach($result as $domain_object){
		$domain['main_domain'][]=$domain_object->domainname;
		$subdomain = json_decode($domain_object->jsonb_pretty);
    	foreach ($subdomain as $subkey => $subvalue) {
			$sub_domain_show = $subdomain->$subkey->style->desktop->suburl.'/'.$subdomain->$subkey->style->mobile->suburl;
			$domain[$domain_object->domainname][]=$sub_domain_show;
		}
	}
	
	return $domain;
}
$search_domainname = search_domainname();

// 獎金類別
$coin_classification = array(
	"gtoken" 	=> $tr['Gtoken'],
	"cash" 		=> $tr['Franchise']
);

// 稽核
$audit_mode = array(
	"depositaudit"	=>	$tr['Deposit audit'],
    "shippingaudit"	=>	$tr['Preferential deposit audit'],
    "freeaudit"		=>	$tr['freeaudit']
 );

// 使用者限制
$ip_fpuser_req = array(
    "user_repeat"       =>  $tr['can not receive when user repeat'],
	"ip_repeat_no_fingerprint"  =>  $tr['ip repeat and no fingerprint can not receive']
);


// 活動啟用時間，註冊幾小時後才能參加
// 存款金額超過 預設0表示無條件限制
// 有效投注超過，預設0表示無條件限制
// 帐号注册时间(小时)
$other_req = array(
    "reg_member_time" => $tr['account register hours'],
    "desposit_amount" => $tr['actual deposit'],
	"betting_amount"  => $tr['effective betting amount more than']."<br>(".$tr['from one month before the event starts to the day before user receive'].")"
);



?>