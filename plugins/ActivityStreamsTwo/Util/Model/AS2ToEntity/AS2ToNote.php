<?php

namespace Plugin\ActivityStreamsTwo\Util\Model\AS2ToEntity;

use App\Core\Event;
use App\Entity\Actor;
use App\Entity\Note;
use App\Util\Formatting;
use DateTime;

abstract class AS2ToNote
{
    /**
     * @param array $args
     *
     * @throws \Exception
     *
     * @return Note
     */
    public static function translate(array $args, ?string $source = null): Note
    {
        $map = [
            'isLocal'      => false,
            'created'      => new DateTime($args['published'] ?? 'now'),
            'content'      => $args['content'] ?? null,
            'content_type' => 'text/html',
            'rendered'     => null,
            'modified'     => new DateTime(),
            'source'       => $source,
        ];
        if ($map['content'] !== null) {
            Event::handle('RenderNoteContent', [
                $map['content'],
                $map['content_type'],
                &$map['rendered'],
                Actor::getFromId(1), // just for testing
                null, // reply to
            ]);
        }

        $obj = new Note();
        foreach ($map as $prop => $val) {
            $set = Formatting::snakeCaseToCamelCase("set_{$prop}");
            $obj->{$set}($val);
        }
        return $obj;
    }
}