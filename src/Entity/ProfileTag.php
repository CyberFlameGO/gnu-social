<?php

// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/soci
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as publ
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public Li
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.
// }}}

namespace App\Entity;

/**
 * Entity for Profile Tag
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@fc.up.pt>
 * @copyright 2020 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class ProfileTag
{
    // {{{ Autocode

    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name' => 'profile_tag',

            'fields' => [
                'tagger'   => ['type' => 'int', 'not null' => true, 'description' => 'user making the tag'],
                'tagged'   => ['type' => 'int', 'not null' => true, 'description' => 'profile tagged'],
                'tag'      => ['type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'hash tag associated with this notice'],
                'modified' => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date the tag was added'],
            ],
            'primary key'  => ['tagger', 'tagged', 'tag'],
            'foreign keys' => [
                'profile_tag_tagger_fkey' => ['profile', ['tagger' => 'id']],
                'profile_tag_tagged_fkey' => ['profile', ['tagged' => 'id']],
            ],
            'indexes' => [
                'profile_tag_modified_idx'   => ['modified'],
                'profile_tag_tagger_tag_idx' => ['tagger', 'tag'],
                'profile_tag_tagged_idx'     => ['tagged'],
            ],
        ];
    }
}