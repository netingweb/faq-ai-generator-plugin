<?php

/**
 * The API handler class for FAQ AI Generator
 *
 * @since      1.0.0
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/includes
 */

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
            return new WP_Error('no_api_key', __('API key non configurata', 'faq-ai-generator'));
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
            ])
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
        return "Sei un esperto di SEO e un copywriting professionista con solide competenze di SEO. " .
               "Il tuo compito è integrare degli articoli e contenuti esistenti sul sito con domande di approfondimento " .
               "inerenti al contenuto della pagina. Il tuo compito è leggere e comprendere il contenuto e generare " .
               "un elenco di domande e risposte(FAQ) che siano utili per i visitatori del sito e che forniscano informazioni ".
               "relative al contenuto della pagina e che siano basate sul contenuto fornito.\n\n" .
               "IMPORTANTE: La risposta DEVE essere in formato JSON con questa struttura:\n" .
               '[{"question": "domanda1", "answer": "risposta1"}, {"question": "domanda2", "answer": "risposta2"}]\n\n';
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
        // 1. Inizia con il prompt base
        $prompt = $this->get_base_prompt();
        
        // 2. Aggiungi eventuali istruzioni extra dalle impostazioni
        $settings = get_option('faq_ai_generator_settings');
        if (!empty($settings['extra_prompt'])) {
            $prompt .= "ISTRUZIONI EXTRA:\n" . trim($settings['extra_prompt']) . "\n\n";
        }
        
        // 3. Aggiungi le FAQ esistenti se presenti
        if (!empty($existing_faqs)) {
            $prompt .= "FAQ GIÀ ESISTENTI (genera FAQ diverse da queste):\n";
            foreach ($existing_faqs as $index => $faq) {
                $prompt .= sprintf("%d. Q: %s\nA: %s\n\n", $index + 1, $faq['question'], $faq['answer']);
            }
        }
        
        // 4. Aggiungi il contenuto dell'articolo
        $prompt .= "CONTENUTO DA ANALIZZARE:\n\n" . wp_strip_all_tags($content);
        
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
            return new WP_Error('no_api_key', __('Chiave API non configurata', 'faq-ai-generator'));
        }

        // Recupera le impostazioni
        $settings = get_option('faq_ai_generator_settings');
        $model = !empty($settings['model']) ? $settings['model'] : 'gpt-3.5-turbo';

        // Genera il prompt completo
        $prompt = $this->build_prompt($content, $existing_faqs);

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
            'max_tokens' => 1000
        );

        // Log dei parametri della richiesta
        error_log('FAQ AI Generator - Request Parameters:');
        error_log('Model: ' . $model);
        error_log('Prompt Length: ' . strlen($prompt));
        error_log('Existing FAQs Count: ' . count($existing_faqs));
        error_log('Request Data: ' . json_encode($request_data, JSON_PRETTY_PRINT));

        // Chiamata API
        $start_time = microtime(true);
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // in millisecondi

        // Log del tempo di esecuzione
        error_log('FAQ AI Generator - API Call Execution Time: ' . $execution_time . 'ms');

        if (is_wp_error($response)) {
            error_log('FAQ AI Generator - API Error: ' . $response->get_error_message());
            error_log('FAQ AI Generator - Error Code: ' . $response->get_error_code());
            return $response;
        }

        // Log della risposta
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('FAQ AI Generator - Response Code: ' . $response_code);
        error_log('FAQ AI Generator - Response Body: ' . $response_body);

        $body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('FAQ AI Generator - JSON Parse Error: ' . json_last_error_msg());
            return new WP_Error('invalid_response', __('Risposta API non valida', 'faq-ai-generator'));
        }

        return $this->parse_response($body);
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
            return new WP_Error('invalid_response', __('Risposta API non valida', 'faq-ai-generator'));
        }

        $content = $response['choices'][0]['message']['content'];
        
        // Prova a decodificare come JSON
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Se non è JSON valido, prova a estrarre FAQ dal testo
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
            return new WP_Error('no_faqs', __('Nessuna FAQ trovata nella risposta', 'faq-ai-generator'));
        }

        return $faqs;
    }
} 