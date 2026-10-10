<?php
/**
 * The output normalizer class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

/**
 * Removes values from output that change between runs or WordPress versions, so golden files stay stable.
 *
 * Post IDs become slugs, upload paths lose their date folders and duplicate-name suffixes, and image tags keep only
 * the attributes the plugin controls. Newer WordPress versions add attributes such as sizes="auto" and decoding.
 */
final class Normalizer {

	/**
	 * Post IDs mapped to readable names.
	 *
	 * @var array<int, string>
	 */
	private array $names = [];

	/**
	 * Register readable names for post IDs.
	 *
	 * @param array<string, int> $ids Post IDs by name.
	 *
	 * @return self
	 */
	public function with_ids( array $ids ): self {
		foreach ( $ids as $name => $id ) {
			$this->names[ (int) $id ] = (string) $name;
		}

		return $this;
	}

	/**
	 * The readable name of a post ID.
	 *
	 * @param int $id The post ID.
	 *
	 * @return string
	 */
	public function name( int $id ): string {
		return $this->names[ $id ] ?? 'unknown-' . $id;
	}

	/**
	 * A word of the word cache with the IDs in its token replaced by names: `post:{ID}` by the name of the post,
	 * `cat:{ID}`, `tag:{ID}` and `tax:{taxonomy}:{ID}` by the slug of the term. Other words stay.
	 *
	 * @param string $word The word.
	 *
	 * @return string
	 */
	public function token( string $word ): string {
		if ( 1 === preg_match( '/^post:(\d+)$/', $word, $match ) ) {
			return 'post:' . $this->name( (int) $match[1] );
		}

		if ( 1 === preg_match( '/^((?:cat|tag|tax:[^:]+):)(\d+)$/', $word, $match ) ) {
			$term = get_term( (int) $match[2] );

			return $match[1] . ( $term instanceof \WP_Term ? $term->slug : 'unknown' );
		}

		return $word;
	}

	/**
	 * Words of the word cache by word, with the IDs in the tokens replaced by names (see token()), sorted.
	 *
	 * @param array<int|string, mixed> $words The words and what is known about each.
	 *
	 * @return array<string, mixed>
	 */
	public function tokens( array $words ): array {
		$named = [];
		foreach ( $words as $word => $value ) {
			$named[ $this->token( (string) $word ) ] = $value;
		}

		ksort( $named, SORT_STRING );

		return $named;
	}

	/**
	 * Normalize a piece of HTML.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	public function html( string $html ): string {
		$html = (string) preg_replace_callback( '/<img\b[^>]*>/', [ $this, 'image' ], $html );
		$html = $this->uploads( $html );

		return (string) preg_replace_callback(
			'/([?&](?:p|page_id|attachment_id)=|\bpost-|wp-image-)(\d+)\b/',
			function ( array $match ) {
				return $match[1] . '{' . $this->name( (int) $match[2] ) . '}';
			},
			$html
		);
	}

	/**
	 * Keep only the image attributes the plugin controls, in a fixed order.
	 *
	 * @param array<int, string> $match The regex match.
	 *
	 * @return string
	 */
	private function image( array $match ): string {
		preg_match_all( '/([a-z-]+)="([^"]*)"/', $match[0], $attributes, PREG_SET_ORDER );

		$kept = [];
		foreach ( $attributes as [ , $name, $value ] ) {
			if ( in_array( $name, [ 'src', 'class', 'alt', 'width', 'height' ], true ) ) {
				$kept[ $name ] = $value;
			}
		}

		ksort( $kept );

		$html = '<img';
		foreach ( $kept as $name => $value ) {
			$html .= ' ' . $name . '="' . $value . '"';
		}

		return $html . '>';
	}

	/**
	 * Remove date folders and duplicate-name suffixes from upload URLs.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	private function uploads( string $html ): string {
		$html = (string) preg_replace( '#/wp-content/uploads/\d{4}/\d{2}/#', '/wp-content/uploads/YYYY/MM/', $html );

		return (string) preg_replace( '#/wp-content/uploads/YYYY/MM/([a-z0-9_]+?)(?:-\d+)?(-\d+x\d+)?\.(jpe?g|png|gif|webp)#', '/wp-content/uploads/YYYY/MM/$1$2.$3', $html );
	}
}
