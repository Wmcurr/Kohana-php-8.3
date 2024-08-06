#!/usr/bin/env bash

set -euo pipefail

# Name of your CLI tool
CLI_NAME="minion"

# Directory where the CLI tool will be installed
INSTALL_DIR="/usr/local/bin"

# Determine the absolute path to the directory containing this script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RELATIVE_SOURCE_PATH="minion"  # Исправленный путь
SOURCE_PATH="$SCRIPT_DIR/$RELATIVE_SOURCE_PATH"

# Check if the source file exists
if [ ! -f "$SOURCE_PATH" ]; then
    echo "Source file not found: $SOURCE_PATH"
    exit 1
fi

# Copy CLI to INSTALL_DIR
echo "Installing $CLI_NAME in $INSTALL_DIR..."
sudo cp "$SOURCE_PATH" "$INSTALL_DIR/$CLI_NAME"
sudo chmod +x "$INSTALL_DIR/$CLI_NAME"

# Check if installation was successful
if [ -f "$INSTALL_DIR/$CLI_NAME" ]; then
    echo "$CLI_NAME successfully installed in $INSTALL_DIR/$CLI_NAME"
else
    echo "Error installing $CLI_NAME"
    exit 1
fi

# Check if INSTALL_DIR is in PATH
if ! echo "$PATH" | grep -q "$INSTALL_DIR"; then
    echo "$INSTALL_DIR not found in PATH."
    echo "Add the following line to your ~/.bashrc or ~/.bash_profile to use $CLI_NAME globally:"
    echo "export PATH=\"$INSTALL_DIR:\$PATH\""
else
    echo "$CLI_NAME is available globally. You can use the \"$CLI_NAME\" command from any directory."
fi
