redis-cli -n 0 keys "u:blog:*"|xargs redis-cli -n 0 del
redis-cli -n 0 keys "u:stage:*"|xargs redis-cli -n 0 del
redis-cli -n 0 keys "u:wish:*"|xargs redis-cli -n 0 del
redis-cli -n 0 keys "u:pray:*"|xargs redis-cli -n 0 del