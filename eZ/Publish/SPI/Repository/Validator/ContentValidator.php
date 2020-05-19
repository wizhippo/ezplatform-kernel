<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
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
