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
    'model' => 'gpt-3.5-turbo',
    'extra_prompt' => '',
    'custom_instructions' => '',
    'enabled_post_types' => array()
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
                    <label for="api_key"><?php esc_html_e('OpenAI API Key', 'faq-ai-generator'); ?></label>
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
                        <?php esc_html_e('Enter your OpenAI API Key. You can get it from', 'faq-ai-generator'); ?>
                        <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ai_provider"><?php esc_html_e('AI Provider', 'faq-ai-generator'); ?></label>
                </th>
                <td>
                    <select id="ai_provider" name="faq_ai_generator_settings[ai_provider]">
                        <option value="openai" <?php selected($settings['ai_provider'], 'openai'); ?>>
                            <?php esc_html_e('OpenAI', 'faq-ai-generator'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="model"><?php esc_html_e('Model', 'faq-ai-generator'); ?></label>
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
            
            <tr>
                <th scope="row">
                    <label for="extra_prompt"><?php esc_html_e('Extra Prompt', 'faq-ai-generator'); ?></label>
                </th>
                <td>
                    <textarea 
                        id="extra_prompt" 
                        name="faq_ai_generator_settings[extra_prompt]" 
                        class="large-text code" 
                        rows="3"
                    ><?php echo esc_textarea(isset($settings['extra_prompt']) ? $settings['extra_prompt'] : ''); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Extra instructions for the AI prompt (use with caution). These instructions will be added at the beginning of the prompt.', 'faq-ai-generator'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php esc_html_e('Custom Post Types', 'faq-ai-generator'); ?>
                </th>
                <td>
                    <?php
                    $custom_post_types = get_post_types(array('_builtin' => false), 'objects');
                    if (!empty($custom_post_types)) {
                        foreach ($custom_post_types as $post_type) {
                            $enabled = isset($settings['enabled_post_types'][$post_type->name]) ? $settings['enabled_post_types'][$post_type->name] : false;
                            ?>
                            <label>
                                <input type="checkbox" 
                                       name="faq_ai_generator_settings[enabled_post_types][<?php echo esc_attr($post_type->name); ?>]" 
                                       value="1" 
                                       <?php checked($enabled, true); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </label><br>
                            <?php
                        }
                    } else {
                        esc_html_e('No custom post types found.', 'faq-ai-generator');
                    }
                    ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Built-in Post Types', 'faq-ai-generator'); ?>
                </th>
                <td>
                    <?php
                    // Imposta i valori di default per i post types built-in
                    $default_post_types = array(
                        'post' => true,  // Posts abilitati di default
                        'page' => false  // Pages disabilitati di default
                    );
                    
                    // Unisci i valori salvati con i default
                    $enabled_post_types = isset($settings['enabled_post_types']) ? $settings['enabled_post_types'] : array();
                    $enabled_post_types = array_merge($default_post_types, $enabled_post_types);
                    ?>
                    <label>
                        <input type="checkbox" 
                               name="faq_ai_generator_settings[enabled_post_types][post]" 
                               value="1"
                               readonly="readonly"
                               class="readonly-checkbox"
                               <?php checked($enabled_post_types['post'], true); ?>>
                        <?php esc_html_e('Posts', 'faq-ai-generator'); ?>
                        <span class="description"><?php esc_html_e('(Sempre abilitato)', 'faq-ai-generator'); ?></span>
                    </label><br>
                    <label>
                        <input type="checkbox" 
                               name="faq_ai_generator_settings[enabled_post_types][page]" 
                               value="1" 
                               <?php checked($enabled_post_types['page'], true); ?>>
                        <?php esc_html_e('Pages', 'faq-ai-generator'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="debug_mode"><?php esc_html_e('Debug Mode', 'faq-ai-generator'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="debug_mode" 
                               name="faq_ai_generator_settings[debug_mode]" 
                               value="1" 
                               <?php checked(isset($settings['debug_mode']) ? $settings['debug_mode'] : false, true); ?>>
                        <?php esc_html_e('Enable debug mode', 'faq-ai-generator'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Enable this option to log debug information to the WordPress debug log. Use this only for development and troubleshooting.', 'faq-ai-generator'); ?>
                    </p>
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

.readonly-checkbox {
    opacity: 0.7;
    cursor: not-allowed;
}

.readonly-checkbox + .description {
    color: #666;
    font-style: italic;
    margin-left: 5px;
}
</style> 