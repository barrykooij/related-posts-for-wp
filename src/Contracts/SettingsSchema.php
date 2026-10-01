<?php
/**
 * The settings schema contract file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Contracts;

/**
 * Describes the settings pages for the admin app and the REST API, and saves them. The premium add-on replaces it with
 * its own pages through `Main::set_settings_schema()`.
 *
 * A page is an array with `id`, `title`, `description`, `option` (the option it is saved in) and `sections`, each
 * with `id`, `title`, `description` and `fields`. Pages with the same `group` share a tab in the app, titled
 * `group_title`, with a switcher between them that shows each page's `group_label`.
 *
 * A field has `id`, `type` (the control in the admin app), `label`, `description` (HTML, already passed through
 * wp_kses_post()), `default`, `filtered` (a filter decides its value, so it can't be changed here) and, per type,
 * `options`, `min`, `max` or `href`.
 */
interface SettingsSchema {

	/**
	 * The settings pages, in order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function pages(): array;

	/**
	 * The current values of the fields of a page, as the admin app edits them.
	 *
	 * @param string $page The page ID.
	 *
	 * @return array<string, mixed>|\WP_Error An error when the page does not exist.
	 */
	public function values( string $page );

	/**
	 * Save the values of fields of a page. Fields that are not given keep their value.
	 *
	 * @param string               $page   The page ID.
	 * @param array<string, mixed> $values The values, by field ID.
	 *
	 * @return array<string, mixed>|\WP_Error The values of the page after saving, or an error.
	 */
	public function save( string $page, array $values );
}
