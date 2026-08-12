<?php

declare(strict_types=1);

return [
  'gemini_structured_evaluation' => [
    'providers' => [
      'gemini-general' => [
        'driver' => 'gemini',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'gemini-structured' => [
        'driver' => 'gemini',
        'sdk_provider' => 'gemini',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [],
      ],
    ],
    'default_provider' => 'gemini-general',
    'failover_order' => [
      'gemini-general',
      'gemini-structured',
    ],
  ],

  'xai_orchestrator_text_generation' => [
    'providers' => [
      'xai-general' => [
        'driver' => 'xai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
    ],
    'default_provider' => 'xai-general',
    'failover_order' => [
      'xai-general',
    ],
  ],

  'xai_to_gemini_audio_review' => [
    'providers' => [
      'xai-general' => [
        'driver' => 'xai',
        'enabled' => true,
        'capabilities' => ['text_generation'],
        'options' => [],
      ],
      'xai-transcription' => [
        'driver' => 'xai',
        'enabled' => true,
        'capabilities' => ['audio_transcription'],
        'options' => [],
      ],
      'gemini-structured' => [
        'driver' => 'gemini',
        'sdk_provider' => 'gemini',
        'enabled' => true,
        'capabilities' => ['text_generation', 'structured_output'],
        'options' => [],
      ],
    ],
    'default_provider' => 'xai-general',
    'failover_order' => [
      'xai-general',
      'xai-transcription',
      'gemini-structured',
    ],
  ],
];
