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
source_commit=$(git -C "$repo_root" rev-parse --verify HEAD) || { echo 'Could not resolve the package source commit.' >&2; exit 1; }
version=$(git -C "$repo_root" show "$source_commit:setup.php" | sed -n "s/^define('PLUGIN_ASSIGNMENTGUARD_VERSION', '\([^']*\)');$/\1/p")
[[ -n "$version" ]] || { echo 'Could not read PLUGIN_ASSIGNMENTGUARD_VERSION.' >&2; exit 1; }

archive_name="assignmentguard-${version}-${candidate}.tar.gz"
mkdir -p "$output_dir"
archive_path=$(cd "$output_dir" && pwd)/$archive_name
stage_dir=$(mktemp -d)
trap 'rm -rf "$stage_dir"' EXIT

package_root="$stage_dir/assignmentguard"
install -d -m 0755 "$package_root" "$package_root/front" "$package_root/locales" "$package_root/src"

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
    git -C "$repo_root" cat-file -e "$source_commit:$file" || { echo "Missing package file in source commit: $file" >&2; exit 1; }
    git -C "$repo_root" show "$source_commit:$file" > "$package_root/$file"
    chmod 0644 "$package_root/$file"
done

mapfile -t source_files < <(git -C "$repo_root" ls-tree -r --name-only "$source_commit" -- src | awk '/^src\/[^/]+\.php$/')
[[ ${#source_files[@]} -gt 0 ]] || { echo 'No source files found in source commit.' >&2; exit 1; }
for source_file in "${source_files[@]}"; do
    git -C "$repo_root" show "$source_commit:$source_file" > "$package_root/$source_file"
    chmod 0644 "$package_root/$source_file"
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
