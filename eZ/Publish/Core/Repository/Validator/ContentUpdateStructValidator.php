<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Repository\Validator;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct;
use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\FieldType\FieldTypeRegistry;
use eZ\Publish\Core\FieldType\ValidationError;
use eZ\Publish\Core\Repository\Mapper\ContentMapper;
use eZ\Publish\SPI\Persistence\Content\Language\Handler;
use eZ\Publish\SPI\Repository\Validator\ContentValidator;

class ContentUpdateStructValidator implements ContentValidator
{
    /** @var \eZ\Publish\Core\Repository\Mapper\ContentMapper */
    private $contentMapper;

    /** @var \eZ\Publish\Core\FieldType\FieldTypeRegistry */
    private $fieldTypeRegistry;

    /** @var \eZ\Publish\Core\Persistence\Legacy\Content\Language\Handler */
    private $contentLanguageHandler;

    public function __construct(
        ContentMapper $contentMapper,
        FieldTypeRegistry $fieldTypeRegistry,
        Handler $contentLanguageHandler
    ) {
        $this->contentMapper = $contentMapper;
        $this->contentLanguageHandler = $contentLanguageHandler;
        $this->fieldTypeRegistry = $fieldTypeRegistry;
    }

    public function supports(ValueObject $object): bool
    {
        return $object instanceof ContentUpdateStruct;
    }

    public function validate(
        ValueObject $object,
        array $context = [],
        ?array $fieldIdentifiers = null
    ): array {
        if (!$this->supports($object)) {
            throw new InvalidArgumentException('$object', 'Not supported');
        }

        if (empty($context['content']) || !$context['content'] instanceof Content) {
            throw new InvalidArgumentException('context[content]', 'Must be a ' . Content::class . ' type');
        }

        $content = $context['content'];

        /** @var ContentUpdateStruct $contentUpdateStruct */
        $contentUpdateStruct = $object;

        $contentType = $content->getContentType();

        $mainLanguageCode = $content->contentInfo->mainLanguageCode;
        if ($contentUpdateStruct->initialLanguageCode === null) {
            $contentUpdateStruct->initialLanguageCode = $mainLanguageCode;
        }

        $allLanguageCodes = $this->contentMapper->getLanguageCodesForUpdate($contentUpdateStruct, $content);
        foreach ($allLanguageCodes as $languageCode) {
            $this->contentLanguageHandler->loadByLanguageCode($languageCode);
        }

        $updatedLanguageCodes = $this->contentMapper->getUpdatedLanguageCodes($contentUpdateStruct);
        $fields = $this->contentMapper->mapFieldsForUpdate(
            $contentUpdateStruct,
            $contentType,
            $mainLanguageCode
        );

        $allFieldErrors = [];

        foreach ($contentType->getFieldDefinitions() as $fieldDefinition) {
            $fieldType = $this->fieldTypeRegistry->getFieldType(
                $fieldDefinition->fieldTypeIdentifier
            );

            foreach ($allLanguageCodes as $languageCode) {
                $isLanguageUpdated = in_array($languageCode, $updatedLanguageCodes);
                $valueLanguageCode = $fieldDefinition->isTranslatable ? $languageCode : $mainLanguageCode;
                $isFieldUpdated = isset($fields[$fieldDefinition->identifier][$valueLanguageCode]);

                $fieldValue = (!$isFieldUpdated || !$fieldDefinition->isTranslatable)
                    ? $content->getField($fieldDefinition->identifier, $valueLanguageCode)->value
                    : $fields[$fieldDefinition->identifier][$valueLanguageCode]->value;

                $fieldValue = $fieldType->acceptValue($fieldValue);

                if ($fieldType->isEmptyValue($fieldValue)) {
                    if ($isLanguageUpdated && $fieldDefinition->isRequired) {
                        $allFieldErrors[$fieldDefinition->id][$languageCode] = new ValidationError(
                            "Value for required field definition '%identifier%' with language '%languageCode%' is empty",
                            null,
                            ['%identifier%' => $fieldDefinition->identifier, '%languageCode%' => $languageCode],
                            'empty'
                        );
                    }
                } elseif ($isLanguageUpdated) {
                    $fieldErrors = $fieldType->validate(
                        $fieldDefinition,
                        $fieldValue
                    );
                    if (!empty($fieldErrors)) {
                        $allFieldErrors[$fieldDefinition->id][$languageCode] = $fieldErrors;
                    }
                }
            }
        }

        return $allFieldErrors;
    }
}
