#!/usr/bin/env bash

_prn_completions()
{
    local cur prev words cword
    _get_comp_words_by_ref -n : cur prev words cword

    # --- Command and Flag Definitions ---
    local commands="create edit complete describe history report help version"
    local file_commands="edit complete describe history"

    # Non-interactive flags for each command
    local create_opts="--name --due --preview --reschedule-interval --reschedule-from"
    local edit_opts="--set-name --set-due --set-preview --remove-preview --set-reschedule-interval --set-reschedule-from --remove-reschedule --rename-file"
    local complete_opts="--date"
    local all_opts="${create_opts} ${edit_opts} ${complete_opts}"

    # --- Main Completion Logic ---

    # If the current word is a flag, complete from the list of all possible flags.
    if [[ "$cur" == -* ]]; then
        local applicable_opts
        case "$prev" in
            create) applicable_opts=$create_opts ;;
            edit)   applicable_opts=$edit_opts ;;
            complete) applicable_opts=$complete_opts ;;
            *)      applicable_opts=$all_opts ;;
        esac
        COMPREPLY=( $(compgen -W "${applicable_opts}" -- "${cur}") )
        return 0
    fi

    # Completion for the main command itself (the word after 'prn')
    if [[ "$prev" == "prn" ]]; then
        COMPREPLY=( $(compgen -W "${commands}" -- "${cur}") )
        return 0
    fi

    # Completion for commands that can take a task file as an argument
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
            COMPREPLY=( $(compgen -f -X "!*.[Xx][Mm][Ll]" -- "${cur}") )
            return 0
        fi

        # Fallback to standard file completion for .xml files if config is not found
        _filedir "xml"
        return 0
    fi
}

complete -F _prn_completions prn
