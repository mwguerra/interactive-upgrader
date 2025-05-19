<?php

namespace MWGuerra\InteractiveUpgrader\Services;

use Symfony\Component\Process\Process;
use MWGuerra\InteractiveUpgrader\Services\FilesystemService;

class NpmService
{
    protected FilesystemService $filesystem;

    public function __construct(FilesystemService $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    public function getOutdated(): array
    {
        $cmd = ['npm','outdated','--json'];
        $out = $this->filesystem->executeJsonCommand($cmd);

        if (empty($out)) {
            return [];
        }

        $result = [];
        foreach ($out as $name => $info) {
            $result[] = [
                'name' => $name,
                'current' => $info['current'],
                'wanted' => $info['wanted'],
                'latest' => $info['latest'],
                'latest_dev' => $this->fetchDevTag($name),
            ];
        }

        return $result;
    }

    protected function fetchDevTag(string $pkg): ?string
    {
        $cmd = ['npm','view',$pkg,'dist-tags','--json'];
        $tags = $this->filesystem->executeJsonCommand($cmd);
        return $tags['next'] ?? null;
    }

    public function allVersions(string $pkg): array
    {
        $cmd = ['npm','view',$pkg,'versions','--json'];
        return $this->filesystem->executeJsonCommand($cmd);
    }
}
