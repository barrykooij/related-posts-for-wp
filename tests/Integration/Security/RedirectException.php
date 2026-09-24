<?php
/**
 * The redirect exception class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security;

/**
 * Thrown from the wp_redirect filter so tests can observe a redirect that the legacy code follows with exit().
 */
final class RedirectException extends \RuntimeException {
}
