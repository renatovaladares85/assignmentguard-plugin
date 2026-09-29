#!/usr/bin/env bash

set -euo pipefail

usage() {
    echo "Usage: $0 --output <directory> [--candidate <label>]" >&2
    exit 64
}

output_dir=''
candidate='rc.1'
while [[ $# -gt 0 ]]; do
    case "$1" in
        --output)
            output_dir=${2:-}
            shift 2
            ;;
        --candidate)
            candidate=${2:-}
            shift 2
            ;;
        *)
            usage
            ;;
    esac
done

[[ -n "$output_dir" && -n "$candidate" ]] || usage

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
version=$(sed -n "s/^define('PLUGIN_ASSIGNMENTGUARD_VERSION', '\([^']*\)');$/\1/p" "$repo_root/setup.php")
[[ -n "$version" ]] || { echo 'Could not read PLUGIN_ASSIGNMENTGUARD_VERSION.' >&2; exit 1; }

archive_name="assignmentguard-${version}-${candidate}.tar.gz"
mkdir -p "$output_dir"
archive_path=$(cd "$output_dir" && pwd)/$archive_name
stage_dir=$(mktemp -d)
trap 'rm -rf "$stage_dir"' EXIT

package_root="$stage_dir/assignmentguard"
mkdir -p "$package_root/front" "$package_root/locales" "$package_root/src"

files=(
    CHANGELOG.md
    LICENSE
    README.md
    hook.php
    plugin.xml
    setup.php
    front/config.form.php
    locales/pt_BR.mo
    locales/pt_BR.po
)

for file in "${files[@]}"; do
    [[ -f "$repo_root/$file" && ! -L "$repo_root/$file" ]] || { echo "Invalid package file: $file" >&2; exit 1; }
    install -m 0644 "$repo_root/$file" "$package_root/$file"
done

for source_file in "$repo_root"/src/*.php; do
    [[ -f "$source_file" && ! -L "$source_file" ]] || { echo "Invalid source file: $source_file" >&2; exit 1; }
    install -m 0644 "$source_file" "$package_root/src/$(basename "$source_file")"
done

tar --sort=name --mtime='@0' --owner=0 --group=0 --numeric-owner \
    -C "$stage_dir" -cf - assignmentguard | gzip -n > "$archive_path"

mapfile -t archive_entries < <(tar -tzf "$archive_path")
[[ ${#archive_entries[@]} -gt 0 ]] || { echo 'Package archive is empty.' >&2; exit 1; }
for entry in "${archive_entries[@]}"; do
    [[ "$entry" == assignmentguard/* || "$entry" == assignmentguard ]] || { echo "Unexpected archive root: $entry" >&2; exit 1; }
    [[ ! "$entry" =~ (^|/)(\.git|vendor|tests|docs|var|files)(/|$) ]] || { echo "Forbidden archive entry: $entry" >&2; exit 1; }
done

for required in assignmentguard/setup.php assignmentguard/hook.php assignmentguard/plugin.xml assignmentguard/src/autoload.php assignmentguard/locales/pt_BR.mo; do
    printf '%s\n' "${archive_entries[@]}" | grep -Fxq "$required" || { echo "Missing archive entry: $required" >&2; exit 1; }
done

printf 'ARCHIVE=%s\n' "$archive_path"
printf 'NAME=%s\n' "$archive_name"
printf 'SIZE_BYTES=%s\n' "$(wc -c < "$archive_path" | tr -d ' ')"
printf 'SHA256=%s\n' "$(sha256sum "$archive_path" | awk '{print $1}')"
