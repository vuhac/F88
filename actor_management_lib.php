<?php
// 登入驗證
$examination_log = [0 => '停用', 1 => '啟用'];

// 狀態啟用
// 啟用=1, 關閉=0, 刪除=2
//$status_open = [0=>'停用',1=>'啟用'];
// 判斷是否顯示附加的限制條件，如果有需要請再底下增加
function actor_option_permission($name)
{
    switch ($name) {
        case 'manual_gcash_deposit':
            $option_array = ['deposit_cash_amount', 'deposit_cash_total_amount'];
            break;
        case 'manual_gtoken_deposit':
            $option_array = ['deposit_gotken_amount', 'deposit_gtoken_total_amount'];
            break;
        case 'site_api_config':
            $option_array = ['payment_flow_control'];
            break;
        case 'offer_management_detail':
            $option_array = ['add_edit_offer'];
            break;
        default:
            $option_array = [];
            break;
    }
    return $option_array;
}

function render_sub_category_checkboxes_html(
    $permission_id_map_actorname, $order_value, $actor_id_pk, $disab_txt, $default_checkbox_value = 'checked',$is_ops=false) {

    global $tr;
    $show_actor_html = '
			<div class="col-12 col-md-12 actorclass">
				<label>
					<input type="checkbox" name="input_name_actor"
						value="' . $actor_id_pk . '"
						class="' . $order_value . '_in_cl_actor_list"
						' . $default_checkbox_value . ' ' . $disab_txt . '>
					' . $permission_id_map_actorname[$actor_id_pk] . '
				</label>';
    if ($disab_txt=='disabled') {
        $ahref = '';
        $construction= '';
        // 2019/09/27 Damocels移除，不需要這一段
        // https://proj.jutainet.com/issues/2867
        // $construction= '('.$tr['in construction'].')';
    } else {
        $ahref = 'actor_management_editor.php?a=edit&snid=' . $actor_id_pk;
        $construction='';
    }
    if($is_ops){
        $pencil_show='  <a href="' . $ahref . '" target="_blank" class="' . $disab_txt . '">
					        <i class="fas fa-pencil-alt"></i>
                        </a>';
    }else{
        $pencil_show='';
    }

    $show_actor_html .=$pencil_show.$construction.'
				<br>
			</div>';
    return $show_actor_html;
}

function render_sub_category_select_html($permission_id_map_actorid, $permission_id_map_actorname, $actor_id_pk, $default_select_option = '')
{
    $show_actor_html['option']   = '<option value="' . $actor_id_pk . '" ' . $default_select_option . '>' . $permission_id_map_actorname[$actor_id_pk] . '</option>';
    $show_actor_html['eng_name'] = $permission_id_map_actorid[$actor_id_pk];
    return $show_actor_html;
}

function show_actor_option_html($actor, $name, $value)
{
    global $tr;
    if ($actor == 'manual_gcash_deposit' or $actor == 'manual_gtoken_deposit') {
        $output_html = '
			<input type="text" class="form-control options" name="' . $name . '" placeholder="ex.100.00"
			value="' . $value . '" style="ime-mode:disabled" onkeyup="return ValidateNumber(this,value)">
			';
    } elseif ($actor == 'site_api_config' OR $actor == 'offer_management_detail') {
        if ($value == '1') {
            $checked = 'checked';
        } else {
            $checked = '';
        }

        $output_html = '
			<label>
                <input type="checkbox" class="options" name="' . $name . '" value="" ' . $checked . '>'.$tr['enabled'].'
            </label>
		';
    }
    return $output_html;
}

//root_member->permission角色权限移除
function member_permission_del($snid)
{
    global $tr;
    $forever_delete_actor_from_member_sql = <<<SQL
					UPDATE root_member
					SET permission=permission::jsonb -'{$snid}'
					where id >=500
					AND id<=1000
SQL;
    $forever_delete_actor_from_member_sql_result = runSQLall($forever_delete_actor_from_member_sql);
    if (!$forever_delete_actor_from_member_sql_result[0]) {
        $logger = $tr['The role permission deletion of the administrator data failed, please contact customer service staff'];
        echo '<script>alert("' . $logger . '");</script>';
        die();
    }
    return true;
};

// 撈出全部角色
$actor_list_sql    = 'SELECT * FROM site_actor_permission WHERE "status" in (1)  ORDER BY actor_name ,actor_id ;';
$actor_list_result = runSQLall($actor_list_sql, 0, 'r');
// unset($actor_list_result[0]);
if ($actor_list_result[0] >= 1) {
    for ($i = 1; $i <= $actor_list_result[0]; $i++) {
        $permission_group_map_id[$actor_list_result[$i]->actor_group][] = $actor_list_result[$i]->id;
        $permission_id_map_actorid[$actor_list_result[$i]->id]          = $actor_list_result[$i]->actor_id;
        // $permission_id_map_actorname[$actor_list_result[$i]->id]        = $actor_list_result[$i]->actor_name;
        // $permission_id_map_actorname[$actor_list_result[$i]->id]        = $actor_list_result[$i]->actor_name_eng;

        if($_SESSION['lang'] == 'en-us'){
            //  選英文語系，顯示英文欄位名稱
            $permission_id_map_actorname[$actor_list_result[$i]->id]        = $actor_list_result[$i]->actor_name_eng;
        }else{
            $permission_id_map_actorname[$actor_list_result[$i]->id]        = $actor_list_result[$i]->actor_name;
        }
    }
}

// 撈出重複角色英文名
function actor_dupication()
{
    $actor_dupication_sql = <<<SQL
	SELECT actor_id FROM site_actor_permission
	WHERE status ='1'
	GROUP BY actor_id
	HAVING COUNT (*)>1
SQL;
    // echo($actor_dupication_sql);die();

    $actor_dupication_sql_result = runSQLall($actor_dupication_sql, 0, 'r');
    if ($actor_dupication_sql_result[0] >= 1) {
        for ($i = 1; $i <= $actor_dupication_sql_result[0]; $i++) {
            $actor_dupication[] = $actor_dupication_sql_result[$i]->actor_id;
        }
    }
    return $actor_dupication;
}

// 重覆角色陣列
function actor_dupication_ary()
{
    $combine_actor_dupication_forsql = '(\'' . implode("','", actor_dupication()) . '\')';
    $actor_dupication_sql_in         = <<<SQL
	SELECT * FROM site_actor_permission
	WHERE actor_id in {$combine_actor_dupication_forsql}
	AND status ='1'
SQL;
    $combine_actor_ary_result = runSQLall($actor_dupication_sql_in, 0, 'r');
    if ($combine_actor_ary_result[0] >= 1) {
        for ($i = 1; $i <= $combine_actor_ary_result[0]; $i++) {
            $final_actor_dupication[$combine_actor_ary_result[$i]->actor_group][$combine_actor_ary_result[$i]->actor_id][] = $combine_actor_ary_result[$i]->id;
        }
    }
    return $final_actor_dupication;
}

// 撈出重覆角色id，給編輯子帳號時，角色預設是否option選取
function actor_dupication_id_ary()
{
    $combine_actor_dupication_forsql = '(\'' . implode("','", actor_dupication()) . '\')';
    $actor_dupication_sql_in         = <<<SQL
	SELECT * FROM site_actor_permission
	WHERE actor_id in {$combine_actor_dupication_forsql}
	AND status ='1'
SQL;
    $combine_actor_ary_result = runSQLall($actor_dupication_sql_in, 0, 'r');
    if ($combine_actor_ary_result[0] >= 1) {
        for ($i = 1; $i <= $combine_actor_ary_result[0]; $i++) {
            $final_actor_dupication[] = $combine_actor_ary_result[$i]->id;
        }
    }
    return $final_actor_dupication;
}



// 不重覆角色陣列
function not_actor_dupication_ary()
{
    global $checkable_file_list;
    $combine_actor_dupication_forsql = '(\'' . implode("','", actor_dupication()) . '\')';
    $actor_dupication_sql_in = <<<SQL
        SELECT * FROM site_actor_permission
        WHERE actor_id not in {$combine_actor_dupication_forsql}
        AND status ='1'
    SQL;
    $combine_actor_ary_result = runSQLall($actor_dupication_sql_in, 0, 'r');
    if ($combine_actor_ary_result[0] >= 1) {
        for ($i = 1; $i <= $combine_actor_ary_result[0]; $i++) {
            $final_actor_dupication[$combine_actor_ary_result[$i]->actor_group][$combine_actor_ary_result[$i]->actor_id] = $combine_actor_ary_result[$i]->id;
        }
    }
    // $return_result=$checkable_file_list+$final_actor_dupication;
    // echo '<pre>', var_dump($final_actor_dupication), '</pre>'; exit();
    return $final_actor_dupication;
} // end not_actor_dupication_ary

// 撈出不重覆角色id，給編輯子帳號時，角色預設checkbox是否打勾
function not_actor_dupication_id_ary()
{
    $combine_actor_dupication_forsql = '(\'' . implode("','", actor_dupication()) . '\')';
    $actor_dupication_sql_in         = <<<SQL
	SELECT * FROM site_actor_permission
	WHERE actor_id not in {$combine_actor_dupication_forsql}
	AND status ='1'
SQL;
    $combine_actor_ary_result = runSQLall($actor_dupication_sql_in, 0, 'r');
    if ($combine_actor_ary_result[0] >= 1) {
        for ($i = 1; $i <= $combine_actor_ary_result[0]; $i++) {
            $final_actor_dupication[] = $combine_actor_ary_result[$i]->id;
        }
    }
    // var_dump($final_actor_dupication);die();
    return $final_actor_dupication;
}

// 判斷角色英文名稱，出現幾次，如果是重複角色，則可禁止修改群組名稱
function actor_name_times($actor_name)
{
    $sql = <<<SQL
	SELECT actor_id FROM site_actor_permission
	WHERE actor_id ='{$actor_name}'
SQL;
    $sql_result = runSQL($sql);
    // var_dump($sql_result);
    return $sql_result;
}

// 限制設定中文翻譯
$option_translation = array(
    'deposit_gotken_amount'       => $tr['Manually depositing a game currency limit'], // 人工存入游戏币单笔限额
    'deposit_gtoken_total_amount' => $tr['Manual deposit of total game currency'], // 人工存入游戏币总限额
    'deposit_cash_amount'         => $tr['Manual deposit of cash in a single limit'], // 人工存入现金单笔限额
    'deposit_cash_total_amount'   => $tr['Manual deposit of cash'], // 人工存入现金总限额
    'payment_flow_control'        => $tr['go to cash flow backstage'], // 前往金流后台
    'add_edit_offer'              => $tr['add edit offer'],
);

// 功能群組
$actor_group_name = array(
    'portal'                 => $tr['Home'],  // 首頁
    'member_agent'           => $tr['Members and Agents'], // 会员与代理商
    'accounting_management'  => $tr['Account Management'], // 帐务管理
    'marketing_management'   => $tr['profit and promotion'], // 营销管理
    'system_management'      => $tr['System Management'], // 系统管理
    'reports'                => $tr['Various reports'], // 各式报表
    'webmaster_tools'        => $tr['webmaster'],
    'maintenance_management' => $tr['maintenance management'],
);

// 大分類順序
$classification_order = [
    0 => 'member_agent',
    1 => 'system_management',
    2 => 'marketing_management',
    3 => 'accounting_management',
    4 => 'maintenance_management',
    5 => 'webmaster_tools',
    6 => 'reports',
    7 => 'portal',
];

if(!(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND in_array($_SESSION['agent']->account, $su['ops']))) {
    $is_ops=false;
}else{
    $is_ops=true;
}


//檔案讀取可勾選之檔案列表
$checkable_file_list[] ='protal_setting_deltail';   //會員端詳細資料設定
$checkable_file_list[] ='message';                  //站內訊息
$checkable_file_list[]='receivemoney_management';  //彩金發放;
// $checkable_file_list['system_management']['protal_setting_deltail'] =50;   //會員端詳細資料設定
// $checkable_file_list['marketing_management']['message'] =3;                  //站內訊息
// $checkable_file_list['marketing_management']['receivemoney_management'] =44;  //彩金發放;

