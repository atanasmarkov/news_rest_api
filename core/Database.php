<?php

/**
 * A query iterator object so we can easily use foreach without fetch methods
 */
class Query implements Iterator {

    protected $query;
    protected $lastrec;
    protected $_index;
    protected $_row;

    /**
     * Create the iterator object
     * 
     * @param mixed $query  Query handle from the mysqli instance
     */
    public function __construct($query) {
        if (!empty($query)) {
            $this->query = $query;
            $this->_row = false;
        }
    }

    /**
     * Close the query in mysqli
     */
    public function __destruct() {
        if (!empty($this->query)) {
            $this->query->close();
        }
    }

    /**
     * Iterator implementation needs this (for using in foreach statements)
     * @return int
     */
    public function count() {
        if (!empty($this->query)) {
            return $this->query->num_rows;
        }

        return 0;
    }

    /**
     * Iterator implementation needs this (for using in foreach statements)
     */
    public function rewind() {
        $this->_row = $this->query->fetch_object();
        $this->_index = 0;
    }

    /**
     * Iterator implementation needs this (for using in foreach statements)
     * @return Object
     */
    public function current() {
        return $this->_row;
    }

    /**
     * Iterator implementation needs this (for using in foreach statements)
     * @return int
     */
    public function key() {
        return $this->_index;
    }

    /**
     * Iterator implementation needs this (for using in foreach statements)
     */
    public function next() {
        if (!empty($this->query)) {
            $this->_row = $this->query->fetch_object();
            $this->_index++;
        }
    }

    /**
     * Iterator implementation needs this (for using in foreach statements)
     * @return boolean
     */
    public function valid() {
        return $this->_row !== false && $this->_row !== null;
    }

}

/**
 * Main database class to handle the connection and allow methods such as escaping strings
 */
class Database {

    protected $dbinstance;
    protected $dbname;
    protected $mysqlversion; //for transaction support
    protected $dbconfig;

    /**
     * 
     * @param array $dbconfig The $dbconfig array from config
     * @throws Exception
     */
    public function __construct($dbconfig) {
        $this->dbconfig = $dbconfig;
        
        $this->dbname = $this->dbconfig['dbname'];
        $this->dbinstance = null;
    }

    /**
     * Init database on demand as we may get all from cache without any access to database ;)
     * @throws Exception
     */
    protected function initDBInstance() {
        /*
         * prevent PHP internal error reporting- we'll throw an exception
         */
        $olderep = error_reporting();

        error_reporting(0);

        if (!empty($this->dbconfig['dbport'])) {
            $this->dbinstance = new mysqli($this->dbconfig['dbhost'], $this->dbconfig['dbuser'], $this->dbconfig['dbpass'], $this->dbconfig['dbname'], $this->dbconfig['dbport']);
        } else {
            $this->dbinstance = new mysqli($this->dbconfig['dbhost'], $this->dbconfig['dbuser'], $this->dbconfig['dbpass'], $this->dbconfig['dbname']);
        }

        error_reporting($olderep);

        $this->dbinstance->select_db($this->dbconfig['dbname']);

        if ($this->dbinstance->errno)
            throw new Exception("MySQL error: (" . $this->dbinstance->errno . ") " . $this->dbinstance->error);

        if ($this->dbinstance->connect_errno)
            throw new Exception("Failed to connect to MySQL: (" . $this->dbinstance->connect_errno . ") " . $this->dbinstance->connect_error);

        /*
         * fix mysql time zone to match the PHP one
         */
        $tzones = DateTimeZone::listIdentifiers();
        $timezone = date_default_timezone_get();

        if (!empty($timezone)) {
            $this->connectiontz = $timezone;

            date_default_timezone_set($timezone);

            $this->execute("set time_zone='$timezone'");
        }

        //explicitely setup utf-8
        $this->execute("set names 'utf8'");

        $this->mysqlversion = $this->dbinstance->server_version;
    }

    /**
     * Execute a database query without returning a handle 
     * 
     * @param string $sql
     * @throws Exception
     */
    public function execute($sql) {
        if (empty($this->dbinstance)) {
            $this->initDBInstance();
        }

        $this->dbinstance->query($sql);

        if ($this->dbinstance->errno)
            throw new Exception("MySQL error: (" . $this->dbinstance->errno . ") " . $this->dbinstance->error);
    }

    /**
     * Execute a sql query and return a query iterator for it
     * 
     * @param type $sql
     * @return \Query
     * @throws Exception
     */
    public function query($sql) {
        if (empty($this->dbinstance)) {
            $this->initDBInstance();
        }

        $tmp = $this->dbinstance->query($sql);

        if ($this->dbinstance->errno)
            throw new Exception("MySQL error: (" . $this->dbinstance->errno . ") " . $this->dbinstance->error);

        return new Query($tmp);
    }

    /**
     * Escape a string for sql 
     * 
     * @param type $str
     * @return type
     */
    public function escape($str) {
        if (empty($this->dbinstance)) {
            $this->initDBInstance();
        }

        return $this->dbinstance->real_escape_string($str);
    }

    /**
     * Escapes a date for mysql.
     * 
     * @param string $date
     * @return string
     */
    public function escapeDate($date) {
        return date('Y-m-d H:i:s', strtotime($date));
    }
    
    /**
     * Explicitely close the database connection on object destruction
     */
    public function __destruct() {
        if (!empty($this->dbinstance)) {
            $this->dbinstance->close();
        }
    }

    /**
     * Get the last insert id 
     * 
     * @return int
     */
    public function insertId() {
        if (!empty($this->dbinstance)) {
            return $this->dbinstance->insert_id;
        } else {
            return null;
        }
    }

    /**
     * Transaction start.
     * As for MySQL 5.6 and later mysqli has different API, we execute two different scenarios depending on MySQL server version
     */
    public function beginTransaction() {
        if (empty($this->dbinstance)) {
            $this->initDBInstance();
        }

        //check mysql version- prior to 5.6 we must use different scenario
        if ($this->mysqlversion >= 50600) {
            //5.6 and up
            $args = func_get_args();
            call_user_func_array(array($this->dbinstance, 'begin_transaction'), $args);
        } else {
            $this->dbinstance->autocommit(false);
        }
    }

    /**
     * Commit a transaction. This is a "gateway" to the mysqli so see its commit docs for arguments.
     */
    public function commit() {
        if (empty($this->dbinstance)) {
            $this->initDBInstance();
        }

        $args = func_get_args();
        call_user_func_array(array($this->dbinstance, 'commit'), $args);

        if ($this->mysqlversion < 50600) {
            $this->dbinstance->autocommit(true);
        }
    }

    /**
     * Rollback a transaction. This is a "gateway" to the mysqli so see its rollback docs for arguments.
     */
    public function rollback() {
        if (empty($this->dbinstance)) {
            $this->initDBInstance();
        }

        $args = func_get_args();
        call_user_func_array(array($this->dbinstance, 'rollback'), $args);

        if ($this->mysqlversion < 50600) {
            $this->dbinstance->autocommit(true);
        }
    }

    /**
     * Return the database name in use
     * 
     * @return string
     */
    public function getDBName() {
        return $this->dbname;
    }

}
