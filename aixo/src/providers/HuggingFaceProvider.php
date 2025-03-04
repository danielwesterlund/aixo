<?php
namespace MODX\Aixo\Providers;

use MODX\Revolution\modX;

class HuggingFaceProvider implements AixoProviderInterface {
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
        return 'huggingface';
    }

    /**
     * Returns the human-readable provider name.
     */
    public function getName(): string {
        return 'HuggingFace API';
    }

    /**
     * Checks if the HuggingFace provider is available (e.g. API key is set).
     */
    public function isAvailable(): bool {
        $apiKey = trim((string)$this->modx->getOption('aixo.api_key_huggingface', null, ''));
        return !empty($apiKey) && function_exists('curl_init');
    }

    /**
     * Processes the AI prompt using the HuggingFace Inference API.
     *
     * @param string $prompt The input text prompt.
     * @param array $options Additional options; expects 'model' to be provided.
     * @return string The generated text (or empty string on failure).
     */
    public function process(string $prompt, array $options = []): string {
        $this->lastError = '';
        // Retrieve API key for HuggingFace from system settings.
        $apiKey = trim((string)$this->modx->getOption('aixo.api_key_huggingface', null, ''));
        if (empty($apiKey)) {
            $this->lastError = 'Missing HuggingFace API key';
            return '';
        }
        
        // Get model name from options or system setting.
        $model = $options['model'] ?? $this->modx->getOption('aixo.default_model_huggingface', null, 'gpt2');
        if (empty($model)) {
            $this->lastError = 'No model specified for HuggingFace';
            return '';
        }
        
        // Build the endpoint URL.
        $endpoint = "https://api-inference.huggingface.co/models/{$model}";
        
        // Prepare the payload.
        $data = [
            'inputs' => $prompt
        ];
        
        $payload = json_encode($data);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$apiKey}"
        ]);
        // Optional: set timeouts
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $this->lastError = 'cURL Error: ' . curl_error($ch);
            curl_close($ch);
            return '';
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->lastError = "HuggingFace API error (HTTP {$httpCode}): " . $responseBody;
            return '';
        }
        
        // Decode the JSON response.
        $resultData = json_decode($responseBody, true);
        if (!$resultData) {
            $this->lastError = 'Invalid JSON response from HuggingFace';
            return '';
        }
        if (isset($resultData['error'])) {
            $this->lastError = "HuggingFace Error: " . $resultData['error'];
            return '';
        }
        
        // Assume the generated text is in the 'generated_text' field,
        // but adjust based on the actual API response structure.
        if (isset($resultData[0]['generated_text'])) {
            return $resultData[0]['generated_text'];
        } elseif (isset($resultData['generated_text'])) {
            return $resultData['generated_text'];
        }
        
        $this->lastError = 'No generated text found in HuggingFace response';
        return '';
    }

    /**
     * Returns the last error message.
     */
    public function getLastError(): string {
        return $this->lastError;
    }
}
