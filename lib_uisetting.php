<?php
// ----------------------------------------------------------------------------
// Features:  後台 -- ui設定 function
// File Name: 
// Author:     orange
// Related: uisetting_action
// DB Table:
// Log:
// ----------------------------------------------------------------------------
/*
//進入uisetting頁面時會呼叫uisetting_init()進行以下動作
//1.確認存在該網域(子網域)id 若無則跳出並警告無效操作
//2.是否曾建立過uisetting資料     
    無則以預設資料建立一份新的
    有則會以預設資料進行uidata_check() 確保資料結構是最新版本
*/

/*function data_merge($default,$input,&$output){  
  if(empty($input)){
    $output = array_merge((array) $default, (array) $input);
  }else{
    $output = (array) $input;
  }
  foreach ($default as $key => $value) {    
    if(is_array($default[$key]) || is_object($default[$key]) ){
      $input[$key] = empty($input[$key])? []:$input[$key];
      data_merge($default[$key],$input[$key],$output[$key]);
    }
  }
}
$obj_merged = null;
data_merge($json_string_data,$ui_data,$obj_merged);*/

//將maindomain資料中 switch關閉的部分移除
function domain_data_merge($default,$input,&$output){  
  if(empty($input)){
    $output = array_merge((array) $default, (array) $input);
  }else{
    $output = (array) $input;
  }
  foreach ($default as $key => $value) {
    if(is_array($default[$key]) && !is_int($key)){
      $input[$key] = empty($input[$key])? []:$input[$key];      
      domain_data_merge($default[$key],$input[$key],$output[$key]);
    }else{
      if(isset($output[$key]['switch'])&&$output[$key]['switch']==0){
        unset($output[$key]);
      }
    }
  }
}

function get_domainbase_uidata($maindomain_id){
  $site_stylesetting_result = runSQLall('SELECT * FROM site_stylesetting WHERE id = '.$maindomain_id.';');
  if($site_stylesetting_result[0]==0){
  	return null;
  }

  $domainbase_ui_data = json_decode(urldecode($site_stylesetting_result[1]->jsondata),true);

  $json_string = file_get_contents(dirname(__FILE__)."/in/component_ui.json");
  $json_string_data = json_decode($json_string,true);
  $d_ui_data = null;
  domain_data_merge($json_string_data,$domainbase_ui_data,$d_ui_data);

  return json_encode($d_ui_data);
}

//uidata資料驗證
function uidata_check($component_id,$input_data,$orign_json_string){

        $json_string_data = json_decode($orign_json_string,true);
        $ui_data = json_decode($input_data,true);   
        
        //最新版本json與舊有資料merge(確保資料結構為最新版本)     
        $replace = array_replace_recursive($json_string_data,$ui_data);          
        $return_data = json_encode($replace);

        //若結構有變更 >向資料庫更新資料
        if($input_data != $return_data){
          $site_stylesetting_update = "UPDATE site_stylesetting SET jsondata = '".$return_data."' WHERE id = ".$component_id;
          runSQLall($site_stylesetting_update);
        }
        return $return_data;
}

//新增一份style(子網域)
function new_stylesheet($domain,$sub_domain,$sql_domainname,$json_string){      
  //新增空白ui json至stylesetting
      $stylesetting_insert =<<<SQL
      INSERT INTO site_stylesetting(name, jsondata, open)
      VALUES ('{$sql_domainname}_{$sub_domain}','{$json_string}',1);
SQL;
      runSQLall($stylesetting_insert);

      $style_maxid =runSQLall('SELECT max(id) FROM site_stylesetting ;');
      $style_maxid =$style_maxid[1]->max;
  //新增對應ui id至子網域設定json
      $subdomain_insert =<<<SQL
      UPDATE site_subdomain_setting
      SET configdata= jsonb_set( configdata, '{{$sub_domain}}',configdata -> '{$sub_domain}' ||'{"component":{$style_maxid}}' )
      WHERE id={$domain};
SQL;
      runSQLall($subdomain_insert);   
      return $style_maxid;
}
//取得uisetting json data 並驗證是否為新版本
function uisetting_init($domain,$sub_domain){  
    $json_string = file_get_contents(dirname(__FILE__)."/in/component_ui.json");
    $sql_result = runSQLall('SELECT domainname as domainname ,configdata as configdata,stylesettingid as stylesettingid FROM site_subdomain_setting WHERE id = '.$domain.';');    
    $sql_domainname = $sql_result[1]->domainname;

    if($sql_result[0]==0){
      return false;
    }else{
      $configdata = json_decode($sql_result[1]->configdata);
      if(!isset($configdata->$sub_domain))
        return false;
    }

    //主網域資料
    if($sql_result[1]->stylesettingid!==NULL){
      $maindomain_data = get_domainbase_uidata($sql_result[1]->stylesettingid);
    }else{
      $maindomain_data = null;
    }

    if(isset($configdata->$sub_domain->component))
      $component_id = $configdata->$sub_domain->component;
    else
      $component_id = null;

    //function:新增一份新的stylesheet
  //判斷是否設定過component
    if($component_id == null){
      $component_id = new_stylesheet($domain,$sub_domain,$sql_domainname,$json_string);
      $return_data = $json_string;
    }
    else{
      $check_style_data = runSQLall('SELECT * from site_stylesetting WHERE id ='.$component_id);
      if($check_style_data[0]==0){
        $component_id = new_stylesheet($domain,$sub_domain,$sql_domainname,$json_string);
        $return_data = $json_string;
      }
      else{
        //資料驗證
        $return_data = uidata_check($component_id,$check_style_data[1]->jsondata,$json_string);
      }
    }
    //取得網域名資訊      
      $sql_data = $configdata->$sub_domain;
      $sql_websiteName = (isset($sql_data->websiteName))? $sql_data->websiteName:'';      
      $sql_subdomainname = $sql_data->style->desktop->suburl .'/'. $sql_data->style->mobile->suburl;      
      $website_info = $sql_websiteName.'('.$sql_domainname.' > '.$sql_subdomainname.')';
  
  return ['id'=>$component_id,'data'=>$return_data,'site' => $website_info,'maindomain_data'=> $maindomain_data];
}

//新增一份style maindomain
function new_maindomain_stylesheet($domain,$sql_domainname,$json_string){
      
  //新增空白ui json至stylesetting
      $stylesetting_insert =<<<SQL
      INSERT INTO site_stylesetting(name, jsondata, open)
      VALUES ('{$sql_domainname}','{$json_string}',1);
SQL;
      runSQLall($stylesetting_insert);

      $style_maxid =runSQLall('SELECT max(id) FROM site_stylesetting ;');
      $style_maxid =$style_maxid[1]->max;
  //新增對應ui id至子網域設定json
      $subdomain_insert =<<<SQL
      UPDATE site_subdomain_setting
      SET stylesettingid= {$style_maxid}
      WHERE id={$domain};
SQL;
      runSQLall($subdomain_insert);   
      return $style_maxid;
}

function maindomain_uisetting_init($domain){
    $json_string = file_get_contents(dirname(__FILE__)."/in/component_ui.json");
    $sql_result = runSQLall('SELECT domainname as domainname ,stylesettingid as stylesettingid FROM site_subdomain_setting WHERE id = '.$domain.';');
    $sql_domainname = $sql_result[1]->domainname;

    if($sql_result[0]==0){
      return false;
    }

    $component_id = $sql_result[1]->stylesettingid;

    //function:新增一份新的stylesheet
  //判斷是否設定過component
    if($component_id == null){
      $component_id = new_maindomain_stylesheet($domain,$sql_domainname,$json_string);
      $return_data = $json_string;
    }
    else{
      $check_style_data = runSQLall('SELECT * from site_stylesetting WHERE id ='.$component_id);
      if($check_style_data[0]==0){
        $component_id = new_maindomain_stylesheet($domain,$sql_domainname,$json_string);
        $return_data = $json_string;
      }
      else{
        //資料驗證
        $return_data = uidata_check($component_id,$check_style_data[1]->jsondata,$json_string);
      }
    }
    //取得網域名資訊
      $sql_domainname = $sql_result[1]->domainname;    
      $website_info = $sql_domainname;
  
  return ['id'=>$component_id,'data'=>$return_data,'site' => $website_info];
}

