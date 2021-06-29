<?php

/**
 * This file is part of the Dibi, smart database abstraction layer (https://dibiphp.com)
 * Copyright (c) 2005 David Grudl (https://davidgrudl.com).
 */

declare(strict_types=1);

namespace Dibi;

/**
 * Profiler & logger event.
 */
class Event {
    use Strict;

    /** event type */
    public const
        CONNECT = 1,
        SELECT = 4,
        INSERT = 8,
        DELETE = 16,
        UPDATE = 32,
        QUERY = 60, // SELECT | INSERT | DELETE | UPDATE
        BEGIN = 64,
        COMMIT = 128,
        ROLLBACK = 256,
        TRANSACTION = 448, // BEGIN | COMMIT | ROLLBACK
        ALL = 1023;

    /** @var Connection */
    public $connection;

    /** @var int */
    public $type;

    /** @var string */
    public $sql;

    /** @var null|array */
    public $sqlParams;

    /** @var null|DriverException|Result */
    public $result;

    /** @var float */
    public $time;

    /** @var null|int */
    public $count;

    /** @var null|array */
    public $source;

    public function __construct(Connection $connection, int $type, string $sql = null, array $sqlParams = null) {
        $this->connection = $connection;
        $this->type = $type;
        $this->sql = trim((string) $sql);
        $this->sqlParams = $sqlParams;
        $this->time = -microtime(true);

        if ($type === self::QUERY && preg_match('#\(?\s*(SELECT|UPDATE|INSERT|DELETE)#iA', $this->sql, $matches)) {
            static $types = [
                'SELECT' => self::SELECT, 'UPDATE' => self::UPDATE,
                'INSERT' => self::INSERT, 'DELETE' => self::DELETE,
            ];
            $this->type = $types[strtoupper($matches[1])];
        }

        $dibiDir = dirname((new \ReflectionClass('dibi'))->getFileName()) . DIRECTORY_SEPARATOR;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $row) {
            if (isset($row['file']) && is_file($row['file']) && strpos($row['file'], $dibiDir) !== 0) {
                $this->source = [$row['file'], (int) $row['line']];

                break;
            }
        }

        \dibi::$elapsedTime = null;
        ++\dibi::$numOfQueries;
        \dibi::$sql = $sql;
    }

    /**
     * @param null|DriverException|Result $result
     */
    public function done($result = null): self {
        $this->result = $result;

        try {
            $this->count = $result instanceof Result ? count($result) : null;
        } catch (Exception $e) {
            $this->count = null;
        }

        $this->time += microtime(true);
        \dibi::$elapsedTime = $this->time;
        \dibi::$totalTime += $this->time;

        return $this;
    }
}
