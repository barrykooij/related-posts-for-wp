<?php
/**
 * The post words class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Words;

/**
 * The words the extractor kept for a post, with what the word cache stores about them.
 */
final class PostWords {

	/**
	 * The words, most important first: word => weight (the share of all words of the post).
	 *
	 * @var array<string, float>
	 */
	public array $weights;

	/**
	 * How many times each word counts: word => count, with the weights of the sources (a title word counts 80 times).
	 *
	 * @var array<string, int>
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
	 * @param array<string, float> $weights  The words and their weight.
	 * @param array<string, int>   $counts   The words and how many times they count.
	 * @param string               $language The language.
	 * @param int                  $tokens   How many words the sources have.
	 */
	public function __construct( array $weights, array $counts, string $language, int $tokens ) {
		$this->weights  = $weights;
		$this->counts   = $counts;
		$this->language = $language;
		$this->tokens   = $tokens;
	}
}
