jQuery(document).ready(function($){
	'use strict';

	$("li#wp-admin-bar-purifycss-runsingle .ab-item").on( "click", adminbar_runsingle_click);
	$("li#wp-admin-bar-purifycss-rerunsingle .ab-item").on( "click", adminbar_clearsingle_click);

	function adminbar_runsingle_click(ev) {
		console.log("Start purifycss for url: ", window.location.href);
		if ($('#wp-admin-bar-purifycss > a.ab-item .spinner').length === 0){
			$('#wp-admin-bar-purifycss > a.ab-item').append("<span class='spinner'>Loading...</span>");
		}
		$.ajax({
			url: purifyData.ajaxurl,
			method: "POST",
			data: {
				action: 'purifycss_getcss_single',
				url: window.location.href
			}
		}).done( (data) => {
			console.log("Done purify: ", data);
			$("#wp-admin-bar-purifycss > a.ab-item .spinner").remove();
			window.location.reload()
		}).fail( (err)=>{
			console.log("Failed purify: ", err);
		} )

		return false;
	}

	function adminbar_clearsingle_click(ev) {
		console.log("Start clear for url: ", window.location.href);
		$('#wp-admin-bar-purifycss > a.ab-item').append("<span class='spinner'>&nbsp;Loading...</span>");
		$.ajax({
			url: purifyData.ajaxurl,
			method: "POST",
			data: {
				action: 'purifycss_clear_single',
				url: window.location.href
			}
		}).done( (data) => {
			console.log("Done clear: ", data);
			adminbar_runsingle_click(ev);
		}).fail( (err)=>{
			console.log("Failed clear: ", err);
		})

		return false;
	}
});
