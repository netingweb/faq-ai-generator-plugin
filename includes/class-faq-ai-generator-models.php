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
            'gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'description' => __('Fast and economical model, ideal for most applications', 'faq-ai-generator')
            ),
            'gpt-4' => array(
                'name' => 'GPT-4',
                'description' => __('More advanced and accurate model, ideal for complex content', 'faq-ai-generator')
            ),
            'gpt-4.1-mini' => array(
                'name' => 'GPT-4.1 Mini',
                'description' => __('Compact version of GPT-4.1, balanced between speed and quality', 'faq-ai-generator')
            ),
            'gpt-4-turbo' => array(
                'name' => 'GPT-4 Turbo',
                'description' => __('Optimized version of GPT-4 for faster performance', 'faq-ai-generator')
            ),
            'gpt-4o' => array(
                'name' => 'GPT-4o',
                'description' => __('Optimized version of GPT-4 for more concise output', 'faq-ai-generator')
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
     * Get model details
     *
     * @since    1.0.0
     * @param    string    $model_id    The model ID
     * @return   array|false            The model details or false if not found
     */
    public static function get_model_details($model_id) {
        $models = self::get_available_models();
        return isset($models[$model_id]) ? $models[$model_id] : false;
    }
} 