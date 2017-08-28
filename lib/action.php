<?php

/**
 * Created by PhpStorm.
 * User: lee
 * Date: 17/3/15
 * Time: 下午12:11
 */
class gamer
{
    public $dbManager = null;

    public $log = null;

    public $DB = null;
    public $arr = array();

    /**
     *
     * @var DisPatchServer
     */
    public $server = null;

    /**
     *
     * @var Redis
     */
    public $redis = NULL;

    public $wid = 0;

    public function __construct($wid, $server)
    {
        $this->log = new LOG();
        $this->wid = $wid;
        $this->server = $server;
        $this->dbManager = new DB();
        $this->redis = new Redis();
        $this->redis->connect(RD_HOST, RD_PORT);
        $this->redis->auth(RD_PASS);
        
        $redis = $this->redis;
        
        _LOG("ACTION:" . $wid);
        if ($wid == 1) {}
    }

    public function onClose($fd)
    {
        _LOG("onClose:" . $fd);
        
        if ($uid != null) {
            // 存档退出
            $this->redis->rPush('fruit_exituser', $uid, $uid);
            $userInfo = $this->redis->hGetAll('userinfo:' . $uid);
            $this->log->log_registered($userInfo, true);
            $this->redis->hSet('userinfo:' . $uid, 'fd', 0);
            $this->log->log_online([
                'uid' => $uid
            ], true);
        }
    }

    public function action($fd, $data)
    {


        $package = netunpack($data);
        $len = $package[0];
        $cmd = $package[1];
        $value = $package[2];
        $value = json_decode($value, true);
        //_LOG("messsdata:".$data);
        switch ($cmd) {
            case 100000:
                $new_arr = [
                    'name'  => $value['name'],
                    'sex'  =>  $value['sex'],
		            ];
				//消息入队
				$this->redis->rPush('msg_center', json_encode($new_arr));
				
                array_push($this->arr,$new_arr);
                if(count($this->arr) >= 5){
                    //$this->dbManager->batchinsert('users',$this->arr);
                    _LOG("insert_data:".json_encode($this->arr)."数量".count($this->arr));
                    $this->arr = [];
                }
				//给客户端回复消息
				/*
				$this->server->sendToFd($fd, 500003, [
                    'state' => 0,
                    'result' => 11,
                ]);
				*/
               break;
            case 100001:
                _LOG("重启插入:数量".count($this->arr));
                if(count($this->arr) >= 1){
                    $this->dbManager->batchinsert('users',$this->arr);
                    $this->arr = [];
                }

                break;
            case 1: //电玩测试
                $_DATEID = date('Ymd', time());
                $_DATETIME = date('Y-m-d H:i:s', time());
                _LOG("test_poker:".$package[2]);
                //return;
                $new_event['date'] = $_DATEID;
                $new_event['event'] = $value['event'];
                $new_event['value'] = json_encode(json_decode($value['value'],true));
                if($value['game_type'] && $value['game_type'] == 1 ){//游戏本身的时间
                    switch($value['event']) {

                        case '10006'://注册
                            _LOG("data:".$value['event']);
                            $event = json_decode($value['value'], true);
                            if($event){
                                $sql = "select uid from {$cmd}_user where uid=" .$event['player_id'];
                                if(!$this->dbManager->getData($sql)[0]) {
                                $new_user['uid'] = $event['player_id'];
                                $new_user['sdkuid'] = $event['sdkuid'];
                                $new_user['nickname'] = $event['nickname'];
                                $new_user['register_ip'] = $event['ip'];
                                $new_user['game'] = 1;
                                $new_user['total_amount'] = '0,0,0,0,0';
                                $new_user['last_login_date'] = $_DATEID;
                                $new_user['last_login_at'] = $_DATETIME;
                                $new_user['register_date'] = $_DATEID;
                                $new_user['created_at'] = $_DATETIME;
                                $new_user['updated_at'] = $_DATETIME;
                                $this->dbManager->insert($cmd.'_user',$new_user);
                              }
                            }

                            break;
                       
                        default:
                            break;

                    }

                }elseif($value['game_type'] && $value['game_type'] == 4){//水浒传

                    switch($value['event']) {
                      
                        case '10005'://水浒传比倍
                            $event = json_decode($value['value'], true);
                            $new_event['gid'] = $event['round'];
                            $sql_b = "select value from {$cmd}_user_event where event = '10004' and  gid=" .$event['round'];
                            $arr_res_b = $this->dbManager->getData($sql_b)[0];
                            if($arr_res_b){
                                $data4 = json_decode($arr_res_b['value'], true);
                                $win = $data4['output'];

                            }else{
                                    $win = 0;
                            }
                            foreach (json_decode($event['bet'],true) as $bet1 => $bet2){
                                $win_a = $bet2['win'] ;
                            }
                            $sql = "select shares4,extra4 from {$cmd}_user where uid=" .$event['player_id'];
                            $arr_res = $this->dbManager->getData($sql)[0];
                            $shares4 = $arr_res['shares4'];
                            $arr_res = json_decode($arr_res['extra4'],true);
                            $arr_res['total_player_out'] += ($win_a - $win);
                            $shares4 = $arr_res['total_player_out'] - $arr_res['total_player_in'];
                            $where_arr =   "uid = " . $event['player_id'];
                            $this->dbManager->update($cmd.'_user',['extra4' => json_encode($arr_res),'shares4' => $shares4],$where_arr);

                            break;
                       
                        default:
                            break;
                    }
                }
                $new_event['game_type'] = $value['game_type'];
                $new_event['created_at'] = $_DATETIME;
                $new_event['updated_at'] = $_DATETIME;
                $new_event['timedate'] = time();
                $this->dbManager->insert($cmd.'_user_event',$new_event);

                break;

           
       
            default:
                break;


        }
    }

    // 执行任务
    public function executeTask($taskid)
    {
        $this->task_broadcast($taskid);
    }
	 // 模拟添加任务
    public function send_task($taskid)
    {
		 _LOG("send_task:".$taskid."_".date('Y-m-d H:i:s',time()));
		$this->redis->rPush('msg_center', json_encode(["name" => rand(1,5000).'lee'.rand(6000,10000), "sex" => "sdsd".rand(1,7000)]));
        
    }
	//处理队列中内容
	public function task_broadcast($taskid){
		
		 _LOG("task_broadcast:".date('Y-m-d H:i:s',time())."--".$taskid."--".$this->redis->lpop('msg_center'));
	}
    protected function GetScore($p,$w){
        if($p > 0) {
            $pw = $p - $w;
        }else{
            $pw = $p;
        }
        return $pw;

    }
   

}
