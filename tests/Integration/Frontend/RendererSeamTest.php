<?php
/**
 * The renderer seam test class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Tests\Integration\Frontend;

use LV2\WordPress\RelatedPostsForWP\Contracts\Renderer;
use LV2\WordPress\RelatedPostsForWP\Links\LinkRepository;
use LV2\WordPress\RelatedPostsForWP\Main;
use LV2\WordPress\RelatedPostsForWP\Tests\Integration\TestCase;

/**
 * Every place that shows related posts asks the renderer of Main, which the premium add-on replaces with its templates.
 *
 * @covers \LV2\WordPress\RelatedPostsForWP\Main::renderer
 * @covers \LV2\WordPress\RelatedPostsForWP\Main::set_renderer
 * @covers \LV2\WordPress\RelatedPostsForWP\Frontend\Renderer::render
 * @covers ::rp4wp_children
 */
final class RendererSeamTest extends TestCase {

	/**
	 * The renderer before the test.
	 *
	 * @var Renderer
	 */
	private Renderer $original;

	/**
	 * The renderer that replaces it.
	 *
	 * @var RecordingRenderer
	 */
	private RecordingRenderer $recorder;

	/**
	 * A post.
	 *
	 * @var int
	 */
	private int $post_id;

	public function set_up(): void {
		parent::set_up();

		$this->original = Main::get()->renderer();
		$this->recorder = new RecordingRenderer();
		Main::get()->set_renderer( $this->recorder );

		$this->post_id = self::factory()->post->create();
	}

	public function tear_down(): void {
		Main::get()->set_renderer( $this->original );

		parent::tear_down();
	}

	public function test_the_template_tag_passes_all_its_arguments(): void {
		$html = rp4wp_children( $this->post_id, false, 'my-template.php', 2, 'Read next', 1 );

		$this->assertSame( 'rendered', $html );
		$this->assertSame(
			[
				[
					$this->post_id,
					[
						'template'     => 'my-template.php',
						'limit'        => 2,
						'heading_text' => 'Read next',
						'offset'       => 1,
					],
				],
			],
			$this->recorder->calls
		);
	}

	public function test_the_template_tag_prints_the_related_posts_of_the_current_post_by_default(): void {
		$this->go_to( get_permalink( $this->post_id ) );
		the_post();

		ob_start();
		$returned = rp4wp_children();

		$this->assertSame( 'rendered', ob_get_clean() );
		$this->assertSame( '', $returned );
		$this->assertSame( $this->post_id, $this->recorder->calls[0][0] );
		$this->assertSame( 'related-posts-default.php', $this->recorder->calls[0][1]['template'] );
		$this->assertSame( -1, $this->recorder->calls[0][1]['limit'] );
		$this->assertNull( $this->recorder->calls[0][1]['heading_text'] );
		$this->assertSame( 0, $this->recorder->calls[0][1]['offset'] );
	}

	public function test_the_shortcode_passes_its_attributes(): void {
		$this->assertSame( 'rendered', do_shortcode( "[rp4wp id={$this->post_id} limit=2 offset=1 template='my-template.php' heading_text='Read next']" ) );
		$this->assertSame(
			[
				[
					$this->post_id,
					[
						'template'     => 'my-template.php',
						'limit'        => 2,
						'heading_text' => 'Read next',
						'offset'       => 1,
					],
				],
			],
			$this->recorder->calls
		);
	}

	public function test_the_shortcode_has_the_defaults_of_the_template_tag(): void {
		do_shortcode( "[rp4wp id={$this->post_id}]" );

		$this->assertSame(
			[
				'template'     => 'related-posts-default.php',
				'limit'        => -1,
				'heading_text' => null,
				'offset'       => 0,
			],
			$this->recorder->calls[0][1]
		);
	}

	public function test_the_content_of_a_single_post_ends_with_the_rendered_posts(): void {
		$this->go_to( get_permalink( $this->post_id ) );
		the_post();

		$content = (string) apply_filters( 'the_content', 'Content' );

		$this->assertStringEndsWith( 'rendered', $content );
		$this->assertSame( [ [ $this->post_id, [] ] ], $this->recorder->calls );
	}

	public function test_the_widget_shows_the_rendered_posts(): void {
		$this->go_to( get_permalink( $this->post_id ) );
		the_post();

		ob_start();
		the_widget( 'RP4WP_Related_Posts_Widget' );

		$this->assertStringContainsString( 'rendered', (string) ob_get_clean() );
		$this->assertSame( [ [ $this->post_id, [] ] ], $this->recorder->calls );
	}

	public function test_the_free_renderer_uses_limit_and_offset_and_ignores_the_premium_arguments(): void {
		$links = new LinkRepository();
		foreach ( self::factory()->post->create_many( 3 ) as $child_id ) {
			$links->add( $this->post_id, $child_id );
		}

		$html = $this->original->render(
			$this->post_id,
			[
				'limit'        => 1,
				'offset'       => 1,
				'template'     => 'my-template.php',
				'heading_text' => 'Read next',
			]
		);

		$this->assertNotSame( '', $html );
		$this->assertSame( $this->free_renderer()->related_posts_html( $this->post_id, 1, 1 ), $html );
	}

	/**
	 * The default renderer, typed.
	 *
	 * @return \LV2\WordPress\RelatedPostsForWP\Frontend\Renderer
	 */
	private function free_renderer(): \LV2\WordPress\RelatedPostsForWP\Frontend\Renderer {
		$this->assertInstanceOf( \LV2\WordPress\RelatedPostsForWP\Frontend\Renderer::class, $this->original );

		return $this->original;
	}
}

/**
 * A renderer that records what it is asked, and answers the same every time.
 */
final class RecordingRenderer implements Renderer {

	/**
	 * The calls: the post and the arguments.
	 *
	 * @var list<array{int, array<string, mixed>}>
	 */
	public array $calls = [];

	/**
	 * Record the call.
	 *
	 * @param int                  $post_id The post.
	 * @param array<string, mixed> $args    What to show.
	 *
	 * @return string
	 */
	public function render( int $post_id, array $args = [] ): string {
		$this->calls[] = [ $post_id, $args ];

		return 'rendered';
	}
}
