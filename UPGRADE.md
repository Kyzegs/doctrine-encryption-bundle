# Upgrade guide

## Upgrading to the modernized release

1. Back up the database and every active encryption key.
2. Upgrade the package and run the application test suite.
3. Configure a stable `key_id` for the existing key.
4. Keep using the existing key initially. Legacy CBC and GCM ciphertext is read automatically.
5. Run `bin/console encrypt:database rotate --dry-run`.
6. Rotate on a tested database copy, verify representative records, and only then rotate production in batches.

New ciphertext is larger because it contains a format version, algorithm, key ID, associated-data context, IV, and authentication tag. Migrate narrow encrypted `VARCHAR` columns to a sufficient size or `TEXT` before rotating.

### Attribute namespace

Use `SpecShaper\EncryptBundle\Attribute\Encrypted` and `BlindIndex` in new code. The historical `Annotations` namespace remains as a deprecated compatibility layer.

### Encryption algorithm

AES-256-GCM is now the default. `AesCbcEncryptor` remains available only for compatibility and should not be selected for new writes.

### Keys

Keys are strictly validated as base64-encoded 32-byte values. Add prior keys to `decryption_keys`, indexed by the key ID embedded in their ciphertext.

Custom key and encryptor implementations should now be registered as normal Symfony services and selected with `key_provider_service` or `encryptor_service`. The historical class-name factory remains for compatibility.

### Events

The modern event names are `EncryptEvent::class` and `DecryptEvent::class`. The historical `sseb.encrypt` and `sseb.decrypt` aliases remain registered during migration.

### Removed configuration and dependencies

The unused `method` option has been removed. MonologBundle and Sodium are no longer package requirements. Twig is optional and is only needed when `enable_twig` is used.
