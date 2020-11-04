function popup_promote($popup_promote){
    var popup_promote_html='';
    popup_promote_html +='<div class="modal fade" id="'+$popup_promote["id"]+'" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">\
      <div class="modal-dialog modal-dialog-centered popup-promote '+$popup_promote["style"]+'" role="document">\
        <div class="modal-content" style="width: auto;">\
          <div class="modal-header">\
            <h5 class="modal-title" id="exampleModalLongTitle">'+$popup_promote["title"]+'</h5>\
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>\
            </button>\
          </div>\
          <div class="modal-body p-0">\
            <a class="popup-promote-link" href="'+$popup_promote["link"]+'" target="'+$popup_promote["target"]+'"></a>\
          </div>\
        </div>\
      </div>\
    </div>';

    $("body").append(popup_promote_html);

    var image = $('<img/>', { 
        src : $popup_promote["img"]
    }).load(function () {
        $(".popup-promote").css("max-width",this.width);    
    })
    .appendTo(".popup-promote .popup-promote-link");
  //console.log(localStorage["new"]+localStorage["temp_date"]);
    $(window).load(function(){
      if(typeof localStorage["temp_date"] == "undefined"){
            $('#'+$popup_promote["id"]).modal('show');
      }
      else{
            localStorage["new_date"]=new Date();
            if((Date.parse(localStorage["new_date"])-Date.parse(localStorage["temp_date"]))>86400000){
              $('#'+$popup_promote["id"]).modal('show');
            }
      }
      $("#"+$popup_promote["id"]).click(function() {        
        localStorage["temp_date"]=new Date();
      });
    });
}