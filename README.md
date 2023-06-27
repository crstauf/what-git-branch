# What Git Branch?

## How It Works

There are two approaches the plugin uses to identify the current branch:

1. Read from an external `.what-git-branch` file
1. Read data in the `.git` directory

### First Approach: `.what-git-branch` File

The first approach looks for `.what-git-branch` (defined in `Repository::EXTERNAL_FILE`) file in the searchable paths, and if found, uses the file's contents as the current head reference.

If this file is not found, the second approach is used.

### Second Approach: `.git/HEAD` file

The second and final approach reads the `.git/HEAD` file, and parses it for the checked out branch's name.

## Searchable Paths

The plugin by default checks `ABSPATH` and `WP_CONTENT_DIR` directories for a `.git` directory, or a `.what-git-branch` file.

More paths can be added via the `what-git-branch/get_dirs_from_scan()/$addtl_paths` filter.

## Remote Environments

To display the branch deployed to a remote environment (a.k.a. an environment without a git repository, or with a different git repository), write the branch name to the `.what-git-branch` file in one of the searchable path directories.

The following git command will return the currently checked out branch:

```bash
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

## Filters

### `what-git-branch/primary()/$dir`

Set the directory for the primary repository. The primary repository will be highlighted in the Dashboard widget, and is displayed in the admin bar.

### `what-git-branch/get_dirs_from_filter()/$dirs`

Hard-code the directories with repositories to improve performance by preventing filesystem scanning.

```php
add_filter( 'what-git-branch/get_dirs_from_filter()/$dirs', static function ( array $dirs ) : array {
	$dirs = array(
		ABSPATH,
		WP_PLUGIN_DIR . '/what-git-branch',
	);
} );
```

### `what-git-branch/get_dirs_from_scan()/$addtl_paths`

Additional paths outside of `WP_CONTENT_DIR` to scan for repositories.

### `what-git-branch/when_can_scan()`

Identifying key for context to perform directory scan. Function `\What_Git_Branch\Plugin::when_can_scan()` will output one of the following:

```
never
http-request
manually
cli
heartbeat
```

### `what-git-branch/cache_store()`

Change directories caching store between [options](https://developer.wordpress.org/apis/options/) and [transients](https://developer.wordpress.org/apis/transients/).

Expected values:

```
option
transient
```

### `what-git-branch/set_dirs_to_cache()/$expiration`

Expiration of transient for directories cache.

### `what-git-branch/dashboard/foreach/continue`

Skip printing repository(ies) in Dashboard widget.

### `what-git-branch/repository/name`

Set name of repository; defaults to repository's directory's name.

### `what-git-branch/repository/get_head_ref()/commit`

Filter the reference for `HEAD` for repository.

### `what-git-branch/repository/get_branch()/$branch`

Change branch name for repository.

### `what-git-branch/repository/get_github_url()/$github_repo`

Add link to repository on GitHub by setting user and repository name: `user/repository`.

```php
add_filter( 'what-git-branch/get_github_url()/$github_repo', static function ( $github_repo, $path ) {
	if ( false === stripos( $path, 'what-git-branch' ) ) {
		return $github_repo;
	}

	return 'crstauf/what-git-branch';
}, 10, 2 );
```

## CLI Commands

### `wp whatgitbranch list`

List the repositories, directories, and head refs.

### `wp whatgitbranch directories scan`

If permitted, scan the filesystem for repositories.

### `wp whatgitbranch directories clear-cache`

Clear the directories cache.

### `wp whatgitbranch primary identify ref`

Print the head ref of the primary repository.

### `wp whatgitbranch primary identify path`

Print the path of the primary repository.

### `wp whatgitbranch primary set {$text}`

Set head ref of primary repository to `$text`.

### `wp whatgitbranch primary reset`

Reset head ref of primary repository (remove external file and use git data).