#!/bin/bash
mkdir -p data
chmod -R 777 data
php -S 0.0.0.0:$PORT -t .
