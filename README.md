# Perennial Task (`prn`) v1.2.2

![CI](https://github.com/arthurdick/perennial-task/actions/workflows/ci.yml/badge.svg)

Like the wood lily that graces the prairies each summer, some tasks are perennial. They return, season after season, requiring steady attention. **Perennial Task** is a simple, command-line utility built to help you cultivate these responsibilities, ensuring nothing is ever overlooked.

![Perennial Task Logo](/docs/prn_logo.png)

## Features

  * **Flexible Task Scheduling**: Perennial Task uses a simple but powerful system. A task is either **Normal** (a simple, one-off to-do) or **Scheduled** (a task with a due date).
  * **Powerful Rescheduling**: A scheduled task can be configured to automatically reschedule itself after completion. You have full control over the new due date, calculating it from either the previous **due date** (for fixed schedules like paying rent) or the **completion date** (for flexible timelines like watering the houseplants). Intervals can be set in days, weeks, months, or years.
  * **Completion History**: Every completed task retains a full history of when it was completed, allowing you to track consistency and habits over time.
  * **Interactive Editing**: Easily edit any detail of an existing task, including its name, due date, or reschedule settings, from a simple interactive menu.
  * **Intelligent Filtering**: The task selection menu is filterable, allowing you to view all tasks, only active ones, or just the reportable (due or upcoming) ones.
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

  * Interactively prompts you to create a new **Normal** or **Scheduled** task.

**`prn edit`**

  * Interactively edit an existing task, including its due date and reschedule settings.

**`prn complete`**

  * Mark a task as complete. If the task is scheduled to repeat, its next due date will be calculated and set automatically.

**`prn describe`**

  * Shows a detailed description, status, and completion summary of any single task.

**`prn history`**

  * Shows the full, detailed completion history for a single task.

**`prn report [date]`**

  * Generates a report of all due, overdue, and upcoming tasks. Optionally run for a specific `[date]`.

**`prn help`**

  * Displays a list of available commands.

**`prn version`**

  * Displays the application's version number.

### Backward Compatibility & Migration

This version of Perennial Task uses a new, more flexible format for scheduling. However, it is **fully backward compatible** with tasks created by older versions.

When you complete a task that uses a legacy format (e.g., "recurring" or "due-date" tasks), it will be **automatically and silently migrated** to the new system. Your data is safe, and your tasks will continue to work as expected.

## Configuration

Perennial Task stores its configuration in a file named `config.ini`, which is automatically created on first run. It allows you to customize paths for your tasks, logs, and other settings. The file is located in `$XDG_CONFIG_HOME/perennial-task/` (defaulting to `~/.config/perennial-task/`).

## Development and Testing

This project includes a comprehensive test suite built with PHPUnit.

### Prerequisites

  * **Composer**: Install from [getcomposer.org](https://getcomposer.org/).

### Installing Dependencies

1.  Clone the repository.
2.  Navigate to the project's root directory.
3.  Run `composer install` to download development dependencies.

### Running the Test Suite

From the project's root directory, run:

```
./vendor/bin/phpunit
```

### Code Style

This project uses `php-cs-fixer`. To automatically format your code, run:

```
./vendor/bin/php-cs-fixer fix .
```
