<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Repository\Strategy\ContentValidator;

use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\SPI\Repository\Validator\ContentValidator;

class ContentValidatorStrategy implements ContentValidator
{
    /** @var \eZ\Publish\SPI\Repository\Validator\ContentValidator[] */
    private $contentValidators;

    public function __construct(iterable $contentValidators)
    {
        $this->contentValidators = $contentValidators;
    }

    public function supports(ValueObject $object): bool
    {
        foreach ($this->contentValidators as $contentValidator) {
            if ($contentValidator->supports($object)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentException
     */
    public function validate(
        ValueObject $object,
        array $context = [],
        ?array $fieldIdentifiers = null
    ): array {
        foreach ($this->contentValidators as $contentValidator) {
            if ($contentValidator->supports($object)) {
                return $contentValidator->validate($object, $context, $fieldIdentifiers);
            }
        }

        throw new InvalidArgumentException('$object', sprintf(
            'Validator for %s type not found.', gettype($object)
        ));
    }
}
