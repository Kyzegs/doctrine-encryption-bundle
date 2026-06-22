<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/rector'])
    ->withPhpSets(php82: true)
    ->withPreparedSets(codeQuality: true, deadCode: true, typeDeclarations: true);
