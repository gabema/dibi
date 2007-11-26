<?php

/**
 * dibi - tiny'n'smart database abstraction layer
 * ----------------------------------------------
 *
 * Copyright (c) 2005, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the "dibi license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://php7.org/dibi/
 *
 * @copyright  Copyright (c) 2005, 2007 David Grudl
 * @license    http://php7.org/dibi/license  dibi license
 * @link       http://php7.org/dibi/
 * @package    dibi
 */


/**
 * The dibi driver for MS SQL database
 *
 * Connection options:
 *   - 'host' - the MS SQL server host name. It can also include a port number (hostname:port)
 *   - 'username' (or 'user')
 *   - 'password' (or 'pass')
 *   - 'persistent' - try to find a persistent link?
 *   - 'database' - the database name to select
 *   - 'lazy' - if TRUE, connection will be established only when required
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2005, 2007 David Grudl
 * @package    dibi
 * @version    $Revision$ $Date$
 */
class DibiMsSqlDriver extends NObject implements DibiDriverInterface
{

    /**
     * Connection resource
     * @var resource
     */
    private $connection;


    /**
     * Resultset resource
     * @var resource
     */
    private $resultset;



    /**
     * Connects to a database
     *
     * @return void
     * @throws DibiException
     */
    public function connect(array &$config)
    {
        DibiConnection::alias($config, 'username', 'user');
        DibiConnection::alias($config, 'password', 'pass');
        DibiConnection::alias($config, 'host');

        if (!extension_loaded('mssql')) {
            throw new DibiException("PHP extension 'mssql' is not loaded");
        }


        if (empty($config['persistent'])) {
            $this->connection = @mssql_connect($config['host'], $config['username'], $config['password'], TRUE);
        } else {
            $this->connection = @mssql_pconnect($config['host'], $config['username'], $config['password']);
        }

        if (!is_resource($this->connection)) {
            throw new DibiDriverException("Can't connect to DB");
        }

        if (isset($config['database']) && !@mssql_select_db($config['database'], $this->connection)) {
            throw new DibiDriverException("Can't select DB '$config[database]'");
        }
    }



    /**
     * Disconnects from a database
     *
     * @return void
     */
    public function disconnect()
    {
        mssql_close($this->connection);
    }



    /**
     * Executes the SQL query
     *
     * @param string       SQL statement.
     * @return bool        have resultset?
     * @throws DibiDriverException
     */
    public function query($sql)
    {
        $this->resultset = @mssql_query($sql, $this->connection);

        if ($this->resultset === FALSE) {
            throw new DibiDriverException('Query error', 0, $sql);
        }

        return is_resource($this->resultset);
    }



    /**
     * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query
     *
     * @return int|FALSE  number of rows or FALSE on error
     */
    public function affectedRows()
    {
        return mssql_rows_affected($this->connection);
    }



    /**
     * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query
     *
     * @return int|FALSE  int on success or FALSE on failure
     */
    public function insertId($sequence)
    {
        throw new BadMethodCallException(__METHOD__ . ' is not implemented');
    }



    /**
     * Begins a transaction (if supported).
     * @return void
     * @throws DibiDriverException
     */
    public function begin()
    {
        $this->query('BEGIN TRANSACTION');
    }



    /**
     * Commits statements in a transaction.
     * @return void
     * @throws DibiDriverException
     */
    public function commit()
    {
        $this->query('COMMIT');
    }



    /**
     * Rollback changes in a transaction.
     * @return void
     * @throws DibiDriverException
     */
    public function rollback()
    {
        $this->query('ROLLBACK');
    }



    /**
     * Format to SQL command
     *
     * @param string     value
     * @param string     type (dibi::FIELD_TEXT, dibi::FIELD_BOOL, dibi::FIELD_DATE, dibi::FIELD_DATETIME, dibi::IDENTIFIER)
     * @return string    formatted value
     * @throws InvalidArgumentException
     */
    public function format($value, $type)
    {
        if ($type === dibi::FIELD_TEXT) return "'" . str_replace("'", "''", $value) . "'";
        if ($type === dibi::IDENTIFIER) return '[' . str_replace('.', '].[', $value) . ']';
        if ($type === dibi::FIELD_BOOL) return $value ? -1 : 0;
        if ($type === dibi::FIELD_DATE) return date("'Y-m-d'", $value);
        if ($type === dibi::FIELD_DATETIME) return date("'Y-m-d H:i:s'", $value);
        throw new InvalidArgumentException('Unsupported formatting type');
    }



    /**
     * Injects LIMIT/OFFSET to the SQL query
     *
     * @param string &$sql  The SQL query that will be modified.
     * @param int $limit
     * @param int $offset
     * @return void
     */
    public function applyLimit(&$sql, $limit, $offset)
    {
        // offset suppot is missing...
        if ($limit >= 0) {
           $sql = 'SELECT TOP ' . (int) $limit . ' * FROM (' . $sql . ')';
        }

        if ($offset) {
            throw new InvalidArgumentException('Offset is not implemented');
        }
    }




    /**
     * Returns the number of rows in a result set
     *
     * @return int
     */
    public function rowCount()
    {
        return mssql_num_rows($this->resultset);
    }



    /**
     * Fetches the row at current position and moves the internal cursor to the next position
     * internal usage only
     *
     * @return array|FALSE  array on success, FALSE if no next record
     */
    public function fetch()
    {
        return mssql_fetch_assoc($this->resultset);
    }



    /**
     * Moves cursor position without fetching row
     *
     * @param  int      the 0-based cursor pos to seek to
     * @return boolean  TRUE on success, FALSE if unable to seek to specified record
     * @throws DibiException
     */
    public function seek($row)
    {
        return mssql_data_seek($this->resultset, $row);
    }



    /**
     * Frees the resources allocated for this result set
     *
     * @return void
     */
    public function free()
    {
        mssql_free_result($this->resultset);
        $this->resultset = NULL;
    }



    /** this is experimental */
    public function buildMeta()
    {
        static $types = array(
            'CHAR'      => dibi::FIELD_TEXT,
            'COUNTER'   => dibi::FIELD_COUNTER,
            'VARCHAR'   => dibi::FIELD_TEXT,
            'LONGCHAR'  => dibi::FIELD_TEXT,
            'INTEGER'   => dibi::FIELD_INTEGER,
            'DATETIME'  => dibi::FIELD_DATETIME,
            'CURRENCY'  => dibi::FIELD_FLOAT,
            'BIT'       => dibi::FIELD_BOOL,
            'LONGBINARY'=> dibi::FIELD_BINARY,
            'SMALLINT'  => dibi::FIELD_INTEGER,
            'BYTE'      => dibi::FIELD_INTEGER,
            'BIGINT'    => dibi::FIELD_INTEGER,
            'INT'       => dibi::FIELD_INTEGER,
            'TINYINT'   => dibi::FIELD_INTEGER,
            'REAL'      => dibi::FIELD_FLOAT,
            'DOUBLE'    => dibi::FIELD_FLOAT,
            'DECIMAL'   => dibi::FIELD_FLOAT,
            'NUMERIC'   => dibi::FIELD_FLOAT,
            'MONEY'     => dibi::FIELD_FLOAT,
            'SMALLMONEY'=> dibi::FIELD_FLOAT,
            'FLOAT'     => dibi::FIELD_FLOAT,
            'YESNO'     => dibi::FIELD_BOOL,
            // and many others?
        );

        $count = mssql_num_fields($this->resultset);
        $meta = array();
        for ($index = 0; $index < $count; $index++) {

            $tmp = mssql_fetch_field($this->resultset, $index);
            $type = strtoupper($tmp->type);
            $info['native'] = $tmp->type;
            $info['type'] = isset($types[$type]) ? $types[$type] : dibi::FIELD_UNKNOWN;
            $info['length'] = $tmp->max_length;
            $info['table'] = $tmp->column_source;

            $meta[$tmp->name] = $info;
        }
        return $meta;
    }



    /**
     * Returns the connection resource
     *
     * @return mixed
     */
    public function getResource()
    {
        return $this->connection;
    }



    /**
     * Returns the resultset resource
     *
     * @return mixed
     */
    public function getResultResource()
    {
        return $this->resultset;
    }



    /**
     * Gets a information of the current database.
     *
     * @return DibiReflection
     */
    function getDibiReflection()
    {}

}