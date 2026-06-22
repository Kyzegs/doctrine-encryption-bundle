<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Event;

final class EncryptEvents
{
    public const ENCRYPT = EncryptEvent::class;
    public const DECRYPT = DecryptEvent::class;
    public const LEGACY_ENCRYPT = 'sseb.encrypt';
    public const LEGACY_DECRYPT = 'sseb.decrypt';
}
