<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Rector;

use Kyzegs\DoctrineEncryptionBundle\Rector\Set\DoctrineEncryptionSetList;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class MogilvieEncryptBundleUpgradeRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): \Iterator
    {
        yield [__DIR__.'/Fixture/mogilvie_upgrade.php.inc'];
    }

    public function provideConfigFilePath(): string
    {
        return DoctrineEncryptionSetList::MIGRATE_FROM_SPEC_SHAPER_ENCRYPT_BUNDLE;
    }
}
