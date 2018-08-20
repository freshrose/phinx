<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Db\Adapter
 */
namespace Phinx\Db\Adapter;

use PDOOCI\PDO as OracleDriver;
use Phinx\Db\Table\Column;
use Phinx\Db\Table\ForeignKey;
use Phinx\Db\Table\Index;
use Phinx\Db\Table\Table;
use Phinx\Db\Util\AlterInstructions;
use Phinx\Util\Literal;
/**
 * Phinx Oracle Adapter.
 *
 * @author FreshFlow Systems s.r.o
 */
class OracleAdapter extends PdoAdapter implements AdapterInterface
{
    /**
     * Columns with comments
     *
     * @var array
     */
    protected $columnsWithComments = [];

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if ($this->connection === null) {
            if (!extension_loaded('oci8')) {
                // @codeCoverageIgnoreStart
                throw new \RuntimeException('You need to enable the OCI8 extension for Phinx to run properly.');
                // @codeCoverageIgnoreEnd
            }

            $db = null;
            $options = $this->getOptions();

            // if port is specified use it, otherwise use the Oracle default
            if (empty($options['port'])) {
                $dsn = $options['host'] . "/" . $options['sid'] . "";
            } else {
                $dsn = $options['host'] . ":" . $options['port'] . "/" . $options['sid'] . "";
            }

            try {
                $db = new OracleDriver($dsn, $options['user'], $options['pass']);
            } catch (\PDOException $exception) {
                throw new \InvalidArgumentException(sprintf(
                    'There was a problem connecting to the database: %s',
                    $exception->getMessage()
                ), $exception->getCode(), $exception);
            }
            $this->setConnection($db);
        }
    }

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTransactions()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction()
    {
        // Oracle transactions starts automatically while SQL query executes
        //$this->execute('BEGIN');
    }

    /**
     * {@inheritdoc}
     */
    public function commitTransaction()
    {
        // make transactions permanent
        $this->execute('COMMIT');
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction()
    {
        // undo whole transactions
        $this->execute('ROLLBACK');
    }

    // OK - PASSED
    /**
     * Quotes a schema name for use in a query.
     *
     * @param string $schemaName Schema Name
     * @return string
     */
    public function quoteSchemaName($schemaName)
    {
        return $this->quoteColumnName($schemaName);
    }

    // OK - PASSED
    /**
     * Quotes a schema table name for use in a query.
     *
     * @param string $schemaTableName Schema Name
     * @return string
     */
    public function quoteSchemaTableName($schemaTableName)
    {
        $parts = $this->getSchemaName($schemaTableName);
        if (is_null($parts['schema'])) {
            $result = $this->quoteTableName($parts['table']);
        } else {
            $result = $this->quoteSchemaName($parts['schema']) . '.' . $this->quoteTableName($parts['table']);

        }
        return $result;
    }

    // OK - PASSED
    /**
     * Quotes a table name for use in a query.
     *
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return $this->quoteColumnName($tableName);
    }

    // OK - PASSED
    /**
     * Quotes a column name for use in a query.
     *
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return '"' .  $columnName . '"';
    }

    // OK - PASSED
    /**
     * Check if logged user has access to selected table.
     *
     * {@inheritdoc}
     */
    public function hasTable($tableName)
    {
        $tableFullName = $this->getSchemaName($tableName);
        $tableSearchName = $tableFullName['schema'] . $tableFullName['table'];

        //var_dump($tableSearchName);die;

        $result = $this->fetchRow(
            sprintf(
                'SELECT count(*) as count FROM ALL_TABLES WHERE owner || table_name = \'%s\'',
                $tableSearchName
            )
        );
        //var_dump($result['COUNT'] > 0);die;
        return $result['COUNT'] > 0;
    }

    // TODO !!!!!!
    /**
     * Creates new table.
     *
     * {@inheritdoc}
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        $options = $table->getOptions();
        //$parts = $this->getSchemaName($table->getName());

        // Add the default primary key
        if (!isset($options['id']) || (isset($options['id']) && $options['id'] === true)) {
            $column = new Column();
            $column->setName('UID')
                ->setType('integer')
                ->setIdentity(true);

            array_unshift($columns, $column);
            $options['primary_key'] = 'UID';
        } elseif (isset($options['id']) && is_string($options['id'])) {
            // Handle id => "field_name" to support AUTO_INCREMENT
            $column = new Column();
            $column->setName($options['id'])
                ->setType('integer')
                ->setIdentity(true);

            array_unshift($columns, $column);
            $options['primary_key'] = $options['id'];
        }

        // TODO - process table options like collation etc
        $sql = 'CREATE TABLE ';
        $sql .= $this->quoteSchemaTableName($table->getName()) . ' (';

        $this->columnsWithComments = [];
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($column->getName()) . ' ' . $this->getColumnSqlDefinition($column) . ', ';

            // set column comments, if needed
            if ($column->getComment()) {
                $this->columnsWithComments[] = $column;
            }
        }

        // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            // set the NAME of pkey to internal code -> 12.1 limit to 30char , 12.2 limit to 60char
            $sql .= sprintf(' CONSTRAINT %s PRIMARY KEY (', $this->quoteColumnName('P' . mt_rand(10000,99999) . time()));
            if (is_string($options['primary_key'])) { // handle primary_key => 'id'
                $sql .= $this->quoteColumnName($options['primary_key']);
            } elseif (is_array($options['primary_key'])) { // handle primary_key => array('tag_id', 'resource_id')
                $sql .= implode(',', array_map([$this, 'quoteColumnName'], $options['primary_key']));
            }
            $sql .= ')';
        } else {
            $sql = rtrim($sql, ', '); // no primary keys
        }

        $sql .= ')';

        // process column comments
        if (!empty($this->columnsWithComments)) {
            foreach ($this->columnsWithComments as $column) {
                $sql .= $this->getColumnCommentSqlDefinition($column, $table->getName());
            }
        }

        // TODO - indexes!!!
        // set the indexes
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql .= $this->getIndexSqlDefinition($index, $table->getName());
            }
        }

        // execute the sql
        //var_dump($sql);//die;
        $this->execute($sql);

        // TODO - table comments
        // process table comments
        if (isset($options['comment'])) {
            $sql = sprintf(
                'COMMENT ON TABLE %s IS %s',
                $this->quoteTableName($table->getName()),
                $this->getConnection()->quote($options['comment'])
            );
            $this->execute($sql);
        }
    }

    // TODO - build test
    /**
     * {@inheritdoc}
     */
    protected function getRenameTableInstructions($tableName, $newTableName)
    {
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($newTableName)
        );

        return new AlterInstructions([], [$sql]);
    }

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $this->execute(sprintf('DROP TABLE %s', $this->quoteSchemaTableName($tableName)));
    }

    // TODO - check
    /**
     * {@inheritdoc}
     */
    protected function getDropTableInstructions($tableName)
    {
        $sql = sprintf('DROP TABLE %s',
            $this->quoteSchemaTableName($tableName));

        return new AlterInstructions([], [$sql]);
    }

    // TODO - build test
    // TODO - check
    /**
     * {@inheritdoc}
     */
    public function truncateTable($tableName)
    {
        $sql = sprintf(
            'TRUNCATE TABLE %s',
            $this->quoteSchemaTableName($tableName)
        );

        $this->execute($sql);
    }

    // TODO ... 1st step done ... do test
    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $schemaTableName = $parts['schema'] . $parts['table'];
        $columns = [];
        $sql = sprintf(
            "SELECT column_name FROM ALL_TAB_COLUMNS WHERE owner || table_name = '%s'",
            $schemaTableName
        );
        $columnsInfo = $this->fetchAll($sql);

        foreach ($columnsInfo as $columnInfo) {
            $isUserDefined = strtoupper(trim($columnInfo['data_type'])) === 'USER-DEFINED';

            if ($isUserDefined) {
                $columnType = Literal::from($columnInfo['udt_name']);
            } else {
                $columnType = $this->getPhinxType($columnInfo['data_type']);
            }

            // If the default value begins with a ' or looks like a function mark it as literal
            if (isset($columnInfo['column_default'][0]) && $columnInfo['column_default'][0] === "'") {
                if (preg_match('/^\'(.*)\'::[^:]+$/', $columnInfo['column_default'], $match)) {
                    // '' and \' are replaced with a single '
                    $columnDefault = preg_replace('/[\'\\\\]\'/', "'", $match[1]);
                } else {
                    $columnDefault = Literal::from($columnInfo['column_default']);
                }
            } elseif (preg_match('/^\D[a-z_\d]*\(.*\)$/', $columnInfo['column_default'])) {
                $columnDefault = Literal::from($columnInfo['column_default']);
            } else {
                $columnDefault = $columnInfo['column_default'];
            }

            $column = new Column();
            $column->setName($columnInfo['column_name'])
                ->setType($columnType)
                ->setNull($columnInfo['is_nullable'] === 'YES')
                ->setDefault($columnDefault)
                ->setIdentity($columnInfo['is_identity'] === 'YES')
                ->setScale($columnInfo['numeric_scale']);

            if (preg_match('/\bwith time zone$/', $columnInfo['data_type'])) {
                $column->setTimezone(true);
            }

            if (isset($columnInfo['character_maximum_length'])) {
                $column->setLimit($columnInfo['character_maximum_length']);
            }

            if (in_array($columnType, [static::PHINX_TYPE_TIME, static::PHINX_TYPE_DATETIME])) {
                $column->setPrecision($columnInfo['datetime_precision']);
            } else {
                $column->setPrecision($columnInfo['numeric_precision']);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    public function hasColumn($tableName, $columnName)
    {
        $parts = $this->getSchemaName($tableName);
        $schemaTableName = $parts['schema'] . $parts['table'];
        $result = $this->fetchRow(sprintf(
            "SELECT count(*) as count FROM ALL_TAB_COLUMNS WHERE owner || table_name = '%s' and column_name = '%s'",
            $schemaTableName,
            $columnName
        ));

        return $result['COUNT'] > 0;
    }

    // TODO - CHECK hardly
    /**
     * {@inheritdoc}
     */
    protected function getAddColumnInstructions(Table $table, Column $column)
    {
        $instructions = new AlterInstructions();
        $instructions->addAlter(sprintf(
            'ADD %s %s',
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        ));

        if ($column->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($column, $table->getName()));
        }

        return $instructions;
    }

    // TODO - CHECK superhardly
    /**
     * {@inheritdoc}
     */
    protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName)
    {
        $parts = $this->getSchemaName($tableName);
        $sql = sprintf(
            "SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END AS column_exists
             FROM information_schema.columns
             WHERE table_schema = %s AND table_name = %s AND column_name = %s",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table']),
            $this->getConnection()->quote($columnName)
        );

        $result = $this->fetchRow($sql);
        if (!(bool)$result['column_exists']) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }

        $instructions = new AlterInstructions();
        $instructions->addPostStep(
            sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $tableName,
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumnName)
            )
        );

        return $instructions;
    }

    // TODO - CHECK wtf?
    /**
     * {@inheritdoc}
     */
    protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn)
    {
        $instructions = new AlterInstructions();

        $sql = sprintf(
            'ALTER COLUMN %s TYPE %s',
            $this->quoteColumnName($columnName),
            $this->getColumnSqlDefinition($newColumn)
        );
        //
        //NULL and DEFAULT cannot be set while changing column type
        $sql = preg_replace('/ NOT NULL/', '', $sql);
        $sql = preg_replace('/ NULL/', '', $sql);
        //If it is set, DEFAULT is the last definition
        $sql = preg_replace('/DEFAULT .*/', '', $sql);

        $instructions->addAlter($sql);

        // process null
        $sql = sprintf(
            'ALTER COLUMN %s',
            $this->quoteColumnName($columnName)
        );

        if ($newColumn->isNull()) {
            $sql .= ' DROP NOT NULL';
        } else {
            $sql .= ' SET NOT NULL';
        }

        $instructions->addAlter($sql);

        if (!is_null($newColumn->getDefault())) {
            $instructions->addAlter(sprintf(
                'ALTER COLUMN %s SET %s',
                $this->quoteColumnName($columnName),
                $this->getDefaultValueDefinition($newColumn->getDefault(), $newColumn->getType())
            ));
        } else {
            //drop default
            $instructions->addAlter(sprintf(
                'ALTER COLUMN %s DROP DEFAULT',
                $this->quoteColumnName($columnName)
            ));
        }

        // rename column
        if ($columnName !== $newColumn->getName()) {
            $instructions->addPostStep(sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->quoteSchemaTableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumn->getName())
            ));
        }

        // change column comment if needed
        if ($newColumn->getComment()) {
            $instructions->addPostStep($this->getColumnCommentSqlDefinition($newColumn, $tableName));
        }

        return $instructions;
    }

    // TODO - CHECK
    /**
     * {@inheritdoc}
     */
    protected function getDropColumnInstructions($tableName, $columnName)
    {
        $alter = sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($columnName)
        );

        return new AlterInstructions([$alter]);
    }

    // TODO - CHECK !!!!!!!!!!!!!
    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $parts = $this->getSchemaName($tableName);

        $indexes = [];
        $sql = sprintf(
            "SELECT
                i.relname AS index_name,
                a.attname AS column_name
            FROM
                pg_class t,
                pg_class i,
                pg_index ix,
                pg_attribute a,
                pg_namespace nsp
            WHERE
                t.oid = ix.indrelid
                AND i.oid = ix.indexrelid
                AND a.attrelid = t.oid
                AND a.attnum = ANY(ix.indkey)
                AND t.relnamespace = nsp.oid
                AND nsp.nspname = %s
                AND t.relkind = 'r'
                AND t.relname = %s
            ORDER BY
                t.relname,
                i.relname",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table'])
        );
        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            if (!isset($indexes[$row['index_name']])) {
                $indexes[$row['index_name']] = ['columns' => []];
            }
            $indexes[$row['index_name']]['columns'][] = $row['column_name'];
        }

        return $indexes;
    }

    // TODO - CHECK !!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $index) {
            if (array_diff($index['columns'], $columns) === array_diff($columns, $index['columns'])) {
                return true;
            }
        }

        return false;
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    public function hasIndexByName($tableName, $indexName)
    {
        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
                return true;
            }
        }

        return false;
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    protected function getAddIndexInstructions(Table $table, Index $index)
    {
        $instructions = new AlterInstructions();
        $instructions->addPostStep($this->getIndexSqlDefinition($index, $table->getName()));

        return $instructions;
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    protected function getDropIndexByColumnsInstructions($tableName, $columns)
    {
        $parts = $this->getSchemaName($tableName);

        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }

        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $indexName => $index) {
            $a = array_diff($columns, $index['columns']);
            if (empty($a)) {
                return new AlterInstructions([], [sprintf(
                    'DROP INDEX IF EXISTS %s',
                    '"' . ($parts['schema'] . '".' . $this->quoteColumnName($indexName))
                )]);
            }
        }

        throw new \InvalidArgumentException(sprintf(
            "The specified index on columns '%s' does not exist",
            implode(',', $columns)
        ));
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    protected function getDropIndexByNameInstructions($tableName, $indexName)
    {
        $parts = $this->getSchemaName($tableName);

        $sql = sprintf(
            'DROP INDEX IF EXISTS %s',
            '"' . ($parts['schema'] . '".' . $this->quoteColumnName($indexName))
        );

        return new AlterInstructions([], [$sql]);
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }
        $foreignKeys = $this->getForeignKeys($tableName);
        if ($constraint) {
            if (isset($foreignKeys[$constraint])) {
                return !empty($foreignKeys[$constraint]);
            }

            return false;
        } else {
            foreach ($foreignKeys as $key) {
                $a = array_diff($columns, $key['columns']);
                if (empty($a)) {
                    return true;
                }
            }

            return false;
        }
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!
    /**
     * Get an array of foreign keys from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getForeignKeys($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $foreignKeys = [];
        $rows = $this->fetchAll(sprintf(
            "SELECT
                    tc.constraint_name,
                    tc.table_name, kcu.column_name,
                    ccu.table_name AS referenced_table_name,
                    ccu.column_name AS referenced_column_name
                FROM
                    information_schema.table_constraints AS tc
                    JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
                    JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
                WHERE constraint_type = 'FOREIGN KEY' AND tc.table_schema = %s AND tc.table_name = %s
                ORDER BY kcu.position_in_unique_constraint",
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table'])
        ));
        foreach ($rows as $row) {
            $foreignKeys[$row['constraint_name']]['table'] = $row['table_name'];
            $foreignKeys[$row['constraint_name']]['columns'][] = $row['column_name'];
            $foreignKeys[$row['constraint_name']]['referenced_table'] = $row['referenced_table_name'];
            $foreignKeys[$row['constraint_name']]['referenced_columns'][] = $row['referenced_column_name'];
        }

        return $foreignKeys;
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey)
    {
        $alter = sprintf(
            'ADD %s',
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
        );

        return new AlterInstructions([$alter]);
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyInstructions($tableName, $constraint)
    {
        $alter = sprintf(
            'DROP CONSTRAINT %s',
            $this->quoteColumnName($constraint)
        );

        return new AlterInstructions([$alter]);
    }

    // TODO - CHECK !!!!!!!!!!!!!!!!!!!!
    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyByColumnsInstructions($tableName, $columns)
    {
        $instructions = new AlterInstructions();

        $parts = $this->getSchemaName($tableName);
        $sql = "SELECT c.CONSTRAINT_NAME
                FROM (
                    SELECT CONSTRAINT_NAME, array_agg(COLUMN_NAME::varchar) as columns
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = %s
                    AND TABLE_NAME IS NOT NULL
                    AND TABLE_NAME = %s
                    AND POSITION_IN_UNIQUE_CONSTRAINT IS NOT NULL
                    GROUP BY CONSTRAINT_NAME
                ) c
                WHERE
                    ARRAY[%s]::varchar[] <@ c.columns AND
                    ARRAY[%s]::varchar[] @> c.columns";

        $array = [];
        foreach ($columns as $col) {
            $array[] = "'$col'";
        }

        $rows = $this->fetchAll(sprintf(
            $sql,
            $this->getConnection()->quote($parts['schema']),
            $this->getConnection()->quote($parts['table']),
            implode(',', $array),
            implode(',', $array)
        ));

        foreach ($rows as $row) {
            $newInstr = $this->getDropForeignKeyInstructions($tableName, $row['constraint_name']);
            $instructions->merge($newInstr);
        }

        return $instructions;
    }

    // TODO - build test
    /**
     * {@inheritdoc}
     */
    public function getSqlType($type, $limit = null)
    {
        // https://docs.oracle.com/database/121/SQLRF/sql_elements001.htm#SQLRF00213
        switch ($type) {
            // datetime datatypes
            case static::PHINX_TYPE_TIME:
            case static::PHINX_TYPE_TIMESTAMP:
                return ['name' => 'TIMESTAMP'];
            case static::PHINX_TYPE_DATE:
            case static::PHINX_TYPE_DATETIME:
                return ['name' => 'DATE'];
            case static::PHINX_TYPE_INTERVAL:
                return ['name' => 'INTERVAL'];

            // numeric datatypes
            case static::PHINX_TYPE_BOOLEAN:
                return ['name' => 'NUMBER', 'precision' => 1, 'scale' => 0];
            case static::PHINX_TYPE_INTEGER:
            return ['name' => 'NUMBER', 'precision' => 11, 'scale' => 0];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'NUMBER', 'precision' => 20, 'scale' => 0];
            case static::PHINX_TYPE_DECIMAL:
                return ['name' => 'NUMBER', 'precision' => 18, 'scale' => 0];
            case static::PHINX_TYPE_FLOAT:
                return ['name' => 'NUMBER'];

            // character datatypes
            case static::PHINX_TYPE_TEXT:
                return ['name' => 'LONG'];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'VARCHAR2', 'limit' => 2000];
            case static::PHINX_TYPE_UUID:
                return ['name' => 'RAW'];
            case static::PHINX_TYPE_CHAR:
                return ['name' => 'CHAR', 'limit' => 255];

            // large object/binaries datatypes
            case static::PHINX_TYPE_BLOB:
            case static::PHINX_TYPE_BINARY:
            case static::PHINX_TYPE_VARBINARY:
                return ['name' => 'BLOB'];

            // other datatypes
            /* @TODO : json/filestream
            case static::PHINX_TYPE_JSON:
            case static::PHINX_TYPE_FILESTREAM:
            case static::PHINX_TYPE_JSONB:
            case static::PHINX_TYPE_BIT:

            // Oracle SDO_GEOMETRY datatypes
             @TODO : geometry
            case static::PHINX_TYPE_GEOMETRY:
                return ['name' => 'geography', 'type' => 'geometry', 'srid' => 4326];
            case static::PHINX_TYPE_POINT:
                return ['name' => 'SDO_POINT_TYPE'];
            case static::PHINX_TYPE_LINESTRING:
                return ['name' => 'geography', 'type' => 'linestring', 'srid' => 4326];
            case static::PHINX_TYPE_POLYGON:
                return ['name' => 'geography', 'type' => 'polygon', 'srid' => 4326];
                  */
            default:
                if ($this->isArrayType($type)) {
                    return ['name' => $type];
                }
                // Return array type
                throw new \RuntimeException('The type: "' . $type . '" is not supported');
        }
    }

    // TODO - build test
    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlType SQL Type definition
     * @param int $precision Precision of NUMBER type to define Phinx Type.
     * @throws \RuntimeException
     * @param string $sqlType SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlType, $precision = null)
    {
        if ($sqlType === 'VARCHAR2') {
            return static::PHINX_TYPE_STRING;
        } elseif ($sqlType === 'CHAR') {
            return static::PHINX_TYPE_CHAR;
        } elseif ($sqlType == 'LONG') {
            return static::PHINX_TYPE_TEXT;
        } elseif ($sqlType === 'NUMBER' && $precision === 11) {
            return static::PHINX_TYPE_INTEGER;
        } elseif ($sqlType === 'NUMBER' && $precision === 20) {
            return static::PHINX_TYPE_BIG_INTEGER;
        } elseif ($sqlType === 'NUMBER') {
            return static::PHINX_TYPE_FLOAT;
        } elseif ($sqlType === 'TIMESTAMP') {
            return static::PHINX_TYPE_TIMESTAMP;
        } elseif ($sqlType === 'DATE') {
            return static::PHINX_TYPE_DATE;
        } elseif ($sqlType === 'INTERVAL') {
            return static::PHINX_TYPE_INTERVAL;
        } elseif ($sqlType === 'BLOB') {
            return static::PHINX_TYPE_BLOB;
        } elseif ($sqlType === 'RAW' && $precision === 16) {
            return static::PHINX_TYPE_UUID;
        } elseif ($sqlType === 'RAW') {
            return static::PHINX_TYPE_BLOB;
        } elseif ($sqlType === 'NUMBER' && $precision === 1) {
            return static::PHINX_TYPE_BOOLEAN;
        } elseif ($sqlType === 'NUMBER' && $precision === 18) {
            return static::PHINX_TYPE_DECIMAL;
        } else {
            throw new \RuntimeException('The Oracle type: "' . $sqlType . '" is not supported');
        }
    }

    // TODO - next implementaion - checking for other SID names
    /**
     * {@inheritdoc}
     */
    public function createDatabase($name,$options = [])
    {
        // create SID ???
    }

    // TODO - next implementaion - checking for other SID names
    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        // checking another SID
    }

    // TODO - next implementaion - checking for other SID names
    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        // drop SID ???
    }

    // TODO - check
    /**
     * Get the definition for a `DEFAULT` statement.
     *
     * @param mixed $default default value
     * @param string $columnType column type added
     * @return string
     */
    protected function getDefaultValueDefinition($default, $columnType = null)
    {
        if (is_string($default) && 'CURRENT_TIMESTAMP' !== $default) {
            $default = $this->getConnection()->quote($default);
        } elseif (is_bool($default)) {
            $default = $this->castToBool($default);
        } elseif ($columnType === static::PHINX_TYPE_BOOLEAN) {
            $default = $this->castToBool((bool)$default);
        }

        return isset($default) ? 'DEFAULT ' . $default : '';
    }

    // TODO - check scale, precision
    /**
     * Gets the PostgreSQL Column Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $buffer = [];
        $sqlType = $this->getSqlType($column->getType());
        $buffer[] = strtoupper($sqlType['name']);
        // integers cant have limits in Oracle
        $noLimits = [
            static::PHINX_TYPE_INTEGER,
            static::PHINX_TYPE_BIG_INTEGER,
            static::PHINX_TYPE_FLOAT,
            static::PHINX_TYPE_UUID,
            static::PHINX_TYPE_BOOLEAN
        ];
        if (!in_array($column->getType(), $noLimits) && ($column->getLimit() || isset($sqlType['limit']))) {
            $buffer[] = sprintf('(%s)', $column->getLimit() ?: $sqlType['limit']);
        }
        if ($column->getPrecision() && $column->getScale()) {
            $buffer[] = '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
        }
        if ($column->getDefault() === null && $column->isNull()) {
            $buffer[] = 'DEFAULT NULL';
        } else {
            $buffer[] = $this->getDefaultValueDefinition($column->getDefault());
        }
        if ($column->isIdentity()) {
            $buffer[] = 'GENERATED BY DEFAULT ON NULL AS IDENTITY MINVALUE -999999999999999999999999 MAXVALUE 999999999999999999999999 INCREMENT BY 1';
        } else {
            $buffer[] = $column->isNull() ? 'NULL' : 'NOT NULL';
        }
        return implode(' ', $buffer);
    }

    // TODO - !!!!!!!!!!!!
    /**
     * Gets the PostgreSQL Column Comment Definition for a column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @param string $tableName Table name
     * @return string
     */
    protected function getColumnCommentSqlDefinition(Column $column, $tableName)
    {
        // passing 'null' is to remove column comment
        $comment = (strcasecmp($column->getComment(), 'NULL') !== 0)
            ? $this->getConnection()->quote($column->getComment())
            : 'NULL';

        return sprintf(
            'COMMENT ON COLUMN %s.%s IS %s;',
            $this->quoteTableName($tableName),
            $this->quoteColumnName($column->getName()),
            $comment
        );
    }

    // TODO - !!!!!!!!!!!!
    /**
     * Gets the PostgreSQL Index Definition for an Index object.
     *
     * @param \Phinx\Db\Table\Index  $index Index
     * @param string $tableName Table name
     * @return string
     */
    protected function getIndexSqlDefinition(Index $index, $tableName)
    {
        $parts = $this->getSchemaName($tableName);

        if (is_string($index->getName())) {
            $indexName = $index->getName();
        } else {
            $columnNames = $index->getColumns();
            $indexName = sprintf('%s_%s', $parts['table'], implode('_', $columnNames));
        }
        $def = sprintf(
            "CREATE %s INDEX %s ON %s (%s);",
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $this->quoteColumnName($indexName),
            $this->quoteTableName($tableName),
            implode(',', array_map([$this, 'quoteColumnName'], $index->getColumns()))
        );

        return $def;
    }

    // TODO - check
    /**
     * Gets the MySQL Foreign Key Definition for an ForeignKey object.
     *
     * @param \Phinx\Db\Table\ForeignKey $foreignKey
     * @param string     $tableName  Table name
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, $tableName)
    {
        $parts = $this->getSchemaName($tableName);

        $constraintName = $foreignKey->getConstraint() ?: ($parts['table'] . '_' . implode('_', $foreignKey->getColumns()) . '_fkey');
        $def = ' CONSTRAINT ' . $this->quoteColumnName($constraintName) .
            ' FOREIGN KEY ("' . implode('", "', $foreignKey->getColumns()) . '")' .
            " REFERENCES {$this->quoteTableName($foreignKey->getReferencedTable()->getName())} (\"" .
            implode('", "', $foreignKey->getReferencedColumns()) . '")';
        if ($foreignKey->getOnDelete()) {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }

        return $def;
    }

    /**
     * Creates the specified schema.
     *
     * @param  string $schemaName Schema Name
     * @return void
     */
    public function createSchema($schemaName = 'public')
    {
        // @TODO : create new user
        //$sql = sprintf('CREATE SCHEMA AUTHORIZATION %s;', $this->quoteSchemaName($schemaName));
       // $this->execute($sql);
    }

    // TODO - build test
    /**
     * Checks to see if a schema exists.
     *
     * @return bool
     */
    public function hasSchemaTable()
    {
        $tableSearchName = $this->getGlobalSchemaName() . $this->getSchemaTableName();

        //var_dump($tableSearchName);die;

        $result = $this->fetchRow(
            sprintf(
                'SELECT count(*) as count FROM ALL_TABLES WHERE owner || table_name = \'%s\'',
                $tableSearchName
            )
        );
        //var_dump($result['COUNT'] > 0);die;
        return $result['COUNT'] > 0;
    }

    // TODO - build test
    /**
     * Checks to see if a schema exists.
     *
     * @param string $schemaName Schema Name
     * @return bool
     */
    public function hasSchema($schemaName)
    {
        $sql = sprintf(
            "SELECT count(*) as count FROM ALL_TABLES WHERE owner = '%s'",
            $schemaName
        );
        $result = $this->fetchRow($sql);

        return $result['COUNT'] > 0;
    }

    /**
     * Drops the specified schema table.
     *
     * @param string $schemaName Schema name
     * @return void
     */
    public function dropSchema($schemaName)
    {
        // @TODO : delete user/schema
       // $sql = sprintf("DROP SCHEMA IF EXISTS %s CASCADE;", $this->quoteSchemaName($schemaName));
       // $this->execute($sql);
    }

    /**
     * Drops all schemas.
     *
     * @return void
     */
    public function dropAllSchemas()
    {
        // @TODO : delete everything
      //  foreach ($this->getAllSchemas() as $schema) {
       //     $this->dropSchema($schema);
       // }
    }


    // TODO - build test
    /**
     * Returns schemas.
     *
     * @return array
     */
    public function getAllSchemas()
    {
        $sql = "SELECT DISTINCT owner FROM ALL_TABLES WHERE owner IS NOT NULL";
        $items = $this->fetchAll($sql);
        $schemaNames = [];
        foreach ($items as $item) {
            $schemaNames[] = $item['owner'];
        }

        return $schemaNames;
    }

    // TODO - build test
    // TODO - check
    /**
     * {@inheritdoc}
     */
    public function isValidColumnType(Column $column)
    {
        // If not a standard column type, maybe it is array type?
        return (parent::isValidColumnType($column) || $this->isArrayType($column->getType()));
    }

    // TODO - build test
    // TODO - check
    /**
     * Check if the given column is an array of a valid type.
     *
     * @param  string $columnType
     * @return bool
     */
    protected function isArrayType($columnType)
    {
        if (!preg_match('/^([a-z]+)(?:\[\]){1,}$/', $columnType, $matches)) {
            return false;
        }

        $baseType = $matches[1];

        return in_array($baseType, $this->getColumnTypes());
    }

    // TODO - build test
    /**
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName()
    {
        return $this->schemaTableName;
    }

    // TODO - build test
    /**
     * Gets the schema name.
     *
     * @param string $tableName Table name
     * @return array
     */
    private function getSchemaName($tableName)
    {
        $schema = $this->getGlobalSchemaName();
        $table = $tableName;
        if (false !== strpos($tableName, '.')) {
            list($schema, $table) = explode('.', $tableName);
        }

        return [
            'schema' => $schema,
            'table' => $table,
        ];
    }

    // TODO - build test
    /**
     * Gets the default schema name.
     *
     * @return string
     */
    private function getGlobalSchemaName()
    {
        $options = $this->getOptions();
        //  set default schema name based on user name
        return empty($options['schema']) ? $options['user'] : $options['schema'];
    }

    // TODO - build test
    /**
     * {@inheritdoc}
     */
    public function castToBool($value)
    {
        return (bool)$value ? 1 : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function getDecoratedConnection()
    {
        // @ oracle has decorated?
    }
}
