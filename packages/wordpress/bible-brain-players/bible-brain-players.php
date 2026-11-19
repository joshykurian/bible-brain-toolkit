<?php
/**
 * Plugin Name:       Bible Brain Players
 * Description:       Bible Reader, Audio Player and Video Player for WordPress, powered by the Bible Brain API (Faith Comes By Hearing). Visitors choose country, language and version. Built for audio-first, mobile-first Scripture engagement.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Shalom Design S2dio (SDS)
 * Author URI:        https://www.shalomdesigns2dio.com/
 * Plugin URI:        https://www.shalomdesigns2dio.com/bible-brain-toolkit/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bible-brain-players
 *
 * Developed by Shalom Design S2dio (SDS), with the help of AI tools. This is an unofficial community project and is not published or supported by Faith Comes By Hearing. Bible Brain is a project of Faith Comes By Hearing. You need your own free API key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BBPLAY_VERSION', '1.1.0' );
define( 'BBPLAY_FILE', __FILE__ );
define( 'BBPLAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBPLAY_URL', plugin_dir_url( __FILE__ ) );

require_once BBPLAY_DIR . 'includes/class-bbp-canon.php';
require_once BBPLAY_DIR . 'includes/class-bbp-settings.php';
require_once BBPLAY_DIR . 'includes/class-bbp-api.php';
require_once BBPLAY_DIR . 'includes/class-bbp-rest.php';
require_once BBPLAY_DIR . 'includes/class-bbp-shortcodes.php';
require_once BBPLAY_DIR . 'includes/class-bbp-admin.php';

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'bible-brain-players', false, dirname( plugin_basename( BBPLAY_FILE ) ) . '/languages' );
		BBPlay_Rest::init();
		BBPlay_Shortcodes::init();
		if ( is_admin() ) {
			BBPlay_Admin::init();
		}
	}
);

register_activation_hook(
	BBPLAY_FILE,
	function () {
		if ( false === get_option( BBPlay_Settings::OPT, false ) ) {
			$earlier = get_option( 'bbp_settings', false ); // name used by early test builds
			add_option( BBPlay_Settings::OPT, is_array( $earlier ) ? $earlier : BBPlay_Settings::defaults(), '', 'no' );
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( BBPLAY_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=bible-brain-players' ) ) . '">' . esc_html__( 'Settings', 'bible-brain-players' ) . '</a>' );
		return $links;
	}
);
