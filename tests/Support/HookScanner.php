<?php
/**
 * The hook scanner class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Support;

/**
 * Lists the actions and filters that PHP source files fire, by reading their tokens.
 *
 * Literal hook names are returned as is. Dynamic parts are kept as placeholders, so
 * apply_filters( 'rp4wp_' . $option, ... ) becomes "rp4wp_{$option}".
 */
final class HookScanner {

	/**
	 * Scan directories and files.
	 *
	 * @param string[] $paths Directories or files.
	 *
	 * @return array<string, string> Hook name => "action" or "filter", sorted by name.
	 */
	public static function scan( array $paths ): array {
		$hooks = [];

		foreach ( self::files( $paths ) as $file ) {
			$hooks = array_merge( $hooks, self::scan_source( (string) file_get_contents( $file ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source file.
		}

		ksort( $hooks );

		return $hooks;
	}

	/**
	 * Scan one piece of PHP source.
	 *
	 * @param string $source The source.
	 *
	 * @return array<string, string>
	 */
	private static function scan_source( string $source ): array {
		$tokens = token_get_all( $source );
		$hooks  = [];
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || T_STRING !== $token[0] || ! in_array( $token[1], [ 'do_action', 'apply_filters' ], true ) ) {
				continue;
			}

			// Skip method calls and definitions such as $x->apply_filters() or function apply_filters().
			$previous = self::previous_significant( $tokens, $i );
			if ( is_array( $previous ) && in_array( $previous[0], [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ], true ) ) {
				continue;
			}

			$name = self::first_argument( $tokens, $i + 1 );
			if ( null !== $name && '' !== $name ) {
				$hooks[ $name ] = 'do_action' === $token[1] ? 'action' : 'filter';
			}
		}

		return $hooks;
	}

	/**
	 * Read the first argument of a call as a hook name.
	 *
	 * @param array<int, mixed> $tokens The tokens.
	 * @param int               $start  The index just after the function name.
	 *
	 * @return string|null
	 */
	private static function first_argument( array $tokens, int $start ): ?string {
		$count = count( $tokens );
		$i     = $start;

		while ( $i < $count && ( ! is_string( $tokens[ $i ] ) || '(' !== $tokens[ $i ] ) ) {
			if ( is_array( $tokens[ $i ] ) && T_WHITESPACE !== $tokens[ $i ][0] ) {
				return null;
			}
			++$i;
		}

		$name  = '';
		$depth = 0;
		for ( ++$i; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( is_string( $token ) ) {
				if ( '(' === $token || '[' === $token ) {
					++$depth;
				} elseif ( ')' === $token || ']' === $token ) {
					if ( 0 === $depth ) {
						break;
					}
					--$depth;
				} elseif ( ',' === $token && 0 === $depth ) {
					break;
				}
				continue;
			}

			if ( T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$name .= substr( $token[1], 1, -1 );
			} elseif ( T_VARIABLE === $token[0] && 0 === $depth ) {
				$name .= '{' . $token[1] . '}';
			}
		}

		return $name;
	}

	/**
	 * The previous token that is not whitespace or a comment.
	 *
	 * @param array<int, mixed> $tokens The tokens.
	 * @param int               $index  The current index.
	 *
	 * @return mixed
	 */
	private static function previous_significant( array $tokens, int $index ) {
		for ( $i = $index - 1; $i >= 0; $i-- ) {
			if ( ! is_array( $tokens[ $i ] ) || ! in_array( $tokens[ $i ][0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
				return $tokens[ $i ];
			}
		}

		return null;
	}

	/**
	 * All PHP files under the given paths.
	 *
	 * @param string[] $paths Directories or files.
	 *
	 * @return string[]
	 */
	private static function files( array $paths ): array {
		$files = [];

		foreach ( $paths as $path ) {
			if ( is_file( $path ) ) {
				$files[] = $path;
				continue;
			}

			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( 'php' === $file->getExtension() ) {
					$files[] = $file->getPathname();
				}
			}
		}

		sort( $files );

		return $files;
	}
}
