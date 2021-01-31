let purified_css;
let customhtml_text;
jQuery(document).ready(function($){
	'use strict';

	init();

	/**
	 * function of initialize settings window
	 */
	function init(){

		if ($('#purified_css').length > 0) {
			var codeEditorSettings = {
				"codemirror":{"indentUnit":4, "indentWithTabs":true, "inputStyle":"contenteditable", "lineNumbers":true, "lineWrapping":true, "styleActiveLine":true, "continueComments":true, "extraKeys":{"Ctrl-Space":"autocomplete","Ctrl-\/":"toggleComment","Cmd-\/":"toggleComment","Alt-F":"findPersistent","Ctrl-F":"findPersistent","Cmd-F":"findPersistent"}, "direction":"ltr","gutters":[],"mode":"text\/css","lint":false,"autoCloseBrackets":true,"matchBrackets":true},
				"csslint":{"errors":true,"box-model":true,"display-property-grouping":true,"duplicate-properties":true,"known-properties":true,"outline-none":true},
				"jshint":{"boss":true,"curly":true,"eqeqeq":true,"eqnull":true,"es3":true,"expr":true,"immed":true,"noarg":true,"nonbsp":true,"onevar":true,"quotmark":"single","trailing":true,"undef":true,"unused":true,"browser":true,"globals":{"_":false,"Backbone":false,"jQuery":false,"JSON":false,"wp":false}},
				"htmlhint":{"tagname-lowercase":true,"attr-lowercase":true,"attr-value-double-quotes":false,"doctype-first":false,"tag-pair":true,"spec-char-escape":true,"id-unique":true,"src-not-empty":true,"attr-no-duplication":true,"alt-require":true,"space-tab-mixed-disabled":"tab","attr-unsafe-chars":true}
			};
			purified_css = wp.codeEditor.initialize( "purified_css", codeEditorSettings);
		}
		/**
		 * bind event click on buttons
		 */
		$('#live_button').off('click').on('click', livebutton_click );
		$('#test_button').off('click').on('click', testbutton_click );
		$('#activate_button').off('click').on('click', activatebutton_click );
		$('#css_button').off('click').on('click', cssbutton_click );
		$('#startJob').off('click').on('click', startJob_click );
		$('#save_button').off('click').on('click', savebutton_click );
		

		$('.expand-click').off('click').on('click', toogletext_click );
		$('.expand-click2').off('click').on('click', toogletext_click2 );
		$('.expand-click3').off('click').on('click', toogletext_click3);


	}


	/**
	 * SaveCSS button click to send request to get CSS
	 * @param {event} ev 
	 */
	function savebutton_click(ev){
		let customhtml='';
		if ( typeof(customhtml_text)!=='undefined' && typeof(customhtml_text.codemirror)!=='undefined' ){
			customhtml = customhtml_text.codemirror.doc.getValue();
		}else{
			customhtml = $('#customhtml_text').val();
		}
		let excludeUrls = $('#purifycss_exclude_urls_text').val();
		let skipCssFiles = $('#purifycss_skip_css_files_text').val();

		sendAjax( {
			action:'purifycss_savecss',
			customhtml:customhtml,
			excludeUrls:excludeUrls,
			skipCssFiles:skipCssFiles,
			editedcss:purified_css.codemirror.doc.getValue()
		}, (data)=>{

			window.scroll({
				top: 0, 
				behavior: 'smooth'
			  });
		} );
	}

	function isCodeMirror() {
		return typeof(customhtml_text)!=='undefined' && typeof(customhtml_text.codemirror)!=='undefined';
	}



	function startJob_click(ev){
		let customhtml = isCodeMirror() ? customhtml_text.codemirror.doc.getValue() : $('#customhtml_text').val();
		let excludeUrls = $('#purifycss_exclude_urls_text').val();

		const data = { action:'purifycss_startjob', customhtml, excludeUrls };
		$.ajax({url: ajaxurl, method: "POST", data})
			.done(handleStartjobResponse)
			.fail(console.error)
			.always(reEnableButtons);
	}



	function reEnableButtons() {
			$('.purifycss-body .button').removeClass('disabled');
	}

	function handleStartjobResponse(data) {
		if (!data) return handleError('failed. no data received.');
		if (!data.response) return handleError(data);
		if (data.response.code >= 400) return handleError(data);

		console.log('purifycss_startjob callback:', data);

		let body = null;
		try {
			body = JSON.parse(data.body);
		} catch (e) { return handleError('An error ocurred. Unable to parse response.'); }

		console.log("got jobId:",body.jobId);
		startPollingJobStatus(body.jobId);
	}

	let pollingInterval;
	function startPollingJobStatus(jobId) {
		$("button#abort").show().on('click', finishPolling);

		pollingInterval = setInterval(() => {
			getJobStatus(jobId);
		}, 1000);
	}

	function finishPolling(ev){
		clearInterval(pollingInterval);
		$("button#abort").hide();
	}

	let currReq = null;
	window.getJobStatus = function(jobId) {
		const data = { action: 'purifycss_jobstatus', jobId };
		currReq = $.ajax({
			url: 'https://api.purifycss.online/status/'+jobId, // ajaxurl,
			method: "GET",
			//data,
			//beforeSend : ()=>{ if (currReq != null) currReq.abort()}
		})
		.done(handleJobStatusResponse)
		.fail(console.error)
	}


	function handleJobStatusResponse(data) {
		if (!data) return handleError('failed. no data received.');
		$('.crawl-summary').html(generateStatusHtml(data));
		$(document).on('click','[data-toggle-details]', (ev)=>{
			const $sub = $("[data-details-for-url='"+$(ev.target).text()+"']");
			if ($sub.visible) $sub.hide()
			else $sub.show();
		});
		if (data.status == 'completed') finishPolling();
	}

	function generateStatusHtml(data) {
		console.log("generateStatusHtml", data);

		let html = `<p>Status: ${data.status}</p>`;
		if (!data.urls) return html;

		html += `
			<table class="jobStatus">
				<tr>
					<td class="text-right">#</td>
					<td>URL</td>
					<td class="text-center">Fetch<br />HTML</td>
					<td class="text-center">Fetch<br />CSS</td>
					<td class="text-center">Remove<br/>unused CSS</td>
					<td class="text-center">Generate<br/>critical CSS</td>
				</tr>
		`;

		data.urls.forEach(u => {
			html += `
				<tr>
					<td class="text-right">${u.crawl.index}</td>
					<td class="url"><div class="ellipsis" data-toggle-details>${u.url}</div></td>
					<td class="text-center">${displayStatusOf(u.crawl)}</td>
					<td class="text-center">${displayStatusOf(u.fullCss)}</td>
					<td class="text-center">${displayStatusOf(u.purifyCss)}</td>
					<td class="text-center">${displayStatusOf(u.criticalCss)}</td>
				</tr>
			`;
			/*if (u.styles && u.styles.cssLinks) {
				html += `
				<tr style="display:none" data-details-for-url="${u.url}">
					<td></td>
					<td colspan="5">${generateUrlDetails(u)}</td>
				</tr>
				`
			}*/
		});
		html += `
			</table>
		`;

		return html;
	}

/*	function generateUrlDetails(urlData) {
		return '<table class="details">' +
			urlData.styles.cssLinks.map(link => `
				<tr>
					<td class="url"><div class="ellipsis">${link.href}</div></td>
					<td>${link.status}</td>
				</tr>
			`).join('') +
			'</table>';
	}*/

	function displayStatusOf(subprocess) {
		if (!subprocess) return '';
		if (!subprocess.status) return '';
		switch (subprocess.status) {
			case 'processing': return '<span class="dashicons dashicons-admin-generic rotate" style="color: darkcyan"></span>';
			case 'enqueued': return '<span class="dashicons dashicons-clock" style="color: darkgrey"></span>';
			case 'failed': return '<span class="dashicons dashicons-no" style="color: indianred"></span>';
			case 'completed': return '<span class="dashicons dashicons-yes-alt" style="color: forestgreen"></span>';
		}
		return '';
	}

	function handleError(...data) {
		console.error(...data);
	}


	/**
	 * GetCSS button click to send request to get CSS
	 * @param {event} ev 
	 */
	function cssbutton_click(ev){
		let customhtml = isCodeMirror() ? customhtml_text.codemirror.doc.getValue() : $('#customhtml_text').val();
		let excludeUrls = $('#purifycss_exclude_urls_text').val();

		sendAjax( { action:'purifycss_getcss', customhtml, excludeUrls }, (data)=>{
			console.log(data);
			$('.result-block').html("Result: "+data.resmsg).show();
			purified_css.codemirror.doc.setValue(data.styles);

			$('.editor-container').hide();

			var html = "";
			$.each(data.files, function(i,mapping) {
			 html = html + '<div class="purified-result-item">'+
				'<a href="'+mapping.orig_css+'">'+mapping.orig_css+'</a><br>' +
				'&middot; clean css file: <a target="_blank" href="'+mapping.css+'">'+mapping.css_filename+'</a><br>'+
				'&middot; <span style="display:inline-block;padding-right: 15px;">before: <strong>'+mapping.before+'</strong></span>'+
				'<span style="display:inline-block;padding-right: 15px;">after: <strong>'+mapping.after+'</strong> </span>'+
				'<span style="display:inline-block;padding-right: 15px;">used: <strong>'+mapping.used+'</strong> </span>'+
				'<span style="display:inline-block;padding-right: 15px;">unused: <strong>'+mapping.unused+'</strong> </span>'+
				'<br>'+
				'</div>';
			});
			$('.file-mapping-container').html(html);


			var crawlSummary = '<span>Crawled '+data.resp.html.length+' URLs.</span><br>';
			$.each(data.resp.html, function(i,html) {
				crawlSummary += '<span> &middot; '+html.url+'</span><br>';
			});

			$('.crawl-summary').html(crawlSummary);

			// enable/disable live mode if code generated succesfully
			if ( data.livemode=='1' ){
				$('#live_button').addClass('active');
			}else{
				$('#live_button').removeClass('active');				
			}
		} );
	}

	/**
	 * Expand/ scrollup block
	 * @param {event} ev
	 */
	function toogletext_click(ev){
		$('.expand-click').toggleClass('active');
		if ( $('.expand-click').hasClass('active') ){
			$('.expand-block').removeClass('d-none');
			$('.expand-click .dashicons').removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');

			if ( !$('#customhtml_text').hasClass('initialized') ){
				// mark textarea as already initialized
				$('#customhtml_text').addClass('initialized');
				
				// initialize code editor
				if ( typeof(customhtml_text)==='undefined' ){
					customhtml_text = wp.codeEditor.initialize( "customhtml_text", customhtml_text_param ); 
				}
			}

		}else{
			$('.expand-block').addClass('d-none');
			$('.expand-click .dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
		}
	}

	function toogletext_click2(ev){
		$('.expand-click2').toggleClass('active');
		if ( $('.expand-click2').hasClass('active') ){
			$('.expand-block2').removeClass('d-none');
			$('.expand-click2 .dashicons').removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
		}else{
			$('.expand-block2').addClass('d-none');
			$('.expand-click2 .dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
		}
	}

	function toogletext_click3(ev){
		$('.expand-click3').toggleClass('active');
		if ( $('.expand-click3').hasClass('active') ){
			$('.expand-block3').removeClass('d-none');
			$('.expand-click3 .dashicons').removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
		}else{
			$('.expand-block3').addClass('d-none');
			$('.expand-click3 .dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
		}
	}

	/**
	 * Activate button click to enable live mode
	 * @param {event} ev 
	 */
	function activatebutton_click(ev){
		let keyval='';
		// get api value
		keyval = $('#api-key').val();

		sendAjax( { action:'purifycss_activate', key:keyval }, (data)=>{
			if ( data.status=="OK" ){
				$('.activated-text').removeClass('d-none');
			}
			console.log(data);
		} );
	}

	/**
	 * Live button click to enable live mode
	 * @param {event} ev 
	 */
	function livebutton_click(ev){
		sendAjax( { action:'purifycss_livemode' }, (data)=>{
			// get livemode status
			if ( typeof(data.livemode)!=='undefined'  ){
				if ( data.livemode==1 ){
					$('#live_button').addClass('active');
				}else{
					$('#live_button').removeClass('active');
				}
			}
		} );
	}

	/**
	 * Test button click to enable test mode
	 * @param {event} ev 
	 */
	function testbutton_click(ev){
		sendAjax( { action:'purifycss_testmode' }, (data)=>{
			// get testmode status
			if ( typeof(data.testmode)!=='undefined'  ){
				if ( data.testmode==1 ){
					$('#test_button').addClass('active');
				}else{
					$('#test_button').removeClass('active');
				}
			}
		} );
	}

	/**
	 * Send ajax request via jQuery
	 * @param {*} url 
	 * @param {*} param 
	 * @param {*} callback 
	 * @param {*} errorMsg 
	 */
	function sendAjax(param, callback=function(){}, errorMsg){
		$('.purifycss-body .button').addClass('disabled');

		$.ajax({
			url: ajaxurl,
			method: "POST",
			data: param,
		}).done( (data)=>{
			console.log("done", data);
			if ( data.status == 'OK' ){
				callback(data);
				// show notice
				if ( typeof(data.msg)!=='undefined' ){
					$('.notice').remove();
					$('#wpbody-content').prepend('<div id="notice" class="notice notice-success is-dismissible"><p>'+data.msg+'</p></div>');
				}
			}else{
				// show error
				if ( typeof(data.msg)!=='undefined' ){
					$('.notice').remove();
					$('#wpbody-content').prepend('<div id="notice" class="notice notice-error is-dismissible"><p>'+data.msg+'</p></div>');
				}
			}
		} )
		.fail( ()=>{
			console.log(errorMsg);
			$('.notice').remove();
			$('#wpbody-content').prepend('<div id="notice" class="notice notice-error is-dismissible"><p>'+errorMsg+'</p></div>');
		} )
		.always( ()=>{
			// enable all buttons when ajax request ending
			$('.purifycss-body .button').removeClass('disabled');
		} );
	}

});
