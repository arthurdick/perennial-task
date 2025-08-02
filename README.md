# Perennial Task (`prn`) v1.5.0

![CI](https://github.com/arthurdick/perennial-task/actions/workflows/ci.yml/badge.svg)

Like the wood lily that graces the prairies each summer, some tasks are perennial. They return, season after season, requiring steady attention. **Perennial Task** is a simple, command-line utility built to help you cultivate these responsibilities, ensuring nothing is ever overlooked.

![Perennial Task Logo](/docs/prn_logo.png)

![Perennial Task Demo](/docs/demo.svg)

## Features

  * **Flexible Task Scheduling**: Perennial Task uses a simple but powerful system. A task is either **Normal** (a simple, one-off to-do) or **Scheduled** (a task with a due date).
  * **Powerful Rescheduling**: A scheduled task can be configured to automatically reschedule itself after completion. You have full control over the new due date, calculating it from either the previous **due date** (for fixed schedules like paying rent) or the **completion date** (for flexible timelines like watering the houseplants). Intervals can be set in days, weeks, months, or years.
  * **Task Prioritization**: Assign a numerical **priority** to your tasks. The report view will sort tasks first by their status (overdue, due today, upcoming) and then by their priority, ensuring the most important items always appear first.
  * **Completion History**: Every completed task retains a full history of when it was completed, allowing you to track consistency and habits over time.
  * **Interactive and Non-Interactive Modes**: Create and edit tasks through a user-friendly interactive menu or automate your workflow with powerful command-line flags.
  * **Intelligent Filtering**: The task selection menu is filterable, allowing you to view all tasks, only active ones, or just the reportable (due or upcoming) ones.
  * **Command-Line Driven**: Designed for efficient use within a terminal environment.

## Installation and Usage

Perennial Task can be used in three different ways, depending on your needs.

### Method 1: PHAR Release (Recommended for most users)

This method uses a single, executable `.phar` file that contains the entire application. It is the easiest way to get started.

1.  **Download the `prn.phar`** file from the latest release on GitHub.
2.  **Make it executable:**
    ```
    chmod +x prn.phar
    ```
3.  **(Optional) Move it into your PATH** to make it accessible from anywhere:
    ```
    sudo mv prn.phar /usr/local/bin/prn
    ```
4.  **Run it:**
    ```
    # If you moved it into your PATH
    prn help

    # If you did not
    ./prn.phar help
    ```

### Method 2: Composer (Recommended for PHP developers)

If you are a PHP developer, you can install Perennial Task globally using Composer.

1.  **Install the Package:**
    ```
    composer global require arthurdick/perennial-task
    ```
2.  **Update Your PATH:** Ensure Composer's global bin directory is in your system's `PATH`. Add the following line to your `~/.bashrc` or `~/.zshrc`:
    ```
    export PATH="$PATH:$(composer global config bin-dir --absolute -q)"
    ```
3.  **Apply the Changes:** Restart your terminal or run `source ~/.bashrc`.
4.  **Set Up Bash Completions (Optional):**
    ```
    COMPLETIONS_PATH=$(composer global config home -q)/vendor/arthurdick/perennial-task/prn-completions.bash
    sudo ln -s "$COMPLETIONS_PATH" /etc/bash_completion.d/prn
    ```

### Method 3: Manual Installation from Source (Recommended for contributors)

This method is for developers who want to work on the source code.

1.  **Platform & Dependencies:** This utility is designed for Linux environments. Ensure you have PHP version 7.4 or higher installed, with the `SimpleXML` and `DOM` extensions enabled.
2.  **Clone the repository and install dependencies:**
    ```
    git clone https://github.com/arthurdick/perennial-task.git
    cd perennial-task
    composer install
    ```
3.  **Run the application** using the `prn` executable in the project root:
    ```
    ./prn help
    ```

## Usage

Once installed, you can use the `prn` command from any directory.

**General Syntax:** `prn [command] [options] [argument]`

### **Commands**

**`prn create`**

  * Interactively prompts you to create a new **Normal** or **Scheduled** task. Can also be used non-interactively with flags.

**`prn edit <task_file>`**

  * Interactively or non-interactively edit an existing task.

**`prn complete <task_file>`**

  * Mark a task as complete. If the task is scheduled to repeat, its next due date will be calculated and set automatically.

**`prn describe <task_file>`**

  * Shows a detailed description, status, and completion summary of any single task.

**`prn history <task_file>`**

  * Shows the full, detailed completion history for a single task.

**`prn report [date]`**

  * Generates a report of all due, overdue, and upcoming tasks. Optionally run for a specific `[date]`.

**`prn help [command]`**

  * Displays a list of available commands or detailed help for a specific command.

**`prn version`**

  * Displays the application's version number.

### **Non-Interactive Switches**

You can use the following flags to bypass the interactive menus and manage tasks in scripts.

#### `create`

  * `--name <name>`: The name of the task. **(Required for non-interactive use)**
  * `--due <YYYY-MM-DD>`: The date the task is due.
  * `--priority <int>`: Set the task's priority (e.g., -2, 0, 10). Defaults to 0.
  * `--preview <days>`: The number of days in advance to show the task in reports.
  * `--reschedule-interval <interval>`: The interval to reschedule the task (e.g., '7 days', '1 month').
  * `--reschedule-from <basis>`: The basis for rescheduling ('due\_date' or 'completion\_date').

*Example:*
`prn create --name "Pay monthly internet bill" --due 2025-08-01 --priority 10 --reschedule-interval "1 month" --reschedule-from due_date`

#### `edit`

  * `--set-name <name>`: Set a new name for the task.
  * `--rename-file`: Rename the task file to match the new name (can only be used with `--set-name`).
  * `--set-due <YYYY-MM-DD>`: Set a new due date for the task.
  * `--set-priority <int>`: Set a new priority for the task.
  * `--set-preview <days>`: Set the number of preview days.
  * `--remove-preview`: Remove the preview setting from the task.
  * `--set-reschedule-interval <interval>`: Set the reschedule interval.
  * `--set-reschedule-from <basis>`: Set the reschedule basis ('due\_date' or 'completion\_date').
  * `--remove-reschedule`: Remove all reschedule settings from the task.

*Example:*
`prn edit tasks/my_task.xml --set-due 2025-09-15 --set-priority 5`

#### `complete`

  * `--date <YYYY-MM-DD>`: The date the task was completed (defaults to today).

*Example:*
`prn complete tasks/water_plants.xml --date 2025-07-20`

### Exit Codes

The application uses the following exit codes to indicate success or the type of error encountered:

* **0**: Success.
* **1**: A general or unknown error occurred.
* **10**: Invalid command-line argument, option, or value.
* **20**: A file system error occurred (e.g., file not found, permission denied).
* **30**: An invalid file format or configuration error was found.
* **40**: A prerequisite check failed (e.g., a required PHP extension is missing).

### Backward Compatibility & Migration

This version of Perennial Task uses a new, more flexible format for scheduling. However, it is **fully backward compatible** with tasks created by older versions.

When you complete or edit a task that uses a legacy format, it will be **automatically and silently migrated** to the new system. Your data is safe, and your tasks will continue to work as expected.

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
composer test
```

### Code Style

This project uses `php-cs-fixer`. To automatically format your code, run:

```
composer fix
```
