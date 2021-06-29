<?php

/**
 * This file is part of the Dibi, smart database abstraction layer (https://dibiphp.com)
 * Copyright (c) 2005 David Grudl (https://davidgrudl.com).
 */

declare(strict_types=1);

namespace Dibi;

/**
 * Provides an interface between a dataset and data-aware components.
 */
interface IDataSource extends \Countable, \IteratorAggregate {
    //function \IteratorAggregate::getIterator();
    //function \Countable::count();
}

/**
 * Driver interface.
 */
interface Driver {
    /**
     * Disconnects from a database.
     *
     * @throws Exception
     */
    public function disconnect(): void;

    /**
     * Internal: Executes the SQL query.
     *
     * @throws DriverException
     */
    public function query(string $sql): ?ResultDriver;

    /**
     * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
     */
    public function getAffectedRows(): ?int;

    /**
     * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
     */
    public function getInsertId(?string $sequence): ?int;

    /**
     * Begins a transaction (if supported).
     *
     * @throws DriverException
     */
    public function begin(string $savepoint = null): void;

    /**
     * Commits statements in a transaction.
     *
     * @throws DriverException
     */
    public function commit(string $savepoint = null): void;

    /**
     * Rollback changes in a transaction.
     *
     * @throws DriverException
     */
    public function rollback(string $savepoint = null): void;

    /**
     * Returns the connection resource.
     *
     * @return mixed
     */
    public function getResource();

    /**
     * Returns the connection reflector.
     */
    public function getReflector(): Reflector;

    /**
     * Encodes data for use in a SQL statement.
     */
    public function escapeText(string $value): string;

    public function escapeBinary(string $value): string;

    public function escapeIdentifier(string $value): string;

    public function escapeBool(bool $value): string;

    public function escapeDate(\DateTimeInterface $value): string;

    public function escapeDateTime(\DateTimeInterface $value): string;

    public function escapeDateInterval(\DateInterval $value): string;

    /**
     * Encodes string for use in a LIKE statement.
     */
    public function escapeLike(string $value, int $pos): string;

    public function addParameter(QueryParameter $param): void;

    public function getParameters(): array;

    public function bindText(?string $value, ?string $length = null, ?string $encoding = null): QueryParameter;

    public function bindAsciiText(?string $value, ?string $length = null, ?string $encoding = null): QueryParameter;

    public function bindIdentifier(?string $value): QueryParameter;

    public function bindInt(?int $value): QueryParameter;

    public function bindNumeric(?float $value, string $precision, string $scale): QueryParameter;

    public function bindDate(?\DateTimeInterface $value): QueryParameter;

    public function bindDateTime(?\DateTimeInterface $value): QueryParameter;

    public function bindDateInterval(?\DateInterval $value): QueryParameter;

    /**
     * Injects LIMIT/OFFSET to the SQL query.
     */
    public function applyLimit(string &$sql, ?int $limit, ?int $offset): void;
}

/**
 * Result set driver interface.
 */
interface ResultDriver {
    /**
     * Returns the number of rows in a result set.
     */
    public function getRowCount(): int;

    /**
     * Moves cursor position without fetching row.
     *
     * @throws Exception
     *
     * @return bool true on success, false if unable to seek to specified record
     */
    public function seek(int $row): bool;

    /**
     * Fetches the row at current position and moves the internal cursor to the next position.
     *
     * @param bool $type true for associative array, false for numeric
     *
     * @internal
     */
    public function fetch(bool $type): ?array;

    /**
     * Frees the resources allocated for this result set.
     */
    public function free(): void;

    /**
     * Returns metadata for all columns in a result set.
     *
     * @return array of {name, nativetype [, table, fullname, (int) size, (bool) nullable, (mixed) default, (bool) autoincrement, (array) vendor ]}
     */
    public function getResultColumns(): array;

    /**
     * Returns the result set resource.
     *
     * @return mixed
     */
    public function getResultResource();

    /**
     * Decodes data from result set.
     */
    public function unescapeBinary(string $value): string;
}

/**
 * Reflection driver.
 */
interface Reflector {
    /**
     * Returns list of tables.
     *
     * @return array of {name [, (bool) view ]}
     */
    public function getTables(): array;

    /**
     * Returns metadata for all columns in a table.
     *
     * @return array of {name, nativetype [, table, fullname, (int) size, (bool) nullable, (mixed) default, (bool) autoincrement, (array) vendor ]}
     */
    public function getColumns(string $table): array;

    /**
     * Returns metadata for all indexes in a table.
     *
     * @return array of {name, (array of names) columns [, (bool) unique, (bool) primary ]}
     */
    public function getIndexes(string $table): array;

    /**
     * Returns metadata for all foreign keys in a table.
     */
    public function getForeignKeys(string $table): array;
}

/**
 * Dibi connection.
 */
interface IConnection {
    /**
     * Connects to a database.
     */
    public function connect(): void;

    /**
     * Disconnects from a database.
     */
    public function disconnect(): void;

    /**
     * Returns true when connection was established.
     */
    public function isConnected(): bool;

    /**
     * Returns the driver and connects to a database in lazy mode.
     */
    public function getDriver(): Driver;

    /**
     * Generates (translates) and executes SQL query.
     *
     * @throws Exception
     */
    public function query(...$args): Result;

    /**
     * Gets the number of affected rows by the last INSERT, UPDATE or DELETE query.
     *
     * @throws Exception
     */
    public function getAffectedRows(): int;

    /**
     * Retrieves the ID generated for an AUTO_INCREMENT column by the previous INSERT query.
     *
     * @throws Exception
     */
    public function getInsertId(string $sequence = null): int;

    /**
     * Begins a transaction (if supported).
     */
    public function begin(string $savepoint = null): void;

    /**
     * Commits statements in a transaction.
     */
    public function commit(string $savepoint = null): void;

    /**
     * Rollback changes in a transaction.
     */
    public function rollback(string $savepoint = null): void;
}
