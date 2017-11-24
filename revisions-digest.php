<?php
/**
 * Revisions Digest plugin for WordPress
 *
 * @package   revisions-digest
 * @link      https://github.com/johnbillion/revisions-digest
 * @author    John Blackbourn <john@johnblackbourn.com>
 * @copyright 2017 John Blackbourn
 * @license   GPL v2 or later
 *
 * Plugin Name:     Revisions Digest
 * Plugin URI:      https://wordpress.org/plugins/revisions-digest
 * Description:     Digests of revisions.
 * Version:         0.1.0
 * Author:          John Blackbourn
 * Author URI:      https://johnblackbourn.com
 * Text Domain:     revisions-digest
 * Domain Path:     /languages
 * Requires PHP:    7.0
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

declare( strict_types=1 );

namespace RevisionsDigest;

use WP_Query;
use WP_Post;
use WP_Error;
use Text_Diff;
use Text_Diff_Renderer;
use WP_Text_Diff_Renderer_Table;

add_action( 'wp_dashboard_setup', function() {
	add_meta_box(
		'revisions_digest_dashboard',
		__( 'Recent Changes', 'revisions-digest' ),
		__NAMESPACE__ . '\widget',
		'index.php',
		'column3',
		'high'
	);
} );

/**
 * Undocumented function
 *
 * @param mixed $no_idea  @TODO find out what this parameter is.
 * @param array $meta_box @TODO find out what this parameter is.
 */
function widget( $no_idea, array $meta_box ) {
	$changes = get_digest_changes();

	if ( empty( $changes ) ) {
		esc_html_e( 'There have been no content changes in the last week', 'revisions-digest' );
		return;
	}

	foreach ( $changes as $change ) {
		echo '<div class="activity-block">';

		printf(
			'<h3><a href="%1$s">%2$s</a></h3>',
			esc_url( get_permalink( $change['post_id'] ) ),
			get_the_title( $change['post_id'] )
		);

		$authors = array_filter( array_map( function( int $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return false;
			}

			return $user->display_name;
		}, $change['authors'] ) );

		$modified_gmt = strtotime( $change['latest']->post_modified_gmt . ' +0000' );

		/* translators: %l: comma-separated list of author names */
		$changes_by = wp_sprintf(
			__( 'Changed by %l %s', 'revisions-digest' ),
			$authors,
			sprintf( __( '%s ago' ), human_time_diff( $modified_gmt, time() ) )
		);
		printf(
			'<p>%1$s</p>',
			esc_html( $changes_by )
		);

		echo '<table class="diff">';
		echo $change['rendered']; // WPCS: XSS ok.
		echo '</table>';

		echo '</div>';
	}
}

/**
 * Undocumented function
 *
 * @return array[] {
 *     Array of data about the changes.
 *
 *     @type array ...$0 {
 *         Data about the changes
 *
 *         @type int       $post_id  The post ID
 *         @type WP_Post   $earliest The earliest revision.
 *         @type WP_Post   $latest   The latest revision.
 *         @type Text_Diff $diff     The diff object.
 *         @type string    $rendered The rendered diff.
 *         @type int[]     $authors  The IDs of authors of the changes.
 *     }
 * }
 */
function get_digest_changes() : array {
	$time     = strtotime( '-1 week' );
	$modified = get_updated_posts( $time );
	$changes  = [];

	foreach ( $modified as $i => $modified_post_id ) {
		$revisions = get_post_revisions( $modified_post_id, $time );
		if ( empty( $revisions ) ) {
			continue;
		}

		if ( ! class_exists( 'WP_Text_Diff_Renderer_Table', false ) ) {
			require_once ABSPATH . WPINC . '/wp-diff.php';
		}

		// @TODO this includes the author of the first revision, which it should not
		$authors = array_unique( array_map( 'intval', wp_list_pluck( $revisions, 'post_author' ) ) );
		$bounds  = get_bound_revisions( $revisions );
		$diff    = get_diff( $bounds['latest'], $bounds['earliest'] );

		$renderer = new WP_Text_Diff_Renderer_Table( [
			'show_split_view'        => false,
			'leading_context_lines'  => 1,
			'trailing_context_lines' => 1,
		] );
		$rendered = render_diff( $diff, $renderer );

		$data = [
			'post_id'  => $modified_post_id,
			'latest'   => $bounds['latest'],
			'earliest' => $bounds['earliest'],
			'diff'     => $diff,
			'rendered' => $rendered,
			'authors'  => $authors,
		];

		$changes[] = $data;
	}

	return $changes;
}

/**
 * Undocumented function
 *
 * @param int $timeframe Fetch posts which have been modified since this timestamp.
 * @return int[] Array of post IDs.
 */
function get_updated_posts( int $timeframe ) : array {
	$earliest = date( 'Y-m-d H:i:s', $timeframe );

	// Fetch IDs of all posts that have been modified within the time period.
	$modified = new WP_Query( [
		'fields'      => 'ids',
		'post_type'   => 'page', // Just Pages for now.
		'post_status' => 'publish',
		'date_query'  => [
			'after'  => $earliest,
			'column' => 'post_modified',
		],
	] );

	// @TODO this might prime the post cache
	/**
	 * $revisions = new WP_Query( [
	 * 'post_type'       => 'revision',
	 * 'post_status'     => 'all',
	 * 'post_parent__in' => $modified->posts,
	 * ] );
	 */

	return $modified->posts;
}

/**
 * Undocumented function
 *
 * @param int $post_id   A post ID.
 * @param int $timeframe Fetch revisions since this timestamp.
 * @return WP_Post[] Array of post revisions.
 */
function get_post_revisions( int $post_id, int $timeframe ) : array {
	$earliest      = date( 'Y-m-d H: i: s', $timeframe );
	$revisions     = wp_get_post_revisions( $post_id );
	$use_revisions = [];

	foreach ( $revisions as $revision_id => $revision ) {
		// @TODO this needs to exclude revisions that occured before the post published date
		$use_revisions[] = $revision;

		// this allows the first revision before the date range to also be included.
		if ( $revision->post_modified < $earliest ) {
			break;
		}
	}

	if ( count( $use_revisions ) < 2 ) {
		return [];
	}

	return $use_revisions;
}

/**
 * Undocumented function
 *
 * @param WP_Post[] $revisions Array of post revisions.
 * @return WP_Post[] {
 *     Associative array of the latest and earliest revisions.
 *
 *     @type WP_Post $latest   The latest revision.
 *     @type WP_Post $earliest The earlist revision.
 * }
 */
function get_bound_revisions( array $revisions ) : array {
	$latest   = reset( $revisions );
	$earliest = end( $revisions );

	return compact( 'latest', 'earliest' );
}

/**
 * Undocumented function
 *
 * @param WP_Post $latest   The latest revision.
 * @param WP_Post $earliest The earliest revision.
 * @return Text_Diff The diff object.
 */
function get_diff( WP_Post $latest, WP_Post $earliest ) : Text_Diff {
	if ( ! class_exists( 'Text_Diff', false ) ) {
		require_once ABSPATH . WPINC . '/wp-diff.php';
	}

	$left_string  = normalize_whitespace( $earliest->post_content );
	$right_string = normalize_whitespace( $latest->post_content );
	$left_lines   = explode( "\n", $left_string );
	$right_lines  = explode( "\n", $right_string );

	return new Text_Diff( $left_lines, $right_lines );
}

/**
 * Undocumented function
 *
 * @param Text_Diff          $text_diff The diff object.
 * @param Text_Diff_Renderer $renderer  The diff renderer.
 * @return string The rendered diff.
 */
function render_diff( Text_Diff $text_diff, Text_Diff_Renderer $renderer ) : string {
	$diff = $renderer->render( $text_diff );

	return $diff;
}
