jQuery(document).ready(function ($) {

	var step = $('.step').attr('rel');

	if (1 == step) {
		srp_install_cache();
	}

	function srp_install_cache() {

		this.total_posts = 0;
		this.ppr = 200;
		this.req_nr = 0;

		this.do_request = function () {
			var instance = this;
			$.post(ajaxurl, {'action': 'srp_install_save_words', 'req_nr': 0}, function (response) {

				// Increase the request nr
				instance.req_nr++;

				// Do Progressbar
				instance.do_progressbar();

				// What next?
				if ('next' == response) {
					instance.do_request();
				} else {
					instance.done();
				}

			});
		};

		this.done = function () {
			console.info("YOU'RE DONE!! :)");
		};

		this.do_progressbar = function() {
			$('#progressbar').progressbar({value: ((this.req_nr*this.ppr)/this.total_posts)*100});
		};

		this.init = function () {
			$('#progressbar').progressbar({value: false});
			this.total_posts = $('#sre_total_posts').val();
			console.info(this.total_posts);
			this.do_request();
		};

		this.init();

	}

});