/**
 * Used on the post screen
 */
jQuery(function($) {
	$.each($('.srp_mb_manage'), function(k,v) {
		new SRP_Related_Manager(v);
	});
});

/**
 * Used on the post screen
 *
 * @param tgt
 * @constructor
 */
function SRP_Related_Manager(tgt) {
	this.container 	= tgt;

	this.init = function() {
		this.bind();
		this.make_sortable();
	};

	this.bind = function() {
		var instance = this;
		jQuery(this.container).find('.pt_table_manage .trash a').bind('click', function(){instance.delete_child(this);});
	};

	this.fix_helper = function(e, ui) {
		ui.children().each(function() {
			jQuery(this).width(jQuery(this).width());
		});
		return ui;
	};

	this.make_sortable = function() {
		var instance = this;
		var sortable_table = jQuery(this.container).find('.sortable tbody');

		sortable_table.sortable({
			helper: instance.fix_helper,
			update: function(event, ui) {

				// Fire sp_sorting_childs hook
				jQuery('body').trigger('sp_sorting_childs', [ sortable_table.sortable('toArray').toString() ]);

				jQuery(instance.container).parent().parent().find('h3').eq(0).append(
						jQuery('<img>').attr('src', jQuery('#srp-dir-img').val()+'ajax-loader.gif').addClass('sp_ajaxloader')
				);

				opts = {
					url: ajaxurl,
					type: 'POST',
					async: true,
					cache: false,
					dataType: 'json',
					data:{
						action: 'srp_related_sort',
						srp_items: sortable_table.sortable('toArray').toString(),
						nonce: jQuery(instance.container).find('#srp-ajax-nonce').val()
					},
					success: function(response) {
						jQuery('.srp_ajaxloader').remove();
						return;
					},
					error: function(xhr,textStatus,e) {
						jQuery('.srp_ajaxloader').remove();
						return;
					}
				};
				jQuery.ajax(opts);
			}
		});

	};

	this.delete_child = function(tgt) {

		// Fire sp_delete_child hook
		jQuery('body').trigger('sp_delete_child', [ jQuery(tgt).closest('tr').attr('id') ]);

		var confirm_delete = confirm( sp_js.confirm_delete_child );
		if(!confirm_delete) {
			return;
		}

		var instance = this;

		var opts = {
			url: ajaxurl,
			type: 'POST',
			async: true,
			cache: false,
			dataType: 'json',
			data:{
				action: 'srp_delete_link',
				id: jQuery(tgt).closest('tr').attr('id'),
				nonce: jQuery(instance.container).find('#srp-ajax-nonce').val()
			},
			success: function(response) {

				// Fire sp_child_deleted hook
				jQuery('body').trigger('sp_child_deleted', [ jQuery(tgt).closest('tr').attr('id') ]);

				jQuery(tgt).closest('tr').fadeTo('fast', 0).slideUp(function() {
					jQuery(this).remove();
				});
				return;
			},
			error: function(xhr,textStatus,e) {
				return;
			}
		};
		jQuery.ajax(opts);
	};

	this.init();
}