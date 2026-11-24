#!/bin/bash
# Allow the Homebrew Apache mod_php user (_www) to write logs, raw CSV
# files and exports. Business data lives in MySQL and is unaffected.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

mkdir -p storage/cache storage/logs storage/raw storage/exports
# Files already created by _www are not chmod-able by the owner; ignore them.
chmod -R u+rwX storage 2>/dev/null || true
chmod 775 storage storage/cache storage/logs storage/raw storage/exports

# macOS ACL: inherited by new cache and export files.
# Apply the inheritable ACL to everything this user owns and ignore
# already-_www-owned cache files that cannot be re-tagged by Thomas.
chmod -R +a "_www allow read,write,delete,add_file,add_subdirectory,file_inherit,directory_inherit" storage 2>/dev/null || true

echo "Apache permissions applied:"
ls -lde storage
echo
echo "Now open http://google.test/"
