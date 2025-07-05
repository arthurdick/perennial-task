<?php

// Perennial Task - Configuration Loader

function initialize_perennial_task_config(): void
{
    $home_dir = getenv('HOME');
    if (!$home_dir) {
        if (isset($_SERVER['HOME'])) $home_dir = $_SERVER['HOME'];
        elseif (isset($_SERVER['HOMEDRIVE']) && isset($_SERVER['HOMEPATH'])) $home_dir = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
    }

    if (!$home_dir) {
        echo "Error: Could not determine user's home directory. Cannot find configuration.\n";
        exit(1);
    }

    $config_path = $home_dir . '/.config/perennial-task/config.ini';

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

    // Define global constants.
    define('TASKS_DIR', $config['tasks_dir']);
    define('COMPLETIONS_LOG', $config['completions_log']);
    define('XSD_PATH', $config['xsd_path']);

    $tasks_per_page = 10;
    if (isset($config['tasks_per_page']) && ctype_digit((string)$config['tasks_per_page']) && $config['tasks_per_page'] > 0) {
        $tasks_per_page = (int)$config['tasks_per_page'];
    }
    define('TASKS_PER_PAGE', $tasks_per_page);
}

initialize_perennial_task_config();
