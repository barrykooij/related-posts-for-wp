<?php
/**
 * The link screen list table class file.
 *
 * @package RelatedPostsForWP
 */

namespace LV2\WordPress\RelatedPostsForWP\Admin\LinkScreen;

use LV2\WordPress\RelatedPostsForWP\PostTypes;
use LV2\WordPress\RelatedPostsForWP\Related\Finder;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The posts on the link screen: suggested related posts, or all posts with search and paging.
 *
 * The 2.x class RP4WP_Link_Related_Table extends this one, so the method and parameter names are kept.
 */
class ListTable extends \WP_List_Table {

	/**
	 * The rows.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $data = [];

	/**
	 * The search text, in the "all" view.
	 *
	 * @var string|null
	 */
	private $search = null;

	/**
	 * Whether the table shows the suggested related posts.
	 *
	 * @var bool
	 */
	private $is_related = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'views_' . $this->screen->id, [ $this, 'add_page_views' ] );
	}

	/**
	 * The views above the table: suggested related posts, or all posts.
	 *
	 * @return array<string, string>
	 */
	public function add_page_views() {
		$current = $this->get_current_view();
		$views   = [
			'related' => __( 'Related Posts', 'related-posts-for-wp' ),
			'all'     => __( 'All Posts', 'related-posts-for-wp' ),
		];

		$links = [];
		foreach ( $views as $key => $label ) {
			$url           = esc_url(
				add_query_arg(
					[
						'rp4wp_view' => $key,
						'paged'      => 1,
					]
				)
			);
			$links[ $key ] = "<a href='" . $url . "'" . ( ( $current === $key ) ? " class='current'" : '' ) . '>' . esc_html( $label ) . '</a>';
		}

		return $links;
	}

	/**
	 * Set the search text. Only the "all" view can be searched.
	 *
	 * @param string|null $search The search text.
	 *
	 * @return void
	 */
	public function set_search( $search ) {
		if ( 'related' !== $this->get_current_view() ) {
			$this->search = $search;
		}
	}

	/**
	 * The search box, only in the "all" view.
	 *
	 * @param string $text     The button text.
	 * @param string $input_id The input ID.
	 *
	 * @return void
	 */
	public function search_box( $text, $input_id ) {
		if ( 'related' !== $this->get_current_view() ) {
			parent::search_box( $text, $input_id );
		}
	}

	/**
	 * The columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return [
			'cb'        => '<input type="checkbox" />',
			'title'     => __( 'Title', 'related-posts-for-wp' ),
			'post_date' => __( 'Post Date', 'related-posts-for-wp' ),
		];
	}

	/**
	 * Load the rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading the view, paging and sorting of a list.
		$view             = $this->get_current_view();
		$this->is_related = 'related' === $view;

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];

		$screen   = get_current_screen();
		$per_page = absint( get_user_meta( get_current_user_id(), $screen->get_option( 'per_page', 'option' ), true ) );
		$per_page = $per_page > 0 ? $per_page : 20;
		$paged    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'title';
		$order    = isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'asc';
		$parent   = isset( $_GET['rp4wp_parent'] ) ? absint( $_GET['rp4wp_parent'] ) : 0;
		// phpcs:enable

		$this->views();

		if ( null !== $this->search ) {
			add_filter( 'posts_where', [ $this, 'filter_posts_where' ] );
		}

		$this->data = [];
		$query      = null;

		if ( 'all' === $view ) {
			$query = new \WP_Query(
				[
					'post_type'        => PostTypes::supported(),
					'posts_per_page'   => $per_page,
					'paged'            => $paged,
					'suppress_filters' => false,
					'orderby'          => $orderby,
					'order'            => $order,
					/**
					 * Filters the post statuses offered for manual linking.
					 *
					 * @since 1.4.0
					 *
					 * @param string[] $statuses The post statuses.
					 */
					'post_status'      => apply_filters( 'rp4wp_manual_link_post_statuses', [ 'publish', 'private' ] ),
				]
			);

			$posts = $query->posts;
		} else {
			$posts = ( new Finder() )->related_posts( $parent, 25 );
		}

		foreach ( $posts as $post ) {
			// Related results only hold the ID and title; get the whole post for its date.
			if ( ! $post instanceof \WP_Post ) {
				$post = get_post( is_object( $post ) ? $post->ID : $post );
			}

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$this->data[] = [
				'ID'        => $post->ID,
				'title'     => $post->post_title,
				'post_date' => date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) ),
			];
		}

		remove_filter( 'posts_where', [ $this, 'filter_posts_where' ] );

		if ( null !== $query ) {
			$this->set_pagination_args(
				[
					'total_items' => $query->found_posts,
					'per_page'    => $per_page,
				]
			);
		}

		$this->items = $this->data;
	}

	/**
	 * The sortable columns; the suggested related posts keep their order.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public function get_sortable_columns() {
		if ( $this->is_related ) {
			return [];
		}

		return [
			'title'     => [ 'title', false ],
			'post_date' => [ 'post_date', false ],
		];
	}

	/**
	 * A usort() callback for the rows. Unused since 2.x, kept for code that calls it.
	 *
	 * @param array<string, mixed> $a A row.
	 * @param array<string, mixed> $b Another row.
	 *
	 * @return int
	 */
	public function custom_reorder( $a, $b ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading the sort order of a list.
		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'title';
		$order   = ! empty( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'asc';
		// phpcs:enable

		$result = strcmp( (string) $a[ $orderby ], (string) $b[ $orderby ] );

		return 'asc' === $order ? $result : -$result;
	}

	/**
	 * The checkbox column.
	 *
	 * @param array<string, mixed> $item The row.
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="rp4wp_bulk[]" value="%s" />', (int) $item['ID'] );
	}

	/**
	 * The title column, with the "Link Post" and "View Post" actions.
	 *
	 * @param array<string, mixed> $item The row.
	 *
	 * @return string
	 */
	public function column_title( $item ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Building links on the current page.
		$page   = isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : Page::SLUG;
		$parent = isset( $_GET['rp4wp_parent'] ) ? absint( $_GET['rp4wp_parent'] ) : 0;
		// phpcs:enable

		$actions = [
			'link' => sprintf(
				'<a href="%s">' . esc_html__( 'Link Post', 'related-posts-for-wp' ) . '</a>',
				esc_attr( sprintf( '?page=%s&rp4wp_parent=%s&rp4wp_create_link=%s&rp4wp_nonce=%s', $page, $parent, (int) $item['ID'], wp_create_nonce( Page::NONCE_LINK ) ) )
			),
			'view' => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_attr( (string) get_permalink( $item['ID'] ) ),
				// A WordPress core string, translated by WordPress itself, like 2.x.
				esc_html__( 'View Post' ) // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- See above.
			),
		];

		return sprintf( '%1$s %2$s', esc_html( $item['title'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Any other column.
	 *
	 * @param array<string, mixed> $item        The row.
	 * @param string               $column_name The column.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	/**
	 * The bulk actions.
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions() {
		return [
			'link' => esc_html__( 'Link Posts', 'related-posts-for-wp' ),
		];
	}

	/**
	 * Limit the posts to titles containing the search text.
	 *
	 * @param string $where The WHERE clause.
	 *
	 * @return string
	 */
	public function filter_posts_where( $where ) {
		global $wpdb;

		return $where . $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s ", '%' . $wpdb->esc_like( (string) $this->search ) . '%' );
	}

	/**
	 * The current view: "related" (default) or "all".
	 *
	 * @return string
	 */
	private function get_current_view() {
		return isset( $_GET['rp4wp_view'] ) ? sanitize_key( $_GET['rp4wp_view'] ) : 'related'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the view of a list.
	}
}
