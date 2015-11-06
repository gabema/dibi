<?php

/**
 * This file is part of the "dibi" - smart database abstraction layer.
 * Copyright (c) 2005 David Grudl (https://davidgrudl.com)
 */

namespace Dibi\Drivers;

use Dibi;
use Dibi\Connection;
use Dibi\Helpers;


/**
 * The dibi driver for Microsoft SQL Server and SQL Azure databases.
 *
 * Driver options:
 *   - host => the MS SQL server host name. It can also include a port number (hostname:port)
 *   - username (or user)
 *   - password (or pass)
 *   - database => the database name to select
 *   - options (array) => connection options {@link https://msdn.microsoft.com/en-us/library/cc296161(SQL.90).aspx}
 *   - charset => character encoding to set (default is UTF-8)
 *   - resource (resource) => existing connection resource
 *   - lazy, profiler, result, substitutes, ... => see Dibi\Connection options
 */
class SqlsrvDriver implements Dibi\Driver, Dibi\ResultDriver
{
	use Dibi\Strict;

	/** @var resource  Connection resource */
	private $connection;

	/** @var resource  Resultset resource */
	private $resultSet;

	/** @var bool */
	private $autoFree = TRUE;

	/** @var int|FALSE  Affected rows */
	private $affectedRows = FALSE;

	/** @var string */
	private $version;


	/**
	 * @throws Dibi\NotSupportedException
	 */
	public function __construct()
	{
		if (!extension_loaded('sqlsrv')) {
			throw new Dibi\NotSupportedException("PHP extension 'sqlsrv' is not loaded.");
		}
	}


	/**
	 * Connects to a database.
	 * @return void
	 * @throws Dibi\Exception
	 */
	public function connect(array & $config)
	{
		Helpers::alias($config, 'options|UID', 'username');
		Helpers::alias($config, 'options|PWD', 'password');
		Helpers::alias($config, 'options|Database', 'database');
		Helpers::alias($config, 'options|CharacterSet', 'charset');

		if (isset($config['resource'])) {
			$this->connection = $config['resource'];

		} else {
			// Default values
			if (!isset($config['options']['CharacterSet'])) {
				$config['options']['CharacterSet'] = 'UTF-8';
			}

			$this->connection = sqlsrv_connect($config['host'], (array) $config['options']);
		}

		if (!is_resource($this->connection)) {
			$info = sqlsrv_errors();
			throw new Dibi\DriverException($info[0]['message'], $info[0]['code']);
		}
		$this->version = sqlsrv_server_info($this->connection)['SQLServerVersion'];
	}


	/**
	 * Disconnects from a database.
	 * @return void
	 */
	public function disconnect()
	{
		sqlsrv_close($this->connection);
	}


	/**
	 * Executes the SQL query.
	 * @param  string      SQL statement.
	 * @return Dibi\ResultDriver|NULL
	 * @throws Dibi\DriverException
	 */
	public function query($sql)
	{
		$this->affectedRows = FALSE;
		$res = sqlsrv_query($this->connection, $sql);

		if ($res === FALSE) {
			$info = sqlsrv_errors();
			throw new Dibi\DriverException($info[0]['message'], $info[0]['code'], $sql);

		} elseif (is_resource($res)) {
			$this->affectedRows = sqlsrv_rows_affected($res);
			return $this->createResultDriver($res);
		}
	}


	/**
	 * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
	 * @return int|FALSE  number of rows or FALSE on error
	 */
	public function getAffectedRows()
	{
		return $this->affectedRows;
	}


	/**
	 * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
	 * @return int|FALSE  int on success or FALSE on failure
	 */
	public function getInsertId($sequence)
	{
		$res = sqlsrv_query($this->connection, 'SELECT @@IDENTITY');
		if (is_resource($res)) {
			$row = sqlsrv_fetch_array($res, SQLSRV_FETCH_NUMERIC);
			return $row[0];
		}
		return FALSE;
	}


	/**
	 * Begins a transaction (if supported).
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws Dibi\DriverException
	 */
	public function begin($savepoint = NULL)
	{
		sqlsrv_begin_transaction($this->connection);
	}


	/**
	 * Commits statements in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws Dibi\DriverException
	 */
	public function commit($savepoint = NULL)
	{
		sqlsrv_commit($this->connection);
	}


	/**
	 * Rollback changes in a transaction.
	 * @param  string  optional savepoint name
	 * @return void
	 * @throws Dibi\DriverException
	 */
	public function rollback($savepoint = NULL)
	{
		sqlsrv_rollback($this->connection);
	}


	/**
	 * Returns the connection resource.
	 * @return mixed
	 */
	public function getResource()
	{
		return is_resource($this->connection) ? $this->connection : NULL;
	}


	/**
	 * Returns the connection reflector.
	 * @return Dibi\Reflector
	 */
	public function getReflector()
	{
		return new SqlsrvReflector($this);
	}


	/**
	 * Result set driver factory.
	 * @param  resource
	 * @return Dibi\ResultDriver
	 */
	public function createResultDriver($resource)
	{
		$res = clone $this;
		$res->resultSet = $resource;
		return $res;
	}


	/********************* SQL ****************d*g**/


	/**
	 * Encodes data for use in a SQL statement.
	 * @param  mixed     value
	 * @return string    encoded value
	 */
	public function escapeText($value)
	{
		return "'" . str_replace("'", "''", $value) . "'";
	}


	public function escapeBinary($value)
	{
		return "'" . str_replace("'", "''", $value) . "'";
	}


	public function escapeIdentifier($value)
	{
		// @see https://msdn.microsoft.com/en-us/library/ms176027.aspx
		return '[' . str_replace(']', ']]', $value) . ']';
	}


	public function escapeBool($value)
	{
		return $value ? 1 : 0;
	}


	public function escapeDate($value)
	{
		if (!$value instanceof \DateTime && !$value instanceof \DateTimeInterface) {
			$value = new Dibi\DateTime($value);
		}
		return $value->format("'Y-m-d'");
	}


	public function escapeDateTime($value)
	{
		if (!$value instanceof \DateTime && !$value instanceof \DateTimeInterface) {
			$value = new Dibi\DateTime($value);
		}
		return $value->format("'Y-m-d H:i:s'");
	}


	/**
	 * Encodes string for use in a LIKE statement.
	 * @param  string
	 * @param  int
	 * @return string
	 */
	public function escapeLike($value, $pos)
	{
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}


	/**
	 * Decodes data from result set.
	 * @param  string
	 * @return string
	 */
	public function unescapeBinary($value)
	{
		return $value;
	}


	/** @deprecated */
	public function escape($value, $type)
	{
		trigger_error(__METHOD__ . '() is deprecated.', E_USER_DEPRECATED);
		return Helpers::escape($this, $value, $type);
	}


	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 * @return void
	 */
	public function applyLimit(& $sql, $limit, $offset)
	{
		if ($limit < 0 || $offset < 0) {
			throw new Dibi\NotSupportedException('Negative offset or limit.');

		} elseif (version_compare($this->version, 11, '<')) { // 11 == SQL Server 2012
			if ($offset) {
				throw new Dibi\NotSupportedException('Offset is not supported by this database.');

			} elseif ($limit !== NULL) {
				$sql = 'SELECT TOP ' . (int) $limit . ' * FROM (' . $sql . ') t';
			}

		} elseif ($limit !== NULL || $offset) {
			// requires ORDER BY, see https://technet.microsoft.com/en-us/library/gg699618(v=sql.110).aspx
			$sql .= ' OFFSET ' . (int) $offset . ' ROWS '
				. 'FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
		}
	}


	/********************* result set ****************d*g**/


	/**
	 * Automatically frees the resources allocated for this result set.
	 * @return void
	 */
	public function __destruct()
	{
		$this->autoFree && $this->getResultResource() && $this->free();
	}


	/**
	 * Returns the number of rows in a result set.
	 * @return int
	 */
	public function getRowCount()
	{
		throw new Dibi\NotSupportedException('Row count is not available for unbuffered queries.');
	}


	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 * @param  bool     TRUE for associative array, FALSE for numeric
	 * @return array    array on success, nonarray if no next record
	 */
	public function fetch($assoc)
	{
		return sqlsrv_fetch_array($this->resultSet, $assoc ? SQLSRV_FETCH_ASSOC : SQLSRV_FETCH_NUMERIC);
	}


	/**
	 * Moves cursor position without fetching row.
	 * @param  int   the 0-based cursor pos to seek to
	 * @return bool  TRUE on success, FALSE if unable to seek to specified record
	 */
	public function seek($row)
	{
		throw new Dibi\NotSupportedException('Cannot seek an unbuffered result set.');
	}


	/**
	 * Frees the resources allocated for this result set.
	 * @return void
	 */
	public function free()
	{
		sqlsrv_free_stmt($this->resultSet);
		$this->resultSet = NULL;
	}


	/**
	 * Returns metadata for all columns in a result set.
	 * @return array
	 */
	public function getResultColumns()
	{
		$columns = [];
		foreach ((array) sqlsrv_field_metadata($this->resultSet) as $fieldMetadata) {
			$columns[] = [
				'name' => $fieldMetadata['Name'],
				'fullname' => $fieldMetadata['Name'],
				'nativetype' => $fieldMetadata['Type'],
			];
		}
		return $columns;
	}


	/**
	 * Returns the result set resource.
	 * @return mixed
	 */
	public function getResultResource()
	{
		$this->autoFree = FALSE;
		return is_resource($this->resultSet) ? $this->resultSet : NULL;
	}

}
