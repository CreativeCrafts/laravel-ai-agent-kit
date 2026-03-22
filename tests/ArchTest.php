<?php

declare(strict_types=1);

arch('it will not use debugging functions')
  ->expect(['dd', 'dump', 'ray'])
  ->each->not->toBeUsed();

arch('public contracts do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Contracts')
  ->not->toUse('Laravel\\Ai');

arch('public blueprints do not depend on laravel ai sdk types')
  ->expect('CreativeCrafts\\LaravelAiAgentKit\\Blueprints')
  ->not->toUse('Laravel\\Ai');

arch('public vector contracts and strategy types do not depend on laravel ai sdk types')
  ->expect([
    'CreativeCrafts\\LaravelAiAgentKit\\Contracts\\Vector',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\Exceptions',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\VectorDocument',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\VectorSearchQuery',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\VectorSearchResult',
    'CreativeCrafts\\LaravelAiAgentKit\\Vector\\SdkBackedVectorAdapterStrategy',
  ])
  ->not->toUse('Laravel\\Ai');
