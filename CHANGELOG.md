# Changelog

## Unreleased

Correctness and hardening release. See [UPGRADE.md](UPGRADE.md) for the required configuration changes.

- Fix an off-by-one length check that made an encrypted empty string unreadable on every subsequent load.
- Stop the unit of work from keeping the ciphertext it just wrote, which made every flush after an insert or
  update rewrite every encrypted column of that entity with a fresh IV.
- Encrypt plaintext that merely ends with the `<ENC>` suffix instead of storing it in the clear.
- Bind a ciphertext to the field it was written to, refusing reads from another field unless
  `verify_associated_data` is disabled for a rename migration.
- Refuse unauthenticated AES-CBC ciphertext, through the versioned envelope and the unversioned legacy path
  alike, unless `allow_legacy_cbc` is enabled for a migration.
- Resolve encrypted and blind-index fields that a mapped superclass declares as private properties, which
  previously threw a `ReflectionException` or silently skipped the field.
- Stop `blind_index_key` from silently falling back to the encryption key. It remains optional for
  applications that map no blind index, and is required, and must differ from `encrypt_key`, for those that
  do.
- Stop `is_disabled: true` from decrypting on load and letting the plaintext be written back to the column.
- Keep plaintext out of `EncryptException`, which exposed it to any handler that dumps exception properties.
- Add `BlindIndexQueryHelper`, which builds blind-index lookups from the mapping so a search no longer has to
  repeat the normalizer declared on the attribute.
- Add the `encrypted_text` DBAL type as an alternative to `#[Encrypted]`. It converts at the driver boundary
  rather than through lifecycle events, so array and partial hydration are decrypted too. It supports neither
  blind indexes, encrypted JSON, `encrypt:database`, nor field binding, because a DBAL type has no column
  context.

## 1.1.0 (2026-06-24) Encrypted JSON arrays and typed internals

- Add explicit encrypted array support for Doctrine `json` fields through `#[Encrypted(format: Encrypted::FORMAT_JSON)]` and the `encrypted: json` mapping option.
- Store JSON ciphertext in an exact, versioned JSON wrapper while retaining reads and maintenance-command migration of plaintext JSON arrays.
- Replace internal database-command array shapes with typed metadata objects, add a typed ciphertext-envelope decoder while retaining the existing array API, and make listener plaintext tracking object-safe.

## 1.0.0 (2026-06-23) Re-release with modernized features and security improvements

- Add a tested Rector set and upgrade guide for migrating from `specshaper/encrypt-bundle`, including modern blind-index, key-provider, hashing, mapping, and ciphertext-envelope symbols used by downstream forks.
- Make versioned AES-256-GCM authenticated encryption the default while retaining legacy CBC/GCM reads.
- Add key IDs, retired decryption keys, a key-provider interface, and the `rotate` maintenance operation.
- Store associated-data context in the ciphertext envelope so mapped field renames remain decryptable.
- Validate keys, envelopes, base64, IVs, tags, and OpenSSL failures strictly.
- Add modern `Attribute` classes with compatibility wrappers for the historical `Annotations` namespace.
- Support Doctrine's `encrypted` field mapping option.
- Preserve encrypted fields inside Doctrine embeddables across lifecycle events and maintenance commands.
- Fix multi-connection event registration and restore inserted plaintext during `postPersist`.
- Harden database commands with dry runs, confirmation, batches, transactions, quoted identifiers, and composite scalar IDs.
- Add Doctrine integration tests, PHPStan, PHP-CS-Fixer, Rector, dependency auditing, and an expanded CI matrix.
- Replace YAML service definitions with native PHP configuration and remove unused Monolog/Sodium requirements.
- Rewrite installation, rotation, migration, and security documentation.

## 3.2.0 (2024-05-30) Update
Resolve issue of child classes containing Encrypted values not persisting correctly.  
Made Twig extension an opt-out in config.  
Check for null encrypted values on insert.  
Fix command for bulk update/changes to the database.  
Restore backward compatibility for 5.4.  
Add AES-GCM-256 Encryptor  

## 3.1.0 (2023-02-22) Update
Add attribute support for #[Encrypted] attributes instead of @Encrypted annotations.  
Add option to catch doctrine events from multiple connections.  
Add encrypt and decrypt CLI commands.  
Refactor for symfony flex and Symfony 6 recommended third party bundle structure.  

## 3.0.1 (2022-03-13) Symfony 6 and PHP 8
Major backward compatibility breaking change to Symfony 6 and PHP 8.

### Encyptor Factory
- Remove logging and event dispatcher constructors
- Change constructor to allow passing of an optional encryptor class name.

Service definition was:
```yaml
    # Factory to create the encryptor/decryptor
    Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorFactory:
        arguments: ['@logger', '@event_dispatcher']
        tags:
            - { name: monolog.logger, channel: app }
        
    Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface:
        factory: ['@Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorFactory','createService']
        arguments:
            - '%doctrine_encryption.method%'
            - '%doctrine_encryption.encrypt_key%'
```
Service definition becomes:
```yaml
    # Factory to create the encryptor/decryptor
    Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorFactory:
        arguments: ['@event_dispatcher']
        tags:
            - { name: monolog.logger, channel: app }

    # The encryptor service created by the factory according to the passed method and using the encrypt_key
    Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorInterface:
        factory: ['@Kyzegs\DoctrineEncryptionBundle\Encryptors\EncryptorFactory','createService']
        arguments:
            $encryptKey: '%doctrine_encryption.encrypt_key%'
            $encryptorClass: '%doctrine_encryption.encryptor_class%' #optional
```
