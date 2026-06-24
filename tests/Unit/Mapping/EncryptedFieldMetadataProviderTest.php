<?php

declare(strict_types=1);

namespace Kyzegs\DoctrineEncryptionBundle\Tests\Unit\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Kyzegs\DoctrineEncryptionBundle\Annotations\Encrypted;
use Kyzegs\DoctrineEncryptionBundle\Attribute\Encrypted as EncryptedAttribute;
use Kyzegs\DoctrineEncryptionBundle\Exception\EncryptException;
use Kyzegs\DoctrineEncryptionBundle\Mapping\EncryptedFieldMetadataProvider;
use PHPUnit\Framework\TestCase;

final class EncryptedFieldMetadataProviderTest extends TestCase
{
    public function testFindsAttributeMapping(): void
    {
        $metadata = $this->metadata([
            'attributeField' => [],
            'plainField' => [],
        ]);

        $fields = (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);

        self::assertSame(['attributeField'], array_keys($fields));
        self::assertSame(EncryptedAttribute::FORMAT_SCALAR, $fields['attributeField']->getFormat());
    }

    public function testFindsExternalMappingOption(): void
    {
        $metadata = $this->metadata([
            'externalField' => ['options' => ['encrypted' => true]],
            'plainField' => [],
        ]);

        $fields = (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);

        self::assertSame(['externalField'], array_keys($fields));
        self::assertSame(EncryptedAttribute::FORMAT_SCALAR, $fields['externalField']->getFormat());
    }

    public function testAcceptsBooleanValueSerializedByExternalMappingDriver(): void
    {
        $metadata = $this->metadata([
            'externalField' => ['options' => ['encrypted' => 'true']],
        ]);

        $fields = (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);

        self::assertSame(['externalField'], array_keys($fields));
    }

    public function testFindsJsonAttributeAndExternalMappingFormats(): void
    {
        $metadata = $this->metadata([
            'jsonAttributeField' => ['type' => 'json'],
            'externalJsonField' => ['type' => 'json', 'options' => ['encrypted' => 'json']],
        ]);

        $fields = (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);

        self::assertSame(EncryptedAttribute::FORMAT_JSON, $fields['jsonAttributeField']->getFormat());
        self::assertSame(EncryptedAttribute::FORMAT_JSON, $fields['externalJsonField']->getFormat());
    }

    public function testRejectsJsonFormatOnNonJsonDoctrineField(): void
    {
        $metadata = $this->metadata(['jsonAttributeField' => ['type' => 'string']]);

        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('must use Doctrine type "json"');

        (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);
    }

    public function testRejectsConflictingAttributeAndMappingFormats(): void
    {
        $metadata = $this->metadata([
            'attributeField' => ['type' => 'json', 'options' => ['encrypted' => 'json']],
        ]);

        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('declares conflicting formats');

        (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);
    }

    public function testAttributeRejectsUnknownFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported encrypted field format "xml"');

        new EncryptedAttribute('xml');
    }

    public function testRejectsUnknownExternalMappingFormat(): void
    {
        $metadata = $this->metadata([
            'externalJsonField' => ['type' => 'json', 'options' => ['encrypted' => 'xml']],
        ]);

        $this->expectException(EncryptException::class);
        $this->expectExceptionMessage('Unsupported encrypted field mapping format');

        (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     *
     * @return ClassMetadata<object>
     */
    private function metadata(array $fields): ClassMetadata
    {
        $metadata = new ClassMetadata(EncryptedFieldMetadataProviderTestEntity::class);
        $metadata->initializeReflection(new RuntimeReflectionService());

        foreach ($fields as $fieldName => $mapping) {
            $metadata->mapField($mapping + [
                'fieldName' => $fieldName,
                'type' => 'string',
                'columnName' => $fieldName,
            ]);
        }

        $metadata->wakeupReflection(new RuntimeReflectionService());

        return $metadata;
    }
}

final class EncryptedFieldMetadataProviderTestEntity
{
    #[Encrypted]
    public string $attributeField;

    public string $externalField;

    /** @var array<mixed> */
    #[Encrypted(format: Encrypted::FORMAT_JSON)]
    public array $jsonAttributeField;

    /** @var array<mixed> */
    public array $externalJsonField;

    public string $plainField;
}
