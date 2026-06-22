<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Encryptors;

use SpecShaper\EncryptBundle\Key\KeyProviderInterface;

interface KeyProviderAwareInterface
{
    public function setKeyProvider(KeyProviderInterface $keyProvider): void;
}
