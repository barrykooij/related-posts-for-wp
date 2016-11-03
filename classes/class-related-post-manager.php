<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class RP4WP_Related_Post_Manager {

	/**
	 * Get related posts by post id and post type
	 *
	 * @param int $post_id
	 * @param int $limit
	 *
	 * @return array
	 */
	public function get_related_posts( $post_id, $limit = - 1 ) {
		global $wpdb;

		// Build SQl
		$sql = "
		SELECT P.`ID`, P.`post_title`, ( SUM( O.`weight` ) *  SUM( R.`weight` ) ) AS `CMS`
		FROM `" . RP4WP_Related_Word_Manager::get_database_table() . "` O
		INNER JOIN `" . RP4WP_Related_Word_Manager::get_database_table() . "` R ON R.`word` = O.`word`
		INNER JOIN `" . $wpdb->posts . "` P ON P.`ID` = R.`post_id`
		WHERE 1=1
		AND O.`post_id` = %d
		AND R.`post_type` = %s
		AND R.`post_id` != %d
		AND P.`post_status` = 'publish'
		GROUP BY P.`id`
		ORDER BY `CMS` DESC
		";

		// Check & Add Limit
		if ( - 1 != $limit ) {
			$sql .= "
			LIMIT 0,%d";
			// Prepare SQL
			$sql = $wpdb->prepare( $sql, $post_id, get_post_type( $post_id ), $post_id, $limit );
		} else {
			// Prepare SQL
			$sql = $wpdb->prepare( $sql, $post_id, get_post_type( $post_id ), $post_id );
		}

		// Get post from related cache
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get non auto linked posts
	 *
	 * @param $limit
	 *
	 * @return array
	 */
	public function get_not_auto_linked_posts_ids( $limit ) {
		return get_posts( array(
			'fields'         => 'ids',
			'post_type'      => RP4WP_Related_Post_Manager::get_supported_post_types(),
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => RP4WP_Constants::PM_POST_AUTO_LINKED,
					'compare' => 'NOT EXISTS',
					'value'   => ''
				),
			)
		) );
	}

	/**
	 * Deprecated, use get_unlinked_post_count() instead
	 *
	 * @deprecated 1.9.0
	 *
	 * @param $post_type
	 *
	 * @return mixed
	 */
	public function get_uncached_post_count( $post_type ) {

		// Deprecated notice
		_deprecated_function( __FUNCTION__, '1.9.0', __CLASS__ . '->get_uncached_post_count()' );

		return $this->get_unlinked_post_count( $post_type );
	}

	/**
	 * Get the unlinked post count
	 *
	 * @since  1.6.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_unlinked_post_count() {
		global $wpdb;

		$post_count = $wpdb->get_var( "SELECT COUNT(P.ID) FROM " . $wpdb->posts . " P LEFT JOIN " . $wpdb->postmeta . " PM ON (P.ID = PM.post_id AND PM.meta_key = '" . RP4WP_Constants::PM_POST_AUTO_LINKED . "') WHERE 1=1 AND P.post_type IN ('" . implode( "','", RP4WP_Related_Post_Manager::get_supported_post_types() ) . "') AND P.post_status = 'publish' AND PM.post_id IS NULL GROUP BY P.post_status" );

		if ( ! is_numeric( $post_count ) ) {
			$post_count = 0;
		}

		return $post_count;
	}

	/**
	 * Link x related posts to post
	 *
	 * @param $post_id
	 * @param $amount
	 *
	 * @return boolean
	 */
	public function link_related_post( $post_id, $amount ) {
		$related_posts = $this->get_related_posts( $post_id, $amount );

		if ( count( $related_posts ) > 0 ) {

			global $wpdb;

			$post_link_manager = new RP4WP_Post_Link_Manager();

			$batch_data = array();
			foreach ( $related_posts as $related_post ) {
				$batch_data[] = $post_link_manager->add( $post_id, $related_post->ID, true );
			}

			// Do batch insert
			$wpdb->query( "INSERT INTO `$wpdb->posts`
						(`post_date`,`post_date_gmt`,`post_content`,`post_title`,`post_type`,`post_status`)
						VALUES
						" . implode( ',', array_map( array( $this, 'batch_data_get_post' ), $batch_data ) ) . "
						" );

			// Get the first post link insert ID
			$pid = $wpdb->insert_id;

			// Set the correct ID's for batch meta insert
			foreach ( $batch_data as $bk => $bd ) {
				$batch_data[ $bk ]['meta'] = array_map( array(
					$this,
					'batch_data_set_pid'
				), $bd['meta'], array_fill( 0, count( $bd['meta'] ), $pid ) );
				$pid ++;
			}

			// Insert all the meta
			$wpdb->query( "INSERT INTO `$wpdb->postmeta`
				(`post_id`,`meta_key`,`meta_value`)
				VALUES
				" . implode( ',', array_map( array( $this, 'batch_data_get_meta' ), $batch_data ) ) . "
				" );

		}

		update_post_meta( $post_id, RP4WP_Constants::PM_POST_AUTO_LINKED, 1 );

		return true;
	}

	/**
	 * Get post batch data
	 *
	 * @param $batch
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function batch_data_get_post( $batch ) {
		return $batch['post'];
	}

	/**
	 * Get meta batch data
	 *
	 * @param $batch
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function batch_data_get_meta( $batch ) {
		return implode( ',', $batch['meta'] );
	}

	/**
	 * Set the post ID's in batch data
	 *
	 * @param $batch
	 * @param $pid
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function batch_data_set_pid( $batch, $pid ) {
		return sprintf( $batch, $pid );
	}

	/**
	 * Link x related posts to y not already linked posts
	 *
	 * @param int $rel_amount
	 * @param int $post_amount
	 *
	 * @return boolean
	 */
	public function link_related_posts( $rel_amount, $post_amount = - 1 ) {
		global $wpdb;

		// Get uncached posts
		$post_ids = $this->get_not_auto_linked_posts_ids( $post_amount );

		// Check & Loop
		if ( count( $post_ids ) > 0 ) {
			foreach ( $post_ids as $post_id ) {
				$this->link_related_post( $post_id, $rel_amount );
			}
		}

		// Done
		return true;
	}

	/**
	 * Returns array with escaped supported post types
	 * @return array
	 */
	public static function get_supported_post_types() {

		// get post types
		$post_types = apply_filters( 'rp4wp_supported_post_types', array( 'post' ) );

		// at least 1 supported post type is needed
		if ( ! is_array( $post_types ) || count( $post_types ) < 1 ) {
			$post_types = array( 'post' );
		}
		
		// escape values
		foreach( $post_types as $pk => $pv ) {
			$post_types[ $pk ] = esc_sql( $pv );
		}

		return $post_types;
	}

}