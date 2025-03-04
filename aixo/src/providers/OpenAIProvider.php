<?php
namespace MODX\Aixo\Providers;

use MODX\Revolution\modX;

class OpenAIProvider implements AixoProviderInterface {
    /** @var modX */
    protected $modx;
    /** @var string Last error message */
    protected $lastError = '';

    public function __construct(modX $modx) {
        $this->modx = $modx;
    }

    /**
     * Returns the unique provider key.
     */
    public function getKey(): string {
        return 'openai';
    }

    /**
     * Returns the human-readable provider name.
     */
    public function getName(): string {
        return 'OpenAI API';
    }

    /**
     * Checks if the OpenAI provider is available (e.g. API key is set).
     */
    public function isAvailable(): bool {
        $apiKey = trim((string)$this->modx->getOption('aixo.api_key_openai', null, ''));
        return !empty($apiKey) && function_exists('curl_init');
    }

    /**
     * Returns the last error message.
     */
    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * Process an AI request.
     * Checks the 'task' option to delegate to text, image, or TTS generation.
     *
     * @param string $prompt The input prompt.
     * @param array $options Additional options:
     *                       - 'task': 'text' (default), 'image', or 'tts'
     *                       - For text: 'model', 'max_tokens', 'temperature'
     *                       - For image: 'model', 'n', 'size'
     *                       - For tts: (optional) 'voice', etc.
     * @return string The AI-generated result.
     */
    public function process(string $prompt, array $options = []): string {
        $task = isset($options['task']) ? strtolower($options['task']) : 'text';
        if ($task === 'image') {
            return $this->processImage($prompt, $options);
        } elseif ($task === 'tts' || $task === 'text-to-speech') {
            return $this->processTTS($prompt, $options);
        }
        return $this->processText($prompt, $options);
    }

    /**
     * Process a text generation request using chat completions.
     */
    protected function processText(string $prompt, array $options = []): string {
        $this->lastError = '';
        $apiKey = trim((string)$this->modx->getOption('aixo.api_key_openai', null, ''));
        if (empty($apiKey)) {
            $this->lastError = 'Missing OpenAI API key';
            return '';
        }
        
        // Get the model from options or default settings.
        $model = $options['model'] ?? $this->modx->getOption('aixo.default_model', null, 'gpt-3.5-turbo');
        
        // Define array of models that support extra parameters.
        $modelsWithExtras = ['gpt-3.5-turbo', 'gpt-4', 'davinci'];
        
        // Build basic payload for chat completions.
        $requestData = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user',   'content' => $prompt]
            ],
        ];
        
        // Only include extra parameters if the model supports them.
        if (in_array(strtolower($model), array_map('strtolower', $modelsWithExtras))) {
            $maxTokens   = $options['max_tokens'] ?? $this->modx->getOption('aixo.max_tokens', null, '256');
            $temperature = $options['temperature'] ?? $this->modx->getOption('aixo.default_temperature', null, '0.7');
            $requestData['max_tokens']  = intval($maxTokens);
            $requestData['temperature'] = floatval($temperature);
        } else {
            $this->modx->log(modX::LOG_LEVEL_INFO, "[Aixo] Model '{$model}' does not support max_tokens/temperature; omitting them.");
        }
        
        $endpoint = "https://api.openai.com/v1/chat/completions";
        $payload = json_encode($requestData);
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}"
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $this->lastError = 'cURL Error: ' . curl_error($ch);
            curl_close($ch);
            return '';
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->lastError = "OpenAI API error (HTTP {$httpCode}): $responseBody";
            return '';
        }
        
        $resultData = json_decode($responseBody, true);
        if (!$resultData) {
            $this->lastError = 'Invalid JSON response from OpenAI';
            return '';
        }
        if (!empty($resultData['error'])) {
            $this->lastError = "OpenAI Error: " . ($resultData['error']['message'] ?? 'Unknown error');
            return '';
        }
        
        if (!isset($resultData['choices'][0]['message']['content'])) {
            $this->lastError = 'No completion message found in response';
            return '';
        }
        
        return $resultData['choices'][0]['message']['content'];
    }

   /**
 * Process an image generation request (e.g. using DALL-E).
 *
 * @param string $prompt The prompt for image generation.
 * @param array $options Additional options, such as:
 *                       - 'model': Optional image model; defaults to system setting aixo.default_image_model.
 *                       - 'n': Number of images (default: 1)
 *                       - 'size': Image size (default: '1024x1024')
 * @return string A comma-separated list of image URLs.
 */
protected function processImage(string $prompt, array $options = []): string {
    $this->lastError = '';
    $apiKey = trim((string)$this->modx->getOption('aixo.api_key_openai', null, ''));
    if (empty($apiKey)) {
        $this->lastError = 'Missing OpenAI API key';
        return '';
    }
    
    // Retrieve image model from options or system setting.
    $model = $options['model'] ?? $this->modx->getOption('aixo.default_image_model', null, 'dall-e-2');
    
    // Retrieve additional parameters.
    $n = isset($options['n']) ? intval($options['n']) : 1;
    $size = $options['size'] ?? '1024x1024';
    
    $requestData = [
        'prompt' => $prompt,
        'n'      => $n,
        'size'   => $size,
    ];
    // Include the model parameter if it's provided.
    if (!empty($model)) {
        $requestData['model'] = $model;
    }
    
    $endpoint = "https://api.openai.com/v1/images/generations";
    $payload = json_encode($requestData);
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer {$apiKey}"
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        $this->lastError = 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        return '';
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $this->lastError = "OpenAI API error (HTTP {$httpCode}): $responseBody";
        return '';
    }
    
    $resultData = json_decode($responseBody, true);
    if (!$resultData) {
        $this->lastError = 'Invalid JSON response from OpenAI';
        return '';
    }
    if (!empty($resultData['error'])) {
        $errorMessage = $resultData['error']['message'] ?? 'Unknown error';
        // Check if the error indicates a size parameter issue.
        if (stripos($errorMessage, 'size') !== false) {
            $this->lastError = "Image generation error: The provided image size is not supported by the selected model. Please adjust the size parameter.";
        } else {
            $this->lastError = "OpenAI Error: " . $errorMessage;
        }
        return '';
    }
    
    if (!isset($resultData['data']) || !is_array($resultData['data'])) {
        $this->lastError = 'No image data found in response';
        return '';
    }
    
    $urls = [];
    foreach ($resultData['data'] as $item) {
        if (isset($item['url'])) {
            $urls[] = $item['url'];
        }
    }
    return !empty($urls) ? implode(', ', $urls) : '';

    }

    /**
     * Process a text-to-speech (TTS) request.
     *
     * @param string $prompt The text to convert to speech.
     * @param array $options Additional options, such as:
     *                       - 'voice': Optional voice selection.
     *                       - 'language': Optional language code.
     * @return string A URL to the generated audio file, or a JSON-encoded array if multiple files.
     */
    protected function processTTS(string $prompt, array $options = []): string {
        $this->lastError = '';
        $apiKey = trim((string)$this->modx->getOption('aixo.api_key_openai', null, ''));
        if (empty($apiKey)) {
            $this->lastError = 'Missing OpenAI API key';
            return '';
        }

        // Retrieve tts model from options or system setting.
    $model = $options['model'] ?? $this->modx->getOption('aixo.default_tts_model', null, 'tts-1'); // Can be tts-1 or tts-1-hd
        
        // For TTS, we use other params.
        $voice = $options['voice'] ?? $this->modx->getOption('aixo.default_voice', null, 'nova'); // Experiment with different voices (alloy, ash, coral, echo, fable, onyx, nova, sage, shimmer)
        
        $requestData = [
            'model'     => $model,
            'input'     => $prompt,
            'voice'    => $voice,
        ];
        
        // TTS Endpoint
        $endpoint = "https://api.openai.com/v1/audio/speech";
        $payload = json_encode($requestData);
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}"
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $this->lastError = 'cURL Error: ' . curl_error($ch);
            curl_close($ch);
            return '';
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->lastError = "OpenAI API error (HTTP {$httpCode}): $responseBody";
            return '';
        }
        
        $resultData = json_decode($responseBody, true);
        if (!$resultData) {
            $this->lastError = 'Invalid JSON response from OpenAI';
            return '';
        }
        if (!empty($resultData['error'])) {
            $this->lastError = "OpenAI Error: " . ($resultData['error']['message'] ?? 'Unknown error');
            return '';
        }
        
        // Assume the TTS response returns an audio URL in 'audio_url'
        if (!isset($resultData['audio_url'])) {
            $this->lastError = 'No audio URL found in TTS response';
            return '';
        }
        return $resultData['audio_url'];
    }
}
