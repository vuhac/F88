<?php
// ----------------------------------------------------------------------------
// Features:	後台--娛樂城管理
// File Name:	casino_switch_process.php
// Author:		Ian
// Related:		casino_switch_process_action.php casino_switch_process_cmd.php
//              casino_switch_process_lib.php
// Log:
// 2019.01.31 新增 Gapi 遊戲清單管理頁籤 Letter
// 2019.03.11 新增娛樂城停用狀態 Letter
// 2019.05.13 #1839 後台娛樂城與遊戲上線流程 Letter
// 2020.02.06 #3540 【後台】娛樂城、遊戲多語系欄位實作 Letter
// 2020.04.15 Feature #3794 娛樂城新增、編輯功能開發 Letter
// ----------------------------------------------------------------------------

session_start();

// 主機及資料庫設定
require_once dirname(__FILE__) . "/config.php";
// 支援多國語系
require_once dirname(__FILE__) . "/i18n/language.php";
// 自訂函式庫
require_once dirname(__FILE__) . "/lib.php";
// 娛樂城管理列表專用函式庫
require_once dirname(__FILE__) . "/casino_switch_process_lib.php";
require_once dirname(__FILE__) . "/casino.php";


// ----------------------------------------------------------------------------
// 只要 session 活著,就要同步紀錄該 user account in redis server db 1
Agent_RegSession2RedisDB();
// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------
// 檢查權限是否合法，允許就會放行。否則中止。
agent_permission();
// ----------------------------------------------------------------------------

global $su;
$debug = 0;

// 權限
$account = $_SESSION['agent']->account;
$tool = new casino_switch_process_lib();
$permission = $tool->getPermissionByAccount($account); // 權限
debugMode($debug, $permission);
$isOps = $permission == 'ops';
$isMaster = $permission == 'master';
$isTherole = $permission == 'R';

// 娛樂城狀態
$casinoOff = casino::$casinoOff;
$casinoOn = casino::$casinoOn;
$casinoOffProcessing = casino::$casinoOffProcessing;
$casinoEmgForCasinoOff = casino::$casinoEmgForCasinoOff;
$casinoEmgForCasinoOn = casino::$casinoEmgForCasinoOn;
$casinoEmgForCasinoCloseOff = casino::$casinoEmgForCasinoCloseOff;
$casinoEmgForCasinoCloseOn = casino::$casinoEmgForCasinoCloseOn;
$casinoClose = casino::$casinoClose;
$casinoCloseForCasinoOff = casino::$casinoCloseForCasinoOff;
$casinoCloseForCasinoOn = casino::$casinoCloseForCasinoOn;
$casinoCloseForCasinoEmgOff = casino::$casinoCloseForCasinoEmgOff;
$casinoCloseForCasinoEmgOn = casino::$casinoCloseForCasinoEmgOn;
$casinoDeprecated = casino::$casinoDeprecated;

// 緊急維護
$maintenanceOn = casino::$maintenanceOn;
$maintenanceOff = casino::$maintenanceOff;

// 取得系統分頁參數
global $page_config;
$page_limit = $page_config['page_limit'];
$page_rate = $page_config['page_rate'];
$datatables_pagelength = $page_config['datatables_pagelength'];

// 預設排序欄位
$defaultSortIndex = 0;
$defaultSortFormation = 'desc';
$opsSortStart = 1;
$opsSortEnd = 100;
$masterSortStart = 101;
$masterSortEnd = $masterSortStart + $tool->getCasinosCount();

// 語系參數
global $supportLang;
$zh_cn = new Language('zh-cn', $supportLang['zh-cn']['selector'], $supportLang['zh-cn']['display']);
$en_us = new Language('en-us', $supportLang['en-us']['selector'], $supportLang['en-us']['display']);

// 對話框 ID
// 編輯視窗
$modalIdCasinoid = 'modal_casinoid';
$modalIdNotifyDatetime = 'modal_notify_datetime';
$modalIdCnName = 'modal_zh_cn_name';
$modalIdEnName = 'modal_en_us_name';
$modalIdI18n = 'modal_i18n';
$modalIdDisplayName = 'modal_display_name';
$modalIdGameFlatformList = 'modal_game_flatform_list';
$modalIdImageLink = 'modal_image_link';
$modalIdImage = 'modal_image';
$modalIdDefaultEnName = 'modal_default_en_us_name';
$modalIdDefaultCnName = 'modal_default_zh_cn_name';
$modalIdRowIndex = 'modal_row_index';
$modalBtnResetEnNameToDefault = 'modal_reset_en_us_name';
$modalBtnResetCnNameToDefault = 'modal_reset_zh_cn_name';
$modalBtnUpdateCasinoName = 'modal_update_casino_name';
// 新增視窗
$modalIdCasinoidCreate = 'modal_casinoid_create';
$modalIdNotifyDatetimeCreate = 'modal_notify_datetime_create';
$modalIdCnNameCreate = 'modal_zh_cn_name_create';
$modalIdEnNameCreate = 'modal_en_us_name_create';
$modalIdI18nCreate = 'modal_i18n_create';
$modalIdDisplayNameCreate = 'modal_display_name_create';
$modalIdGameFlatformListCreate = 'modal_game_flatform_list_create';
$modalIdImageLinkCreate = 'modal_image_link_create';
$modalIdImageCreate = 'modal_image_create';
$modalIdDefaultEnNameCreate = 'modal_default_en_us_name_create';
$modalIdDefaultCnNameCreate = 'modal_default_zh_cn_name_create';


// ----------------------------------------------------------------------------
// Main
// ----------------------------------------------------------------------------
// 初始化變數
// 功能標題，放在標題列及meta
$function_title = $tr['Casino Management'];
// 擴充 head 內的 css or js
$extend_head = '';
// 放在結尾的 js
$extend_js = '';
// body 內的主要內容
$indexbody_content = '';
// 目前所在位置 - 配合選單位置讓使用者可以知道目前所在位置
$menu_breadcrumbs = '
<ol class="breadcrumb">
  <li><a href="home.php">' . $tr['Home'] . '</a></li>
  <li><a href="#">' . $tr['System Management'] . ' </a></li>
  <li class="active">' . $function_title . '</li>
</ol>';

$mct_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ? '<li><a href="maincategory_editor.php" target="_self">' . $tr['MainCategory Management'] . '</a></li>' : '';

$lottery_backoffice_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['superuser'])) ? '<li><a href="casino_backoffice.php" target="_self">' . $tr['Lottery Management'] . '</a></li>' : '';

// 依權限顯示 GAPI 遊戲清單管理頁籤
$gapi_gamelist_management_html = (isset($_SESSION['agent']) AND in_array($_SESSION['agent']->account, $su['ops'])) ?
	'<li><a href="gapi_gamelist_management.php" target="_self">' . $tr['gapi gamelist management'] . '</a></li>' : '';

$casino_switch_html = '<div class="col-12 tab mb-3">
<ul class="nav nav-tabs">
    <li class="active"><a href="" target="_self">
    ' . $tr['Casino Management'] . '</a></li>
    ' . $mct_html . '
    <li><a href="game_management.php" target="_self">
    ' . $tr['Game Management'] . '</a></li>
    ' . $lottery_backoffice_html .
	$gapi_gamelist_management_html . '
 </ul></div>';

// ----------------------------------------------------------------------------


// ----------------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------------

// 擴充 head 內的 css or js
$extend_head .= '<!-- Jquery UI js+css  -->
                 <script src="in/jquery-ui.js"></script>
                 <link rel="stylesheet"  href="in/jquery-ui.css" >
                 <!-- jquery datetimepicker js+css -->
                 <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
                 <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
                 <!-- Datatables js+css  -->
                 <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
                 <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
                 <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>';

// -----------------------------------------------------------------------------
// 將在遊戲中的會員資料以表格方式呈現 -- from casino_switch_process
// -----------------------------------------------------------------------------
// 依照權限顯示頁面
$casino_switch_html .= '<input type="hidden" id="sortTimes" value="0">';
if ($isOps) {
	// 娛樂城狀態切換
	$casino_switch_html .=
		'<div class="col-12 casino_switch_select">
            <select id="casinoStatus" class="form-control" onchange="casinoStatusSelect()">
              <option value="' . $permission . '">' . $tr['all casinos'] . '</option>
              <option value="' . casino::$casinoOff . '">' . $tr['closed casinos'] . '</option>
              <option value="' . casino::$casinoEmg . '">' . $tr['maintained casinos'] . '</option>
              <option value="' . casino::$casinoClose . '">' . $tr['temporary closed'] . '</option>
            </select>
            <div class="col-form-label mr-2"><label for="showDeprecated">' . $tr['show deprecated'] . '</label></div>
			<div class="switch_div">
				<label for="showDeprecated" class="onoffswitch">
					<input id="showDeprecated" type="checkbox" class="onoffswitch-checkbox" /><div class="slider round"></div>
				</label>
			</div>
			<button id="createCasino" type="button" class="btn btn-primary add_casino_switch" data-toggle="modal" data-target="#createCasinoSelectorModal">' . $tr['add'] . '</button>
         </div>';


	// 自訂排序欄位
	$customSortSelectCellHtml = '
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var selectedOrder = rData.casino_order;
            var id = rData.id;
            var disabled = "";
            if (rData.open == ' . $casinoDeprecated . ') {
                disabled = "disabled";
            }
            var sortHtml = "<select id=\'customSort"+ id +"\' class=\'form-control\' onchange=\'customSortSelect("+ id +")\' "+ disabled +"><option value=\'0\'>' . $tr['unsetting'] . '</option>";
            for(i = ' . $opsSortStart . '; i <= ' . $masterSortEnd . '; i++) {
                var selectedHtml = i == selectedOrder ? "selected" : "";
                sortHtml += "<option value=\'"+ i +"\' "+ selectedHtml +">"+ i + "</option>";
            }
            sortHtml += "</select>";
            $(td).html(sortHtml);
        }
    ';


	// 醒目提醒日期選項欄位
	$notifyDatepickerCellHtml = '
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var notifyDate = rData.notify_datetime;
            var id = rData.id;
            var disabled = "";
            if (rData.open == ' . $casinoDeprecated . ') {
                disabled = "disabled";
            }
            $(td).html("<input type=\'hidden\' id=\'row"+ id +"\' value=\'"+ rowIndex +"\'><div class=\'input-group add-on\'><input type=\'text\' class=\'form-control\' name=\'notify_date\' id=\'notify_date"+ id +"\' value=\'"+ notifyDate +"\' "+ disabled +" placeholder=\'' . $tr['notify datetime placeholder'] . '\'><div class=\'input-group-btn\'><button id=\'notify_date_check"+ id +"\' class=\'btn btn-default\' style=\'display: none;\'><i class=\'glyphicon glyphicon-ok\'></i></button><button id=\'notify_date_btn"+ id +"\' onclick=\'notifyDateDatepicker("+ id +")\' class=\'btn btn-default\' "+ disabled +"><i class=\'glyphicon glyphicon-calendar\'></i></button></div></div>");
        }
    ';


	// 開啟/關閉
	$onOffSwitchHtml = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var casinoid = rData.casinoid;
            var onOff = rData.open;
            var checkedHtml = "";
            if (onOff == {$casinoOn}) {
                checkedHtml = "checked";
            } else if (onOff == {$casinoDeprecated}) {
                checkedHtml = "disabled";
            }
            $(td).html("<div class=\'switch_div\'><label for=\'on_off_switch"+ id +"\' class=\'onoffswitch\'><input id=\'on_off_switch"+ id +"\' type=\'checkbox\' class=\'onoffswitch-checkbox\' value=\'"+ onOff +"\' onclick=\'onOffCasino("+ id +", &quot;"+ casinoid +"&quot;)\' "+ checkedHtml +"/><div class=\'slider round\'></div></label></div>");
        }
JS;


	// 維護
	$maintenanceSwitchHtml = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var casinoid = rData.casinoid;
            var maintained = rData.open;
            var checkedHtml = "";
            if (maintained == {$casinoEmgForCasinoOff} || maintained == {$casinoEmgForCasinoOn} 
                || maintained == {$casinoEmgForCasinoCloseOff} || maintained == {$casinoEmgForCasinoCloseOn}) {
                checkedHtml = "checked";
            } else if (maintained == {$casinoDeprecated}) {
                checkedHtml = "disabled";
            }
            $(td).html("<div class=\'switch_div\'><label for=\'maintain_switch"+ id +"\' class=\'onoffswitch\'><input id=\'maintain_switch"+ id +"\' type=\'checkbox\' class=\'onoffswitch-checkbox\' value=\'"+ maintained +"\' onclick=\'maintainCasino("+ id +", &quot;"+ casinoid +"&quot;, &quot;"+ maintained +"&quot;)\' "+ checkedHtml +"/><div class=\'slider round\'></div></label></div>");
        }
JS;

	// 暫時停用
	$closeCasinoSwitchHtml = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var casinoid = rData.casinoid;
            var close = rData.open;
            var checkedHtml = "";
            if (close == {$casinoCloseForCasinoOff} || close == {$casinoCloseForCasinoOn} 
                || close == {$casinoCloseForCasinoEmgOff} || close == {$casinoCloseForCasinoEmgOn}) {
                checkedHtml = "checked";
            } else if (close == {$casinoDeprecated}) {
                checkedHtml = "disabled";
            }
            $(td).html("<div class=\'switch_div\'><label for=\'close_switch"+ id +"\' class=\'onoffswitch\'><input id=\'close_switch"+ id +"\' type=\'checkbox\' class=\'onoffswitch-checkbox\' value=\'"+ close +"\' onclick=\'closeCasino("+ id +", &quot;"+ casinoid +"&quot;, &quot;"+ close +"&quot;)\' "+ checkedHtml +"/><div class=\'slider round\'></div></label></div>");
        }
JS;


	// 永久關閉
	$deprecateCasinoSwitchHtml = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var casinoid = rData.casinoid;
            var disabled = "text-success";
            if (rData.open == {$casinoDeprecated}) {
                disabled = "text-danger";
            }
            $(td).html("<div id=\'deprecate_casino"+ id +"\' data-toggle=\'modal\' data-target=\'#deprecated_alert\' data-cid=\'"+ id +"\' data-casinoid=\'"+ casinoid +"\'><i class=\'glyphicon glyphicon-off "+ disabled +"\'></i></div>");
        }
JS;


	// 永久關閉娛樂城對話框
	$casinoDeprecatedModal = <<< HTML
        <div id="deprecated_alert" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deprecated_alert_label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
				        <h1 class="modal-title text-danger">{$tr['warming']}</h1>
			        </div>
			        <div class="modal-body">
			            <input type="hidden" id="casinoid">
			            <input type="hidden" id="cid">
			            <div class="row">
			                <div class="col-12 h4">
					            <label for="pw_check">{$tr['pls enter pwd']}</label>
					            <input type="password" id="pw_check">
					        </div>    
				        </div>
				        <div class="row">
				            <div class="col-12 h4">
				                <p>{$tr['deprecate casino alert sentence 1']}</p>
				                <p>{$tr['deprecate casino alert sentence 2']}</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="recheck_deprecate" class="btn btn-default" onclick="deprecateCasino()" disabled>{$tr['confirm']}</button>
                    </div>
                </div>
            </div>
        </div>
HTML;

	$casino_switch_html .= $casinoDeprecatedModal;

	// 編輯按鈕
	$casino_edit_button = <<< JS
		createdCell: function( td, cData, rData, rowIndex, colIndex) {
			let id = rData.id;
			let title = "{$tr['edit']}";
			$(td).html("<button type=\'button\' class=\'btn btn-success btn-sm casino_edit_bt\' id=\'editbt"+id+"\' " +
			 "data-toggle=\'modal\' data-target=\'#editbtmodalcenter\' data-row=\'"+rowIndex+"\' data-id=\'"+id+"\'" +
			 "data-casinoid=\'"+rData.casinoid+"\' data-casino_name=\'"+rData.casino_name+"\'" +
			 "data-open=\'"+rData.open+"\' data-casino_order=\'"+rData.casino_order+"\'" +
			 "data-game_flatform_list=\'"+rData.game_flatform_list+"\' data-notify_datetime=\'"+rData.notify_datetime+"\'" +
			 "data-api_update=\'"+rData.api_update+"\' data-display_name=\'"+rData.display_name+"\'" +
			 "data-new_alert=\'"+rData.new_alert+"\' data-zh_cn_name=\'"+rData.zh_cn_name+"\'" +
			 "data-en_us_name=\'"+rData.en_us_name+"\' data-note=\'"+rData.note+"\'>"+ title +"</button>");
		}
JS;

	// 編輯視窗功能
	$casino_edit_js = <<<HTML
	<script>
		// 編輯多語系娛樂城名稱
		$("#{$modalBtnUpdateCasinoName}").on("click", function() {
	   	    let casinoId = $("#{$modalIdCasinoid}").text();
	   	    let langKey = $("#{$modalIdI18n}").val();
	   	    let newCasinoName = $("#{$modalIdDisplayName}").val();
	   	    let row = $("#{$modalIdRowIndex}").val();
	   	    $.ajax({
	            url: "casino_switch_process_action.php?a=updateLanguageCasinoName",
	            type: "POST",
	            data : {
	                casinoId: casinoId,
	                langKey: langKey,
	                casinoName: newCasinoName
	            },
	            success: function(result){
	                let newName = JSON.parse(result);
	                if (newName.result === 1) {
	                    // 更新單列資料
	                    let table = $("#casino_switch_grid").DataTable();
	                    let newNameCasinoRow = table.row(row);
	                    newNameCasinoRow.draw(false);
	                    // 更新編輯視窗資料
	                    if (newName.langKey !== "zh-cn" || newName.langKey !== "en-us") {
							$.ajax({
								url: "casino_switch_process_action.php?a=getLanguageSelector&cid="+ casinoId,
						        dataType: "json",
						        type: "GET",
						        cache:false,
						        contentType: false,
						        processData: false,
						        success: function(data) {
							        // 取得支援的語系
							        let languageKeys = Object.keys(data);
							        let lanHtml = $("#{$modalIdI18n}");
							        lanHtml.empty().html("<option></option");
							        for(let i = 0; i < languageKeys.length; i++) {
							            if (languageKeys[i] === "zh-cn" || languageKeys[i] === "en-us") {
						                    continue;
							            } else {
							                lanHtml.append($("<option value="+ languageKeys[i] +">"+ data[languageKeys[i]].display +"</option>"));
							            }	    	
									}
									    
									lanHtml.val(langKey);
										    
									// 處理遊戲名稱顯示
									let displayInput = $("#{$modalIdDisplayName}");
									lanHtml.change(function() {
								        let lanKey = lanHtml.val();
								        displayInput.val(data[lanKey].name);
									});	    
								}
							});
	                    }
	                }
	            },
	            error: function() {
	                return null;
	            }
	        });
	   	});
	</script>
HTML;

global $supportLang;
$zh_cn = new Language('zh-cn', $supportLang['zh-cn']['selector'], $supportLang['zh-cn']['display']);
$en_us = new Language('en-us', $supportLang['en-us']['selector'], $supportLang['en-us']['display']);

// 新增娛樂城 選擇手動或者 GAPI
$casino_add_modal_html = <<<HTML
<div id="createCasinoSelectorModal" class="modal fade modal_edit" role="dialog" tabindex="-1">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-body casino_switch_add">
				<div class="row">
					<div class="col-6 text-center bg-light py-3 border">GAPI{$tr['add']}</div>
					<div class="col-6 text-center bg-light py-3 border">{$tr['Manual']}{$tr['add']}</div>
				</div>
				<div class="row">
					<div class="col-6 text-center py-3 border border-right-0">
						<form>
							<div class="form-row align-items-center">
								<div class="col-8 my-1">
									<div class="input-group">
										<select id="apiCreateSelector" class="form-control form-control-lg">
										</select>
									</div>
								</div>
								<div class="col-auto my-1">
									<button id="apiCreateCasino" type="button" class="btn btn-primary d-flex gapi_add" data-toggle="modal" data-target="#createCasinoModal" data-mode="api">{$tr['add']}</button>
								</div>
							</div>
						</form>
					</div>
					<div class="col-6 text-center py-3 border">
						<button id="manualCreateCasino" type="button" class="btn btn-primary manual_add" data-toggle="modal" data-target="#createCasinoModal" data-mode="manual">{$tr['Manual']}{$tr['add']}</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
HTML;

// 編輯娛樂城 modal 內容
$edit_casino_modal_html = <<<HTML
<div class="container-fluid casino_edit_list">
	<div class="row">
		<input id="{$modalIdRowIndex}" type="hidden">
	    <div class="col-xs-2 font-weight-bold">
	        <span class="text-danger"></span>{$tr['Casino']} ID
	    </div>
	    <div class="col-8 readonly_edit">
	        <div id="{$modalIdCasinoid}" class="form-control-plaintext mr-2"></div>
	    </div>
	</div>
	<div class="row">
	    <div class="col-xs-2 font-weight-bold"><span class="text-danger">*</span>{$zh_cn->getLangDisplay()} {$tr['name']}</div>
	    <div class="col-8 en_name_edit">
	        <form class="form-inline">
	            <input id="{$modalIdDefaultCnName}" type="hidden">
	            <input id="{$modalIdCnName}" type="text" class="form-control mr-2">
	            <button type="button" id="{$modalBtnResetCnNameToDefault}" class="btn btn-sm btn-danger">{$tr['reset']}</button>
	        </form>
	    </div>
	</div>
	<div class="row">
	    <div class="col-xs-2 font-weight-bold"><span class="text-danger">*</span>{$en_us->getLangDisplay()} {$tr['name']}</div>
	    <div class="col-8 en_name_edit">
	        <form class="form-inline">
	            <input id="{$modalIdDefaultEnName}" type="hidden">
	            <input id="{$modalIdEnName}" type="text" class="form-control mr-2">
	            <button type="button" id="{$modalBtnResetEnNameToDefault}" class="btn btn-sm btn-danger">{$tr['reset']}</button>
	        </form>
	    </div>
	</div>
	<div class="row">
	    <div class="col-xs-2 font-weight-bold">{$tr['display']}{$tr['name']}</div>
	    <div class="col-8 name_edit form-inline">
		    <select id="{$modalIdI18n}" class="form-control form-control-sm form-group">
		        <option value=""></option>
			</select>
	        <div class="form-group mx-sm-2">
	            <input id="{$modalIdDisplayName}" type="text" class="form-control" placeholder="{$tr['select language']}">
	        </div>
	        <button type="button" id="{$modalBtnUpdateCasinoName}" class="btn btn-sm btn-danger">{$tr['confirm']}</button>
	    </div>
	</div>
	<div class="row">
	    <div class="col-xs-2 font-weight-bold">{$tr['MainCategory']}</div>
	    <div id="{$modalIdGameFlatformList}" class="col-8">
	    </div>
	</div>
</div>	
HTML;

// 編輯視窗
$casino_edit_modal = <<< HTML
<div class="modal fade" id="editbtmodalcenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" >{$tr['edit']}{$tr['Casino']}</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				{$edit_casino_modal_html}
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success"onclick="saveEditCasino()">{$tr['confirm']}</button>
				<button type="button" class="btn btn-danger" data-dismiss="modal" aria-hidden="true">{$tr['off']}</button>
			</div>
		</div>
	</div>
</div>
HTML;

// 新增娛樂城 modal 內容
$create_casino_modal_html = <<<HTML
<div class="container-fluid casino_edit_list">
	<div class="row">
	    <div class="col-xs-2 font-weight-bold">
	        <span class="text-danger"></span>{$tr['Casino']} ID
	    </div>
	    <div class="col-8">
	        <input id="{$modalIdCasinoidCreate}" type="text" class="form-control">
	    </div>
	</div>
	<div class="row">
	    <div class="col-xs-2 font-weight-bold"><span class="text-danger">*</span>{$zh_cn->getLangDisplay()} {$tr['name']}</div>
	    <div class="col-8 en_name_edit">
	        <form class="form-inline">
	            <input id="{$modalIdDefaultCnNameCreate}" type="hidden">
	            <input id="{$modalIdCnNameCreate}" type="text" class="form-control">
	        </form>
	    </div>
	</div>
	<div class="row">
	    <div class="col-xs-2 font-weight-bold"><span class="text-danger">*</span>{$en_us->getLangDisplay()} {$tr['name']}</div>
	    <div class="col-8 en_name_edit">
	        <form class="form-inline">
	            <input id="{$modalIdDefaultEnNameCreate}" type="hidden">
	            <input id="{$modalIdEnNameCreate}" type="text" class="form-control">
	        </form>
	    </div>
	</div>
	<div class="row">
	    <div class="col-2 font-weight-bold">{$tr['display']}{$tr['name']}</div>
	    <div class="col-8 name_edit form-inline">
		    <div id="{$modalIdI18nCreate}" class="row"></div>
	    </div>
	</div>
	<div class="row">
	    <div class="col-xs-2 font-weight-bold">{$tr['MainCategory']}</div>
	    <div id="{$modalIdGameFlatformListCreate}" class="col-8">
	    </div>
	</div>  
</div>	
HTML;

//新增視窗
$casino_add_modal = <<<HTML
<div class="modal fade" id="createCasinoModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title casino_addtitle d-flex align-items-center">{$tr['add']}{$tr['Casino']}</h5>
			</div>
			<div class="modal-body">					
				{$create_casino_modal_html}
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success" onclick="saveCreateCasino()">{$tr['confirm']}</button>
    			<button type="button" class="btn btn-danger" data-dismiss="modal" aria-hidden="true">{$tr['off']}</button>
			</div>
		</div>
	</div>
</div>
HTML;


	// Datatables 欄位設定
	$datatable_columns = '
        columns: [
            {data: "id", visible: false },
            {data: "casinoid", title: "' . $tr["Casino"] . '"},
            {data: "zh_cn_name", title: "' . $zh_cn->getLangDisplay() . $tr["name"] . '"},
            {data: "en_us_name", title: "' . $en_us->getLangDisplay() . $tr["name"] . '"},
            {data: "display_name", title: "' . $tr['display'] . $tr["name"] . '"},
            {data: "casino_order", title: "' . $tr["priority order"] . '", ' . $customSortSelectCellHtml . '},
            {data: "notify_datetime", title: "' . $tr["notify datetime"] . '", ' . $notifyDatepickerCellHtml . '},
            {data: "open", title: "' . $tr["on off"] . '", ' . $onOffSwitchHtml . '},
            {data: "open", title: "' . $tr["Maintenance"] . $tr["State"] . '", ' . $maintenanceSwitchHtml . '},
            {data: "open", title: "' . $tr["temporary close"] . '", ' . $closeCasinoSwitchHtml . '},
			{data: "open", title: "' . $tr["deprecated casinos"] . '", ' . $deprecateCasinoSwitchHtml . '},
			{data: "open", title: "' . $tr['edit'] . '", ' . $casino_edit_button . '}						
        ]
    ';

} elseif ($isMaster) {
	// 娛樂城狀態切換
	$casino_switch_html .=
		'<div class="col-12 casino_switch_select">
						<select id="casinoStatus" class="form-control" onchange="casinoStatusSelect()">
							<option value="' . $permission . '">' . $tr['all casinos'] . '</option>
							<option value="' . casino::$casinoOff . '">' . $tr['closed casinos'] . '</option>
							<option value="' . casino::$casinoEmg . '">' . $tr['maintained casinos'] . '</option>
							<option value="new">' . $tr['new casinos'] . '</option>
						</select>
						<input type="hidden" id="new_casinos_status" value="0">
						<div class="btn btn-outline-danger" id="new_casinos_btn" onclick="newCasinos(\'' . $permission . '\')">' . $tr['new casinos'] . '</div>
					</div>';


	// 自訂排序欄位
	$customSortSelectCellHtml = '
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var selectedOrder = rData.casino_order;
            var id = rData.id;
            if (selectedOrder != 0 && selectedOrder < ' . $masterSortStart . ') {
                var disabledHtml = "disabled";
            } else {
                var disabledHtml = "";
            }
            var sortHtml = "<select id=\'customSort"+ id +"\' class=\'form-control\' onchange=\'customSortSelect("+ id +")\' "+ disabledHtml +"><option value=\'0\'>' . $tr['unsetting'] . '</option>";
            for(i = ' . $opsSortStart . '; i <= ' . $opsSortEnd . '; i++) {
                if(i == selectedOrder) {
                    sortHtml += "<option value=\'"+ i +"\' selected>" + i + "</option>";
                }
            }
            for(i = ' . $masterSortStart . '; i <= ' . $masterSortEnd . '; i++) {
                var selectedHtml = i == selectedOrder ? "selected" : "";
                sortHtml += "<option value=\'"+ i +"\' "+ selectedHtml +">"+ i + "</option>";
            }
            sortHtml += "</select>";
            $(td).html(sortHtml);
        }
    ';


	// 最新上線
	$newCasinosHtml = '
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var notify = rData.new_alert;
            var newAlertHtml = "";
            if(notify > 0) {
                newAlertHtml = "<div><button class=\'btn btn-outline-danger\'>' . $tr['new alert'] . '</button></div>";
            }
            $(td).html(newAlertHtml);
        }
    ';


	// 開啟/關閉
	$onOffSwitchHtml = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var casinoid = rData.casinoid;
            var onOff = rData.open;
            var checkedHtml = "";
            if (onOff == {$casinoOn}) {
                checkedHtml = "checked";
            } else if (onOff == {$casinoDeprecated} || onOff == {$casinoEmgForCasinoOn} || onOff == {$casinoEmgForCasinoOff}) {
                checkedHtml = "disabled";
            }
            $(td).html("<input type=\'hidden\' id=\'row"+ id +"\' value=\'"+ rowIndex +"\'><div class=\'switch_div\'><label for=\'on_off_switch"+ id +"\' class=\'onoffswitch\'><input id=\'on_off_switch"+ id +"\' type=\'checkbox\' class=\'onoffswitch-checkbox\' value=\'"+ onOff +"\' onclick=\'onOffCasino("+ id +", &quot;"+ casinoid +"&quot;)\' "+ checkedHtml +"/><div class=\'slider round\'></div></label></div>");
        }
JS;


	// 維護
	$maintenanceTag = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var showHtml = "";        
            var open = rData.open;
            if (open == {$casinoEmgForCasinoOn} || open == {$casinoEmgForCasinoOff}) {
                var showHtml = "<button type=\'button\' class=\'btn btn-info\'>{$tr['is maintenance']}</button>";    
            }
            $(td).html(showHtml);
        }
JS;


	// Datatables 欄位設定
	$datatable_columns = '
        columns: [
            {data: "id", visible: false },
            {data: "casinoid", title: "' . $tr["Casino"] . '"},
            {data: "zh_cn_name", title: "' . $zh_cn->getLangDisplay() . $tr["name"] . '"},
            {data: "en_us_name", title: "' . $en_us->getLangDisplay() . $tr["name"] . '"},
            {data: "display_name", title: "' . $tr['display'] . $tr["name"] . '"},
            {data: "casino_order", title: "' . $tr["Sort"] . '", ' . $customSortSelectCellHtml . '},
            {data: "open", title: "' . $tr["Maintenance"] . $tr["State"] . '", ' . $maintenanceTag . '},
            {data: "open", title: "' . $tr["on off"] . '", ' . $onOffSwitchHtml . '},
            {data: "notify_datetime", title: "' . $tr["new casinos"] . '", ' . $newCasinosHtml . '}
        ]
    ';

	$casino_edit_js = '';
	$casino_edit_modal = '';
	$casino_add_modal = '';
	$casino_add_modal_html = '';
} elseif ($isTherole) {
	// 娛樂城狀態切換
	$casino_switch_html .=
		'<div class="col-12 mb-3">
                <select id="casinoStatus" class="form-control" onchange="casinoStatusSelect()">
                    <option value="' . $permission . '">' . $tr['all casinos'] . '</option>
                    <option value="3">' . $tr['maintained casinos'] . '</option>
                    <option value="0">' . $tr['closed casinos'] . '</option>
                </select>
         </div>';


	// 開啟/關閉
	$onOffSwitchHtml = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var id = rData.id;
            var casinoid = rData.casinoid;
            var onOff = rData.open;
            var checkedHtml = "";
            if (onOff == {$casinoOn}) {
                checkedHtml = "checked";
            } else if (onOff == {$casinoDeprecated} || onOff == {$casinoEmgForCasinoOn} || onOff == {$casinoEmgForCasinoOff}) {
                checkedHtml = "disabled";
            }
            $(td).html("<input type=\'hidden\' id=\'row"+ id +"\' value=\'"+ rowIndex +"\'><div class=\'switch_div\'><label for=\'on_off_switch"+ id +"\' class=\'onoffswitch\'><input id=\'on_off_switch"+ id +"\' type=\'checkbox\' class=\'onoffswitch-checkbox\' value=\'"+ onOff +"\' onclick=\'onOffCasino("+ id +", &quot;"+ casinoid +"&quot;)\' "+ checkedHtml +"/><div class=\'slider round\'></div></label></div>");
        }
JS;


	// 維護
	$maintenanceTag = <<< JS
        createdCell: function( td, cData, rData, rowIndex, colIndex) {
            var showHtml = "";        
            var open = rData.open;
            if (open == {$casinoEmgForCasinoOn} || open == {$casinoEmgForCasinoOff}) {
                var showHtml = "<button type=\'button\' class=\'btn btn-info\'>{$tr['is maintenance']}</button>";    
            }
            $(td).html(showHtml);
        }
JS;


	// Datatables 欄位設定
	$datatable_columns = '
        columns: [
            {data: "id", visible: false },
            {data: "zh_cn_name", title: "' . $zh_cn->getLangDisplay() . $tr["name"] . '"},
            {data: "en_us_name", title: "' . $en_us->getLangDisplay() . $tr["name"] . '"},
            {data: "display_name", title: "' . $tr['display'] . $tr["name"] . '"},
            {data: "open", title: "' . $tr["Maintenance"] . $tr["State"] . '", ' . $maintenanceTag . '},
            {data: "open", title: "' . $tr["on off"] . '", ' . $onOffSwitchHtml . '}
        ]
    ';

	$casino_edit_js = '';
	$casino_edit_modal = '';
	$casino_add_modal = '';
	$casino_add_modal_html = '';
}
// datetimepicker 時間
$current_datepicker = gmdate('Y-m-d H:i:s', time() + -4 * 3600);

// 娛樂城表格
$casino_switch_html .= '<div id="casino_switch"><table class="table table-hover" id="casino_switch_grid"></table></div>';

// javascript for 娛樂城管理
$casino_switch_js = '
<script type="text/javascript" language="javascript" class="init">
    $(document).ready(function() {
        switchShowDeprecated();
        genDatatable("' . $permission . '");
        recheckPassword();
        initDepercateModal();
        initEditCasinoModal();
        initCreateCasinoSelector();
        initApiCreateCasino();
    });
    
    
    // 初始化表格
    function genDatatable(status) {
        $("#casino_switch_grid").DataTable({
            processing: true,
            serverSide: true,
            retrieve: true,
            searching: false,
            ordering: true,
            order: ['. $defaultSortIndex .', "'. $defaultSortFormation .'"],
            dom: "rtip",
            ajax: "casino_switch_process_action.php?a=status&casinoStatus="+ status,
            language: {
                "search": "' . $tr['search'] . '",
                "emptyTable": "' . $tr["Currently no information"] . '",
                "lengthMenu": "' . $tr["Every page shows"] . ' _MENU_ ' . $tr["Count"] . '",
                "infoEmpty": "' . $tr["Currently no information"] . '",
                "zeroRecords": "' . $tr["Currently no information"] . '",
                "info": "' . $tr["Currently in the"] .' _PAGE_ '. $tr["page"] .' '. $tr['total'] .'_PAGES_ '. $tr["page"] .'",
                "infoFiltered": "( ' . $tr["From"] . ' _TOTAL_ ' . $tr["counts data filtering"] . ' )"
            },
            ' . $datatable_columns . '
        })
    }
    
   
    // 選擇娛樂城狀態
    function casinoStatusSelect(){
        var status = $("#casinoStatus").val();
        if (status == "' . casino::$casinoNew . '") {
            $("#new_casinos_btn").removeClass("btn-outline-danger").addClass("btn-danger");
            $("#new_casinos_status").val(1);
        } else {
            $("#new_casinos_btn").removeClass("btn-danger").addClass("btn-outline-danger");
            $("#new_casinos_status").val(0);
        }
        if ($("#showDeprecated").prop("checked")) {
            $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus="+ status +"&deprecated=show").load();    
        } else {
            $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus=" + status).load(); 
        }
    }
    
    
    // 顯示永久停用開關
    function switchShowDeprecated() {
        $("#showDeprecated").on("click", function() {
            var status = $("#casinoStatus").val();
            if ($("#showDeprecated").prop("checked")) {
                $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus="+ status +"&deprecated=show").load();    
            } else {
                $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus=" + status).load(); 
            }
        });
    }
    
    
    // (優先)排序動作
    function customSortSelect(id) {
        var sortNum = $("#customSort" + id).find(":selected").val();
        $.ajax({
            url: "casino_switch_process_action.php?a=sort&id="+ id +"&order="+ sortNum,
            success: function(e) {
                var status = $("#casinoStatus").val();
                if ($("#showDeprecated").prop("checked")) {
                    $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus="+ status +"&deprecated=show").load();    
                } else {
                    $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus="+ status).load(); 
                }
            }
        });
    }
    
    
    // 醒目提醒
    function notifyDateDatepicker(id) {
        var row = $("#row" + id).val();
        var datepicker = $("#notify_date" + id).datetimepicker({
            format: "Y-m-d H:i",
            step: 10,
            closeOnDateSelect: false,
            closeOnWithoutClick: false,
            // defaultDate: false,
            defaultDate: "' . $current_datepicker . '",
            defaultTime: false
        });
        datepicker.datetimepicker("show");
        var checkBtn = $("#notify_date_check" + id);
        var calenderBtn = $("#notify_date_btn" + id);
        checkBtn.css("display", "block");
        calenderBtn.css("display", "none");
        checkBtn.on("click", function() {
            var dateTime = datepicker.val();
            $.ajax({
                url: "casino_switch_process_action.php?a=update&id="+ id +"&col=notify_datetime&val="+ dateTime,
                success: function(result) {
                    var result = JSON.parse(result);
	    	        if (result.result == 1)  {
	    	            // 更新單列
	    	            var newCasino = getCasinoById(casinoId);
	    	            var table = $("#casino_switch_grid").DataTable();
	    	            var row = table.row(row);
	    	            row.data(newCasino);
	    	            row.draw(false);
	    	        } else {
	    	            window.alert("' . $tr['error, please contact the developer for processing.'] . '");
	    	        }
                },
                error: function() {
                    window.alert("' . $tr['error, please contact the developer for processing.'] . '");
                }
            });
            checkBtn.css("display", "none");
            calenderBtn.css("display", "block");
            datepicker.datetimepicker("close");
        });
    }
    
    
    // 最新上線
    function newCasinos() {
        var status = $("#new_casinos_status").val();
        if (status == 0) {
            $("#new_casinos_btn").removeClass("btn-outline-danger").addClass("btn-danger");
            $("#new_casinos_status").val(1);
            $("#casinoStatus").val("' . casino::$casinoNew . '");
            $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus=' . casino::$casinoNew . '").load();
        } else {
            $("#new_casinos_btn").removeClass("btn-danger").addClass("btn-outline-danger");
            $("#new_casinos_status").val(0);
            $("#casinoStatus").val("' . $permission . '");
            $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus=' . $permission . '").load();
        }
    }
    
    
    // 開啟關閉
    function onOffCasino(id, casinoId) {
        var onOff = "";
        if($("#on_off_switch" + id).prop("checked")) {
            onOff = "' . casino::$casinoOn . '";
        } else {
            onOff = "' . casino::$casinoOff . '";
        }
        var row = $("#row" + id).val();
        $.ajax({
            url: "casino_switch_process_action.php?casinostate="+ onOff +"&casinoid="+ casinoId +"&emgsign=' . $maintenanceOff . '",
            success: function(result) {
                var result = JSON.parse(result);
	    	    if (result.result == 1)  {
	    	        // 更新單列
	    	        var newCasino = getCasinoById(casinoId);
	    	        var table = $("#casino_switch_grid").DataTable();
	    	        var row = table.row(row);
	    	        row.data(newCasino);
	    	        row.draw(false);
	    	    } else {
	    	            window.alert("' . $tr['error, please contact the developer for processing.'] . '");
	    	    }
            },
            error: function() {
                window.alert("' . $tr['error, please contact the developer for processing.'] . '");
            }
        });
    }
    
    
    // 維護
    function maintainCasino(id, casinoid, open) {
        var maintain = "";
        var emgsign = "' . $maintenanceOff . '";
        if ($("#maintain_switch" + id).prop("checked")) {
            emgsign = "' . $maintenanceOn . '";
            if (open == "' . $casinoOff . '") {
                maintain = "' . $casinoEmgForCasinoOff . '";
            } else if (open == "' . $casinoOn . '") {
                maintain = "' . $casinoEmgForCasinoOn . '";
            } else if (open == "' . $casinoCloseForCasinoOff . '" || open == "' . $casinoCloseForCasinoEmgOff . '") {
                maintain = "' . $casinoEmgForCasinoCloseOff . '";
            } else if (open == "' . $casinoCloseForCasinoOn . '" || open == "' . $casinoCloseForCasinoEmgOn . '") {
                maintain = "' . $casinoEmgForCasinoCloseOn . '";
            }
        } else {         
            if (open == "' . $casinoEmgForCasinoOff . '") {
                maintain = "' . $casinoOff . '";
            } else if (open == "' . $casinoEmgForCasinoOn . '") {
                maintain = "' . $casinoOn . '";
            } else if (open == "' . $casinoEmgForCasinoCloseOn . '") {
                maintain = "' . $casinoCloseForCasinoEmgOn . '";
            } else if (open == "' . $casinoEmgForCasinoCloseOff . '") {
                maintain = "' . $casinoCloseForCasinoEmgOff . '";
            }
        }
        var row = $("#row" + id).val();
        $.ajax({
           url: "casino_switch_process_action.php?casinostate="+ maintain +"&casinoid="+ casinoid +"&emgsign="+ emgsign +"",
           success: function(result) {
                var result = JSON.parse(result);
	    	    if (result.result == 1)  {
	    	        // 更新單列
	    	        var newCasino = getCasinoById(casinoid);
	    	        var table = $("#casino_switch_grid").DataTable();
	    	        var row = table.row(row);
	    	        row.data(newCasino);
	    	        row.draw(false);
	    	    } else {
	    	            window.alert("' . $tr['error, please contact the developer for processing.'] . '");
	    	    }
           },
           error: function() {
               window.alert("' . $tr['error, please contact the developer for processing.'] . '");
           }
        });
    }
    
    
    // 暫時停用
    function closeCasino(id, casinoid, open) {
        var close = "";
        var emgsign = "' . $maintenanceOff . '";
        if ($("#close_switch" + id).prop("checked")) {
            if (open == "' . casino::$casinoOff . '") {
                close = "' . casino::$casinoCloseForCasinoOff . '";
            } else if (open == "' . casino::$casinoOn . '") {
                close = "' . casino::$casinoCloseForCasinoOn . '";
            } else if (open == "' . casino::$casinoEmgForCasinoOff . '" || open == "' . casino::$casinoEmgForCasinoCloseOff . '") {
                close = "' . casino::$casinoCloseForCasinoEmgOff . '";
            } else if (open == "' . casino::$casinoEmgForCasinoOn . '" || open == "' . casino::$casinoEmgForCasinoCloseOn . '") {
                close = "' . casino::$casinoCloseForCasinoEmgOn . '";
            } 
        } else {         
            if (open == "' . casino::$casinoCloseForCasinoOff . '") {
                close = "' . casino::$casinoOff . '";
            } else if (open == "' . casino::$casinoCloseForCasinoOn . '") {
                close = "' . casino::$casinoOn . '";
            } else if (open == "' . casino::$casinoCloseForCasinoEmgOff . '") {
                close = "' . casino::$casinoEmgForCasinoCloseOff . '";
            } else if (open == "' . casino::$casinoCloseForCasinoEmgOn . '") {
                close = "' . casino::$casinoEmgForCasinoCloseOn . '";
            }
        }
        var row = $("#row" + id).val();
        $.ajax({
            url: "casino_switch_process_action.php?casinostate="+ close +"&casinoid="+ casinoid +"&emgsign="+ emgsign +"",
            success: function(result) {
                var result = JSON.parse(result);
	    	    if (result.result == 1)  {
	    	        // 更新單列
	    	        var newCasino = getCasinoById(casinoid);
	    	        var table = $("#casino_switch_grid").DataTable();
	    	        var row = table.row(row);
	    	        row.data(newCasino);
	    	        row.draw(false);
	    	    } else {
	    	            window.alert("' . $tr['error, please contact the developer for processing.'] . '");
	    	    }
            },
            error: function() {
                window.alert("' . $tr['error, please contact the developer for processing.'] . '");
            }
        });
    }
    
    
    // 永久關閉密碼確認
    function recheckPassword() {
        $("#pw_check").on("keyup", function() {
            var pw = $("#pw_check").val();
            $.ajax({
                url: "casino_switch_process_action.php?a=recheck",
                type: "post",
                data: {
                    pw: pw
                },
                success: function(data) {
                    var data = JSON.parse(data);
                    if (data.result == 1) {
                        $("#recheck_deprecate").attr("disabled", false);
                    } else {
                        $("#recheck_deprecate").attr("disabled", true);
                    }
                }
            });
        });
    }
    
    
    // 永久關閉
    function deprecateCasino() {
        var casinoId = $("#casinoid").val();
        var id = $("#cid").val();
        var emgsign = "' . $maintenanceOff . '";
        var row = $("#row" + id).val();
        $.ajax({
            url: "casino_switch_process_action.php?casinostate=' . casino::$casinoDeprecated . '&casinoid="+ casinoId +"&emgsign="+ emgsign +"",
            success: function(result) {
                var result = JSON.parse(result);
	    	    if (result.result == 1)  {
	    	        // 更新單列
	    	        var newCasino = getCasinoById(casinoId);
	    	        var table = $("#casino_switch_grid").DataTable();
	    	        var row = table.row(row);
	    	        row.data(newCasino);
	    	        row.draw(false);
	    	    } else {
	    	            window.alert("' . $tr['error, please contact the developer for processing.'] . '");
	    	    }
            },
            error: function() {
                window.alert("' . $tr['error, please contact the developer for processing.'] . '");
            }
        });
        $("#deprecated_alert").modal("hide");
        $("#pw_check").val("");
    }
    
    
    // 開啟對話框前動作
    function initDepercateModal() {
        $("#deprecated_alert").on("show.bs.modal", function(e) {
            var cdata = $(e.relatedTarget);
            var modal = $(this);
            
            modal.find(".modal-body #cid").val(cdata.data("cid"));
            modal.find(".modal-body #casinoid").val(cdata.data("casinoid"));
        });
    }
    
    
    // 取得娛樂城
    function getCasinoById(id) {
        var form_data = new FormData();
        form_data.append("id", id);
        $.ajax({
            url: "casino_switch_process_action.php?a=getCasino",
            type: "POST",
            data : form_data,
            cache:false,
            contentType: false,
            processData: false,
            success: function(result){
                return result;
            },
            error: function() {
                return null;
            }
        });  
    }
    
    
    // 開啟編輯娛樂城前動作
    function initEditCasinoModal() {
		$("#editbtmodalcenter").on("show.bs.modal", function(e) {
		  	let casinoData = $(e.relatedTarget);
            let modal = $(this);
            
            //清除必填提示(未填寫後送出的樣式還原)
            $("#'. $modalIdCnName .'").removeClass("alert alert-danger mb-0");
            $("#'. $modalIdEnName .'").removeClass("alert alert-danger mb-0");
		   	
		   	// 取得列索引號
		   	let rowIndex = casinoData.data("row");
		   	
		   	// 取得語系選項
		   	let casinoId = casinoData.data("casinoid");
		   	i18nSelector(casinoId, "", 1);
		   	$("#'. $modalIdDisplayName .'").val("");
		   	
		   	// 綁定重設名稱方法
		   	let defaultCnName = casinoData.data("note");
		   	$("#'. $modalBtnResetCnNameToDefault .'").on("click", function() {
		   	    updateLanguageCasinoName(casinoId, "zh-cn", defaultCnName);
		   	});
		   	let defaultEnName = casinoData.data("casino_name");
		   	$("#'. $modalBtnResetEnNameToDefault .'").on("click", function() {
		   	    updateLanguageCasinoName(casinoId, "en-us", defaultEnName);
		   	});
		    	
		    modal.find(".modal-body #'. $modalIdRowIndex .'").val(rowIndex);
		   	modal.find(".modal-body #'. $modalIdCasinoid .'").text(casinoId);
		   	modal.find(".modal-body #'. $modalIdCnName .'").val(casinoData.data("zh_cn_name"));
		   	modal.find(".modal-body #'. $modalIdDefaultCnName .'").val(casinoData.data("note"));
		   	modal.find(".modal-body #'. $modalIdEnName .'").val(casinoData.data("en_us_name"));
		   	modal.find(".modal-body #'. $modalIdDefaultEnName .'").val(casinoData.data("casino_name"));
		   	
		   	// 主要分類
			getMainCategory($("#'. $modalIdGameFlatformList .'"), casinoId);

		});
		
    }
    
    
    // 儲存編輯娛樂城
    function saveEditCasino() {
        // 取得參數
        let cnName = $("#'. $modalIdCnName .'").val();
        let enName = $("#'. $modalIdEnName .'").val();
        if ( cnName == "" || enName == "" ){
            alert("尚有必填资料需要填写");
            if ( cnName == "" ){
                $("#'. $modalIdCnName .'").addClass("alert alert-danger mb-0");
            }
            if ( enName == "" ){
                $("#'. $modalIdEnName .'").addClass("alert alert-danger mb-0");
            }
        }else{        
            // 取得參數
            let rowIndex = $("#'. $modalIdRowIndex .'").val(); 
            let casinoId = $("#'. $modalIdCasinoid .'").text();
            // let cnName = $("#'. $modalIdCnName .'").val();
            // let enName = $("#'. $modalIdEnName .'").val();
            let langKey = $("#'. $modalIdI18n .'").val();
            let displayName = $("#'. $modalIdDisplayName .'").val();
            let categories = [];
            $.each($("input[name=\'gameFlatform\']:checked"), function() {
                categories.push($(this).val());
            });
            let categoryStr = categories.toString();

            // 組成 form		
            let formData = new FormData();
            formData.append("rowIndex", rowIndex);
            formData.append("casinoId", casinoId);
            formData.append("cnName", cnName);
            formData.append("enName", enName);
            formData.append("langKey", langKey);
            formData.append("displayName", displayName);
            formData.append("categories", categoryStr);
            
            // 傳送 form 資料更新娛樂城
            $.ajax({
                url: "casino_switch_process_action.php?a=updateCasino",
                type: "POST",
                data : formData,
                cache:false,
                contentType: false,
                processData: false,
                success: function(data){
                    let update = JSON.parse(data);
                    if (update.result === 1) {
                        let newCasino = getCasinoById(casinoId);
                        let table = $("#casino_switch_grid").DataTable();
                        let casinoRow = table.row(rowIndex);
                        casinoRow.data(newCasino);
                        casinoRow.draw(false);
                        $("#editbtmodalcenter").modal("hide");
                    } else if (update.result < 1) {
                        window.alert("'. $tr['error, please contact the developer for processing.'] .'");
                    }
                },
                error: function() {
                    window.alert("'. $tr['error, please contact the developer for processing.'] .'");
                }
            }); 
        }
    }
    
    
    // 更新語系名稱
    function updateLanguageCasinoName(casinoId, langKey, casinoName, row) {
        $.ajax({
            url: "casino_switch_process_action.php?a=updateLanguageCasinoName",
            type: "POST",
            data : {
                casinoId: casinoId,
                langKey: langKey,
                casinoName: casinoName
            },
            success: function(result){
                let newName = JSON.parse(result);
                if (newName.result === 1) {
                    // 更新單列資料
                    let casino = getCasinoById(casinoId);
                    let table = $("#casino_switch_grid").DataTable();
                    let newNameCasinoRow = table.row(row);
                    newNameCasinoRow.data(casino);
                    newNameCasinoRow.draw(false);
                    // 更新編輯視窗資料
                    if (newName.langKey !== "zh-cn" || newName.langKey !== "en-us") {
						i18nSelector(casinoId, newName.langKey, 1);
						$("#'. $modalIdDisplayName .'").val("");
                    } else {
                        i18nSelector(casinoId, newName.langKey, 0);
                    }
                }
            },
            error: function() {
                return null;
            }
        });
    }
    
    
    // 語系選項
    function i18nSelector(cid, langKey, allLang) {
   		$.ajax({
			url: "casino_switch_process_action.php?a=getLanguageSelector&cid="+ cid,
	   	    dataType: "json",
	   	    type: "GET",
	  	    cache:false,
	   	    contentType: false,
	   	    processData: false,
	   	    success: function(data) {
		        // 取得支援的語系
		        let languageKeys = Object.keys(data);
		        let lanHtml = $("#' . $modalIdI18n .'");
		    	lanHtml.empty().html("<option></option");
		    	for(let i = 0; i < languageKeys.length; i++) {
		        	if (allLang === 1 && (languageKeys[i] === "zh-cn" || languageKeys[i] === "en-us")) {
	                	continue;
		        	} else {
		            	lanHtml.append($("<option value="+ languageKeys[i] +">"+ data[languageKeys[i]].display +"</option>"));
		        	}	    	
				}
				    
				lanHtml.val(langKey);
					    
				// 處理娛樂城名稱顯示
				let displayInput = $("#'. $modalIdDisplayName .'");
				displayInput.empty();
				lanHtml.change(function() {
			    	let lanKey = lanHtml.val();
			    	displayInput.val(data[lanKey].name);
				});	    
			}
		});
    }
    
	
	// 取得主要分類
	function getMainCategory(categoryHtml, casinoId) {
	  	$.ajax({
			url: "casino_switch_process_action.php?a=getCategory&cid=" + casinoId,
	   	    dataType: "html",
	   	    type: "GET",
	  	    cache:false,
	   	    contentType: false,
	   	    processData: false,
	   	    success: function(data) {
				categoryHtml.html(data);
			}
		});
	}
	
	
	// 開啟新增娛樂城選項前動作
	function initCreateCasinoSelector() {
        let selectHtml = $("#apiCreateSelector");
        $("#apiCreateCasino").attr("disabled", false);
		$("#createCasinoSelectorModal").on("show.bs.modal", function(e) {
            //清除必填提示(未填寫後送出的樣式還原)
            $("#'. $modalIdCnNameCreate .'").removeClass("alert alert-danger mb-0");
            $("#'. $modalIdEnNameCreate .'").removeClass("alert alert-danger mb-0");
		    $("#createCasinoModal").modal("hide");
			$.ajax({
				url: "casino_switch_process_action.php?a=getApiCasinos",
		        dataType: "html",
		        type: "GET",
		        cache:false,
		        contentType: false,
		        processData: false,
		        success: function(data) {
				    if(data.length == 0) {
				        selectHtml.html("<option>No Casino !!</option>");
				        $("#apiCreateCasino").attr("disabled", true);
				    } else {
						selectHtml.html(data);
				    };
				},
				error: function() {
					window.alert("'. $tr['error, please contact the developer for processing.'] .'");
				}
			});
		});
	}
	
	
	// API新增娛樂城視窗開啟前動作
	function initApiCreateCasino() {
        $("#createCasinoModal").on("show.bs.modal", function(e) {
            // 關閉新增娛樂城選項
        	$("#createCasinoSelectorModal").modal("hide");
        	
        	// 取得開啟 modal
        	let button = $(e.relatedTarget);
		   	let modal = $(this);
		   	
		   	// 取得新增方式
		   	let mode = button.data("mode");
		   	if (mode == "api") {
		   	    // 取得 API 娛樂城資料
	            let casinoId = $("#apiCreateSelector").val();
	            $.ajax({
					url: "casino_switch_process_action.php?a=genCreateCasino&casinoId=" + casinoId,
			        type: "GET",
			        cache:false,
			        contentType: false,
			        processData: false,
			        success: function(data) {
						let newCasino = JSON.parse(data);
						modal.find(".modal-body #'. $modalIdCasinoidCreate .'").val(newCasino.casinoid); // casinoId
						$("#'. $modalIdCasinoidCreate .'").attr("readonly", true);
						modal.find(".modal-body #'. $modalIdCnNameCreate .'").val(newCasino.note); // 簡中名稱(預設)
						modal.find(".modal-body #'. $modalIdEnNameCreate .'").val(newCasino.casino_name); // 英文名稱(預設)
						// 語系選項
						i18nSelectorCreate(JSON.parse(newCasino.display_name), "", 1);
//						$("#'. $modalIdDisplayNameCreate .'").val("");
						
						// 娛樂城反水分類
						getMainCategory($("#'. $modalIdGameFlatformListCreate .'"),"");
						
					},
					error: function() {
						window.alert("'. $tr['error, please contact the developer for processing.'] .'");
					}
				});
		   	} else {
		   		modal.find(".modal-body #'. $modalIdCasinoidCreate .'").val(""); // casinoId
				$("#'. $modalIdCasinoidCreate .'").attr("readonly", false);
				modal.find(".modal-body #'. $modalIdCnNameCreate .'").val(""); // 簡中名稱(預設)
				modal.find(".modal-body #'. $modalIdEnNameCreate .'").val(""); // 英文名稱(預設)
		   	    // 語系選項
		   	    i18nSelectorCreate([], "", 1);
//		   	    $("#'. $modalIdDisplayNameCreate .'").val("");
		   	    
		   	    // 娛樂城反水分類
				getMainCategory($("#'. $modalIdGameFlatformListCreate .'"),"");
		   	}
        });
	}
	
	
	// 新增娛樂城語系選項
    function i18nSelectorCreate(i18n, langKey, allLang) {
		// 取得支援的語系
		$.ajax({
			url: "casino_switch_process_action.php?a=getSupportLanguage",
		    type: "GET",
		    cache:false,
		    contentType: false,
		    processData: false,
		    success: function(data) {
		        // 取得所有語系
		        let supportLang = JSON.parse(data);        
       			let languageKeys = Object.keys(supportLang);
				let lanHtml = $("#' . $modalIdI18nCreate .'");
				lanHtml.empty();
				for(let i = 0; i < languageKeys.length; i++) {
				    if (allLang === 1 && (languageKeys[i] === "zh-cn" || languageKeys[i] === "en-us")) {
			            continue;
				    } else {
				        lanHtml.append($("<div class=\'col-12 mb-2 casino_switch_name\'><label>"+ supportLang[languageKeys[i]] +"</label><input id="+ languageKeys[i] +" name=\'i18nName\' class=\'form-control mr-2\' value=\'\'></div>"));
				    }	    	
				}
//				lanHtml.empty().html("<option></option");
//				for(let i = 0; i < languageKeys.length; i++) {
//				    if (allLang === 1 && (languageKeys[i] === "zh-cn" || languageKeys[i] === "en-us")) {
//			            continue;
//				    } else {
//				        lanHtml.append($("<option value="+ languageKeys[i] +">"+ supportLang[languageKeys[i]] +"</option>"));
//				    }	    	
//				}
//						    
//				lanHtml.val(langKey);
//							    
//				// 處理新增娛樂城語系名稱顯示
//				let displayInput = $("#'. $modalIdDisplayNameCreate .'");
//				displayInput.empty();
//				lanHtml.change(function() {
//				    let lanKey = lanHtml.val();
//				    displayInput.val(i18n[lanKey]);
//				});
			},
			error: function() {
				window.alert("'. $tr['error, please contact the developer for processing.'] .'");
			}
		});	    
    }

    
    
    
    // 儲存新增娛樂城
    function saveCreateCasino() {
        //先判斷是否必填資料有填寫
        let cnName = $("#'. $modalIdCnNameCreate .'").val();
        let enName = $("#'. $modalIdEnNameCreate .'").val();
        if ( cnName == "" || enName == "" ){
            alert("尚有必填资料需要填写");
            if ( cnName == "" ){
                $("#'. $modalIdCnNameCreate .'").addClass("alert alert-danger mb-0");
            }
            if ( enName == "" ){
                $("#'. $modalIdEnNameCreate .'").addClass("alert alert-danger mb-0");
            }
        }else{
            // 取得參數
            let casinoId = $("#'. $modalIdCasinoidCreate .'").val();
            // let cnName = $("#'. $modalIdCnNameCreate .'").val();
            // let enName = $("#'. $modalIdEnNameCreate .'").val();
            let i18nName = [];
            $.each($("input[name=\'i18nName\']"), function() {
                let keyVal = $(this).attr("id") + "=" + $(this).val();
                i18nName.push(keyVal);
            });
            let i18nNameStr = i18nName.toString();

            let categories = [];
            $.each($("input[name=\'gameFlatform\']:checked"), function() {
                categories.push($(this).val());
            });
            let categoryStr = categories.toString();
            
            // 組成 form		
            let formData = new FormData();
            formData.append("casinoId", casinoId);
            formData.append("cnName", cnName);
            formData.append("enName", enName);
            formData.append("displayName", i18nNameStr);
            formData.append("categories", categoryStr);
            
            // 傳送 form 資料更新娛樂城
            $.ajax({
                url: "casino_switch_process_action.php?a=createCasino",
                type: "POST",
                data : formData,
                cache:false,
                contentType: false,
                processData: false,
                success: function(data){
                    let update = JSON.parse(data);
                    if (update.result === 1) {
                        let status = $("#casinoStatus").val();
                        if ($("#showDeprecated").prop("checked")) {
                            $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus="+ status +"&deprecated=show").load();    
                            $("#createCasinoModal").modal("hide");
                        } else {
                            $("#casino_switch_grid").DataTable().ajax.url("casino_switch_process_action.php?a=status&casinoStatus="+ status).load(); 
                            $("#createCasinoModal").modal("hide");
                        }
                    } else if (update.result < 1) {
                        window.alert("'. $tr['error, please contact the developer for processing.'] .'");
                    }
                },
                error: function() {
                    window.alert("'. $tr['error, please contact the developer for processing.'] .'");
                }
            });
     }
    }
</script>';


$switch_css = '
<style type="text/css">
.casinoswitch {
  line-height:18px;
}

.switch_div {
  display:inline-block;
  text-align:center;
  margin:5px 0px;
}

.switch_font {
  font-size:18px;
  margin:0px 10px 0px 10px;
}

/* The switch - the box around the slider */
.onoffswitch {
 position: relative;
 display: inline-block;
 width: 30px;
 height: 17px;
 margin:0px 0px 0px 0px;
}

/* Hide default HTML checkbox */
.onoffswitch input {display:none;}

/* The slider */
.slider {
 position: absolute;
 cursor: pointer;
 top: 0;
 left: 0;
 right: 0;
 bottom: 0;
 background-color: #ccc;
 -webkit-transition: .4s;
 transition: .4s;
}

.slider:before {
 position: absolute;
 content: "";
 height: 13px;
 width: 13px;
 left: 2px;
 bottom: 2px;
 background-color: white;
 -webkit-transition: .4s;
 transition: .4s;
}

input:checked + .slider {
 background-color: #2196F3;
}

input:focus + .slider {
 box-shadow: 0 0 1px #2196F3;
}

input:checked + .slider:before {
 -webkit-transform: translateX(13px);
 -ms-transform: translateX(13px);
 transform: translateX(13px);
}

/* Rounded sliders */
.slider.round {
 border-radius: 17px;
}

.slider.round:before {
 border-radius: 50%;
}

.processingimg {
 border: 2px solid #f3f3f3; /* Light grey */
 border-top: 2px solid #3498db; /* Blue */
 border-radius: 50%;
 width: 20px;
 height: 20px;
 animation: spin 2s linear infinite;
}

@keyframes spin {
 0% { transform: rotate(0deg); }
 100% { transform: rotate(360deg); }
}

/* 頁數 */
.pagination {
	float: right;
}

/* 日曆按鈕 */
.add-on .input-group-btn > .btn {
    border-left-width: 0;
    left:-2px;
    -webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
            box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
}

.add-on .form-control:focus {
    -webkit-box-shadow: none; 
            box-shadow: none;
    border-color:#cccccc; 
}

/* 表頭、列文字 */
th, td {
    text-align: center;
}

/* 電源開關大小 */
.glyphicon.glyphicon-off {
    font-size: 24px;
}
</style>';
$extend_js .= $casino_edit_js;
// HTML Head
$extend_head .= $switch_css . $casino_switch_js;

// ----------------------------------------------------------------------------
// 準備填入的內容
// ----------------------------------------------------------------------------

// 將內容塞到 html meta 的關鍵字, SEO 加強使用
$tmpl['html_meta_description'] = $tr['host_descript'];
$tmpl['html_meta_author'] = $tr['host_author'];
$tmpl['html_meta_title'] = $function_title . '-' . $tr['host_name'];

// 頁面大標題
$tmpl['page_title'] = $menu_breadcrumbs;
// 擴充再 head 的內容 可以是 js or css
$tmpl['extend_head'] = $extend_head;
// 擴充於檔案末端的 Javascript
$tmpl['extend_js'] = $extend_js;
// 主要內容 -- title
$tmpl['paneltitle_content'] = '<span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span>' . $function_title;
// 主要內容 -- content
$tmpl['panelbody_content'] = $casino_switch_html . $casino_add_modal_html . $casino_edit_modal . $casino_add_modal;


// ----------------------------------------------------------------------------
// 填入內容結束。底下為頁面的樣板。以變數型態塞入變數顯示。
// ----------------------------------------------------------------------------
// include("template/dashboard.tmpl.php");
include("template/beadmin_fluid.tmpl.php");

?>
