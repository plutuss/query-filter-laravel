<?php

declare(strict_types=1);

namespace Plutuss\Tests;

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Plutuss\Console\Commands\QueryFilterMakeCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class GeneratorCommandTest extends TestCase
{
    private string $directory;
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/query-filter-test-' . bin2hex(random_bytes(8));
        $this->files = new Filesystem();
        $this->files->makeDirectory($this->directory . '/src', 0755, true);
        $this->files->put($this->directory . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['Acme\\' => 'src/']],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function testGeneratesNestedFilterUsingApplicationNamespaceAndDoesNotOverwrite(): void
    {
        $app = new Application($this->directory);
        $app->instance('config', new Repository());
        $app->useAppPath($this->directory . '/src');
        $command = new QueryFilterMakeCommand($this->files);
        $command->setLaravel($app);
        $tester = new CommandTester($command);

        self::assertSame(0, $tester->execute(['name' => 'Admin/UserFilter']));
        $path = $this->directory . '/src/Filters/Admin/UserFilter.php';
        self::assertFileExists($path);
        $source = $this->files->get($path);
        self::assertStringContainsString('namespace Acme\\Filters\\Admin;', $source);
        self::assertStringContainsString('class UserFilter extends QueryFilter', $source);
        self::assertStringContainsString("protected ?array \$allowedFilters = ['name'];", $source);
        self::assertStringNotContainsString('Dummy', $source);

        $this->files->put($path, $source . "\n// Preserve manual edits.\n");
        $tester->execute(['name' => 'Admin/UserFilter']);
        self::assertStringContainsString('Preserve manual edits.', $this->files->get($path));
        self::assertStringContainsString('already exists', strtolower($tester->getDisplay()));
    }
}
