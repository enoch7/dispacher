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
				$newMax = $row['max'] + $row['step'];
				$sql = "update sequence set max = {$newMax} where name = '{$key}'";
				if ($dbconn->query($sql)) {
					$redis->hMSet($key,['current'=>$row['max'],'max'=>$newMax]);
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