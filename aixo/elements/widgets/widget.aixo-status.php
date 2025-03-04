<?php
/**
 * Aixo Status Dashboard Widget
 * Displays the default configuration and availability of AI providers.
 */

/** @var modX $modx */
$output = '';
// Attempt to get Aixo service
$aixo = $modx->services->has('aixo') ? $modx->services->get('aixo') : null;
if (!$aixo) {
    // If Aixo service isn't available, perhaps the extra isn't installed correctly
    $output .= '<p style="color:red;"><strong>Aixo service is not initialized.</strong></p>';
    return $output;
}

// Get system settings
$defaultProvider = $modx->getOption('aixo.default_provider', null, '(none)');
$defaultModel    = $modx->getOption('aixo.default_model', null, '');
$defaultTemp     = $modx->getOption('aixo.default_temperature', null, '');
$debugMode       = (bool) $modx->getOption('aixo.debug', null, false);

// Start building HTML output
$output .= '<h3>Aixo Configuration</h3>';
$output .= '<p><strong>Default Provider:</strong> ' . htmlspecialchars($defaultProvider) . '</p>';
$output .= '<p><strong>Default Model:</strong> ' . htmlspecialchars($defaultModel) . '</p>';
$output .= '<p><strong>Default Temperature:</strong> ' . htmlspecialchars($defaultTemp) . '</p>';
$output .= '<p><strong>Debug Mode:</strong> ' . ($debugMode ? 'On' : 'Off') . '</p>';

// List available providers and their status
$output .= '<h4>Available Providers:</h4><ul>';
$providers = $aixo->getProviders();
if (!empty($providers)) {
    /** @var MODX\Aixo\Providers\AixoProviderInterface $prov */
    foreach ($providers as $key => $prov) {
        $name = $prov->getName();
        $status = $prov->isAvailable() ? '✅ Ready' : '⚠️ Not Configured';
        // If this provider is the default, mark it
        $mark = ($key === strtolower($defaultProvider)) ? ' (default)' : '';
        $output .= '<li><strong>' . htmlspecialchars($name) . ":</strong> {$status}{$mark}</li>";
    }
} else {
    $output .= '<li>No providers loaded.</li>';
}
$output .= '</ul>';

// You can include additional info if needed, e.g., last run status or version.
return $output;
