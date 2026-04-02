<?php

declare(strict_types=1);

return [
  'name' => 'support.reply',
  'current_version' => '2.0.0',
  'versions' => [
    '1.0.0' => [
      'template' => '1.0.0.md',
      'variables' => ['name', 'ticket_id'],
    ],
    '2.0.0' => [
      'template' => '2.0.0.md',
      'variables' => ['name', 'ticket_id', 'agent'],
    ],
  ],
];
