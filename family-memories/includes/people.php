<?php
/**
 * People: the family members memories are about.
 *
 * Each person has a name, a short biography (the "Description" box) and an
 * optional portrait photo. Their page (/person/their-name/) lists every
 * approved memory about them. [family_members] shows everyone as a directory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fmem_portrait_html( $term_id, $size = 'medium' ) {
	$portrait_id = (int) get_term_meta( $term_id, 'fmem_portrait_id', true );
	return $portrait_id ? wp_get_attachment_image( $portrait_id, $size, false, array( 'class' => 'fmem-portrait' ) ) : '';
}

/* ---------- Admin: portrait field on the People screens ---------- */

function fmem_person_add_fields() {
	wp_nonce_field( 'fmem_person_portrait', 'fmem_person_nonce' );
	?>
	<div class="form-field">
		<label><?php esc_html_e( 'Portrait photo', 'family-memories' ); ?></label>
		<?php fmem_portrait_picker( 0 ); ?>
		<p><?php esc_html_e( 'Shown at the top of their page and in the family directory.', 'family-memories' ); ?></p>
	</div>
	<?php
}
add_action( FMEM_TAXONOMY . '_add_form_fields', 'fmem_person_add_fields' );

function fmem_person_edit_fields( $term ) {
	?>
	<tr class="form-field">
		<th scope="row"><label><?php esc_html_e( 'Portrait photo', 'family-memories' ); ?></label></th>
		<td>
			<?php wp_nonce_field( 'fmem_person_portrait', 'fmem_person_nonce' ); ?>
			<?php fmem_portrait_picker( (int) get_term_meta( $term->term_id, 'fmem_portrait_id', true ) ); ?>
			<p class="description"><?php esc_html_e( 'Shown at the top of their page and in the family directory.', 'family-memories' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( FMEM_TAXONOMY . '_edit_form_fields', 'fmem_person_edit_fields' );

function fmem_portrait_picker( $portrait_id ) {
	?>
	<div class="fmem-portrait-picker">
		<div class="fmem-portrait-preview"><?php echo $portrait_id ? wp_get_attachment_image( $portrait_id, 'thumbnail' ) : ''; ?></div>
		<input type="hidden" name="fmem_portrait_id" class="fmem-portrait-id" value="<?php echo esc_attr( $portrait_id ? $portrait_id : '' ); ?>">
		<button type="button" class="button fmem-portrait-choose"><?php esc_html_e( 'Choose photo', 'family-memories' ); ?></button>
		<button type="button" class="button-link fmem-portrait-remove" <?php echo $portrait_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove photo', 'family-memories' ); ?></button>
	</div>
	<?php
}

function fmem_save_person_portrait( $term_id ) {
	if ( ! isset( $_POST['fmem_person_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fmem_person_nonce'] ), 'fmem_person_portrait' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_term', $term_id ) ) {
		return;
	}
	$portrait_id = isset( $_POST['fmem_portrait_id'] ) ? absint( $_POST['fmem_portrait_id'] ) : 0;
	if ( $portrait_id && wp_attachment_is_image( $portrait_id ) ) {
		update_term_meta( $term_id, 'fmem_portrait_id', $portrait_id );
	} else {
		delete_term_meta( $term_id, 'fmem_portrait_id' );
	}
}
add_action( 'created_' . FMEM_TAXONOMY, 'fmem_save_person_portrait' );
add_action( 'edited_' . FMEM_TAXONOMY, 'fmem_save_person_portrait' );

function fmem_people_admin_scripts( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || FMEM_TAXONOMY !== $screen->taxonomy || ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'fmem-portrait', FMEM_URL . 'assets/js/admin-portrait.js', array( 'jquery' ), FMEM_VERSION, true );
	wp_localize_script(
		'fmem-portrait',
		'fmemPortrait',
		array(
			'title'  => __( 'Choose a portrait photo', 'family-memories' ),
			'button' => __( 'Use this photo', 'family-memories' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'fmem_people_admin_scripts' );

/** Portrait column in the People list. */
function fmem_people_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'name' === $key ) {
			$new['fmem_portrait'] = '';
		}
		$new[ $key ] = $label;
	}
	if ( isset( $new['posts'] ) ) {
		$new['posts'] = __( 'Memories', 'family-memories' );
	}
	return $new;
}
add_filter( 'manage_edit-' . FMEM_TAXONOMY . '_columns', 'fmem_people_columns' );

function fmem_people_column_content( $content, $column, $term_id ) {
	if ( 'fmem_portrait' === $column ) {
		$portrait_id = (int) get_term_meta( $term_id, 'fmem_portrait_id', true );
		return $portrait_id ? wp_get_attachment_image( $portrait_id, array( 48, 48 ) ) : '';
	}
	return $content;
}
add_filter( 'manage_' . FMEM_TAXONOMY . '_custom_column', 'fmem_people_column_content', 10, 3 );

/* ---------- Front of the site: person pages ---------- */

/**
 * The header at the top of a person's page: their portrait, biography, and
 * a button to share a memory about them.
 */
function fmem_person_header_html( $term, $extra_class = '' ) {
	fmem_enqueue_styles();

	$html     = '<div class="fmem-person-header ' . esc_attr( $extra_class ) . '">';
	$portrait = fmem_portrait_html( $term->term_id, 'medium' );
	if ( $portrait ) {
		$html .= '<div class="fmem-person-portrait">' . $portrait . '</div>';
	}
	$html .= '<div class="fmem-person-bio">';
	if ( '' !== trim( $term->description ) ) {
		$html .= wpautop( wp_kses_post( $term->description ) );
	}
	$count = (int) $term->count;
	/* translators: %s: number of memories */
	$html    .= '<p class="fmem-person-count">' . esc_html( sprintf( _n( '%s memory shared', '%s memories shared', $count, 'family-memories' ), number_format_i18n( $count ) ) ) . '</p>';
	$form_url = fmem_form_page_url( $term->slug );
	if ( $form_url ) {
		/* translators: %s: person's name */
		$html .= '<p><a class="fmem-button" href="' . esc_url( $form_url ) . '">' . esc_html( sprintf( __( 'Share a memory of %s', 'family-memories' ), $term->name ) ) . '</a></p>';
	}
	$html .= '</div></div>';
	return $html;
}

function fmem_current_person() {
	if ( ! is_tax( FMEM_TAXONOMY ) ) {
		return null;
	}
	$term = get_queried_object();
	return $term instanceof WP_Term ? $term : null;
}

/**
 * Block themes (e.g. Twenty Twenty-Four/Five): put the header straight after
 * the page title, and hide the theme's own plain description to avoid repeating it.
 */
function fmem_person_header_in_block_theme( $content, $block ) {
	$term = fmem_current_person();
	if ( ! $term ) {
		return $content;
	}
	if ( 'core/query-title' === $block['blockName'] ) {
		static $done = false;
		if ( ! $done ) {
			$done = true;
			// Match the width of the title above it.
			$align = isset( $block['attrs']['align'] ) ? 'align' . sanitize_html_class( $block['attrs']['align'] ) : '';
			return $content . fmem_person_header_html( $term, $align );
		}
	}
	if ( 'core/term-description' === $block['blockName'] ) {
		return '';
	}
	return $content;
}
add_filter( 'render_block', 'fmem_person_header_in_block_theme', 10, 2 );

/**
 * Classic themes show the description under the title; replace it with the header.
 */
function fmem_person_header_in_classic_theme( $description ) {
	$term = fmem_current_person();
	if ( ! $term || ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) ) {
		return $description;
	}
	return fmem_person_header_html( $term );
}
add_filter( 'get_the_archive_description', 'fmem_person_header_in_classic_theme' );

/**
 * List memories newest first on person pages, a comfortable number at a time.
 */
function fmem_person_page_query( $query ) {
	if ( ! is_admin() && $query->is_main_query() && $query->is_tax( FMEM_TAXONOMY ) ) {
		$query->set( 'posts_per_page', 20 );
	}
}
add_action( 'pre_get_posts', 'fmem_person_page_query' );

/**
 * [family_members] shortcode: a directory of everyone, with portraits.
 */
function fmem_directory_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'show_empty' => 'yes' ), $atts, 'family_members' );
	fmem_enqueue_styles();

	$people = get_terms(
		array(
			'taxonomy'   => FMEM_TAXONOMY,
			'hide_empty' => 'no' === $atts['show_empty'],
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $people ) || ! $people ) {
		return '<p class="fmem-empty">' . esc_html__( 'No family members have been added yet.', 'family-memories' ) . '</p>';
	}

	$html = '<div class="fmem-directory">';
	foreach ( $people as $person ) {
		$url      = get_term_link( $person );
		$portrait = fmem_portrait_html( $person->term_id, 'medium' );
		if ( ! $portrait ) {
			$initial  = function_exists( 'mb_substr' ) ? mb_substr( $person->name, 0, 1 ) : substr( $person->name, 0, 1 );
			$portrait = '<span class="fmem-portrait fmem-portrait-placeholder" aria-hidden="true">' . esc_html( $initial ) . '</span>';
		}
		$html .= '<a class="fmem-person-card" href="' . esc_url( $url ) . '">';
		$html .= $portrait;
		$html .= '<span class="fmem-person-name">' . esc_html( $person->name ) . '</span>';
		if ( $person->description ) {
			$html .= '<span class="fmem-person-summary">' . esc_html( wp_trim_words( wp_strip_all_tags( $person->description ), 20 ) ) . '</span>';
		}
		/* translators: %s: number of memories */
		$html .= '<span class="fmem-person-count">' . esc_html( sprintf( _n( '%s memory', '%s memories', $person->count, 'family-memories' ), number_format_i18n( $person->count ) ) ) . '</span>';
		$html .= '</a>';
	}
	$html .= '</div>';
	return $html;
}
add_shortcode( 'family_members', 'fmem_directory_shortcode' );
