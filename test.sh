#!/usr/bin/env bash

# param none
curl 127.0.0.1:28888 -i -X POST -H 'Content-Type:json' \
	-d '{"jsonrpc":"2.0","method":"foo","id":1}'

# param array
curl 127.0.0.1:28888 -i -X POST -H 'Content-Type:json' \
	-d '{"jsonrpc":"2.0","method":"foo","params":[123,"abc"],"id":4}'

# param object
curl 127.0.0.1:28888 -i -X POST -H 'Content-Type:json' \
	-d '{"jsonrpc":"2.0","method":"foo","params":{"abc":"ABC"},"id":5}'

# notification
curl 127.0.0.1:28888 -i -X POST -H 'Content-Type:json' \
	-d '{"jsonrpc":"2.0","method":"notify","params":{"abc":"ABC"}}'

# batch
BATCH='[{"jsonrpc":"2.0","method":"notify","params":{"abc":"ABC"}},{"jsonrpc":"2.0","method":"foo","params":{"abc":"ABC"},"id":6},{"jsonrpc":"2.0","method":"bar","params":{"efg":"EFG"},"id":7}]'

#BATCH='[{"jsonrpc":"2.0","method":"notify","params":{"abc":"ABC"}}]'

curl 127.0.0.1:28888 -i -X POST -H 'Content-Type:json' \
	-d ${BATCH}

