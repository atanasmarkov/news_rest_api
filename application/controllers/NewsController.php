<?php

/**
 * News functionality controller.
 */

class NewsController extends Controller {
    
    /**
     * This method returns the getAll action results, but allows caching the whole JSON in memcache.
     * Actually caching inside controller is good for complex html views, but why not show some form of usage...
     * 
     * @return mixed
     */
    protected function getAllNews() {
        $model = new News();
        
        $result= $model->getAll();
        return $result;
    }
    
    protected function actionGetCount() {
        $model= new News();
        return array('itemsCount'=>$model->getCount());
    }
    
    /**
     * Get a list of all news in db. Actually will be latest 1000 to save RAM, bandwidth and cache the list. 
     * List is only id, date and title.
     * 
     * @return array
     */
    protected function actionGetAll() {
        $result= $this->cachedCall('news', 'getAll', 'getAllNews');
        return $result;
    }
    
    /**
     * In this method we will not use caching in controller. Both usages with and without JSON caching are possible.
     * 
     * @param type $start
     * @param type $limit
     */
    protected function actionGetByLimit($start=0,$limit=0) {
        $model = new News();
        
        $result= $model->getItemsByLimit($start, $limit);
        return $result;
    }

    /**
     * Return latest updated news
     * 
     * @param integer $count
     * @return array
     */
    protected function actionGetLatestUpdated($count=10) {
        $model= new News();
        return $model->getLatestUpdatedNews($count);
    }
    
    /**
     * Clears the cache for performance tests / reinit
     * 
     * @return mixed
     */
    protected function actionClearCache() {
        $this->cache->clearCategory('news');
        $this->cache->clearCategory('news_queries');
        
        return array('message'=>'Cache cleared.');
    }
    
    /**
     * Get a single item info by id
     * 
     * @param integer $id
     */
    protected function actionGetItem($id) {
        $model= new News();
        
        return $model->getById($id);
    }
    
    /**
     * The GET method. It calls a proper action based on the parameter(routing first /)
     * 
     * @param mixed $action A parameter for actions as we may list all / get single / list by date /...
     */
    public function methodGet($action=null) {
        if ($action === null) {
            $action= 'getAll';
        }
        
        //allow calling single item get by just passing an int id instead of an action
        $intid= sprintf('%d',$action);
        if (  $intid == $action) {
            $action= 'getItem';
        }
        
        $origAction= $action;
        
        $action= 'action'.ucfirst($action);
        if (method_exists($this, $action)) {
            if ($action != 'actionGetItem') {
                $params= func_get_args();
                array_shift($params);
            } else {
                $params= array($intid);
            }
            
            $result= call_user_func_array(array($this,$action), $params);
        } else {
            throw new Exception('Action '.$origAction.' is not supported by method GET.');
        }
        
        return $result;
    }
    
    /**
     * Delete a record by DELETE http method
     * @param integer $id
     * @throws Exception
     */
    public function methodDelete($id=null) {
        $id= intval($id,10);
        if (!empty($id)) {
            $model= new News();
            $model->deleteItem($id);
            return array('ok'=>'ok');
        } else {
            throw new Exception('You must specify an id.');
        }
    }
    
    /**
     * Save a new / update an old record
     * 
     * @param integer $id
     * @throws Exception
     */
    public function methodPost() {
        $jsondata= file_get_contents('php://input');
        $data= json_decode($jsondata,false);
        
        if (!empty($data)) {
            $model= new News();
            $model->saveItem($data);
            return array('ok'=>'ok');
        } else {
            throw new Exception('You must specify an id.');
        }
    }
    
    /**
     * Just a shortcut to POST
     */
    public function methodPut() {
        return $this->methodPost();
    }
}
