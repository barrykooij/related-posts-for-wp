<?php
/**
 * A stand-in for WP_Post in the unit suite, which runs without WordPress.
 *
 * @package RelatedPostsForWP
 */

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * The fields of a post that the unit tests need.
	 */
	// phpcs:ignore Generic.Classes.DuplicateClassName.Found -- A test stand-in for the WordPress class.
	final class WP_Post {

		/**
		 * The post ID.
		 *
		 * @var int
		 */
		public $ID = 0;

		/**
		 * Set up.
		 *
		 * @param int $id The post ID.
		 */
		public function __construct( int $id = 0 ) {
			$this->ID = $id;
		}
	}
}
