<?php

return [

    'chat' => env('AI_RUNTIME_CHAT', 'python'),

    'document_process' => env('AI_RUNTIME_DOCUMENT_PROCESS', 'python'),

    'document_summarize' => env('AI_RUNTIME_DOCUMENT_SUMMARIZE', 'python'),

    'document_delete' => env('AI_RUNTIME_DOCUMENT_DELETE', 'python'),

    'shadow' => [
        'enabled' => env('AI_SHADOW_ENABLED', false),
        'log_parity' => env('AI_SHADOW_LOG_PARITY', true),
    ],

];