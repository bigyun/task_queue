<?php
/***************************************
 *    2015-05-28 by liumingjie add 日志文件
 *    call _LOG function generates log
 ***************************************/
// -----------------------------------------------------------------------------------------
date_default_timezone_set('Asia/Shanghai');//日期区域转换

define("LOGDIR", __DIR__ . "/../logs/");

if (function_exists("_LOG") != true) {
    function _LOG($form_data = "", $log_name = "", $url = '')
    {
        $data = date("Y-m-d", time());

        $url = LOGDIR;

        $log_name = !empty($log_name)
            ?
            $log_name . '_' . $data . '.txt'
            :
            "common_" . date("Y-m-d", time()) . '.txt';

        $myfile = fopen("$url" . "$log_name", "a+");

        $log = "[" . date('Y-m-d H:i:s', time()) . "]" . $form_data . "\r\n";

        fwrite($myfile, $log);
        //fclose ( $myfile );
        echo $log;
    }
}
// -----------------------------------------------------------------------------------------
/**
 * 游戏统计日志扩展
 * */
class  LOG
{
	public $dbh=null;
	public $redis = null;
	public $datetime =null;
	public $action = null;
	public function __construct()
	{ 		 
	}
	/**
	 * 登录日志记录
	 * @param  array $value 
	 * **/
	public  function log_online($value,$status=false) 
	{		
	}
	/**
	 * 注册日志记录
	 * @param  array $value
	 * **/
	public  function log_registered($value,$status=null)
	{ 	 
    }
	public function log_role_pay($uid,$payLogStr)
	{
	} 
	/**
	 * 充值订单日志记录
	 * ***/
	public function log_pay($value)
	{
	}
	/**
	 * 进场日志记录
	 * **/
	public function log_approach($value=array(),$userinfo=array())
	{		
		
	}
	/**
	验证uid 是否存在
	**/
	public function verify_uid($userinfo)
	{ 
	}
	/**
	 * 验证字段是否设置为空
	 * @param $value whether is set 
	 * @param $empty false or true if true value!=null
	 * **/
	public  function  verify_field($value,$empty=false)
	{
		return null;		
	}
	/**
	 * 结果集检验
	 * @param $data Result set judgment or verify
	 ***/
	public function verify_data( $data ,$function=null)
	{	
	}
	/**
	 * 数据检验录入
	 * @param $table name
	 * @param $data  array()
	 ***/
	public function verify_dbset($table,$data)
	{ 	
	}
	/**
	 * 数据更新
	 * @param $table name
	 * @param $data  array()
	 ***/
	public function db_update($table,$data,$where){
		return false;
	}
	/**
	 * 数据获取
	 * @param $table name
	 * @param $data  array()
	 ***/
	public function db_select($sql)
	{ 
		return false;
	}
	 
}