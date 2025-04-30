jQuery(document).ready(function($) {
    const $metabox = $('.faq-ai-generator-metabox');
    const $list = $('.faq-ai-generator-list');
    const $generateBtn = $('#faq-ai-generate');
    const $addBtn = $('#faq-ai-add');
    const $confirmDialog = $('#faq-ai-confirm-dialog');
    const $overwriteBtn = $('#faq-ai-overwrite');
    const $appendBtn = $('#faq-ai-append');
    const $cancelBtn = $('#faq-ai-cancel');
    const nonce = $('input[name="faq_ai_nonce"]').val();
    const postId = $('#post_ID').val();
    var shouldAppend = false;

    // Funzione per mostrare il popup di conferma
    function showConfirmDialog() {
        $confirmDialog.show();
    }

    // Funzione per nascondere il popup di conferma
    function hideConfirmDialog() {
        $confirmDialog.hide();
    }

    // Gestione del click sul bottone Genera FAQ
    $generateBtn.on('click', function(e) {
        e.preventDefault();
        showConfirmDialog();
    });

    // Gestione del click su Sovrascrivi
    $overwriteBtn.on('click', function() {
        shouldAppend = false;
        hideConfirmDialog();
        generateNewFaqs();
    });

    // Gestione del click su Aggiungi
    $appendBtn.on('click', function() {
        shouldAppend = true;
        hideConfirmDialog();
        generateNewFaqs();
    });

    // Gestione del click su Annulla
    $cancelBtn.on('click', function() {
        hideConfirmDialog();
    });

    // Gestione del click su Aggiungi una nuova FAQ
    $addBtn.on('click', function() {
        const index = $('.faq-item').length;
        addFaqItem('', '', index);
    });

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

    // Funzione per generare nuove FAQ
    function generateNewFaqs() {
        const $self = $generateBtn;
        $self.prop('disabled', true).text('Generazione in corso...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'faq_ai_generate',
                post_id: postId,
                nonce: nonce,
                append: shouldAppend ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    if (!shouldAppend) {
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
}); 