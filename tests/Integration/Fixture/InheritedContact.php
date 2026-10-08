<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Integration\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Kyzegs\DoctrineEncryptionBundle\Attribute\BlindIndex;
use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted;

/**
 * Declares its fields the way Doctrine mapped superclasses normally do: private, on the parent.
 */
#[ORM\MappedSuperclass]
abstract class ContactBase
{
    #[ORM\Column(name: 'email', type: 'string', length: 512)]
    #[Encrypted]
    private string $email = '';

    #[ORM\Column(name: 'email_lookup', type: 'string', length: 64, nullable: true)]
    #[BlindIndex(sourceField: 'email', normalizer: BlindIndex::NORMALIZE_LOWERCASE)]
    private ?string $emailLookup = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getEmailLookup(): ?string
    {
        return $this->emailLookup;
    }

    /** The bundle writes this reflectively; the setter keeps the property a normal part of the entity API. */
    public function setEmailLookup(string $emailLookup): void
    {
        $this->emailLookup = $emailLookup;
    }
}

#[ORM\Entity]
#[ORM\Table(name: 'inherited_contact')]
class InheritedContact extends ContactBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;
}
