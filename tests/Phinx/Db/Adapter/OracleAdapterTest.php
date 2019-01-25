<?php
namespace Test\Phinx\Db\Adapter;

use Phinx\Db\Adapter\OracleAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class OracleAdapterTest extends TestCase
{
    /**
     * @var \Phinx\Db\Adapter\OracleAdapter
     */
    private $adapter;

    /**
     * Set up a new object
     *
     * @return null
     */
    public function setUp()
    {
        if (!TESTS_PHINX_DB_ADAPTER_ORACLE_ENABLED) {
            $this->markTestSkipped('Oracle tests disabled. See TESTS_PHINX_DB_ADAPTER_ORACLE_ENABLED constant.');
        }
        $options = [
            'host' => TESTS_PHINX_DB_ADAPTER_ORACLE_HOST,
            'user' => TESTS_PHINX_DB_ADAPTER_ORACLE_USERNAME,
            'pass' => TESTS_PHINX_DB_ADAPTER_ORACLE_PASSWORD,
            'port' => TESTS_PHINX_DB_ADAPTER_ORACLE_PORT,
            'sid' => TESTS_PHINX_DB_ADAPTER_ORACLE_SID
        ];
        $this->adapter = new OracleAdapter($options, new ArrayInput([]), new NullOutput());
        $this->adapter->getConnection();
        // leave the adapter in a disconnected state for each test
        $this->adapter->disconnect();
    }

    /**
     * Test if it is a valid object
     *
     * @return null
     */
    public function testObject()
    {
        $this->assertNotNull($this->adapter);
    }

    /**
     * Test if can connect
     *
     * @return null
     */
    public function testConnection()
    {
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
    }

    /**
     * Tear Down
     *
     * @return null
     */
    public function tearDown()
    {
        unset($this->adapter);
    }

    /**
     * Test if can connect without port
     *
     * @return null
     */
    public function testConnectionWithoutPort()
    {
        $options = $this->adapter->getOptions();
        unset($options['port']);
        $this->adapter->setOptions($options);
        $this->assertInstanceOf('PDO', $this->adapter->getConnection());
    }

    /**
     * Test if cannot connect without Invalid Credentials
     *
     * @return null
     */
    public function testConnectionWithInvalidCredentials()
    {
        $options = [
            'host' => TESTS_PHINX_DB_ADAPTER_ORACLE_HOST,
            'user' => 'invaliduser',
            'pass' => 'invalidpass',
            'port' => TESTS_PHINX_DB_ADAPTER_ORACLE_PORT,
            'sid' => TESTS_PHINX_DB_ADAPTER_ORACLE_SID
        ];
        try {
            $adapter = new OracleAdapter($options, new ArrayInput([]), new NullOutput());
            $adapter->getConnection();
            $this->fail('Expected the adapter to throw an exception');
            $adapter->disconnect();
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertRegExp('/There was a problem connecting to the database/', $e->getMessage());
        }
    }

    public function testGetUpper()
    {
        $this->assertTrue($this->adapter->getUpper());
    }

    public function testQuoteSchemaName()
    {
        $this->assertEquals(
            $this->adapter->getUpper() ? strtoupper('"test_table"') : '"test_table"',
            $this->adapter->quoteSchemaName('test_table')
        );
    }

    public function testQuoteSchemaTableName()
    {
        $this->assertEquals(
            $this->adapter->getUpper() ? strtoupper('"test_schema"."test_table"') : '"test_schema"."test_table"',
            $this->adapter->quoteSchemaTableName('test_schema.test_table')
        );

        $this->assertEquals($this->adapter->getUpper() ? strtoupper('"test_table"') : '"test_table"', $this->adapter->quoteSchemaTableName('.test_table'));

        $options = $this->adapter->getOptions();
        $options['schema'] = 'test_default_schema';
        $this->adapter->setOptions($options);
        $this->assertEquals($this->adapter->getUpper() ? strtoupper('"test_default_schema"."test_table"') : '"test_default_schema"."test_table"', $this->adapter->quoteSchemaTableName('test_table'));
    }

    public function testQuoteTableName()
    {
        $this->assertEquals($this->adapter->getUpper() ? strtoupper('"test_table"') : '"test_table"', $this->adapter->quoteTableName('test_table'));
    }

    public function testHasTable()
    {
        $this->assertTrue($this->adapter->hasTable('phinxlog'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals($this->adapter->getUpper() ? strtoupper('"test_column"') : '"test_column"', $this->adapter->quoteColumnName('test_column'));
    }
/*
    public function testCreatingTheSchemaTableOnConnect()
    {
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
        $this->assertFalse($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->disconnect();
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasTable($this->adapter->getSchemaTableName()));
        $this->adapter->dropTable($this->adapter->getSchemaTableName());
    }
*/
    public function testSchemaTableIsCreatedWithPrimaryKey()
    {
        $this->adapter->connect();
        $this->assertTrue($this->adapter->hasIndex($this->adapter->getSchemaTableName(), $this->adapter->getUpper() ? ['VERSION'] : ['version']));
        $this->adapter->disconnect();
    }

    public function testCreatingIdentityTableWithDefaultID()
    {
        // default UID is set to "UID" based on FreshFlow requirements
        $table = new \Phinx\Db\Table('identity_table_dtest', [], $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasTable('identity_table_dtest'));
        // assert hascolumn
        // drop table

        $this->adapter->dropTable('identity_table_dtest');
        $this->assertFalse($this->adapter->hasTable('identity_table_dtest'));
    }

    public function testCreatingIdentityTableWithCustomID()
    {
        $options = $this->adapter->getOptions();
        $options['id'] = 'UberID';
        $table = new \Phinx\Db\Table('identity_table_ctest', $options, $this->adapter);
        $table->addColumn('email', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasTable('identity_table_ctest'));
        // assert hascolumn
        // drop table

        $this->adapter->dropTable('identity_table_ctest');
        $this->assertFalse($this->adapter->hasTable('identity_table_ctest'));
    }

    public function testCreateTable()
    {
        $table = new \Phinx\Db\Table('NTABLE', [], $this->adapter);
        $table->addColumn('realname', 'string')
            ->addColumn('email', 'integer')
            ->save();
        $this->assertTrue($this->adapter->hasTable('NTABLE'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'UID'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'realname'));
        $this->assertTrue($this->adapter->hasColumn('NTABLE', 'email'));
        $this->assertFalse($this->adapter->hasColumn('NTABLE', 'address'));
        $this->adapter->dropTable('NTABLE');
    }

    public function testCreateTableLower()
    {
        $this->adapter->setUpper(false);
        $table = new \Phinx\Db\Table('t', [], $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault(0);
        $table->changeColumn('column1', $newColumn1)->save();
        $columns = $this->adapter->getColumns('t');
        $this->adapter->dropTable('t');
        $this->assertSame(0, (int)$columns['column1']->getDefault());
        $this->adapter->setUpper(true);
    }

    public function testTableWithoutIndexesByName()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->save();
        $this->assertFalse($this->adapter->hasIndexByName('TABLE1', strtoupper('MYEMAILINDEX')));
        $this->adapter->dropTable('TABLE1');
    }
    public function testRenameTable()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $this->assertTrue($this->adapter->hasTable('TABLE1'));
        $this->assertFalse($this->adapter->hasTable('TABLE2'));
        $this->adapter->renameTable('TABLE1', 'TABLE2');
        $this->assertFalse($this->adapter->hasTable('TABLE1'));
        $this->assertTrue($this->adapter->hasTable('TABLE2'));
        $this->adapter->dropTable('TABLE2');
    }
    public function testAddColumn()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $this->assertFalse($table->hasColumn('email'));
        $table->addColumn('email', 'string')
            ->save();
        $this->assertTrue($table->hasColumn('email'));
        $this->adapter->dropTable('TABLE1');
    }
    public function testAddColumnWithDefaultValue()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_value', 'string', ['default' => 'test'])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == ($this->adapter->getUpper() ? strtoupper('default_value') : 'default_value')) {
                $this->assertEquals("'test'", trim($column->getDefault()));
            }
        }
        $this->adapter->dropTable('TABLE1');
    }
    public function testAddColumnWithDefaultZero()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_zero', 'integer', ['default' => 0])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == ($this->adapter->getUpper() ? strtoupper('default_zero') : 'default_zero')) {
                $this->assertNotNull($column->getDefault());
                $this->assertEquals('0', trim($column->getDefault()));
            }
        }
        $this->adapter->dropTable('TABLE1');
    }
    public function testAddColumnWithDefaultNull()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table->addColumn('default_null', 'string', ['null' => true, 'default' => null])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == ($this->adapter->getUpper() ? strtoupper('default_null') : 'default_null')) {
                $this->assertEquals('', trim($column->getDefault()));
            }
        }
        $this->adapter->dropTable('TABLE1');
    }
    public function testAddColumnWithDefaultOnNull()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('default_on_null', 'string', ['null' => false, 'default' => 'test', 'defaultOnNull' => true])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == ($this->adapter->getUpper() ? strtoupper('default_on_null') : 'default_on_null')) {
                $this->assertNotNull($column->getDefault());
            }
        }
        $date = date('Y-m-d H:i:s');
        $table->addColumn('default_on_null_date', 'timestamp', ['null' => false, 'default' => $date, 'defaultOnNull' => true]);
        $table->addColumn('default_1', 'string', ['null' => false, 'default' => 'teststringer', 'defaultOnNull' => true]);
        $data = [
            [
                'default_1' => 'stringer'
            ]
        ];
        $table->save();
        $table->insert($data);
        $table->saveData();
        $row = $this->adapter->fetchRow("SELECT default_on_null,
                                                to_char(default_on_null_date,'YYYY-MM-DD HH24:MI:SS') default_on_null_date
                                         FROM table1");
        $this->assertSame('test', $this->adapter->getUpper() ? $row['DEFAULT_ON_NULL'] : $row['default_on_null']);
        $this->assertSame($date, $this->adapter->getUpper() ? $row['DEFAULT_ON_NULL_DATE'] : $row['default_on_null_date']);
        $this->adapter->dropTable('TABLE1');
    }

    public function testAddColumnWithDefaultBool()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->save();
        $table
            ->addColumn('default_false', 'integer', ['default' => false])
            ->addColumn('default_true', 'integer', ['default' => true])
            ->save();
        $columns = $this->adapter->getColumns('TABLE1');
        foreach ($columns as $column) {
            if ($column->getName() == ($this->adapter->getUpper() ? strtoupper('default_false') : 'default_false')) {
                $this->assertSame(0, (int)trim($column->getDefault()));
            }
            if ($column->getName() == ($this->adapter->getUpper() ? strtoupper('default_true') : 'default_true')) {
                $this->assertSame(1, (int)trim($column->getDefault()));
            }
        }
        $this->adapter->dropTable('TABLE1');
    }
    public function testRenameColumn()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $this->assertFalse($this->adapter->hasColumn('T', 'column2'));
        $this->adapter->renameColumn('T', 'column1', 'column2');
        $this->assertFalse($this->adapter->hasColumn('T', 'column1'));
        $this->assertTrue($this->adapter->hasColumn('T', 'column2'));
        $this->adapter->dropTable('T');
    }
    public function testRenamingANonExistentColumn()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        try {
            $this->adapter->renameColumn('T', 'column2', 'column1');
            $this->fail('Expected the adapter to throw an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertInstanceOf(
                'InvalidArgumentException',
                $e,
                'Expected exception of type InvalidArgumentException, got ' . get_class($e)
            );
            $this->assertEquals('The specified column does not exist: column2', $e->getMessage());
        }
        $this->adapter->dropTable('T');
    }
    public function testChangeColumnDefaults()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->save();
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $columns = $this->adapter->getColumns('T');
        $this->assertSame("'test'", trim($this->adapter->getUpper() ? $columns['COLUMN1']->getDefault() : $columns['column1']->getDefault()));
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault('another test');
        $table->changeColumn('column1', $newColumn1)->save();
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $columns = $this->adapter->getColumns('T');
        $this->assertSame("'another test'", trim($this->adapter->getUpper() ? $columns['COLUMN1']->getDefault() : $columns['column1']->getDefault()));
        $this->adapter->dropTable('T');
    }
    public function testChangeColumnDefaultToNull()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string', ['null' => false, 'default' => 'test'])
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setNull(true)
            ->setDefault(null);
        $table->changeColumn('column1', $newColumn1)->save();
        $columns = $this->adapter->getColumns('T');
        $this->adapter->dropTable('T');
        $this->assertNull($this->adapter->getUpper() ? $columns['COLUMN1']->getDefault() : $columns['column1']->getDefault());
    }
    public function testChangeColumnDefaultToZero()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'integer')
            ->save();
        $newColumn1 = new \Phinx\Db\Table\Column();
        $newColumn1
            ->setType('string')
            ->setDefault(0);
        $table->changeColumn('column1', $newColumn1)->save();
        $columns = $this->adapter->getColumns('T');
        $this->adapter->dropTable('T');
        $this->assertSame(0, $this->adapter->getUpper() ? (int)$columns['COLUMN1']->getDefault() : (int)$columns['column1']->getDefault());
    }
    public function testDropColumn()
    {
        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn('column1', 'string')
            ->save();
        $this->assertTrue($this->adapter->hasColumn('T', 'column1'));
        $this->adapter->dropColumn('T', 'column1');
        $this->adapter->dropTable('T');
        $this->assertFalse($this->adapter->hasColumn('T', 'column1'));
    }

    public function columnsProvider()
    {
        return [
            ['column1', 'string', ['null' => true, 'default' => null]],
            ['column2', 'integer', ['default' => 0]],
            ['column3', 'biginteger', ['default' => 5]],
            ['column4', 'text', ['default' => 'text']],
            ['column5', 'float', []],
            ['column6', 'decimal', []],
            ['column7', 'date', []],
            ['column9', 'timestamp', []],
            ['column11', 'blob', []],
            ['column12', 'boolean', []],
            ['column13', 'string', ['limit' => 10]],
        ];
    }

    public function returnTypes()
    {
    }

    /**
     * @dataProvider columnsProvider
     */
    public function testGetColumns($colName, $type, array $options)
    {
        $colName = $this->adapter->getUpper() ? strtoupper($colName) : $colName;

        $table = new \Phinx\Db\Table('T', [], $this->adapter);
        $table->addColumn($colName, $type, $options)->save();

        $columns = $this->adapter->getColumns('T');
        $this->assertEquals($colName, $columns[$colName]->getName());
        $this->assertEquals($type, $columns[$colName]->getType());

        $this->assertNull($this->adapter->getUpper() ? $columns['COLUMN1']->getDefault() : $columns['column1']->getDefault());
    }
    public function testAddIndex()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('EMAIL'));
        $table->addIndex('EMAIL')
            ->save();
        $this->assertTrue($table->hasIndex('EMAIL'));
        $this->adapter->dropTable('TABLE1');
    }
    public function testGetIndexes()
    {
        // single column index
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addColumn('USERNAME', 'string')
            ->save();
        $this->assertFalse($table->hasIndex('TABLE1_EMAIL'));
        $this->assertFalse($table->hasIndex(['EMAIL', 'USERNAME']));
        $table->addIndex('EMAIL')
            ->addIndex(['EMAIL', 'USERNAME'], ['unique' => true, 'name' => 'EMAIL_USERNAME'])
            ->save();
        $this->assertTrue($table->hasIndex('EMAIL'));
        $this->assertTrue($table->hasIndex(['EMAIL', 'USERNAME']));
        $this->adapter->dropTable('TABLE1');
    }
    public function testDropIndex()
    {
        // single column index
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL')
            ->save();
        $this->assertTrue($table->hasIndex('EMAIL'));
        $this->adapter->dropIndex($table->getName(), 'EMAIL');
        $this->assertFalse($table->hasIndex('EMAIL'));
        // multiple column index
        $TABLE2 = new \Phinx\Db\Table('TABLE2', [], $this->adapter);
        $TABLE2->addColumn('FNAME', 'string')
            ->addColumn('LNAME', 'string')
            ->addIndex(['FNAME', 'LNAME'])
            ->save();
        $this->assertTrue($TABLE2->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropIndex($TABLE2->getName(), ['FNAME', 'LNAME']);
        $this->assertFalse($TABLE2->hasIndex(['FNAME', 'LNAME']));
        // index with name specified, but dropping it by column name
        $table3 = new \Phinx\Db\Table('TABLE3', [], $this->adapter);
        $table3->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL', ['name' => 'SOMEINDEXNAME'])
            ->save();
        $this->assertTrue($table3->hasIndex('EMAIL'));
        $this->adapter->dropIndex($table3->getName(), 'EMAIL');
        $this->assertFalse($table3->hasIndex('EMAIL'));
        // multiple column index with name specified
        $table4 = new \Phinx\Db\Table('TABLE4', [], $this->adapter);
        $table4->addColumn('FNAME', 'string')
            ->addColumn('LNAME', 'string')
            ->addIndex(['FNAME', 'LNAME'], ['name' => 'multiname'])
            ->save();
        $this->assertTrue($table4->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropIndex($table4->getName(), ['FNAME', 'LNAME']);
        $this->assertFalse($table4->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropTable('TABLE1');
        $this->adapter->dropTable('TABLE2');
        $this->adapter->dropTable('TABLE3');
        $this->adapter->dropTable('TABLE4');
    }
    public function testDropIndexByName()
    {
        // single column index
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('EMAIL', 'string')
            ->addIndex('EMAIL', ['name' => 'MYEMAILINDEX'])
            ->save();
        $this->assertTrue($table->hasIndex('EMAIL'));
        $this->adapter->dropIndexByName($table->getName(), 'MYEMAILINDEX');
        $this->assertFalse($table->hasIndex('EMAIL'));
        // multiple column index
        $TABLE2 = new \Phinx\Db\Table('TABLE2', [], $this->adapter);
        $TABLE2->addColumn('FNAME', 'string')
            ->addColumn('LNAME', 'string')
            ->addIndex(
                ['FNAME', 'LNAME'],
                ['name' => 'TWOCOLUMNINIQUEINDEX', 'unique' => true]
            )
            ->save();
        $this->assertTrue($TABLE2->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropIndexByName($TABLE2->getName(), 'TWOCOLUMNINIQUEINDEX');
        $this->assertFalse($TABLE2->hasIndex(['FNAME', 'LNAME']));
        $this->adapter->dropTable('TABLE1');
        $this->adapter->dropTable('TABLE2');
    }

    public function testAddForeignKey1()
    {
        $table = new \Phinx\Db\Table('f_events', [], $this->adapter);

        if (!$table->hasForeignKey('source_event_id')) {
            $table->addForeignKeyWithName(
                'fk_source_event',
                'source_event_id',
                'f_events',
                'uid',
                [
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE'
                ]
            );
        }

        $table->save();
    }

    public function testAddForeignKey2()
    {
        $table =  new \Phinx\Db\Table('f_events', [], $this->adapter);

        if (!$table->hasForeignKey('source_task_id')) {
            $table->addForeignKeyWithName(
                'fk_source_task',
                'source_task_id',
                'f_tasks',
                'uid',
                [
                    'delete' => 'CASCADE',
                    'update' => 'CASCADE'
                ]
            );
        }

        $table->save();
    }

    public function testAddForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['UID'])
            ->save();
        $this->assertTrue($this->adapter->hasForeignKey($table->getName(), $this->adapter->getUpper() ?  ['REF_TABLE_ID'] : ['ref_table_id']));
        $this->adapter->dropTable('table');
        $this->adapter->dropTable('ref_table');
    }
    public function testDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['UID'])
            ->save();
        $table->dropForeignKey($this->adapter->getUpper() ?  ['REF_TABLE_ID'] : ['ref_table_id'])->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), $this->adapter->getUpper() ?  ['REF_TABLE_ID'] : ['ref_table_id']));
        $this->adapter->dropTable('table');
        $this->adapter->dropTable('ref_table');
    }
    public function testStringDropForeignKey()
    {
        $refTable = new \Phinx\Db\Table('ref_table', [], $this->adapter);
        $refTable->addColumn('field1', 'string')->save();

        $table = new \Phinx\Db\Table('table', [], $this->adapter);
        $table
            ->addColumn('ref_table_id', 'integer')
            ->addForeignKey(['ref_table_id'], 'ref_table', ['UID'])
            ->save();
        $table->dropForeignKey('ref_table_id')->save();
        $this->assertFalse($this->adapter->hasForeignKey($table->getName(), ['ref_table_id']));
        $this->adapter->dropTable('table');
        $this->adapter->dropTable('ref_table');
    }
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage The type: "idontexist" is not supported
     */
    public function testInvalidSqlType()
    {
        $this->adapter->getSqlType('idontexist');
    }
    public function testGetSqlType()
    {
        $this->assertEquals(['name' => 'CHAR', 'limit' => 255], $this->adapter->getSqlType('char'));
        $this->assertEquals(['name' => 'TIMESTAMP', 'limit' => 6], $this->adapter->getSqlType('time'));
        $this->assertEquals(['name' => 'BLOB'], $this->adapter->getSqlType('blob'));
        $this->assertEquals(
            [
                'name' => 'RAW',
                'precision' => 16,
                'scale' => 0
            ],
            $this->adapter->getSqlType('uuid')
        );
    }
    public function testGetPhinxType()
    {
        $this->assertEquals('integer', $this->adapter->getPhinxType('NUMBER', 11));
        $this->assertEquals('biginteger', $this->adapter->getPhinxType('NUMBER', 20));
        $this->assertEquals('decimal', $this->adapter->getPhinxType('NUMBER', 18));
        $this->assertEquals('float', $this->adapter->getPhinxType('NUMBER'));
        $this->assertEquals('boolean', $this->adapter->getPhinxType('NUMBER', 5));
        $this->assertEquals('string', $this->adapter->getPhinxType('VARCHAR2'));
        $this->assertEquals('char', $this->adapter->getPhinxType('CHAR'));
        $this->assertEquals('text', $this->adapter->getPhinxType('LONG'));
        $this->assertEquals('timestamp', $this->adapter->getPhinxType('TIMESTAMP(6)'));
        $this->assertEquals('date', $this->adapter->getPhinxType('DATE'));
        $this->assertEquals('blob', $this->adapter->getPhinxType('BLOB'));
    }
    public function testAddColumnComment()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => $comment = 'Comments from column "field1"'])
            ->save();
        $resultComment = $this->adapter->getColumnComment('TABLE1', $this->adapter->getUpper() ? 'FIELD1' : 'field1');
        $this->adapter->dropTable('TABLE1');
        $this->assertEquals($comment, $resultComment, 'Dont set column comment correctly');
    }
    /**
     * @depends testAddColumnComment
     */
    public function testGetColumnCommentEmptyReturn()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => ''])
            ->save();
        $resultComment = $this->adapter->getColumnComment('TABLE1', $this->adapter->getUpper() ? 'FIELD1' : 'field1');
        $this->adapter->dropTable('TABLE1');
        $this->assertEquals('', $resultComment, '');
    }
    /**
     * @depends testAddColumnComment
     */
    public function testChangeColumnComment()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
            ->save();
        $table->changeColumn('field1', 'string', ['comment' => $comment = 'New Comments from column "field1"'])
            ->save();
        $resultComment = $this->adapter->getColumnComment('TABLE1', $this->adapter->getUpper() ? 'FIELD1' : 'field1');
        $this->adapter->dropTable('TABLE1');
        $this->assertEquals($comment, $resultComment, 'Dont change column comment correctly');
    }
    /**
     * @depends testAddColumnComment
     */
    public function testRemoveColumnComment()
    {
        $table = new \Phinx\Db\Table('TABLE1', [], $this->adapter);
        $table->addColumn('field1', 'string', ['comment' => 'Comments from column "field1"'])
            ->save();
        $table->changeColumn('field1', 'string', ['comment' => ''])
            ->save();
        $resultComment = $this->adapter->getColumnComment('TABLE1', $this->adapter->getUpper() ? 'FIELD1' : 'field1');
        $this->adapter->dropTable('TABLE1');
        $this->assertEmpty($resultComment, 'Dont remove column comment correctly');
    }
    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testForignKeysArePropertlyEscaped()
    {
        $userId = 'USER123';
        $sessionId = 'SESSION123';
        $local = new \Phinx\Db\Table('USERS', ['primary_key' => $userId, 'id' => $userId], $this->adapter);
        $local->create();
        $foreign = new \Phinx\Db\Table(
            'SESSIONS123',
            ['primary_key' => $sessionId, 'id' => $sessionId],
            $this->adapter
        );
        $foreign->addColumn('USER123', 'integer')
            ->addForeignKey('USER123', 'USERS', $userId, ['constraint' => 'USER_SESSION_ID'])
            ->create();
        $this->assertTrue($foreign->hasForeignKey('USER123'));
        $this->adapter->dropTable('SESSIONS123');
        $this->adapter->dropTable('USERS');
    }
    /**
     * Test that column names are properly escaped when creating Foreign Keys
     */
    public function testDontHasForeignKey()
    {
        $userId = 'USER123';
        $sessionId = 'SESSION123';
        $local = new \Phinx\Db\Table('USERS', ['primary_key' => $userId, 'id' => $userId], $this->adapter);
        $local->create();
        $foreign = new \Phinx\Db\Table(
            'SESSIONS123',
            ['primary_key' => $sessionId, 'id' => $sessionId],
            $this->adapter
        );
        $foreign->addColumn('USER123', 'integer')
            ->addForeignKey('USER123', 'USERS', $userId, ['constraint' => 'USER_SESSION_ID'])
            ->create();
        $this->assertFalse($foreign->hasForeignKey('USER123', 'a'));
        $this->adapter->dropTable('SESSIONS123');
        $this->adapter->dropTable('USERS');
    }

    public function testBulkInsertData()
    {
        $data = [
            [
                'column1' => 'crievko',
                'column2' => 6,
                'column3' => '2018-06-06',
                'column4' => '2018-06-06 15:45:45',
            ],
            [
                'column1' => 'bambulka',
                'column2' => 7,
                'column3' => '2018-07-06',
                'column4' => '2018-07-06 15:45:45',
            ],
            [
                'column1' => 'stromcek',
                'column2' => 8,
                'column3' => '2018-08-06',
                'column4' => '2018-08-06 15:45:45',
            ]
        ];
        $table = new \Phinx\Db\Table('table1', [], $this->adapter);
        $table->addColumn('column1', 'string', ['default' => 'test'])
            ->addColumn('column2', 'integer', ['null' => false , 'default' => 5])
            ->addColumn('column3', 'date', ['default' => '2018-05-05'])
            ->addColumn('column4', 'timestamp', ['default' => '2018-05-05 15:23:00'])
            ->insert($data)
            ->save();
/*
        $rows = $this->adapter->fetchAll('SELECT * FROM table1');
        $this->assertEquals('value1', $rows[0]['column1']);
        $this->assertEquals('value2', $rows[1]['column1']);
        $this->assertEquals('value3', $rows[2]['column1']);
        $this->assertEquals(1, $rows[0]['column2']);
        $this->assertEquals(2, $rows[1]['column2']);
        $this->assertEquals(3, $rows[2]['column2']);
        $this->assertEquals('test', $rows[0]['column3']);
        $this->assertEquals('test', $rows[2]['column3']);
*/
    }
}
