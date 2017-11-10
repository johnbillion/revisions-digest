<?php

namespace RevisionsDigest\Tests;

class TestCase extends \WP_UnitTestCase {

	public static function post_factory( array $args = [] ) : \WP_Post {
		global $wpdb;

		$post_id = self::factory()->post->create( $args );

		if ( isset( $args['post_modified'] ) ) {
			// wp_insert_post() doesn't support the post_modified parameter,
			// so this needs to be set manually:
			$wpdb->update( $wpdb->posts, [
				'post_modified' => $args['post_modified'],
			], [
				'ID' => $post_id,
			] );
		}

		clean_post_cache( $post_id );

		return get_post( $post_id );
	}

}
