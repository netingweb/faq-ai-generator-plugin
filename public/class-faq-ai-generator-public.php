<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://lucamainieri.it
 * @since      1.0.0
 *
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/public
 * @author     Luca Mainieri <info@neting.it>
 */
class Faq_Ai_Generator_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Aggiungi l'hook per visualizzare le FAQ nel contenuto
		add_filter('the_content', array($this, 'display_faqs_in_content'));

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Faq_Ai_Generator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Faq_Ai_Generator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/faq-ai-generator-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Faq_Ai_Generator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Faq_Ai_Generator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/faq-ai-generator-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Visualizza le FAQ nel contenuto
	 *
	 * @since    1.0.0
	 * @param    string    $content    Il contenuto del post
	 * @return   string               Il contenuto modificato
	 */
	public function display_faqs_in_content($content) {
		// Verifica se siamo in una pagina singola
		if (!is_singular()) {
			return $content;
		}

		// Recupera il post type corrente
		$current_post_type = get_post_type();

		// Recupera le impostazioni
		$settings = get_option('faq_ai_generator_settings', array());
		$enabled_post_types = isset($settings['enabled_post_types']) ? $settings['enabled_post_types'] : array();

		// Verifica se il post type è abilitato
		$is_enabled = $current_post_type === 'post' || 
					 $current_post_type === 'page' || 
					 (isset($enabled_post_types[$current_post_type]) && $enabled_post_types[$current_post_type]);

		if (!$is_enabled) {
			return $content;
		}

		// Recupera i dati delle FAQ
		$post_id = get_the_ID();
		$faq_data = get_post_meta($post_id, '_faq_ai_data', true);

		if (empty($faq_data['faqs']) || !$faq_data['display_in_content']) {
			return $content;
		}

		// Genera l'HTML delle FAQ
		$faq_html = '<div class="faq-ai-generator-container">';
		$faq_html .= '<h2>' . __('Frequently Asked Questions', 'faq-ai-generator') . '</h2>';
		$faq_html .= '<div class="faq-ai-generator-list">';

		foreach ($faq_data['faqs'] as $faq) {
			$faq_html .= sprintf(
				'<div class="faq-item">
					<div class="faq-question">%s</div>
					<div class="faq-answer">%s</div>
				</div>',
				esc_html($faq['question']),
				wpautop(wp_kses_post($faq['answer']))
			);
		}

		$faq_html .= '</div></div>';

		// Aggiungi stili CSS inline per le FAQ
		$faq_html .= '
		<style>
		.faq-ai-generator-container {
			margin: 2em 0;
			padding: 1em;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.faq-ai-generator-container h2 {
			margin-bottom: 1em;
		}
		.faq-item {
			margin-bottom: 1em;
		}
		.faq-question {
			font-weight: bold;
			margin-bottom: 0.5em;
			color: #333;
			cursor: pointer;
			position: relative;
			padding-right: 20px;
		}
		.faq-question:after {
			content: "+";
			position: absolute;
			right: 0;
			top: 50%;
			transform: translateY(-50%);
		}
		.faq-question.active:after {
			content: "-";
		}
		.faq-answer {
			
			color: #666;
			display: none;
		}
		.faq-answer.active {
			display: block;
		}
		</style>';

		// Aggiungi JavaScript per il toggle delle FAQ
		$faq_html .= '
		<script>
		document.addEventListener("DOMContentLoaded", function() {
			document.querySelectorAll(".faq-question").forEach(function(question) {
				question.addEventListener("click", function() {
					this.classList.toggle("active");
					var answer = this.nextElementSibling;
					answer.classList.toggle("active");
				});
			});
		});
		</script>';

		return $content . $faq_html;
	}

	/**
	 * Aggiunge lo schema.org delle FAQ
	 *
	 * @since    1.0.0
	 */
	public function add_faq_schema() {
		if (!is_singular()) {
			return;
		}

		// Recupera il post type corrente
		$current_post_type = get_post_type();

		// Recupera le impostazioni
		$settings = get_option('faq_ai_generator_settings', array());
		$enabled_post_types = isset($settings['enabled_post_types']) ? $settings['enabled_post_types'] : array();

		// Verifica se il post type è abilitato
		$is_enabled = $current_post_type === 'post' || 
					 $current_post_type === 'page' || 
					 (isset($enabled_post_types[$current_post_type]) && $enabled_post_types[$current_post_type]);

		if (!$is_enabled) {
			return;
		}

		$post_id = get_the_ID();
		$faq_data = get_post_meta($post_id, '_faq_ai_data', true);

		if (empty($faq_data['faqs'])) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'FAQPage',
			'mainEntity' => array()
		);

		foreach ($faq_data['faqs'] as $faq) {
			$schema['mainEntity'][] = array(
				'@type' => 'Question',
				'name' => wp_strip_all_tags($faq['question']),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text' => wp_strip_all_tags($faq['answer'])
				)
			);
		}

		// Stampa lo schema JSON-LD con i tag script
		echo "\n<!-- FAQ AI Generator Schema -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
	}

}
