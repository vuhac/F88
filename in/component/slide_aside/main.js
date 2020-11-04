function Slide_aside($active_sa,$position="left",$dev=""){
  if(typeof $active_sa["title"] == 'undefined')
    $active_sa["title"]="";
  var slide_aside_html="";
    slide_aside_html +='\
    <div id="'+$active_sa["id"]+'" class="head-'+$active_sa["style"]+' pollslider '+$position+' '+$dev+'">\
      <div class="pollSlider-button '+$active_sa["style"]+'">'+$active_sa["title"]+'</div>\
      <ul class="pollSiderContent '+$active_sa["style"]+'">\
      <li class="pollSiderContenthead"></li>';

    for (var j = 0; j < $active_sa["content"].length; j++) {
      var sa_link = $active_sa["content"][j]["link"];
      if($dev=="dev"){sa_link = "javascript: void(0)";}
        slide_aside_html+='<a href="'+sa_link+'" target="'+$active_sa["content"][j]["target"]+'"><li class="pollSiderContentli">';
        if ($active_sa["content"][j]["title"]!="") {
          slide_aside_html+='<div class="poolsidertit">'+$active_sa["content"][j]["title"]+'</div>';
        }
        if ($active_sa["content"][j]["txt"]!="") {
          slide_aside_html+='<div class="poolsidertxt">'+$active_sa["content"][j]["txt"]+'</div>';
        }
        slide_aside_html+='</li></a>';
    }

    slide_aside_html+='</ul>\
    </div>';

  this.html = slide_aside_html;

  this.show = function($div){
    $($div).append(this.html);
    $(document).ready(function () {
          var pollwidth =$("#"+$active_sa["id"]+" .pollSiderContentli").css("width");
          if($dev=="")
          { 
            if($position=="right")         
              $("#"+$active_sa["id"]).css({ "margin-right" : "-"+pollwidth,"display":"flex"});
            else if($position=="left")
              $("#"+$active_sa["id"]).css({ "margin-left" : "-"+pollwidth,"display":"flex"});
          }
    });
  };
  
  this.act = function(){
  $('.pollSlider-button').mouseover(function() {
    var pollhover = $(this).parent().attr('id');
    var slider_width = $("#"+pollhover+" .pollSiderContent").width();
    if($(this).parent().hasClass('right')){
        if ($("#"+pollhover+".pollSlider").css("margin-right") == 0 + "px" && !$(".pollSlider").is(':animated')) {
          $("#"+pollhover).animate({
            "margin-right": '-=' + slider_width
          });
        } else {
          if (!$(".pollSlider").is(':animated'))
          {
            $("#"+pollhover).animate({
              "margin-right": 0
            });
          }
        }
      } else{
        if ($("#"+pollhover+".pollSlider").css("margin-left") == 0 + "px" && !$(".pollSlider").is(':animated')) {
          $("#"+pollhover).animate({
            "margin-left": '-=' + slider_width
          });
        } else {
          if (!$(".pollSlider").is(':animated'))
          {
            $("#"+pollhover).animate({
              "margin-left": 0
            });
          }
        }
      }
    });  
  };
}