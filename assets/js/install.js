jQuery(document).ready(function ($) {

	// Determine steps
	var step = $('.rp4wp-step').attr('rel');

	// Checks steps
	if (1 == step) {

		// Install the cache
		rp4wp_install_wizard(1);

	} else if (2 == step) {

		// Link the posts
		$('#rp4wp-link-now').click(function () {
			rp4wp_install_wizard(2);
		});

	}

	function rp4wp_install_wizard(step) {

		this.step = step;
		this.total_posts = 0;
		this.ppr = null;
		this.action = null;
		this.percentage_object = null;

		this.do_request = function () {
			var instance = this;
			$.post(ajaxurl, {
				'action'    : this.action,
				'ppr'       : this.ppr,
				'rel_amount': $('#rp4wp_related_posts_amount').val()
			}, function (response) {

				// The RegExp
				var response_regex = new RegExp("^[0-9]+$");

				// Trim that string o/
				response = response.trim();

				// Test it
				if (response_regex.test(response)) {

					var posts_left = parseInt(response);

					// Do Progressbar
					instance.do_progressbar(posts_left);

					if (posts_left > 0) {
						// Do request
						instance.do_request();
					} else {
						// Done
						instance.done();
					}

				} else {
					alert("Woops! Something went wrong while linking.\n\nResponse:\n\n" + response);
				}

			});
		};

		this.done = function () {

			// Update progressbar
			$('#progressbar').progressbar({value: 100});

			// Redirect to next step
			window.location = $('#rp4wp_admin_url').val() + '?page=rp4wp_install&step=' + ( this.step + 1 );
		};

		this.do_progressbar = function (posts_left) {
			var progress = Math.round(( ( this.total_posts - posts_left ) / this.total_posts ) * 100);
			if (progress > 0) {
				this.percentage_object.html(progress + '%');
				$('#progressbar').progressbar({value: progress});
			}
		};

		this.init = function () {

			// Setup the progressbar
			$('#progressbar').progressbar({value: false});

			// Create the span
			this.percentage_object = jQuery('<span>');
			$('#progressbar').find('div:first').append(this.percentage_object);

			// Set the current progress
			this.do_progressbar($('#rp4wp_uncached_posts').val());

			// Get the total posts
			this.total_posts = $('#rp4wp_total_posts').val();

			// Set the correct action
			switch (this.step) {
				case 1:
					this.ppr = 25;
					this.action = 'rp4wp_install_save_words';
					break;
				case 2:
					this.ppr = 5;
					this.action = 'rp4wp_install_link_posts';
					break;
			}

			// Do the first request
			this.do_request();
		};

		this.init();

	}

});