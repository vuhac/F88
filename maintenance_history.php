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


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// $tr['Add affiliate associates'] = '新增加盟聯營股東';
$extend_head					= '';
$extend_js						= '';
$function_title				= '歷史維護紀錄';
$link_title           = '<a href="maintenance.php">維護設定</a>';
// 主要內容 -- title
$panelbody_content		= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
// $tr['Members and Agents'] = '會員與加盟聯營股東';
// $tr['Home'] = '首頁';
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">'.$tr['Home'].'</a></li>
  <li><a href="#">'.$tr['Members and Agents'].'</a></li>
  <li class="active">歷史維護紀錄</li>
</ol>';
$refs = '$refs';
// ----------------------------------------------------------------------------


// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent']) AND $_SESSION['agent']->therole == 'R' AND  in_array($_SESSION['agent']->account, $su['ops'])) {

  $panelbody_content = <<<HTML
          <maintenance-history
            :FV="FV"
            :apiurl="apiurl"
            ref="maintenance_history"
          ></maintenance-history>
  HTML;
  


$extend_js = <<<HTML

<script type="module">
import formValidation from './src/components/form_validation.js'

    new Vue({
      vuetify: new Vuetify(),
      el: '#app',
      components: {
        'maintenance-history': httpVueLoader('./src/components/maintenance/maintenance_history.vue')
      },
      data: {
        FV: formValidation.rules,
        apiurl: url
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
                vm.$refs.maintenance_history.init()
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
 
// 初始化 ws 对象
 
// var ws = new WebSocket('wss://rabbitmq.jutainet.com:53533/ws');
// var client = Stomp.over(ws);
 
// // 定义连接成功回调函数
// var on_connect = function(x) {
//     //data.body是接收到的数据
//     client.subscribe("/exchange/front_notify", function(data) {
//       const datas = JSON.parse(data.body)
//       alert('一筆維護狀態已更新')
//     });
// };

// var on_error = function(x) {
//   console.log(error)
//   this.connected = false
// }
 
// // 连接RabbitMQ
// client.connect('java', '12345', on_connect, on_error, 'demo_mq');
</script>
HTML;

}else{

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
