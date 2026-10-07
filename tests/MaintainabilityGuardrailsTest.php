<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/tools/process-support.php';

final class MaintainabilityGuardrailsTest extends TestCase
{
    private string $fixture = '';

    protected function setUp(): void
    {
        $root = dirname(__DIR__);
        $listing = runBoundedMaintainerProcess(
            ['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'],
            $root,
            null,
            30_000,
            1_048_576,
            65_536,
        );
        self::assertSame(0, $listing['exit_code'], $listing['stderr']);
        self::assertNotSame('', $listing['stdout']);
        $this->fixture = sys_get_temp_dir() . '/phpthis-maintainability-' . bin2hex(random_bytes(8));
        if (!mkdir($this->fixture, 0700)) {
            throw new RuntimeException('Cannot create the guard fixture.');
        }

        foreach (explode("\0", rtrim($listing['stdout'], "\0")) as $relativePath) {
            $source = $root . '/' . $relativePath;
            if (is_link($source)) {
                throw new RuntimeException('Guard fixtures must use repository-owned files.');
            }
            if (!is_file($source)) {
                continue;
            }
            $destination = $this->fixture . '/' . $relativePath;
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) {
                throw new RuntimeException('Cannot create a guard fixture directory.');
            }
            $mode = fileperms($source);
            if ($mode === false || !copy($source, $destination) || !chmod($destination, $mode & 0777)) {
                throw new RuntimeException('Cannot copy a guard fixture file.');
            }
        }

        $this->assertGuardPasses();
    }

    protected function tearDown(): void
    {
        if ($this->fixture === '' || !is_dir($this->fixture)) {
            return;
        }
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->fixture, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                throw new RuntimeException('Unexpected guard fixture entry.');
            }
            if ($entry->isDir() && !$entry->isLink()) {
                $removed = rmdir($entry->getPathname());
            } else {
                $removed = unlink($entry->getPathname());
            }
            if (!$removed) {
                throw new RuntimeException('Cannot remove a guard fixture entry.');
            }
        }
        if (!rmdir($this->fixture)) {
            throw new RuntimeException('Cannot remove the guard fixture.');
        }
    }

    public function testHarmlessCoreWhitespaceDoesNotFailValidity(): void
    {
        $path = $this->fixture . '/src/Application.php';
        self::assertSame(3, file_put_contents($path, "\n\n\n", FILE_APPEND));
        $this->assertGuardPasses();
    }

    public function testFileCountsDoNotPreventDocumentationConsolidation(): void
    {
        $markdown = 0;
        $php = 0;
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->fixture, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                throw new RuntimeException('Unexpected guard fixture entry.');
            }
            if ($entry->getExtension() === 'md') {
                $markdown++;
            }
            if ($entry->getExtension() === 'php' || $entry->getPathname() === $this->fixture . '/bin/phpthis') {
                $php++;
            }
        }
        self::assertTrue(mkdir($this->fixture . '/tools/maintainability-fixture', 0700));
        for ($index = $php; $index <= $markdown; $index++) {
            $this->write('tools/maintainability-fixture/' . $index . '.php', "<?php\n\ndeclare(strict_types=1);\n");
        }
        $this->write('tools/maintainability-fixture/first.md', "First maintenance note.\n");
        $this->write('tools/maintainability-fixture/second.md', "Second maintenance note.\n");
        $this->assertGuardPasses();
        $this->write('tools/maintainability-fixture/first.md', "First maintenance note.\nSecond maintenance note.\n");
        self::assertTrue(unlink($this->fixture . '/tools/maintainability-fixture/second.md'));
        $this->assertGuardPasses();
        $this->write('tools/maintainability-fixture/additional.php', "<?php\n\ndeclare(strict_types=1);\n");
        $this->assertGuardPasses();
    }

    /** @return iterable<string, array{string, string, string, bool}> */
    public static function invalidChanges(): iterable
    {
        yield 'missing contract' => ['docs/consumer-contract.md', '', '', true];
        yield 'broken current route' => ['docs/consumer-contract.md', 'docs/knowledge-map.md', 'docs/missing-map.md', false];
        yield 'required package entry replaced' => ['tools/package-files.txt', 'docs/consumer-contract.md', 'docs/missing-contract.md', false];
        yield 'PHP without strict types' => ['src/Application.php', 'declare(strict_types=1);', '', false];
    }

    #[DataProvider('invalidChanges')]
    public function testEmptyMarkdownCannotRepairRequiredChecks(
        string $relativePath,
        string $original,
        string $replacement,
        bool $remove,
    ): void {
        if ($remove) {
            self::assertTrue(unlink($this->fixture . '/' . $relativePath));
        } else {
            $contents = file_get_contents($this->fixture . '/' . $relativePath);
            self::assertIsString($contents);
            self::assertStringContainsString($original, $contents);
            $this->write($relativePath, str_replace($original, $replacement, $contents));
        }
        $before = $this->guard();
        self::assertSame(1, $before['exit_code'], $before['stderr']);
        self::assertStringContainsString('FAIL ', $before['stderr']);
        self::assertStringContainsString(
            $relativePath === 'tools/package-files.txt' ? 'docs/consumer-contract.md' : $relativePath,
            $before['stderr'],
        );
        $this->write('tools/empty-maintainability-note.md', '');
        $after = $this->guard();
        self::assertSame(1, $after['exit_code'], $after['stderr']);
        self::assertSame($before['stderr'], $after['stderr']);
    }

    private function write(string $relativePath, string $contents): void
    {
        if (file_put_contents($this->fixture . '/' . $relativePath, $contents) !== strlen($contents)) {
            throw new RuntimeException('Cannot write a guard fixture file.');
        }
    }

    /** @return array{exit_code: int, stdout: string, stderr: string} */
    private function guard(): array
    {
        return runBoundedMaintainerProcess(
            [PHP_BINARY, 'tools/guardrails.php'],
            $this->fixture,
            null,
            30_000,
            65_536,
            262_144,
        );
    }

    private function assertGuardPasses(): void
    {
        $result = $this->guard();
        self::assertSame(0, $result['exit_code'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertStringStartsWith('PASS guardrails:', $result['stdout']);
    }
}
