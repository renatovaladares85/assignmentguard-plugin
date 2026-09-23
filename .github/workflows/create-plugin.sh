#!/usr/bin/env bash

set -euo pipefail

if [ -d assignment-guard ]; then
    mv assignment-guard assignmentguard
fi

test -f assignmentguard/setup.php
