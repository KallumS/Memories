<?php
/**
 * Plugin Name:       Family Memories
 * Description:       A gentle archive of stories and memories about the people in your family. Anyone can share a memory (with photos if they like); nothing appears on the site until you approve it.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       family-memories
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FMEM_VERSION', '1.0.0' );
define( 'FMEM_FILE', __FILE__ );
define( 'FMEM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FMEM_URL', plugin_dir_url( __FILE__ ) );

// Internal names. Kept here so they are easy to find.
define( 'FMEM_POST_TYPE', 'family_memory' );
define( 'FMEM_TAXONOMY', 'family_member' );

require_once FMEM_DIR . 'includes/content-types.php';
require_once FMEM_DIR . 'includes/settings.php';
require_once FMEM_DIR . 'includes/submission.php';
require_once FMEM_DIR . 'includes/display.php';
require_once FMEM_DIR . 'includes/people.php';
require_once FMEM_DIR . 'includes/admin.php';
require_once FMEM_DIR . 'includes/emails.php';

/**
 * On activation, register the content types and refresh the site's
 * web addresses so pages like /memory/... and /person/... work straight away.
 */
function fmem_activate() {
	fmem_register_content_types();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'fmem_activate' );

function fmem_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fmem_deactivate' );

// Note: there is deliberately no uninstall routine. Removing the plugin
// never deletes anyone's memories or photos.
