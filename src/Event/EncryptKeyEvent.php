<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class EncryptKeyEvent extends Event
{
    /**
     * The key to be used instead of the parameter key.
     */
    protected ?string $key = null;

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): self
    {
        $this->key = $key;

        return $this;
    }
}
