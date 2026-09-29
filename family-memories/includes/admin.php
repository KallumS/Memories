<?php
/**
 * The review side, in the WordPress dashboard:
 *  - a red counter on the Memories menu showing how many are waiting
 *  - a "Waiting for review" list with one-click "Approve" links
 *  - the contributor's details on each memory's edit screen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publish a pending memory. (wp_update_post, unlike wp_publish_post, also gives
 * it its web address and sets its date to the moment it was approved.)
 */
function fmem_approve_memory( $post_id ) {
	return wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		)
	);
}

function fmem_pending_count() {
	$counts = wp_count_posts( FMEM_POST_TYPE );
	return isset( $counts->pending ) ? (int) $counts->pending : 0;
}

function fmem_review_url() {
	return admin_url( 'edit.php?post_status=pending&post_type=' . FMEM_POST_TYPE );
}

/**
 * "Waiting for review" menu item, and a red number on the Memories menu.
 */
function fmem_admin_menu() {
	global $menu;
	$pending = fmem_pending_count();

	add_submenu_page(
		'edit.php?post_type=' . FMEM_POST_TYPE,
		__( 'Waiting for review', 'family-memories' ),
		__( 'Waiting for review', 'family-memories' ) . ( $pending ? ' <span class="awaiting-mod"><span class="pending-count">' . number_format_i18n( $pending ) . '</span></span>' : '' ),
		'edit_others_posts',
		'edit.php?post_status=pending&post_type=' . FMEM_POST_TYPE
	);

	if ( ! $pending || ! is_array( $menu ) ) {
		return;
	}
	foreach ( $menu as $position => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . FMEM_POST_TYPE === $item[2] ) {
			$menu[ $position ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . number_format_i18n( $pending ) . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			break;
		}
	}
}
add_action( 'admin_menu', 'fmem_admin_menu' );

/**
 * Put "Waiting for review" straight after "All Memories" in the menu.
 */
function fmem_order_submenu() {
	global $submenu;
	$parent = 'edit.php?post_type=' . FMEM_POST_TYPE;
	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}
	$review = null;
	foreach ( $submenu[ $parent ] as $key => $item ) {
		if ( false !== strpos( $item[2], 'post_status=pending' ) ) {
			$review = $item;
			unset( $submenu[ $parent ][ $key ] );
			break;
		}
	}
	if ( $review ) {
		$items = array_values( $submenu[ $parent ] );
		array_splice( $items, 1, 0, array( $review ) );
		$submenu[ $parent ] = $items; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}
}
add_action( 'admin_menu', 'fmem_order_submenu', 99 );

/**
 * A friendly reminder on the Dashboard when memories are waiting.
 */
function fmem_pending_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'dashboard' !== $screen->id || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$pending = fmem_pending_count();
	if ( ! $pending ) {
		return;
	}
	printf(
		'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
		/* translators: %s: number of memories */
		esc_html( sprintf( _n( '%s new memory is waiting for you to review.', '%s new memories are waiting for you to review.', $pending, 'family-memories' ), number_format_i18n( $pending ) ) ),
		esc_url( fmem_review_url() ),
		esc_html__( 'Review them now', 'family-memories' )
	);
}
add_action( 'admin_notices', 'fmem_pending_notice' );

/* ---------- The list of memories ---------- */

function fmem_list_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['cb']          = $columns['cb'] ?? '';
			$new['fmem_photo']  = __( 'Photo', 'family-memories' );
			$new['title']       = __( 'Memory', 'family-memories' );
			$new['fmem_story']  = __( 'Story', 'family-memories' );
			$new['fmem_shared'] = __( 'Shared by', 'family-memories' );
			continue;
		}
		$new[ $key ] = $label;
	}
	if ( isset( $new[ 'taxonomy-' . FMEM_TAXONOMY ] ) ) {
		$new[ 'taxonomy-' . FMEM_TAXONOMY ] = __( 'About', 'family-memories' );
	}
	return $new;
}
add_filter( 'manage_' . FMEM_POST_TYPE . '_posts_columns', 'fmem_list_columns' );

function fmem_list_column_content( $column, $post_id ) {
	if ( 'fmem_photo' === $column ) {
		$photos = (array) get_post_meta( $post_id, '_fmem_photo_ids', true );
		echo get_the_post_thumbnail( $post_id, array( 60, 60 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		$extra = count( array_filter( $photos ) ) - 1;
		if ( $extra > 0 ) {
			/* translators: %d: number of extra photos */
			echo '<br><small>' . esc_html( sprintf( __( '+%d more', 'family-memories' ), $extra ) ) . '</small>';
		}
	} elseif ( 'fmem_story' === $column ) {
		// The start of the story, so it can be read without opening it.
		echo esc_html( wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 35 ) );
	} elseif ( 'fmem_shared' === $column ) {
		$name  = fmem_get_meta( $post_id, 'contributor_name' );
		$rel   = fmem_get_meta( $post_id, 'relationship' );
		$email = fmem_get_meta( $post_id, 'contributor_email' );
		echo esc_html( $name ? $name : '—' );
		if ( $rel ) {
			echo '<br><small>' . esc_html( $rel ) . '</small>';
		}
		if ( $email ) {
			echo '<br><small><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></small>';
		}
	}
}
add_action( 'manage_' . FMEM_POST_TYPE . '_posts_custom_column', 'fmem_list_column_content', 10, 2 );

function fmem_list_column_styles() {
	$screen = get_current_screen();
	if ( $screen && 'edit-' . FMEM_POST_TYPE === $screen->id ) {
		echo '<style>.column-fmem_photo{width:70px}.column-fmem_story{width:35%}.column-fmem_photo img{border-radius:4px;object-fit:cover}.fmem-approve{color:#008a20;font-weight:600}</style>';
	}
}
add_action( 'admin_head', 'fmem_list_column_styles' );

/**
 * An "Approve & publish" link under each pending memory.
 */
function fmem_row_actions( $actions, $post ) {
	if ( FMEM_POST_TYPE !== $post->post_type || 'pending' !== $post->post_status || ! current_user_can( 'publish_post', $post->ID ) ) {
		return $actions;
	}
	$url     = wp_nonce_url( admin_url( 'admin-post.php?action=fmem_approve&post=' . $post->ID ), 'fmem_approve_' . $post->ID );
	$approve = array( 'fmem_approve' => '<a class="fmem-approve" href="' . esc_url( $url ) . '">' . esc_html__( 'Approve & publish', 'family-memories' ) . '</a>' );
	return $approve + $actions;
}
add_filter( 'post_row_actions', 'fmem_row_actions', 10, 2 );

function fmem_handle_approve() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	check_admin_referer( 'fmem_approve_' . $post_id );
	if ( ! $post_id || FMEM_POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'publish_post', $post_id ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to approve this memory.', 'family-memories' ) );
	}
	fmem_approve_memory( $post_id );
	wp_safe_redirect( add_query_arg( 'fmem_approved', 1, wp_get_referer() ? wp_get_referer() : fmem_review_url() ) );
	exit;
}
add_action( 'admin_post_fmem_approve', 'fmem_handle_approve' );

/** "Approve & publish" in the Bulk actions drop-down. */
function fmem_bulk_actions( $actions ) {
	return array( 'fmem_approve' => __( 'Approve & publish', 'family-memories' ) ) + $actions;
}
add_filter( 'bulk_actions-edit-' . FMEM_POST_TYPE, 'fmem_bulk_actions' );

function fmem_handle_bulk_approve( $redirect, $action, $post_ids ) {
	if ( 'fmem_approve' !== $action ) {
		return $redirect;
	}
	$done = 0;
	foreach ( $post_ids as $post_id ) {
		if ( 'pending' === get_post_status( $post_id ) && current_user_can( 'publish_post', $post_id ) ) {
			fmem_approve_memory( $post_id );
			++$done;
		}
	}
	return add_query_arg( 'fmem_approved', $done, $redirect );
}
add_filter( 'handle_bulk_actions-edit-' . FMEM_POST_TYPE, 'fmem_handle_bulk_approve', 10, 3 );

function fmem_approved_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['fmem_approved'] ) ) {
		return;
	}
	$n = absint( $_GET['fmem_approved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	/* translators: %s: number of memories */
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%s memory approved and now on the site.', '%s memories approved and now on the site.', $n, 'family-memories' ), number_format_i18n( $n ) ) ) . '</p></div>';
}
add_action( 'admin_notices', 'fmem_approved_notice' );

/* ---------- The edit screen for a single memory ---------- */

function fmem_add_meta_box() {
	add_meta_box( 'fmem-details', __( 'Who shared this memory', 'family-memories' ), 'fmem_render_meta_box', FMEM_POST_TYPE, 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'fmem_add_meta_box' );

function fmem_render_meta_box( $post ) {
	wp_nonce_field( 'fmem_save_details', 'fmem_details_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( fmem_meta_fields() as $field => $label ) {
		$type = 'contributor_email' === $field ? 'email' : 'text';
		printf(
			'<tr><th scope="row"><label for="fmem-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="fmem-%1$s" name="fmem_meta[%1$s]" value="%4$s"></td></tr>',
			esc_attr( $field ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( fmem_get_meta( $post->ID, $field ) )
		);
	}
	echo '</table>';

	$photos = array_filter( array_map( 'intval', (array) get_post_meta( $post->ID, '_fmem_photo_ids', true ) ) );
	if ( $photos ) {
		echo '<p><strong>' . esc_html__( 'Photos sent with this memory', 'family-memories' ) . '</strong></p><p>';
		foreach ( $photos as $id ) {
			$full = wp_get_attachment_image_url( $id, 'full' );
			if ( $full ) {
				echo '<a href="' . esc_url( $full ) . '" target="_blank" rel="noopener" style="margin-right:8px;display:inline-block">' . wp_get_attachment_image( $id, 'thumbnail' ) . '</a>';
			}
		}
		echo '</p><p class="description">' . esc_html__( 'The first photo is the main picture (see "Featured image"). Extra photos appear below the story.', 'family-memories' ) . '</p>';
	}
}

function fmem_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['fmem_details_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fmem_details_nonce'] ), 'fmem_save_details' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$input = isset( $_POST['fmem_meta'] ) && is_array( $_POST['fmem_meta'] ) ? wp_unslash( $_POST['fmem_meta'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( array_keys( fmem_meta_fields() ) as $field ) {
		$value = isset( $input[ $field ] ) ? ( 'contributor_email' === $field ? sanitize_email( $input[ $field ] ) : sanitize_text_field( $input[ $field ] ) ) : '';
		if ( '' === $value ) {
			delete_post_meta( $post_id, '_fmem_' . $field );
		} else {
			update_post_meta( $post_id, '_fmem_' . $field, wp_slash( $value ) );
		}
	}
}
add_action( 'save_post_' . FMEM_POST_TYPE, 'fmem_save_meta_box' );

/**
 * When a memory is permanently deleted (emptied from the bin), also delete
 * the photos that were sent in with it, so rejected photos don't linger.
 */
function fmem_delete_submitted_photos( $post_id ) {
	if ( FMEM_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	$photos = array_filter( array_map( 'intval', (array) get_post_meta( $post_id, '_fmem_photo_ids', true ) ) );
	foreach ( $photos as $id ) {
		if ( get_post_meta( $id, '_fmem_submitted_photo', true ) ) {
			wp_delete_attachment( $id, true );
		}
	}
}
add_action( 'before_delete_post', 'fmem_delete_submitted_photos' );
