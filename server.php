<?php
// start begin load or onWorkstart load
date_default_timezone_set('PRC');
error_reporting(E_ALL);         //E_ERROR | E_WARNING | E_PARSE
set_time_limit(0);
if (!class_exists("swoole_server")) {
    dl("swoole1.8.5.so");
}
include_once "lib/log.php";
class Util
{

    static function num2UInt16Str($num)
    {
        $str = "";
        $bytes = 16 / 8;
        for ($i = $bytes; $i > 0; $i--) {
            $val = $i <= 1 ? floor($num % (16 * 16)) : floor($num / pow(16 * 16, $i - 1));
            $str .= pack("C", $val);
        }
        return $str;
    }

    static function num2UInt32Str($num)
    {
        $str = "";
        $bytes = 32 / 8;
        for ($i = $bytes; $i > 0; $i--) {
            $val = $i <= 1 ? floor($num % (16 * 16)) : floor($num / pow(16 * 16, $i - 1));
            $str .= pack("C", $val);
        }
        return $str;
    }

    static function UInt32Binary2Int($binArray)
    {
        $a = dechex($binArray[0]);
        $b = dechex($binArray[1]);
        $c = dechex($binArray[2]);
        $d = dechex($binArray[3]);
        $dec = sprintf("%02s%02s%02s%02s", $a, $b, $c, $d);
        return hexdec($dec);
    }

    static function UInt32Binary2Int_($binArray)
    {
        return $binArray[0] * 16 * 16 * 16 + $binArray[1] * 16 * 16 + $binArray[2] * 16 + $binArray[3];
    }

    static function UInt16Binary2Int($binArray)
    {
        return $binArray[2] * 16 + $binArray[3];
    }

    static function dumpBinary($binArray, $isHex = false)
    {
        if ($isHex) {
            foreach ($binArray AS $key => $val) {
                $binArray[$key] = dechex($val);
            }
        }
        return ($isHex ? "[HEX]" : "[BIN]") . implode(" ", $binArray);
    }

    static function utf8substr($str, $start, $len)
    {
        $res = "";
        $strlen = $start + $len;
        for ($i = 0; $i < $strlen; $i++) {
            if (ord(substr($str, $i, 1)) > 127) {
                $res .= substr($str, $i, 3);
                $i += 2;
            } else {
                $res .= substr($str, $i, 1);
            }
        }
        return $res;
    }

    static function dump($data)
    {
        $data = is_array($data) ? json_encode($data) : strval($data);
        echo "[" . date("m-d H:i:s") . "] $data\n";
    }

    /**
     * post|get to url
     * @param $url
     * @param null $data
     * @param int $timeout
     * @param int $agent
     * @param null $cookie
     * @return string
     */
    static function urlReq($url, $data = null, $timeout = 10, $agent = 0, $cookie = null)
    {
        if ($agent && is_int($agent)) {
            $user_agent = ini_get('user_agent');
            ini_set('user_agent', 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727;)');
        } elseif ($agent && is_array($agent)) {
            $user_agent = ini_get('user_agent');
            ini_set('user_agent', $agent[array_rand($agent)]);
        } elseif (is_string($agent)) {
            $user_agent = ini_get('user_agent');
            ini_set('user_agent', $agent);
        } else {
            $user_agent = false;
        }
        $context['http']['method'] = $data && is_array($data) ? 'POST' : 'GET';
        $context['http']['header'] = $data && is_array($data) ? "Content-Type: application/x-www-form-urlencoded; charset=utf-8" : "Content-Type: text/html; charset=utf-8";
        $context['http']['timeout'] = $timeout;
        if ($context['http']['method'] == 'POST') {
            if ($cookie && is_string($cookie)) {
                $context['http']['header'] .= PHP_EOL . $cookie;
            }
            if (strpos($url, 'https://') === 0 && isset($data['https_user']) && isset($data['https_password'])) {
                $context['http']['header'] .= PHP_EOL . "Authorization: Basic " . base64_encode($data['https_user'] . ":" . $data['https_password']);
                unset($data['https_user']);
                unset($data['https_password']);
            }
            $context['http']['content'] = http_build_query($data, '', '&');
        }
        $res = file_get_contents($url, false, stream_context_create($context));
        if ($user_agent !== false) ini_set('user_agent', $user_agent);
        return $res;
    }

    static function str_is_path($filename, $is_hard_path = true)
    {
        $tmpname = strtolower($filename);
        $tmparray = ['://', "\0"];
        if ($is_hard_path) $tmparray[] = '..';
        if (str_replace($tmparray, '', $tmpname) != $tmpname) {
            return false;
        }
        return true;
    }

    static function dir_mk($dir, $mode = 0777)
    {
        if (is_dir($dir) || @mkdir($dir, $mode)) return true;
        if (!dir_mk(dirname($dir), $mode)) return false;
        return @mkdir($dir, $mode);
    }

    static function file_write($filename, $data, $method = 'rb+', $is_lock = true, $is_hard_path = true, $is_chmod = true)
    {
        if (!str_is_path($filename, $is_hard_path)) return false;
        !is_dir(dirname($filename)) && dir_mk(dirname($filename));
        touch($filename);
        $handle = fopen($filename, $method);
        $is_lock && flock($handle, LOCK_EX);
        $is_writen = fwrite($handle, $data);
        $method == 'rb+' && ftruncate($handle, strlen($data));
        fclose($handle);
        $is_chmod && @chmod($filename, 0777);
        return $is_writen;
    }

    static function dateid($date = '')
    {
        if (!$date || !strtotime($date)) return intval(date("Ymd"));
        return intval(str_replace('-', '', substr_replace($date, '', 10)));
    }

}

// 压缩数据包
function netpack($opcode, $payload, &$length = 0)
{
    $msg = is_array($payload) ? \json_encode($payload) : $payload;
    $length = strlen($msg) + 8;
    $len = Util::num2UInt32Str($length);
    $opcode = Util::num2UInt32Str(intval($opcode));
    $pack = $len . $opcode . $msg;
    return $pack;
}

// 接包
function netunpack($package)
{
    $len = Util::UInt32Binary2Int(array_values(unpack("C*", substr($package, 0, 4))));
    $opcode = Util::UInt32Binary2Int(array_values(unpack("C*", substr($package, 4, 4))));
    $payload = substr($package, 8);

    return [$len, $opcode, $payload];
}

 
class StartServer
{

    /**
     * @var gamer
     */
    public $gamer = null;

    private $ss = null;

    public function start()
    {
        _LOG("swoole start ");
        $this->ss = new swoole_server("0.0.0.0", PORT, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
		 
        $this->ss->set([
            'timeout'                  => 3,      // 设置select/epoll溢出时间
            'open_cpu_affinity'        => true,    // 启用CPU亲和设置
            'open_tcp_nodelay'         => true,    // 启用TCP即时发送
            'socket_buffer_size'       => 1024 * 1024,
            'enable_unsafe_event'      => 1,
            'max_conn'                 => 10240,   // 最大连接数
            'backlog'                  => 1024,    // 最大排队数
            'worker_num'               => 3,      // 工作进程数
            'task_worker_num'          => 2,       // 任务进程数
            'max_request'              => 500,    // 进程回收数
            'dispatch_mode'            => 2,       // FD分配模式
            'log_file'                 => LOGDIR . 'swoole-log.txt',
            'heartbeat_check_interval' => 1000,
            'heartbeat_idle_time'      => 10000,
            'open_length_check'        => true,       // 开启
            'package_length_type'      => 'N',
            'package_length_offset'    => 0,
            'package_body_offset'      => 0,
            'package_max_length'       => 1024 * 1024 * 50,//30M
        ]);

        $this->ss->on('Start', [$this, 'onServerStart']);
        $this->ss->on('Shutdown', [$this, 'onServerClose']);
        $this->ss->on('ManagerStart', [$this, 'onManageStart']);
        $this->ss->on('ManagerStop', [$this, 'onManageClose']);
        $this->ss->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->ss->on('WorkerStop', [$this, 'onWorkerClose']);
        $this->ss->on('WorkerError', [$this, 'onWorkerError']);
        //$this->ss->on('pipeMessage', array($this, 'onPipeMessage'));
        //$this->ss->on('Timer', array($this, 'onTimeRun'));
        $this->ss->on('Task', [$this, 'onTaskRun']);
        $this->ss->on('Finish', [$this, 'onTaskEnd']);
        $this->ss->on('Connect', [$this, 'onFdStart']);
        $this->ss->on('Close', [$this, 'onFdClose']);
        $this->ss->on('Receive', [$this, 'onReceive']);
        $this->ss->start();
    }


    public function onServerStart($ss)
    {
        if (PHP_OS == "Linux") {
            swoole_set_process_name("php task_queue Main");
        }
        _LOG("onServerStart");

    }

    public function onTaskRun($srv, $taskid, $wid,$data)
    {
		//$taskid任务id ,$wid服务端进程id
        switch ($data['event'])
        {
            case 'executeTask':
				
                $this->gamer->executeTask($wid);
                break;
			case 'send_task':
				$this->gamer->send_task($wid);
                break;
        }
    }

    public function onTaskEnd($ss)
    {
        _LOG("onTaskEnd");
    }

    public function onServerClose($ss)
    {
        _LOG("onServerClose");
    }

    public function onManageStart($ss)
    {
        if (PHP_OS == "Linux") {
            swoole_set_process_name("php task_queue Manage");
        }
        _LOG("onManageStart");
    }

    public function onManageClose($ss)
    {
        _LOG("onManageClose ");
    }

    public function onWorkerStart($ss, $wid)
    {
        if ($wid == 0) {
            swoole_timer_tick(3600 * 1000, function () use ($ss) {
            	$ss->reload();
            });
        }
		
        $tag = $wid < 3 ? "Worker" : "Tasker";

        if (PHP_OS == "Linux") {
            swoole_set_process_name("php task_queue $tag-$wid");
        }
		//这里面的事件只有work进程才能处理
        //处理队列
        if ($wid == 1) {     
            $tick = 1000;
            $cb = function ($timerId) {
                $data = [];
                $data['event'] = 'executeTask';
				//发送给task进程处理
				//0表示task进程的id从0开始,0表示第一个
                $this->ss->task($data,0);
            };
            swoole_timer_tick($tick, $cb);
        }
		//模拟发送队列
		if ($wid == 2) {     
            $tick2 = 5000;
            $cb2 = function ($timerId2) {
                $data2 = [];
                $data2['event'] = 'send_task';
				
                $this->ss->task($data2,0);
            };
            swoole_timer_tick($tick2, $cb2);
        }
        _LOG("onWorkerStart $tag-$wid");

	    include_once "lib/log.php";
        include_once 'lib/mysql.lib.php';
        include_once 'lib/action.php'; 
        $this->gamer = new gamer($wid, $this);
    }

    public function onWorkerClose($ss, $wid)
    {
        $tag = $wid < 3 ? "Worker" : "Tasker";

        //$this->gamer->action(20, $data);
        /*reload之后内存数据入库*/

            $packs = netpack(100001, json_encode(["name" => 'test']));
            $this->gamer->action(20, $packs);
        /*
        if ($wid == 0) {
            $packs = netpack(100001, json_encode(["name" => 'test']));
            $this->gamer->action(20, $packs);
        }
        */
        _LOG("onWorkerClose $tag-$wid");
    }

    public function onWorkerError($ss, $wid, $pid, $errno)
    {
        _LOG("onWorkerError $errno");
    }

    public function onPipeMessage($ss, $src_wid, $data)
    {
        _LOG("onPipeMessage $data");
    }

    public function onFdStart($ss, $fd, $rid)
    {
        _LOG("begin onFdStart " . $fd);
    }

    public function onFdClose($ss, $fd, $rid)
    {
        _LOG("onFdClose $fd");
        $this->gamer->onClose($fd);

    }

    //接收
    public function onReceive($ss, $fd, $rid, $data)
    {
	    $this->gamer->action($fd, $data);
    }


    public function sendToFd($fd, $opcode, $payload)
    {

        $msg = is_array($payload) ? json_encode($payload) : $payload;
        $length = strlen($msg) + 8;
        $len = Util::num2UInt32Str($length);
        $opcode_e = Util::num2UInt32Str(intval($opcode));
        $package = $len . $opcode_e . $msg;
        _LOG("SENTTO FD=$fd OPCODE=$opcode  DATA=$package"); 
        $this->ss->send($fd, $package);
    }
}


define('ISLOCAL', true);
define("PORT", 18000);
define("HOST", "127.0.0.1");	
define("ISMASTER", 1);
define("HOSTID", HOST."_".PORT);
define("RD_HOST", ISLOCAL ? "127.0.0.1" : "");
define("RD_PORT", ISLOCAL ? 6379 : 6379);
define("RD_PASS", ISLOCAL ? null : "");
define("MY_HOST", ISLOCAL ? "127.0.0.1" : "127.0.0.1");
define("MY_PORT", 3306);
define("MY_USER", "root");
define("MY_PASS", "123456");
define("MY_BASE", "mymvc");
define("MY_CHAR", "UTF8");
define('_DATETIME', date('Y-m-d H:i:s', time()));
define('_DATEID', date('Ymd', time()));
$Server = new StartServer();
$Server->start();
