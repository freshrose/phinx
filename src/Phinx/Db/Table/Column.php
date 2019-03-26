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
 * @subpackage Phinx\Db
 */
namespace Phinx\Db\Table;

use Phinx\Db\Adapter\AdapterInterface;

/**
 *
 * This object is based loosely on: http://api.rubyonrails.org/classes/ActiveRecord/ConnectionAdapters/Table.html.
 */
class Column
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|\Phinx\Util\Literal
     */
    protected $type;

    /**
     * @var integer
     */
    protected $limit = null;

    /**
     * @var boolean
     */
    protected $null = false;

    /**
     * @var mixed
     */
    protected $default = null;

    /**
     * @var boolean
     */
    protected $defaultOnNull = false;

    /**
     * @var boolean
     */
    protected $identity = false;

    /**
     * @var integer
     */
    protected $scale;

    /**
     * @var string
     */
    protected $after;

    /**
     * @var string
     */
    protected $update;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var boolean
     */
    protected $signed = true;

    /**
     * @var boolean
     */
    protected $timezone = false;

    /**
     * @var array
     */
    protected $properties = [];

    /**
     * @var string
     */
    protected $collation;

    /**
     * @var string
     */
    protected $encoding;

    /**
     * @var array
     */
    protected $values;

    /**
     * @var array
     */
    protected $newOptions = [];

    /**
     * Sets the column name.
     *
     * @param string $name
     * @return \Phinx\Db\Table\Column
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets the column name.
     *
     * @return string|null
     */
    public function getName()
    {
        if (isset($this->name)) {
            return $this->name;
        }

        return null;
    }

    /**
     * Sets the column type.
     *
     * @param string|\Phinx\Util\Literal $type Column type
     * @return \Phinx\Db\Table\Column
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the column type.
     *
     * @return string|\Phinx\Util\Literal|null
     */
    public function getType()
    {
        if (isset($this->type)) {
            return $this->type;
        }

        return null;
    }

    /**
     * Sets the column limit.
     *
     * @param int $limit
     * @return \Phinx\Db\Table\Column
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Gets the column limit.
     *
     * @return int|null
     */
    public function getLimit()
    {
        if (isset($this->limit)) {
            return $this->limit;
        }

        return null;
    }

    /**
     * Sets whether the column allows nulls.
     *
     * @param bool $null
     * @return \Phinx\Db\Table\Column
     */
    public function setNull($null)
    {
        $this->null = (bool)$null;

        return $this;
    }

    /**
     * Gets whether the column allows nulls.
     *
     * @return bool|null
     */
    public function getNull()
    {
        if (isset($this->null)) {
            return $this->null;
        }

        return null;
    }

    /**
     * Does the column allow nulls?
     *
     * @return bool
     */
    public function isNull()
    {
        return $this->getNull();
    }

    /**
     * Sets the default column value.
     *
     * @param mixed $default
     * @return \Phinx\Db\Table\Column
     */
    public function setDefault($default)
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Gets the default column value.
     *
     * @return mixed|null
     */
    public function getDefault()
    {
        if (isset($this->default)) {
            return $this->default;
        }

        return null;
    }

    /**
     * Sets the defaultOnNull 4 oracle column value.
     *
     * @param mixed $defaultOnNull
     * @return \Phinx\Db\Table\Column
     */
    public function setDefaultOnNull($defaultOnNull)
    {
        $this->defaultOnNull = $defaultOnNull;

        return $this;
    }

    /**
     * Gets the default column value.
     *
     * @return mixed|null
     */
    public function getDefaultOnNull()
    {
        if (isset($this->defaultOnNull)) {
            return $this->defaultOnNull;
        }

        return null;
    }

    /**
     * Sets whether or not the column is an identity column.
     *
     * @param bool $identity
     * @return \Phinx\Db\Table\Column
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;

        return $this;
    }

    /**
     * Gets whether or not the column is an identity column.
     *
     * @return bool|null
     */
    public function getIdentity()
    {
        if (isset($this->identity)) {
            return $this->identity;
        }

        return null;
    }

    /**
     * Is the column an identity column?
     *
     * @return bool
     */
    public function isIdentity()
    {
        return $this->getIdentity();
    }

    /**
     * Sets the name of the column to add this column after.
     *
     * @param string $after After
     * @return \Phinx\Db\Table\Column
     */
    public function setAfter($after)
    {
        $this->after = $after;

        return $this;
    }

    /**
     * Returns the name of the column to add this column after.
     *
     * @return string|null
     */
    public function getAfter()
    {
        if (isset($this->after)) {
            return $this->after;
        }

        return null;
    }

    /**
     * Sets the 'ON UPDATE' mysql column function.
     *
     * @param  string $update On Update function
     * @return \Phinx\Db\Table\Column
     */
    public function setUpdate($update)
    {
        $this->update = $update;

        return $this;
    }

    /**
     * Returns the value of the ON UPDATE column function.
     *
     * @return string|null
     */
    public function getUpdate()
    {
        if (isset($this->update)) {
            return $this->update;
        }

        return null;
    }

    /**
     * Sets the number precision for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int $precision Number precision
     * @return \Phinx\Db\Table\Column
     */
    public function setPrecision($precision)
    {
        $this->setLimit($precision);

        return $this;
    }

    /**
     * Gets the number precision for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @return int|null
     */
    public function getPrecision()
    {
        if (isset($this->limit)) {
            return $this->limit;
        }

        return null;
    }

    /**
     * Sets the number scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int $scale Number scale
     * @return \Phinx\Db\Table\Column
     */
    public function setScale($scale)
    {
        $this->scale = $scale;

        return $this;
    }

    /**
     * Gets the number scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @return int|null
     */
    public function getScale()
    {
        if (isset($this->scale)) {
            return $this->scale;
        }

        return null;
    }

    /**
     * Sets the number precision and scale for decimal or float column.
     *
     * For example `DECIMAL(5,2)`, 5 is the precision and 2 is the scale,
     * and the column could store value from -999.99 to 999.99.
     *
     * @param int $precision Number precision
     * @param int $scale Number scale
     * @return \Phinx\Db\Table\Column
     */
    public function setPrecisionAndScale($precision, $scale)
    {
        $this->setLimit($precision);
        $this->scale = $scale;

        return $this;
    }

    /**
     * Sets the column comment.
     *
     * @param string $comment
     * @return \Phinx\Db\Table\Column
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Gets the column comment.
     *
     * @return string|null
     */
    public function getComment()
    {
        if (isset($this->comment)) {
            return $this->comment;
        }

        return null;
    }

    /**
     * Sets whether field should be signed.
     *
     * @param bool $signed
     * @return \Phinx\Db\Table\Column
     */
    public function setSigned($signed)
    {
        $this->signed = (bool)$signed;

        return $this;
    }

    /**
     * Gets whether field should be signed.
     *
     * @return bool|null
     */
    public function getSigned()
    {
        if (isset($this->signed)) {
            return $this->signed;
        }

        return null;
    }

    /**
     * Should the column be signed?
     *
     * @return bool
     */
    public function isSigned()
    {
        return $this->getSigned();
    }

    /**
     * Sets whether the field should have a timezone identifier.
     * Used for date/time columns only!
     *
     * @param bool $timezone
     * @return \Phinx\Db\Table\Column
     */
    public function setTimezone($timezone)
    {
        $this->timezone = (bool)$timezone;

        return $this;
    }

    /**
     * Gets whether field has a timezone identifier.
     *
     * @return bool|null
     */
    public function getTimezone()
    {
        if (isset($this->timezone)) {
            return $this->timezone;
        }

        return null;
    }

    /**
     * Should the column have a timezone?
     *
     * @return bool
     */
    public function isTimezone()
    {
        return $this->getTimezone();
    }

    /**
     * Sets field properties.
     *
     * @param array $properties
     *
     * @return \Phinx\Db\Table\Column
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Gets field properties
     *
     * @return array|null
     */
    public function getProperties()
    {
        if (isset($this->properties)) {
            return $this->properties;
        }

        return null;
    }

    /**
     * Sets field values.
     *
     * @param array|string $values
     *
     * @return \Phinx\Db\Table\Column
     */
    public function setValues($values)
    {
        if (!is_array($values)) {
            $values = preg_split('/,\s*/', $values);
        }
        $this->values = $values;

        return $this;
    }

    /**
     * Gets field values
     *
     * @return array|null
     */
    public function getValues()
    {
        if (isset($this->values)) {
            return $this->values;
        }

        return null;
    }

    /**
     * Sets the column collation.
     *
     * @param string $collation
     *
     * @throws \UnexpectedValueException If collation not allowed for type
     * @return $this
     */
    public function setCollation($collation)
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($this->getType(), $allowedTypes)) {
            throw new \UnexpectedValueException('Collation may be set only for types: ' . implode(', ', $allowedTypes));
        }

        $this->collation = $collation;

        return $this;
    }

    /**
     * Gets the column collation.
     *
     * @return string|null
     */
    public function getCollation()
    {
        if (isset($this->collation)) {
            return $this->collation;
        }

        return null;
    }

    /**
     * Sets the column character set.
     *
     * @param string $encoding
     *
     * @throws \UnexpectedValueException If character set not allowed for type
     * @return $this
     */
    public function setEncoding($encoding)
    {
        $allowedTypes = [
            AdapterInterface::PHINX_TYPE_CHAR,
            AdapterInterface::PHINX_TYPE_STRING,
            AdapterInterface::PHINX_TYPE_TEXT,
        ];
        if (!in_array($this->getType(), $allowedTypes)) {
            throw new \UnexpectedValueException('Character set may be set only for types: ' . implode(', ', $allowedTypes));
        }

        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Gets the column character set.
     *
     * @return string|null
     */
    public function getEncoding()
    {
        if (isset($this->encoding)) {
            return $this->encoding;
        }

        return null;
    }

    /**
     * Gets all allowed options. Each option must have a corresponding `setFoo` method.
     *
     * @return array
     */
    protected function getValidOptions()
    {
        return [
            'limit',
            'default',
            'defaultOnNull',
            'null',
            'identity',
            'scale',
            'after',
            'update',
            'comment',
            'signed',
            'timezone',
            'properties',
            'values',
            'collation',
            'encoding',
        ];
    }

    /**
     * Gets all aliased options. Each alias must reference a valid option.
     *
     * @return array
     */
    protected function getAliasedOptions()
    {
        return [
            'length' => 'limit',
            'precision' => 'limit',
        ];
    }

    /**
     * Utility method that maps an array of column options to this objects methods.
     *
     * @param array $options Options
     * @return \Phinx\Db\Table\Column
     */
    public function setOptions($options)
    {
        $validOptions = $this->getValidOptions();
        $aliasOptions = $this->getAliasedOptions();

        foreach ($options as $option => $value) {
            if (isset($aliasOptions[$option])) {
                // proxy alias -> option
                $option = $aliasOptions[$option];
            }

            if (!in_array($option, $validOptions, true)) {
                throw new \RuntimeException(sprintf('"%s" is not a valid column option.', $option));
            }

            $method = 'set' . ucfirst($option);
            $this->$method($value);
        }

        return $this;
    }

    /*
     * @param mixed $columnName The name of the column to change
     * @param mixed $type The type of the column
     * @param mixed $options Additional options for the column
     */
    public function setNewOptions($columnName, $type, $options)
    {
        $options = array_merge([
            'name' => $columnName,
            'type' => $type
        ], $options);

        $this->newOptions = $options;
    }

    /**
     * Gets the options.
     *
     * @return array|null
     */
    public function getNewOptions()
    {
        if (isset($this->newOptions)) {
            return $this->newOptions;
        }

        return null;
    }

    public function unsetDefaultOptions()
    {
        $currentOptions = get_object_vars($this);
        $newOptions = $this->getNewOptions();
        $dropOptions = array_diff_ukey($currentOptions, $newOptions, function($cur, $new) {
            return intval($cur != $new);
        });
        foreach($dropOptions as $drop => $value) {
            unset($this->{$drop});
        }
    }
}
