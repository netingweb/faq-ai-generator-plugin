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

        // Prepara il prompt includendo le FAQ esistenti se presenti
        $prompt = "Genera un elenco di FAQ basate sul seguente contenuto:\n\n";
        $prompt .= $content . "\n\n";
        
        if (!empty($existing_faqs)) {
            $prompt .= "FAQ esistenti:\n";
            foreach ($existing_faqs as $faq) {
                $prompt .= "Q: " . $faq['question'] . "\n";
                $prompt .= "R: " . $faq['answer'] . "\n\n";
            }
            $prompt .= "Genera nuove FAQ diverse da quelle esistenti.\n";
        }

        $prompt .= "Rispondi in formato JSON con la seguente struttura:\n";
        $prompt .= '[{"question": "domanda1", "answer": "risposta1"}, {"question": "domanda2", "answer": "risposta2"}]';

        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 1000
            )),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
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

    private function build_prompt($content, $existing_faqs = array()) {
        $prompt = "Genera 5-7 FAQ pertinenti per il seguente contenuto. ";
        
        if (!empty($existing_faqs)) {
            $prompt .= "Tieni presente che esistono già le seguenti FAQ, quindi genera FAQ diverse:\n\n";
            foreach ($existing_faqs as $index => $faq) {
                $prompt .= sprintf("%d. Q: %s\nA: %s\n\n", $index + 1, $faq['question'], $faq['answer']);
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Il contenuto dell'articolo è:\n\n" . $content;
        return $prompt;
    }
} 