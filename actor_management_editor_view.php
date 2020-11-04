<?php // actor_management_view.php ?>
<?php use_layout("template/beadmin.tmpl.php"); ?>

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

    <!-- Bootstrap Toogle -->
    <link href="./in/bootstrap-toggle/2.2.2/bootstrap-toggle.min.css" rel="stylesheet">
    <script src="./in/bootstrap-toggle/2.2.2/bootstrap-toggle.min.js"></script>

    <!-- 自訂css -->
    <link rel="stylesheet" type="text/css" href="ui/style_seting.css">
    <style>
        .material-switch > input[type="checkbox"] {
              visibility:hidden;
        }
        .material-switch > .label-success {
            cursor: pointer;
            height: 0px;
            position: relative;
            width: 40px;
        }
        .material-switch > .label-success::before {
            background: rgb(0, 0, 0);
            box-shadow: inset 0px 0px 10px rgba(0, 0, 0, 0.5);
            border-radius: 8px;
            content: '';
            height: 16px;
            margin-top: -8px;
            margin-left: -18px;
            position:absolute;
            opacity: 0.3;
            transition: all 0.4s ease-in-out;
            width: 30px;
        }
        .material-switch > .label-success::after {
            background: rgb(255, 255, 255);
            border-radius: 16px;
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
            content: '';
            height: 16px;
            left: -4px;
            margin-top: -8px;
            margin-left: -18px;
            position: absolute;
            top: 0px;
            transition: all 0.3s ease-in-out;
            width: 16px;
        }
        .material-switch > input[type="checkbox"]:checked + .label-success::before {
            background: inherit;
            opacity: 0.5;
        }
        .material-switch > input[type="checkbox"]:checked + .label-success::after {
            background: inherit;
            left: 20px;
        }
    </style>
<?php end_section(); ?>

<?php begin_section('page_title'); ?>
    <ol class="breadcrumb">
        <li><a href="home.php"><?php echo ( isset($tr['Home']) ? $tr['Home'] : 'Home' ); ?></a></li>
        <li><a href="#"><?php echo ( isset($tr['maintenance']) ? $tr['maintenance'] : 'maintenance' ); ?></a></li>
        <li><a href="actor_management.php"><?php echo ( isset($tr['role managment']) ? $tr['role managment'] : 'function managment' ); ?></a></li>
        <li class="active"><?php echo ( isset($tr['edit function']) ? $tr['edit function'] : 'edit function' ); ?></li>
    </ol>
<?php end_section(); ?>

<?php begin_section('paneltitle_content'); ?>
    <i class="fas fa-user-lock"></i><?php echo ( isset($tr['edit function']) ? $tr['edit function'] : 'edit function' ); ?>
    <div id="csv"  style="float:right;margin-bottom:auto"></div>
<?php end_section(); ?>

<?php begin_section('panelbody_content'); ?>
    <?php // 權限資訊 ?>
    <div class="row">
        <div class="col-12 col-md-12">
            <span class="label label-primary">
                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
                <?php echo ( isset($tr['function infomation']) ? $tr['function infomation'] : 'function infomation' ); ?>
            </span>
        </div>
    </div>
    <br>

    <div class="row">
        <?php // 所屬群組-標題 ?>
        <div class="col-12 col-md-2">
            <p class="text-right">
                <?php echo ( isset($tr['group name']) ? $tr['group name'] : 'group name' ); ?>
            </p>
        </div>

        <?php // 所屬群組-輸入框 ?>
        <div class="col-4">
            <input type="text" class="form-control" id="group_name" value="<?php echo ( isset($function_data['group_description']) ? $function_data['group_description'] : '' );?>" disabled>
              <br>
        </div>
        <!---------------------------------------------------------------------------------------------------------------->

        <?php // 權限開放狀態-標題 ?>
        <div class="col-12 col-md-2">
            <p class="text-right">
                <?php echo ( isset($tr['function open state']) ? $tr['function open state'] : 'function open state' ); ?>
            </p>
        </div>

        <?php // 權限開放狀態-checkbox ?>
        <div class="col-4">
            <input type="checkbox" data-toggle="toggle" id="function_public_state" <?php echo ( ( isset($function_data['function_public']) && ($function_data['function_public']=="true") ) ? 'checked' : '' );?>>
            <br>
        </div>
        <!---------------------------------------------------------------------------------------------------------------->

        <?php // 權限代號-標題 ?>
        <div class="col-12 col-md-2">
            <p class="text-right">
                <?php echo ( isset($tr['function code']) ? $tr['function code'] : 'function code' ); ?>
            </p>
        </div>

        <?php // 權限代號-輸入框 ?>
        <div class="col-4">
            <input type="text" class="form-control" id="function_name" placeholder="<?php echo ( isset($tr['function code']) ? $tr['function code'] : 'function code' ); ?>" value="<?php echo ( isset($function_data['function_name']) ? $function_data['function_name'] : '' );?>" disabled>
              <br>
        </div>
        <!---------------------------------------------------------------------------------------------------------------->

        <?php // 權限啟用狀態-標題 ?>
        <div class="col-12 col-md-2">
            <p class="text-right">
                <?php echo ( isset($tr['function status']) ? $tr['function status'] : 'function status' ); ?>
            </p>
        </div>

        <?php // 權限啟用狀態-checkbox ?>
        <div class="col-4">
            <input type="checkbox" data-toggle="toggle" id="function_status" <?php echo ( ( isset($function_data['function_status']) && ($function_data['function_status']=="true") ) ? 'checked' : '' );?>>
            <br>
        </div>
        <!---------------------------------------------------------------------------------------------------------------->

        <?php // 權限名稱-標題 ?>
        <div class="col-12 col-md-2">
            <p class="text-right">
                <?php echo ( isset($tr['function name']) ? $tr['function name'] : 'function name' ); ?>
            </p>
        </div>

        <?php // 權限名稱-輸入框 ?>
        <div class="col-4">
            <input type="text" class="form-control"  id="function_title" placeholder="<?php echo ( isset($tr['function name']) ? $tr['function name'] : 'function name' ); ?>" value="<?php echo ( isset($function_data['function_title']) ? $function_data['function_title'] : '' );?>">
            <div class="invalid-feedback">
                Error Message Here.
            </div>
              <br>
        </div>
        <!---------------------------------------------------------------------------------------------------------------->

        <?php // 維護狀態-標題 ?>
        <div class="col-12 col-md-2">
            <p class="text-right">
                <?php echo ( isset($tr['function maintain status']) ? $tr['function maintain status'] : 'function maintain status' ); ?>
            </p>
        </div>

        <?php // 維護狀態-checkbox ?>
        <div class="col-4">
            <input type="checkbox" data-toggle="toggle" id="function_maintain_status" <?php echo ( ( isset($function_data['function_maintain_status']) && $function_data['function_maintain_status'] ) ? 'checked' : '' );?>>
            <br>
        </div>
        <!---------------------------------------------------------------------------------------------------------------->

        <?php // 權限描述-標題 ?>
        <div class="col-12 col-md-2">
            <p class="text-right">
                <?php echo ( isset($tr['function description']) ? $tr['function description'] : 'function description' ); ?>
            </p>
        </div>

        <?php // 權限描述-textarea ?>
        <div class="col-8">
            <textarea class="form-control" cols="100" rows="5" id="function_description" placeholder="<?php echo ( isset($tr['function description']) ? $tr['function description'] : 'function description' ); ?>"><?php echo ( isset($function_data['function_description']) ? $function_data['function_description'] : '' );?></textarea>
            <br>
        </div>
        <!---------------------------------------------------------------------------------------------------------------->
    </div>
    <hr>

    <?php // 檔案資訊 ?>
    <div class="row">
        <div class="col-12 col-md-12">
            <span class="label label-primary">
                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php echo ( isset($tr['files name']) ? $tr['files name'] : 'files name' ); ?>
            </span>
        </div>
    </div>
    <br>

    <?php // 功能所屬頁面 ?>
    <?php if (count($function_pages) > 0) {?>
        <?php foreach($function_pages as $val) { ?>
            <div class="row">
                <?php // 頁面檔案名稱-標題 ?>
                <div class="col-12 col-md-2">
                    <p class="text-right">
                        <?php echo ( isset($tr['page name']) ? $tr['page name'] : 'page name' ); ?>
                    </p>
                </div>

                <?php // 頁面檔案名稱-輸入框 ?>
                <div class="col-4">
                    <input type="text" class="form-control fake_page_name" value="<?php echo ( isset($val['page_name']) ? $val['page_name'] : '' );?>" disabled>
                    <br>
                </div>

            </div>
        <?php }?>
    <?php } else { // 尚未有所屬的頁面?>
        <div class="row">
            <div class="col-12 col-md-12">
                <h5><?php echo ( isset($tr['no page belong']) ? $tr['no page belong'] : 'No page belong.' ); ?></h5>
            </div>
        </div>
    <?php }?>
    <hr>

    <div class="col-12 mx-auto">
        <p class="text-center">

            <?php // 儲存 ?>
            <button id="submit_to_inquiry" class="btn btn-success ml-3">
                <span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span>&nbsp; <?php echo ( isset($tr['Save']) ? $tr['Save'] : 'save' ); ?>
            </button>

            <?php // 取消 ?>
            <button id="cancel" class="btn btn-secondary ml-3">
                <span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span>&nbsp; <?php echo ( isset($tr['Cancel']) ? $tr['Cancel'] : 'cancel' );?>
            </button>
        </p>
    </div>
<?php end_section(); ?>

<?php begin_section('extend_js'); ?>
    <script>
        $(function(){
            // 點擊取消，確認後回到權限管理
            $('body').on('click', '#cancel', function(){
                if( confirm('<?php echo $tr['Are you sure to quit this modification?']?>') ){
                    location.replace('actor_management.php');
                }
            }); // end on

            // 輸出錯誤訊息
            function get_error( dom, msg ){
                dom.next('.invalid-feedback').html( msg );

                if( !dom.hasClass('is-invalid') ){
                    dom.addClass('is-invalid');
                }
            } // end get_error


            // 清除錯誤訊息
            function clear_error( dom ){
                dom.next('.invalid-feedback').html('');

                if( dom.hasClass('is-invalid') ){
                    dom.removeClass('is-invalid');
                }
            } // end clear_error


            // 取得funciotn data
            function get_function_datas(){
                var result = {}; // 裝載funciotn data

                // 如果function name為空，跳出錯誤訊息提示。
                if( $('#function_title').val() == '' ){
                    get_error( $('#function_title'), 'please keyin your function name' );
                    result['status'] = 'fail';
                }
                else{
                    clear_error( $('#function_title') );
                }

                // function name不為空，獲取其他資料並回傳
                if( result['status'] != 'fail' ){
                    result['status'] = 'success';
                    result['data'] = {};

                    result['function_name'] = $('#function_name').val(); // string
                    result['data']['function_title'] = $('#function_title').val(); // string
                    result['data']['function_public'] = $('#function_public_state').prop('checked'); // boolean
                    result['data']['function_status'] = $('#function_status').prop('checked'); // boolean
                    result['data']['function_maintain_status'] = $('#function_maintain_status').prop('checked'); // boolean
                    result['data']['function_description'] = $('#function_description').val(); // string
                }
                return result;
            } // end get_function_datas


            // 取得page data
            /* function get_pages_datas(){
                var result = [], // 各個頁面資料
                    pages_name = [], // 頁面檔案名稱
                    page_status = [], // 頁面狀態
                    page_description = [], // 頁面描述
                    js_selector = ['.fake_page_name', '.fake_page_status', '.fake_page_description'],
                    push_array = [pages_name, page_status, page_description];

                // 遍歷js_selector，取其資料，並存放至push_array
                for( i=0; i<js_selector.length; i++ ){
                    $( js_selector[i] ).each(function(){
                        push_array[i].push( $(this).val() );
                    }); // end each
                } // end for

                // 組合資料
                for( j=0; j<pages_name.length; j++ ){
                    var this_page_data = {};
                    this_page_data['pages_name'] = pages_name[j]; // string
                    this_page_data['page_status'] = page_status[j]; // string
                    this_page_data['page_description'] = page_description[j]; // string
                    result.push( this_page_data );
                } // end for
                // console.log( result );
                return result;
            } // end get_pages_datas */


            // ajax
            function _launch( function_datas ){
                $.ajax({
                        url: 'actor_management_operate_action.php',
                        type: 'POST',
                        headers: {},
                        async: false,
                        dataType: 'json',
                        data: {
                            'function_datas': function_datas
                        },
                        beforeSend: function(){},
                        ajaxSend: function(){},
                        success: function( data ){
                            if( data.status == 'success' ){ // 帳號已存在
                                location.replace('actor_management.php');
                            }
                            else if( data.status == 'fail' ){ // 帳號不存在
                                alert('updated failed');
                            }
                        }, // end success
                        error: function(xhr, type){
                        }, // end error
                        complete: function(){}
                    }); // end ajax
            } // end _launch


            // 點擊儲存
            $('body').on('click', '#submit_to_inquiry', function(){
                var function_datas = get_function_datas(); // console.log( function_datas );
                // 如果function name為空，跳出錯誤訊息提示(break)
                if( function_datas.status == 'fail' ){
                    return false;
                }
                else if( function_datas.status == 'success' ){
                    function_datas['status'] = null;
                }

                _launch( function_datas );
                return false;
            }); // end on
        }); // END FUNCTION
    </script>
<?php end_section(); ?>
<!-- end of extend_js