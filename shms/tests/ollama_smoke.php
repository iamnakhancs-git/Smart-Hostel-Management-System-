<?php
// Run on your XAMPP PC: C:\xampp\php\php.exe tests\ollama_smoke.php
// This contacts your local model. It does not access MySQL or save/publish notices.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/ollama.php';
try {
    $config = shmsAIConfig();
    if ($config['provider'] !== 'ollama') { throw new RuntimeException('Set provider to ollama first.'); }
    echo shmsCheckOllama($config) . "\nGenerating a sample; please wait...\n";
    $draft = shmsGenerateOllama('Water supply raat 9 baje aaye gi', $config);
    echo $draft['title'] . "\n\n" . $draft['content'] . "\n\nPASS: local inference returned a valid draft. Review its facts.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n"); exit(1);
}
