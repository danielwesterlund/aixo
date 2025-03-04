<?php
namespace MODX\Aixo;

use MODX\Revolution\modX;
use MODX\Aixo\Providers\AixoProviderInterface;

class Aixo {
    /** @var modX */
    protected $modx;
    /** @var array<string, AixoProviderInterface> Loaded provider instances keyed by provider key */
    protected $providers = [];
    /** @var bool Debug mode flag */
    protected $debug = false;

    /**
     * Constructor: initialize Aixo service, load providers, and set debug mode.
     *
     * @param modX $modx
     */
    public function __construct(modX $modx) {
        $this->modx = $modx;
        $this->debug = (bool)$modx->getOption('aixo.debug', null, false);
        $this->loadProviders();
    }

    /**
     * Load all provider classes from the Providers directory and instantiate them.
     */
    protected function loadProviders(): void {
        $providersDir = __DIR__ . '/Providers';
        if (!is_dir($providersDir)) {
            return;
        }
        // Include all PHP files in the Providers directory (except interfaces/abstracts)
        foreach (glob($providersDir . '/*.php') as $providerFile) {
            if (strpos($providerFile, 'Interface.php') !== false || strpos($providerFile, 'Abstract') !== false) {
                continue;
            }
            require_once($providerFile);
        }
        // Instantiate each provider class that implements the interface.
        foreach (get_declared_classes() as $className) {
            if (strpos($className, 'MODX\\Aixo\\Providers\\') === 0) {
                if (in_array(AixoProviderInterface::class, class_implements($className) ?: [])) {
                    /** @var AixoProviderInterface $provider */
                    $provider = new $className($this->modx);
                    $key = strtolower($provider->getKey());
                    $this->providers[$key] = $provider;
                }
            }
        }
    }

    /**
     * Get a provider by name/key.
     *
     * @param string $name
     * @return AixoProviderInterface|null
     */
    public function getProvider(string $name): ?AixoProviderInterface {
        $key = strtolower($name);
        return $this->providers[$key] ?? null;
    }

    /**
     * Return all loaded providers.
     *
     * @return AixoProviderInterface[]
     */
    public function getProviders(): array {
        return $this->providers;
    }

    /**
     * Process an AI request using a specified or default provider.
     * This method accepts a prompt and an array of options.
     * Options may include:
     *  - 'task': 'text' (default), 'image', or 'tts' (text-to-speech)
     *  - For text: 'model', 'max_tokens', 'temperature', 'metadata'
     *  - For image: 'model', 'n', 'size'
     *  - For tts: 'voice', 'language'
     *
     * @param string $prompt The input prompt.
     * @param array $options Additional options to override defaults.
     * @return string The AI-generated response, or an empty string on failure.
     */
    public function process(string $prompt, array $options = []): string {
        if (trim($prompt) === '') {
            return '';
        }
        
        // Determine which provider to use.
        $providerKey = strtolower($options['provider'] ?? $this->modx->getOption('aixo.default_provider', null, 'openai'));
        if (!isset($this->providers[$providerKey])) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[Aixo] Provider '{$providerKey}' is not available (not installed).");
            return '';
        }
        $provider = $this->providers[$providerKey];
        
        if (!$provider->isAvailable()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[Aixo] Provider '{$providerKey}' is not configured or available.");
            return '';
        }
        
        // Merge default options for text generation if task is text.
        if (!isset($options['task']) || strtolower($options['task']) === 'text') {
            if (empty($options['model'])) {
                $options['model'] = $this->modx->getOption('aixo.default_model', null, '');
            }
            if (empty($options['temperature'])) {
                $options['temperature'] = $this->modx->getOption('aixo.default_temperature', null, '0.7');
            }
        }
        
        if ($this->debug) {
            $this->modx->log(modX::LOG_LEVEL_INFO, "[Aixo] Request to provider '{$providerKey}' with prompt: " . $prompt);
            $this->modx->log(modX::LOG_LEVEL_INFO, "[Aixo] Options: " . print_r($options, true));
        }
        
        // Execute the request via the provider.
        $result = '';
        try {
            $result = (string)$provider->process($prompt, $options);
        } catch (\Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[Aixo] Exception in provider '{$providerKey}': " . $e->getMessage());
        }
        
        // Check for provider errors.
        $errorMsg = $provider->getLastError();
        if (!empty($errorMsg)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, "[Aixo] Error from provider '{$providerKey}': " . $errorMsg);
        } else {
            if ($this->debug) {
                $this->modx->log(modX::LOG_LEVEL_INFO, "[Aixo] Response from '{$providerKey}': " . $result);
            }
        }
        
        // Token tracking: for text generation, log tokens used if available.
        // Only applicable to text tasks.
        if (!isset($options['task']) || strtolower($options['task']) === 'text') {
            if (method_exists($provider, 'getLastRawResponse')) {
                $raw = $provider->getLastRawResponse();
                if (is_array($raw)) {
                    $tokensUsed = 0;
                    if (isset($raw['usage']['total_tokens'])) {
                        $tokensUsed = (int)$raw['usage']['total_tokens'];
                    } elseif (isset($raw['token_count'])) {
                        $tokensUsed = (int)$raw['token_count'];
                    }
                    if ($tokensUsed > 0) {
                        // Ensure the package is loaded so that xPDO knows about modAixoTokenUsage.
                        $this->modx->addPackage('aixo', $this->modx->getOption('core_path') . 'components/aixo/model/');
                        $usageLog = $this->modx->newObject('modAixoTokenUsage');
                        if ($usageLog) {
                            $metadata = $options['metadata'] ?? null;
                            $usageLog->fromArray([
                                'provider'  => $providerKey,
                                'model'     => $options['model'] ?? 'unknown',
                                'tokens'    => $tokensUsed,
                                'timestamp' => date('Y-m-d H:i:s'),
                                'metadata'  => $metadata,
                            ]);
                            $usageLog->save();
                        }
                    }
                }
            }
        }
        
        return $result;
    }
}
