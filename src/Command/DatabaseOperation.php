<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Command;

/** @internal */
enum DatabaseOperation: string
{
    case ENCRYPT = 'encrypt';
    case DECRYPT = 'decrypt';
    case ROTATE = 'rotate';
}
