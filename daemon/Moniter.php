<?php
/**
* 
*/
class Moniter
{
	private $redis;
	private $dbconn;

	private $lastNumber = 0;

	public function __construct()
	{
		$this->init();
	}

	public function init()
	{

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

	public function start()
	{
		$sleepTime = 10;
		while (true) {
			try {
				$key = '20180110';
				$redis = $this->getRedisConnection();
				$dbconn = $this->getDbConnection();
				$sql = "select max,step from sequence where name = '{$key}' limit 1";
				$result = $dbconn->query($sql);
				$persistentData = $result->fetch_array(MYSQLI_ASSOC);
				$step = $persistentData['step'];

				$currentStat = $redis->hGetAll($key);
				if ($currentStat['current'] - $this->lastNumber > $step) {
					$step = $currentStat['current'] - $this->lastNumber;
					$sql = "update sequence set step = " . $step . " where name = '{$key}' ";
					$dbconn->query($sql);
				}

				if ($currentStat['max'] - $currentStat['current'] < $step * (60/$sleepTime) * 10) {
					if ($redis->set("lock:set:".$key, 1, ['NX', 'EX'=>10])) {
						$sql = "update sequence set max = last_insert_id(max + step) where name = '{$key}'";
						if ($dbconn->query($sql)) {
							$redis->hSet($key, 'max', ($currentStat['max'] + $step * (60/$sleepTime) * 10));
						}		
						$redis->del("lock:set:".$key);
					}	
				}

			} catch (\Exception $e) {
				if ($e instanceof \RedisException) {
					$this->redis = null;
				} else if ($e instanceof \PDOException) {
					$this->dbconn = null;
				} else {
					//log excption;
				}
			}
			sleep($sleepTime);
		}	
		
	}


	
}