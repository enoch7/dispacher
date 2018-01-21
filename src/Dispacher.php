<?php
require_once __DIR__ . "/Common.php";
/**
* 
*/
class Dispacher extends Common
{

	public  function getSequence(string $key,int $num = 1)
	{
		try {
			$number = $this->getSequenceValue($key);
			if ($number === 0) {
				$redis = $this->getRedisConnection();
				if ($redis->set("lock:set:".$key, 1, ['NX', 'EX'=>10])) {
					$this->updateSequence($key);
					$redis->del("lock:set:".$key);
				} else {
					sleep(1);
				}
				
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
		$redis = $this->getRedisConnection();
		$dbconn = $this->getDbConnection();
		$sql = "select max,step from sequence where name = '{$key}' limit 1";
		$result = $dbconn->query($sql);
		$row = $result->fetch_array(MYSQLI_ASSOC);
		if ($row['max']) {
			$newMax = $row['max'] + $row['step'];
			$sql = "update sequence set max = {$newMax} where name = '{$key}'";
			if ($dbconn->query($sql)) {
				if ($redis->hGet($key, 'current') === false) {
					$redis->hMSet($key,['current'=>$row['max'],'max'=>$newMax]);	
				} else {
					$redis->hSet($key,'max',$newMax);
				}
			}		
		}
			
	}

	public function getSequenceValue($key)
	{
		$number = 0;
		$redis = $this->getRedisConnection();
		if ($redis->exists($key)) {
			$luaScript = <<<LUA
local current = redis.call("HGET", KEYS[1], "current")
current = tonumber(current)
local max = redis.call("HGET", KEYS[1], "max")
max = tonumber(max)
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