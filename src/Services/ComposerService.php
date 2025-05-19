<?php

namespace MWGuerra\InteractiveUpgrader\Services;

use Symfony\Component\Process\Process;
use GuzzleHttp\Client;

class ComposerService
{
    public function getOutdated(): array
    {
        $process = new Process(['composer','outdated','--direct','--format=json']);
        $process->run();
        $data = json_decode($process->getOutput(), true)['installed'] ?? [];
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
        $client = new Client(['base_uri'=>'https://repo.packagist.org']);
        $resp = $client->get("/p2/{$package}.json");
        $packages = json_decode($resp->getBody(), true)['packages'][$package] ?? [];
        foreach ($packages as $release) {
            if (str_starts_with($release['version'], 'dev-')) {
                return $release['version'];
            }
        }
        return null;
    }
}
