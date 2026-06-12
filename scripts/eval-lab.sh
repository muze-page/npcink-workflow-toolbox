#!/bin/sh
set -eu

if [ "$#" -lt 1 ]; then
	echo "Usage: scripts/eval-lab.sh <composer-script> [args...]" >&2
	exit 1
fi

script_name="$1"
shift

eval_lab_path="${MAGICK_AI_EVAL_LAB_PATH:-../magick-ai-eval-lab}"

if [ ! -d "$eval_lab_path" ]; then
	echo "Magick AI eval lab not found: $eval_lab_path" >&2
	echo "Set MAGICK_AI_EVAL_LAB_PATH or clone /Users/muze/gitee/magick-ai-eval-lab next to this repo." >&2
	exit 1
fi

exec composer --working-dir="$eval_lab_path" "$script_name" -- "$@"
