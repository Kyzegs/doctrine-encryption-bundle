<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Annotations;

/** @deprecated Use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted. */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Encrypted extends \Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted
{
}
