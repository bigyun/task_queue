<?php

/**
 * Created by PhpStorm.
 * User: huangxiufeng
 * Date: 16/9/14
 * Time: 下午6:47
 */
date_default_timezone_set('PRC');
error_reporting(E_ALL);         //E_ERROR | E_WARNING | E_PARSE
set_time_limit(0);
if (!class_exists("swoole_server")) {
    dl("swoole1.8.5.so");
}
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

function netpack($opcode, $payload, &$length = 0)
{
    $msg = is_array($payload) ? \json_encode($payload) : $payload;
    $length = strlen($msg) + 8;
    $len = Util::num2UInt32Str($length);
    $opcode = Util::num2UInt32Str(intval($opcode));
    $pack = $len . $opcode . $msg;
    return $pack;
}

$tube_client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC, "");
$tube_client->set([
    'open_tcp_nodelay'      => 1,
    'socket_buffer_size'    => 2400000,
    'open_length_check'     => 1,
    'package_length_type'   => 'N',
    'package_length_offset' => 0,
    'package_body_offset'   => 0,
    'package_max_length'    => 2000000,
]);
$tube_client->on("connect", function ($cli) {
    echo "连接成功!";
    $i = 0;
    while (true){
        $i++;
		sleep(1);
         if($i>10){
          
           break;
           
        }
        $pack = netpack(100000, json_encode(["name" => rand(1,5000).'lee'.rand(6000,10000), "sex" => "sdsd".rand(1,7000)]) );

        $cli->send($pack);
    }




});
$tube_client->on("receive", function ($cli, $data) {
    //var_dump($cli);
    var_dump($data);
});
$tube_client->on("close", function ($cli) {
    var_dump('close');
});
$tube_client->on("error", function ($cli) {
    var_dump("err");
});
//$tube_client->connect('120.26.4.188', 9957);
$tube_client->connect('127.0.0.1', 18000);
