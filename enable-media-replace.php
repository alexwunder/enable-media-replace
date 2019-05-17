<?php
/*
Plugin Name: Enable Media Replace
Plugin URI: http://www.mansjonasson.se/enable-media-replace
Description: Enable replacing media files by uploading a new file in the "Edit Media" section of the WordPress Media Library.
Version: 3.2.9
Author: ShortPixel
Author URI: https://shortpixel.com

Dual licensed under the MIT and GPL licenses:
http://www.opensource.org/licenses/mit-license.php
http://www.gnu.org/licenses/gpl.html
*/

/**
 * Main Plugin file
 * Set action hooks and add shortcode
 *
 * @author      ShortPixel  <https://shortpixel.com>
 * @copyright   ShortPixel 2018
 * @package     wordpress
 * @subpackage  enable-media-replace
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if(!defined("S3_UPLOADS_AUTOENABLE")) {
    define('S3_UPLOADS_AUTOENABLE', true);
}

add_action('admin_init', 'enable_media_replace_init');
add_action('admin_menu', 'emr_menu');
add_filter('attachment_fields_to_edit', 'enable_media_replace', 10, 2);
add_filter('media_row_actions', 'add_media_action', 10, 2);
add_filter('upload_mimes', 'dat_mime_types', 1, 1);
add_action('admin_enqueue_scripts', 'emr_admin_scripts');

add_action('admin_notices', 'emr_display_notices');
add_action('network_admin_notices', 'emr_display_network_notices');
add_action('wp_ajax_emr_dismiss_notices', 'emr_dismiss_notices');

add_shortcode('file_modified', 'emr_get_modified_date');

if(!defined("SHORTPIXEL_AFFILIATE_CODE")) {
	define("SHORTPIXEL_AFFILIATE_CODE", 'VKG6LYN28044');
}

/**
 * @param array $mime_types
 * @return array
 */
function dat_mime_types($mime_types) {
    $mime_types['dat'] = 'text/plain';     // Adding .dat extension

    return $mime_types;
}

/**
 * Register this file in WordPress so we can call it with a ?page= GET var.
 * To suppress it in the menu we give it an empty menu title.
 */
function emr_menu() { //'upload.php'
	add_submenu_page(null, esc_html__("Replace media", "enable-media-replace"), esc_html__("Replace media", "enable-media-replace"), 'upload_files', 'enable-media-replace/enable-media-replace', 'emr_options');
}

/**
 * Initialize this plugin. Called by 'admin_init' hook.
 * Only languages files needs loading during init.
 */
function enable_media_replace_init() {
	load_plugin_textdomain( 'enable-media-replace', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Add some new fields to the attachment edit panel.
 * @param array form fields edit panel
 * @return array form fields with enable-media-replace fields added
 */
function enable_media_replace( $form_fields, $post ) {

	$url = admin_url( "upload.php?page=enable-media-replace/enable-media-replace.php&action=media_replace&attachment_id=" . $post->ID);
	$action = "media_replace";
  	$editurl = wp_nonce_url( $url, $action );

	if (FORCE_SSL_ADMIN) {
		$editurl = str_replace("http:", "https:", $editurl);
	}
	$link = "href=\"$editurl\"";
	$form_fields["enable-media-replace"] = array("label" => esc_html__("Replace media", "enable-media-replace"), "input" => "html", "html" => "<p><a class='button-secondary'$link>" . esc_html__("Upload a new file", "enable-media-replace") . "</a></p>", "helps" => esc_html__("To replace the current file, click the link and upload a replacement.", "enable-media-replace"));

	return $form_fields;
}

function emr_admin_scripts()
{
		wp_register_style('emr_style', plugins_url('css/admin.css', __FILE__) );
		wp_register_script('emr_admin', plugins_url('js/emr_admin.js', __FILE__), array('jquery'), false, true );
}

/**
 * Load the replace media panel.
 * Panel is show on the action 'media-replace' and a given attachement.
 * Called by GET var ?page=enable-media-replace/enable-media-replace.php
 */
function emr_options() {
	$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

	// prepare scripts etc for this page.
	wp_enqueue_style('emr_style');

	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('jquery-ui-datepicker');

	wp_localize_script('emr_admin', 'emr_options', array('dateFormat' => emr_convertdate(get_option( 'date_format' ))));
	wp_enqueue_script('emr_admin');

	if ( $action == 'media_replace' ) {
    	check_admin_referer( $action, '_wpnonce'); // die if invalid or missing nonce
		if ( array_key_exists("attachment_id", $_GET) && intval($_GET["attachment_id"]) > 0) {
			include("popup.php"); // warning variables like $action be overwritten here.
		}
	}
	elseif ( $action == 'media_replace_upload' ) {
		$plugin_url =  str_replace("enable-media-replace.php", "", __FILE__);
    	check_admin_referer( $action, '_wpnonce' ); // die if invalid or missing nonce
		require_once($plugin_url . "upload.php");
	}
}

/** Utility function for the Jquery UI Datepicker */
function emr_convertdate( $sFormat ) {
    switch( $sFormat ) {
        //Predefined WP date formats
        case 'F j, Y':
            return( 'MM dd, yy' );
            break;
        case 'Y/m/d':
            return( 'yy/mm/dd' );
            break;
        case 'm/d/Y':
            return( 'mm/dd/yy' );
            break;
        case 'd/m/Y':
				default:
            return( 'dd/mm/yy' );
        break;
    }
}

/**
 * Function called by filter 'media_row_actions'
 * Enables linking to EMR straight from the media library
*/
function add_media_action( $actions, $post) {
	$url = admin_url( "upload.php?page=enable-media-replace/enable-media-replace.php&action=media_replace&attachment_id=" . $post->ID);
	$action = "media_replace";
  	$editurl = wp_nonce_url( $url, $action );

	if (FORCE_SSL_ADMIN) {
		$editurl = str_replace("http:", "https:", $editurl);
	}
	$link = "href=\"$editurl\"";

	$newaction['adddata'] = '<a ' . $link . ' aria-label="' . esc_html__("Replace media", "enable-media-replace") . '" rel="permalink">' . esc_html__("Replace media", "enable-media-replace") . '</a>';
	return array_merge($actions,$newaction);
}

/**
 * Shorttag function to show the media file modification date/time.
 * @param array shorttag attributes
 * @return string content / replacement shorttag
 * @todo Note this returns the wrong date, ie. server date not corrected for timezone. Function could be removed altogether, not sure about purpose.
 */
function emr_get_modified_date($atts) {
	$id=0;
	$format= '';

	extract(shortcode_atts(array(
		'id' => '',
		'format' => get_option('date_format') . " " . get_option('time_format'),
	), $atts));

	if ($id == '') return false;

    // Get path to file
	$current_file = get_attached_file($id);

	if ( ! file_exists( $current_file ) ) {
		return false;
	}

	// Get file modification time
	$filetime = filemtime($current_file);

	if ( false !== $filetime ) {
		// do date conversion
		return date( $format, $filetime );
	}

	return false;
}

// Add Last replaced by EMR plugin in the media edit screen metabox - Thanks Jonas Lundman (http://wordpress.org/support/topic/add-filter-hook-suggestion-to)
function ua_admin_date_replaced_media_on_edit_media_screen($post) {
	if( !function_exists( 'enable_media_replace' ) ) return; // @todo seems this can go?

	$post_id = $post->ID;

	if ( $post->post_modified == $post->post_date ) {
		return;
	}

	$modified = date_i18n( __( 'M j, Y @ H:i' ) , strtotime( $post->post_modified ) );

	?>
	<div class="misc-pub-section curtime">
		<span id="timestamp"><?php echo esc_html__( 'Revised', 'enable-media-replace' ); ?>: <b><?php echo $modified; ?></b></span>
	</div>
	<?php
}
add_action( 'attachment_submitbox_misc_actions', 'ua_admin_date_replaced_media_on_edit_media_screen', 91 );

/*----------------------------------------------------------------------------------------------------------
	Display/dismiss admin notices if needed
-----------------------------------------------------------------------------------------------------------*/

function emr_display_notices() {
	$current_screen = get_current_screen();

	$crtScreen = function_exists("get_current_screen") ? get_current_screen() : (object)array("base" => false);

	if(current_user_can( 'activate_plugins' ) && !get_option( 'emr_news') && !is_plugin_active('shortpixel-image-optimiser/wp-shortpixel.php')
	   && ($crtScreen->base == "upload" || $crtScreen->base == "plugins")
        //for network installed plugins, don't display the message on subsites.
       && !(function_exists('is_multisite') && is_multisite() && is_plugin_active_for_network('enable-media-replace/enable-media-replace.php') && !is_main_site()))
	{
		require_once( str_replace("enable-media-replace.php", "notice.php", __FILE__) );
	}
}

function emr_display_network_notices() {
    if(current_user_can( 'activate_plugins' ) && !get_option( 'emr_news') && !is_plugin_active_for_network('shortpixel-image-optimiser/wp-shortpixel.php')) {
        require_once( str_replace("enable-media-replace.php", "notice.php", __FILE__) );
    }
}

function emr_dismiss_notices() {
	update_option( 'emr_news', true);
	exit(json_encode(array("Status" => 0)));
}
