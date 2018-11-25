$(function () {
    function isNull( str ){
        if ( str == "" ) return true;
        var regu = "^[ ]+$";
        var re = new RegExp(regu);
        return re.test(str);
    }


    $('.btn-redis').click(function () {
        var postData={
            't_name':$('#topic_server').find('.t_name').val(),
            't_content':$('#topic_server').find('.t_content').val(),
            'id':$('#task_id').val()
        };
        if(isNull(postData.t_name)){
            alert('名称不能为空');
            return false;
        }

        if(isNull(postData.t_content)){
            alert('redis信息不能空');
            return false;
        }

        var post_url='/add.php?op=redis_add';
        $.ajax({
            'url': post_url,
            'type':'get',
            'data': postData,
            'dataType':'json',
            'success':function(res){
                if(res.code==1){
                    alert('提交成功');
                    window.location.href='/index.php';
                }else{
                    alert('添加失败');
                }
            },
            'error':function(jqXHR, textStatus, errorThrown){ //网络异常中断处理
                //alertbox(0,'网络异常',3000);
            }
        })
        return false;
    });

    $('.op-del').click(function () {
        var post_url = $(this).attr('data-url');
        $.ajax({
            'url': post_url,
            'type':'get',
            'data': {},
            'dataType':'json',
            'success':function(res){
                if(res.code==1){
                    alert('删除成功');
                    window.location.reload();
                }else{
                    alert('添加失败');
                }
            },
            'error':function(jqXHR, textStatus, errorThrown){ //网络异常中断处理
                //alertbox(0,'网络异常',3000);
            }
        })
        return false;
    });

    var is_topic_can_use=1;
    $('#topic_add').find('.topic').keyup(function () {
        var topic=$('#topic_add').find('.topic').val();
        if(isNull(topic)){
            return ;
        }
        var post_url='/check.php';
        var post_data={'topic':topic,'id':$('#task_id').val()};
        $.ajax({
            'url': post_url,
            'type':'get',
            'data': post_data,
            'dataType':'json',
            'success':function(res){
                if(res.code==1){
                    is_topic_can_use = 1;
                    $('#topic-err').hide();
                }else{
                    is_topic_can_use = 0;
                    $('#topic-err').show();
                }
            },
            'error':function(jqXHR, textStatus, errorThrown){ //网络异常中断处理
                //alertbox(0,'网络异常',3000);
            }
        })
    });


    $('.btn-topic').click(function () {
        if(!is_topic_can_use){
            alert('请输入正确topic');
            return false;
        }
        var postData={
            't_name':$('#topic_add').find('.t_name').val(),
            'delay':$('#topic_add').find('.delay').val(),
            'callback':$('#topic_add').find('.callback').val(),
            'timeout':$('#topic_add').find('.timeout').val(),
            'email':$('#topic_add').find('.email').val(),
            'createor':$('#topic_add').find('.createor').val(),
            'topic':$('#topic_add').find('.topic').val(),
            'method':$('.method').val(),
            'id':$('#task_id').val(),
            're_notify_flag':$('.re_notify_flag').val(),
            'priority':$('#priority').val()
        };
        var post_url='/add.php?op=topic_add';
        $.ajax({
            'url': post_url,
            'type':'get',
            'data': postData,
            'dataType':'json',
            'success':function(res){
                if(res.code==1){
                    alert('提交成功');
                    window.location.href='/index.php?tab=topic_list';
                }else{
                    alert('添加失败');
                }
            },
            'error':function(jqXHR, textStatus, errorThrown){ //网络异常中断处理
                //alertbox(0,'网络异常',3000);
            }
        })
        return false;
    });

    $('.nav-tabs a').click(function () {
        var li=$(this).attr('href');
        li = li.substr(1);
        window.location.href='/index.php?tab='+li;
    });


    $('.btn-search').click(function () {
        var s_topic=$('.s_topic').val();
        var s_name=$('.s_name').val();
        window.location.href='/index.php?tab=topic_list&s_topic='+s_topic+'&s_name='+s_name;
    });

    $('.btn-alert').click(function () {
        var postData={
            'host':$('.t_smtp_host').val(),
            'port':$('.t_smtp_port').val(),
            'user':$('.t_smtp_user').val(),
            'pwd':$('.t_smtp_pwd').val(),
            'ext':{
              'redis':$('.t_redis_down').val(),
            },
            'id':parseInt($('#alert-id').val())
        };

        if(isNull(postData.host)){
            alert('smtp服务器不能为空');
            return ;
        }

        if(isNull(postData.port)){
            alert('端口不能为空')
            return;
        }

        if(isNull(postData.user)){
            alert('用户名不能为空');
            return ;
        }

        if(isNull(postData.pwd)){
            alert('密码不能为空');
            return;
        }

        var post_url='/add.php?op=alert';
        $.ajax({
            'url': post_url,
            'type':'get',
            'data': postData,
            'dataType':'json',
            'success':function(res){
                if(res.code==1){
                    alert('提交成功');
                    window.location.href='/index.php?tab=alert';
                }else{
                    alert('添加失败');
                }
            },
            'error':function(jqXHR, textStatus, errorThrown){ //网络异常中断处理
                //alertbox(0,'网络异常',3000);
            }
        })
        return false;
    });


    $('.update-status').click(function () {
        var post_url='/add.php?op=update_status';
        var postData={
            'table':$(this).attr('data-table'),
            'id':$(this).attr('data-id'),
            'status':$(this).attr('data-status')
        };
        $.ajax({
            'url': post_url,
            'type':'get',
            'data': postData,
            'dataType':'json',
            'success':function(res){
                if(res.code==1){
                    alert('提交成功');
                    window.location.href='/index.php?tab=topic_list';
                }else{
                    alert('添加失败');
                }
            },
            'error':function(jqXHR, textStatus, errorThrown){ //网络异常中断处理
                //alertbox(0,'网络异常',3000);
            }
        })
        return false;
    });

})