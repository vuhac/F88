<?php use_layout("template/beadmin.tmpl.php"); ?>

<!-- begin of extend_head -->
<?php begin_section('extend_head'); ?>
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

<!-- begin of page_title -->
<?php begin_section('page_title'); ?>
<ol class="breadcrumb">
  <li><a href="home.php"><?php echo $tr['Home']; ?></a></li>
  <li><a href="#"><?php echo $tr['maintenance']; ?></a></li>
  <li><a href="actor_management.php"><?php echo $menu_breadcrumbs; ?></a></li>
  <li class="active"><?php echo $function_title; ?></li>
</ol>
<?php end_section(); ?>
<!-- end of page_title -->


<!-- 內容 -->
<!-- begin of panelbody_content -->
<?php begin_section('panelbody_content'); ?>

<form>
  <div class="row">
    <div class="col-12 col-md-12">
      <span class="label label-primary">
        <span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php echo $tr['actor information'];?>
      </span>
    </div>
  </div>
  <br>

  <div class="row">

    <div class="col-12 col-md-2"><p class="text-right"><?php echo $tr['actor input eng no'];?></p></div>
    <div class="col-4">
        <input type="text" class="form-control" id="account_query_id" name="a_id" placeholder="ex.aaaa" onkeyup = "return ValidateCNWord($(this),value);",
        onblur = "return ValidateCNWord($(this),value);" required> 
    </div>

    <div class="col-12 col-md-2"><p class="text-right"><?php echo $tr['actor input name'];?></p></div>
    <div class="col-4">
        <input type="text" class="form-control" id="account_query" name="aq" placeholder="<?php echo $tr['tester'];?>" 
        required>
        <br>
    </div>

    <div class="col-12 col-md-2"><p class="text-right"><?php echo $tr['actor input name eng'];?></p></div>
    <div class="col-4">
        <input type="text" class="form-control" id="account_eng_query" name="a_eng_q" placeholder="ex.Add agent" required>
        <br>
    </div>

    <div class="col-12 col-md-2"><p class="text-right"><?php echo $tr['permission description'];?></p></div>
    <div class="col-4">
        <textarea class="form-control" id="account_permission_desc" name="per_desc" placeholder="<?php echo $tr['Can maintain the switch of the entertainment city'];?>"></textarea><br>
    </div>

    <div class="col-12 col-md-2"><p class="text-right"><?php echo $tr['functional group'];?></p></div>
    <div class="col-4">
      <select id="group" class="form-control" name="group" style="width:auto;">
        <?php foreach ($actor_group_name as $key => $value): ?>
          <option value="<?php echo $key; ?>"><?php echo $value ; ?></option>
        <?php endforeach ;?>
      </select>
    </div>

    <div class="col-12 col-md-2"><p class="text-right"><?php echo $tr['enable'];?></p></div>
    <div class="col-4 material-switch pull-left">
      <input id="status_open" name="status_open" class="checkbox_switch" value="1" type="checkbox" checked >
      <label for="status_open" class="label-success"></label>
    </div>
    <br>

  </div>
    <hr>

  <div class="row">
      <div class="col-12 col-md-12">
        <span class="label label-primary">
          <span class="glyphicon glyphicon-ok" aria-hidden="true"></span><?php echo $tr['files name'];?>
        </span>
			  <br><br>
      </div>
  </div>

  <div class="row">
    <div class="col-12 col-md-2" ><p class="text-right"><?php echo $tr['files name'];?>(<?php echo $tr['add file name'];?>)</p></div>
    <div id="file_list" class="col-5">
        <div class="d-flex mt-1 input-group">
            <input type="text" class="form-control file_name" name="add_file_name[]" placeholder="ex.deposit_onlinepayment_config.php" value="" required>
            <div class="input-group-append">
                <button type="button" class="btn btn-danger delete_btn" title="<?php echo $tr['delete'];?>">
			              <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
			          </button>
            </div>
        </div>
    </div>

    <div class="mt-1">
      <button type="button" class="btn btn-success" id="add_more" onclick="addItem()" title="<?php echo $tr['add'];?>">
        <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
      </button>
    </div>

  </div>
  <hr>

  <div class="col-12 mx-auto">
    <p class="text-center">
      <button id="submit_to_inquiry" class="btn btn-success  ml-3">
        <span class="glyphicon glyphicon-floppy-saved" aria-hidden="true"></span>&nbsp; <?php echo $tr['Save']; ?>
      </button>
      <button id="cancel" class="btn btn-secondary  ml-3" onclick="javascript:location.href='actor_management.php'">
        <span class="glyphicon glyphicon-floppy-remove" aria-hidden="true"></span>&nbsp; <?php echo $tr['Cancel']; ?>
      </button>
    </p>
  </div>

<form>

<div class="row">
  <div id="preview_result"></div>
</div>

<?php end_section(); ?>
<!-- end of panelbody_content -->


<!-- begin of extend_js -->
<?php begin_section('extend_js'); ?>
<script type="text/javascript" language="javascript" class="init">
function paginateScroll() { // auto scroll to top of page
  $("html, body").animate({
     scrollTop: 0
  }, 100);
}

// 新增檔名
function addItem(){

  var str_file = $(".file_name").val();
  //console.log(str_file);

  if(str_file != '') {
   
   var tmpl = `
    <div class="d-flex mt-1 input-group">
      <input type="text" class="form-control file_name" name="add_file_name[]" placeholder="ex.deposit_onlinepayment_config.php" value="">

        <div class="input-group-append">
          <button type="button" class="btn btn-danger delete_btn" title="{$tr['delete']}">
            <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
          </button>
        </div>
    </div>
   `;

  $(tmpl).fadeIn().appendTo("#file_list");

  }else if(str_file == ''){
    alert("<?php echo $tr['Please fill in the file name'];?>");
  };
   
};


// 刪除
$("#file_list").on('click','.delete_btn',function(e){
  var select = $(e.target).parents(".input-group").remove();
  //console.log(select);
});

// 限制設定 只能輸入數字
function ValidateNumber(e, pnumber){
    if (!/^\d+$/.test(pnumber)){
        e.value = /^\d+/.exec(e.value);
    }
    return false;
};

// function check_abc(){
//   var engValue = document.getElementById("account_eng_query").value;
//   re = /[a-zA-Z0-9]/;
//   if(!re.test(engValue)){
//     alert("有非英文及數字的字喔!");
//   }  
// }
// 角色資訊 
// 角色只能輸入英文
function ValidateCNWord(e, value) {
  if (/[\W]/g.test(value)) {
    value = value.replace(/[\W]/g, '')
    $(e).val(value);
  }
  return false;
};

$(function() {
  // ------------------------------------
  // 新增角色
  // -------------------------------------
  $("#submit_to_inquiry").click(function(e){
      e.preventDefault();

      // account id
      var a_id = $("#account_query_id").val();
      // account name
      var aq = $("#account_query").val();
      // 角色英文名稱
      var account_eng = $("#account_eng_query").val();
      // console.log(account_eng);
      // 權限描述
      var per_desc = $("#account_permission_desc").val();

      //功能群組
      var group = $("#group").val();
     
      // 新增檔名
      // var file_name = $(".file_name").val();
      file_name_ary=[];
      $(".file_name").each(function(){
        file_name_ary.push(
          $(this).val()
        );

      });

      // status
      if($("#status_open").prop('checked')){
        var status_open = 1 ; //open
      } else{
        var status_open = 0; // close
      }
      // var formData = new FormData($('form')[0]);
      // console.log(file_name_ary);

      $.ajax({
        url: 'actor_management_operate_action.php?a=add',
        type: 'POST',
        data:{
          a_id:a_id,
          aq:aq,
          a_eng_q:account_eng,
          per_desc:per_desc,
          group:group,
          status_open:status_open,
          add_file_name:file_name_ary
        },
        // data: formData, 
        success: function(response){
          $('#preview_result').html(response);
        },
        error: function(error){
           $("#preview_result").html(error);
        }
      })
    })
});

</script>
<?php end_section(); ?>