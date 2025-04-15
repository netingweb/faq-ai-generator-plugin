<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://lucamainieri.it
 * @since      1.0.0
 *
 * @package    Faq_Ai_Generator
 * @subpackage Faq_Ai_Generator/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Includi la classe dei modelli
require_once plugin_dir_path(dirname(__FILE__)) . '../includes/class-faq-ai-generator-models.php';

// Recupera le impostazioni
$settings = get_option('faq_ai_generator_settings', array(
    'api_key' => '',
    'ai_provider' => 'openai',
    'model' => 'gpt-3.5-turbo'
));

// Recupera lo stato dell'API
$api_status = get_transient('faq_ai_api_status');
$api_error = get_transient('faq_ai_api_error');

// Recupera i modelli disponibili
$available_models = Faq_Ai_Generator_Models::get_available_models();
$model_options = Faq_Ai_Generator_Models::get_model_options();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if ($api_error): ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($api_error); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('faq_ai_generator_settings');
        do_settings_sections('faq_ai_generator_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_key"><?php _e('OpenAI API Key', 'faq-ai-generator'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="api_key" 
                           name="faq_ai_generator_settings[api_key]" 
                           value="<?php echo esc_attr($settings['api_key']); ?>" 
                           class="regular-text" />
                    <?php if (!empty($settings['api_key'])): ?>
                        <span class="api-status <?php echo esc_attr($api_status); ?>">
                            <?php echo $api_status === 'valid' ? '✓' : '✗'; ?>
                        </span>
                    <?php endif; ?>
                    <p class="description">
                        <?php _e('Inserisci la tua API Key di OpenAI. Puoi ottenerla da', 'faq-ai-generator'); ?>
                        <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ai_provider"><?php _e('Provider AI', 'faq-ai-generator'); ?></label>
                </th>
                <td>
                    <select id="ai_provider" name="faq_ai_generator_settings[ai_provider]">
                        <option value="openai" <?php selected($settings['ai_provider'], 'openai'); ?>>
                            <?php _e('OpenAI', 'faq-ai-generator'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="model"><?php _e('Modello', 'faq-ai-generator'); ?></label>
                </th>
                <td>
                    <select id="model" name="faq_ai_generator_settings[model]">
                        <?php foreach ($model_options as $id => $name): ?>
                            <option value="<?php echo esc_attr($id); ?>" <?php selected($settings['model'], $id); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($available_models[$settings['model']])): ?>
                        <p class="description">
                            <?php echo esc_html($available_models[$settings['model']]['description']); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>

<style>
.api-status {
    display: inline-block;
    margin-left: 10px;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
}

.api-status.valid {
    color: #46b450;
    background: #f0f6f0;
}

.api-status.invalid {
    color: #dc3232;
    background: #fbeaea;
}
</style> 