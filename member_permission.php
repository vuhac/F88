<?php
// ----------------------------------------------------------------------------
// Features :	後台 -- 可以設定管理每個頁面的權限
// File Name: member_permission.php
// Author   :
// Related  :
// Log      :
// ----------------------------------------------------------------------------
// 只提供給 therole = R 管理員權限設定, 可以針對某個使用者設定是否可以登入該頁面
// 前後台會員網頁權限及IP登入權限表格 ,配合 R, A, M 三種屬性
// R + root 可以設定全部
// R 預設後台全部都可以進入, 除了特殊需要 root 身份的以外.
// A 預設只能看到 home.php
// 控制在 root_member 的 permission 欄位, JSON 格式控制
/*
array (size=4)
  'fornt_ip' =>   控制可以登入的 IP, 沒有設定就是全開. 有設定就是只允許這幾個IP
    array (size=2)
      0 => string '114.33.201.242' (length=14)
      1 => string '192.168.1.100' (length=13)
  'front_member' => 沒有設定就是全開, 有設定就是只允許這幾個檔案.
    array (size=4)
      0 => string 'home.php' (length=8)
      1 => string 'stationmail.php' (length=15)
      2 => string 'member.php' (length=10)
      3 => string 'wallets.php' (length=11)
  'back_ip' =>  控制可以登入的 IP, 沒有設定就是全開. 有設定就是只允許這幾個IP
    array (size=2)
      0 => string '114.33.201.242' (length=14)
      1 => string '192.168.1.100' (length=13)
  'back_agent' => 沒有設定就是全開, 有設定就是只允許這幾個檔案.
    array (size=4)
      0 => string 'home.php' (length=8)
      1 => string 'member.php' (length=10)
      2 => string 'member_account.php' (length=18)
      3 => string 'member_treemap.php' (length=18)
*/
// update   : 2017.10.22

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";



// 有列入的 IP ,就允許. 當為 NULL 時，表示沒有允許進入的 IP. 有的IP就是可以通行
// 前台的IP權限
$member_permission['fornt_ip'] = array("114.33.201.242", "192.168.1.100");
// 控制前台的頁面權限
$member_permission['front_member'] = array("home.php", "stationmail.php", "member.php", "wallets.php");
// 控制後台代理商IP權限
$member_permission['back_ip'] = array("114.33.201.242", "192.168.1.100");
// 後台代理商頁面權限
$member_permission['back_agent'] = array("home.php", "member_permission.php", "member.php", "member_account.php", "member_treemap.php");
// var_dump($member_permission);
// echo json_encode($member_permission);

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------



// --------------------------------------------------------------------------
// 管理權限 -- 搭配管理用的函式
// --------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------




// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title 		= '管理頁面權限設定';
// 擴充 head 內的 css or js
$extend_head				= '';
// 放在結尾的 js
$extend_js					= '';
// body 內的主要內容
$indexbody_content	= '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">首頁</a></li>
  <li><a href="#">系统管理 </a></li>
  <li class="active">'.$function_title.'</li>
</ol>';
// ----------------------------------------------------------------------------

// 有登入，且身份為管理員 R 才可以使用這個功能。
if(isset($_SESSION['agent'])) {


  // 限制可以登入的頁面 , 有設定的頁面才可以登入.
  // NULL --> 全部擋掉 all deny (default)
  // root 可以登入任何頁面, 不受這個功能限制 (config 綁定登入IP)
  // 前台IP限制
  $userpermission_html = '前台 IP 限制：'.json_encode($member_permission['fornt_ip']);
  // 前台頁面權限限制
  $userpermission_html = $userpermission_html.'<br>前台頁面權限限制'.json_encode($member_permission['front_member']);
  // 後台IP限制
  $userpermission_html = $userpermission_html.'<br>後台 IP 限制：'.json_encode($member_permission['back_ip']);
  // 後台頁面權限限制
  $userpermission_html = $userpermission_html.'<br>後台頁面權限限制'.json_encode($member_permission['back_agent']);

  // 定義每個頁面的用途陣列
  $pages_default = array(
      "home.php"              => "控制台首頁",
      "member.php"            => "会员与加盟联营股东 /會員查詢",
      "member_permission.php" => "會員權限管理",
      "member_account.php"    => "會員帳號查詢",
      "member_treemap.php"    => "加盟聯營股東樹狀架構",
  );
  // var_dump($pages_default);


  // var_dump($_SERVER);

  $content_row_html = '';

  $content_row_html = $content_row_html.'
  <tr>
    <td>會員帳號</td>
    <td>'.$_SESSION['agent']->account.'</td>
  </tr>
  ';

  $content_row_html = $content_row_html.'
  <tr>
    <td>會員身份(狀態)</td>
    <td>'.$_SESSION['agent']->therole.'('.$_SESSION['agent']->status.')</td>
  </tr>
  ';

  $content_row_html = $content_row_html.'
  <tr>
    <td>會員帳號</td>
    <td>'.$_SESSION['agent']->account.'</td>
  </tr>
  ';

  $content_row_html = $content_row_html.'
  <tr>
    <td>你的電腦所在IPV4位址</td>
    <td>'.explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'].'</td>
  </tr>
  ';

  // IP 列表
  $ip_list_html = '';
  foreach ($member_permission['back_ip'] as $value) {
    $ip_list_html = $ip_list_html.'<li><a href="http://freeapi.ipip.net/'.$value.'" target="_BLANK">'.$value.'</a></li>';
  }
  $content_row_html = $content_row_html.'
  <tr>
    <td>後台開放登錄的IP<br>(NULL表示全部開放)</td>
    <td>'.$ip_list_html.'</td>
  </tr>
  ';

  // 頁面權限 列表
  $pages_list_html = '';
  foreach ($member_permission['back_agent'] as $value) {
    $pages_list_html = $pages_list_html.'<li><a href="'.$value.'">'.$pages_default[$value].'</a></li>';
  }
  $content_row_html = $content_row_html.'
  <tr>
    <td>後台頁面權限限制</td>
    <td>'.$pages_list_html.'</td>
  </tr>
  ';

  $content_row_html = $content_row_html.'
  <tr>
    <td>REMOTE_ADDR</td>
    <td>'.explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])['0'].'</td>
  </tr>
  ';


  $table_title_html = '
  <thead>
    <tr>
      <th width="35%">管制屬性</th>
      <th>內容</th>
    </tr>
  </thead>
  ';

  // 組合內容與表格
  $show_html = '
  <table class="table table-striped">
    '.$table_title_html.'
    <tbody>
    '.$content_row_html.'
    </tbody>
  </table>
  ';


  // 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_html.'
	  </div>
	</div>
	<hr>
	<div class="row">
    <div class="col-12 col-md-12">
		  <div id="preview_result"></div>
    </div>
	</div>
	';


}else{
	// 沒有登入的顯示提示俊息
	$show_html  = '(x) 只有管理員或有權限的會員才可以登入觀看。';

	// 切成 1 欄版面
	$indexbody_content = '';
	$indexbody_content = $indexbody_content.'
	<div class="row">
	  <div class="col-12 col-md-12">
	  '.$show_html.'
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
$tmpl['paneltitle_content'] 			= '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;
// 主要內容 -- content
$tmpl['panelbody_content']				= $indexbody_content;

// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
include("template/beadmin.tmpl.php");
//include("template/beadmin_fluid.tmpl.php");

?>
