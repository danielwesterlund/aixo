<?php
namespace MODX\Aixo\Providers;

use MODX\Revolution\modX;

class LocalProvider implements AixoProviderInterface {
    protected $modx;
    protected $lastError = '';

    public function __construct(modX $modx) {
        $this->modx = $modx;
    }

    public function getKey(): string {
        return 'local';
    }

    public function getName(): string {
        return 'Local AI (Placeholder)';
    }

    public function isAvailable(): bool {
        // Always "available" since no external config is needed for this placeholder
        return true;
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    public function process(string $prompt, array $options = []): string {
        $this->lastError = '';  // reset error (this provider does not actually produce errors in this stub)
        // This is just a dummy implementation. In a real local AI, you'd integrate with a local model here.
        // For now, just return a placeholder response using the prompt.
        $response = "[Local AI placeholder] Response for: \"" . $prompt . "\"";
        return $response;
    }
}
