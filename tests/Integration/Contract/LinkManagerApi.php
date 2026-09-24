<?php
/**
 * The link manager API scenario class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Contract;

use LV2\WordPress\RelatedPostsForWP\Tests\Support\Normalizer;

/**
 * The 2.x link manager, called directly the way themes and other plugins use it. The golden master records the
 * results; this is the only place where it uses the 2.x API.
 */
final class LinkManagerApi {

	/**
	 * Call the link manager for one parent post.
	 *
	 * @param int        $parent_id  The parent post.
	 * @param Normalizer $normalizer Turns post IDs and HTML into stable names.
	 *
	 * @return array<string, mixed>
	 */
	public static function snapshot( int $parent_id, Normalizer $normalizer ): array {
		$manager = new \RP4WP_Post_Link_Manager();

		// 2.x keeps links to hard-deleted posts, and get_children() without arguments returns null for those.
		$names  = static function ( array $posts ) use ( $normalizer ): array {
			return array_values(
				array_map(
					static function ( $post ) use ( $normalizer ) {
						return null === $post ? 'null' : $normalizer->name( (int) $post->ID );
					},
					$posts
				)
			);
		};
		$sorted = static function ( array $names ): array {
			sort( $names );

			return $names;
		};

		return [
			'get_children'                 => $names( $manager->get_children( $parent_id ) ),
			'get_children limit 2'         => $names( $manager->get_children( $parent_id, [ 'posts_per_page' => 2 ] ) ),
			'get_children order DESC'      => $names( $manager->get_children( $parent_id, [ 'order' => 'DESC' ] ) ),
			'get_children orderby title'   => $names(
				$manager->get_children(
					$parent_id,
					[
						'orderby' => 'title',
						'order'   => 'ASC',
					]
				)
			),
			'get_children post_status any' => $names( $manager->get_children( $parent_id, [ 'post_status' => 'any' ] ) ),
			'get_parents'                  => $sorted( $names( $manager->get_parents( $parent_id ) ) ),
			'generate_children_list limit' => $normalizer->html( $manager->generate_children_list( $parent_id, 1 ) ),
		];
	}
}
