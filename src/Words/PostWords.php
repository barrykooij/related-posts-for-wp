<?php
/**
 * The post words class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

/**
 * The words of a post as the extractor found them, with what the word cache stores about the post.
 */
final class PostWords {

	/**
	 * Every word of the post that is not an ignored word, with how many times it counts (with the weights of the
	 * sources: a title word counts 80 times), most first. The word cache picks the words it stores from these.
	 *
	 * @var array<int|string, int>
	 */
	public array $counts;

	/**
	 * The language of the post, which picked its ignored words.
	 *
	 * @var string
	 */
	public string $language;

	/**
	 * How many words the sources of the post have, ignored words included, each counted once.
	 *
	 * @var int
	 */
	public int $tokens;

	/**
	 * Constructor.
	 *
	 * @param array<int|string, int> $counts   The words and how many times they count.
	 * @param string                 $language The language.
	 * @param int                    $tokens   How many words the sources have.
	 */
	public function __construct( array $counts, string $language, int $tokens ) {
		$this->counts   = $counts;
		$this->language = $language;
		$this->tokens   = $tokens;
	}
}
