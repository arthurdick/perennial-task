#!/usr/bin/env bash

# Perennial Task Installation Script
# This script must be run with sudo privileges.

# --- Configuration ---
APP_NAME="perennial-task"
EXECUTABLE_NAME="prn"
INSTALL_DIR="/usr/local/lib/$APP_NAME"
BIN_DIR="/usr/local/bin"
SCHEMA_NAME="task.xsd"
COMPLETIONS_SCRIPT="prn-completions.bash"

# --- Pre-flight Checks ---
if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root or with sudo." 
    exit 1
fi

echo "Checking for dependencies..."
if ! php -m | grep -qi 'SimpleXML'; then
    echo "Error: The 'SimpleXML' PHP extension is required but not installed."
    exit 1
fi
if ! php -m | grep -qi 'dom'; then
    echo "Error: The 'dom' PHP extension is required but not installed."
    exit 1
fi

echo "Starting Perennial Task installation..."

# --- Installation Steps ---
echo "Creating application directory at $INSTALL_DIR..."
mkdir -p "$INSTALL_DIR" || { echo "Error: Could not create directory $INSTALL_DIR. Aborting."; exit 1; }

echo "Copying application files..."
(
  shopt -s nullglob
  cp ./*.php "$INSTALL_DIR/"
  cp ./*.xsd "$INSTALL_DIR/"
  cp ./"$EXECUTABLE_NAME" "$INSTALL_DIR/"
  cp ./"$COMPLETIONS_SCRIPT" "$INSTALL_DIR/"
) || { echo "Error: Failed to copy application files. Aborting."; exit 1; }

echo "Setting file permissions..."
chmod +x "$INSTALL_DIR/$EXECUTABLE_NAME"

echo "Creating symbolic link at $BIN_DIR/$EXECUTABLE_NAME..."
ln -sf "$INSTALL_DIR/$EXECUTABLE_NAME" "$BIN_DIR/$EXECUTABLE_NAME" || { echo "Error: Could not create symbolic link. Aborting."; exit 1; }

# --- Install Bash Completions ---
echo "Installing bash completions..."
COMPLETIONS_DIR=""
if [ -d "/usr/share/bash-completion/completions" ]; then
    COMPLETIONS_DIR="/usr/share/bash-completion/completions"
elif [ -d "/etc/bash_completion.d" ]; then
    COMPLETIONS_DIR="/etc/bash_completion.d"
fi

if [ -n "$COMPLETIONS_DIR" ]; then
    ln -sf "$INSTALL_DIR/$COMPLETIONS_SCRIPT" "$COMPLETIONS_DIR/$EXECUTABLE_NAME"
    if [ $? -eq 0 ]; then
        echo "Bash completions installed. Please restart your shell or run 'source $COMPLETIONS_DIR/$EXECUTABLE_NAME' to enable them."
    else
        echo "Warning: Could not install bash completions."
    fi
else
    echo "Warning: Could not find a bash completion directory. Completions not installed."
fi

# --- Finalization ---

# Determine the likely configuration path for the user who ran sudo
CONFIG_LOCATION_MSG="in ~/.config/$APP_NAME/ or \$XDG_CONFIG_HOME/$APP_NAME/"
if [ -n "$SUDO_USER" ]; then
    USER_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)
    
    # Check for XDG_CONFIG_HOME, defaulting to ~/.config
    if [[ -n "$XDG_CONFIG_HOME" && -d "$XDG_CONFIG_HOME" ]]; then
        CONFIG_BASE="$XDG_CONFIG_HOME"
    else
        CONFIG_BASE="$USER_HOME/.config"
    fi
    CONFIG_LOCATION_MSG="in the '$CONFIG_BASE/$APP_NAME/' directory"
fi

echo ""
echo "-------------------------------------------"
echo " Perennial Task installation complete!"
echo "-------------------------------------------"
echo "You can now use the 'prn' command from anywhere in your terminal."
echo "Your configuration will be created automatically on first run,"
echo "$CONFIG_LOCATION_MSG."
echo "Run 'prn help' to get started."
echo ""

exit 0

