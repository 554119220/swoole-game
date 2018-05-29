<?php
namespace Game\Core;

class RedisPool {
    protected $pool;
	protected $conf;

    function __construct($conf) {
        $this->pool = new SplQueue;
		$this->conf = $conf;
    }

    function put($redis) {
        $this->pool->push($redis);
    }

    function get() {
        //�п�������
        if (count($this->pool) > 0) {
            return $this->pool->pop();
        }

        //�޿������ӣ�����������
        $redis = new \Swoole\Coroutine\Redis();
        $res = $redis->connect($this->conf['host'], $this->conf['port']);
        if ($res == false) {
            return false;
        } else {
            return $redis;
        }
    }
}