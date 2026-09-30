<?php
/**
 * Emails:
 *  - to you, when a new memory is waiting for review
 *  - to the person who shared it, once it has been approved (if they gave an email)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fmem_email_new_memory( $post_id ) {
	$settings = fmem_get_settings();
	if ( ! is_email( $settings['notify_email'] ) ) {
		return;
	}
	$name    = fmem_get_meta( $post_id, 'contributor_name' );
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$story   = wp_trim_words( get_post_field( 'post_content', $post_id ), 80 );
	$photos  = count( array_filter( (array) get_post_meta( $post_id, '_fmem_photo_ids', true ) ) );
	$people  = wp_get_object_terms( $post_id, FMEM_TAXONOMY, array( 'fields' => 'names' ) );
	$subject = sprintf(
		/* translators: 1: site name, 2: contributor's name */
		__( '[%1$s] New memory from %2$s', 'family-memories' ),
		$site,
		$name
	);

	$lines   = array();
	$lines[] = sprintf(
		/* translators: %s: contributor's name */
		__( '%s has shared a memory. It is waiting for you to review it.', 'family-memories' ),
		$name
	);
	$lines[] = '';
	$lines[] = '"' . get_the_title( $post_id ) . '"';
	if ( $people && ! is_wp_error( $people ) ) {
		$lines[] = __( 'About:', 'family-memories' ) . ' ' . implode( ', ', $people );
	}
	$lines[] = '';
	$lines[] = $story;
	$lines[] = '';
	if ( $photos ) {
		/* translators: %d: number of photos */
		$lines[] = sprintf( _n( '(%d photo attached)', '(%d photos attached)', $photos, 'family-memories' ), $photos );
		$lines[] = '';
	}
	$lines[] = __( 'Read it and approve or delete it here:', 'family-memories' );
	$lines[] = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	$lines[] = '';
	$lines[] = __( 'Or see everything waiting for review:', 'family-memories' );
	$lines[] = fmem_review_url();

	$headers = array();
	$email   = fmem_get_meta( $post_id, 'contributor_email' );
	if ( is_email( $email ) ) {
		$headers[] = 'Reply-To: ' . str_replace( array( "\r", "\n", '<', '>', '"' ), '', $name ) . ' <' . $email . '>';
	}

	wp_mail( $settings['notify_email'], $subject, implode( "\n", $lines ), $headers );
}
add_action( 'fmem_memory_submitted', 'fmem_email_new_memory' );

function fmem_email_contributor_on_approval( $new_status, $old_status, $post ) {
	if ( FMEM_POST_TYPE !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	$settings = fmem_get_settings();
	$email    = fmem_get_meta( $post->ID, 'contributor_email' );
	if ( empty( $settings['notify_contributors'] ) || ! is_email( $email ) || get_post_meta( $post->ID, '_fmem_contributor_told', true ) ) {
		return;
	}
	update_post_meta( $post->ID, '_fmem_contributor_told', 1 );

	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$name    = fmem_get_meta( $post->ID, 'contributor_name' );
	/* translators: %s: site name */
	$subject = sprintf( __( 'Your memory is now on %s', 'family-memories' ), $site );
	$lines   = array(
		/* translators: %s: contributor's name */
		sprintf( __( 'Dear %s,', 'family-memories' ), $name ? $name : __( 'friend', 'family-memories' ) ),
		'',
		/* translators: %s: memory title */
		sprintf( __( 'Thank you for sharing "%s". It has now been added and you can see it here:', 'family-memories' ), get_the_title( $post ) ),
		get_permalink( $post ),
		'',
		__( 'It means a great deal to us. If you have more memories, we would love to hear them too.', 'family-memories' ),
		'',
		$site,
	);
	wp_mail( $email, $subject, implode( "\n", $lines ) );
}
add_action( 'transition_post_status', 'fmem_email_contributor_on_approval', 10, 3 );
