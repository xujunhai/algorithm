<?php
/**
 * 延时队列算法
 */

class Task{
    
    /**
     * 几秒后执行
     * @var int s
     */
    private $delayTime;
    
    /**
     * 周期 如果对象cylenum=0 说明要执行  指针指向他的时候如果不为0 --cylenum
     * @var integer
     */
    private $cyleNum = 0;
    
    /**
     * 回调函数
     * @var Closure| Object Method | function
     */
    private $callBack;
    

    public function __construct($delayTime,callable $callBack){
            $this->setCallBack($callBack);
            $this->setDelayTime($delayTime);
    }
    
    /**
     * @return the $delayTime
     */
    public function getDelayTime ()
    {
        return $this->delayTime;
    }

    /**
     * @return the $cyleNum
     */
    public function getCyleNum ()
    {
        return $this->cyleNum;
    }

    /**
     * @return the $callBack
     */
    public function getCallBack ()
    {
        return $this->callBack;
    }

    /**
     * @param number $delayTime
     */
    public function setDelayTime ($delayTime)
    {
        $this->delayTime = $delayTime;
    }

    /**
     * @param number $cyleNum
     */
    public function setCyleNum ($cyleNum)
    {
        $this->cyleNum = $cyleNum;
    }

    /**
     * @param Closure $callBack
     */
    public function setCallBack ($callBack)
    {
        $this->callBack = $callBack;
    }    
}


class DelayMessageQueue{
    
    /**
     * 一个圈大小为3600s的bucket
     * @var integer
     */
    CONST DELAY_BUCKET_SIZE = 3600;
    
    /**
     * 分配的 3600大小的solt容器 二维容器
     * @var array
     */
    public $buckets = []; 
    
    public function __construct(){
        $this->buckets = array_fill(0, self::DELAY_BUCKET_SIZE, []);
    }
    
    /**
     * 新入任务
     */
    public function push(Task $task){
        
       $delayTime =  $task->getDelayTime();
       $cylNum = intval($delayTime/self::DELAY_BUCKET_SIZE);  //第几圈执行 
       
       //第多个槽位 所有任务都是符合计算公式的 考虑越界情况
       $bucketNum = (Timer::$currentPt+$delayTime%self::DELAY_BUCKET_SIZE)%self::DELAY_BUCKET_SIZE;
       
       $task->setCyleNum($cylNum);
       
       array_push($this->buckets[$bucketNum], $task); 
    }
    
    public function pop($key){
        return $this->buckets[$key];
    }
    
}


class Timer{
    
    /**
     * 当时指针
     * @var int
     */
    public static $currentPt = 0;
    
    /**
     * 延时执行任务
     * @param DelayMessageQueue $queue
     * @param number $interval
     */
    public static function setTimeout(DelayMessageQueue $queue,$interval = 1){        
        set_time_limit(0);
        while (true){           
            if(self::$currentPt == DelayMessageQueue::DELAY_BUCKET_SIZE) self::reset(); //重置指针指向 
            echo self::$currentPt;
            $tasks = &$queue->pop(self::$currentPt); 
            if(!empty($tasks)){ //当槽位有任务时---->拉到另外一个线程去做
                foreach ($tasks as $key => $task){                   
                    $cynum = $task->getCyleNum();
                    if($cynum == 0){
                        call_user_func($task->getCallBack());
                        unset($tasks[$key]);
                    }else{
                        $task->setCyleNum(--$cynum);
                    }
                }
            } 
            sleep($interval);//准实时的
            ++self::$currentPt;
        }               
    }
    
    public static function reset(){
        self::$currentPt = 0;
    }
}

$queue = new DelayMessageQueue();
//var_dump($queue);

$task = new Task(3602, function(){echo "helloword";});

$queue->push($task);

var_dump($queue);

//Timer::setTimeout($queue);