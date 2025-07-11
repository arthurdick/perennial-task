<?php

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
        echo "Error: Could not determine user's home directory. Cannot find configuration.\n";
        exit(1);
    }

    return $home_dir . '/.config/perennial-task';
}

function initialize_perennial_task_config(): void
{
    $config_dir = get_perennial_task_config_dir();
    $config_path = $config_dir . '/config.ini';

    if (!is_file($config_path)) {
        echo "Error: Configuration file not found at '$config_path'.\n";
        echo "Please run the installer ('sudo ./install.sh') to generate it.\n";
        exit(1);
    }

    $config = parse_ini_file($config_path);

    if ($config === false) {
        echo "Error: Could not parse configuration file at '$config_path'.\n";
        exit(1);
    }

    // Define global constants for the application to use.
    define('TASKS_DIR', $config['tasks_dir']);
    define('COMPLETIONS_LOG', $config['completions_log']);
    define('XSD_PATH', $config['xsd_path']);

    $tasks_per_page = 10;
    if (isset($config['tasks_per_page']) && ctype_digit((string)$config['tasks_per_page']) && $config['tasks_per_page'] > 0) {
        $tasks_per_page = (int)$config['tasks_per_page'];
    }
    define('TASKS_PER_PAGE', $tasks_per_page);
}

// Only run the configuration initializer if we are NOT in a testing environment.
// The test environment will define its own constants.
if (!defined('PERENNIAL_TASK_TESTING')) {
    initialize_perennial_task_config();
}

