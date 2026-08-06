#!/usr/bin/env sh
set -eu
ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
OUTPUT_DIR="$ROOT_DIR/public/downloads"
OUTPUT_FILE="$OUTPUT_DIR/bienaufait-extension-chrome.zip"
command -v zip >/dev/null 2>&1 || { echo "Erreur : la commande zip est requise." >&2; exit 1; }
mkdir -p "$OUTPUT_DIR"
rm -f "$OUTPUT_FILE"
(cd "$ROOT_DIR" && zip -q -r "$OUTPUT_FILE" chrome-plugin -x 'chrome-plugin/*.test.js' 'chrome-plugin/**/.DS_Store')
echo "Extension générée : $OUTPUT_FILE"
