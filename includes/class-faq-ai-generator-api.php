<?php

/**
 * The API handler class for FAQ AI Generator
 *
 * @since      1.0.0
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/includes
 */

// Definizione della costante di debug
if (!defined('FAQ_AI_DEBUG')) {
    $settings = get_option('faq_ai_generator_settings', array());
    define('FAQ_AI_DEBUG', isset($settings['debug_mode']) ? (bool)$settings['debug_mode'] : false);
}

class Faq_Ai_Generator_Api {

    /**
     * The OpenAI API endpoint
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    /**
     * The API key
     *
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $api_key;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->api_key = get_option('faq_ai_generator_settings')['api_key'] ?? '';
    }

    /**
     * Test the API key
     *
     * @since    1.0.0
     * @param    string    $api_key    The API key to test
     * @return   bool|WP_Error        True if valid, WP_Error if invalid
     */
    public function test_api_key($api_key = null) {
        if ($api_key) {
            $this->api_key = $api_key;
        }

        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'faq-ai-generator'));
        }

        $response = wp_remote_post($this->api_endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Test'
                    ]
                ],
                'max_tokens' => 5
            ]),
            'timeout' => 60
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        return true;
    }

    /**
     * Genera il prompt base per l'AI
     * 
     * @since    1.0.0
     * @access   private
     * @return   string    Il prompt base
     */
    private function get_base_prompt() {
        $locale = get_locale();
        
        if ($locale === 'en_US') {
            return "You are an SEO expert and professional copywriter with solid SEO skills. " .
                   "Your task is to enhance existing articles and content on the site with in-depth questions " .
                   "related to the page content. Your task is to read and understand the content and generate " .
                   "a list of questions and answers (FAQ) that are useful for site visitors and provide information " .
                   "related to the page content and that are based on the provided content.\n\n" .
                   "IMPORTANT: The response MUST be in JSON format with this structure:\n" .
                   '[{"question": "question1", "answer": "answer1"}, {"question": "question2", "answer": "answer2"}]\n\n';
        } else {
            return "Sei un esperto di SEO e un copywriting professionista con solide competenze di SEO. " .
                   "Il tuo compito è integrare degli articoli e contenuti esistenti sul sito con domande di approfondimento " .
                   "inerenti al contenuto della pagina. Il tuo compito è leggere e comprendere il contenuto e generare " .
                   "un elenco di domande e risposte(FAQ) che siano utili per i visitatori del sito e che forniscano informazioni ".
                   "relative al contenuto della pagina e che siano basate sul contenuto fornito.\n\n" .
                   "IMPORTANTE: La risposta DEVE essere in formato JSON con questa struttura:\n" .
                   '[{"question": "domanda1", "answer": "risposta1"}, {"question": "domanda2", "answer": "risposta2"}]\n\n';
        }
    }

    /**
     * Recupera il contenuto della pagina tramite CURL
     *
     * @since    1.0.0
     * @param    int       $post_id    ID della pagina
     * @return   string               Contenuto della pagina
     */
    private function get_page_content_via_curl($post_id) {
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Starting content extraction for post ID: ' . $post_id);
        }

        // Ottieni l'URL della pagina
        $page_url = get_permalink($post_id);
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Page URL: ' . $page_url);
        }
        
        // Esegui la richiesta usando wp_remote_get
        $response = wp_remote_get($page_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ));

        // Verifica se ci sono errori
        if (is_wp_error($response)) {
            error_log('FAQ AI Generator - WP Remote Error: ' . $response->get_error_message());
            return '';
        }

        // Ottieni il contenuto HTML
        $html = wp_remote_retrieve_body($response);
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - HTML content length: ' . strlen($html));
        }
        
        // Estrai il contenuto principale
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        // Rimuovi script e stili
        $scripts = $dom->getElementsByTagName('script');
        $styles = $dom->getElementsByTagName('style');
        
        foreach ($scripts as $script) {
            $script->parentNode->removeChild($script);
        }
        
        foreach ($styles as $style) {
            $style->parentNode->removeChild($style);
        }
        
        // Cerca il contenuto principale nel tag main
        $content = '';
        $main = $dom->getElementsByTagName('main')->item(0);
        
        if ($main) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - Found main tag content');
            }
            // Converti il contenuto in markdown
            $content = $this->html_to_markdown($main);
        } else {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - Main tag not found, trying fallback selectors');
            }
            // Fallback: cerca altri contenitori comuni
            $selectors = array(
                'article',
                '#main-content',
                '#content',
                '.main-content',
                '.content',
                '.entry-content',
                'section'
            );
            
            foreach ($selectors as $selector) {
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - Trying selector: ' . $selector);
                }
                if (strpos($selector, '#') === 0) {
                    $element = $dom->getElementById(substr($selector, 1));
                } else if (strpos($selector, '.') === 0) {
                    $elements = $dom->getElementsByTagName('*');
                    foreach ($elements as $element) {
                        if (strpos($element->getAttribute('class'), substr($selector, 1)) !== false) {
                            $content = $this->html_to_markdown($element);
                            if (FAQ_AI_DEBUG) {
                                error_log('FAQ AI Generator - Found content in class: ' . $selector);
                            }
                            break 2;
                        }
                    }
                } else {
                    $element = $dom->getElementsByTagName($selector)->item(0);
                }
                
                if ($element) {
                    $content = $this->html_to_markdown($element);
                    if (FAQ_AI_DEBUG) {
                        error_log('FAQ AI Generator - Found content in tag: ' . $selector);
                    }
                    break;
                }
            }
        }

        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - HTML content length after extraction: ' . strlen($content));
        }

        // Recupera i dati ACF in modo flessibile
        $acf_content = '';
        if (function_exists('get_fields')) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - ACF plugin is active, attempting to get fields');
            }
            
            $fields = get_fields($post_id);
            if ($fields) {
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - ACF Fields found: ' . count($fields));
                    error_log('FAQ AI Generator - ACF Fields: ' . print_r(array_keys($fields), true));
                    error_log('FAQ AI Generator - ACF Fields Raw Data: ' . print_r($fields, true));
                }
                
                // Funzione ricorsiva per estrarre il contenuto dai campi ACF
                $extract_acf_content = function($field_value, $field_name = '', $field_type = '') use (&$extract_acf_content) {
                    $content = '';
                    
                    if (is_string($field_value)) {
                        // Rimuovi HTML e pulisci il testo
                        $clean_text = wp_strip_all_tags($field_value);
                        if (!empty($clean_text)) {
                            $content .= $clean_text . "\n";
                            if (FAQ_AI_DEBUG) {
                                error_log("FAQ AI Generator - ACF Field '{$field_name}' ({$field_type}): Text content found (" . strlen($clean_text) . " chars)");
                            }
                        }
                    } elseif (is_array($field_value)) {
                        // Gestione specifica per diversi tipi di campi ACF
                        switch ($field_type) {
                            case 'repeater':
                                foreach ($field_value as $index => $row) {
                                    if (is_array($row)) {
                                        foreach ($row as $sub_field_name => $sub_field_value) {
                                            $sub_field_content = $extract_acf_content($sub_field_value, "{$field_name}[{$index}].{$sub_field_name}");
                                            if (!empty($sub_field_content)) {
                                                $content .= $sub_field_content;
                                            }
                                        }
                                    }
                                }
                                break;
                                
                            case 'group':
                                foreach ($field_value as $sub_field_name => $sub_field_value) {
                                    $sub_field_content = $extract_acf_content($sub_field_value, "{$field_name}.{$sub_field_name}");
                                    if (!empty($sub_field_content)) {
                                        $content .= $sub_field_content;
                                    }
                                }
                                break;
                                
                            case 'flexible_content':
                                foreach ($field_value as $index => $layout) {
                                    if (isset($layout['acf_fc_layout'])) {
                                        $layout_name = $layout['acf_fc_layout'];
                                        foreach ($layout as $sub_field_name => $sub_field_value) {
                                            if ($sub_field_name !== 'acf_fc_layout') {
                                                $sub_field_content = $extract_acf_content($sub_field_value, "{$field_name}[{$index}].{$layout_name}.{$sub_field_name}");
                                                if (!empty($sub_field_content)) {
                                                    $content .= $sub_field_content;
                                                }
                                            }
                                        }
                                    }
                                }
                                break;
                                
                            default:
                                foreach ($field_value as $key => $value) {
                                    if (is_string($value)) {
                                        $clean_text = wp_strip_all_tags($value);
                                        if (!empty($clean_text)) {
                                            $content .= $clean_text . "\n";
                                            if (FAQ_AI_DEBUG) {
                                                error_log("FAQ AI Generator - ACF Field '{$field_name}[{$key}]': Text content found (" . strlen($clean_text) . " chars)");
                                            }
                                        }
                                    } elseif (is_array($value)) {
                                        $nested_content = $extract_acf_content($value, "{$field_name}[{$key}]");
                                        if (!empty($nested_content)) {
                                            $content .= $nested_content;
                                        }
                                    }
                                }
                        }
                    }
                    
                    return $content;
                };
                
                // Estrai il contenuto da tutti i campi ACF
                foreach ($fields as $field_name => $field_value) {
                    // Ottieni il tipo di campo ACF
                    $field_type = '';
                    if (function_exists('get_field_object')) {
                        $field_object = get_field_object($field_name, $post_id);
                        if ($field_object && isset($field_object['type'])) {
                            $field_type = $field_object['type'];
                        }
                    }
                    
                    $field_content = $extract_acf_content($field_value, $field_name, $field_type);
                    if (!empty($field_content)) {
                        $acf_content .= $field_content;
                    }
                }
            } else {
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - No ACF fields found for post ID: ' . $post_id);
                }
            }
        } else {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - ACF plugin not active');
            }
        }
        
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - ACF content length: ' . strlen($acf_content));
        }
        
        // Combina il contenuto HTML con i dati ACF
        if (!empty($acf_content)) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - Adding ACF content to main content');
            }
            $content .= "\n\n" . $acf_content;
        }
        
        // Pulisci il contenuto
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Final content length: ' . strlen($content));
            if (empty($content)) {
                error_log('FAQ AI Generator - WARNING: Final content is empty!');
            }
        }
        
        return $content;
    }

    /**
     * Converte HTML in Markdown
     *
     * @since    1.0.0
     * @param    DOMElement    $element    Elemento DOM da convertire
     * @return   string                   Contenuto in formato Markdown
     */
    private function html_to_markdown($element) {
        $markdown = '';
        
        // Funzione ricorsiva per convertire i nodi
        $convert_node = function($node) use (&$convert_node, &$markdown) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = trim($node->textContent);
                if (!empty($text)) {
                    $markdown .= $text . ' ';
                }
                return;
            }
            
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($node->nodeName);
                
                // Gestisci i tag principali
                switch ($tag) {
                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                        $level = substr($tag, 1);
                        $markdown .= "\n\n" . str_repeat('#', $level) . ' ';
                        break;
                        
                    case 'p':
                        $markdown .= "\n\n";
                        break;
                        
                    case 'br':
                        $markdown .= "\n";
                        break;
                        
                    case 'strong':
                    case 'b':
                        $markdown .= '**';
                        break;
                        
                    case 'em':
                    case 'i':
                        $markdown .= '*';
                        break;
                        
                    case 'ul':
                        $markdown .= "\n\n";
                        break;
                        
                    case 'ol':
                        $markdown .= "\n\n";
                        break;
                        
                    case 'li':
                        $markdown .= "\n- ";
                        break;
                        
                    case 'blockquote':
                        $markdown .= "\n\n> ";
                        break;
                        
                    case 'code':
                        $markdown .= '`';
                        break;
                        
                    case 'pre':
                        $markdown .= "\n\n```\n";
                        break;
                }
                
                // Processa i nodi figli
                foreach ($node->childNodes as $child) {
                    $convert_node($child);
                }
                
                // Chiudi i tag
                switch ($tag) {
                    case 'strong':
                    case 'b':
                        $markdown .= '**';
                        break;
                        
                    case 'em':
                    case 'i':
                        $markdown .= '*';
                        break;
                        
                    case 'code':
                        $markdown .= '`';
                        break;
                        
                    case 'pre':
                        $markdown .= "\n```\n";
                        break;
                        
                    case 'p':
                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                    case 'ul':
                    case 'ol':
                    case 'blockquote':
                        $markdown .= "\n";
                        break;
                }
            }
        };
        
        // Converti l'elemento
        $convert_node($element);
        
        // Pulisci il markdown
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        $markdown = preg_replace('/\s+/', ' ', $markdown);
        $markdown = trim($markdown);
        
        return $markdown;
    }

    /**
     * Costruisce il prompt completo combinando il prompt base, extra prompt e contenuto
     * 
     * @since    1.0.0
     * @access   private
     * @param    string    $content         Il contenuto dell'articolo
     * @param    array     $existing_faqs   FAQ esistenti
     * @return   string                     Il prompt completo
     */
    private function build_prompt($content, $existing_faqs = array()) {
        $locale = get_locale();
        
        // 1. Inizia con il prompt base
        $prompt = $this->get_base_prompt();
        
        // 2. Aggiungi eventuali istruzioni extra dalle impostazioni
        $settings = get_option('faq_ai_generator_settings');
        if (!empty($settings['extra_prompt'])) {
            $prompt .= ($locale === 'en_US' ? "EXTRA INSTRUCTIONS:\n" : "ISTRUZIONI EXTRA:\n") . 
                      trim($settings['extra_prompt']) . "\n\n";
        }
        
        // 3. Aggiungi le FAQ esistenti se presenti
        if (!empty($existing_faqs)) {
            $prompt .= ($locale === 'en_US' ? "EXISTING FAQS (generate different FAQs from these):\n" : "FAQ GIÀ ESISTENTI (genera FAQ diverse da queste):\n");
            foreach ($existing_faqs as $index => $faq) {
                $prompt .= sprintf("%d. Q: %s\nA: %s\n\n", 
                    $index + 1, 
                    html_entity_decode(wp_strip_all_tags($faq['question']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    html_entity_decode(wp_strip_all_tags($faq['answer']), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                );
            }
        }
        
        // 4. Aggiungi il contenuto dell'articolo
        $prompt .= ($locale === 'en_US' ? "CONTENT TO ANALYZE:\n\n" : "CONTENUTO DA ANALIZZARE:\n\n") . 
                  html_entity_decode(wp_strip_all_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 5. Assicurati che il prompt sia in UTF-8 pulito
        $prompt = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');
        
        return $prompt;
    }

    /**
     * Generate FAQs from post content
     *
     * @since    1.0.0
     * @param    string    $content        The post content
     * @param    array     $existing_faqs  Existing FAQs to consider
     * @return   array|WP_Error           Generated FAQs or error
     */
    public function generate_faqs($content, $existing_faqs = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key not configured', 'faq-ai-generator'));
        }

        if (empty($content)) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - Content is empty, attempting to get content from post');
            }
            
            // Prova diversi metodi per ottenere l'ID del post
            $post_id = null;
            
            // 1. Controlla se siamo nella pagina di modifica del post
            if (is_admin() && isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
                $post_id = intval($_GET['post']);
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - Edit page context, post ID: ' . $post_id);
                    error_log('FAQ AI Generator - Current URL: ' . $_SERVER['REQUEST_URI']);
                }
            }
            
            // 2. Controlla se siamo in un contesto AJAX
            if (!$post_id && wp_doing_ajax() && isset($_POST['post_id'])) {
                $post_id = intval($_POST['post_id']);
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - AJAX context, post ID from POST: ' . $post_id);
                }
            }
            
            // 3. Prova get_the_ID()
            if (!$post_id) {
                $post_id = get_the_ID();
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - get_the_ID() result: ' . ($post_id ? $post_id : 'false'));
                }
            }
            
            // 4. Se non funziona, prova a ottenere l'ID dalla query globale
            if (!$post_id && isset($GLOBALS['wp_query']->post)) {
                $post_id = $GLOBALS['wp_query']->post->ID;
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - Global query post ID: ' . ($post_id ? $post_id : 'false'));
                }
            }
            
            if ($post_id) {
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - Post ID found: ' . $post_id);
                    error_log('FAQ AI Generator - Post type: ' . get_post_type($post_id));
                    error_log('FAQ AI Generator - Current context: ' . (wp_doing_ajax() ? 'AJAX' : (is_admin() ? 'Admin' : 'Frontend')));
                }
                
                // Verifica che il post esista e sia del tipo corretto
                $post = get_post($post_id);
                if (!$post) {
                    if (FAQ_AI_DEBUG) {
                        error_log('FAQ AI Generator - Post not found: ' . $post_id);
                    }
                    return new WP_Error('invalid_post', __('Invalid post ID. The post does not exist.', 'faq-ai-generator'));
                }
                
                // Verifica che il post sia pubblicato o in bozza
                if ($post->post_status !== 'publish' && $post->post_status !== 'draft') {
                    if (FAQ_AI_DEBUG) {
                        error_log('FAQ AI Generator - Post status not valid: ' . $post->post_status);
                    }
                    return new WP_Error('invalid_status', __('The post must be published or in draft status.', 'faq-ai-generator'));
                }
                
                $content = $this->get_page_content_via_curl($post_id);
                if (empty($content)) {
                    if (FAQ_AI_DEBUG) {
                        error_log('FAQ AI Generator - No content retrieved from post');
                    }
                    return new WP_Error('no_content', __('No content found to generate FAQs', 'faq-ai-generator'));
                }
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - Content retrieved from post, length: ' . strlen($content));
                }
            } else {
                if (FAQ_AI_DEBUG) {
                    error_log('FAQ AI Generator - No post ID found in any context');
                    error_log('FAQ AI Generator - Current context: ' . (wp_doing_ajax() ? 'AJAX' : (is_admin() ? 'Admin' : 'Frontend')));
                }
                return new WP_Error('no_post', __('No post ID found. Please make sure you are on a post or page, or provide a valid post ID.', 'faq-ai-generator'));
            }
        } else {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - Using provided content, length: ' . strlen($content));
            }
        }

        // Recupera le impostazioni
        $settings = get_option('faq_ai_generator_settings');
        $model = !empty($settings['model']) ? $settings['model'] : 'gpt-3.5-turbo';

        // Genera il prompt completo
        $prompt = $this->build_prompt($content, $existing_faqs);
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Built prompt, length: ' . strlen($prompt));
            error_log('FAQ AI Generator - Prompt content: ' . substr($prompt, 0, 500) . '...');
        }

        // Prepara i dati per la chiamata API
        $request_data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Sei un assistente esperto in SEO e creazione di FAQ.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 4000
        );

        // Modifica dei log per usare la costante di debug
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Request Parameters:');
            error_log('Model: ' . $model);
            error_log('Prompt Length: ' . strlen($prompt));
            error_log('Existing FAQs Count: ' . count($existing_faqs));
            error_log('Request Data: ' . json_encode($request_data, JSON_PRETTY_PRINT));
        }

        // Chiamata API
        $start_time = microtime(true);
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data),
            'timeout' => 60
        ));
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // in millisecondi

        // Log del tempo di esecuzione solo in debug
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - API Call Execution Time: ' . $execution_time . 'ms');
        }

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (strpos($error_message, 'Operation timed out') !== false) {
                return new WP_Error('timeout', __('La richiesta ha impiegato troppo tempo. Per favore riprova più tardi.', 'faq-ai-generator'));
            }
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - API Error: ' . $error_message);
                error_log('FAQ AI Generator - Error Code: ' . $response->get_error_code());
            }
            return $response;
        }

        // Log della risposta solo in debug
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Response Body: ' . $response_body);
        }

        $body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - JSON Error: ' . json_last_error_msg());
            }
            return new WP_Error('json_error', __('Error decoding API response', 'faq-ai-generator'));
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - Invalid Response Structure: ' . print_r($body, true));
            }
            return new WP_Error('invalid_response', __('Invalid API response structure', 'faq-ai-generator'));
        }

        $content = $body['choices'][0]['message']['content'];
        
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Raw Content: ' . $content);
        }
        
        // Rimuovi eventuali caratteri di escape e newline
        $content = str_replace(['\n', '\r'], '', $content);
        
        // Pulisci la risposta rimuovendo i backtick e il tag json
        $content = preg_replace('/^```json\s*|\s*```$/', '', $content);
        $content = trim($content);
        
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Cleaned Content: ' . $content);
        }
        
        // Prova a decodificare come JSON
        $faqs = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - JSON Error: ' . json_last_error_msg());
                error_log('FAQ AI Generator - Failed to parse content as JSON: ' . $content);
            }
            return new WP_Error('json_error', __('Error decoding FAQ JSON: ' . json_last_error_msg(), 'faq-ai-generator'));
        }

        if (!is_array($faqs) || empty($faqs)) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - No valid FAQs found in response');
            }
            return new WP_Error('no_faqs', __('No valid FAQs found in response', 'faq-ai-generator'));
        }

        return $faqs;
    }

    /**
     * Get the prompt for the AI
     *
     * @since    1.0.0
     * @param    string    $content    The post content
     * @return   string               The formatted prompt
     */
    private function get_prompt($content) {
        return sprintf(
            "Genera 5-7 FAQ pertinenti per il seguente contenuto. Formatta ogni FAQ come 'Q: domanda\nA: risposta'. Il contenuto è:\n\n%s",
            $content
        );
    }

    /**
     * Parse the API response and format it as FAQ items
     *
     * @since    1.0.0
     * @param    array    $response    The API response
     * @return   array                Formatted FAQ items
     */
    private function parse_response($response) {
        if (empty($response['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', __('Invalid API response', 'faq-ai-generator'));
        }

        $content = $response['choices'][0]['message']['content'];
        
        // Log del contenuto per debug
        if (FAQ_AI_DEBUG) {
            error_log('FAQ AI Generator - Raw Content: ' . $content);
        }
        
        // Rimuovi eventuali caratteri di escape e newline
        $content = str_replace(['\n', '\r'], '', $content);
        
        // Prova a decodificare come JSON
        $decoded = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Verifica che ogni elemento abbia question e answer
            $valid_faqs = array_filter($decoded, function($faq) {
                return isset($faq['question']) && isset($faq['answer']) && 
                       !empty($faq['question']) && !empty($faq['answer']);
            });
            
            if (!empty($valid_faqs)) {
                return array_values($valid_faqs);
            }
        }
        
        // Se non è JSON valido o non contiene FAQ valide, prova a estrarre FAQ dal testo
        $faqs = array();
        $lines = explode("\n", $content);
        
        $current_question = '';
        $current_answer = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Se la riga inizia con "Q:" o "Domanda:", è una nuova domanda
            if (preg_match('/^(Q:|Domanda:|Q\.|Domanda\.)\s*(.+)$/i', $line, $matches)) {
                // Se abbiamo una domanda e risposta precedenti, salvale
                if (!empty($current_question) && !empty($current_answer)) {
                    $faqs[] = array(
                        'question' => trim($current_question),
                        'answer' => trim($current_answer)
                    );
                }
                $current_question = $matches[2];
                $current_answer = '';
            }
            // Se la riga inizia con "R:" o "Risposta:", è una nuova risposta
            elseif (preg_match('/^(R:|Risposta:|R\.|Risposta\.)\s*(.+)$/i', $line, $matches)) {
                $current_answer = $matches[2];
            }
            // Altrimenti, aggiungi alla risposta corrente
            elseif (!empty($current_question)) {
                $current_answer .= "\n" . $line;
            }
        }
        
        // Aggiungi l'ultima FAQ se presente
        if (!empty($current_question) && !empty($current_answer)) {
            $faqs[] = array(
                'question' => trim($current_question),
                'answer' => trim($current_answer)
            );
        }

        if (empty($faqs)) {
            if (FAQ_AI_DEBUG) {
                error_log('FAQ AI Generator - No valid FAQs found in response');
                error_log('FAQ AI Generator - JSON Error: ' . json_last_error_msg());
            }
            return new WP_Error('no_faqs', __('No FAQs found in the response', 'faq-ai-generator'));
        }

        return $faqs;
    }
} 