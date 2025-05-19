<?php

namespace MWGuerra\InteractiveUpgrader\Services;

use Symfony\Component\Process\Process;

class NpmService
{
    public function getOutdated(): array
    {
        $proc = new Process(['npm','outdated','--json']);
        $proc->run();
        $out = json_decode($proc->getOutput(), true) ?: [];
        return array_map(fn($name, $info)=>[
            'name'=>$name,
            'current'=>$info['current'],
            'wanted'=>$info['wanted'],
            'latest'=>$info['latest'],
            'latest_dev'=>$this->fetchDevTag($name),
        ], array_keys($out), $out);
    }

    protected function fetchDevTag(string $pkg): ?string
    {
        $p = new Process(['npm','view',$pkg,'dist-tags','--json']);
        $p->run();
        $tags = json_decode($p->getOutput(), true);
        return $tags['next'] ?? null;
    }

    public function allVersions(string $pkg): array
    {
        $p = new Process(['npm','view',$pkg,'versions','--json']);
        $p->run();
        return json_decode($p->getOutput(), true);
    }
}
