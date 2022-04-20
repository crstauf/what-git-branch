# What Git Branch?

## Searchable Paths

The plugin by default checks `ABSPATH` and `WP_CONTENT_DIR` directories for a `.git` directory or the `.what-git-branch` file (described below).

## Remote Environments

To display the branch deployed to a remote environment (a.k.a. an environment without a git repository), write the branch name to the `.what-git-branch` file in one of the searchable path directories.

The following git command will return the currently checked out branch:

```
git rev-parse --abbrev-ref HEAD
```

In a GitHub Action, this may look similar to this (depending on your deploy process and directory structure):

```
jobs:
  job-name:
    steps:
      - run: git rev-parse --abbrev-ref HEAD > .what-git-branch
```