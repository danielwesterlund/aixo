<?php
namespace MODX\Aixo\Providers;

interface AixoProviderInterface {
    /**
     * A unique provider key (identifier used in settings and code, e.g. "openai").
     */
    public function getKey(): string;

    /**
     * Human-readable provider name (for display, e.g. "OpenAI API").
     */
    public function getName(): string;

    /**
     * Whether this provider is available for use (e.g. proper configuration in place).
     */
    public function isAvailable(): bool;

    /**
     * Process an AI prompt and return the response text.
     * @param string $prompt   The input text/prompt for AI.
     * @param array $options   Options such as model, temperature, etc.
     * @return string          The AI-generated response (empty string on failure).
     */
    public function process(string $prompt, array $options = []): string;

    /**
     * Get a message for the last error (if any) that occurred in process().
     * Returns an empty string if the last operation was successful.
     */
    public function getLastError(): string;
}
