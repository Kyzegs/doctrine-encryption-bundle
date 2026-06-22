<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Annotations;

use Kyzegs\DoctrineEncryptionBundle\Annotations\BlindIndex;
use PHPUnit\Framework\TestCase;

class BlindIndexTest extends TestCase
{
    public function testSourceFieldAndNormalizerCanBeConfigured(): void
    {
        $attribute = new BlindIndex(
            sourceField: 'email',
            normalizer: BlindIndex::NORMALIZE_LOWERCASE
        );

        $this->assertSame('email', $attribute->getSourceField());
        $this->assertSame(BlindIndex::NORMALIZE_LOWERCASE, $attribute->getNormalizer());
    }
}
