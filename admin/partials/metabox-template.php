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

$faq_data = get_post_meta(get_the_ID(), '_faq_ai_data', true);
$display_in_content = isset($faq_data['display_in_content']) ? $faq_data['display_in_content'] : true;
$faqs = isset($faq_data['faqs']) ? $faq_data['faqs'] : [];
?>

<div class="faq-ai-generator-metabox">
    <div class="faq-ai-generator-controls">
        <div class="faq-ai-actions">
            <button type="button" id="faq-ai-generate" class="button button-primary">
                <?php _e('Genera FAQ', 'faq-ai-generator'); ?>
            </button>
        </div>
    </div>

    <div class="faq-ai-generator-display-option">
        <label>
            <input type="checkbox" 
                   name="faq_ai_display_in_content" 
                   value="1" 
                   <?php checked($display_in_content, true); ?>>
            <?php _e('Mostra FAQ nel contenuto dell\'articolo', 'faq-ai-generator'); ?>
        </label>
    </div>

    <div class="faq-ai-generator-list">
        <?php foreach ($faqs as $index => $faq): ?>
            <div class="faq-item" data-index="<?php echo esc_attr($index); ?>">
                <div class="faq-question">
                    <input type="text" 
                           name="faq_ai_questions[]" 
                           value="<?php echo esc_attr($faq['question']); ?>" 
                           placeholder="<?php _e('Domanda', 'faq-ai-generator'); ?>">
                </div>
                <div class="faq-answer">
                    <textarea name="faq_ai_answers[]" 
                              placeholder="<?php _e('Risposta', 'faq-ai-generator'); ?>"><?php echo esc_textarea($faq['answer']); ?></textarea>
                </div>
                <button type="button" class="button faq-remove">
                    <?php _e('Rimuovi', 'faq-ai-generator'); ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="faq-ai-generator-add">
        <button type="button" class="button" id="faq-ai-add">
            <?php _e('Aggiungi una nuova FAQ', 'faq-ai-generator'); ?>
        </button>
    </div>

    <input type="hidden" name="faq_ai_nonce" value="<?php echo wp_create_nonce('faq_ai_nonce'); ?>">

    <!-- Popup di conferma -->
    <div id="faq-ai-confirm-dialog" style="display: none;">
        <div class="faq-ai-confirm-content">
            <h3><?php _e('Conferma generazione FAQ', 'faq-ai-generator'); ?></h3>
            <p><?php _e('Scegli come procedere con la generazione delle FAQ:', 'faq-ai-generator'); ?></p>
            <div class="faq-ai-confirm-buttons">
                <button type="button" id="faq-ai-overwrite" class="button button-primary">
                    <?php _e('Sovrascrivi FAQ esistenti', 'faq-ai-generator'); ?>
                </button>
                <button type="button" id="faq-ai-append" class="button">
                    <?php _e('Aggiungi nuove FAQ', 'faq-ai-generator'); ?>
                </button>
                <button type="button" id="faq-ai-cancel" class="button">
                    <?php _e('Annulla', 'faq-ai-generator'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="faq-ai-message">
        <!-- Messaggi di notifica -->
    </div>
</div>

<style>
.faq-ai-generator-metabox {
    padding: 10px;
}

.faq-ai-generator-controls {
    margin-bottom: 15px;
}

.faq-ai-generator-display-option {
    margin-bottom: 15px;
}

.faq-ai-generator-add {
    margin-top: 15px;
}

.faq-item {
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #ddd;
    background: #fff;
}

.faq-question input,
.faq-answer textarea {
    width: 100%;
    margin-bottom: 5px;
}

.faq-answer textarea {
    height: 100px;
}

.faq-remove {
    margin-top: 5px;
}

#faq-ai-confirm-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.faq-ai-confirm-content {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    max-width: 500px;
    width: 90%;
}
.faq-ai-confirm-buttons {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var shouldAppend = false;

    // Funzione per mostrare il popup di conferma
    function showConfirmDialog() {
        $('#faq-ai-confirm-dialog').show();
    }

    // Funzione per nascondere il popup di conferma
    function hideConfirmDialog() {
        $('#faq-ai-confirm-dialog').hide();
    }

    // Gestione del click sul bottone Genera FAQ
    $('#faq-ai-generate').on('click', function(e) {
        e.preventDefault();
        showConfirmDialog();
    });

    // Gestione del click su Sovrascrivi
    $('#faq-ai-overwrite').on('click', function() {
        shouldAppend = false;
        hideConfirmDialog();
        generateNewFaqs();
    });

    // Gestione del click su Aggiungi
    $('#faq-ai-append').on('click', function() {
        shouldAppend = true;
        hideConfirmDialog();
        generateNewFaqs();
    });

    // Gestione del click su Annulla
    $('#faq-ai-cancel').on('click', function() {
        hideConfirmDialog();
    });

    // Funzione per generare nuove FAQ
    function generateNewFaqs() {
        var $button = $('#faq-ai-generate');
        var originalText = $button.html();
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> <?php _e('Generazione in corso...', 'faq-ai-generator'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_faqs',
                post_id: '<?php echo $post->ID; ?>',
                nonce: '<?php echo wp_create_nonce('faq_ai_nonce'); ?>',
                append: shouldAppend ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    var faqsHtml = '';
                    
                    // Se dobbiamo aggiungere, manteniamo le FAQ esistenti
                    if (shouldAppend) {
                        // Manteniamo le FAQ esistenti
                        $('.faq-ai-generator-list .faq-item').each(function() {
                            faqsHtml += $(this).prop('outerHTML');
                        });
                    }

                    // Aggiungiamo le nuove FAQ
                    response.data.faqs.forEach(function(faq, index) {
                        faqsHtml += generateFaqHtml($('.faq-item').length + index, faq.question, faq.answer);
                    });

                    $('.faq-ai-generator-list').html(faqsHtml);
                    $('#faq-ai-message').html('<div class="notice notice-success"><p><?php _e('FAQ generate con successo!', 'faq-ai-generator'); ?></p></div>');
                } else {
                    $('#faq-ai-message').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#faq-ai-message').html('<div class="notice notice-error"><p><?php _e('Si è verificato un errore durante la generazione delle FAQ.', 'faq-ai-generator'); ?></p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
});
</script> 