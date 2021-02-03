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

			let body = null;
			try {
				body = JSON.parse(data.body);
			} catch (e) { return handleError('An error ocurred. Unable to parse response.'); }

			console.log("got jobId:",body.jobId);
			startPolling(body.jobId);
			//
			//window.location.reload()
		}).fail( (err)=>{
			console.log("Failed purify: ", err);
		} )
		return false;
	}

	let statusInterval;
	function startPolling(jobId) {
		statusInterval = setInterval(()=>{
			getJobStatus(jobId, handleSingleStatusResponse)
		},1000);
	}

	function handleSingleStatusResponse(data) {
		console.log("got job status ", data);

		if (data.status === 'completed') {
			finishPolling();
			getGeneratedData(data.jobId);
		}
	}

	function getGeneratedData(jobId) {
		const data = { action: 'purifycss_jobstatus', jobId, single: true };
		$.ajax({
			url: purifyData.ajaxurl,
			method: "GET",
			data,
		})

	}

	let currReq = null;
	window.getJobStatus = function(jobId, cb) {
		currReq = $.ajax({
			url: purifyData.apiHost + '/status/'+jobId,
			method: "GET",
			beforeSend : ()=>{ if (currReq != null) currReq.abort()}
		})
			.done(cb)
			.fail(console.error)
	};

	function finishPolling() {
		clearInterval(statusInterval);
		$("#wp-admin-bar-purifycss > a.ab-item .spinner").remove(); // hide spinner
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
