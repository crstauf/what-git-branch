# What Git Branch?

## How It Works

There are two approaches the plugin uses to identify the current branch:

1. Read from an external `.what-git-branch` file
1. Read data in the `.git` directory

### First Approach: `.what-git-branch` File

The first approach looks for `.what-git-branch` file in the searchable paths, and if found, uses the file's contents as the current branch name.

If this file is not found, the second approach is used.

### Second Approach: `.git/HEAD` file

The second and final approach reads the `.git/HEAD` file, and parses it for the checked out branch's name.

## Searchable Paths

The plugin by default checks `ABSPATH` and `WP_CONTENT_DIR` directories for a `.git` directory, or a `.what-git-branch` file.

More paths can be added via the `what-git-branch/set_repos/$additional_paths` filter.

## Remote Environments

To display the branch deployed to a remote environment (a.k.a. an environment without a git repository, or with a different git repository), write the branch name to the `.what-git-branch` file in one of the searchable path directories.

The following git command will return the currently checked out branch:

```
git rev-parse --abbrev-ref HEAD
```

### GitHub Action

In a GitHub Action, this may look similar to this (depending on your deploy process and directory structure):

```
jobs:
  job-name:
    steps:
      - run: echo ${GITUHB_REF#refs/heads/} > .what-git-branch
```