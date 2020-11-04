<?php

// ----------------------------------------------------------------------------
// Features:	後台--維護功能
// File Name:	maintenance.php
// Author:		WeiLun
// Related:   maintenance.vue
// Log:
//
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

//var_dump($_SESSION);
// var_dump(session_id());

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------
// 網域 子網域
// function search_domainname(){
// 	$sql=<<<SQL
// 		SELECT domainname,jsonb_pretty(configdata) 
// 		FROM site_subdomain_setting  
// 		WHERE open = '1'
// SQL;
// 	$result=runsqlall($sql);
// 	unset($result[0]);
// 	foreach($result as $domain_object){
//     $domain['main_domain'][]=$domain_object->domainname;
//     $subdomain = json_decode($domain_object->jsonb_pretty);
//     	foreach ($subdomain as $subkey => $subvalue) {
// 			$sub_domain_show = $subdomain->$subkey->style->desktop->suburl.'/'.$subdomain->$subkey->style->mobile->suburl;
// 			$domain[$domain_object->domainname][]=$sub_domain_show;
// 		}
// 	}
	
// 	return $domain;
// }
// 	// 網域
// 	$result = search_domainname();
// 	$domain_list = [];
//   	foreach($result['main_domain'] as $domainvalue){
// 			array_push($domain_list,$domainvalue);
//     }

$domain_data = runSQLall('SELECT * FROM site_subdomain_setting WHERE open = 1;');
$domain_list=array();
$domain_id_val=array();
foreach ($domain_data as $key => $value) {
  if($key == 0)
    continue;
  $subdomain_data=json_decode($value->configdata);
  $subdomain_list=array();
  foreach ($subdomain_data as $s_key => $s_value) {
    if($subdomain_data->$s_key->open == 1)
      if(isset($subdomain_data->$s_key->websiteName) AND $subdomain_data->$s_key->websiteName!='')
        $websiteName = $subdomain_data->$s_key->websiteName.'(%s)';
      else
        $websiteName = '%s';
      $websitePath = $subdomain_data->$s_key->style->desktop->suburl;
      $websiteMobilePath = $subdomain_data->$s_key->style->mobile->suburl;
      array_push($subdomain_list,array('sid'=>$s_key,'subdomainname'=>sprintf($websitePath)));
      array_push($subdomain_list,array('sid'=>$s_key,'subdomainname'=>sprintf($websiteMobilePath)));
  }
  $domain_list[$value->domainname]=array('id'=>$value->id,'subdomain'=>$subdomain_list);
}

$domain_array=[];
foreach ($domain_list as $key=>$value) {
  array_push($domain_array,$key);
}

$subdomain_array=[];
foreach ($domain_list as $key=>$value) {
  //var_dump($value['subdomain']);
  $domain = $key;
  foreach ($value['subdomain'] as $s_key => $s_value) {
    array_push($subdomain_array,'https://'.$s_value['subdomainname'].'.'.$domain);
  }
}
$domainList = json_encode($domain_array);
$subdomainList = json_encode($subdomain_array);
$SESSION = in_array($_SESSION['agent']->account, $su['ops']);
// ----------------------------------------------------------
// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// $tr['Add affiliate associates'] = '新增加盟聯營股東';
$extend_head					= '';
$extend_js						= '';
$function_title				= '維護設定';
$link_title           = '<a href="maintenance_history.php">歷史維護紀錄</a>';
// 主要內容 -- title
$panelbody_content		= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['Members and Agents'] = '會員與加盟聯營股東';
// $tr['Home'] = '首頁';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Members and Agents'].'</a></li>
  <li class="active">維護設定</li>
</ol>';
$refs = '$refs';
// ----------------------------------------------------------------------------


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent'])) {

$panelbody_content = <<<HTML
          <maintenance
            :FV="FV"
            :apiurl="apiurl"
            :domain= "domain"
            :website="website"
            :identity="identity"
            ref="maintenance"
          ></maintenance>
HTML;


$extend_js = <<<HTML

<script type="text/javascript">
    let subdomainList = $subdomainList
    subdomainList.push(domain)
    var website = subdomainList
    var identity = '$SESSION'
</script>

<script type="module">
import formValidation from './src/components/form_validation.js'

    new Vue({
      vuetify: new Vuetify(),
      el: '#app',
      components: {
        'maintenance': httpVueLoader('./src/components/maintenance/maintenance.vue')
      },
      data: {
        FV: formValidation.rules,
        domain: domain,
        apiurl: url,
        website: website,
        identity: identity
      },
      mounted() {
        this.connect()
      },
      methods: {
        connect() {
          const vm = this
          vm.client = Stomp.client('wss://rabbitmq.jutainet.com:53533/ws')

          // vm.client.debug = null
          vm.client.connect(
            'java',
            '12345',
            (frame) => {
              // console.info('连接成功!');
              this.connected = true

              vm.client.subscribe('/exchange/front_notify', function(data) {
                const datas = JSON.parse(data.body)
                vm.$refs.maintenance.init()
              })
            },
            (error) => {
              // console.info('连接失败!')
              console.log(error)
              this.connected = false
            },
            'demo_mq'
          )
        }
      }
    });

     
if (typeof WebSocket == 'undefined') {
    console.log('不支持websocket')
}
 
</script>
HTML;

} else{

  // 沒有登入權限的處理
  $panelbody_content = $panelbody_content.'
  <br>
  <div class="row">
	  <div class="col-12 col-md-12">
      <div class="alert alert-danger">
      此页面只允许特定帐号存取
      </div>
    </div>
  </div>
  ';

}

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] 		= $tr['host_descript'];
$tmpl['html_meta_author']	 				= $tr['host_author'];
$tmpl['html_meta_title'] 					= $function_title.'-'.$tr['host_name'];

// 頁面大標題
$tmpl['page_title']								= $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head']							= $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js']								= $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] 			= $function_title;
// 次要內容 -- title
$tmpl['paneltitle_link'] 			= $link_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $panelbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/vue.tmpl.php");
?>
