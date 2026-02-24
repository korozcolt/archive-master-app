<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BootstrapProductionInstance extends Command
{
    protected $signature = 'app:bootstrap-production
        {--seed-class=Database\\Seeders\\ClientDefaultSeeder : Seeder base para inicializar la instancia}
        {--skip-seed : Omite la carga de datos base}
        {--skip-storage-link : Omite storage:link}
        {--restart-workers : Reinicia queue workers / Horizon al final}';

    protected $description = 'Inicializa una instancia recién desplegada (migraciones, seed baseline, enlaces y caches)';

    public function handle(): int
    {
        $this->line('<fg=cyan>Bootstrap inicial de ArchiveMaster</>');
        $this->line('Entorno: '.app()->environment());
        $this->newLine();

        $steps = self::buildStepPlan(
            skipSeed: (bool) $this->option('skip-seed'),
            skipStorageLink: (bool) $this->option('skip-storage-link'),
            restartWorkers: (bool) $this->option('restart-workers'),
            seedClass: (string) $this->option('seed-class'),
        );

        foreach ($steps as $step) {
            $success = $this->runStep(
                label: $step['label'],
                command: $step['command'],
                parameters: $step['parameters'] ?? [],
                allowFailure: (bool) ($step['allow_failure'] ?? false),
            );

            if (! $success) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Bootstrap inicial completado.');
        $this->line('Siguiente paso: validar acceso con el super_admin del baseline.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{label:string, command:string, parameters:array<string, mixed>, allow_failure?:bool}>
     */
    public static function buildStepPlan(bool $skipSeed, bool $skipStorageLink, bool $restartWorkers, string $seedClass): array
    {
        $steps = [
            ['label' => 'Limpiar cachés previas', 'command' => 'optimize:clear', 'parameters' => []],
            ['label' => 'Ejecutar migraciones', 'command' => 'migrate', 'parameters' => ['--force' => true]],
        ];

        if (! $skipSeed) {
            $steps[] = [
                'label' => 'Cargar configuración base',
                'command' => 'db:seed',
                'parameters' => [
                    '--class' => $seedClass,
                    '--force' => true,
                ],
            ];
        }

        if (! $skipStorageLink) {
            $steps[] = [
                'label' => 'Crear enlace de storage',
                'command' => 'storage:link',
                'parameters' => ['--force' => true],
                'allow_failure' => true,
            ];
        }

        $steps = array_merge($steps, [
            ['label' => 'Cachear configuración', 'command' => 'config:cache', 'parameters' => []],
            ['label' => 'Cachear rutas', 'command' => 'route:cache', 'parameters' => []],
            ['label' => 'Cachear eventos', 'command' => 'event:cache', 'parameters' => []],
            ['label' => 'Cachear vistas', 'command' => 'view:cache', 'parameters' => []],
        ]);

        if ($restartWorkers) {
            $steps[] = [
                'label' => 'Reiniciar workers de cola',
                'command' => 'queue:restart',
                'parameters' => [],
                'allow_failure' => true,
            ];
            $steps[] = [
                'label' => 'Terminar procesos Horizon',
                'command' => 'horizon:terminate',
                'parameters' => [],
                'allow_failure' => true,
            ];
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function runStep(string $label, string $command, array $parameters, bool $allowFailure = false): bool
    {
        $this->line("• {$label} <fg=gray>({$command})</>");

        try {
            $exitCode = Artisan::call($command, $parameters);
            $output = trim((string) Artisan::output());

            if ($exitCode !== 0) {
                if ($allowFailure) {
                    $this->warn("  Advertencia: {$command} devolvió código {$exitCode}. Se continúa.");
                    if ($output !== '') {
                        $this->line('  '.$output);
                    }

                    return true;
                }

                $this->error("  Error ejecutando {$command} (código {$exitCode}).");
                if ($output !== '') {
                    $this->line('  '.$output);
                }

                return false;
            }

            $this->line('  OK');

            return true;
        } catch (\Throwable $exception) {
            if ($allowFailure) {
                $this->warn("  Advertencia: {$command} lanzó excepción y se continúa: {$exception->getMessage()}");

                return true;
            }

            $this->error("  Excepción en {$command}: {$exception->getMessage()}");

            return false;
        }
    }
}
