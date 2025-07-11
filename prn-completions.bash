# bash completion for prn

_prn_complete()
{
    local cur prev words cword
    _get_comp_words_by_ref cur prev words cword

    # Define the list of main commands
    local commands="create edit complete describe report help version"
    # Define which commands can be followed by a file path
    local file_commands="edit complete describe"

    # If we are completing the first argument (the command itself)
    if [ "$cword" -eq 1 ]; then
        COMPREPLY=( $(compgen -W "$commands" -- "$cur") )
        return 0
    fi

    # If the previous argument is a command that takes a file
    if [[ " ${file_commands[*]} " =~ " ${prev} " ]]; then
        # Determine config file path based on XDG Base Directory Spec
        local config_dir
        if [[ -n "$XDG_CONFIG_HOME" && -d "$XDG_CONFIG_HOME" ]]; then
            config_dir="$XDG_CONFIG_HOME/perennial-task"
        else
            config_dir="$HOME/.config/perennial-task"
        fi
        
        local config_file="$config_dir/config.ini"

        if [ -f "$config_file" ]; then
            # Read the tasks_dir from the config file, trim whitespace and quotes
            local tasks_dir_raw=$(grep 'tasks_dir' "$config_file" | cut -d '=' -f 2)
            # Use xargs to trim whitespace and tr to remove quotes
            local tasks_dir=$(echo "$tasks_dir_raw" | xargs | tr -d '"')
            
            # Safely expand the tilde (~) character to the user's home directory
            tasks_dir="${tasks_dir/#\~/$HOME}"

            if [ -d "$tasks_dir" ]; then
                # Generate a list of all .xml files in the tasks directory
                local task_files
                task_files=$(find "$tasks_dir" -maxdepth 1 -type f -name "*.xml")
                # Provide the list of files as completion suggestions
                COMPREPLY=( $(compgen -W "$task_files" -- "$cur") )
            fi
        fi
        return 0
    fi
}

# Register the completion function for the 'prn' command
complete -F _prn_complete prn

