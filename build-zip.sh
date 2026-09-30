#!/bin/sh
# Rebuild family-memories.zip (the file you upload to WordPress) from the plugin folder.
set -e
cd "$(dirname "$0")"
rm -f family-memories.zip
zip -r -X family-memories.zip family-memories -x '*.DS_Store'
echo "Built family-memories.zip"
