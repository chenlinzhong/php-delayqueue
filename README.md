
延迟队列，顾名思义它是一种带有延迟功能的消息队列。 那么，是在什么场景下我才需要这样的队列呢？

### 一、背景
先看看一下业务场景：
* 1.会员过期前3天发送召回通知
* 2.订单支付成功后，5分钟后检测下游环节是否都正常，比如用户购买会员后，各种会员状态是否都设置成功
* 3.如何定期检查处于退款状态的订单是否已经退款成功？
* 4.实现通知失败，1，3，5，7分钟重复通知，直到对方回复？


通常解决以上问题，最简单直接的办法就是定时去扫表。

扫表存在的问题是：

* 1.扫表与数据库长时间连接，在数量量大的情况容易出现连接异常中断，需要更多的异常处理，对程序健壮性要求高
* 2.在数据量大的情况下延时较高，规定内处理不完，影响业务，虽然可以启动多个进程来处理，这样会带来额外的维护成本，不能从根本上解决。
* 3.每个业务都要维护一个自己的扫表逻辑。 当业务越来越多时，发现扫表部分的逻辑会重复开发，但是非常类似

延时队列能对于上述需求能很好的解决

### 二、调研
调研了市场上一些开源的方案，以下：
* 1.有赞科技：只有原理，没有开源代码
* 2.github个人的：https://github.com/ouqiang/delay-queue

      1.基于redis实现，redis只能配置一个,如果redis挂了整个服务不可用，可用性差点
      2.消费端实现的是拉模式，接入成本大，每个项目都得去实现一遍接入代码
      3.在star使用的人数不多，放在生产环境，存在风险，加之对go语言不了解，出了问题难以维护

* 3.SchedulerX-阿里开源的： 功能很强大，但是运维复杂，依赖组件多，不够轻量
* 4.RabbitMQ-延时任务:  本身没有延时功能，需要借助一特性自己实现，而且公司没有部署这个队列，去单独部署一个这个来做延时队列成本有点高，而且还需要专门的运维来维护，目前团队不支持

基本以上原因打算自己写一个，平常使用php多，项目基本redis的zset结构作为存储，用php语言实现 ，实现原理参考了有赞团队：https://tech.youzan.com/queuing_delay/

整个延迟队列主要由4个部分

  * JobPool用来存放所有Job的元信息。
  * DelayBucket是一组以时间为维度的有序队列，用来存放所有需要延迟的Job（这里只存放Job Id）。
  * Timer负责实时扫描各个Bucket，并将delay时间大于等于当前时间的Job放入到对应的Ready Queue。
  * ReadyQueue存放处于Ready状态的Job（这里只存放JobId），以供消费程序消费。

![image](./images/delaythird.png)

消息结构
每个Job必须包含一下几个属性：

  1. topic：Job类型。可以理解成具体的业务名称。
  2. id：Job的唯一标识。用来检索和删除指定的Job信息。
  3. delayTime：jod延迟执行的时间，13位时间戳
  4. ttr（time-to-run)：Job执行超时时间。
  5. body：Job的内容，供消费者做具体的业务处理，以json格式存储。


对于同一类的topic delaytime,ttr一般是固定，job可以在精简一下属性

  1.topic：Job类型。可以理解成具体的业务名称
  
  2.id：Job的唯一标识。用来检索和删除指定的Job信息。
  
  3.body：Job的内容，供消费者做具体的业务处理，以json格式存储。

delaytime,ttr在topicadmin后台配置



### 三、目标

* 轻量级：有较少的php的拓展就能直接运行，不需要引入网络框架，比如swoole，workman之类的
* 稳定性：采用master-work架构，master不做业务处理，只负责管理子进程，子进程异常退出时自动拉起
* 可用性：
     * 1.支持多实例部署，每个实例无状态，一个实例挂掉不影响服务
     * 2.支持配置多个redis，一个redis挂了只影响部分消息
     * 3.业务方接入方便，在后台只需填写相关消息类型和回掉接口
* 拓展性:  当消费进程存在瓶颈时，可以配置加大消费进程数，当写入存在瓶颈时，可增加实例数写入性能可线性提高
* 实时性：允许存在一定的时间误差。
* 支持消息删除：业务使用方，可以随时删除指定消息。
* 消息传输可靠性：消息进入到延迟队列后，保证至少被消费一次。
* 写入性能：qps>1000+

#### 四、架构设计与说明

总体架构
![image](./images/jiagou.png)
采用master-work架构模式，主要包括6个模块：

* 1.dq-mster:  主进程，负责管理子进程的创建，销毁，回收以及信号通知
* 2.dq-server: 负责消息写入，读取，删除功能以及维护redis连接池
* 3.dq-timer-N: 负责从redis的zset结构中扫描到期的消息，并负责写入ready 队列，个数可配置，一般2个就行了，因为消息在zset结构是按时间有序的
* 4.dq-consume-N: 负责从ready队列中读取消息并通知给对应回掉接口，个数可配置
* 5.dq-redis-checker: 负责检查redis的服务状态，如果redis宕机，发送告警邮件
* 6.dq-http-server: 提供web后台界面，用于注册topic

### 五、模块时序图
消息写入:

![image](./images/xieru.png)

timer查找到期消息:

![image](./images/scan.png)

consumer消费流程:

![image](./images/xiaofei.png)

#### 六、部署

环境依赖：`PHP 5.4+  安装sockets，redis，pcntl,pdo_mysql 拓展`

###### step1:安装数据库用于存储一些topic以及告警信息

```
create database dq;
#存放告警信息
CREATE TABLE `dq_alert` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `host` varchar(255) NOT NULL DEFAULT '',
  `port` int(11) NOT NULL DEFAULT '0',
  `user` varchar(255) NOT NULL DEFAULT '',
  `pwd` varchar(255) NOT NULL DEFAULT '',
  `ext` varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
#存放redis信息
CREATE TABLE `dq_redis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `t_name` varchar(200) NOT NULL DEFAULT '',
  `t_content` varchar(2048) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;
#存储注册信息
CREATE TABLE `dq_topic` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `t_name` varchar(1024) NOT NULL DEFAULT '',
  `delay` int(11) NOT NULL DEFAULT '0',
  `callback` varchar(1024) NOT NULL DEFAULT '',
  `timeout` int(11) NOT NULL DEFAULT '3000',
  `email` varchar(1024) NOT NULL DEFAULT '',
  `re_notify_flag` varchar(1024) NOT NULL DEFAULT '',
  `topic` varchar(255) NOT NULL DEFAULT '',
  `createor` varchar(1024) NOT NULL DEFAULT '',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `method` varchar(32) NOT NULL DEFAULT 'GET',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;
```

###### step2:在DqConfg.文件中配置数据库信息： DqConf::$db

###### step3: 启动http服务
在DqConf.php文件中修改php了路径 $logPath

命令：
> php DqHttpServer.php --port 8088

访问:http://127.0.0.1:8088,出现配置界面
![image](./images/index.png)

redis信息格式：host:post:auth 比如 127.0.0.1:6379:12345

###### stop4:启动服务进程:   
> php DqInit.php --port 6789 
看到如下信息说明启动成功
![image](./images/list.png)

###### stop5:配置告信息(比如redis宕机)
![image](./images/warning.png)

###### stop6:注册topic
![image](./images/topic.png)

![image](./images/topiclist.png)

###### step7: 写入数据，在项目根目录下新建test.php文件写入
```
<?php
include_once 'DqLoader.php';
date_default_timezone_set("PRC");
//可配置多个
$server=array(
    '127.0.0.1:6789',
);
$dqClient = new DqClient();
$dqClient->addServer($server);

$topic ='order_openvip_checker'; //topic在后台注册
$id = uniqid();
$data=array(
    'id'=>$id,
    'body'=>array(
        'a'=>1,
        'b'=>2,
        'c'=>3,
        'ext'=>str_repeat('a',64),
    ),
    //可选，设置后以这个通知时间为准，默认延时时间在注册topic的时候指定
    'fix_time'=>date('Y-m-d 23:50:50'),
);
$time=msectime();
//添加
$boolRet = $dqClient->add($topic, $data);
echo 'add耗时:'.(msectime() - $time)."ms\n";
//查询
$time = msectime();
$result = $dqClient->get($topic, $id);
echo 'get耗时:'.(msectime() - $time)."ms\n";

//删除
$time = msectime();
$boolRet = $dqClient->del($topic,$id);
echo 'del耗时:'.(msectime() - $time)."ms\n";

function msectime() {
    list($msec, $sec) = explode(' ', microtime());
    $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}


```
执行php test.php

###### step8:查看日志
默认日志目录在项目目录的logs目录下，在DqConf.php修改$logPath

* 1.请求日志：request_ymd.txt
* 2.通知日志：notify_ymd.txt 
* 3.错误日志：err_ymd.txt

###### step9:如果配置文件有改动

* 1.系统会自动检测配置文件新，如果有改动，会自动退出(没有找到较好的热更新的方案)，需要重启，可以在crontab里面建个任务,1分钟执行一次，程序有check_self的判断
* 2.优雅退出命令:  master检测侦听了USR2信号，收到信号后会通知所有子进程，子进程完成当前任务后会自动退出

> ps -ef | grep dq-master| grep -v grep | head -n 1 | awk '{print $2}' | xargs kill -USR2



### 七、性能测试
需要安装pthreads拓展：

测试原理：使用多线程模拟并发，在1s内能成功返回请求成功的个数

```
php DqBench  concurrency  requests
concurrency:并发数
requests： 每个并发产生的请求数

测试环境：内存 8G ，8核cpu，2个redis和1个dq-server 部署在一个机器上，数据包64字节
qps：2400
```

### 八、值得一提的性能优化点：
* 1.redis multi命令：将多个对redis的操作打包成一个减少网络开销
* 2.计数的操作异步处理，在异步逻辑里面用函数的static变量来保存，当写入redis成功后释放static变量，可以在redis出现异常时计数仍能保持一致，除非进程退出
* 3.内存泄露检测有必要:  所有的内存分配在底层都是调用了brk或者mmap，只要程序只有大量brk或者mmap的系统调用，内存泄露可能性非常高 ,检测命令: strace -c -p pid | grep  'mmap| brk'
* 4.检测程序的系统调用情况：strace -c -p pid  ，发现某个系统函数调用是其他的数倍，可能大概率程序存在问题

### 九、异常处理

如果调用通知接口在超时时间内，没有收到回复认为通知失败，系统会重新把数据放入队列，重新通知，系统默认最大通知10次(可以在Dqconf.php文件中修改$notify_exp_nums)通知间隔为2n+1，比如第一次1分钟，通知失败，第二次3分钟后，直到收到回复，超出最大通知次数后系统自动丢弃，同时发邮件通知

`ps:网络抖动在所难免，通知接口如果涉及到核心的服务,一定要保证幂等！！`

redis宕机通知:

![image](./images/redisdown.png)



### 十、线上情况

线上部署了两个实例每个机房部一个，4个redis共16G内存作存储，服务稳定运行数月，各项指标均符合预期

主要接入业务:

* 订单10分钟召回通知
* 调用接口超时或者失败时做补偿
* 会员过期前3天召回通知


### 十一、参考

https://www.cnblogs.com/peachyy/p/7398430.html

https://tech.youzan.com/queuing_delay/

http://www.runoob.com/bootstrap/bootstrap-tutorial.html



bug、修改建议、疑惑都欢迎提在issue中，或加本人qq：490103404

















