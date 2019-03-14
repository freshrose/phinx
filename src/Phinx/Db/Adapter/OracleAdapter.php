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
use Phinx\Migration\MigrationInterface;

/**
 * Phinx Oracle Adapter.
 *
 * @author Marek - FreshFlow Systems s.r.o
 */
class OracleAdapter extends PdoAdapter implements AdapterInterface
{
    /**
     * Columns with comments
     *
     * @var array
     */
    protected $columnsWithComments = [];
    private $upper = true;
    private const ORACLE_DEFAULT = '12.1';

    public function getUpper() : bool
    {
        return $this->upper;
    }

    /**
     * @param bool $upper
     */
    public function setUpper($upper)
    {
        $this->upper = $upper;
    }

    /**
     * @param string $string
     * @return string $string
     */
    public function checkUpper($string) : string
    {
        if ($this->getUpper()) {
            return strtoupper($string);
        }

        return $string;
    }

    /**
     * @param string $name
     * @return string $name
     */
    public function getShortName($name) : string
    {
        if (strlen($name) > $this->getVersionLimit()) {
            $tableNameArray = explode('_', $name);
            $arrayLen = count($tableNameArray);
            $oracleLenLimit = $this->getVersionLimit();
            $chunkLen = floor($oracleLenLimit / $arrayLen) - 1;
            $newArray = array_map(
                function ($param) use ($chunkLen) {
                    return substr($param, 0, $chunkLen);
                },
                $tableNameArray
            );

            return implode('_', $newArray);
        }

        return $name;
    }

    /**
     * @return int
     */
    public function getVersionLimit() : int
    {
        $options = $this->getOptions();
        if (!isset($options['oracle_version'])) {
            $options['oracle_version'] = static::ORACLE_DEFAULT;
        }
        if ($options['oracle_version'] === '12.1') {
            return 30;
        }
        if ($options['oracle_version'] === '12.2') {
            return 60;
        }
        if ($options['oracle_version'] === 'debug') {
            return 10;
        }

        return 128;
    }

    /**
     * {@inheritdoc}
     */
    public function connect() : void
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
    /**
     * {@inheritdoc}
     */
    public function getDecoratedConnection() : void
    {
        //@TODO : build CakePHP oracleAdapter
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect() : void
    {
        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     * @return bool
     */
    public function hasTransactions() : bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction() : void
    {
        $this->execute('SET TRANSACTION');
    }

    /**
     * {@inheritdoc}
     */
    public function commitTransaction() : void
    {
        // make transactions permanent
        $this->execute('COMMIT');
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction() : void
    {
        // undo whole transactions
        $this->execute('ROLLBACK');
    }

    /**
     * Quotes a schema name for use in a query.
     *
     * @param string $schemaName Schema Name
     * @return string
     */
    public function quoteSchemaName($schemaName)
    {
        return $this->checkUpper($this->quoteColumnName($schemaName));
    }

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

        return $this->checkUpper($result);
    }

    /**
     * Quotes a table name for use in a query.
     *
     * {@inheritdoc}
     */
    public function quoteTableName($tableName)
    {
        return $this->checkUpper($this->quoteColumnName($tableName));
    }

    /**
     * Quotes a column name for use in a query.
     *
     * {@inheritdoc}
     */
    public function quoteColumnName($columnName)
    {
        return $this->checkUpper('"' .  $columnName . '"');
    }

    /**
     * Quotes a column name for use in a query.
     *
     * {@inheritdoc}
     */
    public function quoteIndexName($columnName)
    {
        return $this->checkUpper("'" .  $columnName . "'");
    }

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
                ->setType('biginteger')
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
        $tableName = $this->getShortName($table->getName());
        $sql .= $this->quoteSchemaTableName($tableName) . ' (';

        $this->columnsWithComments = [];
        foreach ($columns as $column) {
            $sql .= $this->quoteColumnName($this->getShortName($column->getName())) . ' ' . $this->getColumnSqlDefinition($column) . ', ';

            // set column comments, if needed
            if ($column->getComment()) {
                $this->columnsWithComments[] = $column;
            }
        }

        // set the primary key(s)
        if (isset($options['primary_key'])) {
            $sql = rtrim($sql);
            // set the NAME of pkey to internal code -> 12.1 limit to 30char , 12.2 limit to 60char
            $sql .= sprintf(' CONSTRAINT %s PRIMARY KEY (', $this->quoteColumnName('PK_' . $this->getShortName($table->getName())));
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
        // set the indexes
        if (!empty($indexes)) {
            foreach ($indexes as $index) {
                $sql = $this->getIndexSqlDefinition($index, $table->getName());
                $this->execute($sql);
            }
        }

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

    /**
     * {@inheritdoc}
     */
    protected function getRenameTableInstructions($tableName, $newTableName)
    {
        $sql = sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($this->getShortName($newTableName))
        );

        return new AlterInstructions([], [$sql]);
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable($tableName)
    {
        $this->execute(sprintf('DROP TABLE %s', $this->quoteSchemaTableName($tableName)));
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropTableInstructions($tableName)
    {
        $sql = sprintf(
            'DROP TABLE %s',
            $this->quoteSchemaTableName($tableName)
        );

        return new AlterInstructions([], [$sql]);
    }

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
            DATA_DEFAULT \"default\", DATA_LENGTH \"char_length\", DATA_PRECISION \"limit\", DATA_SCALE \"scale\", 
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
                ->setType($this->getPhinxType($columnInfo['type'], $columnInfo['limit'], $columnInfo['scale']))
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
            $this->checkUpper($columnName),
            $schemaTableName
        );
        $row = $this->fetchRow($sql);

        return $row['COMMENTS'];
    }

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
            $this->checkUpper($columnName)
        ));

        return $result['COUNT'] > 0;
    }
    public function isNotNullColumn($tableName, $columnName)
    {
        $parts = $this->getSchemaName($tableName);
        $parts['column'] = $this->checkUpper($columnName);
        $schemaTableColumnName = $parts['schema'] . $parts['table'] . $parts['column'];
        $result = $this->fetchRow(sprintf(
            "select count(*) as count FROM ALL_TAB_COLUMNS WHERE owner || table_name || column_name= '%s' and nullable = 'N'",
            $schemaTableColumnName
        ));

        return $result['COUNT'] > 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAddColumnInstructions(Table $table, Column $column)
    {
        $result = $this->hasTable($table->getName());
        if (!(bool)$result) {
            throw new \InvalidArgumentException("The specified table does not exist: " . $table->getName());
        }
        $instructions = sprintf(
            'ALTER TABLE %s ADD %s %s',
            $this->quoteSchemaTableName($table->getName()),
            $this->quoteColumnName($this->getShortName($column->getName())),
            $this->getColumnSqlDefinition($column)
        );

        return new AlterInstructions([], [$instructions]);
    }

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
                $this->quoteColumnName($this->getShortName($newColumnName))
            )
        );

        return $instructions;
    }

    /**
     * {@inheritdoc}
     */
    protected function getChangeColumnInstructions($tableName, $columnName, Column $newColumn)
    {
        $result = $this->hasColumn($tableName, $columnName);
        if (!(bool)$result) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }
        $newInstructions = $this->getColumnSqlDefinition($newColumn);
        if ($this->isNotNullColumn($tableName, $columnName)) {
            $newInstructions = str_ireplace(' NOT NULL', '', $newInstructions);
        }
        $alter = sprintf(
            'ALTER TABLE %s MODIFY(%s %s)',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($newColumn->getName()),
            $newInstructions
        );
        $sql = $this->getColumnCommentSqlDefinition($newColumn, $tableName);
        $this->execute($sql);

        return new AlterInstructions([], [$alter]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDropColumnInstructions($tableName, $columnName)
    {
        $result = $this->hasColumn($tableName, $columnName);
        if (!(bool)$result) {
            throw new \InvalidArgumentException("The specified column does not exist: $columnName");
        }
        $alter = sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->quoteSchemaTableName($tableName),
            $this->quoteColumnName($columnName)
        );

        return new AlterInstructions([], [$alter]);
    }

    /**
     * Get an array of indexes from a particular table.
     * J.Rosa query => not returning all index names. cannot search hasIndexByName TODO: fix
     *
     * @param string $tableName Table Name
     * @return array
     */
    protected function getIndexes($tableName)
    {
        $parts = $this->getSchemaName($tableName);
        $fullTableName = $parts['schema'] . $parts['table'];

        $indexes = [];
        $sql = sprintf(
            "
                            SELECT
                            	B.INDEX_NAME,
                            	A.COLUMN_NAME 
                            FROM
                            	ALL_TAB_COLUMNS A
                            	LEFT JOIN ALL_IND_COLUMNS B ON A.OWNER = B.TABLE_OWNER 
                            	AND A.TABLE_NAME = B.TABLE_NAME 
                            	AND A.COLUMN_NAME = B.COLUMN_NAME 
                            WHERE
                            	OWNER || A.TABLE_NAME = '%s'
                            ORDER BY B.TABLE_NAME, B.INDEX_NAME
        ",
            $fullTableName
        );
        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            if (!isset($indexes[$row['INDEX_NAME']])) {
                $indexes[$row['INDEX_NAME']] = ['columns' => []];
            }
            $indexes[$row['INDEX_NAME']]['columns'][] = $row['COLUMN_NAME'];
        }

        return $indexes;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndex($tableName, $columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        if ($this->upper) {
            $columns = array_map('strtoupper', $columns);
        }
        $indexes = $this->getIndexes($tableName);

        foreach ($indexes as $index) {
            $a = array_diff($columns, $index['columns']);

            if (empty($a)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hasIndexByName($tableName, $indexName)
    {
        $indexName = $this->checkUpper($indexName);
        $indexes = $this->getIndexes($tableName);
        foreach ($indexes as $name => $index) {
            if ($name === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAddIndexInstructions(Table $table, Index $index)
    {
        $alter = $this->getIndexSqlDefinition($index, $table->getName());

        return new AlterInstructions([], [$alter]);
    }

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

    /**
     * {@inheritdoc}
     */
    public function hasForeignKey($tableName, $columns, $constraint = null)
    {
        if (is_string($columns)) {
            $columns = [$columns]; // str to array
        }
        if ($this->upper) {
            $columns = array_map('strtoupper', $columns);
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

        return new AlterInstructions([], [$alter]);
    }

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
                return ['name' => 'NUMBER', 'limit' => 5, 'scale' => 0];
            case static::PHINX_TYPE_SMALL_INTEGER:
                return ['name' => 'NUMBER', 'limit' => 6, 'scale' => 0];
            case static::PHINX_TYPE_INTEGER:
                return ['name' => 'NUMBER', 'limit' => 11, 'scale' => 0];
            case static::PHINX_TYPE_DECIMAL:
                return ['name' => 'NUMBER', 'limit' => 18, 'scale' => 0];
            case static::PHINX_TYPE_BIG_INTEGER:
                return ['name' => 'NUMBER', 'limit' => 24, 'scale' => 0];
            case static::PHINX_TYPE_FLOAT:
                return ['name' => 'NUMBER'];
            case static::PHINX_TYPE_DOUBLE;

                return ['name' => 'BINARY_DOUBLE'];

            // character datatypes
            case static::PHINX_TYPE_TEXT:
                return ['name' => 'LONG'];
            case static::PHINX_TYPE_STRING:
                return ['name' => 'VARCHAR2', 'limit' => '2000 CHAR'];
            case static::PHINX_TYPE_UUID:
                return ['name' => 'RAW', 'limit' => 16, 'scale' => 0];
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

    /**
     * Returns Phinx type by SQL type
     *
     * @param string $sqlType SQL Type definition
     * @param int $limit limit of NUMBER type to define Phinx Type.
     * @param int $scale Scale of NUMBER type to define Phinx Type.
     * @throws \RuntimeException
     * @param string $sqlType SQL type
     * @returns string Phinx type
     */
    public function getPhinxType($sqlType, $limit = null, $scale = null)
    {
        $limit = (int)$limit;
        $scale = (int)$scale;

        if ($sqlType === 'VARCHAR2') {
            return static::PHINX_TYPE_STRING;
        } elseif ($sqlType === 'CHAR') {
            return static::PHINX_TYPE_CHAR;
        } elseif ($sqlType == 'LONG') {
            return static::PHINX_TYPE_TEXT;
        } elseif ($sqlType === 'NUMBER' && $limit === 5 && $scale === 0) {
            return static::PHINX_TYPE_BOOLEAN;
        } elseif ($sqlType === 'NUMBER' && $limit === 6 && $scale === 0) {
            return static::PHINX_TYPE_SMALL_INTEGER;
        } elseif ($sqlType === 'NUMBER' && $limit === 11 && $scale === 0) {
            return static::PHINX_TYPE_INTEGER;
        } elseif ($sqlType === 'NUMBER' && $limit === 18 && $scale === 0) {
            return static::PHINX_TYPE_DECIMAL;
        } elseif ($sqlType === 'NUMBER' && $limit === 24 && $scale === 0) {
            return static::PHINX_TYPE_BIG_INTEGER;
        } elseif ($sqlType === 'NUMBER') {
            return static::PHINX_TYPE_FLOAT;
        } elseif ($sqlType === 'BINARY_DOUBLE') {
            return static::PHINX_TYPE_DOUBLE;
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
        } elseif ($sqlType === 'RAW' && $limit === 16 && $scale === 0) {
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
    public function createDatabase($name, $options = [])
    {
        // @TODO : create SID ???
    }

    /**
     * {@inheritdoc}
     */
    public function hasDatabase($name)
    {
        // @TODO : checking another SID
    }

    /**
     * {@inheritdoc}
     */
    public function dropDatabase($name)
    {
        // @TODO : drop SID ???
    }

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

        $timestamp = '';
        if (in_array($columnType, ['timestamp', 'time', 'date', 'datetime']) && 'CURRENT_TIMESTAMP' !== $default) {
            $timestamp = strlen($default) === 12 ? 'date ' : 'timestamp ';
        }

        return isset($default) ? $timestamp . $default : '';
    }

    /**
     * Gets the SQL Column Definition for a Column object.
     *
     * @param \Phinx\Db\Table\Column $column Column
     * @return string
     */
    protected function getColumnSqlDefinition(Column $column)
    {
        $buffer = [];
        $sqlType = $this->getSqlType($column->getType());
        $buffer[] = strtoupper($sqlType['name']);
        /*
        // integers cant have limits in Oracle
        $noLimits = [
            static::PHINX_TYPE_INTEGER,
            static::PHINX_TYPE_SMALL_INTEGER,
            static::PHINX_TYPE_BIG_INTEGER,
            static::PHINX_TYPE_FLOAT,
            static::PHINX_TYPE_UUID,
            static::PHINX_TYPE_BOOLEAN
        ];
        if (!in_array($column->getType(), $noLimits) && ($column->getLimit() || isset($sqlType['limit']))) {
            $buffer[] = sprintf('(%s)', $column->getLimit() ?: $sqlType['limit']);
        }
        */
        // TODO check isset or NULL
        if ($column->getLimit() !== null || isset($sqlType['limit'])) {
            $buffer[] = '(';
            $buffer[] = $column->getLimit() ?: $sqlType['limit'];
            if ($column->getScale() !== null || isset($sqlType['scale'])) {
                $buffer[] =  ',';
                $buffer[] = $column->getScale() ?: $sqlType['scale'];
            }
            $buffer[] = ')';
        }
        if ($column->isIdentity()) {
            $buffer[] = 'GENERATED BY DEFAULT ON NULL AS IDENTITY MINVALUE 1 MAXVALUE 999999999999999999999999 INCREMENT BY 1';
        } else {
            if ($column->getDefault() !== null) {
                $default = $this->getDefaultValueDefinition($column->getDefault(), $column->getType());
                if ($column->getDefaultOnNull()) {
                    $buffer[] = 'DEFAULT ON NULL ' . $default;
                } else {
                    $buffer[] = 'DEFAULT ' . $default . ' NOT NULL';
                }
            } elseif ($column->isNull()) {
                $buffer[] = 'NULL';
            } else {
                $buffer[] = 'NOT NULL';
            }
        }

        return implode(' ', $buffer);
    }

    /**
     * Gets the SQL Column Comment Definition for a column object.
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
            $this->quoteColumnName($column->getName())
        );
        if ($comment === '') {
            $sql .= "''";
        } else {
            $sql .= $comment;
        }

        return $sql;
    }

    /**
     * Gets the SQL Index Definition for an Index object.
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
            $this->quoteColumnName($this->getShortName($indexName)),
            $this->quoteSchemaTableName($tableName),
            implode(',', array_map([$this, 'quoteIndexName'], $index->getColumns()))
        );

        return $def;
    }

    /**
     * Gets the Oracle Foreign Key Definition for an ForeignKey object.
     *
     * @param \Phinx\Db\Table\ForeignKey $foreignKey
     * @param string     $tableName  Table name
     * @return string
     */
    protected function getForeignKeySqlDefinition(ForeignKey $foreignKey, $tableName)
    {
        $parts = $this->getSchemaName($tableName);

        $constraintName = $foreignKey->getConstraint() ?: ($parts['table'] . '_' . implode('_', $foreignKey->getColumns()) . '_FKEY');
        $def = ' CONSTRAINT ' . $this->quoteColumnName($this->getShortName($constraintName)) .
            ' FOREIGN KEY ("' . implode('", "', $this->upper ? array_map('strtoupper', $foreignKey->getColumns()) : $foreignKey->getColumns()) . '")' .
            " REFERENCES {$this->quoteSchemaTableName($foreignKey->getReferencedTable()->getName())} (\"" .
            implode('", "', $this->upper ? array_map('strtoupper', $foreignKey->getReferencedColumns()) : $foreignKey->getReferencedColumns()) . '")';
        if ($foreignKey->getOnDelete()) {
            $def .= " ON DELETE {$foreignKey->getOnDelete()}";
        }
        /*
        if ($foreignKey->getOnUpdate()) {
            $def .= " ON UPDATE {$foreignKey->getOnUpdate()}";
        }
        */
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
    }

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

    /**
     * {@inheritdoc}
     */
    public function isValidColumnType(Column $column)
    {
        // If not a standard column type, maybe it is array type?
        return (parent::isValidColumnType($column) || $this->isArrayType($column->getType()));
    }

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

    /**
     * Gets the schema table name.
     *
     * @return string
     */
    public function getSchemaTableName()
    {
        return $this->schemaTableName;
    }

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
            'schema' => $this->checkUpper($schema),
            'table' => $this->checkUpper($table),
        ];
    }

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

    /**
     * {@inheritdoc}
     */
    public function castToBool($value)
    {
        return (bool)$value ? 1 : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionLog()
    {
        $result = [];
        //TODO = Show names instead 0 and show dates to.
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
        $rows = $this->fetchAll(sprintf(
            'SELECT * FROM %s ORDER BY %s',
            $this->quoteSchemaTableName($this->getSchemaTableName()),
            $this->checkUpper($orderBy)
        ));
        foreach ($rows as $version) {
            $version = array_change_key_case($version,CASE_LOWER);
            $result[$version['version']] = $version;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(Table $table, $row)
    {
        $sql = sprintf(
            'INSERT INTO %s ',
            $this->quoteSchemaTableName($table->getName())
        );
        $columns = array_keys($row);
        $sql .= '(' . implode(', ', array_map([$this, 'quoteColumnName'], $columns)) . ')';

        foreach ($row as $column => $value) {
            if (is_bool($value)) {
                $row[$column] = $this->castToBool($value);
            }
        }
        $times = [];
        $columnDetails = $this->getColumns($table->getName());
        foreach ($columnDetails as $col) {
            if (in_array($col->getType(), ['timestamp', 'time', 'date', 'datetime'])) {
                $times[] = $col->getName();
            }
        }
        $schema = [];
        foreach ($row as $column => $value) {
            if (in_array(strtoupper($column), $times)) {
                if (strlen($value) === 10) {
                    $schema[] = 'date \''.$value.'\'';
                } else {
                    $schema[] = 'timestamp \''.$value.'\'';
                }
            } else {
                $schema[] = '\''.$value.'\'';
            }
        }
        //@TODO : dry run enabled
            //$sql .= ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $sql .= ' VALUES (' . implode(', ', $schema) . ')';
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function bulkinsert(Table $table, $rows) : void
    {
        foreach ($rows as $row) {
            $this->insert($table, $row);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function migrated(MigrationInterface $migration, $direction, $startTime, $endTime)
    {
        if (strcasecmp($direction, MigrationInterface::UP) === 0) {
            // up
            $sql = sprintf(
                "INSERT INTO %s (%s, %s, %s, %s, %s) VALUES
                ('%s', '%s', timestamp '%s', timestamp '%s', %s)",
                $this->quoteSchemaTableName($this->getSchemaTableName()),
                $this->quoteColumnName('version'),
                $this->quoteColumnName('migration_name'),
                $this->quoteColumnName('start_time'),
                $this->quoteColumnName('end_time'),
                $this->quoteColumnName('breakpoint'),
                $migration->getVersion(),
                substr($migration->getName(), 0, 100),
                $startTime,
                $endTime,
                $this->castToBool(false)
            );

            $this->execute($sql);
        } else {
            // down
            $sql = sprintf(
                "DELETE FROM %s WHERE %s = '%s'",
                $this->quoteSchemaTableName($this->getSchemaTableName()),
                $this->quoteColumnName('version'),
                $migration->getVersion()
            );

            $this->execute($sql);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPrimaryKey($tableName, $columns, $constraint = null)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKey($tableName, $columns, $constraint = null)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function getChangePrimaryKeyInstructions(Table $table, $newColumns)
    {
        // TODO
    }

    /**
     * {@inheritdoc}
     */
    public function getChangeCommentInstructions(Table $table, $newComment)
    {
        // TODO
    }
}
