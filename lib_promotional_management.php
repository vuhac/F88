<?php
function get_promotion_classification()
{
    $sql = <<<SQL
  SELECT * FROM (
    SELECT DISTINCT ON (classification) classification, sort
    FROM root_promotions
    WHERE status != 2
    ORDER BY classification
  ) t
  ORDER BY sort;
SQL;

    $result = runSQLall($sql);

    if (empty($result[0])) {
        $error_text = '优惠分类查询错误或暫無優惠';
        return array('status' => false, 'result' => $error_text);
    }

    unset($result[0]);
    return array('status' => true, 'result' => $result);
}
// 搜尋框內的分類
function get_promotion_classification_bydomain($desktop_domain, $mobile_domain)
{
    $sql = <<<SQL
  SELECT * FROM (
    SELECT DISTINCT ON (classification) classification, sort,id,name,status,classification_status
    FROM root_promotions
    WHERE ((desktop_domain = '{$desktop_domain}' AND desktop_domain IS NOT NULL)
    OR (mobile_domain = '{$mobile_domain}' AND mobile_domain IS NOT NULL))
    AND status NOT IN (2)
    AND classification_status != 0
    ORDER BY classification
  ) t
  ORDER BY sort;
SQL;
    // var_dump($sql);die();
    $result = runSQLall($sql);

    if (empty($result[0])) {
        $error_text = '优惠分类查询错误或暫無優惠';
        return array('status' => false, 'result' => $error_text);
    }

    unset($result[0]);
    return array('status' => true, 'result' => $result);
}

function combination_classification_html($arr)
{
    global $tr;
    $span_html = '';

    foreach ($arr as $k => $v) {
        $span_html .= <<<HTML
    <span class="label label-primary" id="classification_{$v->classification}">{$v->classification}</span>
HTML;
    }

    $html = <<<HTML
  <form>
    <div class="form-group">
      <label class="control-label">{$tr['created a discounted category']} </label>
      {$span_html}
    </div>
  </form>
HTML;

    return $html;
}

function get_domain_data_byid($id)
{
    $sql = <<<SQL
  SELECT *
  FROM site_subdomain_setting
  WHERE id = '{$id}';
SQL;
    $result = runSQLall($sql);
    // var_dump($result);die();

    if (empty($result[0])) {
        $error_text = '网域查询错误';
        return array('status' => false, 'result' => $error_text);
    }

    // unset($result[0]);
    return array('status' => true, 'result' => $result);
}

function get_all_promotion()
{
    global $tzonename;

    $sql = <<<SQL
  SELECT * ,
        to_char((effecttime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS effecttime,
        to_char((endtime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS endtime
  FROM root_promotions
  WHERE status != '2'
  ORDER BY seq;
SQL;

    $result = runSQLall($sql);

    if (empty($result[0])) {
        $error_text = '优惠查询错误';
        return array('status' => false, 'result' => $error_text);
    }

    unset($result[0]);
    return array('status' => true, 'result' => $result);
}
// 取分類內的活動
function get_promotion_bydomain($desktop_domain, $mobile_domain)
{
    global $tzonename;
    $sql = <<<SQL
  SELECT * ,
        to_char((effecttime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS effecttime,
        to_char((endtime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS endtime
  FROM root_promotions
  WHERE status != '2'
  AND (desktop_domain = '{$desktop_domain}'
  OR mobile_domain = '{$mobile_domain}')
  ORDER BY sort;
SQL;
    // var_dump($sql);die();
    $result = runSQLall($sql);

    if (empty($result[0])) {
        $error_text = '优惠查询错误';
        return array('status' => false, 'result' => $error_text);
    }

    unset($result[0]);
    return array('status' => true, 'result' => $result);
}

function get_designatedclassification_promotional($classification)
{
    global $tzonename;

    $sql = <<<SQL
  SELECT * ,
        to_char((effecttime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS effecttime,
        to_char((endtime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS endtime
  FROM root_promotions
  WHERE status != '2'
  AND classification = {$classification}
  ORDER BY seq;
SQL;

    $result = runSQLall($sql);

    if (empty($result[0])) {
        $error_text = '指定分类 : ' . $classification . ' 优惠查询错误';
        return array('status' => false, 'result' => $error_text);
    }

    unset($result[0]);
    return array('status' => true, 'result' => $result);
}

function get_designatedid_promotional($id)
{
    global $tzonename;

    $sql = <<<SQL
  SELECT *,
        to_char((effecttime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS effecttime,
        to_char((endtime AT TIME ZONE '{$tzonename}'),'YYYY/MM/DD HH24:MI') AS endtime
  FROM root_promotions
  WHERE id = '{$id}'
  AND status != '2';
SQL;
    // var_dump($sql);die();
    $result = runSQLall($sql);

    if (empty($result[0])) {
        $error_text = '指定优惠查询错误';
        return array('status' => false, 'result' => $error_text);
    }

    return array('status' => true, 'result' => $result[1]);
}
// 單一優惠狀態修改
function update_promotional_status($id, $status)
{
    $sql = <<<SQL
  UPDATE root_promotions
  SET status = '{$status}'
  WHERE id = '{$id}';
SQL;
    // var_dump($sql);die();
    return runSQL($sql);
}

function get_desktop_mobile_domain($domain_id, $subdomain_id)
{
    $domain = [
        'desktop' => '',
        'mobile'  => '',
    ];

    $domain_id    = filter_var($domain_id, FILTER_SANITIZE_STRING);
    $subdomain_id = filter_var($subdomain_id, FILTER_SANITIZE_STRING);

    if ($domain_id == '' || $subdomain_id == '') {
        return ['status' => false, 'result' => '错误的请求'];
    }

    $domain_data = get_domain_data_byid($domain_id);
    // var_dump($domain_data);die();
    if (!$domain_data['status']) {
        return ['status' => false, 'result' => '错误的网域'];
    }

    if ($domain_data['result'][1]->open != 1) {
        return ['status' => false, 'result' => '网域关闭中或已删除'];
    }

    $subdomain_configdata = json_decode($domain_data['result'][1]->configdata);

    if ($subdomain_configdata->$subdomain_id->open != 1) {
        return ['status' => false, 'result' => '子网域关闭中或已删除'];
    }

    $desktop_subdomain = $subdomain_configdata->$subdomain_id->style->desktop->suburl ?? '';
    $mobile_subdomain  = $subdomain_configdata->$subdomain_id->style->mobile->suburl ?? '';

    if ($desktop_subdomain == '' || $mobile_subdomain == '') {
        return ['status' => false, 'result' => '错误的子网域'];
    }

    // $desktop_domain = $desktop_subdomain.'.'.$domain['result'][1]->domainname;
    // $mobile_domain = $mobile_subdomain.'.'.$domain['result'][1]->domainname;
    $domain = [
        'domain_id'    => $domain_id,
        'subdomain_id' => $subdomain_id,
        'desktop'      => $desktop_subdomain . '.' . $domain_data['result'][1]->domainname,
        'mobile'       => $mobile_subdomain . '.' . $domain_data['result'][1]->domainname,
    ];

    return ['status' => true, 'result' => $domain];
}

function update_promotional(array $promotional)
{
    $sql = <<<SQL
  UPDATE root_promotions
  SET processingaccount = '{$promotional['processingaccount']}',
      name = '{$promotional['name']}',
      effecttime = '{$promotional['effecttime']}',
      endtime = '{$promotional['endtime']}',
      status = '{$promotional['status']}',
      classification = '{$promotional['classification']}',
      mobile_show = '{$promotional['mobile_show']}',
      desktop_show = '{$promotional['desktop_show']}',
      bannerurl_effect = '{$promotional['bannerurl_effect']}',
      content = '{$promotional['content']}',
      bannerurl_end = '{$promotional['bannerurl_end']}',
      update = now() ,
      desktop_domain = '{$promotional['desktop_domain']}',
      mobile_domain = '{$promotional['mobile_domain']}',
      show_promotion_activity = '{$promotional['show_promotion_activity']}',
      classification_status = '{$promotional['classification_status']}'
  WHERE id = '{$promotional['id']}';
SQL;
    // var_dump($sql);die();
    return runSQL($sql);
}

function insert_promotional(array $promotional)
{
    $sql = <<<SQL
  INSERT INTO root_promotions
  (
    "processingaccount", "name", "effecttime", "endtime", "status",
    "classification", "mobile_show", "desktop_show", "sort", "bannerurl_effect",
    "content", "bannerurl_end", "update", "desktop_domain", "mobile_domain","show_promotion_activity","classification_status"
  ) VALUES (
    '{$promotional['processingaccount']}', '{$promotional['name']}', '{$promotional['effecttime']}', '{$promotional['endtime']}', '{$promotional['status']}',
    '{$promotional['classification']}', '{$promotional['mobile_show']}', '{$promotional['desktop_show']}', '{$promotional['sort']}', '{$promotional['bannerurl_effect']}',
    '{$promotional['content']}', '{$promotional['bannerurl_end']}', now(), '{$promotional['desktop_domain']}', '{$promotional['mobile_domain']}','{$promotional['show_promotion_activity']}','{$promotional['classification_status']}'
  );
SQL;
    // var_dump($sql);die();
    return runSQL($sql);
}

// function get_tzonename($tz)
// {
//   // 轉換時區所要用的 sql timezone 參數
//   $tzsql = "select * from pg_timezone_names where name like '%posix/Etc/GMT%' AND abbrev = '".$tz."';";
//   $tzone = runSQLALL($tzsql);

//   if($tzone[0]==1) {
//     $tzonename = $tzone[1]->name;
//   } else {
//     $tzonename = 'posix/Etc/GMT-8';
//   }

//   return $tzonename;
// }

function CheckFile($file, $fileextension = array('jpg', 'png', 'bmp'))
{
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (in_array($extension, $fileextension)) {
        return true;
    } else {
        //echo '不允許該檔案格式';
        return false;
    }
}

function cdnupload($up_file, $del_file = null)
{
    global $config;
    global $tr;
    $cdn = new CDNConnection($up_file);
    //檔案類型確認
    if ($cdn->CheckFile(array('jpg', 'png', 'bmp')) != true) {
        echo '<script>alert("'.$tr['upload failed Format error'].'jpg,png,bmp");</script>';
        die();
    }
    //上傳檔案
    $res = $cdn->UploatFile('upload/promotions/');
    if ($res['res'] != 1) {
        echo '<script>alert("' . $up_file['name'] . $tr['upload failed'].'");</script>';
        die();
    }
    //刪除舊有檔案
    if ($del_file != null && $del_file != '') {
        $del_res = DeleteCDNFile('upload/promotions/',$del_file);
        /*
        if ($del_res['res'] != 1) {
        echo '<script>alert("' . $up_file['name'] . '删除旧档失败");</script>';
        die();
        }*/
        //var_dump($del_res);
    }
    //var_dump($res);
    return $res['url'];
}

// 活動優惠碼管理
function get_promotion_activity(){
    $sql = <<<SQL
        SELECT *
        FROM root_promotion_activity 
        WHERE activity_status = 1 
        AND effecttime <= current_timestamp 
        AND endtime >= current_timestamp
SQL;
    $result = runSQLall($sql);
    unset($result[0]);
    
    return $result;
}

function sql($query_array){

    $sql=<<<SQL
    SELECT * FROM root_promotions
    WHERE ((desktop_domain = '{$query_array['domain_name']}' AND desktop_domain IS NOT NULL)
        OR (mobile_domain = '{$query_array['sub_name']}' AND mobile_domain IS NOT NULL))
    AND status != '2'
    AND classification_status != '0'
SQL;

//     $sql=<<<SQL
//     SELECT * FROM root_promotions
//     WHERE (status != '2' AND classification_status != 0 AND (desktop_domain = '{$query_array['domain_name']}' AND desktop_domain IS NOT NULL)
//         OR (mobile_domain = '{$query_array['sub_name']}' AND mobile_domain IS NOT NULL))
// SQL;
    return $sql;
}

function sql_new(){
    $sql=<<<SQL
    SELECT * FROM root_promotions as pro
SQL;
    return $sql;
}

function combine_sql($query_array){
    $check = 0;
    $return_sql = '';

    $name = isset($query_array['name']) ? $query_array['name'] : '';
    $start_date = isset($query_array['start_date']) ? $query_array['start_date'] : '';
    $end_date = isset($query_array['end_date']) ? $query_array['end_date'] : '';
    $category = isset($query_array['category']) ? $query_array['category'] : 'undefined';
    $status = isset($query_array['status']) ? $query_array['status'] : '';
    if($status != '' AND is_array($status)){
        $implode_status = implode("','",$status);
    }else{
        $implode_status = $status;
    }
    // var_dump($start_date);die();

    if($query_array['domain_name'] != '' AND $query_array['sub_name'] != ''){
        $return_sql .=<<<SQL
            ((desktop_domain = '{$query_array['domain_name']}' AND desktop_domain IS NOT NULL)
            OR (mobile_domain = '{$query_array['sub_name']}' AND mobile_domain IS NOT NULL))
            AND status != 2
            AND classification_status != 0
SQL; 
            $check = 1;
    }else{
        die();
    }

    // 名稱
    if($name != ''){
        if($check == 1){
            $return_sql .= <<<SQL
            AND
SQL;
        }
        $return_sql .=<<<SQL
        name = '{$query_array['name']}'
SQL; 
        $check = 1;
    }

    // 開始
    if($start_date != '' AND $end_date != ''){
        if($implode_status != 2){
            if($check == 1){
                $return_sql .= <<<SQL
                AND 
SQL;
            }
            $return_sql .=<<<SQL
                effecttime <= '{$end_date}'  AND endtime >= '{$start_date}'
SQL;
            $check = 1;
        }/*else{
            // 已過期
            if( $check == 1){
                $return_sql .= <<<SQL
                AND 
SQL;
            }
//             $return_sql .=<<<SQL
//                 effecttime <= '{$start_date}'  AND endtime <= now()
// SQL;
            $return_sql .=<<<SQL
            endtime >= '{$start_date}' AND endtime <= '{$end_date}'
SQL;
            $check = 1;
        }*/

    }elseif($start_date != '' AND $end_date == ''){
        if( $check == 1){
            $return_sql .= <<<SQL
            AND 
SQL;
        }
        $return_sql .=<<<SQL
            effecttime >= '{$start_date}'
SQL;
        $check = 1;

    }else{
        $return_sql .=<<<SQL
SQL;
    }

//     // 狀態
//     if($status != '' AND strpos($implode_status,'2') != true){
//         if($check == 1){
//              $return_sql .= <<<SQL
//                 AND
// SQL;
//         }
//         $return_sql .=<<<SQL
//         status in ('{$implode_status}')
// SQL;
//         $check == 1;

//     }elseif($status != '' AND strpos($implode_status,'2') != false){
//         // 狀態有過期
//         if($check == 1){
//             $return_sql .= <<<SQL
//             AND 
// SQL;
//         }
//         $return_sql .=<<<SQL
//             endtime >= '{$start_date}' AND endtime <= '{$end_date}'
// SQL;
//         $check = 1;
//     }else{
//         $return_sql .=<<<SQL

// SQL;
//     }
    
    // 狀態
    if($status != '' AND $implode_status == 2){
        // 單選過期
        // if(strpos($implode_status,'1') !== false){
        //     $str = 1;
        // }else{
        //     $str = 0;
        // }

        // 狀態有過期
        if($check == 1){
            $return_sql .= <<<SQL
            AND 
SQL;
        }
//         $return_sql .=<<<SQL
//             endtime >= '{$start_date}' AND endtime <= '{$end_date}' --OR status in('{$implode_status}')
// SQL;
        
        $return_sql .=<<<SQL
            (endtime >= '{$start_date}' AND endtime <= '{$end_date}') AND effecttime <= '{$end_date}'
SQL;

        // $return_sql .=<<<SQL
        //     effecttime <= '{$start_date}' AND endtime >= '{$end_date}' --OR status in('{$implode_status}')
        // SQL;
        $check = 1;

    }elseif($status != '' AND strpos($implode_status,'2') == true){
        // 複選中有過期的
        if($check == 1){
            $return_sql .= <<<SQL
                AND
SQL;
        }
        $return_sql .=<<<SQL
        status in ('{$implode_status}')
SQL;
        $check == 1;
    }elseif($status != '' AND $implode_status != 2){
        if($check == 1){
            $return_sql .= <<<SQL
            AND 
SQL;
        }
        $return_sql .=<<<SQL
        status in('{$implode_status}')
SQL;
        $check == 1;
    }else{
        $return_sql .=<<<SQL
SQL;
    }
    
    // 單一分類
    if($category != ''){
        if($check == 1){
            $return_sql .= <<<SQL
            AND
SQL;
        }
        if(is_array($category)){
            $implode = implode("','",$category);
        }else{
            $implode = $category;
        }
        $return_sql .=<<<SQL
        classification in ('{$implode}')
SQL;
        $check = 1;
    }else{
        $return_sql .=<<<SQL
       
SQL;
    }

    // $return_result= ($check == 1) ?' AND ' . $return_sql:'';
    $return_result= ($check == 1) ?' WHERE ' . $return_sql:'';


    return $return_result;
}

// modal 編輯該分類名稱、狀態
// 此function會修改 所有 同分類名稱的優惠、狀態
function update_promotions_data($query_array){
    $promotions_sql=<<<SQL
        UPDATE root_promotions SET classification = '{$query_array['new_category']}' ,
        classification_status = '{$query_array['cat_switch']}', 
        desktop_domain = '{$query_array['modal_domain_name']}',
        mobile_domain = '{$query_array['modal_sub_name']}'
        WHERE classification = '{$query_array['origin']}' 
SQL;
    // echo '<pre>', var_dump($promotions_sql), '</pre>';
    // die();
    $promo_result = runSQL($promotions_sql);

    return $promo_result;
}
function update_classification_data($query_array){
    
    $classification_sql = <<<SQL
    UPDATE root_promotions_classification SET classification_name = '{$query_array['new_category']}' ,
    status = '{$query_array['cat_switch']}',desktop_domain = '{$query_array['modal_domain_name']}' ,
    mobile_domain = '{$query_array['modal_sub_name']}'
    WHERE classification_name = '{$query_array['origin']}' 
SQL;
    // echo '<pre>', var_dump($classification_sql), '</pre>';
    // die();
    $classification_result = runSQL($classification_sql);
    // var_dump($classification_result);die();
    return $classification_result;
}

// 啟用/不啟用 tab
function switch_tab($domain_name,$sub_name,$classification_query){
    // 兩張表都有的分類
    $sql=<<<SQL
    SELECT DISTINCT ON(classification)
        pro.id,pro.classification,
        pro.desktop_domain,pro.mobile_domain,
        pro.status,
        pro.classification_status,

        cla.id,
        cla.classification_name,
        cla.desktop_domain,cla.mobile_domain,
        cla.status

        FROM root_promotions AS pro
        JOIN root_promotions_classification AS cla
        ON pro.classification = cla.classification_name	

        WHERE ((pro.desktop_domain = '{$domain_name}' AND pro.desktop_domain IS NOT NULL)
        OR (pro.mobile_domain ='{$sub_name}' AND pro.mobile_domain IS NOT NULL))
        AND pro.status != 2
        {$classification_query} 
SQL;
    // var_dump($sql);die();
    $result = runSQLall($sql);
    unset($result[0]);

    return $result;
}

// 找root_promotions沒有的分類(新增分類)
function classification_sql($domain_name,$sub_name,$classification_query){
    $sql=<<<SQL
    SELECT DISTINCT ON (classification_name) classification_name,desktop_domain,mobile_domain,status
    FROM root_promotions_classification AS cla

    WHERE cla.classification_name NOT IN(SELECT classification FROM root_promotions)
    {$classification_query}
    AND ((cla.desktop_domain ='{$domain_name}'AND cla.desktop_domain IS NOT NULL)
    OR (cla.mobile_domain ='{$sub_name}' AND cla.mobile_domain IS NOT NULL))
SQL;
    // var_dump($sql);die();
    $result = runSQLall($sql);
    unset($result[0]);

    return $result;
}
// 把promotions_classification和root_promotions的分類合再一起
function switch_tab_html_o($promotions_table,$classification_table){
    $tab_listedit = '';
   
    $combine = array_merge($promotions_table,$classification_table);
    // echo '<pre>', var_dump($combine), '</pre>';
    // die();
    foreach($combine as $cat_key => $cat_value){

        if(isset($cat_value->classification)){
            $key_name['category'] = $cat_value->classification;
        }
        if(isset($cat_value->classification_name)){
            $key_name['category'] = $cat_value->classification_name;
        }
        if(isset($cat_value->classification_status)){
            $key_name['status'] = $cat_value->classification_status;
        }elseif(isset($cat_value->status)){
            $key_name['status'] = $cat_value->status;
        }
        // $key_name['desktop'] = $cat_value->desktop_domain;
        // $key_name['mobile'] = $cat_value->mobile_domain;
        // $key_name['status'] = isset($cat_value->classification_status) ? $cat_value->classification_status : '';

        // $key_name['desktop'] = $cat_value->desktop_domain;
        // $key_name['mobile'] = $cat_value->mobile_domain;
        // $key_name['status'] = isset($cat_value->status) ? $cat_value->status : '';

        if($key_name['status'] == 1){
            $status = 'checked';
        }else{
            $status = '';
        }

        // modal 優惠分類管理
        $tab_listedit .= <<<HTML
        <div class="row mb-3 d-flex align-items-center row_category" role="tabpanel" aria-labelledby="nav-home-tab" id="{$key_name['category']}">          
            <div class="col-4 word-break-all">
                <input type="text" id="category_{$key_name['category']}" name="orig_name" class="form-control" value="{$key_name['category']}" disabled>
            </div>
            <div class="col-5" id="categoryedit_{$key_name['category']}">
                <input type="text" name= "new_name" class="form-control" placeholder="修改名称" value="">
            </div>

            <div class="col-3 pl-5">
                <div class="material-switch pull-left">
                    <input id="categoryopen_{$key_name['category']}" name="" class="checkbox_switch_cat" data-name = "{$key_name['category']}" value="{$key_name['category']}" type="checkbox" {$status}>
                    <label for="categoryopen_{$key_name['category']}" class="label-success"></label>
                </div>
            </div>
        </div>
HTML;
    }
    return $tab_listedit;
}

// 新增或編輯優惠時，撈的分類
// 因為會有新增的分類名稱，所以新增名稱另外放在root_promotions_classification內
function get_classification_by_domain($pro_result,$cl_result){
    
    $combine = array_merge($pro_result,$cl_result);
    if (empty($combine)) {
        $error_text = '优惠分类查询错误或暫無優惠';
        return array('status' => false, 'result' => $error_text);
    }  
    
    return array('status' => true, 'result' => $combine);
}

function check_promotion_isset($query_array){

    $sql=<<<SQL
    SELECT DISTINCT ON(classification)
    pro.classification,
    pro.desktop_domain,pro.mobile_domain,
    -- pro.status
    FROM root_promotions AS pro
    WHERE classification = '{$query_array['new_category']}'

    AND pro.desktop_domain = '{$query_array['modal_domain_name']}'
    AND pro.mobile_domain = '{$query_array['modal_sub_name']}'
    -- AND pro.status != 2
SQL;
    // echo '<pre>', var_dump($sql), '</pre>';
    // die();
    $result = runSQLall($sql);
    return $result;
}

function check_classification_exist($query_array){
    $sql = <<<SQL
    SELECT classification_name FROM root_promotions_classification 
    WHERE classification_name = '{$query_array['new_category']}'
    AND desktop_domain = '{$query_array['modal_domain_name']}'
    AND mobile_domain = '{$query_array['modal_sub_name']}'
SQL;
    // echo '<pre>', var_dump($sql), '</pre>';
    // die();

    $result = runSQLall($sql);
    return $result;
}