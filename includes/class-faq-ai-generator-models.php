<?php

/**
 * The models handler class for FAQ AI Generator
 *
 * @since      1.0.0
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/includes
 */

class Faq_Ai_Generator_Models {

    /**
     * Get available models
     *
     * @since    1.0.0
     * @return   array    The available models
     */
    public static function get_available_models() {
        return array(
            'gpt-3.5-turbo-16k' => array(
                'name' => 'GPT-3.5 Turbo 16K',
                'description' => __('Versione estesa di GPT-3.5 con contesto più ampio, ideale per contenuti lunghi', 'faq-ai-generator'),
                'max_tokens' => 16384
            ),
            'gpt-4-32k' => array(
                'name' => 'GPT-4 32K',
                'description' => __('Versione estesa di GPT-4 con contesto molto ampio, ottimo per analisi approfondite', 'faq-ai-generator'),
                'max_tokens' => 32768
            ),
            'gpt-4-turbo-preview' => array(
                'name' => 'GPT-4 Turbo',
                'description' => __('Versione ottimizzata di GPT-4 per prestazioni più veloci e contesto ampio', 'faq-ai-generator'),
                'max_tokens' => 128000
            ),
            'gpt-4-1106-preview' => array(
                'name' => 'GPT-4 1106',
                'description' => __('Versione stabile di GPT-4 con ottimo bilanciamento tra qualità e contesto', 'faq-ai-generator'),
                'max_tokens' => 128000
            )
        );
    }

    /**
     * Get model options for select field
     *
     * @since    1.0.0
     * @return   array    The model options
     */
    public static function get_model_options() {
        $models = self::get_available_models();
        $options = array();

        foreach ($models as $id => $model) {
            $options[$id] = $model['name'];
        }

        return $options;
    }

    /**
     * Get default model
     *
     * @since    1.0.0
     * @return   string    The default model ID
     */
    public static function get_default_model() {
        return 'gpt-4-1106-preview';
    }

    /**
     * Get model details
     *
     * @since    1.0.0
     * @param    string    $model_id    The model ID
     * @return   array|WP_Error        The model details or error
     */
    public static function get_model_details($model_id) {
        $models = self::get_available_models();
        
        if (!isset($models[$model_id])) {
            return new WP_Error('invalid_model', __('Invalid model ID', 'faq-ai-generator'));
        }
        
        return $models[$model_id];
    }
} 