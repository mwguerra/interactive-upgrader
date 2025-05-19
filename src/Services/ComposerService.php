<?php

namespace MWGuerra\InteractiveUpgrader\Services;

use Symfony\Component\Process\Process;
use GuzzleHttp\Client;
use MWGuerra\InteractiveUpgrader\Services\FilesystemService;

class ComposerService
{
    protected FilesystemService $filesystem;

    public function __construct(FilesystemService $filesystem)
    {
        $this->filesystem = $filesystem;
    }
    public function getOutdated(): array
    {
        $cmd = ['composer','outdated','--direct','--format=json'];
        $output = $this->filesystem->executeJsonCommand($cmd);
        $data = $output['installed'] ?? [];
        return array_map(fn($i)=>[
            'name'=>$i['name'],
            'current'=>$i['version'],
            'wanted'=>$i['latest-status']=='update-available' ? $i['latest'] : $i['version'],
            'latest'=>$i['latest'],
            'latest_dev'=>$this->getDevVersion($i['name']),
        ], $data);
    }

    protected function getDevVersion(string $package): ?string
    {
        $options = ['base_uri' => 'https://repo.packagist.org'];
        $url = "/p2/{$package}.json";
        $response = $this->filesystem->getJson($url, $options);
        $packages = $response['packages'][$package] ?? [];
        foreach ($packages as $release) {
            if (str_starts_with($release['version'], 'dev-')) {
                return $release['version'];
            }
        }
        return null;
    }
}
