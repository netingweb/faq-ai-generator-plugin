<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://neting.it
 * @since             1.1.2
 * @package           Faq_Ai_Generator
 *
 * @wordpress-plugin
 * Plugin Name:       FAQ AI Generator
 * Plugin URI:        https://neting.it/faq-ai-generator
 * Description:       Genera automaticamente FAQ per i tuoi contenuti WordPress utilizzando l'intelligenza artificiale.
 * Version:           1.1.2
 * Author:            Neting
 * Author URI:        https://neting.it
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       faq-ai-generator
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'FAQ_AI_GENERATOR_VERSION', '1.1.2' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-faq-ai-generator-activator.php
 */
function activate_faq_ai_generator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-faq-ai-generator-activator.php';
	Faq_Ai_Generator_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-faq-ai-generator-deactivator.php
 */
function deactivate_faq_ai_generator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-faq-ai-generator-deactivator.php';
	Faq_Ai_Generator_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_faq_ai_generator' );
register_deactivation_hook( __FILE__, 'deactivate_faq_ai_generator' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-faq-ai-generator.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_faq_ai_generator() {

	$plugin = new Faq_Ai_Generator();
	$plugin->run();

}
run_faq_ai_generator();
