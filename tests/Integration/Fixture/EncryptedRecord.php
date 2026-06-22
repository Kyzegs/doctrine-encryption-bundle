<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Tests\Integration\Fixture;

use Doctrine\ORM\Mapping as ORM;
use SpecShaper\EncryptBundle\Attribute\BlindIndex;
use SpecShaper\EncryptBundle\Attribute\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'encrypted_record')]
class EncryptedRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(name: 'secret_value', type: 'string', length: 512)]
    #[Encrypted]
    public string $secret;

    #[ORM\Column(name: 'mapped_secret', type: 'string', length: 512, options: ['encrypted' => true])]
    public string $mappedSecret;

    #[ORM\Embedded(class: EncryptedContact::class, columnPrefix: 'contact_')]
    public EncryptedContact $contact;

    #[ORM\Column(name: 'secret_lookup', type: 'string', length: 64, nullable: true)]
    #[BlindIndex(sourceField: 'secret', normalizer: BlindIndex::NORMALIZE_LOWERCASE)]
    public ?string $secretLookup = null;
}

#[ORM\Embeddable]
class EncryptedContact
{
    public function __construct(
        #[ORM\Column(type: 'string', length: 512)]
        #[Encrypted]
        public string $token,
    ) {
    }
}
