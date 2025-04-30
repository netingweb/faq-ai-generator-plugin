<?php

/**
 * The Schema.org handler class for FAQ AI Generator
 *
 * @since      1.0.0
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/includes
 */

class Faq_Ai_Generator_Schema {

    /**
     * Generate FAQPage schema for a post
     *
     * @since    1.0.0
     * @param    int       $post_id    The post ID
     * @return   string               The JSON-LD schema
     */
    public function generate_schema($post_id) {
        $faq_data = get_post_meta($post_id, '_faq_ai_data', true);
        
        if (empty($faq_data) || empty($faq_data['faqs'])) {
            return '';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => []
        ];

        foreach ($faq_data['faqs'] as $faq) {
            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                ]
            ];
        }

        return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
    }

    /**
     * Add schema to the head of the page
     *
     * @since    1.0.0
     */
    public function add_schema_to_head() {
        if (!is_single()) {
            return;
        }

        global $post;
        $schema = $this->generate_schema($post->ID);
        
        if (!empty($schema)) {
            echo wp_kses_post($schema);
        }
    }
} 