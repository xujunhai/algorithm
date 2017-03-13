<?php

/**
 * 
 * 递归和非递归
 * 
 * 典型的尾递归 通过简单的循环
 * 
 */

$fibonacci = [1,1,2,3,5,8,13];

function Fibonacci($n){
    
    if($n <= 2){
        return 1;
    }
    
    return Fibonacci($n-1)+Fibonacci($n-2);   
}

echo Fibonacci(3);

/**
 * 非递归
 * @param unknown $n
 */
function Fibonacci2($n){
    
    //可以理解为3个指针移动
    $result = $nextRes = 1; //前2个的位置的值
    $prevRes = null; //prevRes -2位置的值 nextRes -1位置的值 result当前结果  初始化的时候-2位置没有为null
 
    while ($n > 2){  //类似冒泡  有多少趟循环
        
        $prevRes = $nextRes; //-2位置的值就变为啦 -1位置的值
        $nextRes = $result;  //-1位置的值就变为啦 当前结果
        
        $result = $prevRes + $nextRes;
        $n--;
    }
    
    return $result;
}

echo Fibonacci2(7);