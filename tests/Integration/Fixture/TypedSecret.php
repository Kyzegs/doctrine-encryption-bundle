<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Kyzegs\DoctrineEncryptionBundle\Types\EncryptedTextType;

/** Encrypted through the DBAL type rather than the listener, so it carries no #[Encrypted] attribute. */
#[ORM\Entity]
#[ORM\Table(name: 'typed_secret')]
class TypedSecret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: EncryptedTextType::NAME)]
    public string $secret = '';

    #[ORM\Column(name: 'optional_secret', type: EncryptedTextType::NAME, nullable: true)]
    public ?string $optionalSecret = null;
}
