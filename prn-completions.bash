#!/usr/bin/env bash

_prn_completions()
{
    local cur prev words cword
    _get_comp_words_by_ref -n : cur prev words cword

    local commands="create edit complete describe history report help version"
    local file_commands="edit complete describe history"

    # Completion for the main command
    if [[ "$prev" == "prn" ]]; then
        COMPREPLY=( $(compgen -W "${commands}" -- "${cur}") )
        return 0
    fi

    # Completion for commands that take a task file
    if [[ " ${file_commands} " =~ " ${prev} " ]]; then
        local config_dir tasks_dir config_file
        
        # Determine config directory based on XDG spec
        if [[ -n "$XDG_CONFIG_HOME" && -d "$XDG_CONFIG_HOME" ]]; then
            config_dir="$XDG_CONFIG_HOME/perennial-task"
        else
            config_dir="$HOME/.config/perennial-task"
        fi
        
        config_file="$config_dir/config.ini"

        # Read tasks_dir from config.ini
        if [[ -f "$config_file" ]]; then
            tasks_dir=$(grep -oP 'tasks_dir\s*=\s*"\K[^"]+' "$config_file")
        fi

        # If we have a valid tasks directory, provide completions from it
        if [[ -n "$tasks_dir" && -d "$tasks_dir" ]]; then
            local task_files
            task_files=$(find "$tasks_dir" -maxdepth 1 -type f -name "*.xml")
            COMPREPLY=( $(compgen -W "$(echo ${task_files} | tr '\n' ' ')" -- ${cur}) )
            return 0
        fi

        # Fallback to standard file completion if config is not found
        _filedir "xml"
        return 0
    fi
}

complete -F _prn_completions prn
