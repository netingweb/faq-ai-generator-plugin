<?php
require_once 'vendor/autoload.php';

use Gettext\Translations;
use Gettext\Generator\MoGenerator;

// Carica il file .po
$translations = Translations::fromPoFile('languages/faq-ai-generator-it_IT.po');

// Genera il file .mo
$generator = new MoGenerator();
$generator->generateFile($translations, 'languages/faq-ai-generator-it_IT.mo');

echo "File .mo generato con successo!\n"; 