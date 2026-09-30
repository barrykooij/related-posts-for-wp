<?php
/**
 * The save amount task class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install\Tasks;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Settings\Settings;

/**
 * Save the number of related posts the admin chose as the setting for automatic linking, so posts published later get
 * as many.
 *
 * Keys that were never saved get their defaults, because the sanitize callback of the settings treats a missing
 * checkbox as off. Filtered values are not saved; the 2.x wizard saved them, which made them stick.
 */
class SaveAmountTask implements InstallTask {

	/**
	 * The number of related posts per post.
	 *
	 * @var int
	 */
	private int $amount;

	/**
	 * Set up the task.
	 *
	 * @param int $amount The number of related posts per post.
	 */
	public function __construct( int $amount ) {
		$this->amount = $amount;
	}

	/**
	 * The task ID.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'save_amount';
	}

	/**
	 * The task name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Saving the settings', 'related-posts-for-wp' );
	}

	/**
	 * Runs in one batch, so there is nothing to count.
	 *
	 * @return int
	 */
	public function remaining(): int {
		return 0;
	}

	/**
	 * Save the amount.
	 *
	 * @return bool Always true.
	 */
	public function run_batch(): bool {
		$stored  = get_option( Settings::OPTION, [] );
		$options = array_merge( Main::get()->settings()->defaults(), is_array( $stored ) ? $stored : [] );

		$options['automatic_linking_post_amount'] = $this->amount;

		update_option( Settings::OPTION, $options );

		return true;
	}
}
