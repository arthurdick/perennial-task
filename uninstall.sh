#!/usr/bin/env bash

# Perennial Task Uninstallation Script
# This script must be run with sudo privileges.

# --- Configuration ---
APP_NAME="perennial-task"
INSTALL_DIR="/usr/local/lib/$APP_NAME"
BIN_FILE="/usr/local/bin/prn" # Updated executable name

# --- Pre-flight Checks ---
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root or with sudo." 
   exit 1
fi

echo "This script will remove the Perennial Task application."
read -p "Are you sure you want to continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Uninstallation cancelled."
    exit 0
fi

# --- Removal Steps ---
echo "Removing symbolic link at $BIN_FILE..."
rm -f "$BIN_FILE"

echo "Removing application directory at $INSTALL_DIR..."
rm -rf "$INSTALL_DIR"

echo "Application files have been removed."
echo ""

# --- User Data Removal (Optional) ---
if [ -n "$SUDO_USER" ]; then
    USER_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)
    USER_CONFIG_DIR="$USER_HOME/.config/$APP_NAME"

    if [ -d "$USER_CONFIG_DIR" ]; then
        echo "User data (tasks and logs) was found at $USER_CONFIG_DIR."
        read -p "Do you want to remove this user data as well? THIS CANNOT BE UNDONE. (y/n) " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            echo "Removing user data directory..."
            rm -rf "$USER_CONFIG_DIR"
            echo "User data removed."
        else
            echo "User data has been kept."
        fi
    fi
else
    echo "Warning: Could not determine the original user. Please manually check for and remove '~/.config/$APP_NAME' if desired."
fi

echo ""
echo "Perennial Task has been successfully uninstalled."
exit 0
