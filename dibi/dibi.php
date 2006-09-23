<?php

/**
 * dibi - Database Abstraction Layer according to dgx
 * --------------------------------------------------
 *
 * For PHP 5.0.3 or newer
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/dibi/
 * @copyright  Copyright (c) 2005-2006 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    dibi
 * @category   Database
 * @version    0.6c $Revision$ $Date$
 */


define('DIBI', 'Version 0.6c $Revision$');


if (version_compare(PHP_VERSION , '5.0.3', '<'))
    die('dibi needs PHP 5.0.3 or newer');


// libraries
require_once dirname(__FILE__).'/libs/driver.php';
require_once dirname(__FILE__).'/libs/resultset.php';
require_once dirname(__FILE__).'/libs/parser.php';
require_once dirname(__FILE__).'/libs/exception.php';



// required since PHP 5.1.0
if (function_exists('date_default_timezone_set'))
    date_default_timezone_set('Europe/Prague');    // or 'GMT'



/**
 * Interface for user variable, used for generating SQL
 */
interface IDibiVariable
{
    /**
     * Format for SQL
     *
     * @param  object  destination DibiDriver
     * @param  string  optional modifier
     * @return string  SQL code
     */
    public function toSQL($driver, $modifier = NULL);
}





/**
 * Interface for database drivers
 *
 * This class is static container class for creating DB objects and
 * store debug & connections info.
 *
 */
class dibi
{
    /**
     * Column type in relation to PHP native type
     */
    const
        FIELD_TEXT =       's', // as 'string'
        FIELD_BINARY =     'S',
        FIELD_BOOL =       'b',
        FIELD_INTEGER =    'i',
        FIELD_FLOAT =      'f',
        FIELD_DATE =       'd',
        FIELD_DATETIME =   't',
        FIELD_UNKNOWN =    '?',

        // special
        FIELD_COUNTER =    'c'; // counter or autoincrement, is integer


    /**
     * Connection registry storage for DibiDriver objects
     * @var array
     */
    static private $registry = array();

    /**
     * Current connection
     * @var object DibiDriver
     */
    static private $conn;

    /**
     * Last SQL command @see dibi::query()
     * @var string
     */
    static public $sql;
    static public $error;

    /**
     * File for logging SQL queryies - strongly recommended to use with NSafeStream
     * @var string|NULL
     */
    static public $logFile;
    static public $logMode = 'w';

    /**
     * Enable/disable debug mode
     * @var bool
     */
    static public $debug = false;


    /**
     * Substitutions for identifiers
     * @var array
     */
    static private $substs = array();



    /**
     * Creates a new DibiDriver object and connects it to specified database
     *
     * @param  array|string connection parameters
     * @param  string       connection name
     * @return bool|object  TRUE on success, FALSE or Exception on failure
     */
    static public function connect($config, $name = '1')
    {
        // DSN string
        if (is_string($config))
            parse_str($config, $config);

        // config['driver'] is required
        if (empty($config['driver']))
            return new DibiException('Driver is not specified.');

        // include dibi driver
        $className = "Dibi$config[driver]Driver";
        if (!class_exists($className)) {
            include_once dirname(__FILE__) . "/drivers/$config[driver].php";

            if (!class_exists($className))
                return new DibiException("Unable to create instance of dibi driver class '$className'.");
        }


        // create connection object
        /** like $conn = $className::connect($config); */
        $conn = call_user_func(array($className, 'connect'), $config);

        // optionally log to file
        // todo: log other exceptions!
        if (self::$logFile != NULL && self::$logMode) {
            if (is_error($conn))
                $msg = "Can't connect to DB '$config[driver]': ".$conn->getMessage();
            else
                $msg = "Successfully connected to DB '$config[driver]'";

            $f = fopen(self::$logFile, self::$logMode);
            fwrite($f, "$msg\r\n\r\n");
            fclose($f);
        }

        if (is_error($conn)) {
            // optionally debug on display
            if (self::$debug) echo '[dibi error] ' . $conn->getMessage();

            return $conn; // reraise the exception
        }

        // store connection in list
        self::$conn = self::$registry[$name] = $conn;

        return TRUE;
    }



    /**
     * Returns TRUE when connection was established
     *
     * @return bool
     */
    static public function isConnected()
    {
        return (bool) self::$conn;
    }


    /**
     * Retrieve active connection
     *
     * @return object   DibiDriver object.
     */
    static public function getConnection()
    {
        return self::$conn;
    }



    /**
     * Change active connection
     *
     * @param  string   connection registy name
     * @return void
     */
    static public function activate($name)
    {
        if (!isset(self::$registry[$name]))
            return FALSE;

        // change active connection
        self::$conn = self::$registry[$name];
        return TRUE;
    }






    /**
     * Generates and executes SQL query
     *
     * @param  array|mixed    one or more arguments
     * @return int|DibiResult|Exception
     */
    static public function query($args)
    {
        if (!self::$conn) return new DibiException('Dibi is not connected to DB'); // is connected?

        // receive arguments
        if (!is_array($args))
            $args = func_get_args();

        // and generate SQL
        $parser = new DibiParser(self::$conn, self::$substs);
        self::$sql = $parser->parse($args);
        if (is_error(self::$sql)) return self::$sql;  // reraise the exception

        // execute SQL
        $timer = -microtime(true);
        $res = self::$conn->query(self::$sql);
        $timer += microtime(true);

        if (is_error($res)) {
            // optionally debug on display
            if (self::$debug) {
                echo '[dibi error] ' . $res->getMessage();
                self::dump(self::$sql);
            }
            // todo: log all errors!
            self::$error = $res;
        } else {
            self::$error = FALSE;
        }

        // optionally log to file
        if (self::$logFile != NULL)
        {
            if (is_error($res))
                $msg = $res->getMessage();
            elseif ($res instanceof DibiResult)
                $msg = 'object('.get_class($res).') rows: '.$res->rowCount();
            else
                $msg = 'OK';

            $f = fopen(self::$logFile, 'a');
            fwrite($f,
               self::$sql
               . ";\r\n-- Result: $msg"
               . "\r\n-- Takes: " . sprintf('%0.3f', $timer * 1000) . ' ms'
               . "\r\n\r\n"
            );
            fclose($f);
        }

        return $res;
    }





    /**
     * Generates and returns SQL query
     *
     * @param  array|mixed  one or more arguments
     * @return string
     */
    static public function test($args)
    {
        if (!self::$conn) return FALSE; // is connected?

        // receive arguments
        if (!is_array($args))
            $args = func_get_args();

        // and generate SQL
        $parser = new DibiParser(self::$conn, self::$substs);
        $sql = $parser->parse($args);
        $dump = TRUE; // !!!
        if ($dump) {
            if (is_error($sql))
                self::dump($sql->getSql());
            else
                self::dump($sql);
        }
        return $sql;
    }



    /**
     * Monostate for DibiDriver::insertId()
     *
     * @return int
     */
    static public function insertId()
    {
        return self::$conn ? self::$conn->insertId() : FALSE;
    }



    /**
     * Monostate for DibiDriver::affectedRows()
     *
     * @return int
     */
    static public function affectedRows()
    {
        return self::$conn ? self::$conn->affectedRows() : FALSE;
    }



    static private function dumpHighlight($matches)
    {
        if (!empty($matches[1])) // comment
            return '<em style="color:gray">'.$matches[1].'</em>';

        if (!empty($matches[2])) // error
            return '<strong style="color:red">'.$matches[2].'</strong>';

        if (!empty($matches[3])) // most important keywords
            return '<strong style="color:blue">'.$matches[3].'</strong>';

        if (!empty($matches[4])) // other keywords
            return '<strong style="color:green">'.$matches[4].'</strong>';
    }


    /**
     * Prints out a syntax highlighted version of the SQL command
     *
     * @param string   SQL command
     * @return void
     */
    static public function dump($sql) {
        static $keywords2 = 'ALL|DISTINCT|AS|ON|INTO|AND|OR|AS';
        static $keywords1 = 'SELECT|UPDATE|INSERT|DELETE|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN';

        // insert new lines
        $sql = preg_replace("#\\b(?:$keywords1)\\b#", "\n\$0", $sql);

        $sql = trim($sql);
        // reduce spaces
        // $sql = preg_replace('#  +#', ' ', $sql);

        $sql = wordwrap($sql, 100);
        $sql = htmlSpecialChars($sql);
        $sql = str_replace("\n", '<br />', $sql);

        // syntax highlight
        $sql = preg_replace_callback("#(/\*.+?\*/)|(\*\*.+?\*\*)|\\b($keywords1)\\b|\\b($keywords2)\\b#", array('dibi', 'dumpHighlight'), $sql);

        echo '<pre class="dibi">', $sql, '</pre>';
    }



    /**
     * Displays complete result-set as HTML table
     *
     * @param object   DibiResult
     * @return void
     */
    static public function dumpResult(DibiResult $res)
    {
        echo '<table class="dump"><tr>';
        echo '<th>Row</th>';
        $fieldCount = $res->fieldCount();
        for ($i = 0; $i < $fieldCount; $i++) {
            $info = $res->fieldMeta($i);
            echo '<th>'.htmlSpecialChars($info['name']).'</th>';
        }
        echo '</tr>';

        foreach ($res as $row => $fields) {
            echo '<tr><th>', $row, '</th>';
            foreach ($fields as $field) {
                if (is_object($field)) $field = $field->__toString();
                echo '<td>', htmlSpecialChars($field), '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    }



    /**
     * Create a new substitution pair for indentifiers
     * @param string from
     * @param string to
     * @return void
     */
    static public function addSubst($expr, $subst)
    {
        self::$substs[':'.$expr.':'] = $subst;
    }


    /**
     * Remove substitution pair
     * @param string from
     * @return void
     */
    static public function removeSubst($expr)
    {
        unset(self::$substs[':'.$expr.':']);
    }




} // class dibi
