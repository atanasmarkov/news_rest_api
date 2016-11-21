<?php

/*
 * A base class for models so we can easily implement caching and so on.
 */

class Model {

    const TABLE_NAME = ''; //the model table name

    protected $cache;
    protected $database;

    /**
     * init the model- set cache instance and so on
     * 
     * @param Database $database Database connection. If null/not set, the global one will be used.
     * @param MemcacheCache $cache Memcache controller instance. If null/not set, the global one will be used.
     */
    public function __construct($database = null, $cache = null) {
        if (empty($database)) {
            global $databaseInstance;
            $database = $databaseInstance;
        }

        $this->database = $database;

        if (empty($cache)) {
            global $cacheInstance;
            $cache = $cacheInstance;
        }
        $this->cache = $cache;
    }

    /**
     * Return the table name escaped for use in SQLs
     * @return type
     */
    static function getTableName() {
        $class= get_called_class();
        
        return '`' . $class::TABLE_NAME . '`';
    }

}
