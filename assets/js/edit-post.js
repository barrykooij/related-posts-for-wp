/**
 * Used on the post screen
 */
jQuery(function ($) {
	$.each($('.rp4wp_mb_manage'), function (k, v) {
		new RP4WP_Related_Manager(v);
	});
});

/**
 * Used on the post screen
 *
 * @param tgt
 * @constructor
 */
function RP4WP_Related_Manager(tgt) {
	this.container = tgt;

	this.init = function () {
		this.bind();
		this.make_sortable();
	};

	this.bind = function () {
		var instance = this;
		jQuery(this.container).find('.rp4wp_table_manage .trash a').bind('click', function () {
			instance.delete_child(this);
		});
	};

	this.fix_helper = function (e, ui) {
		ui.children().each(function () {
			jQuery(this).width(jQuery(this).width());
		});
		return ui;
	};

	this.make_sortable = function () {
		var instance = this;
		var sortable_table = jQuery(this.container).find('.sortable tbody');

		sortable_table.sortable({
			helper: instance.fix_helper,
			update: function (event, ui) {

				jQuery(instance.container).parent().parent().find('h3').eq(0).append(
					jQuery('<img>').attr('src', jQuery('#rp4wp-dir-img').val() + 'ajax-loader.gif').addClass('rp4wp_ajaxloader')
				);

				opts = {
					url     : ajaxurl,
					type    : 'POST',
					async   : true,
					cache   : false,
					dataType: 'json',
					data    : {
						action     : 'rp4wp_related_sort',
						rp4wp_items: sortable_table.sortable('toArray').toString(),
						nonce      : jQuery(instance.container).find('#rp4wp-ajax-nonce').val()
					},
					success : function (response) {
						jQuery('.rp4wp_ajaxloader').remove();
						return;
					},
					error   : function (xhr, textStatus, e) {
						jQuery('.rp4wp_ajaxloader').remove();
						return;
					}
				};
				jQuery.ajax(opts);
			}
		});

	};

	this.delete_child = function (tgt) {

		var confirm_delete = confirm(rp4wp_js.confirm_delete_related_post);
		if (!confirm_delete) {
			return;
		}

		var instance = this;

		var opts = {
			url     : ajaxurl,
			type    : 'POST',
			async   : true,
			cache   : false,
			dataType: 'json',
			data    : {
				action: 'rp4wp_delete_link',
				id    : jQuery(tgt).closest('tr').attr('id'),
				nonce : jQuery(instance.container).find('#rp4wp-ajax-nonce').val()
			},
			success : function (response) {
				jQuery(tgt).closest('tr').fadeTo('fast', 0).slideUp(function () {
					jQuery(this).remove();
				});
				return;
			},
			error   : function (xhr, textStatus, e) {
				return;
			}
		};
		jQuery.ajax(opts);
	};

	this.init();
}