<?php

namespace MWGuerra\InteractiveUpgrader\Services;

use Composer\Semver\Semver;
use MWGuerra\InteractiveUpgrader\Services\ComposerService;
use MWGuerra\InteractiveUpgrader\Services\FilesystemService;
use MWGuerra\InteractiveUpgrader\Services\NpmService;

class DependencyResolver
{
    protected ComposerService $composer;
    protected NpmService $npm;
    protected FilesystemService $filesystem;

    public function __construct(ComposerService $composer, NpmService $npm, FilesystemService $filesystem)
    {
        $this->composer = $composer;
        $this->npm = $npm;
        $this->filesystem = $filesystem;
    }

    /**
     * Look in composer.lock and package-lock.json for packages
     * that depend on $package, and whose declared constraint
     * would _not_ accept $targetVersion.
     *
     * @return array<int, array{name:string, required:string, target:string}>
     */
    public function suggest(string $package, string $targetVersion): array
    {
        $suggestions = [];

        $root = getcwd();

        // 1) composer.lock
        $lockFile = $root . '/composer.lock';
        if ($this->filesystem->fileExists($lockFile)) {
            $lock = json_decode($this->filesystem->getFileContents($lockFile), true);
            foreach (['packages','packages-dev'] as $section) {
                foreach ($lock[$section] ?? [] as $entry) {
                    $requires = $entry['require'] ?? [];
                    if (isset($requires[$package])) {
                        $constraint = $requires[$package];
                        // if our new version would break them…
                        if (! Semver::satisfies($targetVersion, $constraint)) {
                            // find their recommended (wanted) version
                            $outdated = array_filter(
                                $this->composer->getOutdated(),
                                fn($p) => $p['name'] === $entry['name']
                            );
                            $recommended = $outdated
                                ? array_values($outdated)[0]['wanted']
                                : $entry['version'];

                            $suggestions[] = [
                                'name'     => $entry['name'],
                                'required' => $constraint,
                                'target'   => $recommended,
                            ];
                        }
                    }
                }
            }
        }

        // 2) package-lock.json (npm)
        $npmLock = $root . '/package-lock.json';
        if ($this->filesystem->fileExists($npmLock)) {
            $lock = json_decode($this->filesystem->getFileContents($npmLock), true);
            // support both v1 (dependencies) and v2 (packages) formats
            $deps = $lock['dependencies']
                ?? ($lock['packages'] ?? []);
            foreach ($deps as $depName => $info) {
                $requires = $info['requires'] ?? [];
                if (isset($requires[$package])) {
                    $constraint = $requires[$package];
                    if (! Semver::satisfies($targetVersion, $constraint)) {
                        $outdated = array_filter(
                            $this->npm->getOutdated(),
                            fn($p) => $p['name'] === $depName
                        );
                        $recommended = $outdated
                            ? array_values($outdated)[0]['wanted']
                            : ($info['version'] ?? 'unknown');

                        $suggestions[] = [
                            'name'     => $depName,
                            'required' => $constraint,
                            'target'   => $recommended,
                        ];
                    }
                }
            }
        }

        return $suggestions;
    }
}
