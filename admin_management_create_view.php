actor_management_view.php -->
<?php use_layout("template/beadmin.tmpl.php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
<!-- Jquery UI js+css  -->
<script src="in/popper.min.js"></script>
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
<style type="text/css">
/*.input-group>.form-control:not(:first-child) {
    border-radius: 4px;
}*/
/*.input-group>span{
    text-align:right;
    padding-right:30px;
}*/
/*.row+span{
    padding-left: 0px;
}*/

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
a.disabled {
   pointer-events: none;
   cursor: default;
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

<form>
    <div class="row">
        <div class="col-6">
            <div class="row">
                <div class="col-4">
                    <p class="text-right"><span style="color:red;">&#42;</span><?php echo $tr['administrator account'];?></p>
                </div>
                <div class="col-8">
                    <input id="id_admin_account" class="form-control" type="text" name="admin_account" placeholder="<?php echo $tr['administrator account'];?>">
                </div>
            </div>
            <div class="row">
                <div class="col-4">
                    <p class="text-right"><?php echo $tr['administrator name'];?></p>
                </div>
                <div class="col-8">
                    <input id="id_admin_name" class="form-control" type="text" name="admin_name" placeholder="<?php echo $tr['Real Name Example'];?>">
                </div>
            </div>
            <div class="row">
                <div class="col-4">
                    <p class="text-right"><span style="color:red;">&#42;</span><?php echo $tr['enter password']; ?></p>
                </div>
                <div class="col-8">
                    <input class="form-control" autocomplete="off" id="input_password" type="password" name="password" placeholder="<?php echo $tr['enter password'];?>">
                </div>
                <div class="col-6"><div id=""></div></div>
            </div>
        </div>
        <div class="col-6"><div id="admin_account_create_result"></div></div>
    </div>

  
    <div class="row">
        <div class="col-2">
            <p class="text-right"><span style="color:red;">&#42;</span><?php echo $tr['confirm'];echo $tr['your pwd'];?></p>
        </div>
        <div class="col-4">
            <input class="form-control" autocomplete="off" id="confirm_password" type="password" name="confirm_password" placeholder="<?php echo $tr['confirm'];echo $tr['your pwd'];?>">
        </div>
        <div class="col-6"><div id=""></div></div>
    </div>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['Cell Phone'];?></p>
        </div>
        <div class="col-4">
            <input class="form-control" id="cell_phone" type="text" name="cell_phone" placeholder="ex:15820859791">
        </div>
        <div class="col-6"><div id=""></div></div>
    </div>
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['Email'];?></p>
        </div>
        <div class="col-4">
            <input class="form-control" id="email" type="text" name="email" placeholder="ex:abcd1234@qq.com">
        </div>
        <div class="col-6"><div id=""></div></div>
    </div>


    <div class="row">
        <div class="col-2">
            <p class="text-right"><span style="color:red;">&#42;</span><?php echo $tr['identify'];?></p>
        </div>
        <div class="col-6 radio-padding">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="admin_account_status" id="admin_account_status_id1" value="0">
                <label class="form-check-label" for="admin_account_status_id1"><?php echo $tr['account disabled'];?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="admin_account_status" id="admin_account_status_id2" value="1" checked>
                <label class="form-check-label" for="admin_account_status_id2"><?php echo $tr['effective account'];?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="admin_account_status" id="admin_account_status_id3" value="2">
                <label class="form-check-label" for="admin_account_status_id3"><?php echo $tr['freeze account'];?></label>
            </div>
        </div>
        <div class="col-6"><div id=""></div></div>
    </div>
    
    <div class="row">
        <div class="col-2">
            <p class="text-right"><?php echo $tr['note'];?></p>
        </div>
        <div class="col-10">
            <textarea id="note" class="form-control" rows="3" name="note" placeholder="<?php echo $tr['note'];?>"></textarea>
        </div>
    </div>
    <hr>

    <!-- 權限設定 -->
    <div class="row">
        <div class="col-12 col-md-12">
            <span class="label label-primary">
            <span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php echo $tr['permission setting'];?>
            </span>
        </div>
    </div><br>


    <div class="row">
    <?php  $actor_dupication=actor_dupication_ary();//撈出重覆角色[group][eng_name][0]=id 
    ?>
        <?php foreach($classification_order as $order_value): ?> 
        <div class="col-4">
            <?php
                // 重複角色顯示
                if (count($actor_dupication[$order_value]??[])>0){
                    echo '
                        <label>
                            <h5>'.$actor_group_name[$order_value].'</h5>
                        </label>';
                    foreach ($actor_dupication[$order_value] as $engname=>$id_ary){
                        $actor_group_str_combine=[];
                        $actor_group_str_combine_show=$actor_group_str_combine_result='';
                        foreach ($id_ary as $pkid){
                            $actor_group_str_combine= render_sub_category_select_html(
                                $permission_id_map_actorid,
                                $permission_id_map_actorname,
                                $pkid
                            );
                            $actor_group_str_combine_result.=$actor_group_str_combine['option'];
                        }
                        if($is_ops){
                            $pencil_show='<a class="'.$permission_id_map_actorid[$pkid].'_a"  href="actor_management.php?actor_name='.$actor_group_str_combine['eng_name'].'" target="_blank"> 
                                <i class="fas fa-pencil-alt"></i>
                            </a>';
                        }else{
                            $pencil_show='';
                        }

                        $actor_group_str_combine_show='
                        <div class="col-10 col-md-10 actorclass">       
                            <select name="sel_name_actor" class="'.$permission_id_map_actorid[$pkid].'_se_class" size="1">
                                <option value="">'.$tr['unselected'].' -'.$permission_id_map_actorname[$pkid].'</option>
                                '.$actor_group_str_combine_result.'
                            </select>'.$pencil_show.'
                        </div>
                        <br>';
                        echo($actor_group_str_combine_show);
                    }
                }
            ?>
        </div>
        <?php endforeach; ?>
    </div>


    <hr>
    <!-- 檔案讀取 -->
    <div class="row">
        <div class="col-12 col-md-12">
            <span class="label label-primary">
            <span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php echo $tr['files reading'];?>
            </span>
        </div>
    </div><br>

    
    <div class="row">
    <?php $not_actor_dupication_ary=not_actor_dupication_ary();//撈出不重覆角色[group][eng_name]=id ?>
        <?php $disab_txt='disabled'; foreach($classification_order as $order_value): ?> 
        <div class="col-4">
            <label>
                <h5>
                <input type="checkbox" name="main_category" class="<?php echo $order_value ;?>_in_cl_actor_list_parent" checked  
                            onclick="main_category_check_all(this,'<?php echo $order_value;?>_in_cl_actor_list')"
                            value="cate_<?php echo $order_value ;?>"
                            <?php echo $disab_txt; ?>>
                            <?php echo $actor_group_name[$order_value] ;?>
                </h5>
            </label>
            <?php
                foreach ($not_actor_dupication_ary[$order_value] as $engname=>$pkid){
                    $disab_txt=''; 
                    if(!in_array($engname,$checkable_file_list)){
                        $disab_txt='disabled';
                    }
                    $actor_group_str_combine= render_sub_category_checkboxes_html(
                        $permission_id_map_actorname,
                        $order_value,
                        $pkid,
                        $disab_txt,
                        'checked',
                        $is_ops
                    );
                    echo($actor_group_str_combine);
                }
            ?>
            <br>
        </div>

        <?php endforeach; ?>
    </div>




    <hr>
    <div class="row">
        <div class="col-9">
            <div id="submit_to_admin_create_result" class="text-center font-weight-bold"></div>
        </div>
        <div class="col-auto">
            <button id="submit_to_admin_create" class="btn btn-success">
            <span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span><?php echo $tr['Save']; ?>
            </button>
            <button id="cancel" type="button" class="btn btn-secondary ml-3" onclick="location.href='admin_management.php'">
            <span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span><?php echo $tr['Cancel'];?>
            </button>
        </div>
    </div>
</form>

<?php end_section(); ?>
<!-- end of panelbody_content -->



<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script type="text/javascript" language="javascript">
$( "select" )
  .on('click',function (e) {
    var clname =$.trim($(e.target).attr('class'));
    var link_id= $('.'+clname+' option:selected').val();
    var a_class= clname.replace("_se_class", "_a");
    var no_a_class=clname . replace("_se_class", "");
    $('.'+a_class).attr("href", "actor_management.php?actor_name="+no_a_class);
    if (link_id!="" ){
        $('.'+a_class).attr("href", "actor_management_editor.php?a=edit&snid="+link_id);
    }

  });



// 角色分類全選.取消
function main_category_check_all(obj,cName) { 
    var checkboxs = document.getElementsByClassName(cName); 
    for(var i=0;i<checkboxs.length;i++){checkboxs[i].checked = obj.checked;} 
    // 其它寫法
    // $('#transactionAllType').on('change', function() {
    //   if ($('#transactionAllType').prop('checked')) {
    //     $('.transactionType').prop('checked', true);
    //   } else {
    //     $('.transactionType').prop('checked', false);
    //   }
    // });
}

// 子項目全選時，大分類選取;反之取消勾選
$('.actorclass input:checkbox').on('change', function(e) {
    var parent_checkbox_class =  $.trim($(e.target).attr('class')) + '_parent';
    var checkbox_class =$.trim($(e.target).attr('class'));
    // if ($('.'+checkbox_class+':checkbox').length - $('.'+checkbox_class+':checkbox').filter(':checked').length == 0) {
    // console.log(checkbox_class);
    // console.log($('.'+checkbox_class+':checked').length);
    // console.log($('.'+checkbox_class).length);
    if ($('.'+checkbox_class+':checked').length == $('.'+checkbox_class).length) {
        $('.'+parent_checkbox_class).prop('checked', true);
        // $('.manual_gcash_deposit').prop('checked', false);
    } else {
        $('.'+parent_checkbox_class).prop('checked', false);
    }
});

// 角色分類全選.取消
// $('.member_agent_in_cl_actor_list_parent').on('change', function() {
//     if ($('.member_agent_in_cl_actor_list_parent').prop('checked')) {
//         $('.member_agent_in_cl_actor_list').prop('checked', true);
//         // $('.manual_gtoken_deposit').prop('checked', false);
//         // $('.manual_gcash_deposit').prop('checked', false);
//     } else {
//         $('.member_agent_in_cl_actor_list').prop('checked', false);
//         // $('.manual_gcash_deposit').prop('checked', true);
//     }
// });

$('.member_agent_in_cl_actor_list').on('change', function() {
    if ($('.member_agent_in_cl_actor_list:checked').length == $('.member_agent_in_cl_actor_list').length) {
    $('.member_agent_in_cl_actor_list_parent').prop('checked', true);
    } else {
    $('.member_agent_in_cl_actor_list_parent').prop('checked', false);
    }
});



// 檢查會員帳號格式，是否有誤
$('#id_admin_account').click(function(){
    var admin_account_create_input = $('#id_admin_account').val();
    var csrftoken = '<?php echo $csrftoken; ?>';
    $.post('admin_management_create_action.php?a=admin_check',
        {   admin_account_create_input: admin_account_create_input,
            csrftoken:csrftoken
        },
        function(result){
            $('#admin_account_create_result').html(result);
        });
});
$(function() {
    $('#id_admin_account').keyup(function(e) {
        // all key
        if(e.keyCode >= 65 || e.keyCode <= 90) {
            $('#id_admin_account').trigger('click');
        }
    });
});




$("#submit_to_admin_create").click(function(e){
    e.preventDefault();

    if ($('#input_password').val() == '' || $('#confirm_password').val() == '') {
        alert("<?php echo $tr['Please fill in the password'];?>");
        return;
    }else if
        ($('#input_password').val().length < 4  || $('#confirm_password').val().length < 4) {
        alert("<?php echo $tr['The password must be 4 numbers or more!'];?>");
        return;
    }

    var submit_to_admin_create = 'is_admincreateaccount';
    var admin_account_create_input = $('#id_admin_account').val();
    var id_admin_name = $("#id_admin_name").val();
    // var input_password_valid = $().crypt({method:'sha1', source:jQuery.trim($('#input_password').val()) });
    // var confirm_password_valid = $().crypt({method:'sha1', source:jQuery.trim($('#confirm_password').val()) });
    var input_password_valid = $().crypt({method:'sha1',source:$('#input_password').val() });
    var confirm_password_valid = $().crypt({method:'sha1',source:$('#confirm_password').val() });
    var cell_phone = $("#cell_phone").val();
    var email = $("#email").val();
    var status =$('input[name=admin_account_status]:checked').val();
    var note = $("#note").val();

    var input_name_actor = [];
    //  原本用序列化取值，但checkbox disabled 後，會抓不到值傳到action，如果將disable取消，則輸入密碼錯誤時，卻重新開啟，這算bug；如果要取值前後，disable取消再還原，那這些disable的欄位，要先標計，這樣卻用太多步驟，所以最好方式，重新取checkbox的值，不論是否有disable。
    // var input_name_actor = $('input[name="input_name_actor"]').serializeArray();
    $('input[name="input_name_actor"]:checked').each(function(){
        input_name_actor.push({
            name:$(this).attr("name"),
            value:$(this).val(),
            id:$(this).attr("class")
        });
    });

    var select_name_actor=[];
    $('select[name="sel_name_actor"]').each(function(){
        select_name_actor.push({
            name:$(this).attr("name"),
            value:$(this).val(),
            id:$(this).attr("class")
    });
    });
    console.log(select_name_actor);

    var csrftoken = '<?php echo $csrftoken; ?>';
    // console.log(input_name_actor);
    // console.log(status);
    // console.log(csrftoken);

    if((input_password_valid) !='' && (confirm_password_valid) !='' ){
        if(input_password_valid == confirm_password_valid ){
            var currently_pwd = 1;
        }else{
            var currently_pwd = 0;
            alert("<?php echo $tr['Inconsistent passwords'];?>");
            return;
        }
    }
    if (currently_pwd==1 && input_password_valid !=''){
    $.post('admin_management_create_action.php?a=admin_create',
    {
        submit_to_admin_create: submit_to_admin_create,
        admin_account_create_input:admin_account_create_input,
        id_admin_name: id_admin_name,
        input_password_valid:input_password_valid,
        confirm_password_valid:confirm_password_valid,
        cell_phone:cell_phone,
        email:email,
        status:status,
        note:note,
        csrftoken:csrftoken,
        input_name_actor:input_name_actor,
        select_name_actor:select_name_actor
    },
    function(result){
        $('#submit_to_admin_create_result').html(result);}

    ); 
    }; 


});

</script>
<?php end_section(); ?>
<!-- end of extend_js