# Roadmap Notes

This directory stores roadmap documents and implementation notes that map to major epics or large GitHub issues in this repository.

## Purpose

Files in this directory are intended to:

- preserve implementation direction across phases or milestones
- bridge GitHub issues with practical execution plans inside the codebase
- capture more detailed work breakdowns than the issue description alone
- stay available across machines after cloning the repository

## Naming Convention

- prefer the GitHub issue key at the beginning of the filename
- add a short descriptive slug for the topic
- add an optional language suffix when needed

Examples:

- `opas-0069-ai-coding-control-system-vi.md`
- `opas-0102-issue-and-branch-convention-en.md`

## Notes

- this is not a temporary scratch directory
- do not use this directory for debug logs, transient notes, or throwaway output
- files here may be committed and shared across environments when they provide durable planning value
- roadmap files are primarily operator-facing planning documents
- assistants should inspect roadmap files when the active task depends on phase boundaries, delivery intent, or issue-level planning context
- roadmap files are not universal mandatory input for every coding change

## Current Roadmap Files

- `opas-0069-ai-coding-control-system-vi.md`: Telegram/local/multi-machine AI coding control system roadmap.
- `opas-0101-realtime-audio-capture-service-vi.md`: OPAS-0100 realtime translation phase map, with OPAS-0101 audio capture implementation detail.
