<?php
/**
 * The public "Share a memory" form: [memory_form]
 *
 * Every memory sent in is saved as "Pending", so it stays hidden from the
 * site until an administrator approves (publishes) it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FMEM_MAX_PHOTOS          = 5;
const FMEM_MAX_STORY_LENGTH    = 20000;
const FMEM_MAX_SUBMISSIONS_HR  = 10;
const FMEM_MIN_SECONDS_TO_FILL = 3;

/** Holds errors and typed-in values when a submission needs correcting. */
$GLOBALS['fmem_form_state'] = array(
	'errors' => array(),
	'values' => array(),
);

function fmem_allowed_photo_types() {
	return array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
	);
}

/**
 * Tidy up the multi-file upload array into a simple list of files.
 */
function fmem_collect_uploaded_photos() {
	if ( empty( $_FILES['fmem_photos'] ) || ! is_array( $_FILES['fmem_photos']['name'] ) ) {
		return array();
	}
	$raw   = $_FILES['fmem_photos']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$files = array();
	foreach ( $raw['name'] as $i => $name ) {
		if ( UPLOAD_ERR_NO_FILE === (int) $raw['error'][ $i ] ) {
			continue;
		}
		$files[] = array(
			'name'     => $name,
			'type'     => $raw['type'][ $i ],
			'tmp_name' => $raw['tmp_name'][ $i ],
			'error'    => (int) $raw['error'][ $i ],
			'size'     => (int) $raw['size'][ $i ],
		);
	}
	return $files;
}

/**
 * Check the photos before anything is saved. Returns a list of problems.
 */
function fmem_validate_photos( $files ) {
	$errors = array();
	if ( count( $files ) > FMEM_MAX_PHOTOS ) {
		/* translators: %d: maximum number of photos */
		$errors[] = sprintf( __( 'Please choose no more than %d photos.', 'family-memories' ), FMEM_MAX_PHOTOS );
		return $errors;
	}
	$max = wp_max_upload_size();
	foreach ( $files as $file ) {
		$label = esc_html( $file['name'] );
		if ( in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || $file['size'] > $max ) {
			/* translators: 1: file name, 2: size limit such as "8 MB" */
			$errors[] = sprintf( __( '"%1$s" is too large. Each photo must be under %2$s.', 'family-memories' ), $label, size_format( $max ) );
			continue;
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			/* translators: %s: file name */
			$errors[] = sprintf( __( 'Sorry, "%s" did not upload properly. Please try again.', 'family-memories' ), $label );
			continue;
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], fmem_allowed_photo_types() );
		if ( empty( $check['type'] ) ) {
			/* translators: %s: file name */
			$errors[] = sprintf( __( '"%s" is not a photo we can use. Please choose a JPG, PNG, GIF or WebP image.', 'family-memories' ), $label );
		}
	}
	return $errors;
}

function fmem_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
}

/**
 * The address of the page the form sits on (used to come back after sending).
 */
function fmem_current_page_url() {
	$id = get_queried_object_id();
	if ( $id && is_singular() ) {
		return get_permalink( $id );
	}
	return home_url( '/' );
}

/**
 * Handle a submitted memory. Runs before the page is drawn.
 *
 * This is a public form for visitors who are not logged in, so there is no
 * login-based security token; spam is kept out with a hidden "honeypot" field,
 * a minimum time to fill the form in, and a limit on submissions per hour.
 * Nothing is published without an administrator approving it.
 */
function fmem_handle_submission() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	if ( empty( $_GET['fmem_post'] ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}

	$state =& $GLOBALS['fmem_form_state'];

	// If the upload was bigger than the server allows, PHP throws away the whole form.
	if ( empty( $_POST ) ) {
		$state['errors'][] = sprintf(
			/* translators: %s: size limit such as "8 MB" */
			__( 'Sorry, those photos were too large to send all at once (the limit is %s in total). Please try fewer or smaller photos.', 'family-memories' ),
			size_format( wp_max_upload_size() )
		);
		return;
	}

	if ( empty( $_POST['fmem_action'] ) || 'share_memory' !== $_POST['fmem_action'] ) {
		return;
	}

	$done_url = add_query_arg( 'memory_shared', '1', fmem_current_page_url() ) . '#memory-form';

	// Spam checks: bots fill in the hidden field, or submit impossibly fast.
	$honeypot = isset( $_POST['fmem_website'] ) ? trim( wp_unslash( $_POST['fmem_website'] ) ) : '';
	$started  = isset( $_POST['fmem_ts'] ) ? (int) $_POST['fmem_ts'] : 0;
	if ( '' !== $honeypot || ! $started || ( time() - $started ) < FMEM_MIN_SECONDS_TO_FILL ) {
		// Quietly pretend it worked, so bots learn nothing.
		wp_safe_redirect( $done_url );
		exit;
	}

	$values = array(
		'contributor_name'  => sanitize_text_field( wp_unslash( $_POST['fmem_name'] ?? '' ) ),
		'contributor_email' => sanitize_email( wp_unslash( $_POST['fmem_email'] ?? '' ) ),
		'relationship'      => sanitize_text_field( wp_unslash( $_POST['fmem_relationship'] ?? '' ) ),
		'title'             => sanitize_text_field( wp_unslash( $_POST['fmem_title'] ?? '' ) ),
		'story'             => trim( wp_kses( wp_unslash( $_POST['fmem_story'] ?? '' ), array() ) ),
		'when'              => sanitize_text_field( wp_unslash( $_POST['fmem_when'] ?? '' ) ),
		'where'             => sanitize_text_field( wp_unslash( $_POST['fmem_where'] ?? '' ) ),
		'people'            => array_filter( array_map( 'absint', (array) ( $_POST['fmem_people'] ?? array() ) ) ),
	);
	// phpcs:enable
	$state['values'] = $values;

	$errors = array();
	if ( '' === $values['contributor_name'] ) {
		$errors[] = __( 'Please tell us your name.', 'family-memories' );
	}
	if ( ! empty( $_POST['fmem_email'] ) && ! is_email( $values['contributor_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$errors[] = __( 'That email address doesn\'t look quite right. You can also leave it blank.', 'family-memories' );
	}
	if ( '' === $values['title'] ) {
		$errors[] = __( 'Please give your memory a short title.', 'family-memories' );
	}
	if ( '' === $values['story'] ) {
		$errors[] = __( 'Please write your memory in the box.', 'family-memories' );
	} elseif ( mb_strlen( $values['story'] ) > FMEM_MAX_STORY_LENGTH ) {
		$errors[] = __( 'Your memory is longer than we can take in one go. Please split it into two separate memories.', 'family-memories' );
	}

	$photos = fmem_collect_uploaded_photos();
	$errors = array_merge( $errors, fmem_validate_photos( $photos ) );

	$rate_key = 'fmem_rate_' . md5( fmem_client_ip() );
	$count    = (int) get_transient( $rate_key );
	if ( $count >= FMEM_MAX_SUBMISSIONS_HR ) {
		$errors[] = __( 'You\'ve shared a lot of memories in a short time, thank you! Please wait a little while before sending more.', 'family-memories' );
	}

	if ( $errors ) {
		$state['errors'] = $errors;
		return;
	}

	$post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => FMEM_POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => mb_substr( $values['title'], 0, 200 ),
				'post_content' => $values['story'],
				'post_author'  => get_current_user_id(),
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		$state['errors'][] = __( 'Sorry, something went wrong saving your memory. Please try again.', 'family-memories' );
		return;
	}

	foreach ( array( 'contributor_name', 'contributor_email', 'relationship', 'when', 'where' ) as $field ) {
		if ( '' !== $values[ $field ] ) {
			update_post_meta( $post_id, '_fmem_' . $field, wp_slash( $values[ $field ] ) );
		}
	}

	$people = array();
	foreach ( $values['people'] as $term_id ) {
		if ( get_term( $term_id, FMEM_TAXONOMY ) instanceof WP_Term ) {
			$people[] = $term_id;
		}
	}
	if ( $people ) {
		wp_set_object_terms( $post_id, $people, FMEM_TAXONOMY );
	}

	fmem_save_uploaded_photos( $post_id, $photos );

	set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

	do_action( 'fmem_memory_submitted', $post_id );

	wp_safe_redirect( $done_url );
	exit;
}
add_action( 'template_redirect', 'fmem_handle_submission' );

/**
 * Store the photos in the Media Library, attached to the memory.
 * The first photo becomes the memory's main picture.
 */
function fmem_save_uploaded_photos( $post_id, $photos ) {
	if ( ! $photos ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$ids = array();
	foreach ( $photos as $photo ) {
		$_FILES['fmem_single_photo'] = $photo;
		$attachment_id               = media_handle_upload(
			'fmem_single_photo',
			$post_id,
			array(),
			array(
				'test_form' => false,
				'mimes'     => fmem_allowed_photo_types(),
			)
		);
		if ( is_wp_error( $attachment_id ) ) {
			continue;
		}
		update_post_meta( $attachment_id, '_fmem_submitted_photo', 1 );
		$ids[] = $attachment_id;
	}
	unset( $_FILES['fmem_single_photo'] );

	if ( $ids ) {
		set_post_thumbnail( $post_id, $ids[0] );
		update_post_meta( $post_id, '_fmem_photo_ids', $ids );
	}
}

/**
 * [memory_form] shortcode. Optional: [memory_form person="dad"] to pre-tick a person.
 */
function fmem_form_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'person' => '' ), $atts, 'memory_form' );
	fmem_enqueue_styles();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_GET['memory_shared'] ) ) {
		$s = fmem_get_settings();
		return '<div id="memory-form" class="fmem-form fmem-thanks" role="status">'
			. '<p class="fmem-thanks-heading">' . esc_html__( 'Thank you', 'family-memories' ) . ' &#9829;</p>'
			. wpautop( esc_html( $s['thank_you'] ) )
			. '<p><a class="fmem-button" href="' . esc_url( remove_query_arg( 'memory_shared' ) ) . '#memory-form">' . esc_html__( 'Share another memory', 'family-memories' ) . '</a></p>'
			. '</div>';
	}

	$state  = $GLOBALS['fmem_form_state'];
	$v      = wp_parse_args(
		$state['values'],
		array(
			'contributor_name'  => '',
			'contributor_email' => '',
			'relationship'      => '',
			'title'             => '',
			'story'             => '',
			'when'              => '',
			'where'             => '',
			'people'            => array(),
		)
	);
	$people = get_terms(
		array(
			'taxonomy'   => FMEM_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	$people = is_wp_error( $people ) ? array() : $people;

	// Pre-tick a person from the shortcode, or from a "?about=name" link.
	if ( empty( $state['values'] ) ) {
		$preselect = $atts['person'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['about'] ) ) {
			$preselect = sanitize_title( wp_unslash( $_GET['about'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( 1 === count( $people ) ) {
			$v['people'] = array( $people[0]->term_id );
		} elseif ( $preselect ) {
			$term = get_term_by( 'slug', $preselect, FMEM_TAXONOMY );
			if ( $term ) {
				$v['people'] = array( $term->term_id );
			}
		}
	}

	$action = add_query_arg( 'fmem_post', '1', remove_query_arg( array( 'memory_shared', 'fmem_post' ) ) );

	ob_start();
	?>
	<form id="memory-form" class="fmem-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( $action ); ?>#memory-form">
		<?php if ( $state['errors'] ) : ?>
			<div class="fmem-errors" role="alert">
				<p><strong><?php esc_html_e( 'Just a couple of things to fix:', 'family-memories' ); ?></strong></p>
				<ul>
					<?php foreach ( $state['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<input type="hidden" name="fmem_action" value="share_memory">
		<input type="hidden" name="fmem_ts" value="<?php echo esc_attr( time() ); ?>">
		<div class="fmem-hp" aria-hidden="true">
			<label for="fmem-website"><?php esc_html_e( 'Leave this empty', 'family-memories' ); ?></label>
			<input type="text" id="fmem-website" name="fmem_website" value="" tabindex="-1" autocomplete="off">
		</div>

		<fieldset>
			<legend><?php esc_html_e( 'About you', 'family-memories' ); ?></legend>
			<p class="fmem-field">
				<label for="fmem-name"><?php esc_html_e( 'Your name', 'family-memories' ); ?> <span class="fmem-required">*</span></label>
				<input type="text" id="fmem-name" name="fmem_name" required maxlength="100" autocomplete="name" value="<?php echo esc_attr( $v['contributor_name'] ); ?>">
			</p>
			<p class="fmem-field">
				<label for="fmem-relationship"><?php esc_html_e( 'How do you know them?', 'family-memories' ); ?></label>
				<input type="text" id="fmem-relationship" name="fmem_relationship" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. his niece, an old school friend, a work colleague', 'family-memories' ); ?>" value="<?php echo esc_attr( $v['relationship'] ); ?>">
			</p>
			<p class="fmem-field">
				<label for="fmem-email"><?php esc_html_e( 'Your email', 'family-memories' ); ?> <span class="fmem-optional"><?php esc_html_e( '(optional)', 'family-memories' ); ?></span></label>
				<input type="email" id="fmem-email" name="fmem_email" maxlength="200" autocomplete="email" value="<?php echo esc_attr( $v['contributor_email'] ); ?>">
				<span class="fmem-hint"><?php esc_html_e( 'Only used to let you know when your memory is added. It is never shown on the site.', 'family-memories' ); ?></span>
			</p>
		</fieldset>

		<fieldset>
			<legend><?php esc_html_e( 'Your memory', 'family-memories' ); ?></legend>
			<?php if ( $people ) : ?>
				<div class="fmem-field">
					<span class="fmem-label"><?php esc_html_e( 'Who is this memory about?', 'family-memories' ); ?></span>
					<div class="fmem-people-choices">
						<?php foreach ( $people as $person ) : ?>
							<label class="fmem-person-choice">
								<input type="checkbox" name="fmem_people[]" value="<?php echo esc_attr( $person->term_id ); ?>" <?php checked( in_array( $person->term_id, $v['people'], true ) ); ?>>
								<?php echo esc_html( $person->name ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<p class="fmem-field">
				<label for="fmem-title"><?php esc_html_e( 'Give it a short title', 'family-memories' ); ?> <span class="fmem-required">*</span></label>
				<input type="text" id="fmem-title" name="fmem_title" required maxlength="200" placeholder="<?php esc_attr_e( 'e.g. The day we got lost in Cornwall', 'family-memories' ); ?>" value="<?php echo esc_attr( $v['title'] ); ?>">
			</p>
			<p class="fmem-field">
				<label for="fmem-story"><?php esc_html_e( 'Your memory or story', 'family-memories' ); ?> <span class="fmem-required">*</span></label>
				<textarea id="fmem-story" name="fmem_story" required rows="10" maxlength="<?php echo esc_attr( FMEM_MAX_STORY_LENGTH ); ?>"><?php echo esc_textarea( $v['story'] ); ?></textarea>
			</p>
			<div class="fmem-row">
				<p class="fmem-field">
					<label for="fmem-when"><?php esc_html_e( 'When was this?', 'family-memories' ); ?> <span class="fmem-optional"><?php esc_html_e( '(optional)', 'family-memories' ); ?></span></label>
					<input type="text" id="fmem-when" name="fmem_when" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Summer 1995, or Christmas 2010', 'family-memories' ); ?>" value="<?php echo esc_attr( $v['when'] ); ?>">
				</p>
				<p class="fmem-field">
					<label for="fmem-where"><?php esc_html_e( 'Where was this?', 'family-memories' ); ?> <span class="fmem-optional"><?php esc_html_e( '(optional)', 'family-memories' ); ?></span></label>
					<input type="text" id="fmem-where" name="fmem_where" maxlength="100" value="<?php echo esc_attr( $v['where'] ); ?>">
				</p>
			</div>
			<p class="fmem-field">
				<label for="fmem-photos"><?php esc_html_e( 'Photos', 'family-memories' ); ?> <span class="fmem-optional"><?php esc_html_e( '(optional)', 'family-memories' ); ?></span></label>
				<input type="file" id="fmem-photos" name="fmem_photos[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
				<span class="fmem-hint">
					<?php
					/* translators: 1: number of photos, 2: size limit such as "8 MB" */
					echo esc_html( sprintf( __( 'Up to %1$d photos, each under %2$s.', 'family-memories' ), FMEM_MAX_PHOTOS, size_format( wp_max_upload_size() ) ) );
					if ( $state['errors'] && $state['values'] ) {
						echo ' ' . esc_html__( 'If you chose photos before, please choose them again.', 'family-memories' );
					}
					?>
				</span>
			</p>
		</fieldset>

		<p class="fmem-note"><?php esc_html_e( 'Every memory is read before it appears on the site.', 'family-memories' ); ?></p>
		<p><button type="submit" class="fmem-button"><?php esc_html_e( 'Share this memory', 'family-memories' ); ?></button></p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'memory_form', 'fmem_form_shortcode' );
