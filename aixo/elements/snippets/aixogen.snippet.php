<?php
/**
 * AixoGen snippet: Calls the Aixo AI service and returns its response.
 *
 * Usage examples:
 *  - Text: [[!AixoGen? &prompt=`Hello`]]
 *  - Image: [[!AixoGen? &prompt=`A futuristic city skyline` &task=`image` &n=`2` &size=`512x512`]]
 *  - Text-to-Speech: [[!AixoGen? &prompt=`Hello world` &task=`tts` &voice=`en-US` &language=`en`]]
 */

// Retrieve the prompt.
$prompt = $modx->getOption('prompt', $scriptProperties, '');
if (empty($prompt)) {
    return ''; // No prompt provided.
}

// Build options array with sensible defaults.
$options = [];

// Determine task type: defaults to 'text'
$options['task'] = $modx->getOption('task', $scriptProperties, 'text');

// Provider: default from system settings (e.g. 'openai')
$options['provider'] = $modx->getOption(
    'provider',
    $scriptProperties,
    $modx->getOption('aixo.default_provider', null, 'openai')
);

// For Text generation (task = 'text')
if (strtolower($options['task']) === 'text') {
    $options['model'] = $modx->getOption(
        'model',
        $scriptProperties,
        $modx->getOption('aixo.default_model', null, 'gpt-3.5-turbo')
    );
    $options['temperature'] = $modx->getOption(
        'temperature',
        $scriptProperties,
        $modx->getOption('aixo.default_temperature', null, '0.7')
    );
    $options['max_tokens'] = $modx->getOption(
        'max_tokens',
        $scriptProperties,
        $modx->getOption('aixo.max_tokens', null, '256')
    );
}

// For Image generation (task = 'image')
if (strtolower($options['task']) === 'image') {
    $options['n'] = $modx->getOption('n', $scriptProperties, 1);
    $options['size'] = $modx->getOption('size', $scriptProperties, '1024x1024');
    $options['model'] = $modx->getOption(
        'model',
        $scriptProperties,
        $modx->getOption('aixo.default_image_model', null, 'dall-e-2')
    );
}

// For Text-to-Speech (task = 'tts' or 'text-to-speech')
if (in_array(strtolower($options['task']), ['tts', 'text-to-speech'])) {
    $options['voice'] = $modx->getOption(
        'voice',
        $scriptProperties,
        $modx->getOption('aixo.default_voice', null, 'en-US')
    );
    $options['language'] = $modx->getOption(
        'language',
        $scriptProperties,
        $modx->getOption('aixo.default_language', null, 'en')
    );
}

// Retrieve the Aixo service instance.
$aixo = $modx->services->get('aixo');
if (!$aixo) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[Aixo] Aixo service is not available.');
    return '';
}

// Call the Aixo service to process the prompt.
$response = $aixo->process($prompt, $options);
return $response;
