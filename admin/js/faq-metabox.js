jQuery(document).ready(function($) {
    const $metabox = $('.faq-ai-generator-metabox');
    const $list = $('.faq-ai-generator-list');
    const $generateBtn = $('#faq-ai-generate');
    const $confirmDialog = $('#faq-ai-confirm-dialog');
    const $overwriteBtn = $('#faq-ai-overwrite');
    const $appendBtn = $('#faq-ai-append');
    const $cancelBtn = $('#faq-ai-cancel');
    const nonce = $('input[name="faq_ai_nonce"]').val();
    const postId = $('#post_ID').val();

    // Il pulsante "Genera FAQ" mostra solo il popup
    $generateBtn.on('click', function() {
        $confirmDialog.show();
    });

    // Gestione click su "Sovrascrivi FAQ esistenti"
    $overwriteBtn.on('click', function() {
        $confirmDialog.hide();
        generateFaqs(false);
    });

    // Gestione click su "Aggiungi nuove FAQ"
    $appendBtn.on('click', function() {
        $confirmDialog.hide();
        generateFaqs(true);
    });

    // Gestione click su "Annulla"
    $cancelBtn.on('click', function() {
        $confirmDialog.hide();
    });

    // Funzione per generare le FAQ
    function generateFaqs(append = false) {
        const $self = $generateBtn;
        $self.prop('disabled', true).text('Generazione in corso...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'faq_ai_generate',
                post_id: postId,
                nonce: nonce,
                append: append
            },
            success: function(response) {
                if (response.success) {
                    if (!append) {
                        $list.empty();
                    }
                    response.data.faqs.forEach(function(faq, index) {
                        addFaqItem(faq.question, faq.answer, index);
                    });
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('Errore durante la generazione delle FAQ');
            },
            complete: function() {
                $self.prop('disabled', false).text('Genera FAQ');
            }
        });
    }

    // Funzione per aggiungere un elemento FAQ
    function addFaqItem(question, answer, index) {
        const $item = $('<div class="faq-item" data-index="' + index + '">' +
            '<div class="faq-question">' +
            '<input type="text" name="faq_ai_questions[]" value="' + question + '" placeholder="Domanda">' +
            '</div>' +
            '<div class="faq-answer">' +
            '<textarea name="faq_ai_answers[]" placeholder="Risposta">' + answer + '</textarea>' +
            '</div>' +
            '<button type="button" class="button faq-remove">Rimuovi</button>' +
            '</div>');

        $list.append($item);
    }

    // Rimuovi FAQ
    $list.on('click', '.faq-remove', function() {
        $(this).closest('.faq-item').remove();
        updateIndexes();
    });

    // Funzione per aggiornare gli indici
    function updateIndexes() {
        $('.faq-item').each(function(index) {
            $(this).attr('data-index', index);
        });
    }
}); 