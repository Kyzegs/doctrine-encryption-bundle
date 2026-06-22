<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Encryptors;

use Kyzegs\DoctrineEncryptionBundle\Encryptors\AesGcmEncryptor;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Key\StaticKeyProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author David Dadon <david.dadon@neftys.fr>
 */
class AesGcmEncryptorTest extends \PHPUnit\Framework\TestCase
{
    private const TEST_KEY = 'YBmNcBGfrZoayB+V254wdYa/abvxSUWJsjCtlMc1tRI=';

    public function testEncryptNullReturnsNull(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $encryptor->setDefaultAssociatedData(null);

        // When
        $result = $encryptor->encrypt(null);

        // Then
        $this->assertTrue(null === $result);
    }

    public function testEncryptOnlySuffix(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $encryptor->setDefaultAssociatedData(null);

        // When
        $result = $encryptor->encrypt('<ENC>');

        // Then
        $this->assertTrue('<ENC>' === $result);
    }

    public function testEncryptAndDecryptReturnsOriginalValue(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $encryptor->setDefaultAssociatedData(null);
        $value = 'Honey, where are my pants?';

        // When
        $encryptedValue = $encryptor->encrypt($value);

        // Then
        $this->assertTrue($encryptedValue !== $value);

        // When
        $decrypted = $encryptor->decrypt($encryptedValue);

        // Then
        $this->assertTrue($decrypted === $value);
    }

    /**
     * @throws \Exception
     */
    public function testDecryptNullReturnsNull(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $encryptor->setDefaultAssociatedData(null);

        // When
        $result = $encryptor->decrypt(null);

        // Then
        $this->assertTrue(null === $result);
    }

    public function testDecryptWithoutSuffixReturnsOrignialValue(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $encryptor->setDefaultAssociatedData(null);

        // When
        $result = $encryptor->decrypt('Test value <ENC');

        // Then
        $this->assertTrue('Test value <ENC' === $result);
    }

    public function testDecryptReturnsExpectedValue(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $encryptor->setDefaultAssociatedData(null);

        // When
        $decrypted = $encryptor->decrypt('g5wofClWz/wG44umXsUw+wAHQiqhTmo0eGIcODXvV6bjU3xDR8paa7wzu8EoJh0xGOJPD+Ue<ENC>');

        // Then
        $this->assertTrue('Honey, where are my pants?' === $decrypted);
    }

    public function testEncryptWithColumnName(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $value = 'Honey, where are my pants?';

        // When
        $encryptedValue = $encryptor->encrypt($value, 'columnName');

        // Then
        $this->assertFalse($encryptedValue === $value);

        // When
        $decrypted = $encryptor->decrypt($encryptedValue, 'columnName');

        // Then
        $this->assertTrue($decrypted === $value);
    }

    public function testEncryptWithDefaultAssociatedData(): void
    {
        // Given
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $encryptor->setDefaultAssociatedData('DefaultAssociatedData');
        $value = 'Honey, where are my pants?';

        // When
        $encryptedValue = $encryptor->encrypt($value);

        // Then
        $this->assertFalse($encryptedValue === $value);

        // When
        $decrypted = $encryptor->decrypt($encryptedValue);

        // Then
        $this->assertTrue($decrypted === $value);
    }

    public function testNewCiphertextIsVersionedAndCarriesItsAssociatedData(): void
    {
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setKeyProvider(new StaticKeyProvider(self::TEST_KEY, 'primary'));

        $ciphertext = $encryptor->encrypt('secret', 'oldPropertyName');

        $this->assertIsString($ciphertext);
        $this->assertStringStartsWith('SSEB1:gcm:primary:', $ciphertext);
        $this->assertSame('secret', $encryptor->decrypt($ciphertext, 'renamedProperty'));
    }

    public function testRetiredKeyCanDecryptBeforeRotation(): void
    {
        $oldEncryptor = new AesGcmEncryptor(new EventDispatcher());
        $oldEncryptor->setKeyProvider(new StaticKeyProvider(self::TEST_KEY, '2025'));
        $ciphertext = $oldEncryptor->encrypt('rotate me', 'secret');

        $newKey = base64_encode(str_repeat('B', 32));
        $newEncryptor = new AesGcmEncryptor(new EventDispatcher());
        $newEncryptor->setKeyProvider(new StaticKeyProvider($newKey, '2026', ['2025' => self::TEST_KEY]));

        $this->assertSame('rotate me', $newEncryptor->decrypt($ciphertext, 'secret'));
        $newCiphertext = $newEncryptor->encrypt('rotate me', 'secret');
        $this->assertIsString($newCiphertext);
        $this->assertStringStartsWith('SSEB1:gcm:2026:', $newCiphertext);
    }

    public function testTamperedCiphertextFailsAuthentication(): void
    {
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);
        $ciphertext = $encryptor->encrypt('secret', 'field');
        $this->assertIsString($ciphertext);
        $payloadSeparator = strrpos($ciphertext, ':');
        $this->assertNotFalse($payloadSeparator);
        $payloadStart = $payloadSeparator + 1;
        $ciphertext[$payloadStart] = 'A' === $ciphertext[$payloadStart] ? 'B' : 'A';

        $this->expectException(EncryptException::class);
        $encryptor->decrypt($ciphertext, 'field');
    }

    public function testDefaultGcmEncryptorReadsLegacyCbcCiphertext(): void
    {
        $encryptor = new AesGcmEncryptor(new EventDispatcher());
        $encryptor->setSecretKey(self::TEST_KEY);

        $this->assertSame(
            'Honey, where are my pants?',
            $encryptor->decrypt('5hhCphjZSgXvZgAu9t3O99fnFsdDgHr67QR7lf8NVZdgHTH8Dj/gsfQ+AI2agJOc<ENC>'),
        );
    }
}
