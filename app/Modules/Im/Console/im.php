<?php

/**
 * Created by PhpStorm.
 * User: kuke
 * Date: 2016/6/7
 * Time: 16:57
 */
class WebsocketServ
{
    //websocket服务
    public $server;
    //服务端ip
    public $ip = '';
    //默认端口
    public $port = '';

    public $host = '';

    public $database = '';

    public $username = '';

    public $password = '';

    public $tablePre = 'kppw_';

    private $map;

    public $memory_table;


    public function __construct()
    {

        //创建连接池内存表
        $this->memory_table = new swoole_table(1024);
        $this->memory_table->column('fd', swoole_table::TYPE_INT, 11);       //1,2,4,8
        $this->memory_table->column('uid', swoole_table::TYPE_INT, 11);       //1,2,4,8
        $this->memory_table->create();


        //数据库相关配置
        $nowPath = dirname(__FILE__);
        $rootPath = substr($nowPath,0,-23);
        $path = $rootPath. DIRECTORY_SEPARATOR . '.env';
        $str = file_get_contents($path);
        $arr = explode("\n",$str);
        if(!empty($arr)){
            foreach($arr as $key =>$value){
                if(!empty($value)){
                    if(strstr($value,'DB_HOST')){
                        $this->host = substr($value,strpos($value,'=')+1);
                    }
                    if(strstr($value,'DB_DATABASE')){
                        $this->database = substr($value,strpos($value,'=')+1);
                    }
                    if(strstr($value,'DB_USERNAME')){
                        $this->username = substr($value,strpos($value,'=')+1);
                    }
                    if(strstr($value,'DB_PASSWORD')){
                        $this->password = substr($value,strpos($value,'=')+1);
                    }
                }
            }
        }

        //查询IM相关配置
        $conn = $this->connectDB();
        $sql = "SELECT * FROM " . $this->tablePre . "config WHERE alias = 'IM_config' AND type = 'basis'";
        $status = mysqli_query($conn, $sql);
        $imConfig = mysqli_fetch_array($status);
        $imRule = json_decode($imConfig['rule'], true);
        $this->ip = $imRule['IM_ip'];
        $this->port = $imRule['IM_port'];
        mysqli_close($conn);
        $this->server = new swoole_websocket_server($this->ip, $this->port);
        $this->server->set(['work_num' => 100]);
        $this->server->on('open', array($this, 'open'));
        $this->server->on('message', array($this, 'message'));
        $this->server->on('close', array($this, 'close'));
        $this->server->start();
    }

    //连接socket
    public function open(swoole_websocket_server $server, $request)
    {
        $param = $request->get;
        $fd = $request->fd;
        //写入连接池
        $this->map[$fd] = $param['fromUid'];
        $this->memory_table->set($fd, array('fd' => $fd, 'uid' => $param['fromUid']));

        foreach($this->memory_table as $fd => $item)
        {
            $map[$fd] = $item['uid'];
        }
        $conn = $this->connectDB();
        $sql = sprintf("SELECT `friend_uid` FROM kppw_im_attention WHERE uid = '" . $param['fromUid'] . "'");
        $result = mysqli_query($conn, $sql);
        $friendArr = array();
        while($item = $result->fetch_assoc()){
            $friendArr[] = $item['friend_uid'];
        }
        $friend['online'] = array();
        foreach ($friendArr as $v){
            if (in_array($v, $this->map)){
                $friend['online'][] = $v;
			}
		}
        $server->push(array_flip($map)[$param['fromUid']], json_encode($friend['online']));
    }

    //message事件
    public function message(swoole_websocket_server $server, $frame)
    {
        //发送消息内容
        $data = json_decode($frame->data);

        //设置时区
        date_default_timezone_set("Asia/Shanghai");
        $conn = $this->connectDB();
        $query = "select name from kppw_users where id=".$data->fromUid;
        $status = mysqli_query($conn,$query);
        $from_username = mysqli_fetch_array($status);
        $msg = [
            'fromUid' => $data->fromUid,
            'toUid' => isset($data->toUid) ? $data->toUid : '',
            'content' => isset($data->content) ? $data->content : '',
            'created_at' => date('Y/m/d H:i:s'),
            'status' => 1,
            'from_username'=>$from_username['name'],
        ];
        //写入im消息记录
        if (!empty($msg['toUid']) && !empty($msg['content'])) {

            foreach($this->memory_table as $fd => $item)
            {
                $map[$fd] = $item['uid'];
            }

            //遍历连接池搜索消息推送线程ID
            foreach ($map as $k => $v) {
                if ($msg['fromUid'] == $v || $msg['toUid'] == $v) {
                    $list[] = $k;
                }
                if ($msg['toUid'] == $v){
                    $msg['status'] = 2;
                }
            }


            $msg['content'] = htmlspecialchars($msg['content']);
            $sql = sprintf(
                "INSERT INTO kppw_im_message (`from_uid`, `to_uid`, `content`, `created_at`, `status`) VALUES ('%d', '%d', '%s', '%s', '%d')",
                $msg['fromUid'], $msg['toUid'], $msg['content'], $msg['created_at'], $msg['status']);
            $status = mysqli_query($conn, $sql);
            $id = mysqli_insert_id($conn);

            if ($status){
                $msg = array_merge(array('id'=>$id),$msg);
                foreach ($list as $fd) {
                    $server->push($fd,json_encode($msg));
                }
            }
            mysqli_close($conn);
        }

    }

    //断开连接
    public function close($ser, $fd)
    {
        //清除连接池
        $this->memory_table->del($fd);

    }

    //连接DB
    public function connectDB()
    {
        $conn = mysqli_connect($this->host, $this->username, $this->password);
        if (!$conn) {
            die('Could not connect:' . mysqli_error($conn));
        }
        $db = mysqli_select_db($conn,$this->database);
        if (!$db) {
            die("sql error:" . mysqli_error($conn));
        }
        return $conn;
    }
}

//实例化websocket并启动服务
$websocket = new WebsocketServ();

