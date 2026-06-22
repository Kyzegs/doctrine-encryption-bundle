# Upgrade guide

## Migrating from `specshaper/encrypt-bundle`

The bundled Rector set migrates the complete public PHP API from
[`mogilvie/EncryptBundle`](https://github.com/mogilvie/EncryptBundle). It updates imports, fully-qualified names,
type declarations, attributes, inheritance, `::class` references, and PHPDoc class references. The legacy
`Annotations\Encrypted` marker is intentionally migrated to the modern `Attribute\Encrypted` class.

Back up the encryption key and the old YAML file first. Replace the package without running Symfony's Composer
scripts until the PHP and configuration migration is complete:

```console
composer remove specshaper/encrypt-bundle --no-scripts
composer require kyzegs/doctrine-encryption-bundle --no-scripts
composer require --dev rector/rector --no-scripts
```

Add the migration set to the application's `rector.php`:

```php
use Rector\Config\RectorConfig;
use Kyzegs\DoctrineEncryptionBundle\Rector\Set\DoctrineEncryptionSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config/bundles.php'])
    ->withSets([
        DoctrineEncryptionSetList::MIGRATE_FROM_SPEC_SHAPER_ENCRYPT_BUNDLE,
    ]);
```

Run Rector and review the changes:

```console
vendor/bin/rector process
```

Rector only edits PHP. If the old bundle is registered in `config/bundles.php`, including that file in
`withPaths()` replaces `SpecShaperEncryptBundle` with `DoctrineEncryptionBundle` automatically.

### Configuration and Symfony Flex

Flex recipes are useful for a fresh installation, but deliberately do not overwrite an existing application's
configuration. A recipe therefore cannot safely migrate `config/packages/spec_shaper_encrypt.yaml` or its secret.
Move the values into `config/packages/doctrine_encryption.yaml` and change the root key:

```yaml
doctrine_encryption:
    encrypt_key: '%env(DOCTRINE_ENCRYPTION_ENCRYPT_KEY)%'
    key_id: 'default'
```

Rename the environment variable in `.env.local` or the deployment secret store. Existing encryption material must
be preserved: changing the key makes existing ciphertext unreadable. The old options `is_disabled`, `connections`,
`listener_class`, `encryptor_class`, `annotation_classes`, and `enable_twig` keep the same names. The old `method`
option has been removed. See the sections below for the new key-provider, rotation, and blind-index options.

The recommended Flex integration is a recipe in `symfony/recipes-contrib`, maintained alongside releases of this
bundle. It should register `DoctrineEncryptionBundle` and create a minimal `doctrine_encryption.yaml` for new
installs; it should not attempt to delete or rewrite the legacy configuration.

## Upgrading to the modernized release

1. Back up the database and every active encryption key.
2. Upgrade the package and run the application test suite.
3. Configure a stable `key_id` for the existing key.
4. Keep using the existing key initially. Legacy CBC and GCM ciphertext is read automatically.
5. Run `bin/console encrypt:database rotate --dry-run`.
6. Rotate on a tested database copy, verify representative records, and only then rotate production in batches.

New ciphertext is larger because it contains a format version, algorithm, key ID, associated-data context, IV, and authentication tag. Migrate narrow encrypted `VARCHAR` columns to a sufficient size or `TEXT` before rotating.

### Attribute namespace

Use `Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted` and `BlindIndex` in new code. The historical `Annotations` namespace remains as a deprecated compatibility layer.

### Encryption algorithm

AES-256-GCM is now the default. `AesCbcEncryptor` remains available only for compatibility and should not be selected for new writes.

### Keys

Keys are strictly validated as base64-encoded 32-byte values. Add prior keys to `decryption_keys`, indexed by the key ID embedded in their ciphertext.

Custom key and encryptor implementations should now be registered as normal Symfony services and selected with `key_provider_service` or `encryptor_service`. The historical class-name factory remains for compatibility.

### Events

The modern event names are `EncryptEvent::class` and `DecryptEvent::class`. The historical `sseb.encrypt` and `sseb.decrypt` aliases remain registered during migration.

### Removed configuration and dependencies

The unused `method` option has been removed. MonologBundle and Sodium are no longer package requirements. Twig is optional and is only needed when `enable_twig` is used.
