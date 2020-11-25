<?php

// {{{ License

// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

// }}}

namespace App\Entity;

use App\Core\Entity;
use DateTimeInterface;

class Cover extends Entity
{
    // {{{ Autocode

    private int $gsactor_id;
    private int $file_id;
    private DateTimeInterface $created;
    private DateTimeInterface $modified;

    public function setGsactorId(int $gsactor_id): self
    {
        $this->gsactor_id = $gsactor_id;
        return $this;
    }

    public function getGsactorId(): int
    {
        return $this->gsactor_id;
    }

    public function setFileId(int $file_id): self
    {
        $this->file_id = $file_id;
        return $this;
    }

    public function getFileId(): int
    {
        return $this->file_id;
    }

    public function setCreated(DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function setModified(DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): DateTimeInterface
    {
        return $this->modified;
    }

    // }}} Autocode

    public static function schemaDef(): array
    {
        return [
            'name'   => 'cover',
            'fields' => [
                'gsactor_id' => ['type' => 'int',       'not null' => true, 'description' => 'foreign key to gsactor table'],
                'file_id'    => ['type' => 'int',       'not null' => true, 'description' => 'foreign key to file table'],
                'created'    => ['type' => 'datetime',  'not null' => true, 'description' => 'date this record was created',  'default' => 'CURRENT_TIMESTAMP'],
                'modified'   => ['type' => 'timestamp', 'not null' => true, 'description' => 'date this record was modified', 'default' => 'CURRENT_TIMESTAMP'],
            ],
            'primary key'  => ['gsactor_id'],
            'foreign keys' => [
                'cover_gsactor_id_fkey' => ['gsactor', ['gsactor_id' => 'id']],
                'cover_file_id_fkey'    => ['file', ['file_id' => 'id']],
            ],
            'indexes' => [
                'cover_file_id_idx' => ['file_id'],
            ],
        ];
    }
}