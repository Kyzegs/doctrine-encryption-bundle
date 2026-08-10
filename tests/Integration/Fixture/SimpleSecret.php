<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted;

/** An encrypted entity that maps no blind index, as an application not using the feature would have. */
#[ORM\Entity]
#[ORM\Table(name: 'simple_secret')]
class SimpleSecret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Encrypted]
    public string $secret = '';
}
