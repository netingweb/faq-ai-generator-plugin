<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://lucamainieri.it
 * @since      1.0.0
 *
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/admin
 */

// Includi le classi necessarie
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-faq-ai-generator-api.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-faq-ai-generator-models.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/admin
 * @author     Luca Mainieri <info@neting.it>
 */
class Faq_Ai_Generator_Admin {

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
	public function __construct($plugin_name, $version) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Includi la classe Models
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-faq-ai-generator-models.php';

		// Aggiungi l'hook per l'azione AJAX
		add_action('wp_ajax_faq_ai_generate', array($this, 'ajax_generate_faqs'));
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url(__FILE__) . 'css/faq-ai-generator-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url(__FILE__) . 'js/faq-metabox.js',
			array('jquery'),
			$this->version,
			false
		);

		wp_localize_script($this->plugin_name, 'faqAiGenerator', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('faq_ai_nonce')
		));
	}

	/**
	 * Add menu page
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_options_page(
			__('FAQ AI Generator', 'faq-ai-generator'),
			__('FAQ AI Generator', 'faq-ai-generator'),
			'manage_options',
			$this->plugin_name,
			array($this, 'display_plugin_setup_page')
		);
	}

	/**
	 * Register settings
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting(
			'faq_ai_generator_settings',
			'faq_ai_generator_settings',
			array($this, 'validate_settings')
		);
	}

	/**
	 * Validate settings
	 *
	 * @since    1.0.0
	 * @param    array    $input    The settings input
	 * @return   array              The validated settings
	 */
	public function validate_settings($input) {
		$valid = array();
		$valid['api_key'] = sanitize_text_field($input['api_key']);
		$valid['ai_provider'] = sanitize_text_field($input['ai_provider']);
		$valid['model'] = sanitize_text_field($input['model']);
		$valid['extra_prompt'] = isset($input['extra_prompt']) ? sanitize_textarea_field($input['extra_prompt']) : '';
		$valid['custom_instructions'] = isset($input['custom_instructions']) ? sanitize_textarea_field($input['custom_instructions']) : '';

		// Salva le impostazioni dei custom post type
		$valid['enabled_post_types'] = array();
		if (isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])) {
			foreach ($input['enabled_post_types'] as $post_type => $enabled) {
				$valid['enabled_post_types'][$post_type] = (bool)$enabled;
			}
		}

		// Se la chiave API è stata modificata, verifichiamola
		if (!empty($valid['api_key']) && $valid['api_key'] !== get_option('faq_ai_generator_settings')['api_key']) {
			$api = new Faq_Ai_Generator_Api();
			$test_result = $api->test_api_key($valid['api_key']);

			if (is_wp_error($test_result)) {
				set_transient('faq_ai_api_error', $test_result->get_error_message(), 45);
				set_transient('faq_ai_api_status', 'invalid', 45);
				$valid['api_key'] = ''; // Resetta la chiave API se non valida
			} else {
				set_transient('faq_ai_api_status', 'valid', 45);
			}
		} elseif (empty($valid['api_key'])) {
			delete_transient('faq_ai_api_status');
		}

		return $valid;
	}

	/**
	 * Display plugin setup page
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_setup_page() {
		include_once 'partials/settings-page.php';
	}

	/**
	 * Add FAQ meta box
	 *
	 * @since    1.0.0
	 */
	public function add_faq_meta_box() {
		// Recupera le impostazioni
		$settings = get_option('faq_ai_generator_settings', array());
		$enabled_post_types = isset($settings['enabled_post_types']) ? $settings['enabled_post_types'] : array();

		// Post types di default (post e page)
		$post_types = array('post', 'page');

		// Aggiungi i custom post types abilitati
		foreach ($enabled_post_types as $post_type => $enabled) {
			if ($enabled) {
				$post_types[] = $post_type;
			}
		}

		add_meta_box(
			'faq_ai_generator_meta_box',
			__('FAQ AI Generator', 'faq-ai-generator'),
			array($this, 'display_faq_meta_box'),
			$post_types,
			'normal',
			'high'
		);
	}

	/**
	 * Display FAQ meta box
	 *
	 * @since    1.0.0
	 * @param    WP_Post    $post    The post object
	 */
	public function display_faq_meta_box($post) {
		$faq_data = get_post_meta($post->ID, '_faq_ai_data', true);
		$model_info = isset($faq_data['model_used']) ? $faq_data['model_used'] : null;

		if ($model_info) {
			echo '<div class="faq-model-info">';
			echo sprintf(
				__('FAQ generate con %s il %s alle %s', 'faq-ai-generator'),
				'<strong>' . esc_html($model_info['id']) . '</strong>',
				date_i18n(get_option('date_format'), $model_info['timestamp']),
				date('H:i', $model_info['timestamp'])
			);
			echo '</div>';
		}

		include_once 'partials/metabox-template.php';
	}

	/**
	 * AJAX handler for generating FAQs
	 *
	 * @since    1.0.0
	 */
	public function ajax_generate_faqs() {
		check_ajax_referer('faq_ai_nonce', 'nonce');

		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		if (!$post_id) {
			wp_send_json_error(array('message' => __('ID post non valido', 'faq-ai-generator')));
		}

		$post = get_post($post_id);
		if (!$post) {
			wp_send_json_error(array('message' => __('Post non trovato', 'faq-ai-generator')));
		}

		// Recupera le FAQ esistenti se richiesto
		$existing_faqs = array();
		if (isset($_POST['append']) && $_POST['append'] === 'true') {
			$faq_data = get_post_meta($post_id, '_faq_ai_data', true);
			if (!empty($faq_data['faqs'])) {
				$existing_faqs = $faq_data['faqs'];
			}
		}

		$settings = get_option('faq_ai_generator_settings');
		$model_id = $settings['model'] ?? Faq_Ai_Generator_Models::get_default_model();
		$model_details = Faq_Ai_Generator_Models::get_model_details($model_id);

		// Verifica se c'è stato un errore nel recupero dei dettagli del modello
		if (is_wp_error($model_details)) {
			wp_send_json_error(array('message' => $model_details->get_error_message()));
			return;
		}

		$api = new Faq_Ai_Generator_Api($model_id);
		$result = $api->generate_faqs($post->post_content, $existing_faqs);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
			return;
		}

		// Aggiungi informazioni sul modello
		$faq_data = array(
			'faqs' => $result,
			'model_used' => array(
				'id' => $model_id,
				'timestamp' => current_time('timestamp')
			),
			'display_in_content' => true
		);

		// Recupera le FAQ esistenti per mantenere il valore di display_in_content
		$existing_faq_data = get_post_meta($post_id, '_faq_ai_data', true);
		if ($existing_faq_data && isset($existing_faq_data['display_in_content'])) {
			$faq_data['display_in_content'] = $existing_faq_data['display_in_content'];
		}

		update_post_meta($post_id, '_faq_ai_data', $faq_data);

		wp_send_json_success(array('faqs' => $result));
	}

	/**
	 * AJAX handler for saving FAQs
	 *
	 * @since    1.0.0
	 */
	public function ajax_save_faqs() {
		check_ajax_referer('faq_ai_nonce', 'nonce');

		if (!current_user_can('edit_posts')) {
			wp_send_json_error(array('message' => __('Permessi insufficienti', 'faq-ai-generator')));
		}

		$post_id = intval($_POST['post_id']);
		$faqs = array();
		$display_in_content = isset($_POST['display_in_content']) ? (bool)$_POST['display_in_content'] : true;

		if (isset($_POST['faqs']) && is_array($_POST['faqs'])) {
			foreach ($_POST['faqs'] as $faq) {
				if (!empty($faq['question']) && !empty($faq['answer'])) {
					$faqs[] = array(
						'question' => sanitize_text_field($faq['question']),
						'answer' => wp_kses_post($faq['answer'])
					);
				}
			}
		}

		$faq_data = array(
			'last_generated' => current_time('timestamp'),
			'model_used' => get_option('faq_ai_generator_settings')['model'],
			'faqs' => $faqs,
			'display_in_content' => $display_in_content
		);

		$result = update_post_meta($post_id, '_faq_ai_data', $faq_data);

		if ($result === false) {
			wp_send_json_error(array('message' => __('Errore durante il salvataggio delle FAQ', 'faq-ai-generator')));
		}

		wp_send_json_success(array(
			'message' => __('FAQ salvate con successo', 'faq-ai-generator'),
			'faqs' => $faqs,
			'display_in_content' => $display_in_content
		));
	}

	/**
	 * Save FAQ data when post is saved
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID
	 */
	public function save_faq_data($post_id) {
		// Verifica nonce
		if (!isset($_POST['faq_ai_nonce']) || !wp_verify_nonce($_POST['faq_ai_nonce'], 'faq_ai_nonce')) {
			return;
		}

		// Verifica autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// Verifica permessi
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		// Prepara i dati delle FAQ
		$faqs = array();
		if (isset($_POST['faq_ai_questions']) && isset($_POST['faq_ai_answers'])) {
			foreach ($_POST['faq_ai_questions'] as $index => $question) {
				if (!empty($question) && !empty($_POST['faq_ai_answers'][$index])) {
					$faqs[] = array(
						'question' => wp_unslash(sanitize_text_field($question)),
						'answer' => wp_unslash(wp_kses_post($_POST['faq_ai_answers'][$index]))
					);
				}
			}
		}

		// Gestisci il checkbox
		$display_in_content = isset($_POST['faq_ai_display_in_content']) ? true : false;

		// Recupera i dati esistenti
		$existing_data = get_post_meta($post_id, '_faq_ai_data', true);
		if ($existing_data) {
			$faq_data = array_merge($existing_data, array(
				'faqs' => $faqs,
				'display_in_content' => $display_in_content
			));
		} else {
			$faq_data = array(
				'faqs' => $faqs,
				'display_in_content' => $display_in_content
			);
		}

		update_post_meta($post_id, '_faq_ai_data', $faq_data);
	}

	private function build_prompt($content, $existing_faqs = array()) {
		$settings = get_option('faq_ai_generator_settings');
		$custom_instructions = !empty($settings['custom_instructions']) ? $settings['custom_instructions'] : '';

		$prompt = "Sei un esperto nella creazione di FAQ.\n";
		$prompt .= "Le FAQ devono essere chiare, concise e rilevanti per il contenuto fornito.\n\n";
		
		// Aggiungi le istruzioni personalizzate se presenti
		if (!empty($custom_instructions)) {
			$prompt .= "ISTRUZIONI PERSONALIZZATE:\n";
			$prompt .= $custom_instructions . "\n\n";
		}

		$prompt .= "CONTENUTO DA ANALIZZARE PER GENERARE FAQ:\n";
		$prompt .= wp_strip_all_tags($content) . "\n\n";

		if (!empty($existing_faqs)) {
			$prompt .= "FAQ ESISTENTI (genera FAQ diverse da queste):\n";
			foreach ($existing_faqs as $faq) {
				$prompt .= "Q: " . $faq['question'] . "\n";
				$prompt .= "A: " . $faq['answer'] . "\n\n";
			}
		}

		$prompt .= "FORMATO RICHIESTO:\n";
		$prompt .= "Genera le FAQ in formato JSON con questa struttura:\n";
		$prompt .= '[{"question": "domanda1", "answer": "risposta1"}, {"question": "domanda2", "answer": "risposta2"}]';

		return $prompt;
	}

	public function generate_faqs($content, $existing_faqs = array()) {
		if (empty($this->api_key)) {
			return new WP_Error('no_api_key', __('Chiave API non configurata', 'faq-ai-generator'));
		}

		// Ottieni le impostazioni
		$settings = get_option('faq_ai_generator_settings');
		$model_id = $settings['model'] ?? Faq_Ai_Generator_Models::get_default_model();
		$model_details = Faq_Ai_Generator_Models::get_model_details($model_id);

		// Costruisci il prompt
		$prompt = $this->build_prompt($content, $existing_faqs);

		// Calcola la lunghezza approssimativa del prompt
		$prompt_tokens = mb_strlen($prompt) / 4; // Approssimazione grossolana
		$max_response_tokens = min(
			1000,
			$model_details['max_tokens'] - $prompt_tokens
		);

		$response = wp_remote_post($this->api_endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type' => 'application/json',
			),
			'body' => json_encode(array(
				'model' => $model_id,
				'messages' => array(
					array(
						'role' => 'system',
						'content' => 'Sei un esperto nella creazione di FAQ in italiano.'
					),
					array(
						'role' => 'user',
						'content' => $prompt
					)
				),
				'temperature' => 0.7,
				'max_tokens' => $max_response_tokens
			)),
			'timeout' => 30
		));

		// ... gestione della risposta ...
	}
}
