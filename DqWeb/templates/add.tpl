<!DOCTYPE html>
<html style="height:100%;">
<head>
    <meta charset="utf-8">
    <title>延迟队列配置系统</title>
    <link rel="stylesheet" href="/bootstrap.min.css">
    <script src="/jquery.min.js"></script>
    <script src="/bootstrap.min.js"></script>
    <script src="/dq.js"></script>
</head>
<body style="min-height:100%;margin:0;padding:0;position:relative;">
<div style="height: 40px; width: 100%;background-color: #337ab7;color: white;padding-top: 8px;font-size: 18px;font-weight: bold;padding-left: 20px">延时队列配置系统</div>
<input type="hidden" value="{$get['id']}" id="task_id">
<ul id="myTab" class="nav nav-tabs">
    <li  class="{if $tab =='topic_server' || empty($tab) }active{/if}"  >
        <a href="#topic_server" data-toggle="tab">
            添加redis
        </a>
    </li>
    <li  class="{if $tab =='topic_add' }active{/if}"  >
        <a href="#topic_add" data-toggle="tab">
            新增topic
        </a>
    </li>
    <li class="{if $tab =='topic_list' } active{/if}"  ><a href="#topic_list" data-toggle="tab">topic列表</a></li>
    <li class="{if $tab =='alert' } active{/if}"  ><a href="#alert" data-toggle="tab">告警设置</a></li>
</ul>

<div id="myTabContent" class="tab-content">
    <div class="tab-pane fade in {if $tab =='topic_server' || empty($tab) }active{/if}" id="topic_server">
        <form class="form-horizontal" role="form" style="margin-top: 30px">
            <div class="form-group">
                <label for="firstname" class="col-sm-2 control-label">名称</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_name"
                           placeholder="请输入名称 " style="width: 200px" value="{$get['t_name']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label">redis信息</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_content" id="lastname"
                           placeholder="请输入redis信息" style="width: 400px" value="{$get['t_content']}">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button type="submit" class="btn btn-default btn-redis">提交</button>
                </div>
            </div>
        </form>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th>名称</th>
                <th>配置</th>
                <th>状态</th>
                <th>版本</th>
                <th>内存使用</th>
                <th>总写入</th>
                <th>总消费</th>
                <th>总删除</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            {foreach $redis_list as $redis}
            <tr>
                <td>{$redis['t_name']}</td>
                <td>{$redis['t_content']}</td>
                <td>{$redis['online_desc']}</td>
                <td>{$redis['redis_version']}</td>
                <td>{$redis['used_memory_human']}</td>
                <td>{$redis['total_nums']|default:0}</td>
                <td>{$redis['notify_nums']|default:0}</td>
                <td>{$redis['total_del']|default:0}</td>
                <td><a href="/index.php?op=redis_add&id={$redis['id']}&{http_build_query($redis)}">修改</a>|<a href="#" class="op-del"  data-url="/add.php?op=del&table=dq_redis&id={$redis['id']}">删除</a></td>
            </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
    <div class="tab-pane fade in {if $tab =='topic_add' }active{/if}" id="topic_add">
        <form class="form-horizontal" role="form" style="margin-top: 30px">
            <div class="form-group">
                <label for="firstname" class="col-sm-2 control-label">名称</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_name" id="firstname"
                           placeholder="请输入名称 " style="width: 200px" value="{$get['t_name']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label">topic</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control topic" id="lastname"
                           placeholder="请输入topic" style="width: 200px" value="{$get['topic']}">
                    <span style="color: red;display: none" id="topic-err">topic已存在请重新输入</span>
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">延迟时间:(单位秒)</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control delay" id="lastname"
                           placeholder="请输入延迟时间" style="width: 200px" value="{$get['delay']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">回调通知url</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control callback" id="lastname"
                           placeholder="请输入地址" style="width: 200px" value="{$get['callback']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">请求方式</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control method" id="lastname"
                           placeholder="请输入请求方式GET|POST" style="width: 200px" value="{$get['method']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">重试标记</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control re_notify_flag" id="lastname"
                           placeholder="" style="width: 200px" value="{$get['re_notify_flag']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">超时时间</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control timeout" id="lastname"
                           placeholder="请输入超时时间" style="width: 200px" value="{$get['timeout']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">优先级</label>
                <div class="col-sm-10">
                    <select id="priority">
                        <option value="1"  {if $get['priority'] =='1' }selected = "selected"{/if}>高</option>
                        <option value="2" {if $get['priority'] =='2' }selected = "selected"{/if}>中</option>
                        <option value="3" {if $get['priority'] =='3' }selected = "selected"{/if}>低</option>
                    </select>
                    备注：优先级越高越快被消费
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">异常通知</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control email" id="lastname"
                           placeholder="请输入邮箱地址" style="width: 200px" value="{$get['email']}">
                    备注:(回调接口出现问题，dns解析超时,接口超时，302错误邮件通知)
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label  ">创建人</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control createor" id="lastname"
                           placeholder="请输入创建人" style="width: 200px" value="{$get['createor']}">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button type="submit" class="btn btn-default btn-topic">提交</button>
                </div>
            </div>
        </form>
    </div>
    <div class="tab-pane fade in {if $tab =='topic_list' } active{/if}" id="topic_list">

        <table class="table table-bordered">
            <caption>
                <div class="col-sm-10">
                    <input type="text" class="form-control s_topic"
                           placeholder="请输入topic查找 " style="width: 200px" value="{$get['s_topic']}"><br>
                    <input type="text" class="form-control s_name"
                           placeholder="请输入名称查找 " style="width: 200px" value="{$get['s_name']}">
                    <br>
                    <button type="submit" class="btn btn-default btn-search" >查找</button>
                </div>
            </caption>
            <thead>
            <tr>
                <th>编号</th>
                <th>名称</th>
                <th>topic</th>
                <th>回调地址</th>
                <th>超时时间</th>
                <th>延迟时间(单位:s)</th>
                <th>异常通知地址</th>
                <th>优先级</th>
                <th>总写入</th>
                <th>总删除</th>
                <th>总消费</th>
                <th>今日写入</th>
                <th>今日删除</th>
                <th>今日消费</th>
                <th>今日异常</th>
                <th>状态</th>
                <th>创建人</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            {foreach $topic_list as $topic}
            <tr>
                <td>{$topic['id']}</td>
                <td>{$topic['t_name']}</td>
                <td>{$topic['topic']}</td>
                <td>{$topic['callback']}</td>
                <td>{$topic['timeout']}</td>
                <td>{$topic['delay']}</td>
                <td>{$topic['email']|default:'-'}</td>
                <td>{$topic['priority_name']|default:'-'}</td>
                <td>{$topic['total_write']|default:0}</td>
                <td>{$topic['total_del']|default:0}</td>
                <td>{$topic['total_notfiy']|default:0}</td>
                <td>{$topic['today_write']|default:0}</td>
                <td>{$topic['today_del']|default:0}</td>
                <td>{$topic['today_notify']|default:0}</td>
                <td>{$topic['today_exp']|default:0}</td>
                <td>{$topic['status_desc']}</td>
                <td>{$topic['createor']|default:0}</td>
                <td>
                    <a href="/index.php?tab=topic_add&{http_build_query($topic)}">修改</a>|
                    <a href="#" class="op-del"  data-url="/add.php?op=del&table=dq_topic&id={$topic['id']}">删除</a>
                    <a href="#" class="update-status" data-status="{$topic['online_status']}" data-id="{$topic['id']}" data-table="dq_topic">{$topic['online']}</a>
                </td>
            </tr>
            {/foreach}
            </tbody>
        </table>
        <ul class="pagination">
            <li><a href="#">&laquo;</a></li>
            {for $i=1 to $pages}
                <li class="{if $i==$page}active{/if}"><a href="/index.php?tab=topic_list&page={$i}">{$i}</a></li>
            {/for}
            <li><a href="#">&raquo;</a></li>
        </ul>
    </div>
    <div class="tab-pane fade in {if $tab =='alert' }active{/if}" id="alert">
        <div class="form-horizontal" role="form" style="margin-top: 30px">
            <input type="hidden" id="alert-id" value="{$alert['id']}">
            <div class="form-group">
                <label for="firstname" class="col-sm-2 control-label">SMTP服务器</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_smtp_host"
                           placeholder="输入smtp服务器地址" style="width: 200px" value="{$alert['host']}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label">端口</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_smtp_port"
                           placeholder="输入smtp服务器端口" style="width: 200px" value="{{$alert['port']}}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">用户名</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_smtp_user" id="lastname"
                           placeholder="输入用户名" style="width: 200px" value="{{$alert['user']}}">
                </div>
            </div>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">密码</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_smtp_pwd"
                           placeholder="输入密码" style="width: 200px" value="{{$alert['pwd']}}">
                </div>
            </div>
            <div style="width:100%;height:0px;border-top:1px black dashed;"></div>
            <br>
            <div class="form-group">
                <label for="lastname" class="col-sm-2 control-label ">redis宕机通知</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control t_redis_down" id="lastname"
                           placeholder="输入通知邮件列表逗号','分隔" style="width: 300px" value="{$alert['ext']['redis']}">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button type="submit" class="btn btn-default btn-alert">保存</button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>
<br><br>
<div style="background-color: gray;width: 100%;height: 40px;position:absolute;bottom:0;color: white;padding-top: 10px;float: right;padding-left: 20px">
    <a href="https://github.com/chenlinzhong/php-delayqueue" style="color: white">Github</a> | designed 2018.09
</div>
</body>
</html>