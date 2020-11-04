<?php
// ----------------------------------------------------------------------------
// Features:  後台-- 新增角色
// File Name: actor_management_opt.php?a=add
// Author:    Mavis
// Related:   actor_management.php，actor_management_action.php，actor_management_view.php, actor_management_opt_view.php,actor_mamagement_opt_editor.php,
// 			  actor_management_opt_action.php
// DB Table:
// Log:
// ----------------------------------------------------------------------------


session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) ."/config.php";
// 支援多國語系
require_once dirname(__FILE__) ."/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) ."/lib.php";

require_once dirname(__FILE__) ."/lib_view.php";
// 文字檔
require_once dirname(__FILE__) ."/actor_management_lib.php";

// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 agent user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

if(!$is_ops) {
	$logger = $tr['You dont have permission'];
        echo '<script>alert("'.$logger.'");history.go(-1);</script>';die();
}

$function_title = $tr['add role'];
$menu_breadcrumbs = $tr['role managment'];

$tmpl['html_meta_title'] 	= $function_title.'-'.$tr['host_name'];

$tmpl['page_title'] = $menu_breadcrumbs;

$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>'.$function_title;

return render(
	__DIR__ . '/actor_management_create_view.php',
		compact(
			'menu_breadcrumbs',
			'function_title',
			'actor_group_name'
		)
);

?>
