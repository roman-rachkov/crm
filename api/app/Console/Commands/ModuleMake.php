<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ModuleMake extends Command
{
    public function __construct(
        private Filesystem $files
    )
    {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module {name}
                                        {--all}
                                        {--migration}
                                        {--vue}
                                        {--view}
                                        {--controller}
                                        {--model}
                                        {--api}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create module for project';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('all')) {
            $this->input->setOption('migration', true);
            $this->input->setOption('vue', true);
            $this->input->setOption('view', true);
            $this->input->setOption('controller', true);
            $this->input->setOption('model', true);
            $this->input->setOption('api', true);
        }

        if ($this->option('model')) {
            $this->createModel();
        }

        if ($this->option('controller')) {
            $this->createController();
        }

        if ($this->option('migration')) {
            $this->createMigration();
        }

        if ($this->option('vue')) {
            $this->createVueComponent();
        }

        if ($this->option('view')) {
            $this->createView();
        }

        if ($this->option('api')) {
            $this->createApiController();
        }

        return 0;
    }

    private function createVueComponent(): void
    {
        $path = $this->getVueComponentPath($this->argument('name'));

        $component = Str::studly(class_basename($this->argument('name')));

        if ($this->alreadyExists($path)) {
            $this->error('Vue Component already exists!');
            return;
        }
        $this->makeDirectory($path);

        $stub = $this->files->get(base_path('resources/stubs/vue.component.stub'));

        $stub = str_replace(
            [
                'DummyClass',
            ],
            [
                $component,
            ],
            $stub
        );

        $this->files->put($path, $stub);
        $this->info('Vue Component created successfully.');

    }


    private function createView(): void
    {
        $paths = $this->getViewPath($this->argument('name'));

        foreach ($paths as $path) {
            $view = Str::studly(class_basename($this->argument('name')));

            if ($this->alreadyExists($path)) {
                $this->error('View already exists!');
            } else {
                $this->makeDirectory($path);

                $stub = $this->files->get(base_path('resources/stubs/view.stub'));

                $stub = str_replace(
                    [
                        '',
                    ],
                    [
                    ],
                    $stub
                );

                $this->files->put($path, $stub);
                $this->info('View created successfully.');
            }
        }
    }

    private function createMigration(): void
    {
        $table = Str::plural(Str::snake(class_basename($this->argument('name'))));
        try {
            $this->call('make:migration', [
                'name' => "create_{$table}_table",
                '--create' => $table,
                '--path' => "app/Modules/" . trim($this->argument('name')) . "/Migrations",
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function createController(): void
    {
        $controller = Str::studly(class_basename($this->argument('name')));
        $modelName = Str::singular(Str::studly(class_basename($this->argument('name'))));
        $path = $this->getControllerPath($this->argument('name'));

        $this->makeController($controller, $modelName, $path);

        $this->createRoutes($controller, $modelName);

    }

    private function createApiController(): void
    {
        $controller = Str::studly(class_basename($this->argument('name')));
        $modelName = Str::singular(Str::studly(class_basename($this->argument('name'))));
        $path = $this->getApiControllerPath($this->argument('name'));

        $this->makeController($controller, $modelName, $path, true);

        $this->createRoutes($controller, $modelName, 'api');
    }

    private function createModel(): void
    {
        $model = Str::singular(Str::studly(class_basename($this->argument('name'))));
        $this->call('make:model', [
            'name' => "App\\Modules\\" . trim($this->argument('name')) . "\\Models\\" . $model,
        ]);
    }

    private function getControllerPath(string $name): string
    {
        $controller = Str::studly(class_basename($name));
        return $this->laravel['path'] . '/Modules/' . str_replace('\\', '/', $name) . "/Controllers/{$controller}Controller.php";
    }

    private function getApiControllerPath(string $name): string
    {
        $controller = Str::studly(class_basename($name));
        return $this->laravel['path'] . '/Modules/' . str_replace('\\', '/', $name) . "/Controllers/Api/{$controller}Controller.php";
    }

    private function makeDirectory(string $path): void
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true, true);
        }

    }

    private function makeController($controller, $modelName, $path, $api = false)
    {
        if ($this->alreadyExists($path)) {
            $this->error('Controller already exists!');
            return;
        }

        $this->makeDirectory($path);
        $stub = $this->files->get(base_path('resources/stubs/controller.model.api.stub'));

        $stub = str_replace([
            'DummyNamespace',
            'DummyRootNamespace',
            'DummyClass',
            'DummyFullModelClass',
            'DummyModelClass',
            'DummyModelVariable'
        ], [
            "App\\Modules\\" . trim(str_replace('/', '\\', $this->argument('name'))) . "\\Controllers" . ($api ? "\\Api" : ''),
            $this->laravel->getNamespace(),
            $controller . 'Controller',
            "App\\Modules\\" . trim(str_replace('/', '\\', $this->argument('name'))) . "\\Models\\{$modelName}",
            $modelName,
            lcfirst(($modelName))
        ],
            $stub
        );

        $this->files->put($path, $stub);
        $this->info('Controller created successfully');
        $this->updateModularConfig();
    }

    private function createRoutes(string $controller, string $modelName, string $type = 'web')
    {
        $routePath = $this->laravel['path'] . '/Modules/' . str_replace('\\', '/', $this->argument('name')) . "/Routes/{$type}.php";

        if ($this->alreadyExists($routePath)) {
            $this->error('Routes already exists!');
            return;
        }

        $this->makeDirectory($routePath);

        $stub = $this->files->get(base_path('resources/stubs/routes.' . $type . '.stub'));

        $stub = str_replace(
            [
                'DummyClass',
                'DummyRoutePrefix',
                'DummyModelVariable',
            ],
            [
                $controller . 'Controller',
                Str::plural(Str::snake(lcfirst($modelName), '-')),
                lcfirst($modelName)
            ],
            $stub
        );

        $this->files->put($routePath, $stub);
        $this->info('Routes created successfully.');

    }

    private function updateModularConfig()
    {

    }

    private function alreadyExists(string $path): bool
    {
        return $this->files->exists($path);
    }

    private function getVueComponentPath(string $name): string
    {
        return base_path('resources/js/components/' . str_replace('\\', '/', $name) . ".vue");
    }

    private function getViewPath(string $name): Collection
    {
        $arrFiles = collect([
            'create',
            'edit',
            'index',
            'show',
        ]);

        return $arrFiles->map(function ($item) use ($name) {
            return base_path('resources/views/' . str_replace('\\', '/', $name) . '/' . $item . ".blade.php");
        });
    }


}
