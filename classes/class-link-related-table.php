<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class RP4WP_Link_Related_Table extends WP_List_Table {

	private $post_type;
	private $data;
	private $search;

	private $enable_related = false;
	private $is_related = false;

	public function __construct() {
		parent::__construct();
		add_filter( 'views_' . $this->screen->id, array( $this, 'add_page_views' ) );
	}

	/**
	 * Get the current view
	 *
	 * @return string
	 */
	private function get_current_view() {
		return ( isset ( $_GET['rp4wp_view'] ) ? $_GET['rp4wp_view'] : 'related' );
	}

	/**
	 * Add page views
	 *
	 * @return array
	 */
	public function add_page_views() {

		// Get current
		$current = $this->get_current_view();

		$views_arr = array(
			'related' => __( 'Related Posts', 'related-posts-for-wp' ),
			'all'     => __( 'All Posts', 'related-posts-for-wp' ),
		);

		$new_views = array();

		foreach ( $views_arr as $key => $val ) {
			$new_views[ $key ] = "<a href='" . esc_url( add_query_arg( array(
					'rp4wp_view' => $key,
					'paged'      => 1
				) ) ) . "'" . ( ( $current == $key ) ? " class='current'" : "" ) . ">{$val}</a>";
		}

		return $new_views;
	}

	/**
	 * Set the search string
	 *
	 * @param $search
	 */
	public function set_search( $search ) {

		// Can't search through related posts
		if ( $this->get_current_view() != 'related' ) {
			$this->search = $search;
		}

	}

	/**
	 * Display the search box.
	 *
	 * @param string $text The search button text
	 * @param string $input_id The search input id
	 */
	public function search_box( $text, $input_id ) {
		if ( $this->get_current_view() != 'related' ) {
			parent::search_box( $text, $input_id );
		}
	}

	/**
	 * Get the columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'title'     => __( 'Title', 'related-posts-for-wp' ),
			'post_date' => __( 'Post Date', 'related-posts-for-wp' ),
		);

		return $columns;
	}

	/**
	 * Prepare the items
	 */
	public function prepare_items() {

		// Get current view
		$view = $this->get_current_view();

		// Check if we're in the related view
		if ( 'related' == $view ) {
			$this->is_related = true;
		}

		// Set table properties
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// pagination and sorting
		$screen   = get_current_screen();
		$per_page = absint( get_user_meta( get_current_user_id(), $screen->get_option( 'per_page', 'option' ), true ) );
		$per_page = ( ( $per_page > 0 ) ? $per_page : 20 );
		$paged    = absint( isset( $_GET['paged'] ) ? $_GET['paged'] : 1 );
		$orderby  = isset( $_GET['orderby'] ) ? $_GET['orderby'] : 'title';
		$order    = isset( $_GET['order'] ) ? $_GET['order'] : 'asc';

		// Vies
		$this->views();

		// Set search
		if ( null !== $this->search ) {
			add_filter( 'posts_where', array( $this, 'filter_posts_where' ) );
		}

		// Get Data
		$this->data = array();

		// Get posts
		if ( 'all' == $view ) {

			// posts
			$posts = array();

			// the posts query
			$post_query = new WP_Query( array(
				'post_type'        => RP4WP_Related_Post_Manager::get_supported_post_types(),
				'posts_per_page'   => $per_page,
				'paged'            => $paged,
				'suppress_filters' => false,
				'orderby'          => $orderby,
				'order'            => $order,
				'post_status'      => apply_filters( 'rp4wp_manual_link_post_statuses', array( 'publish', 'private' ) ),
			) );

			// Format data for table
			if ( $post_query->have_posts() ) {
				while ( $post_query->have_posts() ) {
					$posts[] = $post_query->next_post();
				}
			}


		} else {
			$rpm    = new RP4WP_Related_Post_Manager();
			$parent = intval($_GET['rp4wp_parent']);
			$posts  = $rpm->get_related_posts( $parent, 25 );
		}

		// Format data for table
		if ( count( $posts ) > 0 ) {
			foreach ( $posts as $post ) {

				// Related results only contain ID to keep query fast. Rather have an extra query per post on manual linking than a much heavier query for all linking.
				if ( ! isset( $post->post_date ) ) {
					$post = get_post( $post->ID );
				}

				$this->data[] = array( 'ID'        => $post->ID,
				                       'title'     => $post->post_title,
				                       'post_date' => date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) )
				);
			}
		}

		// Remove search filter
		remove_filter( 'posts_where', array( $this, 'filter_posts_where' ) );

		// Pagination only for all view
		if ( 'all' == $view ) {
			$this->set_pagination_args( array(
				'total_items' => $post_query->found_posts,
				'per_page'    => $per_page
			) );
		}

		// Set items
		$this->items = $this->data;
	}

	/**
	 * Get the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array();
		if ( ! $this->is_related ) {
			$sortable_columns['title']     = array( 'title', false );
			$sortable_columns['post_date'] = array( 'post_date', false );
		}

		return $sortable_columns;
	}

	/**
	 * Method to do the custom reorder
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return int
	 */
	public function custom_reorder( $a, $b ) {
		// If no sort, default to title
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'title';
		// If no order, default to asc
		$order = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'asc';
		// Determine sort order
		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );

		// Send final sort direction to usort
		return ( $order === 'asc' ) ? $result : - $result;
	}

	/**
	 * Checkbox column
	 *
	 * @param $item
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="rp4wp_bulk[]" value="%s" />', $item['ID']
		);
	}

	/**
	 * Title column
	 *
	 * @param $item
	 *
	 * @return string
	 */
	public function column_title( $item ) {
		$actions = array(
			'link' => sprintf(
				'<a href="%s">' . __( 'Link Post', 'related-posts-for-wp' ) . '</a>',
				esc_attr(
					sprintf(
						"?page=%s&amp;rp4wp_parent=%s&amp;rp4wp_create_link=%s",
						$_REQUEST['page'],
						$_GET['rp4wp_parent'],
						$item['ID']
					)
				)
			),
			'view' => sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_attr( get_permalink( $item['ID'] ) ),
				__( 'View Post' )
			)
		);

		return sprintf( '%1$s %2$s', $item['title'], $this->row_actions( $actions ) );
	}

	/**
	 * Default column
	 *
	 * @param $item
	 * @param $column_name
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( isset( $item[ $column_name ] ) ) {
			return $item[ $column_name ];
		}

		return '';
	}

	/**
	 * Get the bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array(
			'link' => __( 'Link Posts', 'related-posts-for-wp' )
		);

		return $actions;
	}

	/**
	 * Filter on the post where
	 *
	 * @param $where
	 *
	 * @return string
	 */
	public function filter_posts_where( $where ) {
		global $wpdb;
		$where .= $wpdb->prepare( " AND {$wpdb->prefix}posts.post_title LIKE '%%%s%%' ", $this->search );

		return $where;
	}

}
