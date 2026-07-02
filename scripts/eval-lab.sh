#!/bin/sh
set -eu

if [ "$#" -lt 1 ]; then
	echo "Usage: scripts/eval-lab.sh task=<eval-lab-task> [args...]" >&2
	exit 1
fi

eval_lab_path="${NPCINK_EVAL_LAB_PATH:-../npcink-eval-lab}"

if [ ! -d "$eval_lab_path" ]; then
	echo "Npcink eval lab not found: $eval_lab_path" >&2
	echo "Set NPCINK_EVAL_LAB_PATH or clone /Users/muze/gitee/npcink-eval-lab next to this repo." >&2
	exit 1
fi

for eval_lab_arg in "$@"; do
	case "$eval_lab_arg" in
		files=*)
			WORKFLOW_TOOLBOX_FILES="${eval_lab_arg#files=}"
			export WORKFLOW_TOOLBOX_FILES
			;;
	esac
done

case "$1" in
	task=*|--list|--help|-h|help|list|tasks)
		exec composer --working-dir="$eval_lab_path" eval:task -- "$@"
		;;
	*)
		script_name="$1"
		shift
		exec composer --working-dir="$eval_lab_path" "$script_name" -- "$@"
		;;
esac
