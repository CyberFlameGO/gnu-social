<?php
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

/**
 * Database schema for MariaDB
 *
 * @category  Database
 * @package   GNUsocial
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

defined('GNUSOCIAL') || die();

/**
 * Class representing the database schema for MariaDB
 *
 * A class representing the database schema. Can be used to
 * manipulate the schema -- especially for plugins and upgrade
 * utilities.
 *
 * @copyright 2019 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class MysqlSchema extends Schema
{
    public static $_single = null;

    /**
     * Main public entry point. Use this to get
     * the singleton object.
     *
     * @param object|null $conn
     * @param string|null dummy param
     * @return Schema the (single) Schema object
     */
    public static function get($conn = null, $_ = 'mysql')
    {
        if (empty(self::$_single)) {
            self::$_single = new Schema($conn, 'mysql');
        }
        return self::$_single;
    }

    /**
     * Returns a TableDef object for the table
     * in the schema with the given name.
     *
     * Throws an exception if the table is not found.
     *
     * @param string $table Name of the table to get
     *
     * @return array of tabledef for that table.
     * @throws PEAR_Exception
     * @throws SchemaTableMissingException
     */
    public function getTableDef($table)
    {
        $def = [];
        $hasKeys = false;

        // Pull column data from INFORMATION_SCHEMA
        $columns = $this->fetchMetaInfo($table, 'COLUMNS', 'ORDINAL_POSITION');
        if (count($columns) == 0) {
            throw new SchemaTableMissingException("No such table: $table");
        }

        foreach ($columns as $row) {
            $name = $row['COLUMN_NAME'];
            $field = [];

            // warning -- 'unsigned' attr on numbers isn't given in DATA_TYPE and friends.
            // It is stuck in on COLUMN_TYPE though (eg 'bigint(20) unsigned')
            $field['type'] = $type = $row['DATA_TYPE'];

            if ($type == 'char' || $type == 'varchar') {
                if ($row['CHARACTER_MAXIMUM_LENGTH'] !== null) {
                    $field['length'] = intval($row['CHARACTER_MAXIMUM_LENGTH']);
                }
            }
            if ($type == 'decimal') {
                // Other int types may report these values, but they're irrelevant.
                // Just ignore them!
                if ($row['NUMERIC_PRECISION'] !== null) {
                    $field['precision'] = intval($row['NUMERIC_PRECISION']);
                }
                if ($row['NUMERIC_SCALE'] !== null) {
                    $field['scale'] = intval($row['NUMERIC_SCALE']);
                }
            }
            if ($row['IS_NULLABLE'] == 'NO') {
                $field['not null'] = true;
            }
            if ($row['COLUMN_DEFAULT'] !== null) {
                // Hack for timestamp columns
                if ($row['COLUMN_DEFAULT'] === 'current_timestamp()') {
                    // skip timestamp columns as they get a CURRENT_TIMESTAMP default implicitly
                    if ($type !== 'timestamp') {
                        $field['default'] = 'CURRENT_TIMESTAMP';
                    }
                } elseif ($this->isNumericType($type)) {
                    $field['default'] = intval($row['COLUMN_DEFAULT']);
                } else {
                    $field['default'] = $row['COLUMN_DEFAULT'];
                }
            }
            if ($row['COLUMN_KEY'] !== null) {
                // We'll need to look up key info...
                $hasKeys = true;
            }
            if ($row['COLUMN_COMMENT'] !== null && $row['COLUMN_COMMENT'] != '') {
                $field['description'] = $row['COLUMN_COMMENT'];
            }

            $extra = $row['EXTRA'];
            if ($extra) {
                if (preg_match('/(^|\s)auto_increment(\s|$)/i', $extra)) {
                    $field['auto_increment'] = true;
                }
                // $row['EXTRA'] may contain 'on update CURRENT_TIMESTAMP'
                // ^ ...... how to specify?
            }

            /* @fixme check against defaults?
            if ($row['CHARACTER_SET_NAME'] !== null) {
                $def['charset'] = $row['CHARACTER_SET_NAME'];
                $def['collate'] = $row['COLLATION_NAME'];
            }*/

            $def['fields'][$name] = $field;
        }

        if ($hasKeys) {
            // INFORMATION_SCHEMA's CONSTRAINTS and KEY_COLUMN_USAGE tables give
            // good info on primary and unique keys but don't list ANY info on
            // multi-value keys, which is lame-o. Sigh.
            $keyColumns = $this->fetchMetaInfo($table, 'KEY_COLUMN_USAGE', 'CONSTRAINT_NAME, ORDINAL_POSITION');
            $keys = [];
            $fkeys = [];

            foreach ($keyColumns as $row) {
                $keyName = $row['CONSTRAINT_NAME'];
                $keyCol = $row['COLUMN_NAME'];
                if (!isset($keys[$keyName])) {
                    $keys[$keyName] = [];
                }
                $keys[$keyName][] = $keyCol;
                if (!is_null($row['REFERENCED_TABLE_NAME'])) {
                    $fkeys[] = $keyName;
                }
            }

            foreach ($keys as $keyName => $cols) {
                if ($keyName === 'PRIMARY') {
                    $def['primary key'] = $cols;
                } elseif (in_array($keyName, $fkeys)) {
                    $fkey = $this->fetchForeignKeyInfo($table, $keyName);
                    $colMap = array_combine($cols, $fkey['cols']);
                    $def['foreign keys'][$keyName] = [$fkey['table_name'], $colMap];
                } else {
                    $def['unique keys'][$keyName] = $cols;
                }
            }

            $indexInfo = $this->fetchIndexInfo($table);

            foreach ($indexInfo as $row) {
                $keyName = $row['key_name'];
                $cols = $row['cols'];

                if ($row['key_type'] === 'FULLTEXT') {
                    $def['fulltext indexes'][$keyName] = $cols;
                } else {
                    $def['indexes'][$keyName] = $cols;
                }
            }
        }
        return $def;
    }

    /**
     * Pull the given table properties from INFORMATION_SCHEMA.
     * Most of the good stuff is MySQL extensions.
     *
     * @param $table
     * @param $props
     * @return array
     * @throws PEAR_Exception
     * @throws SchemaTableMissingException
     */
    public function getTableProperties($table, $props)
    {
        $data = $this->fetchMetaInfo($table, 'TABLES');
        if ($data) {
            return $data[0];
        } else {
            throw new SchemaTableMissingException("No such table: $table");
        }
    }

    /**
     * Pull some INFORMATION.SCHEMA data for the given table.
     *
     * @param string $table
     * @param $infoTable
     * @param null $orderBy
     * @return array of arrays
     * @throws PEAR_Exception
     */
    public function fetchMetaInfo($table, $infoTable, $orderBy = null)
    {
        $query = "SELECT * FROM INFORMATION_SCHEMA.%s " .
            "WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s'";
        $schema = $this->conn->dsn['database'];
        $sql = sprintf($query, $infoTable, $schema, $table);
        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        return $this->fetchQueryData($sql);
    }

    /**
     * Pull index and keys information for the given table.
     *
     * @param string $table
     * @return array of arrays
     * @throws PEAR_Exception
     */
    public function fetchIndexInfo(string $table): array
    {
        $query = 'SELECT INDEX_NAME AS `key_name`, INDEX_TYPE AS `key_type`, COLUMN_NAME AS `col` ' .
            'FROM INFORMATION_SCHEMA.STATISTICS ' .
            'WHERE TABLE_SCHEMA = \'%s\' AND TABLE_NAME = \'%s\' AND NON_UNIQUE = TRUE ' .
            'AND INDEX_NAME NOT IN (SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL) ' .
            'ORDER BY SEQ_IN_INDEX';
        $schema = $this->conn->dsn['database'];
        $sql = sprintf($query, $schema, $table);
        $data = $this->fetchQueryData($sql);

        $rows = [];
        foreach ($data as $row) {
            $name = $row['key_name'];
            if (isset($rows[$name])) {
                $rows[$name]['cols'][] = $row['col'];
            } else {
                $row['cols'] = [$row['col']];
                unset($row['col']);
                $rows[$name] = $row;
            }
        }
        return array_values($rows);
    }

    /**
     * @param string $table
     * @param string $constraint_name
     * @return array array of rows with keys: table_name, cols (array of strings)
     * @throws PEAR_Exception
     */
    public function fetchForeignKeyInfo(string $table, string $constraint_name): array
    {
        $query = 'SELECT REFERENCED_TABLE_NAME AS `table_name`, REFERENCED_COLUMN_NAME AS `col` ' .
            'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE ' .
            'WHERE TABLE_SCHEMA = \'%s\' AND TABLE_NAME = \'%s\' AND CONSTRAINT_NAME = \'%s\' ' .
            'AND REFERENCED_TABLE_SCHEMA IS NOT NULL ' .
            'ORDER BY POSITION_IN_UNIQUE_CONSTRAINT';
        $schema = $this->conn->dsn['database'];
        $sql = sprintf($query, $schema, $table, $constraint_name);
        $data = $this->fetchQueryData($sql);
        if (count($data) < 1) {
            throw new Exception('Could not find foreign key ' . $constraint_name . ' on table ' . $table);
        }

        $info = [
            'table_name' => $data[0]['table_name'],
            'cols'       => [],
        ];
        foreach ($data as $row) {
            $info['cols'][] = $row['col'];
        }
        return $info;
    }

    /**
     * Append an SQL statement with an index definition for a full-text search
     * index over one or more columns on a table.
     *
     * @param array $statements
     * @param string $table
     * @param string $name
     * @param array $def
     */
    public function appendCreateFulltextIndex(array &$statements, $table, $name, array $def)
    {
        $statements[] = "CREATE FULLTEXT INDEX $name ON $table " . $this->buildIndexList($def);
    }

    /**
     * Close out a 'create table' SQL statement.
     *
     * @param string $name
     * @param array $def
     * @return string;
     *
     */
    public function endCreateTable($name, array $def)
    {
        $engine = $this->preferredEngine($def);
        return ") ENGINE=$engine CHARACTER SET utf8mb4 COLLATE utf8mb4_bin";
    }

    public function preferredEngine($def)
    {
        /* MyISAM is no longer required for fulltext indexes, fortunately
        if (!empty($def['fulltext indexes'])) {
            return 'MyISAM';
        }
        */
        return 'InnoDB';
    }

    /**
     * Get the unique index key name for a given column on this table
     * @param $tableName
     * @param $columnName
     * @return string
     */
    public function _uniqueKey($tableName, $columnName)
    {
        return $this->_key($tableName, $columnName);
    }

    /**
     * Get the index key name for a given column on this table
     * @param $tableName
     * @param $columnName
     * @return string
     */
    public function _key($tableName, $columnName)
    {
        return "{$tableName}_{$columnName}_idx";
    }

    /**
     * MySQL doesn't take 'DROP CONSTRAINT', need to treat primary keys as
     * if they were indexes here, but can use 'PRIMARY KEY' special name.
     *
     * @param array $phrase
     */
    public function appendAlterDropPrimary(array &$phrase, string $tableName)
    {
        $phrase[] = 'DROP PRIMARY KEY';
    }

    /**
     * MySQL doesn't take 'DROP CONSTRAINT', need to treat unique keys as
     * if they were indexes here.
     *
     * @param array $phrase
     * @param string $keyName MySQL
     */
    public function appendAlterDropUnique(array &$phrase, $keyName)
    {
        $phrase[] = 'DROP INDEX ' . $keyName;
    }

    /**
     * Throw some table metadata onto the ALTER TABLE if we have a mismatch
     * in expected type, collation.
     * @param array $phrase
     * @param $tableName
     * @param array $def
     * @throws Exception
     */
    public function appendAlterExtras(array &$phrase, $tableName, array $def)
    {
        // Check for table properties: make sure we're using a sane
        // engine type and charset/collation.
        // @fixme make the default engine configurable?
        $oldProps = $this->getTableProperties($tableName, ['ENGINE', 'TABLE_COLLATION']);
        $engine = $this->preferredEngine($def);
        if (strtolower($oldProps['ENGINE']) != strtolower($engine)) {
            $phrase[] = "ENGINE=$engine";
        }
        if (strtolower($oldProps['TABLE_COLLATION']) != 'utf8mb4_bin') {
            $phrase[] = 'CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_bin';
            $phrase[] = 'DEFAULT CHARACTER SET = utf8mb4';
            $phrase[] = 'DEFAULT COLLATE = utf8mb4_bin';
        }
    }

    /**
     * Is this column a string type?
     * @param array $cd
     * @return bool
     */
    private function _isString(array $cd)
    {
        $strings = ['char', 'varchar', 'text'];
        return in_array(strtolower($cd['type']), $strings);
    }

    /**
     * Return the proper SQL for creating or
     * altering a column.
     *
     * Appropriate for use in CREATE TABLE or
     * ALTER TABLE statements.
     *
     * @param string $name column name to create
     * @param array $cd column to create
     *
     * @return string correct SQL for that column
     */
    public function columnSql(string $name, array $cd)
    {
        $line = [];
        $line[] = parent::columnSql($name, $cd);

        // This'll have been added from our transform of 'serial' type
        if (!empty($cd['auto_increment'])) {
            $line[] = 'auto_increment';
        }

        if (!empty($cd['description'])) {
            $line[] = 'comment';
            $line[] = $this->quoteValue($cd['description']);
        }

        return implode(' ', $line);
    }

    public function mapType($column)
    {
        $map = [
            'integer' => 'int',
            'bool'    => 'tinyint',
            'numeric' => 'decimal',
        ];

        $type = $column['type'];
        if (isset($map[$type])) {
            $type = $map[$type];
        }

        if (!empty($column['size'])) {
            $size = $column['size'];
            if ($type == 'int' &&
                in_array($size, ['tiny', 'small', 'medium', 'big'])) {
                $type = $size . $type;
            } elseif ($type == 'float' && $size == 'big') {
                $type = 'double';
            } elseif (in_array($type, ['blob', 'text']) &&
                in_array($size, ['tiny', 'medium', 'long'])) {
                $type = $size . $type;
            }
        }

        return $type;
    }

    public function typeAndSize(string $name, array $column)
    {
        if ($column['type'] === 'enum') {
            foreach ($column['enum'] as &$val) {
                $vals[] = "'" . $val . "'";
            }
            return 'enum(' . implode(',', $vals) . ')';
        } elseif ($this->_isString($column)) {
            $col = parent::typeAndSize($name, $column);
            if (!empty($column['charset'])) {
                $col .= ' CHARSET ' . $column['charset'];
            }
            if (!empty($column['collate'])) {
                $col .= ' COLLATE ' . $column['collate'];
            }
            return $col;
        } else {
            return parent::typeAndSize($name, $column);
        }
    }

    /**
     * Filter the given table definition array to match features available
     * in this database.
     *
     * This lets us strip out unsupported things like comments, foreign keys,
     * or type variants that we wouldn't get back from getTableDef().
     *
     * @param array $tableDef
     * @return array
     */
    public function filterDef(array $tableDef)
    {
        $version = $this->conn->getVersion();
        foreach ($tableDef['fields'] as $name => &$col) {
            if ($col['type'] == 'serial') {
                $col['type'] = 'int';
                $col['auto_increment'] = true;
            }

            $col['type'] = $this->mapType($col);
            unset($col['size']);
        }
        if (!common_config('db', 'mysql_foreign_keys')) {
            unset($tableDef['foreign keys']);
        }
        return $tableDef;
    }
}
