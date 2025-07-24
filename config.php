<?php

declare(strict_types=1);

// Perennial Task - Configuration Loader

/**
 * Determines the configuration directory path based on the XDG Base Directory Specification.
 *
 * @return string|null The path to the perennial-task configuration directory, or null if it cannot be determined.
 */
function get_perennial_task_config_dir(): ?string
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
        return null;
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
            throw new Exception("Error: Could not create configuration directory at '$config_dir'.", 20);
        }
    }
    if (!is_dir($tasks_dir)) {
        if (!mkdir($tasks_dir, 0775, true)) {
            throw new Exception("Error: Could not create tasks directory at '$tasks_dir'.", 20);
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
        throw new Exception("Error: Could not write configuration file to '$config_path'.", 20);
    }

    file_put_contents('php://stderr', "Notice: A new configuration file has been created at '$config_path'.\n");
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
        $config = [];
        $config_dir = null;

        // Only try to load config files if environment variables are not sufficient.
        // We use PERENNIAL_TASKS_DIR as the key indicator.
        if (getenv('PERENNIAL_TASKS_DIR') === false) {
            $config_dir = get_perennial_task_config_dir();

            if ($config_dir !== null) {
                $config_path = $config_dir . '/config.ini';

                if (!is_file($config_path)) {
                    create_default_config($config_path);
                }

                $parsed_config = parse_ini_file($config_path);
                if ($parsed_config === false) {
                    throw new Exception("Error: Could not parse configuration file at '$config_path'.", 30);
                }
                $config = $parsed_config;
            }
        }

        // Determine timezone, prioritizing environment variable.
        $timezone = get_config_value('PERENNIAL_TIMEZONE', $config, 'timezone', date_default_timezone_get());
        if (!empty($timezone) && in_array($timezone, timezone_identifiers_list())) {
            date_default_timezone_set($timezone);
        }

        // Define global constants, prioritizing environment variables.
        $tasks_dir_default = ($config_dir !== null) ? $config_dir . '/tasks' : '';
        define('TASKS_DIR', get_config_value('PERENNIAL_TASKS_DIR', $config, 'tasks_dir', $tasks_dir_default));

        $completions_log_default = ($config_dir !== null) ? $config_dir . '/completions.log' : '/tmp/completions.log';
        define('COMPLETIONS_LOG', get_config_value('PERENNIAL_COMPLETIONS_LOG', $config, 'completions_log', $completions_log_default));

        $xsd_path_default = __DIR__ . '/task.xsd';
        define('XSD_PATH', get_config_value('PERENNIAL_XSD_PATH', $config, 'xsd_path', $xsd_path_default));

        $tasks_per_page_raw = get_config_value('PERENNIAL_TASKS_PER_PAGE', $config, 'tasks_per_page', 10);
        $tasks_per_page = (ctype_digit((string)$tasks_per_page_raw) && $tasks_per_page_raw > 0) ? (int)$tasks_per_page_raw : 10;
        define('TASKS_PER_PAGE', $tasks_per_page);

        // Final sanity check for the most critical path
        if (empty(TASKS_DIR)) {
            throw new Exception("Error: Tasks directory is not defined. Please set PERENNIAL_TASKS_DIR or configure it via config.ini.", 30);
        }

    } catch (Exception $e) {
        file_put_contents('php://stderr', $e->getMessage() . "\n");
        exit($e->getCode() ?: 1); // Use the exception's code, or default to 1
    }
}

// Only run the configuration initializer if we are NOT in a testing environment.
if (!defined('PERENNIAL_TASK_TESTING')) {
    initialize_perennial_task_config();
}
