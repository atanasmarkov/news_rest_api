<?php

/**
 * A caching layer for models. If disabled, it will just return false as memcached does.
 * Has extra local caching- not exactly good if some functionality needs lets say counters in
 * caches, but is good for most cases.
 * 
 * May be extended to save data on object destruction / calling a flush method from index.php. 
 * Actually much faster for saves will be:
 * 
 * - delete marks for deletion locally
 * - save updates only local cache, marks record as changed and unmarks deletion if needed
 * - category clear clears markings for deletions and saves for the category 
 * - flush deletes marked items and saves all save updates
 * 
 * This way if needed to update a record many times in a script call we'll use only 1 memcache save / delete.
 */
class MemcacheCache {

    protected $server;

    /**
     * an array for storing caches in PHP too ontoo many accesses for same data 
     */
    protected $level2Cache;

    /**
     * an array for storing category keys for reuse     *
     */
    protected $categoryKeys;

    /**
     * Checks for a memcache config and connects to a server if present.
     * 
     * @param array $config Must contain at least a host field. Port is optional. If empty, memcached caching is disabled.
     */
    public function __construct($config) {
        $this->server = null;

        if (!empty($config) && class_exists('Memcache')) {
            $this->server = new Memcache();
            if (!empty($config['port'])) {
                $this->server->connect($config['host'], $config['port']);
            } else {
                $this->server->connect($config['host']);
            }
        }

        $this->categoryKeys = array();
        $this->level2Cache = array();
    }

    /**
     * Gets a key that must match with a field inside the item(for mass deletes)
     * 
     * @param string $category Category name/key
     */
    public function getCategoryKey($category) {
        if (isset($this->categoryKeys[$category])) {
            return $this->categoryKeys[$category];
        }

        $result = false;
        if (!empty($this->server)) {
            $catkey = 'catkey_' . $category;

            $result = $this->server->get($catkey);

            if (!$result) {
                $result = microtime(true) . mt_rand(0, 999999);
                $this->server->set($catkey, $result); //set key for other scripts
            }
        }

        $this->categoryKeys[$category] = $result;

        return $result;
    }

    

    /**
     * Set a new category key and cleat local caches for it
     * @param string $category Category name/key
     */
    public function clearCategory($category) {
        $newkey = microtime(true) . mt_rand(0, 999999);
        if (!empty($this->server)) {
            $catkey = 'catkey_' . $category;

            $this->server->set($catkey, $newkey); //set key for other scripts
        }

        $this->categoryKeys[$category] = $newkey;
        
        $this->level2Cache[$category]= null; //do not wait for GC
        unset($this->level2Cache[$category]);
    }

    
    /**
     * Get an item with checking if it is with a deleted category.
     * 
     * @param string $category Category name/key
     * @param string $key Item key
     */
    public function getItem($category, $key) {
        if (!empty($this->level2Cache[$category]) && isset($this->level2Cache[$category][$key])) {
            //check local cache first

            return $this->level2Cache[$category][$key];
        }

        if (!empty($this->server)) {
            //get from memcache if using it
            $catkey = $this->getCategoryKey($category);

            if ($catkey) {
                //we need to fetch items only if cat is set up there ;)
                $fullkey = $catkey . '_' . $key; //this allows easy clearing of whole categories

                $serverResult = $this->server->get($fullkey);

                if (!is_array($serverResult) || !isset($serverResult['data'])) {
                    $result = null;
                } else {
                    $result = $serverResult['data'];
                }
            } else {
                $result = null; //no way to have a record for a key if category has no own key yet
            }
        } else {
            $result = null;
        }

        //local cache update
        if (!isset($this->level2Cache[$category])) {
            $this->level2Cache[$category] = array();
        }
        $this->level2Cache[$category][$key] = $result;

        return $result;
    }

    /**
     * Set an item in cache. If needed create a category key.
     * 
     * @param string $category Category name/key
     * @param string $key Item key
     * @param mixed $data Item value
     */
    public function setItem($category, $key, $data) {
        if (!isset($this->level2Cache[$category])) {
            $this->level2Cache[$category] = array();
        }
        $this->level2Cache[$category][$key] = $data;

        if (!empty($this->server)) {
            $catkey = $this->getCategoryKey($category);
            if (empty($catkey)) {
                $this->clearCategory($category); //create a new key if category has had no key till now
            }

            $fullkey = $catkey . '_' . $key; //this allows easy clearing of whole categories
            $this->server->set($fullkey, array('data' => $data));
        }
    }
    
    /**
     * Delete an item from cache.
     * 
     * @param string $category Category name/key
     * @param string $key Item key
     */
    public function deleteItem($category, $key) {
        if (!empty($this->level2Cache[$category]) && isset($this->level2Cache[$category][$key])) {
            $this->level2Cache[$category][$key]= null;
            unset($this->level2Cache[$category][$key]);
        }

        if (!empty($this->server)) {
            $catkey = $this->getCategoryKey($category);
            if (!empty($catkey)) {
                $fullkey = $catkey . '_' . $key; 
                $this->server->delete($fullkey);
            }
        }
    }
}
