<?
class iCache{
    private $cache_id;
    private $cache_time;
    private $cache_path;
    private $cache;
    public function __construct($cache_id, $cache_time=36000, $cache_path="/iCache/")
    {

        $this->cache_id=$cache_id.'_'.SITE_ID;
        $this->cache_time=$cache_time;
        $this->cache_path=$cache_path;
        $this->cache=new CPHPCache();

    }
    function hasCache(){
        if ($this->cache->InitCache($this->cache_time, $this->cache_id, $this->cache_path) && $_REQUEST["clear_cache"]!=="Y") {
            return   $this->cache->GetVars();
        }
        return false;

    }
    function SaveToCache($var){
        $this->cache->Clean($this->cache_id, $this->cache_path);
        $this->cache->StartDataCache($this->cache_time, $this->cache_id, $this->cache_path);
        $this->cache->EndDataCache($var);
    }
    public static function init($key, $function, $time='36000'){
        $cache=new iCache($key, $time);
        if(!$data=$cache->hasCache()){
            $reflectionAction = new ReflectionFunction($function);
            $data=$reflectionAction->invoke();
            if(is_array($data)){
                $cache->SaveToCache($data);
            }
        }
        return $data;
    }

}
?>