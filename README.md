基于workerMan的定时任务系统,
=================
本项目基于workerMan,与需要执行定时任务的后台部署到相同服务器.  
需要占用8181,8182,1238端口,部署时需修改Events.php中的Redis配置,并将Redis做持久化设置,防止任务丢失   
传递参数中不可出现`--`两个横连续的情况  

传过来什么值就返回什么值!!!!     传的所有值都会返回去    自己定义好回调地址即可


使用方法:
![Image text](https://raw.githubusercontent.com/cuozisun/img-folder/master/1566529238.jpg)
```php

public function timedTaskTestPost()
{
    $array['start_time'] = '2019-08-23 11:11:00';//必须
    $array['url']           = '/api/Order/timedTaskTestResult';//必须
    $array['url']           = 'www.baidu.com';//必须
    $array['id']           = '333';//必须用于后续修改定时任务,如果定时任务中已存在相同id,则删除原来任务,创建新任务
    $array['data']          = '1';//随意
    $array['delete']          = '1';//取消任务时传值,创建是传该值则丢掉该信息
    //调用soket给我的程序发送参数
    $this->soket($array);
}


//模拟soket请求
function soket($data=''){
    //创建socket
    $data = json_encode($data);
 	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
 	$result = socket_connect($socket, '127.0.0.1', 8181);
    $in = $data;
    $out = '';
    if(!socket_write($socket, $in, strlen($in))) {
        socket_close($socket);
    }else {
        socket_close($socket);
    }       
}
```
GatewayWorker Linux 版本
======================
Linux 版本GatewayWorker 在这里 https://github.com/walkor/GatewayWorker

启动
=======
双击start_for_win.bat

Applications\YourApp测试方法
======
使用telnet命令测试（不要使用windows自带的telnet）
```shell
 telnet 127.0.0.1 8282
Trying 127.0.0.1...
Connected to 127.0.0.1.
Escape character is '^]'.
Hello 3
3 login
haha
3 said haha
```

手册
=======
http://www.workerman.net/gatewaydoc/

使用GatewayWorker-for-win开发的项目
=======
## [tadpole](http://kedou.workerman.net/)  
[Live demo](http://kedou.workerman.net/)  
[Source code](https://github.com/walkor/workerman)  
![workerman-todpole](http://www.workerman.net/img/workerman-todpole.png)   

## [chat room](http://chat.workerman.net/)  
[Live demo](http://chat.workerman.net/)  
[Source code](https://github.com/walkor/workerman-chat)  
![workerman-chat](http://www.workerman.net/img/workerman-chat.png)  
