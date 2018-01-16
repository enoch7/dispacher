<?php

class Dispacher 
{
	private $redis;
	private $dbconn;

	private $config;

	public function __construct(array $config)
	{
		$this->config = $config;
	}

	public function getRedisConnection()
	{
		if ($this->redis) {
			return $this->redis;
		}
		$redis = new \Redis();
		$redis->connect($this->config['redis']['hostname'], $this->config['redis']['port']);

		if ($redis->ping()) {
			$this->redis = $redis;
			return $this->redis;
		} else {
			throw new \RedisException("redis connnect fail");
		}

	}

	public function getDbConnection()
	{
		if ($this->dbconn) {
			return $this->dbconn;
		}

		$conn = new mysqli('127.0.0.1','root','root','test');
		$this->dbconn = $conn;
		return $this->dbconn;

	}

	public  function getSequence(string $key,int $num = 1)
	{
		try {
			$number = $this->getSequenceValue($key);
			if ($number == 0) {
				$this->updateSequence($key);
				$number = $this->getSequenceValue($key);
			} 
			return $number;
		} catch (\Exception $e) {
			//do something
			throw $e;
		}
	}

	public function updateSequence(string $key)
	{
		if ($redis->set("lock:set:".$key, 1, ['NX', 'EX'=>10])) {
			$dbconn = $this->getDbConnection();
			$sql = "select max,step from sequence where name = '{$key}' limit 1";
			$result = $dbconn->query($sql);
			$row = $result->fetch_array(MYSQLI_ASSOC);
			if ($row['max']) {
				$sql = "update sequence set max = last_insert_id(max + step) where name = '{$key}'";
				if ($dbconn->query($sql)) {
					$redis->hMSet($key,['current'=>$row['max'],'max'=>($row['max'] + $row['step'])]);
				}		
			}
			$redis->del("lock:set:".$key);
		} else {
			sleep(1);
		}
	}

	public function getSequenceValue($key)
	{
		$number = 0;
		$redis = $this->getRedisConnection();
		if ($redis->exists($key)) {
			$luaScript = <<<LUA
local current = redis.call("HGET", KEYS[1], "current")
local max = redis.call("HGET", KEYS[1], "max")
if max > current then
	current = redis.call("HINCRBY", KEYS[1], "current",1)
    return current
else
    return 0
end
LUA;
			$number = $redis->eval($luaScript,[$key],1);		

		}
		return $number;
	}
}

