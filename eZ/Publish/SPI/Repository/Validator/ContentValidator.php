<?php

namespace eZ\Publish\SPI\Repository\Validator;

use eZ\Publish\API\Repository\Values\ValueObject;

interface ContentValidator
{
    public function supports(ValueObject $object): bool;

    /**
     * @param string[]|null $fieldIdentifiers
     */
    public function validate(
        ValueObject $object,
        array $context = [],
        ?array $fieldIdentifiers = null
    ): array;
}
