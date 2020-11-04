function Float_promote($active_fp,$dev=""){		
	this.html='\
		<div id="'+ $active_fp["id"] +'" class="float-promote '+$dev+'" style="'+ $active_fp["position-x"] +': 0; '+ $active_fp["position-y"] +':0;">\
		<div class="float-promote-close float-promote-close-'+$active_fp["position-x"]+'><i class="fa fa-times" aria-hidden="true"></i></div>\
		<a href="'+ $active_fp["link"] +'" target="'+ $active_fp["target"] +'"><div data-img = "01"><img src="'+ $active_fp["img"] +'"><span>'+ $active_fp["content"] +'</span></div></a>\
		</div>';
	this.show = function($div) {
    $($div).append(this.html);
  	};
  	this.act = function(){
  		$(document).on('click', '#'+$active_fp["id"]+' .float-promote-close', function(){
  			this.hide();
  		});
  	};
}