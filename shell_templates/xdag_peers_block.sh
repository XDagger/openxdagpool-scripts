#!/bin/bash

while read -u 10 line; do
        IP="`echo $line | cut -d ':' -f 1`"
        route add -host "$IP" reject
done 10<netdb-white.txt
