 _______________________________
|                               |
|  +++ defaultOnNullSwitch +++  |
|_______________________________|

CONSTRUCTION:
	'defaultOnNull' => true  &&  'default' => NOT NULL ( && 'null' => false // this is by default )

EXAMPLE:
        $date = date('Y-m-d H:i:s');
        $table->addColumn('default_on_null_date', 'timestamp', ['null' => false, 'default' => $date, 'defaultOnNull' => true]);
   OR
        $table->addColumn('default_1', 'string', ['null' => false, 'default' => 'teststringer', 'defaultOnNull' => true]);

 _______________________________
|                               |
|  +++ setUpper / getUpper +++  |
|_______________________________|

CONSTRUCTION:
	$this->adapter->setUpper(false);
	$this->adapter->setUpper(true);
INSTRUCTIONS:
	If you want to use OracleAdapter with CaseSensitivity, set this to false.
	This FreshFlow version of OracleAdapter translates every object name to uppercase by default.
	