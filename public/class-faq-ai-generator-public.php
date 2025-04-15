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
	 * Display FAQs in the content if enabled
	 *
	 * @since    1.0.0
	 * @param    string    $content    The post content
	 * @return   string               The modified content
	 */
	public function display_faqs_in_content($content) {
		if (!is_single()) {
			return $content;
		}

		global $post;
		$faq_data = get_post_meta($post->ID, '_faq_ai_data', true);

		if (empty($faq_data) || empty($faq_data['faqs']) || !$faq_data['display_in_content']) {
			return $content;
		}

		$faq_html = '<div class="faq-ai-generator-container">';
		$faq_html .= '<h2>' . __('Domande Frequenti', 'faq-ai-generator') . '</h2>';
		$faq_html .= '<div class="faq-ai-generator-list">';

		foreach ($faq_data['faqs'] as $faq) {
			$faq_html .= '<div class="faq-item">';
			$faq_html .= '<div class="faq-question">' . esc_html($faq['question']) . '</div>';
			$faq_html .= '<div class="faq-answer">' . wpautop(esc_html($faq['answer'])) . '</div>';
			$faq_html .= '</div>';
		}

		$faq_html .= '</div></div>';

		return $content . $faq_html;
	}

}
