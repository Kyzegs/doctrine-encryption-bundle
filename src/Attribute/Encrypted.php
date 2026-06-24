<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Encrypted
{
    public const FORMAT_SCALAR = 'scalar';
    public const FORMAT_JSON = 'json';

    public readonly string $format;

    public function __construct(string $format = self::FORMAT_SCALAR)
    {
        if (!in_array($format, [self::FORMAT_SCALAR, self::FORMAT_JSON], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported encrypted field format "%s".', $format));
        }

        $this->format = $format;
    }
}
