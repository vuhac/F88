<?php // first_store_report_view.php ?>
<?php function_exists('use_layout') or die() ?>
<?php use_layout("template/s2col.tmpl.php") ?>


<?php // 頁首的CSS與js ?>
<?php begin_section('extend_head') ?>
    <?php // jquery datetimepicker js+css ?>
    <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
    <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>

    <?php // jquery blockui ?>
    <script src="in/jquery-ui.js"></script>
    <link rel="stylesheet"  href="in/jquery-ui.css" >
    <!-- Jquery blockUI js  -->
    <script src="./in/jquery.blockUI.js"></script>
    <!-- <script type="text/javascript" src="in/jquery.blockUI.js"></script> -->

    <?php // dataTable ?>
    <script src="in/datatables/js/jquery.dataTables.min.js"></script>
    <link href="in/datatables/css/jquery.dataTables.min.css" rel="stylesheet">
    <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>

    <?php // larvel page ?>
    <script src="in/js/laravel_page.js"></script>

    <style>
        .border {
            margin: 0px;
        }
        .betlog_h6 {
            background: rgba(224,228,233,.5);
            color: #26282a;
            padding: 10px;
            font-size: 15px;
            width: 100%;
            margin: 0px;
        }
        .ck-button {
            margin: 0px;
            overflow: auto;
            float: left;
            width: 50%;
        }
        .ck-button label {
            float: left;
            width: 100%;
            height: 100%;
            margin-bottom: 0;
            background-color: transparent;
            transition: all 0.2s;
        }
        .ck-button label input {
            position: absolute;
            z-index: -5;
        }
        .ck-button label span {
            text-align: center;
            display: block;
            font-size: 15px;
            line-height: 38px;
        }

        /* 表格標題、內容文字至中 */
        #total > thead > tr > th,
        #total > tbody > tr > td {
            text-align: center;
            vertical-align: middle;
        }

        /* 左邊搜尋框 */
        #agent_search_form {
            display: none;
        }

        /* 左邊搜尋框/統計方式 */
        div.panel-body > div.border {
            margin-bottom: 15px;
            border-radius: 0px 0px 5px 5px;
        }

        /* 左邊搜尋框/日期快速選擇 */
        .date-range-search {
            margin: auto;
        }

        /* 首儲日期的錯誤訊息提示 */
        div.invalid-feedback {
            text-align: center;
        }
    </style>
<?php end_section() ?>


<?php // 瀏覽器頁籤顯示的標題 ?>
<?php begin_section('html_meta_title') ?>
<?php echo $tr['first_store_report-title'].'-'.$tr['host_name'] ?>
<?php end_section() ?>


<?php // 麵包屑 ?>
<?php begin_section('page_title') ?>
    <ol class="breadcrumb">
        <li><a href="home.php"><?php echo $tr['homepage'] ?></a></li>
        <li><a href="#"><?php echo $tr['Various reports']  ?></a></li>
        <li class="active"><?php echo $tr['first_store_report-title'] ?></li>
    </ol>
<?php end_section() ?>

<?php begin_section('indextitle_content')?>
<span class="glyphicon glyphicon-search" aria-hidden="true"></span>
<?=$tr['Search criteria'];?>
<?php end_section()?>

<?php // 頁面中的標題 ?>
<?php // begin_section('paneltitle_content')  ?>
    <!-- <span class="glyphicon glyphicon-bookmark" aria-hidden="true"></span> -->
    <?php // echo $tr['first_store_report-title']; ?>
<?php // end_section() ?>

<?php begin_section('indexbody_content');?>
<?php // 統計方式-會員/代理商 ?>
<div class="row border">
    <div class="ck-button text-white bg-primary" id="type_member">
        <label>
            <input type="checkbox" value="member">
            <span class="status_sel_0"><?php echo $tr['first_store_report-title-member']; ?></span>
        </label>
    </div>
    <div class="ck-button" id="type_agent">
        <label>
            <input type="checkbox" value="agent">
            <span class="status_sel_1"><?php echo $tr['first_store_report-title-agent']; ?></span>
        </label>
    </div>
</div>
<form id="member_search_form">
    <?php // 首儲帳號 ?>
    <div class="row">
        <div class="col-12">
            <label for="member_search_store_account">
                <?php echo $tr['first_store_report-member-first_store_account']; ?>
            </label>
        </div>
        <div class="col-12 form-group input-group">
            <div class="input-group">
                <input type="text" class="form-control" name="search_store_account" id="member_search_store_account" value="" placeholder="<?php echo $tr['first_store_report-member-first_store_account-placeholder']; ?>">
            </div>
        </div>
    </div>

    <?php // 一級代理/直屬代理 ?>
    <div class="row">
        <div class="col-12">
            <label for="member_search_agent">
                <?php echo $tr['first_store_report-member-root agent/first agent']; ?>
            </label>
        </div>
        <div class="col-12 form-group input-group">
            <input type="text" class="form-control" name="search_agent" id="member_search_agent" value="" placeholder="<?php echo $tr['first_store_report-member-root agent/first agent-placeholder']; ?>">
        </div>
    </div>

    <?php // 首儲時間起迄搜尋 ?>
    <div class="row">
        <div class="col-12 d-flex">
            <label for="sdate">
                <?php echo $tr['first_store_report-member-first_store_date']; ?>
                <?php
                    if (!$is_setted_default_timezone) { //  系統未設定時區
                        echo <<<HTML
                            <span class="glyphicon glyphicon-info-sign" style="color: red;" title="{$default_timezone_message}"></span>
                        HTML;
                    }
                ?>
            </label>
            <?php // 日期快速搜尋 ?>
            <?php if ($is_setted_default_timezone) { ?>
                <div class="btn-group btn-group-sm ml-auto member-application" role="group" aria-label="Button group with nested dropdown">
                    <button type="button" class="btn btn-secondary first"><?=$tr['grade default'];?></button>

                    <div class="btn-group btn-group-sm" role="group">
                        <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
                        <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
                            <?php // 本週一00:00~本週日23:59 ?>
                            <a type="button" class="dropdown-item week" onclick="memberMarkDatetimeRange('<?php echo $sunday_date.' 00:00'; ?>', '<?php echo $saturday_date.' 23:59'; ?>', 'week')"><?php echo $tr['This week']; ?></a>
                            <?php // 上週一00:00~上週日23:59 ?>
                            <a type="button" class="dropdown-item lastweek" onclick="memberMarkDatetimeRange('<?php echo $last_sunday_date.' 00:00'; ?>', '<?php echo $last_saturday_date.' 23:59'; ?>', 'lastweek')"><?php echo $tr['Last week']; ?></a>
                            <?php // 本月第一天00:00~本月最後一天23:59 ?>
                            <a type="button" class="dropdown-item month" onclick="memberMarkDatetimeRange('<?php echo $first_date_in_month.' 00:00'; ?>', '<?php echo $last_date_in_month.' 23:59'; ?>', 'month')"><?php echo $tr['this month']; ?></a>
                            <?php // 今天00:00~23:59 ?>
                            <a type="button" class="dropdown-item today" onclick="memberMarkDatetimeRange('<?php echo date('Y-m-d 00:00'); ?>', '<?php echo date('Y-m-d 23:59'); ?>', 'today')"><?php echo $tr['Today']; ?></a>
                            <?php // 昨天00:00~23:59 ?>
                            <a type="button" class="dropdown-item yesterday" onclick="memberMarkDatetimeRange('<?php echo date('Y-m-d 00:00', strtotime('-1 day')); ?>', '<?php echo date('Y-m-d 23:59', strtotime('-1 day')); ?>', 'yesterday')"><?php echo $tr['yesterday']; ?></a>
                            <?php // 上個月第一天00:00~上個月最後一天23:59 ?>
                            <a type="button" class="dropdown-item lastmonth" onclick="memberMarkDatetimeRange('<?php echo $first_date_in_last_month.' 00:00'; ?>', '<?php echo $last_date_in_last_month.' 23:59'; ?>', 'lastmonth')"><?php echo $tr['last month']; ?></a>
                        </div>
                    </div>
                </div>
            <?php }?>
        </div>
        <div class="col-12 form-group input-group">
            <?php // 首儲日期(起)(迄)
                if ($is_setted_default_timezone) { //  is-invalid
                    echo <<<HTML
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['start']}</span>
                            </div>
                            <input type="text" class="form-control" name="start_datetime" id="member_start_datetime" value="{$now_datetime_start}" data-default="{$now_datetime_start}">
                            <div class="invalid-feedback">請輸入首儲開始時間</div>
                        </div>
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['end']}</span>
                            </div>
                            <input type="text" class="form-control" name="end_datedatetime" id="member_end_datetime" value="{$now_datetime_end}" data-default="{$now_datetime_end}">
                            <div class="invalid-feedback">請輸入首儲結束時間</div>
                        </div>
                    HTML;
                } else {
                    echo <<<HTML
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['start']}</span>
                            </div>
                            <input type="text" class="form-control" name="start_datetime" id="member_start_datetime" disabled>
                        </div>
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['end']}</span>
                            </div>
                            <input type="text" class="form-control" name="end_datedatetime" id="member_end_datetime" disabled>
                        </div>
                    HTML;
                }
            ?>
        </div>
    </div>

    <?php // 首儲金額最大、最小值 ?>
    <div class="row">
        <div class="col-12">
            <label for="member_store_min_value"><?php echo $tr['first_store_report-member-first_store_amount']; ?>
                <!-- <span class="glyphicon glyphicon-info-sign" title=""></span> -->
            </label>
        </div>
        <div class="col-12 form-group">
            <div class="input-group">
                <input type="number" class="form-control" name="store_min_value" id="member_store_min_value" placeholder="<?php echo $tr['Lower limit']; ?>" value="" min="0">
                <span class="input-group-addon amount-range-sign">~</span>
                <input type="number" class="form-control" name="store_max_value" id="member_store_max_value" placeholder="<?php echo $tr['Upper limit']; ?>" value="" min="0">
            </div>
        </div>
    </div>

    <?php // 搜尋類型 ?>
    <input type="hidden" name="type" value="member">

    <?php // 執行查詢 ?>
    <hr>
    <div class="row">
        <div class="col-12 col-md-12">
            <?php
                // 有設定站台目前使用時區才可以正常的搜尋資料
                if ($is_setted_default_timezone) {
                    echo <<<HTML
                        <button id="member_submit_query" class="btn btn-success btn-block" type="button">{$tr['first_store_report-search']}</button>
                    HTML;
                } else {
                    echo <<<HTML
                        <button id="member_submit_query" class="btn btn-success btn-block" title="{$default_timezone_message}" type="button" disabled>{$tr['first_store_report-search']}</button>
                    HTML;
                }
            ?>
        </div>
    </div>
</form>
<?php // 代理商統計方式搜尋框 ?>
<form id="agent_search_form">
    <?php // 代理商 ?>
    <div class="row">
        <div class="col-12">
            <label for="search_agent_account">
                <?php echo $tr['first_store_report-title-agent']; ?>
            </label>
        </div>
        <div class="col-12 form-group input-group">
            <input type="text" class="form-control" name="agent" id="search_agent_account" placeholder="<?php echo $tr['first_store_report-agent-agent-placeholder']; ?>">
        </div>
    </div>

    <?php // 日期搜尋條件 ?>
    <div class="row">
        <div class="col-12 d-flex">
            <label for="sdate">
                <?php echo $tr['first_store_report-agent-first_store_date']; ?>
                <?php
                    if (!$is_setted_default_timezone) { //  系統未設定時區
                        echo <<<HTML
                            <span class="glyphicon glyphicon-info-sign" style="color: red;" title="{$default_timezone_message}"></span>
                        HTML;
                    }
                ?>
            </label>
            <?php // 日期快速搜尋 ?>
            <?php if ($is_setted_default_timezone) { ?>
                <div class="btn-group btn-group-sm ml-auto agent-application" role="group" aria-label="Button group with nested dropdown">
                    <button type="button" class="btn btn-secondary first"><?=$tr['grade default'];?></button>

                    <div class="btn-group btn-group-sm" role="group">
                        <button id="btnGroupDrop1" type="button" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
                        <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
                            <?php // 本週日00:00~本週六23:59 ?>
                            <a type="button" class="dropdown-item week" onclick="agentMarkDatetimeRange('<?php echo $sunday_date.' 00:00'; ?>', '<?php echo $saturday_date.' 23:59'; ?>', 'week')"><?php echo $tr['This week']; ?></a>
                            <?php // 上週日00:00~上週六23:59 ?>
                            <a type="button" class="dropdown-item lastweek" onclick="agentMarkDatetimeRange('<?php echo $last_sunday_date.' 00:00'; ?>', '<?php echo $last_saturday_date.' 23:59'; ?>', 'lastweek')"><?php echo $tr['Last week']; ?></a>
                            <?php // 本月第一天00:00~本月最後一天23:59 ?>
                            <a type="button" class="dropdown-item month" onclick="agentMarkDatetimeRange('<?php echo $first_date_in_month.' 00:00'; ?>', '<?php echo $last_date_in_month.' 23:59'; ?>', 'month')"><?php echo $tr['this month']; ?></a>
                            <?php // 今天00:00~23:59 ?>
                            <a type="button" class="dropdown-item today" onclick="agentMarkDatetimeRange('<?php echo date('Y-m-d 00:00'); ?>', '<?php echo date('Y-m-d 23:59'); ?>', 'today')"><?php echo $tr['Today']; ?></a>
                            <?php // 昨天00:00~23:59 ?>
                            <a type="button" class="dropdown-item yesterday" onclick="agentMarkDatetimeRange('<?php echo date('Y-m-d 00:00', strtotime('-1 day')); ?>', '<?php echo date('Y-m-d 23:59', strtotime('-1 day')); ?>', 'yesterday')"><?php echo $tr['yesterday']; ?></a>
                            <?php // 上個月第一天00:00~上個月最後一天23:59 ?>
                            <a type="button" class="dropdown-item lastmonth" onclick="agentMarkDatetimeRange('<?php echo $first_date_in_last_month.' 00:00'; ?>', '<?php echo $last_date_in_last_month.' 23:59'; ?>', 'lastmonth')"><?php echo $tr['last month']; ?></a>
                        </div>
                    </div>
                </div>
            <?php }?>
        </div>
        <div class="col-12 form-group input-group">
            <?php // 首儲日期(起)(迄)
                if ($is_setted_default_timezone) { //  is-invalid
                    echo <<<HTML
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['start']}</span>
                            </div>
                            <input type="text" class="form-control" name="start_datetime" id="agent_start_datetime" value="{$now_datetime_start}" data-default="{$now_datetime_start}">
                            <div class="invalid-feedback">{$tr['require first store start time']}</div>
                        </div>
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['end']}</span>
                            </div>
                            <input type="text" class="form-control" name="end_datetime" id="agent_end_datetime" value="{$now_datetime_end}" data-default="{$now_datetime_end}">
                            <div class="invalid-feedback">{$tr['require first store end time']}</div>
                        </div>
                    HTML;
                } else {
                    echo <<<HTML
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['start']}</span>
                            </div>
                            <input type="text" class="form-control" name="start_datetime" id="agent_start_datetime" disabled>
                        </div>
                        <div class="input-group">
                            <div class="input-group-prepend">
                            <span class="input-group-text">{$tr['end']}</span>
                            </div>
                            <input type="text" class="form-control" name="end_datetime" id="agent_end_datetime" disabled>
                        </div>
                    HTML;
                }
            ?>
        </div>
    </div>

    <?php // 首儲金額最大、最小值 ?>
    <div class="row">
        <div class="col-12">
            <label for="agent_store_min_value"><?php echo $tr['first_store_report-agent-first_store_amount']; ?>
                <!-- <span class="glyphicon glyphicon-info-sign" title=""></span> -->
            </label>
        </div>
        <div class="col-12 form-group">
            <div class="input-group">
                <input type="number" class="form-control" name="min_store_amount" id="agent_store_min_value" placeholder="<?php echo $tr['Lower limit']; ?>" value="" min="0">
                <span class="input-group-addon amount-range-sign">~</span>
                <input type="number" class="form-control" name="max_store_amount" id="agent_store_max_value" placeholder="<?php echo $tr['Upper limit']; ?>" value="" min="0">
            </div>
        </div>
    </div>

    <?php // 搜尋類型 ?>
    <input type="hidden" name="type" value="agent">

    <?php // 執行查詢 ?>
    <hr>
    <div class="row">
        <div class="col-12 col-md-12">
            <?php
                // 有設定站台目前使用時區才可以正常的搜尋資料
                if ($is_setted_default_timezone) {
                    echo <<<HTML
                        <button id="agent_submit_query" class="btn btn-success btn-block" type="button">{$tr['first_store_report-search']}</button>
                    HTML;
                } else {
                    echo <<<HTML
                        <button id="agent_submit_query" class="btn btn-success btn-block" title="{$default_timezone_message}" type="button" disabled>{$tr['first_store_report-search']}</button>
                    HTML;
                }
            ?>
        </div>
    </div>
</form>
<?php end_section()?>

<!-- main title -->
<?php begin_section('paneltitle_content')?>
    <span class="glyphicon glyphicon-list" aria-hidden="true"></span><?php echo $tr['first_store_report-search_result'] ?>
    <?php // 匯出Excel ?>
    <div id="csv" style="float:right;margin-bottom:auto">
        <!-- <a href="" class="btn btn-success btn-sm" role="button" target="_blank" aria-pressed="true"><?php echo $tr['first_store_report-output_excel']; ?></a> -->
    </div>
<?php end_section()?>

<!-- main content -->
<?php begin_section('panelbody_content')?>
    <?php /* 統計方式-會員 */ ?>
    <div  id="member_output_panel" class="panel-body" style="padding-bottom:0px;">
        <div>
            <table id="member_total_area" class="table" cellspacing="0">
                <thead class="thead-inverse">
                    <tr>
                        <th style="text-align:center;"><?php echo $tr['first_store_report-member-member_total_area-account_count']; ?></th>
                        <th style="text-align:center;"><?php echo $tr['first_store_report-member-member_total_area-first_store_amount_count']; ?></th>
                        <th style="text-align:center;"></th>
                        <th style="text-align:center;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td  align="center" id="member_account_count">----</td>
                        <td  align="center" id="member_store_count">----</td>
                        <td  align="center"></td>
                        <td  align="center"></td>
                    </tr>
                </tbody>
            </table>

            <div id="show_summary" style="display: block;">
                <table id="member_content" class="display" cellspacing="2" width="100%">
                    <thead class="thead-inverse">
                        <tr>
                            <th><?php echo $tr['first_store_report-member-member_content-first_store_account']; ?></th>
                            <th><?php echo $tr['first_store_report-member-member_content-therole']; ?></th>
                            <th><?php echo $tr['first_store_report-member-member_content-root_agent']; ?></th>
                            <th><?php echo $tr['first_store_report-member-member_content-upper_agent']; ?></th>
                            <th><?php echo $tr['first_store_report-member-member_content-first_store_datetime']; ?></th>
                            <th><?php echo $tr['first_store_report-member-member_content-registered_datetime']; ?></th>
                            <th><?php echo $tr['first_store_report-member-member_content-first_store_amount']; ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php // 統計方式-代理商 ?>
    <div  id="agent_output_panel" class="panel-body" style="padding-bottom:0px; display:none;">
        <div>
            <div id="" style="display: block;">
                <table id="agent_content" class="table display" cellspacing="2" width="100%">
                    <thead class="thead-inverse">
                        <tr>
                            <th><?php echo $tr['first_store_report-agent-agent_content-agent']; ?></th>
                            <th><?php echo $tr['first_store_report-agent-agent_content-lower_chiid']; ?></th>
                            <th><?php echo $tr['first_store_report-agent-agent_content-lower_chiid_first_store_amount_count']; ?></th>
                            <th><?php echo $tr['first_store_report-agent-agent_content-lower_chiidren_first_store_account_count']; ?></th>
                            <th><?php echo $tr['first_store_report-agent-agent_content-lower_chiidren_first_store_amount_count']; ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div id="paging" class="mt-3 d-flex justify-content-center" style="margin: 15px auto;">
                </div>
            </div>
        </div>
    </div>
<?php end_section()?>

<?php // 擴充的JS ?>
<?php begin_section('extend_js') ?>
    <script>
        <?php // 點擊快速搜尋，自動帶日期去搜尋框 ?>
        <?php if ($is_setted_default_timezone) { ?>
            function memberMarkDatetimeRange(start_datetime, end_datetime, text)
            {
                $('#member_start_datetime').val(start_datetime);
                $('#member_end_datetime').val(end_datetime);

                //更換顯示到選單外 20200525新增
                var currentonclick = $('.member-application .'+text+'').attr('onclick');
                var currenttext = $('.member-application .'+text+'').text();

                //first change
                $('.member-application .first').removeClass('week month');
                $('.member-application .first').attr('onclick',currentonclick);
                $('.member-application .first').text(currenttext);
            }
            function agentMarkDatetimeRange(start_datetime, end_datetime, text)
            {
                $('#agent_start_datetime').val(start_datetime);
                $('#agent_end_datetime').val(end_datetime);

                //更換顯示到選單外 20200525新增
                var currentonclick = $('.agent-application .'+text+'').attr('onclick');
                var currenttext = $('.agent-application .'+text+'').text();

                //first change
                $('.agent-application .first').removeClass('week month');
                $('.agent-application .first').attr('onclick',currentonclick);
                $('.agent-application .first').text(currenttext);
            }
        <?php }?>
        <?php // ------------------------------------------?>


        <?php // 日期搜尋框 (系統有設定時區才有) ?>
        <?php if ($is_setted_default_timezone) { ?>
            $('#member_start_datetime, #member_end_datetime, #agent_start_datetime, #agent_end_datetime').datetimepicker(
            {
                showButtonPanel: true,
                formatTime: 'H:i',
                format: 'Y-m-d H:i',
                changeMonth: true,
                changeYear: true,
                step: 1
            });
        <?php }?>
        <?php // ------------------------------------------?>


        <?php // 點擊統計方式-會員 ?>
        $(document).on('click', '#type_member', function(){
            if ( !$(this).hasClass('bg-primary') ) {
                $('div.ck-button.bg-primary').removeClass('bg-primary').removeClass('text-white');
                $(this).addClass('bg-primary').addClass('text-white');
            }

            if ( $('#member_search_form').is(':hidden') ) {
                $('#member_search_form').show();
                $('#agent_search_form').hide();
            }

            $('#member_output_panel').show();
            $('#agent_output_panel').hide();
        });

        <?php // 點擊統計方式-代理商 ?>
        $(document).on('click', '#type_agent', function(){
            if ( !$(this).hasClass('bg-primary') ) {
                $('div.ck-button.bg-primary').removeClass('bg-primary').removeClass('text-white');
                $(this).addClass('bg-primary').addClass('text-white');
            }

            if ( $('#agent_search_form').is(':hidden') ) {
                $('#agent_search_form').show();
                $('#member_search_form').hide();
            }

            $('#member_output_panel').hide();
            $('#agent_output_panel').show();
        });
        <?php // ------------------------------------------?>


        <?php // 統計方式->會員->初次載入 ?>
        $("#member_content").DataTable({
            'bLengthChange': false,
            'bProcessing': true,
            'bServerSide': true,
            'bRetrieve': true,
            'searching': true,
            'pageLength': 10,
            'info': true,
            'paging': true,
            'stateSave': false,
            "oLanguage": {
                "sSearch": "<?php echo $tr['search'] ?>",//"搜索:",
                "sEmptyTable": "<?php echo $tr['no data']?>",//"目前没有资料!",
                "sLengthMenu": "<?php echo $tr['each page']?>_MENU_<?php echo $tr['Count']?>",//"每页显示 _MENU_ 笔",
                "sZeroRecords": "<?php echo $tr['no data']?>",//"没有匹配结果",
                "sInfo": "<?php echo $tr['Display']?> _START_ <?php echo $tr['to']?> _END_ <?php echo $tr['result']?>,<?php echo $tr['total']?> _TOTAL_ <?php echo $tr['item']?>",//"显示第 _START_ 至 _END_ 项结果，共 _TOTAL_ 项",
                "sInfoEmpty": "<?php echo $tr['no data']?>",//"目前没有资料",
                "sInfoFiltered": "(<?php echo $tr['from']?> _MAX_ <?php echo $tr['filtering in data']?>)"//"(由 _MAX_ 项结果过滤)"
            },
            /* "aaSorting": [
                [ 0, "desc" ]
            ], */
            'ajax': {
                'url': 'first_store_report_action.php',
                'type': 'GET',
                'data': {
                    type: 'member',
                    search_store_account: $('#member_search_store_account').val(),
                    search_agent: $('#member_search_agent').val(),
                    store_min_value: $('#member_store_min_value').val(),
                    store_max_value: $('#member_store_max_value').val(),
                    <?php
                        if ($is_setted_default_timezone) {
                            echo <<<JS
                                start_datetime: $('#member_start_datetime').val(),
                                end_datedatetime: $('#member_end_datetime').val()
                            JS;
                        } else {
                            echo <<<JS
                                start_datetime: '',
                                end_datedatetime: ''
                            JS;
                        }
                    ?>
                }
            },
            'columnDefs': [
                {'targets': [0], 'className': 'dt-center', 'orderable': false},
                {'targets': [1], 'className': 'dt-left', 'orderable': false},
                {'targets': [2], 'className': 'dt-left', 'orderable': false},
                {'targets': [3], 'className': 'dt-left', 'orderable': false},
                {'targets': [4], 'className': 'dt-center', 'orderable': false},
                {'targets': [5], 'className': 'dt-center', 'orderable': false},
                {'targets': [6], 'className': 'dt-center', 'orderable': false}
            ],
            'columns': [
                { 'data': 'account', 'width':'15%' },
                { 'data': 'therole', 'width':'10%'},
                { 'data': 'root_account', 'width':'15%'},
                { 'data': 'parent_account', 'width': '15%'},
                { 'data': 'first_deposite_date', 'width':'15%'},
                { 'data': 'enrollmentdate', 'width':'15%'},
                { 'data': 'deposit', 'width':'15%'}
            ],
            'rowCallback': function(row, data){
                $('#member_content_length, #member_content_filter').remove();
            },
            'drawCallback': function(settings){
                $('#member_content_length, #member_content_filter').remove();
                $('#member_account_count').html(settings.json.recordsTotal);
                $('#member_store_count').html(settings.json.deposit_count);
            }
        });

        <?php // 統計方式->會員->搜尋 ?>
        $(document).on('click', '#member_submit_query', function()
        {
            <?php // 判斷首儲日期起迄時間是否都有輸入 ?>
            var is_field = columnsIsField([
                '#member_start_datetime',
                '#member_end_datetime'
            ]);

            if (!is_field) {
                return false;
            }

            $("#member_content").DataTable().search(JSON.stringify({
                type: 'member',
                search_store_account: $('#member_search_store_account').val(),
                search_agent: $('#member_search_agent').val(),
                store_min_value: $('#member_store_min_value').val(),
                store_max_value: $('#member_store_max_value').val(),
                <?php
                    if ($is_setted_default_timezone) {
                        echo <<<JS
                            start_datetime: $('#member_start_datetime').val(),
                            end_datedatetime: $('#member_end_datetime').val()
                        JS;
                    } else {
                        echo <<<JS
                            start_datetime: '',
                            end_datedatetime: ''
                        JS;
                    }
                ?>
            })).draw(true);
            $('#member_content_length, #member_content_filter').remove();
            return false;
        });
        <?php // ------------------------------------------?>

        <?php // 統計方式->代理商->搜尋 ?>
        $(document).on('click', '#agent_submit_query', function()
        {
            <?php // 判斷首儲日期起迄時間是否都有輸入 ?>
            var is_field = columnsIsField([
                '#agent_start_datetime',
                '#agent_end_datetime'
            ]);

            if (is_field) {
                queryFirstStoreRecordByAgent(1);
            }
        });

        <?php // 統計方式->代理商->右邊表格，請求資料 ?>
        function queryFirstStoreRecordByAgent(page=1)
        {
            var count = 0;
            var data = [];
            var msg = '';
            var length = 10;
            var start = ( (page - 1) * length );
            $.ajax({
                url: 'first_store_report_action.php',
                type: 'GET',
                async: false,
                dataType: 'JSON',
                data: $('#agent_search_form').serialize() + '&start=' + start + '&length=' + length,
                success: function(result) {
                    count = result.count;
                    data = result.data;
                    msg = result.msg;
                }, error: function(xhr, type) {
                    alert('查詢代理商統計方式失敗.');
                    return false;
                }, complete: function(){}
            });

            <?php // 判斷是否有回傳錯誤訊息 ?>
            if (msg != '') {
                alert(msg);
            }

            <?php // 清除版面後，把資料寫回版面 ?>
            $('#agent_content > tbody').html('');
            if (data.length > 0) {
                for (var i=0; i<data.length; i++) {
                    $('#agent_content > tbody').append(
                        '<tr>' +
                            '<td>' + data[i]['account'] + '</td>' +
                            '<td>' + data[i]['under_line_people_count'] + '</td>' +
                            '<td>' + data[i]['under_line_amount_total'] + '</td>' +
                            '<td>' + data[i]['agent_line_people_count'] + '</td>' +
                            '<td>' + data[i]['agent_line_amount_total'] + '</td>' +
                        '</tr>'
                    );
                }
            } else {
                $('#agent_content > tbody').html('<tr style="text-align:center;"><td colspan="5"><?php echo $tr['no data found']; ?></td></tr>');
            }

            $('#paging').html( laravel_page(count, page) );

            // $.blockUI({ message: "<img id='agent_loading' src=\"ui/loading_text.gif\" />" }); // $.unblockUI();
            // $.unblockUI();
        }

        <?php // 上一頁 (非disabled狀態) ?>
        $(document).on('click', '#paging > ul.pagination > li.page-item:first:not(.disabled)', function()
        {
            var prev_page = parseInt( $('#paging > ul.pagination > li.active > span.page-link').text() ) - 1;
            queryFirstStoreRecordByAgent(prev_page);
            return false;
        });

        <?php // 點擊頁碼 ?>
        $(document).on('click', '#paging > ul.pagination > li.page-item:not(.active):not(:first):not(:last)', function()
        {
            var order_page = $(this).children('a.page-link').text();
            queryFirstStoreRecordByAgent(order_page);
            return false;
        });

        <?php // 下一頁 ?>
        $(document).on('click', '#paging > ul.pagination > li.page-item:last:not(.disabled)', function()
        {
            var next_page = parseInt( $('#paging > ul.pagination > li.active > span.page-link').text() ) + 1;
            queryFirstStoreRecordByAgent(next_page);
            return false;
        });

        <?php // 阻止表格內的輸入框按下Enter後送出，因為並非使用form來傳送資料，而是各別的方式儲送資料 ?>
        $(document).on('keydown', '#member_search_form input, #agent_search_form input', function(e){
            if (e.which == 13) {
                return false;
            }
        });

        <?php // 判斷指定的欄位是否有填寫 ?>
        function columnsIsField(column_id_array)
        {
            var is_field = true;
            for (var i=0; i<column_id_array.length; i++) {
                var column_val = $(column_id_array[i]).val();
                if ( (column_val == '') || (column_val == undefined) || (column_val == null) || (column_val.trim() == '') ) {
                    $(column_id_array[i]).addClass('is-invalid');
                    is_field = false;
                } else {
                    $(column_id_array[i]).removeClass('is-invalid');
                }
            }
            return is_field;
        }
    </script>
<?php end_section() ?>