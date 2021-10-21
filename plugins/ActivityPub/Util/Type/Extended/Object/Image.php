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

namespace Plugin\ActivityPub\Util\Type\Extended\Object;

/**
 * \Plugin\ActivityPub\Util\Type\Extended\Object\Image is an implementation of
 * one of the Activity Streams Extended Types.
 *
 * An image document of any kind.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-image
 */
class Image extends Document
{
    protected string $type = 'Image';
}