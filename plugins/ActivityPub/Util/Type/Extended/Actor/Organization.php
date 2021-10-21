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

namespace Plugin\ActivityPub\Util\Type\Extended\Actor;

use Plugin\ActivityPub\Util\Type\Extended\AbstractActor;

/**
 * \Plugin\ActivityPub\Util\Type\Extended\Actor\Organization is an implementation of
 * one of the Activity Streams Extended Types.
 *
 * Represents a formal or informal collective of Actors.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-organization
 */
class Organization extends AbstractActor
{
    protected string $type = 'Organization';
}