#!/usr/bin/env bash

set -euo pipefail

archive_path=${1:-}
[[ -f "$archive_path" ]] || { echo "Usage: $0 <assignmentguard-package.tar.gz>" >&2; exit 64; }

stage_dir=$(mktemp -d)
trap 'rm -rf "$stage_dir"' EXIT
tar -xzf "$archive_path" -C "$stage_dir"

package_root="$stage_dir/assignmentguard"
[[ -d "$package_root" ]] || { echo 'Package technical directory is missing.' >&2; exit 1; }
for required in setup.php hook.php plugin.xml src/autoload.php locales/pt_BR.mo; do
    [[ -f "$package_root/$required" ]] || { echo "Missing package file: $required" >&2; exit 1; }
done

version=$(sed -n "s/^define('PLUGIN_ASSIGNMENTGUARD_VERSION', '\([^']*\)');$/\1/p" "$package_root/setup.php")
[[ "$version" == '0.1.0' ]] || { echo 'Unexpected package version.' >&2; exit 1; }
php -r "\$xml = simplexml_load_file('$package_root/plugin.xml'); if (!\$xml || (string) \$xml->key !== 'assignmentguard') { exit(1); }"
find "$package_root" -name '*.php' -type f -print0 | xargs -0 -n1 php -l >/dev/null
printf 'PACKAGE_VERIFIED=%s\n' "$archive_path"
