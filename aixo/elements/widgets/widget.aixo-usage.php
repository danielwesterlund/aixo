<?php
/** @var modX $modx */
$componentPath = $modx->getOption('core_path') . 'components/aixo/';
$modx->addPackage('aixo', $componentPath . 'model/');

// Get the most recent usage log
$query = $modx->newQuery('modAixoTokenUsage');
$query->sortby('timestamp', 'DESC');
$query->limit(1);
$lastEntry = $modx->getObject('modAixoTokenUsage', $query);

if ($lastEntry) {
    $lastProvider = $lastEntry->get('provider');
    $lastModel    = $lastEntry->get('model');
    $lastTokens   = $lastEntry->get('tokens');
    $lastTime     = $lastEntry->get('timestamp');
    $lastInfo = sprintf(
        "Last Request: %s (model %s) used %s tokens at %s.",
        $lastProvider,
        $lastModel,
        $lastTokens,
        $lastTime
    );
} else {
    $lastInfo = "Last Request: (no data yet)";
}

// Aggregate total tokens per provider and model
$statsList = [];
$q = $modx->newQuery('modAixoTokenUsage');
$q->select([
    'provider',
    'model',
    'SUM(`tokens`) AS total_tokens',
]);
// Use array grouping for cleaner grouping by both fields at once.
$q->groupby(['provider', 'model']);

if ($q->prepare() && $q->stmt->execute()) {
    $rows = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $provider = $row['provider'] ?: 'Unknown';
        $model    = $row['model'] ?: 'Unknown';
        $total    = (int) $row['total_tokens'];
        $statsList[] = sprintf("%s (model %s): %d tokens", $provider, $model, $total);
    }
}

// Build HTML output
$output  = "<div class='aixo-token-stats'>";
$output .= "<p><strong>{$lastInfo}</strong></p>";
if (!empty($statsList)) {
    $output .= "<h4>Total Tokens Used (by Provider/Model):</h4><ul>";
    foreach ($statsList as $status) {
        $output .= "<li>{$status}</li>";
    }
    $output .= "</ul>";
}
$output .= "</div>";

return $output;
