local current = redis.call("HGET", KEYS[1], "current")

local max = redis.call("HGET", KEYS[1], "max")
if max > current then
	current = redis.call("HINCRBY", KEYS[1], "current",1)
	return current
else
	return 0
end
