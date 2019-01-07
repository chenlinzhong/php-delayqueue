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
 `topic` varchar(255) NOT NULL DEFAULT '',
 `re_notify_flag` varchar(1024) NOT NULL DEFAULT '重试标记',
 `createor` varchar(1024) NOT NULL DEFAULT '',
 `status` tinyint(4) NOT NULL DEFAULT '1',
 `method` varchar(32) NOT NULL DEFAULT 'GET',
 `priority` int(11) NOT NULL DEFAULT '2',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

CREATE TABLE `dq_stat` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `u_key` varchar(255) NOT NULL DEFAULT '',
 `num` int(11) NOT NULL DEFAULT '0',
 `create_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
 `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `u_key` (`u_key`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
