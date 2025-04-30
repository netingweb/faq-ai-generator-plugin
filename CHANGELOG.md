# Changelog

Tutte le modifiche notevoli a questo progetto saranno documentate in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2024-03-21

### Aggiunto
- Supporto multilingua (Italiano e Inglese)
- File di traduzione per l'inglese (faq-ai-generator-en_US.po e .mo)
- Prompt AI localizzati in base alla lingua del sito
- Traduzione di tutte le stringhe dell'interfaccia utente

### Modificato
- Migliorata la gestione dei prompt AI per supportare più lingue
- Aggiornato il sistema di debug per essere più efficiente
- Ottimizzata la gestione delle risposte API

### Corretto
- Risolto il problema di sanitizzazione dell'input nelle FAQ
- Corretta la gestione degli errori API
- Migliorata la validazione dei dati in input

## [1.1.0] - 2024-03-20

### Aggiunto
- Supporto per GPT-4 e altri modelli avanzati
- Sistema di debug configurabile
- Migliorata la gestione degli errori API
- Supporto per custom post types

### Modificato
- Ottimizzata la generazione delle FAQ
- Migliorata l'interfaccia utente
- Aggiornato il sistema di caching

## [1.0.0] - 2024-03-19

### Aggiunto
- Prima versione del plugin
- Generazione FAQ con OpenAI
- Integrazione schema.org
- Interfaccia amministrativa
- Supporto per post e pagine
- Sistema di caching base 