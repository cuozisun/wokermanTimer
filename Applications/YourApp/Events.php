<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \Workerman\Connection\AsyncTcpConnection;
use \Workerman\Lib\Timer;
use \Workerman\Worker;

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    public static $time = 0;
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // $is_online = Gateway::isOnline($client_id);
        echo $client_id;
        // echo 1;
    }

    public static function onWorkerStop()
    {
        global $Redis;
        $Redis->set('time', 1);
        echo 'close0';
    }

    public static function onWorkerStart()
    {
        global $Redis;
        $Redis = new \Redis();
        $Redis->connect('127.0.0.1', 6379);
        $Redis->auth('Baoma88.');
        $Redis->select(0); //选择数据库1
        $time = $Redis->get('time');
        //防止每个生命生命周期都被触发
        if (!$time || ($time && $time == 1)) {
            if (!$time) {
                $Redis->set('time', 1);
            }
            $time = $time + 1;
            $Redis->set('time', $time);
            $result = $Redis->keys('*'); //取出所有的键名
            foreach ($result as $key => $value) {
                if ($value != 'time') {
                    $str = $Redis->get($value);
                    //分割出数据
                    $message = explode('--', $str)[1];
                    $array   = json_decode($message,true);
                    //添加新的事件
                    $timer_id = self::addTimer($array);
                    if($timer_id){
                        $Redis->del($array['id']);
                        $str      = $timer_id . '--' . $message;
                        $Redis->set($array['id'], $str);
                    }
                }
            }
        }
    }

    public static function addTimer($array)
    {
        global $Redis;
        $time_interval = strtotime($array['start_time']) - time();
        if($time_interval < 0 ){
            if($Redis->get($array['id'])){
                $Redis->del($array['id']);
            }
            return false;
        }
        // 给connection对象临时添加一个timer_id属性保存定时器id
        $timer_id = Timer::add($time_interval, function () use ($array,$Redis) {

            self::http_post($array['host'],$array['url'], $array);
            $Redis->del($array['id']);
        }, [], false);
        // echo '任务创建成功\n';
        return $timer_id;

    }
    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        global $Redis;
        $array = json_decode($message, true);
        if ($array) {
            $key   = $array['id'];
            $value = $Redis->get($key);
            if ($value) {
                //如果有则删掉原来的定时任务和redis,重新添加任务
                $timer_id = explode('--', $value)[0];
                Timer::del($value);
                $Redis->del($key);
                // echo '任务取消成功\n';
            }
            if(isset($array['delete'])){
                return false;
            }
            $timer_id = self::addTimer($array);
            if($timer_id){
                $str      = $timer_id . '--' . $message;
                $Redis->set($key, $str);
            }
        }
    }
    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // 向所有人发送
        // GateWay::sendToAll("$client_id logout\r\n");
    }

    /**
     * 模拟post异步请求可进行回调
     * @param array $post_data
     */
    public static function http_post($param = [])
    {
        if(count($param) == 0){
            return false
        }  
        $query = isset($param) ? http_build_query($param) : '';
        // $task_connection->connect();
        $connection_to_baidu = new AsyncTcpConnection('tcp://'.$param['host'].':80');
        // 当连接建立成功时，发送http请求数据
        $connection_to_baidu->onConnect = function ($connection_to_baidu) use ($param, $query) {
            // echo '连接成功';
            $connection_to_baidu->send("POST " . $param['url'] . " HTTP/1.1\r\nHost: edu.llhlec.cn\r\n" . "content-length:" . strlen($query) . "\r\ncontent-type:application/x-www-form-urlencoded\r\nConnection: keep-alive\r\n\r\n" . $query);
        };
        $connection_to_baidu->onMessage = function ($connection_to_baidu, $http_buffer) {
            if (empty($http_buffer)) {return false;}
            $hunks = explode("\r\n\r\n", trim($http_buffer));
            if (!is_array($hunks) or count($hunks) < 2) {
                return false;
            }
            $header  = $hunks[count($hunks) - 2];
            $body    = $hunks[count($hunks) - 1];
            $headers = explode("\n", $header);
            unset($hunks);
            unset($header);
            if (in_array('Transfer-Encoding: chunked', $headers)) {
                return trim(unchunkHttpResponse($body));
            } else {
                $result = preg_split('/[;\r\n]+/s', $body);
                // $result = $result[1];
                // echo $result;
            }
        };
        $connection_to_baidu->onClose = function ($connection_to_baidu) {
            // echo "connection closed\n";
        };
        $connection_to_baidu->onError = function ($connection_to_baidu, $code, $msg) {
            // echo "Error code:$code msg:$msg\n";
        };
        $connection_to_baidu->connect();
    }
}
