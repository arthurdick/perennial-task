#!/usr/bin/env bash

# Perennial Task Uninstallation Script
# This script must be run with sudo privileges.

# --- Configuration ---
APP_NAME="perennial-task"
INSTALL_DIR="/usr/local/lib/$APP_NAME"
EXECUTABLE_NAME="prn"
BIN_FILE="/usr/local/bin/$EXECUTABLE_NAME"

# --- Pre-flight Checks ---
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root or with sudo."
   exit 1
fi

# --- Initial Confirmation ---
echo "This script will remove the Perennial Task application files from your system."
echo "Your personal task data will NOT be deleted at this step."
echo ""
echo "To confirm that you want to remove the application, please type 'yes':"
read -p "> " INITIAL_CONFIRMATION

if [[ "$INITIAL_CONFIRMATION" != "yes" ]]; then
    echo "Confirmation failed. Uninstallation cancelled."
    exit 0
fi

echo "Confirmation accepted. Proceeding with application removal..."
echo ""

# --- Removal Steps ---
echo "Removing symbolic link at $BIN_FILE..."
rm -f "$BIN_FILE"

echo "Removing application directory at $INSTALL_DIR..."
rm -rf "$INSTALL_DIR"

# --- Remove Bash Completions ---
echo "Removing bash completions..."
COMPLETIONS_FILE=""
if [ -f "/usr/share/bash-completion/completions/$EXECUTABLE_NAME" ]; then
    COMPLETIONS_FILE="/usr/share/bash-completion/completions/$EXECUTABLE_NAME"
elif [ -f "/etc/bash_completion.d/$EXECUTABLE_NAME" ]; then
    COMPLETIONS_FILE="/etc/bash_completion.d/$EXECUTABLE_NAME"
fi

if [ -n "$COMPLETIONS_FILE" ]; then
    rm -f "$COMPLETIONS_FILE"
fi

echo "Application files have been removed."
echo ""

# --- User Data Removal (Optional) ---
if [ -n "$SUDO_USER" ]; then
    USER_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)

    # Determine the base config directory according to XDG Base Directory Specification.
    if [[ -n "$XDG_CONFIG_HOME" && -d "$XDG_CONFIG_HOME" ]]; then
        CONFIG_BASE="$XDG_CONFIG_HOME"
    else
        CONFIG_BASE="$USER_HOME/.config"
    fi
    USER_CONFIG_DIR="$CONFIG_BASE/$APP_NAME"

    if [ -d "$USER_CONFIG_DIR" ]; then
        echo "User data (tasks and configuration) was found at $USER_CONFIG_DIR."
        echo "---"
        echo "WARNING: This is a destructive action that cannot be undone."
        echo "To confirm the deletion of all your tasks and settings, please type 'yes':"
        read -p "> " DATA_CONFIRMATION
        
        if [[ "$DATA_CONFIRMATION" == "yes" ]]; then
            echo "Confirmation accepted. Removing user data directory..."
            rm -rf "$USER_CONFIG_DIR"
            echo "User data removed."
        else
            echo "Confirmation failed. Your data has not been touched."
        fi
    fi
else
    echo "Warning: Could not determine the original user. Please manually check for and remove user data."
    echo "Common locations are '~/.config/$APP_NAME' or '\$XDG_CONFIG_HOME/$APP_NAME'."
fi

echo ""
echo "Perennial Task has been successfully uninstalled."
exit 0

