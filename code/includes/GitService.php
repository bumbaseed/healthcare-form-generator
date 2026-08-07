<?php
/**
 * Creates a git branch per finalized form so each form change goes through
 * normal code review before deployment. Called from FormDefinition::
 * finalizeForm() after a form's table and PHP file have been written.
 *
 * Flow:
 *   hash-object -w - stage the file content as a blob in the object DB
 *   read-tree -seed a temp index from the base branch
 *   update-index - add the blob at the target path
 *   write-tree - snapshot the index as a tree
 *   commit-tree - create the commit with parent = base branch tip
 *   update-ref - create the branch ref pointing at the commit
 */

class GitService
{
    private string $repoRoot;
    private string $gitBinary;
    private string $authorName;
    private string $authorEmail;

    public function __construct(
        string $repoRoot,
        string $gitBinary = 'git',
        string $authorName = 'Form Builder',
        string $authorEmail = 'forms@healthcare-portal.local'
    ) {
        $this->repoRoot = $repoRoot;
        $this->gitBinary = $gitBinary;
        $this->authorName = $authorName;
        $this->authorEmail = $authorEmail;
    }

    /**
     * Create $branchName at $baseBranch with one extra commit that adds $fileRelPath using the file's current contents on disk. Returns the new commit SHA. Throws on any failure. If the branch already exists, throws GitBranchExistsException so the caller can decide whether to treat that as success on retries.
     */
    public function createBranchWithFile(
        string $branchName,
        string $fileRelPath,
        string $commitMessage,
        string $baseBranch = 'main'
    ): string {
        $this->assertValidBranchName($branchName);
        $this->assertValidRelativePath($fileRelPath);

        $absFile = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fileRelPath);
        if (!is_file($absFile)) {
            throw new RuntimeException("GitService: file not found for commit: {$fileRelPath}");
        }

        // Refuse if the branch already exists rather than silently overwriting a developer's work.
        $existing = $this->run(['rev-parse', '--verify', '--quiet', "refs/heads/{$branchName}"], expectSuccess: false);
        if ($existing['exit'] === 0 && trim($existing['stdout']) !== '') {
            throw new GitBranchExistsException("Branch '{$branchName}' already exists at " . trim($existing['stdout']));
        }

        // Resolve base branch tip and tree.
        $baseSha = trim($this->runExpect(['rev-parse', '--verify', "refs/heads/{$baseBranch}"])['stdout']);
        if ($baseSha === '') {
            throw new RuntimeException("GitService: base branch '{$baseBranch}' does not exist");
        }
        $baseTree = trim($this->runExpect(['rev-parse', "{$baseSha}^{tree}"])['stdout']);

        // Write the file content as a blob in the object DB.
        $blobSha = trim($this->runExpect(['hash-object', '-w', '--', $absFile])['stdout']);

        // Build the new tree in a temp index so the working tree stays put.
        $tmpIndex = tempnam(sys_get_temp_dir(), 'gitidx_');
        if ($tmpIndex === false) {
            throw new RuntimeException('GitService: could not allocate temporary index file');
        }
        // tempnam() creates an empty file, but git wants to create the index itself, so unlink the placeholder first.
        @unlink($tmpIndex);

        $indexEnv = ['GIT_INDEX_FILE' => $tmpIndex];
        try {
            $this->runExpect(['read-tree', $baseTree], $indexEnv);
            $this->runExpect(
                ['update-index', '--add', '--cacheinfo', "100644,{$blobSha},{$fileRelPath}"],
                $indexEnv
            );
            $newTree = trim($this->runExpect(['write-tree'], $indexEnv)['stdout']);
        } finally {
            if (is_file($tmpIndex)) {
                @unlink($tmpIndex);
            }
        }

        $commitEnv = [
            'GIT_AUTHOR_NAME' => $this->authorName,
            'GIT_AUTHOR_EMAIL' => $this->authorEmail,
            'GIT_COMMITTER_NAME' => $this->authorName,
            'GIT_COMMITTER_EMAIL' => $this->authorEmail,
        ];
        $commitSha = trim($this->runExpect(
            ['commit-tree', $newTree, '-p', $baseSha, '-m', $commitMessage],
            $commitEnv
        )['stdout']);

        // Point the branch ref at the new commit. The explicit null old-value makes update-ref refuse if another request created the branch between the existence check above and this call.
        $this->runExpect(['update-ref', "refs/heads/{$branchName}", $commitSha, str_repeat('0', 40)]);

        return $commitSha;
    }

    public function isRepository(): bool
    {
        $res = $this->run(['rev-parse', '--is-inside-work-tree'], expectSuccess: false);
        return $res['exit'] === 0 && trim($res['stdout']) === 'true';
    }


    private function assertValidBranchName(string $name): void
    {
        // Allow one optional "<prefix>/" segment followed by slug chars. Blocks ".." segments, leading or trailing "/", control chars, spaces, and shell metacharacters.
        if (!preg_match('#^[a-z0-9][a-z0-9_\-]*(?:/[a-z0-9][a-z0-9_\-]*)?$#', $name)) {
            throw new InvalidArgumentException("GitService: invalid branch name '{$name}'");
        }
    }

    private function assertValidRelativePath(string $path): void
    {
        if ($path === '' || str_contains($path, '..') || $path[0] === '/' || str_contains($path, '\\')) {
            throw new InvalidArgumentException("GitService: invalid relative path '{$path}'");
        }
        if (!preg_match('#^[a-zA-Z0-9][a-zA-Z0-9_\-./]*$#', $path)) {
            throw new InvalidArgumentException("GitService: disallowed characters in path '{$path}'");
        }
    }


    /** @return array{exit:int,stdout:string,stderr:string} */
    private function run(array $args, array $env = [], bool $expectSuccess = true): array
    {
        $cmd = array_merge([$this->gitBinary], $args);

        // Inherit the current env and overlay any extra vars. Passing null would inherit but couldn't add GIT_INDEX_FILE and similar, so merge explicitly.
        $baseEnv = getenv();
        $fullEnv = array_merge($baseEnv, $env);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptors, $pipes, $this->repoRoot, $fullEnv);
        if (!is_resource($proc)) {
            throw new RuntimeException('GitService: failed to start git: ' . implode(' ', $cmd));
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        if ($expectSuccess && $exit !== 0) {
            throw new RuntimeException(
                'GitService: `git ' . implode(' ', $args) . "` failed (exit {$exit}): " . trim($stderr)
            );
        }

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /** @return array{exit:int,stdout:string,stderr:string} */
    private function runExpect(array $args, array $env = []): array
    {
        return $this->run($args, $env, expectSuccess: true);
    }
}

class GitBranchExistsException extends RuntimeException
{
}
