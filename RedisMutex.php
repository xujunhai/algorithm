<?php
/**
 * 基于单点 redis的分布式锁 
 * 
 * 但是存在如下问题：
 * 1.锁必须要加过期时间 否则 第一个client获取锁 崩溃了或者无法与redis通信,一直持有锁，这个时间lock validity time，
 * 获取锁的客户端必须在这个时间内完成资源访问
 * 2.一条指令获取锁，保证原子性，防止网络通信不能设置过期时间
 * 3.随机字符值,客户端必须是删除自己持有的锁 不能是固定值 防止client互相释放
 * 4.释放锁必须lua脚本实现. 执行序列 1.client1获取锁 2.client1释放锁 3.get随机字符串==自己的随机字符串 4.client1由于
 * 某些原因阻塞了一段时间 5.过期时间到了，锁自动释放 6.client2获取资源锁 7.client1从阻塞中恢复 执行del操作释放了client2
 * 持有的锁
 * 
 *  REDLOCK算法实现 http://malkusch.github.io/lock/api/class-malkusch.lock.mutex.PHPRedisMutex.html
 * 
 */

abstract class Mutex{
    
    /**
     * This method should be extended by concrete mutex implementations. Acquires lock by given name.
     * @param string $name 占用锁名称
     * @param integer $timeout 锁的时间 --- 防止一个进程获取锁之后没有释放
     * @return boolean acquiring result.
     */
    abstract protected function acquireLock($name, $timeout = 0);

    /**
     * This method should be extended by concrete mutex implementations. Releases lock by given name.
     * @param string $name of the lock to be released.
     * @return boolean release result.
     */
    abstract protected function releaseLock($name);
}

class RedisMutex extends Mutex{
      
    /**
     * 依赖的redis实例 version>2.6.0 参见github phpredis扩展 
     * @var 
     */
    private $redis;
    
    /**
     * 资源名称
     * @var string key
     */
    private $resourceName;
    
    private $lockExpire;
    
    /**
     * @var int sleep ms until next lock try during timeout waiting
     */
    public $lockWaitSleep = 200;
    
    public function __construct($resourceName,$expire = 30000){
        $this->redis = new Redis(); //注入或者单例
        $this->resourceName = $resourceName;
        $this->lockExpire = $expire;
    }
    
    /**
     * 随机资源值
     */
    private function getRandStr($length = 32){
        return random_bytes();
    }
    
    protected function acquireLock($name, $timeout = 0){
        
        $lockKey = $this->resourceName;
        $lockValue = $this->getRandStr();
        
        /**
         * Set lock command
         * 发送命令: SET resource_name my_random_value NX PX 300000
         *
         * @return array|bool|null|string
         */
        $setLock = function () use ($name, $lockKey, $lockValue) {
            return $this->redis->set($lockKey, $lockValue, ['NX','PX'=>$this->lockExpire]);
        };
        if ($setLock()) {
            return true;
        }
        
        while ($timeout > 0) {
            usleep($this->lockWaitSleep * 1000);
            $timeout -= $this->lockWaitSleep;
            if ($setLock()) {
                return true;
            }
        }
        return false;
    }
    
    
    
    /**
     * redis2.6.0版本内置lua解释器 通过eval命令对lua脚本执行
     * 
     * {@inheritDoc}
     * @see Mutex::releaseLock()
     */
    protected function releaseLock($name){
        $lockKey = $this->resourceName;
        $lockValue = $this->getRandStr();

        $luaScript = 'if redis.call("GET", KEYS[1]) == ARGV[1] then
                        return redis.call("DEL", KEYS[1])
                    else
                        return 0
                    end';
        if ($this->redis->eval($luaScript,1,[$lockKey], [$lockValue])) {
            return true;
        }
        return false;
    }
}