<?php
// ----------------------------------------
// Features:	反水設定的 各娛樂城服務類別定義
// File Name:	favorable_config.php
// Author:		Neil
// Related:
// Log:
// -----------------------------------------------------------------------------
// 以後如果需要稱家娛樂城的反水設定值，修改這個列表就可以增加娛樂城的設定


// 娛樂城列表
$casino_list = array('MG', 'PT', 'BBIN', 'SABA');

// $game_flatform_list['MG'] = array('mg_live', 'mg_flash', 'mg_html5');
// $game_flatform_list['PT'] = array('pt_live', 'pt_flash');

// 每個娛樂城的分類項目列表, 配合產生 JSON 編輯項目。
$game_flatform_list['MG'] = array('live', 'flash', 'html5');
$game_flatform_list['PT'] = array('live', 'flash');

$game_flatform_list['BBIN'] = array('live', 'flash');
$game_flatform_list['SABA'] = array('live', 'flash', 'html5');
