<?php
/**
 * The two kinds of content this plugin adds:
 *  - "Memories" (a post type), each one a story someone has shared.
 *  - "People" (a taxonomy), the family members the memories are about.
 *    Each person gets their own page listing every memory about them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fmem_register_content_types() {
	register_post_type(
		FMEM_POST_TYPE,
		array(
			'labels'        => array(
				'name'               => __( 'Memories', 'family-memories' ),
				'singular_name'      => __( 'Memory', 'family-memories' ),
				'menu_name'          => __( 'Memories', 'family-memories' ),
				'all_items'          => __( 'All Memories', 'family-memories' ),
				'add_new'            => __( 'Add Memory', 'family-memories' ),
				'add_new_item'       => __( 'Add a Memory', 'family-memories' ),
				'edit_item'          => __( 'Review / Edit Memory', 'family-memories' ),
				'new_item'           => __( 'New Memory', 'family-memories' ),
				'view_item'          => __( 'View Memory', 'family-memories' ),
				'search_items'       => __( 'Search Memories', 'family-memories' ),
				'not_found'          => __( 'No memories found.', 'family-memories' ),
				'not_found_in_trash' => __( 'No memories in the bin.', 'family-memories' ),
			),
			'public'        => true,
			// No automatic archive page: put the [memory_wall] shortcode on any page instead.
			'has_archive'   => false,
			'rewrite'       => array(
				'slug'       => 'memory',
				'with_front' => false,
			),
			'menu_position' => 5,
			'menu_icon'     => 'dashicons-heart',
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'show_in_rest'  => true,
		)
	);

	register_taxonomy(
		FMEM_TAXONOMY,
		FMEM_POST_TYPE,
		array(
			'labels'            => array(
				'name'          => __( 'People', 'family-memories' ),
				'singular_name' => __( 'Person', 'family-memories' ),
				'menu_name'     => __( 'People', 'family-memories' ),
				'all_items'     => __( 'All People', 'family-memories' ),
				'edit_item'     => __( 'Edit Person', 'family-memories' ),
				'view_item'     => __( 'View Person', 'family-memories' ),
				'update_item'   => __( 'Update Person', 'family-memories' ),
				'add_new_item'  => __( 'Add a Person', 'family-memories' ),
				'new_item_name' => __( 'New Person Name', 'family-memories' ),
				'search_items'  => __( 'Search People', 'family-memories' ),
				'not_found'     => __( 'No people added yet.', 'family-memories' ),
				'back_to_items' => __( '&larr; Back to People', 'family-memories' ),
			),
			'public'            => true,
			// Hierarchical gives tick-boxes (rather than a tag box) when choosing people.
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'person',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'fmem_register_content_types' );

/**
 * Make sure memories can have a main photo, whatever theme is in use.
 */
function fmem_theme_support() {
	add_theme_support( 'post-thumbnails', array( FMEM_POST_TYPE ) );
}
add_action( 'after_setup_theme', 'fmem_theme_support', 99 );

/**
 * The details stored alongside each memory (beyond its title, story and photos).
 * The contributor's email is private and never shown on the site.
 */
function fmem_meta_fields() {
	return array(
		'contributor_name'  => __( 'Shared by', 'family-memories' ),
		'contributor_email' => __( 'Their email (private)', 'family-memories' ),
		'relationship'      => __( 'How they know them', 'family-memories' ),
		'when'              => __( 'When', 'family-memories' ),
		'where'             => __( 'Where', 'family-memories' ),
	);
}

function fmem_get_meta( $post_id, $field ) {
	return (string) get_post_meta( $post_id, '_fmem_' . $field, true );
}
