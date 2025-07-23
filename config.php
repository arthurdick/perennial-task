<?php

declare(strict_types=1);

// Perennial Task - Configuration Loader

/**
 * Determines the configuration directory path based on the XDG Base Directory Specification.
 *
 * @return string The path to the perennial-task configuration directory.
 */
function get_perennial_task_config_dir(): string
{
    // Check for XDG_CONFIG_HOME environment variable.
    $xdg_config_home = getenv('XDG_CONFIG_HOME');
    if ($xdg_config_home && is_dir($xdg_config_home)) {
        return $xdg_config_home . '/perennial-task';
    }

    // Fallback to the default directory: $HOME/.config
    $home_dir = getenv('HOME');
    if (!$home_dir) {
        if (isset($_SERVER['HOME'])) {
            $home_dir = $_SERVER['HOME'];
        } elseif (isset($_SERVER['HOMEDRIVE']) && isset($_SERVER['HOMEPATH'])) {
            $home_dir = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        }
    }

    if (!$home_dir) {
        // This is a fatal error as we cannot locate the configuration.
        file_put_contents('php://stderr', "Error: Could not determine user's home directory. Cannot find configuration.\n");
        exit(1);
    }

    return $home_dir . '/.config/perennial-task';
}

/**
 * Creates a default configuration file if one does not exist.
 *
 * @param string $config_path The full path where the config file should be created.
 * @throws Exception if directories or the file cannot be created.
 */
function create_default_config(string $config_path): void
{
    $config_dir = dirname($config_path);
    $tasks_dir = $config_dir . '/tasks';

    // Create the configuration and tasks directories if they don't exist.
    if (!is_dir($config_dir)) {
        if (!mkdir($config_dir, 0775, true)) {
            throw new Exception("Error: Could not create configuration directory at '$config_dir'.");
        }
    }
    if (!is_dir($tasks_dir)) {
        if (!mkdir($tasks_dir, 0775, true)) {
            throw new Exception("Error: Could not create tasks directory at '$tasks_dir'.");
        }
    }

    // The application's installation directory, where this script and the schema reside.
    $install_dir = __DIR__;
    $xsd_path = $install_dir . '/task.xsd';
    $completions_log = $config_dir . '/completions.log';
    $system_timezone = date_default_timezone_get();

    $config_content = <<<INI
; Perennial Task Configuration File
; This file was automatically generated.
; You can edit these paths and settings.

tasks_dir = "$tasks_dir"
completions_log = "$completions_log"
xsd_path = "$xsd_path"
tasks_per_page = 10
timezone = "$system_timezone"
INI;

    if (file_put_contents($config_path, $config_content) === false) {
        throw new Exception("Error: Could not write configuration file to '$config_path'.");
    }

    echo "Notice: A new configuration file has been created at '$config_path'.\n";
}

/**
 * Gets a configuration value by first checking an environment variable,
 * then falling back to the .ini file.
 *
 * @param string $env_var_name The name of the environment variable.
 * @param array  $config The parsed .ini configuration array.
 * @param string $config_key The key to look for in the .ini array.
 * @param mixed  $default The default value if neither is found.
 * @return mixed The determined configuration value.
 */
function get_config_value(string $env_var_name, array $config, string $config_key, $default)
{
    $env_value = getenv($env_var_name);
    if ($env_value !== false) {
        return $env_value;
    }
    return $config[$config_key] ?? $default;
}


/**
 * Initializes the application configuration.
 * It finds, parses, or creates the config.ini file and defines global constants.
 */
function initialize_perennial_task_config(): void
{
    try {
        $config_dir = get_perennial_task_config_dir();
        $config_path = $config_dir . '/config.ini';

        if (!is_file($config_path)) {
            // If config doesn't exist, create a default one.
            create_default_config($config_path);
        }

        $config = parse_ini_file($config_path);

        if ($config === false) {
            throw new Exception("Error: Could not parse configuration file at '$config_path'.");
        }

        // Determine timezone, prioritizing environment variable.
        $timezone = get_config_value('PERENNIAL_TIMEZONE', $config, 'timezone', date_default_timezone_get());
        if (!empty($timezone) && in_array($timezone, timezone_identifiers_list())) {
            date_default_timezone_set($timezone);
        }

        // Define global constants, prioritizing environment variables.
        define('TASKS_DIR', get_config_value('PERENNIAL_TASKS_DIR', $config, 'tasks_dir', $config_dir . '/tasks'));
        define('COMPLETIONS_LOG', get_config_value('PERENNIAL_COMPLETIONS_LOG', $config, 'completions_log', $config_dir . '/completions.log'));
        define('XSD_PATH', get_config_value('PERENNIAL_XSD_PATH', $config, 'xsd_path', __DIR__ . '/task.xsd'));

        $tasks_per_page_raw = get_config_value('PERENNIAL_TASKS_PER_PAGE', $config, 'tasks_per_page', 10);
        $tasks_per_page = 10;
        if (ctype_digit((string)$tasks_per_page_raw) && $tasks_per_page_raw > 0) {
            $tasks_per_page = (int)$tasks_per_page_raw;
        }
        define('TASKS_PER_PAGE', $tasks_per_page);

    } catch (Exception $e) {
        file_put_contents('php://stderr', $e->getMessage() . "\n");
        exit(1);
    }
}

// Only run the configuration initializer if we are NOT in a testing environment.
// The test environment will define its own constants.
if (!defined('PERENNIAL_TASK_TESTING')) {
    initialize_perennial_task_config();
}
