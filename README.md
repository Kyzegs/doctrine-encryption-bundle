# SpecShaper Encrypt Bundle

Field-level authenticated encryption and blind indexes for Doctrine entities in Symfony applications.

## Requirements

- PHP 8.2 or newer
- Symfony 6.4, 7.4, or 8.x
- Doctrine ORM 2.20 or 3.x
- OpenSSL with AES-256-GCM support

## Installation

```bash
composer require specshaper/encrypt-bundle
```

Register the bundle if Symfony Flex has not already done so:

```php
// config/bundles.php
return [
    SpecShaper\EncryptBundle\SpecShaperEncryptBundle::class => ['all' => true],
];
```

Generate a 256-bit key:

```bash
bin/console encrypt:genkey
```

Store the result in a secret manager or an uncommitted environment file:

```dotenv
SPEC_SHAPER_ENCRYPT_KEY=base64-encoded-key
SPEC_SHAPER_BLIND_INDEX_KEY=a-different-secret
```

Configure the bundle:

```yaml
# config/packages/spec_shaper_encrypt.yaml
spec_shaper_encrypt:
    encrypt_key: '%env(SPEC_SHAPER_ENCRYPT_KEY)%'
    blind_index_key: '%env(SPEC_SHAPER_BLIND_INDEX_KEY)%'
    key_id: '2026-01'
```

The encryption and blind-index keys should be different. Never commit either key.

## Encrypting fields

Use the PHP attribute on a Doctrine string field. Allow room for the versioned ciphertext envelope; `TEXT` is the least surprising choice.

```php
use Doctrine\ORM\Mapping as ORM;
use SpecShaper\EncryptBundle\Attribute\Encrypted;

#[ORM\Column(type: 'text', nullable: true)]
#[Encrypted]
private ?string $personalNumber = null;
```

New values are written with AES-256-GCM in a versioned, authenticated envelope. The entity contains plaintext while it is in memory; Doctrine stores ciphertext. Existing unversioned AES-CBC and AES-GCM values from older bundle versions remain readable.

External XML, YAML, or PHP Doctrine mappings can opt in without modifying the entity:

```xml
<field name="personalNumber" type="text">
    <options>
        <option name="encrypted">true</option>
    </options>
</field>
```

The equivalent field option is `encrypted: true`.

Encrypted scalar properties inside Doctrine embeddables are supported. Mark the embeddable property normally; bundle uses Doctrine's nested field path for encryption, decryption, change tracking, and database maintenance:

```php
#[ORM\Embeddable]
final class ContactDetails
{
    #[ORM\Column(type: 'text')]
    #[Encrypted]
    public string $privateNote;
}
```

## Searching encrypted values

Randomized encryption cannot be queried by plaintext and must not carry a meaningful unique constraint. Add a blind-index column instead:

```php
use SpecShaper\EncryptBundle\Attribute\BlindIndex;
use SpecShaper\EncryptBundle\Attribute\Encrypted;

#[ORM\Column(type: 'text')]
#[Encrypted]
private string $email;

#[ORM\Column(type: 'string', length: 64, unique: true, nullable: true)]
#[BlindIndex(sourceField: 'email', normalizer: BlindIndex::NORMALIZE_LOWERCASE)]
private ?string $emailLookupHash = null;
```

Create the same hash when querying:

```php
use SpecShaper\EncryptBundle\Hashers\BlindIndexHasherInterface;

$hash = $blindIndexHasher->hash($email, BlindIndex::NORMALIZE_LOWERCASE);
$user = $repository->findOneBy(['emailLookupHash' => $hash]);
```

Available normalizers are `none`, `trim`, `lowercase`, and `uppercase`. Blind indexes reveal equality patterns; use them only for fields that genuinely need lookups.

Rebuild indexes in bounded batches:

```bash
bin/console encrypt:blind-index --batch-size=500 --dry-run
bin/console encrypt:blind-index --batch-size=500
```

## Key rotation

Change the current key and key ID, then retain old keys under `decryption_keys`:

```yaml
spec_shaper_encrypt:
    encrypt_key: '%env(SPEC_SHAPER_ENCRYPT_KEY_2026)%'
    key_id: '2026'
    decryption_keys:
        '2025': '%env(SPEC_SHAPER_ENCRYPT_KEY_2025)%'
```

Back up the database, inspect the operation, and rotate in batches:

```bash
bin/console encrypt:database rotate --dry-run
bin/console encrypt:database rotate --batch-size=250
```

Remove a retired key only after every value using its key ID has been rotated and verified.

## Database maintenance

The maintenance command supports `encrypt`, `decrypt`, and `rotate`, custom and composite scalar identifiers, quoted identifiers, transactions, batches, confirmation, and dry runs:

```bash
bin/console encrypt:database encrypt --dry-run
bin/console encrypt:database decrypt --manager=tenant --batch-size=100
```

Association identifiers are rejected because they cannot be updated safely by the low-level command. Always take a verified backup before a write operation.

## Multiple Doctrine connections

```yaml
spec_shaper_encrypt:
    encrypt_key: '%env(SPEC_SHAPER_ENCRYPT_KEY)%'
    connections: ['default', 'tenant']
```

## Optional Twig filter

Install Twig and leave `enable_twig` enabled to expose `value|decrypt`:

```bash
composer require symfony/twig-bundle
```

Decrypting in templates increases the number of places where plaintext exists. Prefer decrypting only at the application boundary that needs it.

## Custom integrations

`KeyProviderInterface` is the extension point for Vault, KMS, HSM, or another secret source. It returns binary 32-byte keys and supports lookup by ciphertext key ID. Register the implementation as a service and configure it directly:

```yaml
spec_shaper_encrypt:
    key_provider_service: 'App\\Encryption\\KmsKeyProvider'
    blind_index_key: '%env(SPEC_SHAPER_BLIND_INDEX_KEY)%'
```

Likewise, `encryptor_service` accepts any registered `EncryptorInterface` service with arbitrary injected dependencies. `encryptor_class` remains available as a deprecated compatibility path for classes using the historical event-dispatcher constructor.

## Security notes

- Encryption does not replace access control, TLS, backups, audit logging, or database hardening.
- Losing an encryption key permanently loses the corresponding data.
- Application compromise can expose plaintext and keys while the process is running.
- Blind indexes permit equality analysis and should use a separate high-entropy secret.
- Test restoration and rotation on a copy of production data before operating on production.

See [UPGRADE.md](UPGRADE.md) before upgrading an existing installation and [SECURITY.md](SECURITY.md) for vulnerability reporting.

## Development

```bash
composer test
composer analyse
composer cs:check
composer rector:check
composer audit
```

## License

MIT. See [LICENSE](LICENSE).
