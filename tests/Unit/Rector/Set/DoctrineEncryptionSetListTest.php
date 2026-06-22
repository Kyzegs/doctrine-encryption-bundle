<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Rector\Set;

use Kyzegs\DoctrineEncryptionBundle\Rector\Set\DoctrineEncryptionSetList;
use PHPUnit\Framework\TestCase;

final class DoctrineEncryptionSetListTest extends TestCase
{
    public function testMigrationSetIsDistributedWithTheBundle(): void
    {
        self::assertFileExists(DoctrineEncryptionSetList::MIGRATE_FROM_SPEC_SHAPER_ENCRYPT_BUNDLE);
    }
}
