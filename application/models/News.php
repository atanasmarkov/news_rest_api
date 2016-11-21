<?php

/**
 * News related information class.
 */
class News extends Model {

    const TABLE_NAME = 'news';
    const MAX_GET_ALL = 1000;

    /**
     * Get a list of all / 10000 latest news
     * 
     * @return array
     */
    public function getAll() {
        $sql = sprintf('select n.*, nt.title 
            from %1$s n
            join %2$s nt on n.id=nt.id
            order by n.adate desc limit %3$d'
                , self::getTableName()
                , NewsTexts::getTableName()
                , self::MAX_GET_ALL
        );

        $res = $this->database->query($sql);

        $result = array();
        foreach ($res as $value) {
            $result[] = array(
                'id' => $value->id,
                'date' => $value->adate,
                'title' => $value->title
            );
        }

        return $result;
    }

    /**
     * Get a specific record from table
     * 
     * @param integer $id
     * @return mixed    Full news item data
     * @throws Exception
     */
    public function getById($id) {
        $id = intval($id, 10);

        if (empty($id)) {
            throw new Exception('Id must be a valid integer.');
        }

        $item = $this->cache->getItem('news', $id);
        if ($item === null) {
            $sql = sprintf('select n.*, nt.title, nt.atext 
                from %1$s n
                join %2$s nt on n.id=%3$d and n.id=nt.id'
                    , self::getTableName()
                    , NewsTexts::getTableName()
                    , $id
            );

            $res = $this->database->query($sql);
            foreach ($res as $value) {
                $item = array(
                    'id' => $value->id,
                    'date' => $value->adate,
                    'updatedAt' => $value->updated_at,
                    'title' => $value->title,
                    'text' => $value->atext
                );
            }

            if (!empty($item)) {
                $item = (object) $item;
            } else {
                $item = false;
            }

            $this->cache->setItem('news', $id, $item);
        }

        if (!$item) {
            throw new Exception('Id not found in database.');
        }

        return $item;
    }

    public function validateData($data) {
        if (!empty($data) && is_object($data)) {
            if (!empty($data->id)) {
                $found= 0;
                $sql= sprintf('select id from %1$s where id=%2$d'
                        , self::getTableName()
                        , $data->id
                        );
                $res= $this->database->query($sql);
                
                foreach ($res as $value) {
                    $found= 1;
                }
                
                if (!$found) {
                    throw new Exception('Id is not valid.');
                }
            }
            
            if (empty($data->date) || !strtotime($data->date)) {
                    throw new Exception('Date is invalid.');
            }
            
            if (empty($data->title)) {
                    throw new Exception('Title can not be blank.');
            }
            
            if (empty($data->text)) {
                    throw new Exception('Text can not be blank.');
            }
            
        }
        
        return true;
    }
    
    /**
     * Saves a record.
     * 
     * @param object $data
     */
    public function saveItem($data) {
        if (!$this->validateData($data)) {
            throw new Exception('Data is invalid');
        }
        
        $this->database->beginTransaction();
        
        try {
            $curDate= date('Y-m-d H:i:s');
            
            $oldid= 0;
            if (!empty($data->id)) {
                $oldid= intval($data->id,10);
            }
            
            $sql= sprintf('insert into %1$s (id,adate,updated_at)
                values (%2$s,\'%3$s\',\'%4$s\')
                on duplicate key update adate=values(adate), updated_at=values(updated_at)
                '
                    , self::getTableName()
                    , !$oldid?'null':$oldid
                    , $this->database->escapeDate($data->date)
                    , $curDate
                    );
            $this->database->execute($sql);
            
            $id= $this->database->insertId();

            if (!$id) {
                throw new Exception('Insert failed');
            }
            
            $sql= sprintf('insert into %1$s (id,title,atext)
                values (%2$d,\'%3$s\',\'%4$s\')
                on duplicate key update title=values(title), atext=values(atext)
                '
                    , NewsTexts::getTableName()
                    , $id
                    , $this->database->escape($data->title)
                    , $this->database->escape($data->text)
                    );
            $this->database->execute($sql);
            
            $this->database->commit();
            
            if ($oldid) {
                $this->cache->deleteItem('news',$id);
            }
            
            $this->clearCachesOnUpdates();
        } catch (Exception $e) {
            $this->database->rollback();
            throw new Exception('Problem with saving data to db: '.$e->getMessage());
        }
        
        return $id;
    }

    /**
     * Deletes an item by given id. First checks for the item in db to give proper error message on fail.
     * This is slower than direct delete, but better for debugging client apps.
     * 
     * @param integer $id
     */
    public function deleteItem($id) {
        $id = intval($id, 10);

        if (!empty($id)) {
            $found = 0;

            $sql = sprintf('select id from %1$s where id=%2$d'
                    , self::getTableName()
                    , $id
            );
            $res = $this->database->query($sql);

            foreach ($res as $value) {
                $found = 1;
            }

            if (!$found) {
                throw new Exception('Item is not found in database.');
            }

            $sql = sprintf('delete from %1$s n, %2$s nt
                where n.id=%3$d and n.id=nt.id
                ');
            $this->database->execute($sql);

            $this->cache->deleteItem('news', $id);
            $this->clearCachesOnUpdates();
        }
    }

    /**
     * Returns a list of latest updated $count news.
     * 
     * @param type $count
     * @return array
     * @throws Exception
     */
    public function getLatestUpdatedNews($count = 10) {
        $count = intval($count, 10);
        if (!$count) {
            throw new Exception('Invalid count given.');
        }

        $cachekey = 'latest_' . $count;

        $result = $this->cache->getItem('news_queries', $cachekey);

        if ($result === null) {
            $result = array();

            $sql = sprintf('select n.*, nt.title, nt.atext 
                from %1$s n
                join %2$s nt on n.id=nt.id
                order by n.updated_at desc 
                limit %3$d
            '
                    , self::getTableName()
                    , NewsTexts::getTableName()
                    , $count
            );
            $res = $this->database->query($sql);

            foreach ($res as $value) {
                $result[] = array(
                    'id' => $value->id,
                    'date' => $value->adate,
                    'updatedAt' => $value->updated_at,
                    'title' => $value->title,
                    'text' => $value->atext
                );
            }

            $this->cache->setItem('news_queries', $cachekey, $result);
        }

        return $result;
    }

    /**
     * Get the number of news items
     * 
     * @return integer
     */
    public function getCount() {
        $result = $this->cache->getItem('news', 'count');

        if ($result === null) {
            $result = 0;

            $sql = sprintf('select count(*) as cnt from %1$s'
                    , self::getTableName()
            );
            $res = $this->database->query($sql);

            foreach ($res as $value) {
                $result = $value->cnt;
            }

            $this->cache->setItem('news', 'count', $result);
        }
        return $result;
    }

    /**
     * Get a set of results with some kind of pagination
     * 
     * @param integer $start
     * @param integer $limit
     */
    public function getItemsByLimit($start = 0, $limit = 0) {
        $limit = intval($limit, 10);

        if (empty($limit)) {
            $limit = self::MAX_GET_ALL;
        }

        /*
         * on really large sets a possible scenario is to get ids only and load items using cache. We can have two steps:
         * 
         * - get items in cache
         * - get the rest as bulk query 
         * - save the rest of items in cache
         * 
         * This tactic can be useful when we make complex filtering too.
         */

        if ($start < 0) {
            throw new Exception('Out of boundaries.');
        }

        /*
         * this way we may save an SQL query to try to count results in table, then decide that limit is not proper. especially on filters this 
         * may be useful on big data.
         */
        $count = $this->getCount();
        if ($start > $count) {
            throw new Exception('Out of boundaries.');
        }

        $cachekey = sprintf('limit_%d_%d', $start, $limit);
        $result = $this->cache->getItem('news_queries', $cachekey);

        if ($result === null) {
            $sql = sprintf('select n.*, nt.title , nt.atext
            from %1$s n
            join %2$s nt on n.id=nt.id
            limit %3$d,%4$d'
                    , self::getTableName()
                    , NewsTexts::getTableName()
                    , $start
                    , $limit
            );

            $res = $this->database->query($sql);

            $result = array();
            foreach ($res as $value) {
                $result[] = array(
                    'id' => $value->id,
                    'date' => $value->adate,
                    'title' => $value->title,
                    'text' => $value->atext
                );
            }

            $this->cache->setItem('news_queries', $cachekey, $result);
        }

        return $result;
    }

    /**
     * Clears category caches, count and so on items that may contain the updated item
     */
    protected function clearCachesOnUpdates() {
        $this->cache->clearCategory('news_queries');
        $this->cache->deleteItem('news', 'count');
        $this->cache->deleteItem('news', 'getAll');
    }

}
