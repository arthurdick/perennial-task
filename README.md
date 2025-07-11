# Perennial Task (`prn`) v1.0.4

Like the wood lily that graces the prairies each summer, some tasks are perennial. They return, season after season, requiring steady attention. **Perennial Task** is a simple, command-line utility built to help you cultivate these recurring responsibilities, ensuring nothing is ever overlooked.

![Perennial Task Logo](prn_logo.png)

## Features

* **Create Tasks**: Quickly create normal, due-date, or recurring tasks.
* **Edit Tasks**: Interactively edit any detail of an existing task.
* **Complete Tasks**: Mark tasks as complete, which either deletes them or updates their next due/completion date.
* **Describe Tasks**: Get a detailed, human-readable description of any single task.
* **Run Reports**: Generate a report of all tasks that are due, overdue, or upcoming.
* **Paginated Selection**: Easily navigate and select from a large number of tasks.
* **Command-Line Driven**: Designed for efficient use within a terminal environment.

## Installation

1.  **Platform & Dependencies:** This utility is designed for Linux environments. Ensure you have PHP version 7.4 or higher installed, with the `SimpleXML` and `DOM` extensions enabled (these are usually included by default). You can check this by running `php -v` and `php -m`.
2.  **Gather Files:** Place all the packaged files (`prn`, `install.sh`, `uninstall.sh`, `prn-completions.bash`, `task.xsd`, and all `*.php` scripts) into a single directory.
3.  **Run the Installer:** From within that directory, make the installation script executable and run it with `sudo`:
    ```
    chmod +x install.sh
    sudo ./install.sh
    ```
    The installer will copy the application files to `/usr/local/lib/perennial-task`, create your user configuration directory at `~/.config/perennial-task/`, and create a symbolic link at `/usr/local/bin/prn`.

## Uninstallation

To completely remove the application, run the `uninstall.sh` script from the directory where you originally placed the package files:

```
chmod +x uninstall.sh
sudo ./uninstall.sh
```

The script will remove all application files and will prompt you if you also wish to remove your personal task data.

## Usage

Once installed, you can use the `prn` command from any directory.

**General Syntax:** `prn [command] [argument]`

### **Commands**

**`prn create`**
* Interactively prompts you to create a new task.

**`prn edit [task_file]`**
* Interactively edit an existing task via a paginated menu, or edit `[task_file]` directly.

**`prn complete [task_file]`**
* Mark a task as complete via a paginated menu, or complete `[task_file]` directly.

**`prn describe [task_file]`**
* Shows a detailed description of a task, selected from a menu or specified by `[task_file]`.

**`prn report [date]`**
* Generates a report of all due and upcoming tasks. Optionally run for a specific `[date]`.

**`prn help`**
* Displays a list of available commands.

**`prn version`**
* Displays the application's version number.

## Development and Testing

This project includes a comprehensive test suite built with PHPUnit to ensure code quality and prevent regressions.

### Prerequisites

* **Composer**: The test suite dependencies are managed by [Composer](https://getcomposer.org/). Please follow the official instructions to install it if you haven't already.

### Installing Dependencies

1. Clone the repository to your local machine.

2. Navigate to the project's root directory in your terminal.

3. Run the following command to install PHPUnit:

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

## Files and Directories

* **Application Files**: `/usr/local/lib/perennial-task/`
* **User Configuration & Tasks**: `~/.config/perennial-task/`
* **Executable**: `/usr/local/bin/prn`
