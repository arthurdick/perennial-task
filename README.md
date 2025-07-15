# Perennial Task (`prn`) v1.1.2

Like the wood lily that graces the prairies each summer, some tasks are perennial. They return, season after season, requiring steady attention. **Perennial Task** is a simple, command-line utility built to help you cultivate these recurring responsibilities, ensuring nothing is ever overlooked.

![Perennial Task Logo](/docs/prn_logo.png)

## Features

  * **Create Tasks**: Quickly create normal, due-date, or recurring tasks.
  * **Edit Tasks**: Interactively edit any detail of an existing task.
  * **Complete Tasks**: Mark tasks as complete, which updates their next due/completion date or archives them with a full history.
  * **Completion History**: Every completed task retains a full history of when it was completed, allowing you to track consistency and habits over time.
  * **Describe Tasks**: Get a detailed, human-readable description of any single task.
  * **Run Reports**: Generate a report of all tasks that are due, overdue, or upcoming.
  * **Intelligent Filtering**: The task selection menu is filterable, allowing you to view all tasks, only active ones, or just the reportable ones.
  * **Command-Line Driven**: Designed for efficient use within a terminal environment.

## Installation

You can install Perennial Task using one of the two methods below.

### Method 1: Manual Installation

1.  **Platform & Dependencies:** This utility is designed for Linux environments. Ensure you have PHP version 7.4 or higher installed, with the `SimpleXML` and `DOM` extensions enabled (these are usually included by default). You can check this by running `php -v` and `php -m`.
2.  **Gather Files:** Place all the packaged files (`prn`, `install.sh`, `uninstall.sh`, `prn-completions.bash`, `task.xsd`, and all `*.php` scripts) into a single directory.
3.  **Run the Installer:** From within that directory, make the installation script executable and run it with `sudo`:
    ```
    chmod +x install.sh
    sudo ./install.sh
    ```
    The installer will copy the application files to `/usr/local/lib/perennial-task`, create a symbolic link at `/usr/local/bin/prn`, and set up your user configuration directory.

#### Uninstallation

To completely remove the application, run the `uninstall.sh` script from the directory where you originally placed the package files:

```
chmod +x uninstall.sh
sudo ./uninstall.sh
```

The script will remove all application files and will prompt you if you also wish to remove your personal task data.

### Method 2: Composer Installation

1.  **Install the Package:** Run the following command to install the package globally:

    ```
    composer global require arthurdick/perennial-task
    ```

2.  **Update Your PATH:** You must ensure Composer's global bin directory is in your system's `PATH`. Add the following line to your `~/.bashrc`:

    ```
    export PATH="$PATH:$(composer global config bin-dir --absolute -q)"
    ```

3.  **Apply the Changes:** Restart your terminal or run `source ~/.bashrc` to apply the changes.

4.  **Set Up Bash Completions (Optional):** The Composer installation does not run the setup script, so bash completions must be linked manually. First, find your system's completion directory (e.g., `/etc/bash_completion.d/` or `/usr/share/bash-completion/completions/`). Then, create a symbolic link to the `prn-completions.bash` file from the package.

    *Example command:*

    ```
    # Get the full path to the completions script
    COMPLETIONS_PATH=$(composer global config home -q)/vendor/arthurdick/perennial-task/prn-completions.bash

    # Link it to your system's completion directory (use the correct destination for your system)
    sudo ln -s "$COMPLETIONS_PATH" /etc/bash_completion.d/prn
    ```

## Usage

Once installed, you can use the `prn` command from any directory.

**General Syntax:** `prn [command] [argument]`

### **Commands**

**`prn create`**

  * Interactively prompts you to create a new task.

**`prn edit`**

  * Interactively edit an existing task. Defaults to showing `active` tasks.

**`prn complete`**

  * Mark a task as complete. Defaults to showing `reportable` (due or upcoming) tasks.

**`prn describe`**

  * Shows a detailed description and completion summary of a task. Defaults to showing `all` tasks.

**`prn history`**

  * Shows the full, detailed completion history for a single task. Defaults to showing `all` tasks.

**`prn report [date]`**

  * Generates a report of all due and upcoming tasks. Optionally run for a specific `[date]`.

**`prn help`**

  * Displays a list of available commands.

**`prn version`**

  * Displays the application's version number.

## Configuration

Perennial Task stores its configuration in a file named `config.ini`. This file is automatically created the first time you run a command.

The configuration file allows you to customize paths for your tasks, logs, and other settings. The location of this file follows the XDG Base Directory Specification:

  * It will be created in `$XDG_CONFIG_HOME/perennial-task/`.
  * If the `$XDG_CONFIG_HOME` environment variable is not set, it defaults to `~/.config/perennial-task/`.

Here is an example of the default `config.ini` file:

```ini
; Perennial Task Configuration File
; This file was automatically generated.
; You can edit these paths and settings.

tasks_dir = "/home/user/.config/perennial-task/tasks"
completions_log = "/home/user/.config/perennial-task/completions.log"
xsd_path = "/usr/local/lib/perennial-task/task.xsd"
tasks_per_page = 10
timezone = "America/Edmonton"
```

## Files and Directories

The locations of application files and user data depend on the installation method.

### Manual Installation

  * **Application Files**: `/usr/local/lib/perennial-task/`
  * **Executable**: `/usr/local/bin/prn`
  * **User Configuration & Tasks**: `$XDG_CONFIG_HOME/perennial-task/` (defaults to `~/.config/perennial-task/`)

### Composer Installation

  * **Application Files**: These are located within your Composer home directory, typically at `~/.config/composer/vendor/arthurdick/perennial-task/`. You can find the exact path by running `composer global config home`.
  * **Executable**: The `prn` executable is a symbolic link located in Composer's `bin` directory. You can find this directory by running `composer global config bin-dir --absolute -q`.
  * **User Configuration & Tasks**: `$XDG_CONFIG_HOME/perennial-task/` (defaults to `~/.config/perennial-task/`)

## Development and Testing

This project includes a comprehensive test suite built with PHPUnit to ensure code quality and prevent regressions.

### Prerequisites

  * **Composer**: The test suite dependencies are managed by [Composer](https://getcomposer.org/). Please follow the official instructions to install it if you haven't already.

### Installing Dependencies

1.  Clone the repository to your local machine.

2.  Navigate to the project's root directory in your terminal.

3.  Run the following command to install PHPUnit:

    ```
    composer install
    ```

    This will download all necessary development dependencies into a `vendor/` directory.

### Running the Test Suite

From the project's root directory, run the following command to execute the entire test suite:

```
./vendor/bin/phpunit
```

A successful run will show a series of dots followed by an "OK" message, indicating that all tests have passed.

