<?php

declare(strict_types=1);

namespace SpecShaper\EncryptBundle\Tests\Unit\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\TestCase;
use SpecShaper\EncryptBundle\Annotations\Encrypted;
use SpecShaper\EncryptBundle\Mapping\EncryptedFieldMetadataProvider;

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
    }

    public function testFindsExternalMappingOption(): void
    {
        $metadata = $this->metadata([
            'externalField' => ['options' => ['encrypted' => true]],
            'plainField' => [],
        ]);

        $fields = (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);

        self::assertSame(['externalField'], array_keys($fields));
    }

    public function testAcceptsBooleanValueSerializedByExternalMappingDriver(): void
    {
        $metadata = $this->metadata([
            'externalField' => ['options' => ['encrypted' => 'true']],
        ]);

        $fields = (new EncryptedFieldMetadataProvider([Encrypted::class]))->getForClassMetadata($metadata);

        self::assertSame(['externalField'], array_keys($fields));
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

    public string $plainField;
}
