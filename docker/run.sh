#!/usr/bin/env bash

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd $DIR
docker-compose up -d db
sleep 10
docker-compose exec -T db bash < reset.sh
docker-compose build
docker-compose run php
