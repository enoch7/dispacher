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
