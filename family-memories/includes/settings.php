<?php
/**
 * A small settings page: Memories → Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fmem_get_settings() {
	$defaults = array(
		'notify_email'        => get_option( 'admin_email' ),
		'notify_contributors' => 1,
		'thank_you'           => __( 'Thank you for sharing your memory. It will appear on the site once it has been reviewed.', 'family-memories' ),
	);
	$saved    = get_option( 'fmem_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

function fmem_register_settings() {
	register_setting(
		'fmem_settings',
		'fmem_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'fmem_sanitize_settings',
		)
	);
}
add_action( 'admin_init', 'fmem_register_settings' );

function fmem_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$email = isset( $input['notify_email'] ) ? sanitize_email( $input['notify_email'] ) : '';
	return array(
		'notify_email'        => is_email( $email ) ? $email : get_option( 'admin_email' ),
		'notify_contributors' => empty( $input['notify_contributors'] ) ? 0 : 1,
		'thank_you'           => isset( $input['thank_you'] ) ? sanitize_textarea_field( $input['thank_you'] ) : '',
	);
}

function fmem_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=' . FMEM_POST_TYPE,
		__( 'Family Memories Settings', 'family-memories' ),
		__( 'Settings', 'family-memories' ),
		'manage_options',
		'fmem-settings',
		'fmem_render_settings_page'
	);
}
add_action( 'admin_menu', 'fmem_settings_menu' );

function fmem_render_settings_page() {
	$s = fmem_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Family Memories Settings', 'family-memories' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'fmem_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="fmem-notify-email"><?php esc_html_e( 'Email me new memories at', 'family-memories' ); ?></label></th>
					<td>
						<input type="email" class="regular-text" id="fmem-notify-email" name="fmem_settings[notify_email]" value="<?php echo esc_attr( $s['notify_email'] ); ?>">
						<p class="description"><?php esc_html_e( 'Whenever someone shares a memory, an email goes here so you know there is something to review.', 'family-memories' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Let people know', 'family-memories' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="fmem_settings[notify_contributors]" value="1" <?php checked( $s['notify_contributors'], 1 ); ?>>
							<?php esc_html_e( 'Email the person who shared a memory when it has been approved (if they gave an email address).', 'family-memories' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fmem-thank-you"><?php esc_html_e( 'Thank-you message', 'family-memories' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" id="fmem-thank-you" name="fmem_settings[thank_you]"><?php echo esc_textarea( $s['thank_you'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Shown after someone sends in a memory.', 'family-memories' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
