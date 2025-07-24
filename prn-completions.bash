#!/usr/bin/env bash

_prn_completions()
{
    local cur prev words cword
    _get_comp_words_by_ref -n : cur prev words cword

    # --- Command and Flag Definitions ---
    local commands="create edit complete describe history report help version"
    local file_commands="edit complete describe history"
    local create_opts="--name --due --preview --reschedule-interval --reschedule-from --priority"
    local edit_opts="--set-name --set-due --set-preview --remove-preview --set-reschedule-interval --set-reschedule-from --remove-reschedule --rename-file --set-priority"
    local complete_opts="--date"
    local all_opts="${create_opts} ${edit_opts} ${complete_opts}"

    # --- Main Completion Logic ---

    # 1. Complete non-interactive flags (e.g., --name, --due)
    if [[ "$cur" == -* ]];
    then
        local applicable_opts
        case "${words[1]}" in
            create)   applicable_opts=$create_opts ;;
            edit)     applicable_opts=$edit_opts ;;
            complete) applicable_opts=$complete_opts ;;
            *)        applicable_opts=$all_opts ;;
        esac
        COMPREPLY=( $(compgen -W "${applicable_opts}" -- "${cur}") )
        return 0
    fi

    # 2. Complete the main command (e.g., create, edit)
    if [[ "$prev" == "prn" ]];
    then
        COMPREPLY=( $(compgen -W "${commands}" -- "${cur}") )
        return 0
    fi

    # 3. Complete task file paths
    if [[ " ${file_commands} " =~ " ${words[1]} " ]];
    then
        # --- Single Completion Check ---
        # If a .xml file is already on the command line, don't offer more file suggestions.
        for word in "${words[@]}"; do
            if [[ "$word" == *.xml ]];
            then
                return 0
            fi
        done
        # --- End Single Completion Check ---

        local tasks_dir

        # Read tasks_dir if it exists
        tasks_dir=$(prn --get-tasks-dir)

        # If we have a valid tasks directory, find the .xml files within it
        if [[ -n "$tasks_dir" && -d "$tasks_dir" ]];
        then
            local task_files
            task_files=$(find "$tasks_dir" -maxdepth 1 -type f -name "*.xml")
            COMPREPLY=( $(compgen -W "${task_files}" -- "${cur}") )
            return 0
        fi

        # Fallback to standard .xml file completion in the current directory if config is not found
        _filedir "xml"
        return 0
    fi
}

# Register the completion function for the 'prn' command
complete -F _prn_completions prn
