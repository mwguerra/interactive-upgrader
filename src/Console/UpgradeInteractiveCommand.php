<?php

declare(strict_types=1);

namespace MWGuerra\InteractiveUpgrader\Console;

use Illuminate\Console\Command;
use MWGuerra\InteractiveUpgrader\Services\ComposerService;
use MWGuerra\InteractiveUpgrader\Services\NpmService;
use MWGuerra\InteractiveUpgrader\Services\DependencyResolver;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;

class UpgradeInteractiveCommand extends Command
{
    // Signature: renamed --keep → -i|--ignore, added --ignore-major and --ignore-dev
    protected $signature = <<<'SIG'
upgrade:interactive
{--latest           : Only offer the “latest” (major) version}
{--dev              : Only offer the “dev” version}
{--ignore=          : Comma-separated list of type:package to skip (e.g. composer:predis/predis,npm:tailwindcss)}
{--ignore-major     : Hide all major version upgrades from the table}
{--ignore-dev       : Hide dev column and dev‐upgrade options; omit items without any update}
SIG;

    protected $description = 'Interactively upgrade Composer & npm packages';

    // Richer help text with examples
    protected $help = <<<'HELP'
Interactively upgrade your Composer and npm dependencies in a single table-driven flow.

Usage:
  php artisan upgrade:interactive [options]

Options:
  --latest           Only consider “latest” (major) upgrades
  --dev              Only consider “dev” (unstable) upgrades
  --ignore=          Skip specific packages (comma-separated type:package pairs)
  --ignore-major     Don’t show any major bumps in the table
  --ignore-dev       Don’t show the “Dev” column or dev‐upgrades; drop packages with no actionable updates
  -h, --help         Display this help message

Examples:
  # See everything updatable
  php artisan upgrade:interactive

  # Only show major jumps
  php artisan upgrade:interactive --latest

  # Skip Predis and Tailwind from the table
  php artisan upgrade:interactive --ignore=composer:predis/predis,npm:tailwindcss

  # Hide all major bumps (even if --latest is given)
  php artisan upgrade:interactive --ignore-major

  # Don’t show any dev‐builds, and drop packages already fully up‐to-date
  php artisan upgrade:interactive --ignore-dev
HELP;

    public function handle(
        ComposerService $composer,
        NpmService $npm,
        DependencyResolver $resolver
    ) {
        // Parse new flags
        $ignoreMajor = (bool) $this->option('ignore-major');
        $ignoreDev   = (bool) $this->option('ignore-dev');

        // Parse -i|--ignore
        $ignoreMap = ['composer' => [], 'npm' => []];
        if ($raw = $this->option('ignore')) {
            foreach (preg_split('/\s*,\s*/', $raw) as $entry) {
                [$type, $name] = array_pad(explode(':', $entry, 2), 2, null);
                if (in_array($type, ['composer', 'npm'], true) && $name) {
                    $ignoreMap[$type][] = $name;
                }
            }
        }

        // Read dev-lists
        $cwd         = getcwd();
        $composerCfg = json_decode(@file_get_contents("{$cwd}/composer.json") ?: '{}', true);
        $npmCfg      = json_decode(@file_get_contents("{$cwd}/package.json")  ?: '{}', true);
        $composerDev = array_keys($composerCfg['require-dev']  ?? []);
        $npmDev      = array_keys($npmCfg['devDependencies'] ?? []);

        // 1) Fetch outdated
        $comp = spin(callback: fn() => $composer->getOutdated(), message: 'Checking composer…');
        $node = spin(callback: fn() => $npm->getOutdated(),      message: 'Checking npm…');
        $comp = array_map(fn($p) => array_merge($p, ['type'=>'composer']), $comp);
        $node = array_map(fn($p) => array_merge($p, ['type'=>'npm']),      $node);
        $all  = array_merge($comp, $node);

        // Annotate dev-packages
        $all = array_map(function(array $p) use ($composerDev, $npmDev) {
            $isDev = $p['type'] === 'composer'
                ? in_array($p['name'], $composerDev, true)
                : in_array($p['name'], $npmDev, true);
            return array_merge($p, ['is_dev' => $isDev]);
        }, $all);

        // 1a) Drop explicitly ignored packages
        $all = array_filter($all, fn($p) =>
        ! in_array($p['name'], $ignoreMap[$p['type']] ?? [], true)
        );

        // 1b) If --ignore-major, drop major bumps
        if ($ignoreMajor) {
            $all = array_filter($all, function($p) {
                if (is_null($p['latest'])) {
                    return true;
                }
                $curMaj = (int) explode('.', ltrim($p['current'], 'vV'))[0];
                $latMaj = (int) explode('.', ltrim($p['latest'],  'vV'))[0];
                return $latMaj <= $curMaj;
            });
        }

        // 1c) If --ignore-dev, drop fully up-to-date items
        if ($ignoreDev) {
            $all = array_filter($all, fn($p) =>
                $p['current'] !== ($p['wanted']    ?? $p['current'])
                || $p['current'] !== ($p['latest']    ?? $p['current'])
            );
        }

        // If nothing to upgrade, show friendly message and exit
        if (empty($all)) {
            outro('✨  All of your dependencies are already up to date!  ✨');
            return;
        }

        // 2) Show outdated table
        info('➤ Outdated packages:');
        $headers = ['Type','Package','Current','Recommended','Latest'];
        if (! $ignoreDev) {
            $headers[] = 'Dev';
        }

        table(
            headers: $headers,
            rows: array_map(function($p) use ($ignoreDev) {
                $row = [
                    $p['type'],
                    $p['name'] . ($p['is_dev'] ? ' (dev)' : ''),
                    $p['current'],
                    $this->colorFor($p['current'], $p['wanted']),
                    $this->colorFor($p['current'], $p['latest']),
                ];
                if (! $ignoreDev) {
                    $row[] = $this->colorFor($p['current'], $p['latest_dev']);
                }
                return $row;
            }, $all)
        );

        // 3) Determine available strategies
        $hasRecommended = collect($all)->contains(fn($p) =>
            $p['wanted'] !== null && $p['wanted'] !== $p['current']
        );
        $hasLatest = collect($all)->contains(fn($p) =>
            $p['latest'] !== null && $p['latest'] !== $p['current']
        );
        $hasDev = collect($all)->contains(fn($p) =>
            $p['latest_dev'] !== null && $p['latest_dev'] !== $p['current']
        );

        $strategies = [];
        if ($hasRecommended) {
            $strategies['recommended'] = 'Recommended (safe)';
        }
        if ($hasLatest) {
            $strategies['latest'] = 'Latest (major)';
        }
        if ($hasDev && ! $ignoreDev) {
            $strategies['dev'] = 'Development (unstable)';
        }

        if (empty($strategies)) {
            info('✅ All packages are up to date!');
            return;
        }

        // 4) Choose strategy
        if ($this->option('latest') && isset($strategies['latest'])) {
            $strategy = 'latest';
        } elseif ($this->option('dev') && isset($strategies['dev'])) {
            $strategy = 'dev';
        } elseif (count($strategies) === 1) {
            $strategy = array_key_first($strategies);
            info("Using only available strategy: {$strategies[$strategy]}");
        } else {
            $strategy = select(
                label: 'Which update strategy?',
                options: $strategies,
                default: array_key_first($strategies)
            );
        }

        // 5) Warn on major bumps for “latest”
        if ($strategy === 'latest') {
            $major = array_filter($all, function($p) {
                if (is_null($p['latest'])) {
                    return false;
                }
                $cur = ltrim($p['current'], 'vV');
                $lat = ltrim($p['latest'],  'vV');
                return intval(explode('.', $lat)[0])
                    > intval(explode('.', $cur)[0]);
            });

            if (! empty($major)) {
                info('The following packages will jump major versions:');
                foreach ($major as $p) {
                    $range = "<fg=red>{$p['current']} → {$p['latest']}</>";
                    $this->info("  • {$p['name']}  {$range}");
                }

                if (! confirm('Major version upgrades may break your app. Proceed with all majors?', false)) {
                    info('Falling back to Recommended versions for major bumps.');
                    $strategy = 'recommended';
                }
            }
        }

        // 6) Warn on instability for “dev”
        if ($strategy === 'dev') {
            $devable = array_filter($all, fn($p) => ! is_null($p['latest_dev']));
            if (! empty($devable)) {
                info('You’ve chosen Development versions for these packages:');
                foreach ($devable as $p) {
                    $this->info("  • {$p['name']}  → {$p['latest_dev']}");
                }
                if (! confirm('Development builds may be unstable. Proceed with all devs?', false)) {
                    info('Falling back to Recommended versions.');
                    $strategy = 'recommended';
                }
            } else {
                info('No development tags found; using Recommended.');
                $strategy = 'recommended';
            }
        }

        // 7) Build the list of updates
        $toRun = [];
        foreach ($all as $p) {
            $newVersion = match ($strategy) {
                'latest'    => $p['latest']     ?? $p['wanted'],
                'dev'       => $p['latest_dev'] ?? $p['wanted'],
                default     => $p['wanted'],
            };

            if (is_null($newVersion) || $newVersion === $p['current']) {
                continue;
            }

            // Dependency suggestions
            foreach ($resolver->suggest($p['name'], $newVersion) as $dep) {
                $depIsDev = $p['type'] === 'composer'
                    ? in_array($dep['name'], $composerDev, true)
                    : in_array($dep['name'], $npmDev, true);

                // Try to infer “current” for the dependency, fallback to target if missing
                $found = collect($all)
                    ->first(fn($x) => $x['name'] === $dep['name'] && $x['type'] === $p['type']);
                $currentDep = $found['current'] ?? $dep['target'];

                if (confirm("→ {$p['name']}@{$newVersion} may require {$dep['name']} ({$dep['required']}). Upgrade?", true)) {
                    $toRun[] = [
                        $p['type'],
                        $dep['name'],
                        $currentDep,
                        $dep['target'],
                        $depIsDev,
                    ];
                }
            }

            // The package itself
            $toRun[] = [
                $p['type'],
                $p['name'],
                $p['current'],
                $newVersion,
                $p['is_dev'],
            ];
        }

        if (empty($toRun)) {
            info('✅ Everything is already up to date!');
            return;
        }

        // 8) Summary & confirmation
        info('➤ The following packages will be updated:');
        foreach ($toRun as list($type, $pkg, $current, $new, $isDev)) {
            $label = $pkg . ($isDev ? ' (dev)' : '');
            $this->info("  • [{$type}] {$label}  {$current} → {$new}");
        }
        if (! confirm('Proceed with updates?', true)) {
            info('Aborted.');
            return;
        }

        // 9) Create backups
        $this->createBackups();

        // 10) Perform updates
        progress(
            label: 'Updating…',
            steps: $toRun,
            callback: function(array $item, $progress) {
                [$type, $pkg, , $ver, $isDev] = $item;
                if ($type === 'composer') {
                    $flag = $isDev ? '--dev' : '';
                    $progress->label("composer require {$pkg}:{$ver} {$flag}");
                    passthru("composer require {$pkg}:{$ver} {$flag} --quiet");
                } else {
                    $flag = $isDev ? '--save-dev' : '';
                    $progress->label("npm install {$pkg}@{$ver} {$flag}");
                    passthru("npm install {$pkg}@{$ver} {$flag} --silent");
                }
                $progress->advance();
            }
        );

        info('✅ All done!');
    }

    /**
     * Color a target version relative to current:
     *  - gray    if equal
     *  - red     if major bump
     *  - green   if minor bump
     *  - yellow  if no target
     */
    protected function colorFor(string $current, ?string $target): string
    {
        if (is_null($target)) {
            return '<fg=yellow>n/a</>';
        }

        if ($target === $current) {
            return "<fg=gray>{$target}</>";
        }

        // Strip leading “v” or “V”
        $cleanCurrent = ltrim($current, 'vV');
        $cleanTarget  = ltrim($target,  'vV');
        [$curMaj] = explode('.', $cleanCurrent);
        [$tarMaj] = explode('.', $cleanTarget);

        return (intval($tarMaj) > intval($curMaj))
            ? "<fg=red>{$target}</>"
            : "<fg=green>{$target}</>";
    }

    /**
     * Creates incremental backups of composer.json & package.json
     * named composer.json.uibkp_1, composer.json.uibkp_2, …
     */
    protected function createBackups(): void
    {
        foreach (['composer.json', 'package.json'] as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $pattern = "{$file}.uibkp_*";
            $max     = 0;
            foreach (glob($pattern) as $bkp) {
                if (preg_match("/\.uibkp_(\d+)$/", $bkp, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }

            $next = $max + 1;
            copy($file, "{$file}.uibkp_{$next}");
            $this->info("Backup created: {$file}.uibkp_{$next}");
        }
        $this->newLine();
        $this->newLine();
    }
}
