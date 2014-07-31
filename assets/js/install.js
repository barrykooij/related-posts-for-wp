jQuery(document).ready(function ($) {

	var step = $('.src-step').attr('rel');

	if (1 == step) {
		srp_install_cache();
	}

	function srp_install_cache() {

		this.total_posts = 0;
		this.ppr = 200;
		this.req_nr = 0;

		this.do_request = function () {
			var instance = this;
			$.post(ajaxurl, {'action': 'srp_install_save_words' }, function (response) {

				// What next?
				if ('more' == response) {
					// Increase the request nr
					instance.req_nr++;

					// Do Progressbar
					instance.do_progressbar();

					// Do request
					instance.do_request();

				} else {
					// Done
					instance.done();
				}

			});
		};

		this.done = function () {

			// Update progressbar
			$('#progressbar').progressbar({value: 100});

			// etc.
			window.location = $('#sre_admin_url').val() + '?page=srp_install&step=2';
		};

		this.do_progressbar = function() {
			$('#progressbar').progressbar({value: ((this.req_nr*this.ppr)/this.total_posts)*100});
		};

		this.init = function () {
			$('#progressbar').progressbar({value: false});
			this.total_posts = $('#sre_total_posts').val();
			this.do_request();
		};

		this.init();

	}

});