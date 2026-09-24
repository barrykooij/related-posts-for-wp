<?php
/**
 * The sentinel reached exception class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Security;

/**
 * Thrown by a sentinel link to stop a legacy AJAX handler before it calls exit().
 */
final class SentinelReachedException extends \RuntimeException {
}
