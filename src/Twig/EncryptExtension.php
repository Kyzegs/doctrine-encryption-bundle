<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Twig;

use Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class EncryptExtension extends AbstractExtension
{
    public function __construct(private readonly EncryptorInterface $encryptor)
    {
    }

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [new TwigFilter('decrypt', $this->decryptFilter(...))];
    }

    public function decryptFilter(?string $data): ?string
    {
        return $this->encryptor->decrypt($data);
    }
}
