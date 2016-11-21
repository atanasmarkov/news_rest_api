<?php

class Controller {

    protected $cache;
    protected $database;

    /**
     * init- set cache instance and so on
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
     * A layer for caching whole method calls. 
     * Useful for requests like /items/100 where we return an item or lists
     * 
     * @param string $action
     * @param string $category
     * @param string $key
     * @return string Action output
     */
    protected function cachedCall($category, $key, $action) {
        $data = $this->cache->getItem($category, $key);

        if ($data === null || $data === false) {
            $params = func_get_args();
            array_shift($params);

            $data = call_user_func_array(array($this, $action), $params);
            $this->cache->setItem($category, $key, $data);
        }

        return $data;
    }

}
