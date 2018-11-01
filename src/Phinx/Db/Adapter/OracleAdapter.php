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
        if ($parts['schema'] === '') {
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

        $result = $this->fetchRow(
            sprintf(
                'SELECT count(*) as count FROM ALL_TABLES WHERE owner || table_name = \'%s\'',
                $tableSearchName
            )
        );
        return $result['COUNT'] > 0;
    }

    /**
     * Creates new table.
     *
     * {@inheritdoc}
     */
    public function createTable(Table $table, array $columns = [], array $indexes = [])
    {
        $options = $table->getOptions();
        $parts = $this->getSchemaName($table->getName());

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
        $this->execute($sql);

        // process column comments
        if (!empty($this->columnsWithComments)) {
            foreach ($this->columnsWithComments as $column) {
                $sql = $this->getColumnCommentSqlDefinition($column, $table->getName());
                $this->execute($sql);
            }
        }

        // execute the sql
        //var_dump($sql);//die;

        // set the indexes
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql = $this->getIndexSqlDefinition($index, $table->getName());
                $this->execute($sql);
            }
        }

        // TODO - build test
        // process table comments
        if (isset($options['comment'])) {
            $sql = sprintf(
                'COMMENT ON TABLE %s IS %s',
                $this->quoteSchemaTableName($table->getName()),
                $this->getConnection()->quote($options['comment'])
            );
            $this->execute($sql);
        }
    }

    // OK - PASSED
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

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    protected function getDropTableInstructions($tableName)
    {
        $sql = sprintf('DROP TABLE %s',
            $this->quoteSchemaTableName($tableName));

        return new AlterInstructions([], [$sql]);
    }

    // OK - PASSED
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

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    public function getColumns($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $schemaTableName = $parts['schema'] . $parts['table'];
        $columns = [];
        $sql = sprintf(
            "select TABLE_NAME \"table_name\", COLUMN_NAME \"name\", DATA_TYPE \"type\", NULLABLE \"null\", 
            DATA_DEFAULT \"default\", DATA_LENGTH \"char_length\", DATA_PRECISION \"precision\", DATA_SCALE \"scale\", 
            COLUMN_ID \"ordinal_position\" FROM ALL_TAB_COLUMNS WHERE owner || table_name = '%s'",
            $schemaTableName
        );

        $rows = $this->fetchAll($sql);
        foreach ($rows as $columnInfo) {
            $default = null;
            if (trim($columnInfo['default']) != 'NULL') {
                $default = trim($columnInfo['default']);
            }
            $column = new Column();
            $column->setName($columnInfo['name'])
                ->setType($this->getPhinxType($columnInfo['type'], $columnInfo['precision'], $columnInfo['scale']))
                ->setNull($columnInfo['null'] !== 'N')
                ->setDefault($default)
                ->setComment($this->getColumnComment($columnInfo['table_name'], $columnInfo['name']));
            if (!empty($columnInfo['char_length'])) {
                $column->setLimit($columnInfo['char_length']);
            }
            $columns[$columnInfo['name']] = $column;
        }
        return $columns;
    }
    // OK - PASSED
    /**
     * Get the comment for a column
     *
     * @param string $tableName Table Name
     * @param string $columnName Column Name
     *
     * @return string
     */
    public function getColumnComment($tableName, $columnName)
    {
        $parts = $this->getSchemaName($tableName);
        $schemaTableName = $parts['schema'] . $parts['table'];
        $sql = sprintf(
            "select COMMENTS from ALL_COL_COMMENTS WHERE COLUMN_NAME = '%s' and OWNER || TABLE_NAME = '%s'",
            $columnName,
            $schemaTableName
        );
        $row = $this->fetchRow($sql);
        return $row['COMMENTS'];
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

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    protected function getAddColumnInstructions(Table $table, Column $column)
    {
        $instructions = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $this->quoteSchemaTableName($table->getName()),
            $this->quoteColumnName($column->getName()),
            $this->getColumnSqlDefinition($column)
        );

        return new AlterInstructions([], [$instructions]);
    }

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    protected function getRenameColumnInstructions($tableName, $columnName, $newColumnName)
    {
        $result = $this->hasColumn($tableName, $columnName);
        if (!(bool)$result) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }

        $instructions = new AlterInstructions();
        $instructions->addPostStep(
            sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->quoteSchematableName($tableName),
                $this->quoteColumnName($columnName),
                $this->quoteColumnName($newColumnName)
            )
        );

        return $instructions;
    }

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn)
    {
        $alter = sprintf(
            'ALTER TABLE %s MODIFY(%s %s)',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($newColumn->getName()),
            $this->getColumnSqlDefinition($newColumn)
        );
        $sql = $this->getColumnCommentSqlDefinition($newColumn, $tableName);
        $this->execute($sql);

        return new AlterInstructions([], [$alter]);
    }

    // OK - PASSED
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

        return new AlterInstructions([], [$alter]);
    }

    // --------------------------------------------------- START work

    // OK - PASSED
    /**
     * Get an array of indexes from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $fullTableName = $parts['schema'] . $parts['table'];

        $indexes = [];
        $sql = sprintf("SELECT
                  INDEX_NAME, COLUMN_NAME FROM ALL_IND_COLUMNS
                  WHERE TABLE_OWNER || TABLE_NAME = '%s' ORDER BY TABLE_NAME, INDEX_NAME",
            $fullTableName);
        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            if (!isset($indexes[$row['INDEX_NAME']])) {
                $indexes[$row['INDEX_NAME']] = ['columns' => []];
            }
            $indexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
        }

        return $indexes;
    }

    // OK - PASSED
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

    // OK - PASSED
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

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    protected function getAddIndexInstructions(Table $table, Index $index)
    {
        $alter = $this->getIndexSqlDefinition($index, $table->getName());

        return new AlterInstructions([], [$alter]);
    }

    // OK - PASSED
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
                    'DROP INDEX %s',
                    '"' . ($parts['schema'] . '".' . $this->quoteColumnName($indexName))
                )]);
            }
        }

        throw new \InvalidArgumentException(sprintf(
            "The specified index on columns '%s' does not exist",
            implode(',', $columns)
        ));
    }

    // OK - PASSED
    /**
     * {@inheritdoc}
     */
    protected function getDropIndexByNameInstructions($tableName, $indexName)
    {
        $parts = $this->getSchemaName($tableName);

        $sql = sprintf(
            'DROP INDEX %s',
            '"' . ($parts['schema'] . '".' . $this->quoteColumnName($indexName))
        );

        return new AlterInstructions([], [$sql]);
    }

    // OK - PASSED
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

    // OK - PASSED
    /**
     * Get an array of foreign keys from a particular table.
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getForeignKeys($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $fullTableName = $parts['schema'] . $parts['table'];
        $foreignKeys = [];
        $rows = $this->fetchAll(sprintf(
            "SELECT a.constraint_name, a.owner, a.table_name, a.column_name,  
						/* referenced values */
					    c_pk.constraint_name AS referenced_constraint_name,
						c.r_owner AS referenced_owner,
						c_pk.table_name  AS referenced_table_name,
						b.column_name  AS referenced_column_name			 
  					FROM all_cons_columns a
 					 JOIN all_constraints c ON a.owner = c.owner AND a.constraint_name = c.constraint_name
 					 JOIN all_constraints c_pk ON c.r_owner = c_pk.owner AND c.r_constraint_name = c_pk.constraint_name
 					 JOIN all_cons_columns b ON b.owner = c_pk.owner AND b.constraint_name = c_pk.constraint_name
 					WHERE c.constraint_type = 'R' /* foreign key oracle code */
 					AND a.owner || a.table_name = '%s'",
            $fullTableName
        ));
        foreach ($rows as $row) {
            $foreignKeys[$row['CONSTRAINT_NAME']]['table'] = $row['TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['columns'][] = $row['COLUMN_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['referenced_table'] = $row['REFERENCED_TABLE_NAME'];
            $foreignKeys[$row['CONSTRAINT_NAME']]['referenced_columns'][] = $row['REFERENCED_COLUMN_NAME'];
        }
        return $foreignKeys;
    }

    // TODO - build test
    /**
     * {@inheritdoc}
     */
    protected function getAddForeignKeyInstructions(Table $table, ForeignKey $foreignKey)
    {
        $alter = sprintf(
            'ALTER TABLE %s ADD %s',
            $this->quoteSchemaTableName($table->getName()),
            $this->getForeignKeySqlDefinition($foreignKey, $table->getName())
        );

        return new AlterInstructions([], [$alter]);
    }

    // TODO - build test
    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyInstructions($tableName, $constraint)
    {
        $alter = sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($constraint)
        );

        return new AlterInstructions([],[$alter]);
    }

    // TODO - build test
    /**
     * {@inheritdoc}
     */
    protected function getDropForeignKeyByColumnsInstructions($tableName, $columns)
    {
        $instructions = new AlterInstructions();

        $parts = $this->getSchemaName($tableName);
        $fullTableName = $parts['schema'] . $parts['table'];
        $sql = "SELECT a.constraint_name 
  					FROM all_cons_columns a
 					 JOIN all_constraints c ON a.owner = c.owner AND a.constraint_name = c.constraint_name
 					 JOIN all_constraints c_pk ON c.r_owner = c_pk.owner AND c.r_constraint_name = c_pk.constraint_name
 					WHERE c.constraint_type = 'R' /* foreign key oracle code */
 					AND a.owner || a.table_name = '%s'";

        $array = [];
        foreach ($columns as $col) {
            $array[] = $this->quoteColumnName($col);
        }

        $rows = $this->fetchAll(sprintf(
            $sql,
            $fullTableName,
            implode(',', $array),
            implode(',', $array)
        ));

        foreach ($rows as $row) {
            $newInstr = $this->getDropForeignKeyInstructions($tableName, $row['CONSTRAINT_NAME']);
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
                return ['name' => 'TIMESTAMP', 'limit' => 6];
            case static::PHINX_TYPE_DATE:
            case static::PHINX_TYPE_DATETIME:
                return ['name' => 'DATE'];
            case static::PHINX_TYPE_INTERVAL:
                return ['name' => 'INTERVAL'];

            // numeric datatypes
            case static::PHINX_TYPE_BOOLEAN:
                return ['name' => 'NUMBER', 'precision' => 5, 'scale' => 0];
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
                return ['name' => 'RAW', 'precision' => 16, 'scale' => 0];
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
     * @param int $scale Scale of NUMBER type to define Phinx Type.
     * @throws \RuntimeException
     * @param string $sqlType SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlType, $precision = null, $scale = null)
    {
        $precision = (int)$precision;
        $scale = (int)$scale;

        if ($sqlType === 'VARCHAR2') {
            return static::PHINX_TYPE_STRING;
        } elseif ($sqlType === 'CHAR') {
            return static::PHINX_TYPE_CHAR;
        } elseif ($sqlType == 'LONG') {
            return static::PHINX_TYPE_TEXT;
        } elseif ($sqlType === 'NUMBER' && $precision === 5 && $scale === 0) {
            return static::PHINX_TYPE_BOOLEAN;
        } elseif ($sqlType === 'NUMBER' && $precision === 11 && $scale === 0) {
            return static::PHINX_TYPE_INTEGER;
        } elseif ($sqlType === 'NUMBER' && $precision === 18 && $scale === 0) {
            return static::PHINX_TYPE_DECIMAL;
        } elseif ($sqlType === 'NUMBER' && $precision === 20 && $scale === 0) {
            return static::PHINX_TYPE_BIG_INTEGER;
        } elseif ($sqlType === 'NUMBER') {
            return static::PHINX_TYPE_FLOAT;
        } elseif ($sqlType === 'TIMESTAMP') {
            return static::PHINX_TYPE_TIMESTAMP;
        } elseif ($sqlType === 'TIMESTAMP(6)') {
            return static::PHINX_TYPE_TIMESTAMP;
        } elseif ($sqlType === 'DATE') {
            return static::PHINX_TYPE_DATE;
        } elseif ($sqlType === 'INTERVAL') {
            return static::PHINX_TYPE_INTERVAL;
        } elseif ($sqlType === 'BLOB') {
            return static::PHINX_TYPE_BLOB;
        } elseif ($sqlType === 'RAW' && $precision === 16 && $scale === 0) {
            return static::PHINX_TYPE_UUID;
        } elseif ($sqlType === 'RAW') {
            return static::PHINX_TYPE_BLOB;
        } else {
            throw new \RuntimeException('The Oracle type: "' .$sqlType. ')" is not supported');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabase($name,$options = [])
    {
        // create SID ???
    }

    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        // checking another SID
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        // drop SID ???
    }

    // TODO - do test
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

        return isset($default) ? 'DEFAULT ON NULL ' . $default : '';
    }

    // TODO - check scale, precision, noLimits
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

        // TODO check isset or NULL
        if (!$column->getPrecision() === NULL || isset($sqlType['precision'])) {
            $buffer[] = '(';
            $buffer[] = $column->getPrecision() ?: $sqlType['precision'];
            if (!$column->getScale() === NULL || isset($sqlType['scale'])) {
                $buffer[] =  ',';
                $buffer[] = $column->getScale() ?: $sqlType['scale'];
            }
            $buffer[] = ')';
        }
        if ($column->getDefault() === null && $column->isNull()) {
            $buffer[] = 'DEFAULT NULL';
        } else {
            $buffer[] = $this->getDefaultValueDefinition($column->getDefault());
        }
        if ($column->isIdentity()) {
            $buffer[] = 'GENERATED BY DEFAULT ON NULL AS IDENTITY MINVALUE -999999999999999999999999 MAXVALUE 999999999999999999999999 INCREMENT BY 1';
        } else {
            $buffer[] = $column->isNull() ? 'NULL' : (!$column->getDefault() === null ? 'NOT NULL' : '');
        }
        return implode(' ', $buffer);
    }

    // TODO - build test
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
        $comment = (strcasecmp($column->getComment(), '') !== 0)
            ? $this->getConnection()->quote($column->getComment())
            : '';

        $sql = sprintf(
            'COMMENT ON COLUMN %s.%s IS ',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($column->getName()));
            if ($comment === '') {
                $sql .= "''";
            } else {
                $sql .= $comment;
            }

        return $sql;
    }

    // OK - PASSED
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
            "CREATE %s INDEX %s ON %s (%s)",
            ($index->getType() === Index::UNIQUE ? 'UNIQUE' : ''),
            $this->quoteColumnName($indexName),
            $this->quoteSchemaTableName($tableName),
            implode(',', array_map([$this, 'quoteColumnName'], $index->getColumns()))
        );

        return $def;
    }

    // TODO - rebuild (fast)
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
    /**
     * {@inheritdoc}
     */
    public function isValidColumnType(Column $column)
    {
        // If not a standard column type, maybe it is array type?
        return (parent::isValidColumnType($column) || $this->isArrayType($column->getType()));
    }

    // TODO - build test
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

    // OK - Passed
    /**
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName()
    {
        return $this->schemaTableName;
    }

    // OK - Passed
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

    // OK - Passed
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


    // OK - Passed
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


    // TODO - build test
    // OK - Passed
    /**
     * {@inheritdoc}
     */
    public function getVersionLog()
    {
        $result = [];

        switch ($this->options['version_order']) {
            case \Phinx\Config\Config::VERSION_ORDER_CREATION_TIME:
                $orderBy = '"version" ASC';
                break;
            case \Phinx\Config\Config::VERSION_ORDER_EXECUTION_TIME:
                $orderBy = '"start_time" ASC, "version" ASC';
                break;
            default:
                throw new \RuntimeException('Invalid version_order configuration option');
        }
        $rows = $this->fetchAll(sprintf('SELECT * FROM %s ORDER BY %s', $this->quoteSchemaTableName($this->getSchemaTableName()), $orderBy));
        foreach ($rows as $version) {
            $result[$version['version']] = $version;
        }

        return $result;
    }
}
