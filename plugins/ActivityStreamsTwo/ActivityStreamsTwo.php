<?php

namespace Plugin\ActivityStreamsTwo;

use App\Core\Event;
use App\Core\Modules\Plugin;
use Exception;
use Plugin\ActivityStreamsTwo\Util\Response\ActorResponse;
use Plugin\ActivityStreamsTwo\Util\Response\NoteResponse;
use Plugin\ActivityStreamsTwo\Util\Response\TypeResponse;

class ActivityStreamsTwo extends Plugin
{
    public function version(): string
    {
        return '0.1.0';
    }

    public static array $accept_headers = [
        'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
        'application/activity+json',
        'application/json',
        'application/ld+json',
    ];

    /**
     * @param string            $route
     * @param array             $accept_header
     * @param array             $vars
     * @param null|TypeResponse $response
     *
     *@throws Exception
     *
     * @return bool
     *
     */
    public function onControllerResponseInFormat(string $route, array $accept_header, array $vars, ?TypeResponse &$response = null): bool
    {
        if (count(array_intersect(self::$accept_headers, $accept_header)) === 0) {
            return Event::next;
        }
        switch ($route) {
            case 'actor_view_id':
            case 'actor_view_nickname':
                $response = ActorResponse::handle($vars['actor']);
                return Event::stop;
            case 'note_view':
                $response = NoteResponse::handle($vars['note']);
                return Event::stop;
            case 'actor_favourites_id':
            case 'actor_favourites_nickname':
                $response = LikeResponse::handle($vars['actor']);
                return Event::stop;
            case 'actor_subscriptions_id':
            case 'actor_subscriptions_nickname':
                $response = FollowingResponse::handle($vars['actor']);
                return Event::stop;
            case 'actor_subscribers_id':
            case 'actor_subscribers_nickname':
                $response = FollowersResponse::handle($vars['actor']);
                return Event::stop;
            default:
                return Event::next;
        }
    }
}
