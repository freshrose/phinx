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

    public function testQuoteSchemaName()
    {
        $this->assertEquals('"test_table"', $this->adapter->quoteSchemaName('test_table'));
    }

    public function testQuoteSchemaTableName()
    {
        $this->assertEquals('"test_schema"."test_table"', $this->adapter->quoteSchemaTableName('test_schema.test_table'));

        // @TODO : test / solution for tables without schemas
        // $this->assertEquals('"test_table"', $this->adapter->quoteSchemaTableName('test_table'));

        $options = $this->adapter->getOptions();
        $options['schema'] = 'test_default_schema';
        $this->adapter->setOptions($options);
        $this->assertEquals('"test_default_schema"."test_table"', $this->adapter->quoteSchemaTableName('test_table'));
    }

    public function testQuoteTableName()
    {
        $this->assertEquals('"test_table"', $this->adapter->quoteTableName('test_table'));
    }

    public function testHasTable()
    {
        $this->assertTrue($this->adapter->hasTable('phinxlog'));
    }

    public function testQuoteColumnName()
    {
        $this->assertEquals('"test_column"', $this->adapter->quoteColumnName('test_column'));
    }

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
}