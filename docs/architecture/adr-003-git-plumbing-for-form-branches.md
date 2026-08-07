# ADR-003: Commit generated forms with git plumbing against a temporary index

**Status:** Accepted
**Recorded:** 2026-08-07 (retrospectively, from the implementation)

## Context

Finalising a form writes a self-contained PHP file into `forms/`. That file is
code: it runs on the server, handles a POST, validates input and writes to the
database. The governance requirement was that code reaching production goes
through the same review as hand-written code, so a finalised form should land
on a branch for review, not appear silently in the working tree.

The awkward part is _where this runs_. It executes inside a web request, in a
repository a developer may be actively working in: dirty working tree, staged
changes, sitting on some feature branch.

Two implementation routes:

1. **Porcelain** — the commands a human types: `git checkout -b`, `git add`,
   `git commit`, `git checkout -` to return.
2. **Plumbing** — the low-level object commands, building the commit directly in
   the object database.

Porcelain is the obvious choice and it is the wrong one. Every command in that
sequence mutates shared state the developer owns. `git checkout` rewrites the
working tree; if the tree is dirty the checkout fails or the change must be
stashed first. Any failure part-way leaves the developer's checkout on a branch
they didn't choose, or their work in a stash they don't know about. A web
request which can be abandoned, time out, or run concurrently with another,
has no business moving someone's HEAD.

## Decision

Build the commit entirely in the object database using plumbing commands, with
the tree assembled in a temporary index:

```
hash-object -w         write the file's content as a blob
read-tree              seed a temp index from the base branch's tree
update-index           add the blob at the target path
write-tree             snapshot the temp index as a tree object
commit-tree            create the commit, parented to the base branch tip
update-ref             point the new branch ref at the commit
```

The index steps run with `GIT_INDEX_FILE` set to a temporary path, so the
repository's real index is never opened.

## Consequences

**Positive**

- **The working tree and real index are never read or written.** The
  developer's checkout, current branch and staged changes are untouched. The
  operation is invisible until someone runs `git branch`.
- **No index lock contention.** Because it uses a private index, the operation
  can't block or be blocked by a developer's concurrent git command.
- **Branch creation is a compare-and-swap.** `update-ref` is passed an
  all-zeros old value, so it refuses if the ref came into existence between the
  existence check and the update. A concurrent duplicate request fails cleanly
  rather than clobbering.
- **No shell is involved.** git is invoked through `proc_open` with an argument
  array, so there is no shell to interpolate into. Branch names and file paths
  are additionally regex-validated, rejecting `..` segments, absolute paths,
  backslashes, control characters and shell metacharacters.
- **Pre-existing branches are explicit.** A duplicate raises
  `GitBranchExistsException` rather than a generic failure, letting the caller
  distinguish "already done" from "broken" which is what makes retry safe
  ([ADR-006](adr-006-finalisation-ordering.md)).

**Negative**

- **Higher barrier to comprehension.** Six plumbing commands need explaining
  where three porcelain ones would not. The sequence is documented in the class
  header for exactly this reason, but it remains the least approachable code in
  the project.
- **Nothing validates the committed file.** Because no checkout occurs, the
  generated PHP is committed without ever being parsed. A generator bug produces
  a branch containing a syntactically invalid file, discovered only at review.
- **The base branch must exist locally.** The configured `git_base_branch`
  (default `main`) is resolved via `rev-parse`; a repository whose branch is
  named `master` fails at runtime with "base branch does not exist". This is a
  real and easily-hit failure mode, it is configuration-dependent, and it only
  surfaces once the directory is actually a git repository.
- **Commits are unsigned** and attributed to a synthetic author
  (`Form Builder <forms@healthcare-portal.local>`). In a repository enforcing
  signed commits this would be rejected.

**Accepted trade-off**

More code and more specialist knowledge, bought in exchange for an operation
that cannot corrupt a developer's working state no matter how or when it fails.
For an integration triggered by an HTTP request, that isolation is worth the
complexity.

## Evidence

- [`code/includes/GitService.php:5-14`](../../code/includes/GitService.php#L5-L14) — the command sequence, documented at the class header
- [`code/includes/GitService.php:52-56`](../../code/includes/GitService.php#L52-L56) — refuses to overwrite an existing branch
- [`code/includes/GitService.php:68-88`](../../code/includes/GitService.php#L68-L88) — temporary index via `GIT_INDEX_FILE`
- [`code/includes/GitService.php:96-102`](../../code/includes/GitService.php#L96-L102) — `commit-tree` and the compare-and-swap `update-ref`
- [`code/includes/GitService.php:114-130`](../../code/includes/GitService.php#L114-L130) — branch name and path validation
- [`code/includes/GitService.php:134-167`](../../code/includes/GitService.php#L134-L167) — `proc_open` with an argument array, no shell
