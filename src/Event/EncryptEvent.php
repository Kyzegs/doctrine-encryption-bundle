<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class EncryptEvent extends Event implements EncryptEventInterface
{
    public function __construct(
        /**
         * The string / object to be encrypted or decrypted.
         */
        protected ?string $value,
    ) {
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
