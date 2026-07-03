<?php
/**
 * Plugin Name:       Media Cleaner Lite
 * Plugin URI:        https://github.com/mohammadkhodaei/media-cleaner-lite
 * Description:       افزونه سبک و پرکارایی برای شناسایی ایمن فایل‌های رسانه استفاده‌نشده، تحلیل ارجاعات در وردپرس و کمک به مدیران برای بازیابی فضای ذخیره‌سازی بدون خطر حذف تصادفی.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.0
 * Author:            Mohammad Khodaei
 * Author URI:        https://github.com/mohammadkhodaei
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       media-cleaner-lite
 * Domain Path:       /languages
 *
 * @package MediaCleanerLite
 */

declare(strict_types=1);

namespace MediaCleanerLite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MCL_VERSION' ) ) {
	return;
}

/**
 * Plugin version.
 */
define( 'MCL_VERSION', '1.0.0' );

/**
 * Absolute path to the main plugin file.
 */
define( 'MCL_FILE', __FILE__ );

/**
 * Filesystem path to the plugin directory (trailing slash).
 */
define( 'MCL_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory (trailing slash).
 */
define( 'MCL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Option key that stores all plugin settings.
 */
define( 'MCL_OPTION', 'mcl_settings' );

require_once MCL_PATH . 'includes/autoload.php';

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function mcl_bootstrap(): void {
	Plugin::instance()->run();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\mcl_bootstrap' );
