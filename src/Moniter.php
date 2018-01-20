<?php
require_once __DIR__ . "/Common.php";
/**
* 
*/
class Moniter extends Common
{
	private $lastNumber = 0;

	public function start()
	{
		global $argv;
		if (isset($argv[1])) {
			$key = $argv[1];	
		} else {
			echo "need a key name";
			exit(0);
		}
		
		$sleepTime = 10;
		while (true) {
			$startTime = microtime(true);
			try {
				$redis = $this->getRedisConnection();
				$dbconn = $this->getDbConnection();
				$sql = "select max,step from sequence where name = '{$key}' limit 1";
				$result = $dbconn->query($sql);
				$persistentData = $result->fetch_array(MYSQLI_ASSOC);
				$step = $persistentData['step'];

				$currentStat = $redis->hGetAll($key);

				if ($this->lastNumber !=0 && $currentStat['current'] - $this->lastNumber > $step) {
					$step = $currentStat['current'] - $this->lastNumber;
					$sql = "update sequence set step = " . $step . " where name = '{$key}' ";
					$dbconn->query($sql);
				}

				$this->lastNumber = $currentStat['current']; 

				if ($currentStat['max'] - $currentStat['current'] < intval($step * (60/$sleepTime) * 10)) {
					if ($redis->set("lock:set:".$key, 1, ['NX', 'EX'=>10])) {
						$newMax = $persistentData['max'] + intval($step * (60/$sleepTime) * 10);
						$sql = "update sequence set max = {$newMax} where name = '{$key}'";
						if ($dbconn->query($sql)) {
							$redis->hSet($key, 'max', $newMax);
						}		
						$redis->del("lock:set:".$key);
					}	
				}

			} catch (\Exception $e) {
				if ($e instanceof \RedisException) {
					$this->setRedis();
				} else if ($e instanceof \PDOException) {
					$this->setDbConn();
				} else {
					//log excption;
				}
			}

			usleep(($sleepTime - (microtime(true) - $startTime)) * 1000000);
		}		
	}
}
