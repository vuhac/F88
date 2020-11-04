<!-- actor_management_view.php -->
<?php use_layout("template/beadmin.tmpl.php"); ?>


<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
    <!-- Jquery UI js+css  -->
    <script src="in/jquery-ui.js"></script>
    <link rel="stylesheet"  href="in/jquery-ui.css" >
    <!-- Jquery blockUI js  -->
    <script src="./in/jquery.blockUI.js"></script>
    <!-- jquery datetimepicker js+css -->
    <link href="in/datetimepicker/jquery.datetimepicker.css" rel="stylesheet"/>
    <script src="in/datetimepicker/jquery.datetimepicker.full.min.js"></script>
    <!-- Datatables js+css  -->
    <link rel="stylesheet" type="text/css" href="./in/datatables/css/jquery.dataTables.min.css?v=180612">
    <script type="text/javascript" language="javascript" src="./in/datatables/js/jquery.dataTables.min.js?v=180612"></script>
    <script type="text/javascript" language="javascript" src="./in/datatables/js/dataTables.bootstrap.min.js?v=180612"></script>
    <!-- 自訂css -->
    <link rel="stylesheet" type="text/css" href="ui/style_seting.css">

    <!-- 值區間選擇 -->
    <!--Plugin CSS file with desired skin-->
    <link rel="stylesheet" href="./in/rangeSlider/2.3.0/ion.rangeSlider.min.css"/>
    <!--Plugin JavaScript file-->
    <script src="./in/rangeSlider/2.3.0/ion.rangeSlider.min.js"></script>

    <!-- Bootstrap Toogle -->
    <link href="./in/bootstrap-toggle/2.2.2/bootstrap-toggle.min.css" rel="stylesheet">
    <script src="./in/bootstrap-toggle/2.2.2/bootstrap-toggle.min.js"></script>
    <script src="./in/jQuery-Validation-Engine/js/languages/jquery.validationEngine-zh_CN.js" type="text/javascript" charset="utf-8"></script>
    <script src="./in/jQuery-Validation-Engine/js/jquery.validationEngine.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" href="./in/jQuery-Validation-Engine/css/validationEngine.jquery.css" type="text/css"/>

    <script type="text/javascript" language="javascript" class="init">
        $(document).ready(function () {
            $("#admin_management").validationEngine();
        });
    </script>

    <style>
        p.form-control-lg.text-right ,.radio-padding{
            padding-top: 3px;
        }

        .radio-padding>.form-check>label{
            font-weight: 400;
        }

        .col-auto{
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #submit_to_admin_create_result{
            margin-top: 10px;
        }

        i.fas.fa-asterisk {
            font-size: 12px;
            color: lightcoral;
        }

        div.deposit_val_title {
            padding: 0px 20px;
        }
    </style>
<?php end_section(); ?>
<!-- end of extend_head -->


<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
    <ol class="breadcrumb">
        <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
        <li><a href="#"><?php echo $tr['maintenance']; ?></a></li>
        <li><a href="admin_management.php"><?php echo $tr['sub-account management']; ?></a></li>
        <li class="active"><?php echo $function_title; ?></li>
    </ol>
<?php end_section(); ?>
<!-- end of page_title -->


<!-- 主要內容  title -->
<!-- begin of paneltitle_content -->
<?php begin_section('paneltitle_content'); ?>
    <i class="fas fa-user-lock"></i><?php echo $function_title; ?>
    <div id="csv"  style="float:right;margin-bottom:auto"></div>
<?php end_section(); ?>
<!-- end of paneltitle_content -->


<!-- 主要內容 content -->
<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>
<!-- <span class="glyphicon glyphicon-search" aria-hidden="true"></span><?php echo $tr['Search criteria']; ?> -->

<form id="admin_management">
    <?php // 管理員ID(新增狀態時為空) ?>
    <input type="hidden" value="<?php echo ( isset($account_data->id) && !empty($account_data->id) && ($account_data->id != null) ? $account_data->id : '' ); ?>" id="id">

    <?php // 管理員帳號(編輯狀態時不可編輯) ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['administrator account'];?></p>
        </div>
        <div class="col-4">
            <input id="id_admin_account" class="form-control" type="text" name="admin_account" maxlength="12" placeholder="<?php echo $tr['administrator account'];?>"
                   value="<?php echo ( isset($account_data->account) ? $account_data->account : '' ); ?>"
                   <?php echo ( isset($account_data->account) ? 'disabled' : '' );?>>
            <div class="invalid-feedback">
                Error Message Here.
            </div>
        </div>
    </div>

    <?php // 管理員名稱 ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['administrator name'];?></p>
        </div>
        <div class="col-4">
            <input id="id_admin_name" class="form-control" type="text" name="admin_name" placeholder="<?php echo $tr['Real Name Example'];?>"
                   value="<?php echo ( isset($account_data->realname) ? $account_data->realname : '' ); ?>">
            <div class="invalid-feedback">
                Error Message Here.
            </div>
        </div>
    </div>

    <?php // 修改密碼 ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['enter password'];?></p>
        </div>
        <div class="col-4">
            <input class="form-control" autocomplete="off" id="input_pwd" type="password" name="password" autocomplete="off" placeholder="<?php echo $tr['enter password'];?>">
            <div class="invalid-feedback">
                Error Message Here.
            </div>
        </div>
    </div>

    <?php // 再次輸入修改密碼 ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['confirm'].$tr['Password'];?></p>
        </div>
        <div class="col-4">
            <input class="form-control" autocomplete="off" id="confirm_pwd" type="password" name="confirm_password" autocomplete="off" placeholder="<?php echo $tr['confirm']; echo $tr['Password'];?>">
            <div class="invalid-feedback">
                Error Message Here.
            </div>
        </div>
    </div>

    <?php // 手機號碼 ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['Cell Phone'];?></p>
        </div>
        <div class="col-4">
            <input class="form-control" id="cell_phone" type="text" name="cell_phone" placeholder="ex:15820859791" autocomplete="off"
                   value="<?php echo ( isset($account_data->mobilenumber) ? $account_data->mobilenumber : '' ); ?>">
            <div class="invalid-feedback">
                Error Message Here.
            </div>
        </div>
    </div>

    <?php // Email ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['Email'];?></p>
        </div>
        <div class="col-4">
            <input class="form-control" id="email" type="text" name="email" placeholder="ex：test@test.com" autocomplete="off"
                   value="<?php echo ( isset($account_data->email) ? $account_data->email : '' ); ?>">
            <div class="invalid-feedback">
                Error Message Here.
            </div>
        </div>
    </div>

    <?php // 驗證方式 ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right">
                <i class="fas fa-asterisk"></i>
                <?php echo $tr['identify'];?>
            </p>
        </div>

        <?php // 帳號狀態選項 ?>
        <div class="col-6 radio-padding" style="margin-bottom: 5px;">

            <?php // 帳號有效 ?>
            <div class="custom-control custom-radio" style="margin-bottom: 5px;">
                <input type="radio" id="admin_account_status1" name="admin_account_status" class="custom-control-input" value="1"
                <?php echo ( ( (isset($account_data->status) && ($account_data->status==1)) || (!isset($account_data->status)) ) ? 'checked' : '' ); ?> >
                <label class="custom-control-label" for="admin_account_status1">
                    <?php echo $tr['effective account'];?>
                </label>
            </div>

            <?php // 帳號停用 ?>
            <div class="custom-control custom-radio" style="margin-bottom: 5px;">
                <input type="radio" id="admin_account_status0" name="admin_account_status" class="custom-control-input" value="0"
                <?php echo ( ( (isset($account_data->status)) && ($account_data->status==0) ) ? 'checked' : '' ); ?> >
                <label class="custom-control-label" for="admin_account_status0">
                    <?php echo $tr['account disabled'];?>
                </label>
            </div>

            <?php // 帳號凍結 ?>
            <div class="custom-control custom-radio" style="margin-bottom: 7px;">
                <input type="radio" id="admin_account_status2" name="admin_account_status" class="custom-control-input" value="2"
                <?php echo ( ( (isset($account_data->status)) && ($account_data->status==2) ) ? 'checked' : '' ); ?> >
                <label class="custom-control-label" for="admin_account_status2">
                    <?php echo $tr['freeze account'];?>
                </label>
            </div>

        </div>
    </div>

    <?php // 備註 ?>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['note'];?></p>
        </div>
        <div class="col-10">
            <textarea id="note" class="form-control validate[maxSize[500]]" style="resize:none;" rows="3" name="note" maxlength="500" placeholder="<?php echo $tr['note'];?>(<?php echo $tr['max'];?>500<?php echo $tr['word']?>)"><?php echo ( isset($account_data->notes) ? $account_data->notes : '' ); ?></textarea>
        </div>
    </div>
    <hr>

    <?php // 權限設定的標示 ?>
    <div class="row">
        <div class="col-12 col-md-12">
            <span class="label label-primary">
                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php echo $tr['permission setting'];?>
            </span>
        </div>
    </div><br>

    <?php // 人工現金單次存入最大值 ?>
    <div class="row" style="margin-top:30px;">
        <div class="deposit_val_title">
            <i class="fas fa-asterisk"></i>
            <span class="text-right"><?php echo $tr['maximum single gcash deposit'] ?? 'maximum single gcash deposit';?></span>
        </div>
        <div class="col-2">
            <input type="number" class="js-range-slider form-control" id="gcash_input_max" min="100" step="100" value="<?php echo $account_setting->gcash_input_max ?? '500';?>"><!-- gcash_input -->
            <div class="invalid-feedback"></div>
            <script>
                $('#gcash_input_max').blur(function(){
                    var gcash_input_max = $(this).val(),
                        gcash_input_daily_max = $('#gcash_input_daily_max').val();

                    if( (gcash_input_max == undefined) || (gcash_input_max == '') ){
                        $(this).addClass('is-invalid');
                        $(this).next('div.invalid-feedback').text("<?php echo $tr['please enter this field'] ?? 'please enter this field';?>");
                    }
                    else{
                        gcash_input_max = parseInt(gcash_input_max);
                        gcash_input_daily_max = parseInt(gcash_input_daily_max);

                        if( (gcash_input_max % 100) != 0 ){
                            $(this).addClass('is-invalid');
                            $(this).next('div.invalid-feedback').text("<?php echo $tr['amount must be a multiple of one hundred'] ?? 'amount must be a multiple of one hundred';?>");
                        }
                        else if( (gcash_input_daily_max != undefined) && (gcash_input_daily_max != '') ){
                            if( gcash_input_max > gcash_input_daily_max ){
                                $(this).addClass('is-invalid');
                                $(this).next('div.invalid-feedback').text("<?php echo $tr['single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit'] ?? 'single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit';?>");
                            }
                            else{
                                $(this).removeClass('is-invalid');
                                $(this).next('div.invalid-feedback').text('');

                                $('#gcash_input_daily_max').removeClass('is-invalid');
                                $('#gcash_input_daily_max').next('div.invalid-feedback').text('');
                            }
                        }
                        else{
                            $(this).removeClass('is-invalid');
                            $(this).next('div.invalid-feedback').text('');
                        }
                    }
                }); // end blur
            </script>
        </div>
    </div>

    <?php // 人工現金單日存入最大值 ?>
    <div class="row" style="margin-top:30px;">
        <div class="deposit_val_title">
            <i class="fas fa-asterisk"></i>
            <span class="text-right"><?php echo $tr['maximum single day of manual gcash deposit'] ?? 'maximum single day of manual gcash deposit';?></span>
        </div>
        <div class="col-2">
            <input type="number" class="js-range-slider form-control" id="gcash_input_daily_max" min="100" step="100" value="<?php echo $account_setting->gcash_input_daily_max ?? '1200';?>">
            <div class="invalid-feedback"></div>
            <script>
                $('#gcash_input_daily_max').blur(function(){
                    var gcash_input_max = $('#gcash_input_max').val(),
                        gcash_input_daily_max = $(this).val();

                    if( (gcash_input_daily_max == undefined) || (gcash_input_daily_max == '') ){
                        $(this).addClass('is-invalid');
                        $(this).next('div.invalid-feedback').text("<?php echo $tr['please enter this field'] ?? 'please enter this field';?>");
                    }
                    else{
                        gcash_input_max = parseInt(gcash_input_max);
                        gcash_input_daily_max = parseInt(gcash_input_daily_max);

                        if( (gcash_input_daily_max % 100) != 0 ){
                            $(this).addClass('is-invalid');
                            $(this).next('div.invalid-feedback').text("<?php echo $tr['amount must be a multiple of one hundred'] ?? 'amount must be a multiple of one hundred';?>");
                        }
                        else if( (gcash_input_max != undefined) && (gcash_input_max != '') ){
                            if( gcash_input_max > gcash_input_daily_max ){
                                $(this).addClass('is-invalid');
                                $(this).next('div.invalid-feedback').text("<?php echo $tr['single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit'] ?? 'single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit';?>");
                            }
                            else{
                                $(this).removeClass('is-invalid');
                                $(this).next('div.invalid-feedback').text('');

                                $('#gcash_input_max').removeClass('is-invalid');
                                $('#gcash_input_max').next('div.invalid-feedback').text('');
                            }
                        }
                        else{
                            $(this).removeClass('is-invalid');
                            $(this).next('div.invalid-feedback').text('');
                        }
                    }
                }); // end blur
            </script>
        </div>
    </div>

    <?php // 人工戲幣存單次存入最大值 ?>
    <div class="row" style="margin-top:30px;">
        <div class="deposit_val_title">
            <i class="fas fa-asterisk"></i>
            <span class="text-right"><?php echo $tr['maximum single gtoken deposit'] ?? 'maximum single gtoken deposit';?></span>
        </div>
        <div class="col-2">
            <input type="number" class="js-range-slider form-control" id="gtoken_input_max" min="100" step="100" value="<?php echo $account_setting->gtoken_input_max ?? '1200';?>"><!-- gtoken_input -->
            <div class="invalid-feedback"></div>
            <script>
                $('#gtoken_input_max').blur(function(){
                    var gtoken_input_max = $(this).val(),
                        gtoken_input_daily_max = $('#gtoken_input_daily_max').val();

                    if( (gtoken_input_max == undefined) || (gtoken_input_max == '') ){
                        $(this).addClass('is-invalid');
                        $(this).next('div.invalid-feedback').text("<?php echo $tr['please enter this field'] ?? 'please enter this field';?>");
                    }
                    else{
                        gtoken_input_max = parseInt(gtoken_input_max);
                        gtoken_input_daily_max = parseInt(gtoken_input_daily_max);

                        if( (gtoken_input_max % 100) != 0 ){
                            $(this).addClass('is-invalid');
                            $(this).next('div.invalid-feedback').text("<?php echo $tr['amount must be a multiple of one hundred'] ?? 'amount must be a multiple of one hundred';?>");
                        }
                        else if( (gtoken_input_daily_max != undefined) && (gtoken_input_daily_max != '') ){
                            if( gtoken_input_max > gtoken_input_daily_max ){
                                $(this).addClass('is-invalid');
                                $(this).next('div.invalid-feedback').text("<?php echo $tr['single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit'] ?? 'single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit';?>");
                            }
                            else{
                                $(this).removeClass('is-invalid');
                                $(this).next('div.invalid-feedback').text('');

                                $('#gtoken_input_daily_max').removeClass('is-invalid');
                                $('#gtoken_input_daily_max').next('div.invalid-feedback').text('');
                            }
                        }
                        else{
                            $(this).removeClass('is-invalid');
                            $(this).next('div.invalid-feedback').text('');
                        }
                    }
                }); // end blur
            </script>
        </div>
    </div>

    <?php // 人工戲幣存單日存入最大值 ?>
    <div class="row" style="margin-top:30px;">
        <div class="deposit_val_title">
            <i class="fas fa-asterisk"></i>
            <span class="text-right"><?php echo $tr['maximum single day of manual gtoken deposit'] ?? 'maximum single day of manual gtoken deposit';?></span>
        </div>
        <div class="col-2">
            <input type="number" class="js-range-slider form-control" id="gtoken_input_daily_max" min="100" step="100" value="<?php echo $account_setting->gtoken_input_daily_max ?? '1200';?>">
            <div class="invalid-feedback"></div>
            <script>
                $('#gtoken_input_daily_max').blur(function(){
                    var gtoken_input_max = $('#gtoken_input_max').val(),
                        gtoken_input_daily_max = $(this).val();

                    if( (gtoken_input_daily_max == undefined) || (gtoken_input_daily_max == '') ){
                        $(this).addClass('is-invalid');
                        $(this).next('div.invalid-feedback').text("<?php echo $tr['please enter this field'] ?? 'please enter this field';?>");
                    }
                    else{
                        gtoken_input_max = parseInt(gtoken_input_max);
                        gtoken_input_daily_max = parseInt(gtoken_input_daily_max);

                        if( (gtoken_input_daily_max % 100) != 0 ){
                            $(this).addClass('is-invalid');
                            $(this).next('div.invalid-feedback').text("<?php echo $tr['amount must be a multiple of one hundred'] ?? 'amount must be a multiple of one hundred';?>");
                        }
                        else if( (gtoken_input_max != undefined) && (gtoken_input_max != '') ){
                            if( gtoken_input_max > gtoken_input_daily_max ){
                                $(this).addClass('is-invalid');
                                $(this).next('div.invalid-feedback').text("<?php echo $tr['single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit'] ?? 'single-time gtoken deposit limit can not be greater than single-day gtoken deposit limit';?>");
                            }
                            else{
                                $(this).removeClass('is-invalid');
                                $(this).next('div.invalid-feedback').text('');

                                $('#gtoken_input_max').removeClass('is-invalid');
                                $('#gtoken_input_max').next('div.invalid-feedback').text('');
                            }
                        }
                        else{
                            $(this).removeClass('is-invalid');
                            $(this).next('div.invalid-feedback').text('');
                        }
                    }
                }); // end blur
            </script>
        </div>
    </div><hr>

    <?php // 權限列表-標題 ?>
    <div class="row">
        <div class="col-12 col-md-12">
            <span class="label label-primary">
                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php echo $tr['files reading'];?>
            </span>
        </div>
    </div><br>

    <?php // 權限列表-列表 ?>
    <div class="row">

        <?php
            // function groups
            foreach( $function_groups_data as $key_group=>$val_group ){
                echo '
                    <div class="col-4" style="margin-bottom:10px;">
                        <div class="col-12 col-md-12 actorclass">
                            <div class="custom-control custom-checkbox">';
                // 該group底下都是public function
                if($val_group->all_public_function){
                    echo        '<input type="checkbox" class="custom-control-input fake_group" id="'.$val_group->group_name.'" checked disabled>';
                }
                // 該群組有全部function的權限
                else if($val_group->has_all_function_premission){
                    echo        '<input type="checkbox" class="custom-control-input fake_group" id="'.$val_group->group_name.'" checked>';
                }
                // 該group"沒有"全部function的權限
                else{
                    echo        '<input type="checkbox" class="custom-control-input fake_group" id="'.$val_group->group_name.'">';
                }
                    echo        '<label class="custom-control-label" for="'.$val_group->group_name.'"><h5>'.$val_group->group_description.'</h5></label>
                            </div><br>
                        </div>'; // end 群組名稱標籤

                    // function名稱標籤
                    $public_function_tag = false; // 在該群組內的第一個public function後要放置public function標題。
                    foreach( $account_function_premission as $key_function=>$val_function ){ // function premission
                        if( $val_function->group_name == $val_group->group_name ){ // function屬於該function group
                            echo '
                                <div class="col-12 col-md-12 actorclass" style="margin-left:15px;">
                                    <div class="custom-control custom-checkbox">';
                        if( $val_function->function_public ){ // public function
                            if( !$public_function_tag ){
                                $public_function_tag = true;
                                echo '<h5 style="margin-bottom:0;"><b>'.( isset($tr['function list-public function']) ? $tr['function list-public function'] : 'Public Function' ).'</b></h5><hr style="margin-top:0.3rem;">';
                            }
                            echo '<label class="" for="'.$val_function->function_name.'">'.$val_function->function_title.'</label>';
                        }
                        else{
                            if( isset($val_function->has_premission) && $val_function->has_premission ){ // unpublicfunctionbut has premission
                                echo '
                                <input type="checkbox" class="custom-control-input fake_function" id="'.$val_function->function_name.'" data-group="'.$val_group->group_name.'" checked>
                                <label class="custom-control-label" for="'.$val_function->function_name.'">'.$val_function->function_title.'</label>';
                            }
                            else{ // unpublic function and has no premission
                                echo '
                                <input type="checkbox" class="custom-control-input fake_function" id="'.$val_function->function_name.'" data-group="'.$val_group->group_name.'">
                                <label class="custom-control-label" for="'.$val_function->function_name.'">'.$val_function->function_title.'</label>';
                            }
                        }
                            echo
                                    '</div>
                                    <br>
                                </div>';
                        }
                    } // end function foreach

                echo '
                    </div>
                ';
            } // end group foreach
        ?>

    </div><hr>

    <?php // 儲存/取消按鈕 ?>
    <div class="row">
        <div class="col-9">
            <div id="submit_to_admin_create_result" class="text-center font-weight-bold"></div>
        </div>
        <div class="col-auto">
            <button id="submit_to_admin_create" class="btn btn-success">
                <span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span>&nbsp;
                <?php echo $tr['Save']; ?>
            </button>
            <button id="cancel" type="button" class="btn btn-secondary ml-3" onclick="location.href='admin_management.php'">
                <span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span>&nbsp;
                <?php echo $tr['Cancel'];?>
            </button>
        </div>
    </div>


</form>

<?php end_section(); ?>
<!-- end of panelbody_content -->


<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script>

$(function() {
    <?php // 點擊function時，確認在所屬function group底下所有private funciotn是否都有權限，都有的話勾選所屬的function group ?>
    $('body').on('change', 'input.fake_function', function(){
        var this_function_group = $(this).data('group'); // 點擊的function所屬group

        if( $(this).prop('checked') ){
            var function_group_checked = true;
            $('input.fake_function').each(function(){
                if( $(this).data('group') == this_function_group ){
                    if( !$(this).prop('checked') ){
                        function_group_checked = false;
                    }
                }
            }); // end each

            if( function_group_checked ){
                $('#'+this_function_group).prop('checked', true);
            }
        }
        else{
            $('#'+this_function_group).prop('checked', false);
        }
    }); // end on

    <?php // 點擊function group時，把底下所屬的private funciotn都賦予權限 ?>
    $('body').on('change', 'input.fake_group', function(){
        var function_group = $(this).attr('id');
        if( $(this).prop('checked') ){
            $('input.fake_function').each(function(){
                if( $(this).data('group') == function_group ){
                    $(this).prop('checked', true);
                }
            }); // end each
        }
        else{
            $('input.fake_function').each(function(){
                if( $(this).data('group') == function_group ){
                    $(this).prop('checked', false);
                }
            }); // end each
        }
    }); // end on

    <?php // 輸出錯誤訊息 ?>
    function get_error(dom, msg){
        dom.next('.invalid-feedback').html( msg );
        if( !dom.hasClass('is-invalid') ){
            dom.addClass('is-invalid');
        }
    } // end get_error

    <?php // 清除錯誤訊息 ?>
    function clear_error(dom){
        dom.next('.invalid-feedback').html('');
        if( dom.hasClass('is-invalid') ){
            dom.removeClass('is-invalid');
        }
    } // end clear_error

    <?php // 密碼輸入 ?>
    $('body').on('blur', '#input_pwd', function(){
        // 密碼欄位有輸入
        if( ($(this).val() != '') && ($(this).val() != undefined)  ){
            // 確認密碼欄位有輸入
            if( ($('#confirm_pwd').val() != '') && ($('#confirm_pwd').val() != undefined)  ){
                // 兩個密碼一樣
                if( $(this).val() == $('#confirm_pwd').val() ){
                    clear_error( $(this).val() );
                    clear_error( $('#confirm_pwd').val() );
                }
                // 兩個密碼不一樣，顯示錯誤訊息
                else{
                    get_error( $(this), 'two of the passwords are different' );
                    get_error( $('#confirm_pwd'), 'two of the passwords are different' );
                }
            }
            // 確認密碼欄位沒有輸入，顯示錯誤訊息 (輸入期間不需要確認)
            /* else{
                clear_error( $(this) );
                get_error( $('#confirm_pwd'), 'please keyin your confirm password' );
            } */
        }
        // 密碼欄位沒有輸入
        else{
            // 確認密碼欄位沒有輸入，去除錯誤訊息
            if( $('#confirm_pwd').val() == '' ){
                clear_error( $(this) );
                clear_error( $('#confirm_pwd') );
            }
            // 確認密碼欄位有輸入，密碼欄位顯示錯誤訊息
            else{
                get_error( $(this), 'please keyin your password' );
                clear_error( $('#confirm_pwd') );
            }
        }
    }); // end on

    <?php // 確認密碼輸入 ?>
    $('body').on('blur', '#confirm_pwd', function(){
        // 確認密碼欄位有輸入
        if( ($(this).val() != '') && ($(this).val() != undefined)  ){
            // 密碼欄位有輸入
            if( ($('#input_pwd').val() != '') && ($('#input_pwd').val() != undefined)  ){
                // 兩個密碼一樣
                if( $(this).val() == $('#input_pwd').val() ){
                    clear_error( $('#input_pwd') );
                    clear_error( $(this) );
                }
                // 兩個密碼不一樣，顯示錯誤訊息
                else{
                    get_error( $(this), 'two of the passwords are different' );
                    get_error( $('#input_pwd'), 'two of the passwords are different' );
                }
            }
            // 密碼欄位沒有輸入，顯示錯誤訊息 (輸入期間不需要確認)
            else{
                clear_error( $(this) );
                get_error( $('#input_pwd'), 'please keyin your confirm password' );
            }
        }
        // 確認密碼欄位沒有輸入
        else{
            // 密碼欄位沒有輸入，去除錯誤訊息
            if( $('#input_pwd').val() == '' ){
                clear_error( $('#input_pwd') );
                clear_error( $(this) );
            }
            // 密碼欄位有輸入，確認密碼欄位顯示錯誤訊息
            else{
                clear_error( $('#input_pwd') );
                get_error( $(this), 'please keyin your password' );
            }
        }
    }); // end on

    <?php // 正規表達式-email ?>
    $('body').on('blur', '#email', function(){
        if( ($('#email').val() != '') && ($('#email').val() != undefined) ){
            var re = /^([\w\.\-]){1,64}\@([\w\.\-]){1,64}$/;
            if( !re.test( $('#email').val() ) ){
                get_error( $('#email'), 'wrong format email address' );
            }
            else{
                clear_error( $('#email') );
            }
        }
    }); // end on

    <?php // 建立子帳號-查詢是否有重複帳號 ?>
    <?php if( !isset($account_data->id) || empty($account_data->id) || ($account_data->id == null) ){?>
        <?php // 把新的子帳號傳到後台確認是否已經存在 ?>
        function isset_admin_account(){
            $.ajax({
                url: 'admin_management_edit_action.php',
                type: 'POST',
                headers: {},
                async: false,
                dataType: 'json',
                data: {
                    'method': 'query',
                    'account': $('#id_admin_account').val()
                },
                beforeSend: function(){},
                ajaxSend: function(){},
                success: function(data){
                    if( data.rowCount == 1 ){ <?php // 帳號已存在 ?>
                        get_error( $('#id_admin_account'), 'this account already exists' );
                        return false;
                    }
                    else{ <?php // 帳號不存在 ?>
                        clear_error( $('#id_admin_account') );
                    }
                }, // end success
                error: function(xhr, type){}, // end error
                complete: function(){}
            }); // end ajax
        } // end isset_admin_account

        <?php // 限定帳號不能為空且長度需要大於3碼 ?>
        $('body').on('blur', '#id_admin_account', function(){
            if( ($(this).val() == '') || ($(this).val().length < 3) ){
                get_error( $('#id_admin_account'), 'account length must be greater than 2 yards.' );
            }
            else if( ($(this).val()!='') && ($(this).val()!=undefined) ){
                isset_admin_account();
            }
        }); // end on

        <?php // 紀錄"輸入前"的值 ?>
        var keydown_account = '';
        $('body').on('keydown', '#id_admin_account', function(e){
            keydown_account = $(this).val();
        }); // end on

        <?php // 正規表達式 ?>
        $('body').on('keyup', '#id_admin_account', function(e){
            $(this).val( $(this).val().toLowerCase() ); <?php // 強迫變成小寫 ?>
            var re = /^([a-z0-9]){1,13}$/; <?php // 正規表達式，限制只能輸入小寫英文字母跟數字 ?>
            if( !re.test( $(this).val() ) && (e.which != 8) ){ <?php // 不符合上述正規表達式 與 按下的不是刪除鍵 ?>
                $(this).val( keydown_account );
            }
        }); // end on
    <?php }?>

    <?php // 打包帳號資料 ?>
    function get_account_data(){
        var result = {}; <?php // 裝載帳號資料 ?>
            result['id'] = $('#id').val(); <?php // 裝載帳號ID，如果是編輯帳號時這邊才會有值，新增帳號時這邊是空值，後台操作時會再確認一次權限 ?>
        var status = true; <?php // 是否可以傳出資料 ?>
        var items = [
            'id_admin_name',
            'input_pwd',
            'cell_phone',
            'email',
            'input[name=admin_account_status]:checked',
            'note'
        ];
        var item_name = [
            'realname',
            'passwd',
            'mobilenumber',
            'email',
            'status',
            'notes'
        ];

        for( i=0; i<items.length; i++ ){
            <?php // 如果email有輸入，檢查格式 ?>
            if( items[i] == 'email' ){
                if( ($('#email').val() != '') && ($('#email').val() != undefined) ){
                    var re = /^([\w\.\-]){1,64}\@([\w\.\-]){1,64}$/;
                    if( !re.test($('#email').val()) ){ // 格式檢查
                        get_error( $('#email'), 'wrong format email address' );
                        status = false;
                    }
                    else{
                        clear_error( $('#email') );
                        result.email = $('#email').val();
                    }
                }
                else{
                    result.email = '';
                }
            }
            <?php // 如果密碼有輸入，檢查兩個密碼是否一致 ?>
            else if( items[i] == 'input_pwd' ){
                <?php // 沒有修改密碼 ?>
                if( ($('#input_pwd').val() == '') && ($('#confirm_pwd').val() == '') ){
                    result.passwd = '';
                }
                <?php // 有要修改密碼 ?>
                else if( ($('#input_pwd').val() != '') && ($('#confirm_pwd').val() != '') ){
                    <?php // 兩個密碼一樣 ?>
                    if( $('#input_pwd').val() == $('#confirm_pwd').val() ){
                        result.passwd = $().crypt({method:'sha1', source:$('#input_pwd').val()})

                        clear_error( $('#input_pwd') );
                        clear_error( $('#confirm_pwd') );
                    }
                    <?php // 兩個密碼不一樣 ?>
                    else{
                        get_error( $('#input_pwd'), 'two of the passwords are different' );
                        get_error( $('#confirm_pwd'), 'two of the passwords are different' );
                        status = false;
                    }
                }
                <?php // 其中一個欄位沒有輸入 ?>
                else{
                    <?php // 密碼欄位沒有輸入 ?>
                    if( ($('#input_pwd').val() == '') || ($('#input_pwd').val() == undefined) ){
                        get_error( $('#input_pwd'), 'please keyin your password' );
                        status = false;
                    }
                    else{
                        clear_error( $('#input_pwd') );
                    }

                    <?php // 確認密碼欄位沒有輸入 ?>
                    if( ($('#confirm_pwd').val() == '') || ($('#confirm_pwd').val() == undefined) ){
                        get_error( $('#confirm_pwd'), 'please keyin your confirm password' );
                        status = false;
                    }
                    else{
                        clear_error( $('#confirm_pwd') );
                    }
                }
            }
            else if( items[i] == 'input[name=admin_account_status]:checked' ){
                if( ($(items[i]).val() != '') && ($(items[i]).val() != undefined) ){
                    result.status = $(items[i]).val();
                }
                else{
                    result.status = '';
                }
            }
            <?php // 其他欄位，判斷是否有輸入值 ?>
            else{
                if( ($('#' + items[i]).val() != '') && ($('#' + items[i]).val() != undefined) ){
                    result[ item_name[i] ] = $('#' + items[i]).val();
                }
                else{
                    result[ item_name[i] ] = '';
                }
            }
        } // end for

        <?php // 新建帳號，要擷取帳號欄位資料 ?>
        <?php if( !isset($account_data->id) || empty($account_data->id) || ($account_data->id == null) ){?>
            var new_admin_account = $("#id_admin_account").val();
            if( (new_admin_account == "") || (new_admin_account == undefined) ){
                get_error( $("#id_admin_account"), "please keyin your account" );
                status = false;
            }
            else{
                if( $('#id_admin_account').hasClass('is-invalid') ){
                    status = false;
                    get_error( $('#id_admin_account'), 'this account already exists' );
                }
                else{
                    result.account = new_admin_account;
                    clear_error( $("#id_admin_account") );
                }
            }
        <?php }?>

        var _result = {};
        if(status){
            _result.status = 'success';
            _result.content = result;
        }
        else{
            _result.status = 'fail';
        }
        return _result;
    } // end get_account_data

    <?php // 打包帳號設定值 ?>
    function get_account_setting(){
        var result = {},
            content = {},
            status = true, <?php // 檢查設定值狀態 ?>
            account_setting_items = [
                'gcash_input_max',
                'gcash_input_daily_max',
                'gtoken_input_max',
                'gtoken_input_daily_max'
            ];
        for( i=0; i<account_setting_items.length; i++ ){
            if( $('#' + account_setting_items[i]).hasClass('is-invalid') ){
                status = false;
                break;
            }
            else{
                content[account_setting_items[i]] = $('#' + account_setting_items[i]).val();
            }
        } // end for

        if(!status){
            result['status'] = 'fail';
            return result;
        }
        else{
            result['status'] = 'success';
        }
        result['content'] = content;
        return result;
    } // end get_account_setting

    <?php // 打包權限設定值 (待調整) ?>
    function get_function_premission(){
        var function_premission = [];
        $('input.fake_function').each(function(){
            var this_round_premission = {};
            this_round_premission['function_name'] = $(this).attr('id');
            this_round_premission['group_name'] = $(this).data('group');
            this_round_premission['status'] = ( ($(this).prop('checked')) ? 't' : 'f' );
            function_premission.push( this_round_premission );
        }); // end each
        return function_premission;
    } // end get_function_premission

    <?php // 送出 (已完成) ?>
    $('body').on('click', '#submit_to_admin_create', function(){

        <?php // 帳號資料 ?>
        var account_data = get_account_data();
        if( account_data.status == 'fail' ){
            alert('帳號資料尚未填寫完全！');
            return false;
        }

        <?php // 帳號設定值 ?>
        var account_setting = get_account_setting();
        if( account_setting.status == 'fail' ){
            alert('帳號設定值有誤，請重新設定！');
            return false;
        }

        <?php // 權限設定值 ?>
        var function_premission = get_function_premission();

        $.ajax({
			url: 'admin_management_edit_action.php',
			type: 'POST',
			headers: {},
			async: false,
			dataType: 'json',
			data: {
				'account_data': account_data.content,
				'account_setting': account_setting,
                'function_premission': function_premission
			},
			beforeSend: function(){},
			ajaxSend: function(){},
			success: function(data){
				if( data.status=='success' ){
					location.replace('admin_management.php');
				}
				else if( data.status=='fail' ){
					alert( data.msg );
				}
			}, // end success
			error: function(xhr, type){
				alert('Something is wrong！\n' + 'Please contact your web manager');
			}, // end error
			complete: function(){}
		}); // end ajax
        return false;
    }); // end on

    <?php // 取消 ?>
    $('body').on('click', '#cancel', function(){
        if( confirm('<?php echo $tr['Are you sure to quit this modification?']?>') ){
            location.replace('admin_management.php');
        }
    }); // end on

}); // END FUNCTION

</script>
<?php end_section(); ?>
<!-- end of extend_js