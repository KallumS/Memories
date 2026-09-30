<?php
/**
 * Showing approved memories on the site:
 *  - [memory_wall]      a searchable grid of memories (optionally for one person)
 *  - single memory pages get the extra details (who shared it, when, more photos)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fmem_enqueue_styles() {
	wp_enqueue_style( 'family-memories', FMEM_URL . 'assets/css/memories.css', array(), FMEM_VERSION );
}

function fmem_maybe_enqueue_styles() {
	if ( is_singular( FMEM_POST_TYPE ) || is_tax( FMEM_TAXONOMY ) ) {
		fmem_enqueue_styles();
	}
}
add_action( 'wp_enqueue_scripts', 'fmem_maybe_enqueue_styles' );

/**
 * Find the page that holds the [memory_form], so we can link to it.
 */
function fmem_form_page_url( $person_slug = '' ) {
	$page_id = get_transient( 'fmem_form_page_id' );
	if ( false === $page_id ) {
		global $wpdb;
		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '[memory_form' ) . '%'
			)
		);
		set_transient( 'fmem_form_page_id', $page_id, DAY_IN_SECONDS );
	}
	if ( ! $page_id ) {
		return '';
	}
	$url = get_permalink( $page_id );
	return $person_slug ? add_query_arg( 'about', $person_slug, $url ) : $url;
}

function fmem_forget_form_page( $post_id, $post ) {
	if ( 'page' === $post->post_type ) {
		delete_transient( 'fmem_form_page_id' );
	}
}
add_action( 'save_post', 'fmem_forget_form_page', 10, 2 );

/**
 * "Shared by Jane, his niece" line.
 */
function fmem_byline( $post_id ) {
	$name = fmem_get_meta( $post_id, 'contributor_name' );
	if ( '' === $name ) {
		return '';
	}
	$relationship = fmem_get_meta( $post_id, 'relationship' );
	$text         = $relationship
		/* translators: 1: person's name, 2: how they know them */
		? sprintf( __( 'Shared by %1$s, %2$s', 'family-memories' ), $name, $relationship )
		/* translators: %s: person's name */
		: sprintf( __( 'Shared by %s', 'family-memories' ), $name );
	return esc_html( $text );
}

/**
 * "Summer 1995 · Cornwall"
 */
function fmem_when_where( $post_id ) {
	$parts = array_filter( array( fmem_get_meta( $post_id, 'when' ), fmem_get_meta( $post_id, 'where' ) ) );
	return esc_html( implode( ' · ', $parts ) );
}

function fmem_people_links( $post_id ) {
	$terms = get_the_terms( $post_id, FMEM_TAXONOMY );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$links = array();
	foreach ( $terms as $term ) {
		$links[] = '<a class="fmem-person-tag" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
	}
	return implode( ' ', $links );
}

/**
 * Add the memory's details around its story on its own page.
 */
function fmem_single_memory_content( $content ) {
	if ( ! is_singular( FMEM_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$post_id = get_the_ID();

	$before    = '';
	$when_where = fmem_when_where( $post_id );
	if ( $when_where ) {
		$before .= '<p class="fmem-when-where">' . $when_where . '</p>';
	}

	$after = '';

	// Any extra photos beyond the main one (which the theme shows at the top).
	$photo_ids = (array) get_post_meta( $post_id, '_fmem_photo_ids', true );
	$thumb_id  = (int) get_post_thumbnail_id( $post_id );
	$extra     = array_filter(
		array_map( 'intval', $photo_ids ),
		function ( $id ) use ( $thumb_id ) {
			return $id && $id !== $thumb_id;
		}
	);
	if ( $extra ) {
		$after .= '<div class="fmem-gallery">';
		foreach ( $extra as $id ) {
			$full = wp_get_attachment_image_url( $id, 'full' );
			if ( $full ) {
				$after .= '<a href="' . esc_url( $full ) . '">' . wp_get_attachment_image( $id, 'medium_large' ) . '</a>';
			}
		}
		$after .= '</div>';
	}

	$byline = fmem_byline( $post_id );
	$people = fmem_people_links( $post_id );
	if ( $byline || $people ) {
		$after .= '<div class="fmem-memory-footer">';
		if ( $byline ) {
			$after .= '<p class="fmem-byline">' . $byline . '</p>';
		}
		if ( $people ) {
			$after .= '<p class="fmem-about">' . esc_html__( 'About:', 'family-memories' ) . ' ' . $people . '</p>';
		}
		$after .= '</div>';
	}

	$form_url = fmem_form_page_url();
	if ( $form_url ) {
		$after .= '<p class="fmem-share-yours"><a class="fmem-button" href="' . esc_url( $form_url ) . '">' . esc_html__( 'Share a memory of your own', 'family-memories' ) . '</a></p>';
	}

	return '<div class="fmem-single">' . $before . $content . $after . '</div>';
}
add_filter( 'the_content', 'fmem_single_memory_content', 20 );

/**
 * One memory card, used in the wall grid.
 */
function fmem_render_card( $post_id ) {
	$thumb = get_the_post_thumbnail( $post_id, 'medium_large', array( 'class' => 'fmem-card-photo' ) );
	$url   = get_permalink( $post_id );
	$words = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 40 );

	$html  = '<article class="fmem-card' . ( $thumb ? ' has-photo' : '' ) . '">';
	if ( $thumb ) {
		$html .= '<a class="fmem-card-photo-link" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $thumb . '</a>';
	}
	$html .= '<div class="fmem-card-body">';
	$html .= '<h3 class="fmem-card-title"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></h3>';
	$when_where = fmem_when_where( $post_id );
	if ( $when_where ) {
		$html .= '<p class="fmem-when-where">' . $when_where . '</p>';
	}
	$html .= '<p class="fmem-card-excerpt">' . esc_html( $words ) . '</p>';
	$byline = fmem_byline( $post_id );
	if ( $byline ) {
		$html .= '<p class="fmem-byline">' . $byline . '</p>';
	}
	$people = fmem_people_links( $post_id );
	if ( $people ) {
		$html .= '<p class="fmem-about">' . $people . '</p>';
	}
	$html .= '<a class="fmem-read-more" href="' . esc_url( $url ) . '">' . esc_html__( 'Read the full memory', 'family-memories' ) . ' &rarr;</a>';
	$html .= '</div></article>';
	return $html;
}

/**
 * [memory_wall] shortcode.
 * Options: person="dad" (only show one person's memories), per_page="12", search="no".
 */
function fmem_wall_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'person'   => '',
			'per_page' => 12,
			'search'   => 'yes',
		),
		$atts,
		'memory_wall'
	);
	fmem_enqueue_styles();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$person = $atts['person'] ? sanitize_title( $atts['person'] ) : '';
	$locked = (bool) $person;
	if ( ! $locked && ! empty( $_GET['about'] ) ) {
		$person = sanitize_title( wp_unslash( $_GET['about'] ) );
	}
	$search = isset( $_GET['memory_search'] ) ? sanitize_text_field( wp_unslash( $_GET['memory_search'] ) ) : '';
	$page   = isset( $_GET['memories_page'] ) ? max( 1, absint( $_GET['memories_page'] ) ) : 1;
	// phpcs:enable

	$args = array(
		'post_type'      => FMEM_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, (int) $atts['per_page'] ),
		'paged'          => $page,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $person ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => FMEM_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $person,
			),
		);
	}
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	$query = new WP_Query( $args );

	ob_start();
	echo '<div class="fmem-wall">';

	if ( 'no' !== $atts['search'] ) {
		$people = $locked ? array() : get_terms(
			array(
				'taxonomy'   => FMEM_TAXONOMY,
				'hide_empty' => true,
			)
		);
		$base   = remove_query_arg( array( 'about', 'memory_search', 'memories_page' ) );
		?>
		<form class="fmem-filter" method="get" action="<?php echo esc_url( $base ); ?>" role="search">
			<label class="fmem-sr-only" for="fmem-search"><?php esc_html_e( 'Search memories', 'family-memories' ); ?></label>
			<input type="search" id="fmem-search" name="memory_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search memories…', 'family-memories' ); ?>">
			<?php if ( $people && ! is_wp_error( $people ) && count( $people ) > 1 ) : ?>
				<label class="fmem-sr-only" for="fmem-about"><?php esc_html_e( 'Choose a person', 'family-memories' ); ?></label>
				<select id="fmem-about" name="about">
					<option value=""><?php esc_html_e( 'Everyone', 'family-memories' ); ?></option>
					<?php foreach ( $people as $p ) : ?>
						<option value="<?php echo esc_attr( $p->slug ); ?>" <?php selected( $person, $p->slug ); ?>><?php echo esc_html( $p->name ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<button type="submit" class="fmem-button"><?php esc_html_e( 'Search', 'family-memories' ); ?></button>
			<?php if ( '' !== $search || ( $person && ! $locked ) ) : ?>
				<a class="fmem-clear" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Show all', 'family-memories' ); ?></a>
			<?php endif; ?>
		</form>
		<?php
	}

	if ( $query->have_posts() ) {
		echo '<div class="fmem-grid">';
		foreach ( $query->posts as $memory ) {
			echo fmem_render_card( $memory->ID ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
		}
		echo '</div>';

		if ( $query->max_num_pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => esc_url_raw( add_query_arg( 'memories_page', '%#%' ) ),
					'format'    => '',
					'current'   => $page,
					'total'     => $query->max_num_pages,
					'prev_text' => '&larr; ' . __( 'Newer', 'family-memories' ),
					'next_text' => __( 'Older', 'family-memories' ) . ' &rarr;',
				)
			);
			echo '<nav class="fmem-pagination" aria-label="' . esc_attr__( 'More memories', 'family-memories' ) . '">' . $links . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	} else {
		echo '<p class="fmem-empty">';
		if ( '' !== $search ) {
			esc_html_e( 'No memories matched your search.', 'family-memories' );
		} else {
			esc_html_e( 'No memories have been added yet.', 'family-memories' );
		}
		$form_url = '' === $search ? fmem_form_page_url( $person ) : '';
		if ( $form_url ) {
			echo ' <a href="' . esc_url( $form_url ) . '">' . esc_html__( 'Be the first to share one.', 'family-memories' ) . '</a>';
		}
		echo '</p>';
	}

	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'memory_wall', 'fmem_wall_shortcode' );

/**
 * Remove the "Person:" prefix WordPress puts on person page titles.
 */
function fmem_archive_title_prefix( $prefix ) {
	return is_tax( FMEM_TAXONOMY ) ? '' : $prefix;
}
add_filter( 'get_the_archive_title_prefix', 'fmem_archive_title_prefix' );

/**
 * Where a theme shows "by [author]", show the person who shared the memory
 * (memories sent in through the form have no WordPress user as their author).
 */
function fmem_contributor_as_author( $name ) {
	$post = get_post();
	if ( $post && FMEM_POST_TYPE === $post->post_type ) {
		$contributor = fmem_get_meta( $post->ID, 'contributor_name' );
		if ( '' !== $contributor ) {
			return $contributor;
		}
	}
	return $name;
}
add_filter( 'the_author', 'fmem_contributor_as_author' );

function fmem_contributor_author_block( $content, $parsed_block, $instance ) {
	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;
	if ( ! $post_id || FMEM_POST_TYPE !== get_post_type( $post_id ) ) {
		return $content;
	}
	$contributor = fmem_get_meta( $post_id, 'contributor_name' );
	if ( '' === $contributor ) {
		return $content;
	}
	return '<div class="wp-block-post-author-name">' . esc_html( $contributor ) . '</div>';
}
add_filter( 'render_block_core/post-author-name', 'fmem_contributor_author_block', 10, 3 );
