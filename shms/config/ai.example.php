<?php
// Copy to ai.local.php (excluded from version control), or use server environment variables.
// Defaults already work with Ollama on the same Windows PC as XAMPP.
// No API key or account is needed for the local model.
return [
    'provider' => 'ollama',
    'endpoint' => 'http://127.0.0.1:11434/api/chat',
    'api_key' => '',
    'model' => 'qwen3:4b',
    'timeout' => 180,
];
