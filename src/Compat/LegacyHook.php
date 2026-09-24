<?php
/**
 * The legacy hook proxy class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Compat;

/**
 * Stands in for a 2.x hook or filter object, so code that unhooks the plugin the 2.x way keeps working:
 *
 *     $hook = RP4WP_Manager_Filter::get_filter_object( 'RP4WP_Filter_After_Post' );
 *     remove_filter( $hook->get_tag(), [ $hook, 'run' ], $hook->get_priority() );
 *
 * The proxy itself is the registered callback; run() calls the real one. LegacyAction and LegacyFilter add run().
 */
abstract class LegacyHook {

	/**
	 * The WordPress hook.
	 *
	 * @var string
	 */
	private string $tag;

	/**
	 * The real callback.
	 *
	 * @var callable
	 */
	protected $callback;

	/**
	 * The priority.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * The number of arguments.
	 *
	 * @var int
	 */
	private int $args;

	/**
	 * Other public methods of the 2.x class, mapped to where they live now.
	 *
	 * @var array<string, callable>
	 */
	private array $methods = [];

	/**
	 * Constructor.
	 *
	 * @param string   $tag      The WordPress hook.
	 * @param callable $callback The real callback.
	 * @param int      $priority The priority.
	 * @param int      $args     The number of arguments.
	 */
	public function __construct( string $tag, callable $callback, int $priority = 10, int $args = 1 ) {
		$this->tag      = $tag;
		$this->callback = $callback;
		$this->priority = $priority;
		$this->args     = $args;
	}

	/**
	 * Map other public methods of the 2.x class, like RP4WP_Hook_Shortcode::output(), to where they live now.
	 *
	 * @param array<string, callable> $methods 2.x method name => callable.
	 *
	 * @return void
	 */
	public function set_methods( array $methods ): void {
		$this->methods = $methods;
	}

	/**
	 * Call one of the mapped 2.x methods.
	 *
	 * @param string       $name      The method.
	 * @param array<mixed> $arguments The arguments.
	 *
	 * @return mixed
	 *
	 * @throws \BadMethodCallException When the 2.x class did not have the method either.
	 */
	public function __call( string $name, array $arguments ) {
		if ( ! isset( $this->methods[ $name ] ) ) {
			throw new \BadMethodCallException( esc_html( sprintf( 'Call to undefined method %s::%s()', static::class, $name ) ) );
		}

		return call_user_func_array( $this->methods[ $name ], $arguments );
	}

	/**
	 * The WordPress hook.
	 *
	 * @return string
	 */
	public function get_tag(): string {
		return $this->tag;
	}

	/**
	 * The priority.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * The number of arguments.
	 *
	 * @return int
	 */
	public function get_args(): int {
		return $this->args;
	}
}
