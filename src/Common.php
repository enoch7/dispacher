<?php
class Common
{
	protected $redis;
	protected $dbconn;

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

		$conn = new mysqli($this->config['db']['hostname'],$this->config['db']['user'],$this->config['db']['password'],$this->config['db']['database']);
		$this->dbconn = $conn;
		return $this->dbconn;

	}
	
}