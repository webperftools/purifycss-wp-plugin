jQuery(document).ready(function($){
	'use strict';

	$("li#wp-admin-bar-purifycss-runsingle .ab-item").on( "click", adminbar_runsingle_click);
	$("li#wp-admin-bar-purifycss-clearsingle .ab-item").on( "click", adminbar_clearsingle_click);

	function adminbar_runsingle_click(ev) {
		console.log("Start purifycss for url: ", window.location.href);
		$.ajax({
			url: purifyData.ajaxurl,
			method: "POST",
			data: {
				action: 'purifycss_getcss_single',
				url: window.location.href
			}
		}).done( (data) => {
			console.log("Done purify: ", data);
			window.location.reload()
		}).fail( (err)=>{
			console.log("Failed purify: ", err);
		} )

		return false;
	}

	function adminbar_clearsingle_click(ev) {
		console.log("Start clear for url: ", window.location.href);
		$.ajax({
			url: purifyData.ajaxurl,
			method: "POST",
			data: {
				action: 'purifycss_clear_single',
				url: window.location.href
			}
		}).done( (data) => {
			console.log("Done clear: ", data);
			window.location.reload()
		}).fail( (err)=>{
			console.log("Failed clear: ", err);
		})

		return false;
	}
});
