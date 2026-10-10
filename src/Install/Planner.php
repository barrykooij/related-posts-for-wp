<?php
/**
 * The install planner class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Install;

use LV2\WordPress\RelatedPostsForWP\Contracts\InstallPlanner;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\CacheWordsTask;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\LinkPostsTask;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\ReindexWordsTask;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\RelinkPostsTask;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\ResetTask;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\SaveAmountTask;
use LV2\WordPress\RelatedPostsForWP\Install\Tasks\WeighWordsTask;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Words\Cache;

/**
 * The installation of the free plugin: optionally remove everything, cache the words of all posts, save the number of
 * related posts, then link them.
 */
class Planner implements InstallPlanner {

	/**
	 * The most related posts per post the installer accepts, unless filtered.
	 */
	public const MAX_AMOUNT = 50;

	/**
	 * What a request may hold.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function args(): array {
		return [
			'amount'       => [
				'description' => __( 'The number of related posts to link to each post.', 'related-posts-for-wp' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => self::max_amount(),
				'default'     => self::current_amount(),
			],
			'skip_linking' => [
				'description' => __( 'Only cache the words of the posts, and link nothing.', 'related-posts-for-wp' ),
				'type'        => 'boolean',
				'default'     => false,
			],
			'rebuild'      => [
				'description' => __( 'Remove every link and all cached words first, and install again.', 'related-posts-for-wp' ),
				'type'        => 'boolean',
				'default'     => false,
			],
		];
	}

	/**
	 * Whether the site was installed: it has cached words.
	 *
	 * @return bool
	 */
	public function is_installed(): bool {
		return ( new Cache() )->word_count() > 0;
	}

	/**
	 * The tasks of an installation.
	 *
	 * @param array<string, mixed> $request What the admin asked for.
	 *
	 * @return \LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask[]
	 */
	public function plan( array $request ): array {
		if ( ! empty( $request['update'] ) ) {
			return $this->update_plan( (int) ( $request['generation'] ?? 0 ) );
		}

		$amount = max( 1, min( self::max_amount(), (int) ( $request['amount'] ?? 3 ) ) );
		$tasks  = [];

		if ( ! empty( $request['rebuild'] ) ) {
			$tasks[] = new ResetTask();
		}

		$tasks[] = new CacheWordsTask();
		$tasks[] = new WeighWordsTask( (int) ( $request['generation'] ?? 0 ) );
		$tasks[] = new SaveAmountTask( $amount );

		if ( empty( $request['skip_linking'] ) ) {
			$tasks[] = new LinkPostsTask( $amount );
		}

		return $tasks;
	}

	/**
	 * The tasks of the update of 3.0 (see Update): read every post again, weigh the words, and link every post that
	 * was linked automatically again, by difference.
	 *
	 * @param int $generation When the update started, in milliseconds.
	 *
	 * @return \LV2\WordPress\RelatedPostsForWP\Contracts\InstallTask[]
	 */
	private function update_plan( int $generation ): array {
		return [
			new ReindexWordsTask( $generation ),
			new WeighWordsTask( $generation ),
			new RelinkPostsTask( self::current_amount(), $generation ),
		];
	}

	/**
	 * The number of related posts of the settings, as the default for the next installation.
	 *
	 * @return int
	 */
	private static function current_amount(): int {
		$amount = (int) Main::get()->settings()->get( 'automatic_linking_post_amount' );

		return max( 1, min( self::max_amount(), $amount > 0 ? $amount : 3 ) );
	}

	/**
	 * The most related posts per post the installer accepts.
	 *
	 * @return int
	 */
	public static function max_amount(): int {
		/**
		 * Filters the most related posts per post the installer accepts.
		 *
		 * @since 3.0.0
		 *
		 * @param int $max The maximum. Default 50.
		 */
		return max( 1, (int) apply_filters( 'rp4wp_install_max_amount', self::MAX_AMOUNT ) );
	}
}
