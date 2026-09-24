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
