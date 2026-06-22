<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\Key\KeyProviderInterface;

interface KeyProviderAwareInterface
{
    public function setKeyProvider(KeyProviderInterface $keyProvider): void;
}
