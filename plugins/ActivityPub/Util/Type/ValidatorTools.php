<?php

declare(strict_types = 1);

/*
 * This file is part of the ActivityPhp package.
 *
 * Copyright (c) landrok at github.com/landrok
 *
 * For the full copyright and license information, please see
 * <https://github.com/landrok/activitypub/blob/master/LICENSE>.
 */

namespace Plugin\ActivityPub\Util\Type;

use Exception;
use Plugin\ActivityPub\Util\Type\Core\ObjectType;
use Plugin\ActivityPub\Util\Type\Extended\AbstractActor;

/**
 * \Plugin\ActivityPub\Util\Type\ValidatorTools is an abstract class for
 * attribute validation.
 * Its purpose is to be extended by Plugin\ActivityPub\Util\Type\Validator\*
 * classes.
 * It provides some methods to make some regular validations.
 * It implements \Plugin\ActivityPub\Util\Type\ValidatorInterface.
 */
abstract class ValidatorTools implements ValidatorInterface
{
    /**
     * Validate a map attribute value.
     *
     * @param string $type      An attribute name
     * @param object $container A valid container
     *
     * @throws Exception
     */
    protected function validateMap(string $type, mixed $map, object $container): bool
    {
        // A map
        if (!\is_array($map)) {
            return false;
        }

        foreach ($map as $key => $value) {
            if (!Util::validateBcp47($key)
                || !Validator::validate($type, $value, $container)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate an attribute value
     *
     * @param mixed    $container An object
     * @param callable $callback  A dedicated validator
     *
     * @throws Exception
     */
    public function validateListOrObject(mixed $value, mixed $container, callable $callback): bool
    {
        Util::subclassOf($container, ObjectType::class, true);

        // Not supported: Can be a JSON string
        // Must be a value like a URL, a text
        if (\is_string($value)) {
            return $callback($value);
        }

        if (\is_array($value)) {
            // Can be empty
            if (!\count($value)) {
                return true;
            }

            // A collection
            if (\is_int(key($value))) {
                return $this->validateObjectCollection($value, $callback);
            }

            $value = Util::arrayToType($value);
        }

        if (!\is_object($value)) {
            return false;
        }

        return $callback($value);
    }

    /**
     * Validate a list of Collection
     *
     * @param callable $callback A dedicated validator
     */
    protected function validateObjectCollection(array $collection, callable $callback): bool
    {
        foreach ($collection as $item) {
            if ($callback($item)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Validate that a value is a string
     *
     * @throws Exception
     */
    protected function validateString(string $value): bool
    {
        if (!\is_string($value) || $value === '') {
            throw new Exception(
                sprintf(
                    'Value must be a non-empty string. Given: "%s"',
                    print_r($value, true),
                ),
            );
        }

        return true;
    }

    /**
     * A callback function for validateListOrObject method
     *
     * It validates a Link or a named object
     */
    protected function getLinkOrNamedObjectValidator(): callable
    {
        return
            /** The implementation lambda */
            static function ($item): bool {
                if (\is_string($item)) {
                    return Util::validateUrl($item);
                }

                if (\is_array($item)) {
                    $item = Util::arrayToType($item);
                }

                if (\is_object($item)) {
                    Util::hasProperties($item, ['type'], true);

                    // Validate Link type
                    if ($item->type === 'Link') {
                        return Util::validateLink($item);
                    }

                    // Validate Object type
                    Util::hasProperties($item, ['name'], true);

                    return \is_string($item->name);
                }

                return false;
            };
    }

    /**
     * A callback function for validateListOrObject method
     *
     * Validate a reference with a Link or an Object with a URL
     */
    protected function getLinkOrUrlObjectValidator(): callable
    {
        return
            /** The implementation lambda */
            static function ($item): bool {
                if (\is_array($item)) {
                    $item = Util::arrayToType($item);
                }

                if (\is_object($item)
                && Util::isLinkOrUrlObject($item)) {
                    return true;
                }

                return (bool) (Util::validateUrl($item));
            };
    }

    /**
     * A callback function for attachment validation
     *
     * Validate a reference with a Link, an Object with a URL
     * or an ObjectType
     */
    protected function getAttachmentValidator(): callable
    {
        return
            /** The implementation lambda */
            static function ($item): bool {
                if (\is_array($item)) {
                    $item = Util::arrayToType($item);
                }

                if (\is_object($item)) {
                    if (Util::isLinkOrUrlObject($item)) {
                        return true;
                    }

                    return $item instanceof ObjectType;
                }

                return (bool) (Util::validateUrl($item));
            };
    }

    /**
     * Validate that a Question answer is a Note
     */
    protected function getQuestionAnswerValidator(): callable
    {
        return
            /** The implementation lambda */
            static function ($item): bool {
                if (\is_array($item)) {
                    $item = Util::arrayToType($item);
                }

                if (!\is_object($item)) {
                    return false;
                }

                Util::hasProperties($item, ['type', 'name'], true);

                return $item->type === 'Note'
                && is_scalar($item->name);
            };
    }

    /**
     * Validate that a list of items is valid
     */
    protected function getCollectionItemsValidator(): callable
    {
        return
            /** The implementation lambda */
            static function ($item): bool {
                if (\is_string($item)) {
                    return Util::validateUrl($item);
                }

                if (\is_array($item)) {
                    $item = Util::arrayToType($item);
                }

                if (!\is_object($item)) {
                    return false;
                }

                return Util::hasProperties($item, ['type'], true);
            };
    }

    /**
     * Validate that a list of items are actors
     */
    protected function getCollectionActorsValidator(): callable
    {
        return
            /** The implementation lambda */
            static function ($item): bool {
                if (\is_string($item)) {
                    return Util::validateUrl($item);
                }

                if (\is_array($item)) {
                    $item = Util::arrayToType($item);
                }

                if (!\is_object($item)) {
                    return false;
                }
                // id must be filled
                if ($item instanceof AbstractActor) {
                    return !\is_null($item->id);
                }

                return Util::validateLink($item);
            };
    }
}