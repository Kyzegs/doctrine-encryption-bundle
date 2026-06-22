<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Exception;

class EncryptException extends \RuntimeException
{
    public function __construct(
        ?string $message = null,
        private mixed $value = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?? 'sseb.exception.encryptionException', $code, $previous);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }
}
