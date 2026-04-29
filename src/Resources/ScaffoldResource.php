<?php

namespace Solutionforest\FilamentScaffold\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Solutionforest\FilamentScaffold\Resources\ScaffoldResource\Pages;

if (! defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

class ScaffoldResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $pluralModelLabel = 'Scaffold';

    protected static ?string $navigationLabel = 'Scaffold Manager';

    protected static ?string $modelLabel = 'Scaffold';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                // ─── TABLE NAME / MODEL NAME / RESOURCE NAME ───────────────────
                Forms\Components\Section::make('Table & Resource Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([

                                Forms\Components\TextInput::make('Table Name')
                                    ->reactive()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $modelName = str_replace('_', '', ucwords($state, '_'));
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', 'app\\Filament\\Resources\\' . $modelName . 'Resource');
                                        $set('Choose Table', $state);
                                    })
                                    ->required(),

                                Forms\Components\Select::make('Choose Table')
                                    ->options(self::getAllTableNames())
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $allTables = self::getAllTableNames();

                                        if (! isset($allTables[$state])) {
                                            return;
                                        }

                                        $tableName = $allTables[$state];
                                        $tableColumns = self::getTableColumns($tableName);
                                        $modelName = Str::singular(str_replace('_', '', ucwords($tableName, '_')));
                                        $set('Table Name', $tableName);
                                        $set('Model', 'app\\Models\\' . $modelName);
                                        $set('Resource', 'app\\Filament\\Resources\\' . $modelName . 'Resource');
                                        $set('Table', $tableColumns);
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('Model')
                                    ->default('app\\Models\\')
                                    ->live(onBlur: true),
                                Forms\Components\TextInput::make('Resource')
                                    ->default('app\\Filament\\Resources\\')
                                    ->live(onBlur: true),
                            ]),
                    ])
                    ->columnSpan(2),

                // ─── GENERATION OPTIONS ────────────────────────────────────────
                Forms\Components\Section::make('Generation Options')
                    ->schema([
                        Forms\Components\Checkbox::make('Create Resource')
                            ->default(true),
                        Forms\Components\Checkbox::make('Create Model')
                            ->default(true),
                        Forms\Components\Checkbox::make('Simple Resource')
                            ->default(false)
                            ->label('Simple (Modal Type) Resource'),
                        Forms\Components\Checkbox::make('Create Migration'),
                        Forms\Components\Checkbox::make('Create Factory'),
                        Forms\Components\Checkbox::make('Create Controller'),
                        Forms\Components\Checkbox::make('Run Migrate'),
                        Forms\Components\Checkbox::make('Create Route'),
                        Forms\Components\Checkbox::make('Create Policy')
                            ->default(false)
                            ->hidden(fn () => ! class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)),
                        Forms\Components\Checkbox::make('create_api')
                            ->label('Create API')
                            ->default(false)
                            ->hidden(fn () => ! class_exists(\Rupadana\ApiService\ApiService::class)),
                    ])
                    ->columns(2)
                    ->columnSpan(1),

                // ─── TABLE STRUCTURE ───────────────────────────────────────────
                Forms\Components\Section::make('Table Structure')
                    ->schema([
                        Forms\Components\Repeater::make('Table')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Field Name')
                                    ->required()
                                    ->default(fn ($record) => $record['name'] ?? ''),
                                Forms\Components\TextInput::make('translation'),
                                Forms\Components\Select::make('type')
                                    ->native(false)
                                    ->searchable()
                                    ->options([
                                        'string'        => 'string',
                                        'integer'       => 'integer',
                                        'bigInteger'    => 'bigInteger',
                                        'text'          => 'text',
                                        'float'         => 'float',
                                        'double'        => 'double',
                                        'decimal'       => 'decimal',
                                        'boolean'       => 'boolean',
                                        'date'          => 'date',
                                        'time'          => 'time',
                                        'datetime'      => 'dateTime',
                                        'timestamp'     => 'timestamp',
                                        'char'          => 'char',
                                        'mediumText'    => 'mediumText',
                                        'longText'      => 'longText',
                                        'tinyInteger'   => 'tinyInteger',
                                        'smallInteger'  => 'smallInteger',
                                        'mediumInteger' => 'mediumInteger',
                                        'json'          => 'json',
                                        'jsonb'         => 'jsonb',
                                        'binary'        => 'binary',
                                        'enum'          => 'enum',
                                        'ipAddress'     => 'ipAddress',
                                        'macAddress'    => 'macAddress',
                                    ])
                                    ->default(fn ($record) => $record['type'] ?? 'string')
                                    ->reactive(),
                                Forms\Components\Checkbox::make('nullable')
                                    ->inline(false)
                                    ->default(fn ($record) => $record['nullable'] ?? false),
                                Forms\Components\Select::make('key')
                                    ->default('')
                                    ->options([
                                        ''        => 'NULL',
                                        'primary' => 'Primary',
                                        'unique'  => 'Unique',
                                        'index'   => 'Index',
                                    ])
                                    ->default(fn ($record) => $record['key'] ?? ''),
                                Forms\Components\TextInput::make('default')
                                    ->default(fn ($record) => $record['default'] ?? ''),
                                Forms\Components\Textarea::make('comment')
                                    ->autosize()
                                    ->default(fn ($record) => $record['comment'] ?? ''),
                            ])
                            ->columns(7),
                    ])
                    ->columnSpan('full'),

                // ─── MIGRATION ADDITIONAL FEATURES ────────────────────────────
                Forms\Components\Section::make('Migration Additional Features')
                    ->schema([
                        Forms\Components\Checkbox::make('Created_at & Updated_at')
                            ->label('Created_at & Updated_at timestamps')
                            ->default(true)
                            ->inline(),
                        Forms\Components\Checkbox::make('Soft Delete')
                            ->label('Soft Delete (recycle bin)')
                            ->default(true)
                            ->inline(),
                    ])
                    ->columns(2)
                    ->columnSpan('full'),
            ])
            ->columns(3);
    }

    // ─── DATABASE HELPERS ──────────────────────────────────────────────────────

    /**
     * CAMBIO v4/v5: reemplaza `SHOW TABLES` (MySQL-only) por Schema::getTableListing()
     * que funciona con MySQL, PostgreSQL y SQLite.
     */
    public static function getAllTableNames(): array
    {
        try {
            // Filament v4 requiere Laravel 11.28+, que incluye Schema::getTableListing()
            $tables = Schema::getTableListing();

            return array_combine($tables, $tables);
        } catch (\Throwable $e) {
            // Fallback para compatibilidad con instalaciones antiguas
            try {
                $driver = DB::getDriverName();

                if (in_array($driver, ['mysql', 'mariadb'])) {
                    $rows = DB::select('SHOW TABLES');
                    $tables = array_map('current', $rows);
                } elseif ($driver === 'pgsql') {
                    $rows = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                    $tables = array_column($rows, 'tablename');
                } elseif ($driver === 'sqlite') {
                    $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                    $tables = array_column($rows, 'name');
                } else {
                    $tables = [];
                }

                return array_combine($tables, $tables);
            } catch (\Throwable $e2) {
                Log::warning('FilamentScaffold: No se pudieron obtener las tablas de la base de datos.', [
                    'error' => $e2->getMessage(),
                ]);

                return [];
            }
        }
    }

    /**
     * CAMBIO v4/v5: reemplaza `SHOW COLUMNS FROM` (MySQL-only) por Schema::getColumns()
     * disponible en Laravel 10.23+ (incluido en el requisito de Filament v4: Laravel 11.28+).
     */
    public static function getTableColumns(string $tableName): array
    {
        $typeMapping = [
            'varchar'    => 'string',
            'int'        => 'integer',
            'bigint'     => 'bigInteger',
            'text'       => 'text',
            'float'      => 'float',
            'double'     => 'double',
            'decimal'    => 'decimal',
            'bool'       => 'boolean',
            'boolean'    => 'boolean',
            'tinyint'    => 'tinyInteger',
            'smallint'   => 'smallInteger',
            'mediumint'  => 'mediumInteger',
            'date'       => 'date',
            'time'       => 'time',
            'datetime'   => 'dateTime',
            'timestamp'  => 'timestamp',
            'char'       => 'char',
            'mediumtext' => 'mediumText',
            'longtext'   => 'longText',
            'json'       => 'json',
            'jsonb'      => 'jsonb',
            'binary'     => 'binary',
            'enum'       => 'enum',
            'ipaddress'  => 'ipAddress',
            'macaddress' => 'macAddress',
        ];

        $columnDetails = [];

        try {
            // Schema::getColumns() — disponible en Laravel 10.23+ (cross-database)
            $columns = Schema::getColumns($tableName);

            foreach ($columns as $column) {
                $fieldName = $column['name'];

                if (in_array($fieldName, ['id', 'ID', 'created_at', 'updated_at', 'deleted_at'])) {
                    continue;
                }

                // El tipo viene como "varchar(255)" → extraer solo "varchar"
                $rawType = strtolower(preg_replace('/\(.+\)/', '', $column['type_name'] ?? $column['type'] ?? 'string'));
                $rawType = preg_split('/\s+/', $rawType)[0];
                $mappedType = $typeMapping[$rawType] ?? 'string';

                // Schema::getColumns() devuelve 'nullable' como bool directamente
                $nullable = $column['nullable'] ?? false;

                // No hay mapeo de key en Schema::getColumns() — dejamos vacío por defecto
                $columnDetails[] = [
                    'name'     => $fieldName,
                    'type'     => $mappedType,
                    'nullable' => (bool) $nullable,
                    'key'      => '',
                    'default'  => $column['default'] ?? '',
                    'comment'  => $column['comment'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            // Fallback: SHOW COLUMNS para MySQL/MariaDB únicamente
            try {
                $columns = DB::select('SHOW COLUMNS FROM ' . $tableName);

                $keyMapping = ['PRI' => 'primary', 'UNI' => 'unique', 'MUL' => 'index'];

                foreach ($columns as $column) {
                    if (in_array($column->Field, ['id', 'ID', 'created_at', 'updated_at', 'deleted_at'])) {
                        continue;
                    }

                    $type = preg_replace('/\(.+\)/', '', $column->Type);
                    $type = strtolower(preg_split('/\s+/', $type)[0]);

                    $columnDetails[] = [
                        'name'     => $column->Field,
                        'type'     => $typeMapping[$type] ?? $type,
                        'nullable' => $column->Null === 'YES',
                        'key'      => $keyMapping[$column->Key] ?? '',
                        'default'  => $column->Default ?? '',
                        'comment'  => '',
                    ];
                }
            } catch (\Throwable $e2) {
                Log::error('FilamentScaffold: No se pudieron obtener las columnas de ' . $tableName, [
                    'error' => $e2->getMessage(),
                ]);
            }
        }

        return $columnDetails;
    }

    // ─── PAGES ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\CreateScaffold::route('/'),
        ];
    }

    // ─── FILE GENERATION ───────────────────────────────────────────────────────

    public static function getFileName(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $fileNameWithExtension = basename($normalizedPath);

        return pathinfo($fileNameWithExtension, PATHINFO_FILENAME);
    }

    public static function generateFiles(array $data): void
    {
        $basePath = base_path();
        $modelName = self::getFileName($data['Model']);
        $resourceName = self::getFileName($data['Resource']);

        chdir($basePath);

        $migrationPath  = null;
        $resourcePath   = null;
        $modelPath      = null;
        $controllerPath = null;

        // ── MIGRATION ────────────────────────────────────────────────────────
        if ($data['Create Migration']) {
            Artisan::call('make:migration', [
                'name'             => 'create_' . $data['Table Name'] . '_table',
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();

            if (str_contains($output, 'Migration') || str_contains($output, 'migration')) {
                preg_match('/\[([^\]]+)\]/', $output, $matches);
                $migrationPath = $matches[1] ?? null;
            }

            self::overwriteMigrationFile($migrationPath, $data);
        }

        // ── FACTORY ──────────────────────────────────────────────────────────
        if ($data['Create Factory']) {
            Artisan::call('make:factory', [
                'name'             => $modelName . 'Factory',
                '--no-interaction' => true,
            ]);
        }

        // ── MODEL ─────────────────────────────────────────────────────────────
        if ($data['Create Model']) {
            Artisan::call('make:model', [
                'name'             => $modelName,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();

            if (str_contains($output, 'Model') || str_contains($output, 'model')) {
                preg_match('/\[([^\]]+)\]/', $output, $matches);
                $modelPath = $matches[1] ?? null;
            }

            self::overwriteModelFile($modelPath, $data);
        }

        // ── RESOURCE ──────────────────────────────────────────────────────────
        if ($data['Create Resource']) {
            $command = [
                'name'             => $resourceName,
                '--generate'       => true,
                '--force'          => true,
                '--no-interaction' => true,
            ];

            // CAMBIO v4: --view fue renombrado / eliminado en v4.
            // En v4 se usa --view-resource o directamente se genera la página View.
            // Detectamos si la opción existe para mantener compatibilidad.
            if (self::artisanOptionExists('make:filament-resource', '--view')) {
                $command['--view'] = true;
            }

            if ($data['Simple Resource']) {
                $command['--simple'] = true;
            }

            Artisan::call('make:filament-resource', $command);
            $output = Artisan::output();

            // CAMBIO v4/v5: la nueva estructura de directorios genera el resource dentro
            // de su propia carpeta (Resources/PostResource/PostResource.php) en lugar de
            // en la raíz (Resources/PostResource.php). Detectamos ambos casos.
            $resourcePath = self::detectResourcePath($resourceName, $output);

            self::overwriteResourceFile($resourcePath, $data, $resourceName);
        }

        // ── CONTROLLER ────────────────────────────────────────────────────────
        if ($data['Create Controller']) {
            Artisan::call('make:controller', [
                'name'             => $data['Table Name'] . 'Controller',
                '--model'          => $modelName,
                '--resource'       => true,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();
            preg_match('/\[([^\]]+)\]/', $output, $matches);
            $controllerPath = $matches[1] ?? null;
            self::overwriteControllerFile($controllerPath, $data);
        }

        // ── POLICY (FilamentShield) ───────────────────────────────────────────
        if (class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            /** @phpstan-ignore-next-line */
            $url = \BezhanSalleh\FilamentShield\Resources\RoleResource::getUrl();

            if ($data['Create Policy']) {
                $modelName = self::getFileName($data['Model']);
                Artisan::call('make:policy', [
                    'name'             => $modelName . 'Policy',
                    '--model'          => $modelName,
                    '--no-interaction' => true,
                ]);
                $output = Artisan::output();

                if (str_contains($output, 'Policy') || str_contains($output, 'policy')) {
                    preg_match('/\[([^\]]+)\]/', $output, $matches);
                    $policyPath = $matches[1] ?? null;

                    if ($policyPath) {
                        self::updatePolicyFile($policyPath, $modelName);

                        Notification::make()
                            ->success()
                            ->persistent()
                            ->title('Scaffold with Policy Created Successfully!')
                            ->body('A new policy file has been successfully created for your model. Please configure the permissions for the new policy.')
                            ->icon('heroicon-o-shield-check')
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view')
                                    ->label('Configure Permissions')
                                    ->button()
                                    ->url($url, shouldOpenInNewTab: true),
                                \Filament\Notifications\Actions\Action::make('close')
                                    ->color('gray')
                                    ->close(),
                            ])
                            ->send();
                    }
                }
            }
        }

        // ── ROUTES ────────────────────────────────────────────────────────────
        if ($data['Create Route']) {
            $controllerName = $controllerPath ? self::getFileName($controllerPath) : $data['Table Name'] . 'Controller';
            self::addRoutes($data, $controllerName);
        }

        // ── API SERVICE (filament-api-service) ────────────────────────────────
        if (! empty($data['create_api']) && class_exists(\Rupadana\ApiService\ApiService::class)) {
            self::generateApiService($data, $resourceName);
        }

        // ── POST-GENERATION ARTISAN COMMANDS ──────────────────────────────────
        // CAMBIO v4/v5: 'filament:cache-components' fue reemplazado por 'filament:optimize'
        // en Filament v4. Detectamos qué comando existe antes de ejecutar.
        $commands = self::resolvePostCommands();
        $commandErrors = [];

        foreach ($commands as $command) {
            $fullCommand = "php artisan $command";
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($fullCommand, $descriptorspec, $pipes, base_path());

            if (is_resource($process)) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $returnValue = proc_close($process);

                if ($returnValue !== 0) {
                    Log::warning("FilamentScaffold: El comando '$fullCommand' terminó con código $returnValue", [
                        'error'  => $error,
                        'output' => $output,
                    ]);
                    // No tratamos todos los errores como fatales (algunos comandos pueden no existir)
                }
            }
        }

        if (empty($commandErrors)) {
            Notification::make()
                ->success()
                ->persistent()
                ->title('Scaffold created')
                ->body('The scaffold resource has been created successfully.')
                ->icon('heroicon-o-cube-transparent')
                ->send();
        } else {
            Notification::make()
                ->title('Error running commands')
                ->body('Check logs for more details.')
                ->danger()
                ->send();
        }
    }

    // ─── PATH DETECTION (v3 / v4 / v5) ────────────────────────────────────────

    /**
     * NUEVO en el fork v4/v5:
     * Filament v4 cambió la estructura de directorios por defecto.
     *
     * v3: app/Filament/Resources/PostResource.php
     * v4: app/Filament/Resources/PostResource/PostResource.php
     *
     * Intentamos obtener el path desde el output de Artisan y, si no viene,
     * probamos las rutas posibles en el sistema de archivos.
     */
    protected static function detectResourcePath(string $resourceName, string $artisanOutput): ?string
    {
        // Primero intentamos extraer del output de Artisan (forma más confiable)
        preg_match('/\[([^\]]+)\]/', $artisanOutput, $matches);

        if (! empty($matches[1]) && file_exists($matches[1])) {
            return $matches[1];
        }

        // Rutas candidatas en orden de prioridad
        $candidates = [
            // v4 nueva estructura: dentro de su propia carpeta
            app_path("Filament/Resources/{$resourceName}/{$resourceName}.php"),
            // v3 estructura clásica (también funciona en v4 con FileGenerationFlag)
            app_path("Filament/Resources/{$resourceName}.php"),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * NUEVO en el fork v4/v5:
     * Detecta si un comando Artisan soporta una opción específica.
     * Usado para verificar si --view sigue existiendo en make:filament-resource.
     */
    protected static function artisanOptionExists(string $command, string $option): bool
    {
        try {
            $app = app();
            $artisan = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            /** @phpstan-ignore-next-line */
            $cmd = $artisan->all()[$command] ?? null;

            if ($cmd === null) {
                return false;
            }

            return $cmd->getDefinition()->hasOption(ltrim($option, '-'));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * NUEVO en el fork v4/v5:
     * Resuelve los comandos post-generación según la versión de Filament instalada.
     * - filament:cache-components  → v3
     * - filament:optimize          → v4/v5
     *
     * @return array<string>
     */
    protected static function resolvePostCommands(): array
    {
        $base = [
            'cache:clear',
            'config:cache',
            'config:clear',
            'route:cache',
            'route:clear',
            'icons:cache',
        ];

        // Detectar comando filament correcto
        try {
            $app = app();
            $artisan = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            /** @phpstan-ignore-next-line */
            $all = $artisan->all();

            if (isset($all['filament:optimize'])) {
                // Filament v4/v5
                $base[] = 'filament:optimize';
            } elseif (isset($all['filament:cache-components'])) {
                // Filament v3
                $base[] = 'filament:cache-components';
            }
        } catch (\Throwable) {
            // Si no podemos detectar, no ejecutamos ninguno de los dos
        }

        return $base;
    }

    // ─── RESOURCE FILE OVERWRITE ───────────────────────────────────────────────

    /**
     * CAMBIO v4/v5: En Filament v4, por defecto el form y la table
     * se generan en clases Schema separadas (si no se usa FileGenerationFlag::EMBEDDED_*).
     * Este método detecta si hay Schema classes y las modifica también.
     */
    public static function overwriteResourceFile(?string $resourceFile, array $data, string $resourceName): void
    {
        if (! $resourceFile || ! file_exists($resourceFile)) {
            Log::warning("FilamentScaffold: No se encontró el archivo de resource en: $resourceFile");

            return;
        }

        $modelName = self::getFileName($data['Model']);
        $content = file_get_contents($resourceFile);

        $formSchema  = self::generateFormSchema($data);
        $tableSchema = self::generateTableSchema($data);

        // ── Actualizar la referencia al Model ─────────────────────────────────
        $useClassChange = "use App\\Models\\{$modelName};";
        $classChange    = "protected static ?string \$model = {$modelName}::class;";

        $content = preg_replace('/use\s+App\\\\Models\\\\.*?;/s', $useClassChange, $content);
        $content = preg_replace('/protected static\s+\?string\s+\$model\s*=\s*[^\;]+;/s', $classChange, $content);

        // ── Detectar si es estructura v4 con Schema classes separadas ─────────
        $resourceDir = dirname($resourceFile);
        $schemaDir = $resourceDir . '/Schemas';

        // Filament v4 genera: Resources/PostResource/Schemas/PostSchema.php (form + infolist)
        // y tablas en:        Resources/PostResource/Tables/PostTable.php (si EMBEDDED_PANEL_RESOURCE_TABLES no está)
        $hasSchemaDir = is_dir($schemaDir);
        $schemaFile   = $schemaDir . "/{$resourceName}Schema.php";
        $hasSchema    = file_exists($schemaFile);

        $tableDir  = $resourceDir . '/Tables';
        $tableFile = $tableDir . "/{$resourceName}Table.php";
        $hasTable  = file_exists($tableFile);

        if ($hasSchema) {
            // ── v4 con Schema class separada: reescribir el Schema ─────────────
            self::overwriteSchemaFile($schemaFile, $data, $modelName, $formSchema);
        } else {
            // ── v3 / v4 embedded: modificar form() directamente en el resource ─
            $formFunction = <<<EOD
                public static function form(Form \$form): Form
                    {
                        return \$form
                            ->schema([
                                {$formSchema}
                            ]);
                    }
                EOD;

            $content = preg_replace('/public static function form.*?\{.*?\}/s', $formFunction, $content);
        }

        if ($hasTable) {
            // ── v4 con Table class separada ───────────────────────────────────
            self::overwriteTableClassFile($tableFile, $data, $tableSchema);
        } else {
            // ── v3 / v4 embedded: modificar table() directamente en el resource ─
            $tableFunction = <<<EOD
                public static function table(Table \$table): Table
                    {
                        return \$table
                            ->columns([
                                {$tableSchema}
                            ])
                            ->filters([
                                //
                            ])
                            ->actions([
                                Tables\Actions\ViewAction::make(),
                                Tables\Actions\EditAction::make(),
                            ])
                            ->bulkActions([
                                Tables\Actions\BulkActionGroup::make([
                                    Tables\Actions\DeleteBulkAction::make(),
                                ]),
                            ]);
                    }
                EOD;

            $content = preg_replace('/public static function table.*?\{.*?\}/s', $tableFunction, $content);
        }

        file_put_contents($resourceFile, $content);
    }

    /**
     * NUEVO en el fork v4/v5:
     * Reescribe el archivo Schema separado que genera Filament v4.
     * Ejemplo: app/Filament/Resources/PostResource/Schemas/PostSchema.php
     */
    protected static function overwriteSchemaFile(string $schemaFile, array $data, string $modelName, string $formSchema): void
    {
        if (! file_exists($schemaFile)) {
            return;
        }

        $content = file_get_contents($schemaFile);

        // El método en los Schema de v4 se llama schema() o form() dependiendo del tipo
        // Filament v4 genera: public function schema(): array { return [...]; }
        $schemaMethod = <<<EOD
            public function schema(): array
                {
                    return [
                        {$formSchema}
                    ];
                }
            EOD;

        // Reemplazar el método schema() si existe
        if (str_contains($content, 'public function schema()')) {
            $content = preg_replace('/public function schema\(\).*?\{.*?\}/s', $schemaMethod, $content);
        } elseif (str_contains($content, 'public static function schema()')) {
            $schemaMethod = str_replace('public function', 'public static function', $schemaMethod);
            $content = preg_replace('/public static function schema\(\).*?\{.*?\}/s', $schemaMethod, $content);
        }

        file_put_contents($schemaFile, $content);
    }

    /**
     * NUEVO en el fork v4/v5:
     * Reescribe el archivo Table class separado que genera Filament v4.
     * Ejemplo: app/Filament/Resources/PostResource/Tables/PostTable.php
     */
    protected static function overwriteTableClassFile(string $tableFile, array $data, string $tableSchema): void
    {
        if (! file_exists($tableFile)) {
            return;
        }

        $content = file_get_contents($tableFile);

        $tableMethod = <<<EOD
            public function table(Table \$table): Table
                {
                    return \$table
                        ->columns([
                            {$tableSchema}
                        ])
                        ->filters([
                            //
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make(),
                            Tables\Actions\EditAction::make(),
                        ])
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                            ]),
                        ]);
                }
            EOD;

        $content = preg_replace('/public function table.*?\{.*?\}/s', $tableMethod, $content);
        file_put_contents($tableFile, $content);
    }

    // ─── FORM & TABLE SCHEMA GENERATORS ───────────────────────────────────────

    public static function generateFormSchema(array $data): string
    {
        $fields = [];

        foreach ($data['Table'] as $column) {
            $name = $column['name'];
            $type = $column['type'] ?? 'string';

            $field = match (true) {
                in_array($type, ['boolean'])
                    => "Forms\\Components\\Toggle::make('{$name}')",
                in_array($type, ['text', 'mediumText', 'longText'])
                    => "Forms\\Components\\Textarea::make('{$name}')->columnSpanFull()",
                in_array($type, ['date'])
                    => "Forms\\Components\\DatePicker::make('{$name}')",
                in_array($type, ['datetime', 'timestamp'])
                    => "Forms\\Components\\DateTimePicker::make('{$name}')",
                default
                    => "Forms\\Components\\TextInput::make('{$name}')" . (($column['nullable'] ?? false) ? '' : '->required()'),
            };

            $fields[] = $field;
        }

        return implode(",\n                                ", $fields);
    }

    public static function generateTableSchema(array $data): string
    {
        $columns = [];

        foreach ($data['Table'] as $column) {
            $name = $column['name'];
            $type = $column['type'] ?? 'string';

            $col = match (true) {
                in_array($type, ['boolean'])
                    => "Tables\\Columns\\IconColumn::make('{$name}')->boolean()",
                in_array($type, ['date', 'datetime', 'timestamp'])
                    => "Tables\\Columns\\TextColumn::make('{$name}')->dateTime()->sortable()",
                default
                    => "Tables\\Columns\\TextColumn::make('{$name}')->sortable()->searchable()",
            };

            $columns[] = $col;
        }

        return implode(",\n                                ", $columns);
    }

    // ─── MIGRATION FILE ────────────────────────────────────────────────────────

    public static function overwriteMigrationFile(?string $filePath, array $data): void
    {
        if (! $filePath || ! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        $upPart = self::generateUp($data);

        $upFunction = <<<EOD
            public function up(): void
                {
                    Schema::create('{$data['Table Name']}', function (Blueprint \$table) {
                        \$table->id();
                        {$upPart};
                    });
                }
            EOD;

        $downFunction = <<<EOD
            public function down(): void
                {
                    Schema::dropIfExists('{$data['Table Name']}');
                }
            EOD;

        $content = preg_replace('/public function up.*?\{.*?\}/s', $upFunction, $content);
        $content = preg_replace('/public function down.*?\{.*?\}/s', $downFunction, $content);

        file_put_contents($filePath, $content);

        if ($data['Run Migrate'] == true) {
            Artisan::call('migrate');
        }
    }

    public static function generateUp(array $data): string
    {
        $fields = array_map(
            fn (array $column): string => self::generateColumnDefinition($column),
            $data['Table']
        );

        if ($data['Created_at & Updated_at'] == true) {
            $fields[] = '$table->timestamps()';
        }

        if ($data['Soft Delete'] == true) {
            $fields[] = '$table->softDeletes()';
        }

        return implode(";\n                        ", $fields);
    }

    private static function generateColumnDefinition(array $column): string
    {
        $definition = "\$table->{$column['type']}('{$column['name']}')";

        $methods = [
            'nullable' => fn (): bool => $column['nullable'] ?? false,
            'default'  => fn (): ?string => ($column['default'] !== null && $column['default'] !== '') ? $column['default'] : null,
            'comment'  => fn (): ?string => ($column['comment'] !== null && $column['comment'] !== '') ? $column['comment'] : null,
            'key'      => fn (): ?string => ($column['key'] !== null && $column['key'] !== '') ? $column['key'] : null,
        ];

        foreach ($methods as $method => $condition) {
            $value = $condition();

            if ($value !== null && $value !== false) {
                $definition .= match ($method) {
                    'nullable' => '->nullable()',
                    'default'  => "->default('{$value}')",
                    'comment'  => "->comment('{$value}')",
                    'key'      => "->{$value}()",
                };
            }
        }

        return $definition;
    }

    // ─── MODEL FILE ────────────────────────────────────────────────────────────

    public static function overwriteModelFile(?string $filePath, array $data): void
    {
        if (! $filePath || ! file_exists($filePath)) {
            return;
        }

        $column = self::getColumn($data);
        $content = file_get_contents($filePath);

        if ($data['Soft Delete'] == true) {
            $useSoftDel = "use Illuminate\\Database\\Eloquent\\Model;\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;";
            $withSoftdel = "use HasFactory;\n    use SoftDeletes;\n    protected \$table = '{$data['Table Name']}';\n    protected \$fillable = {$column};";

            $content = preg_replace('/use Illuminate\\\\Database\\\\Eloquent\\\\Model;/s', $useSoftDel, $content);
            $content = preg_replace('/use HasFactory;/s', $withSoftdel, $content);
        } else {
            $chooseTable = "use HasFactory;\n    protected \$table = '{$data['Table Name']}';\n    protected \$fillable = {$column};";
            $content = preg_replace('/use HasFactory;/s', $chooseTable, $content);
        }

        file_put_contents($filePath, $content);
    }

    public static function getColumn(array $data): string
    {
        $fields = array_column($data['Table'], 'name');

        return "['" . implode("','", $fields) . "']";
    }

    // ─── CONTROLLER FILE ───────────────────────────────────────────────────────

    public static function overwriteControllerFile(?string $filePath, array $data): void
    {
        if (! $filePath || ! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        $changeIndex = <<<'EOD'
            public function index()
                {
                    return 'This your index page';
                }
            EOD;

        $content = preg_replace('/public function index.*?\{.*?\}/s', $changeIndex, $content);
        file_put_contents($filePath, $content);
    }

    // ─── ROUTES ────────────────────────────────────────────────────────────────

    public static function addRoutes(array $data, string $controllerName): void
    {
        // Soporte para rutas en Windows (backslash) y Unix (slash)
        $webPhp = base_path('routes/web.php');

        if (! file_exists($webPhp)) {
            $webPhp = base_path('routes\\web.php');
        }

        if (! file_exists($webPhp)) {
            return;
        }

        $content = file_get_contents($webPhp);

        $useStatement = "use Illuminate\\Support\\Facades\\Route;\nuse App\\Http\\Controllers\\{$controllerName};";
        $addRoute = "\n\nRoute::resource('{$data['Table Name']}', {$controllerName}::class)->only([\n    'index', 'show'\n]);";

        $content = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/s', $useStatement, $content);
        $content .= $addRoute;

        file_put_contents($webPhp, $content);
    }

    // ─── POLICY FILE (FilamentShield) ──────────────────────────────────────────

    public static function updatePolicyFile(string $filePath, string $modelName): void
    {
        if (! class_exists(\BezhanSalleh\FilamentShield\FilamentShield::class)) {
            return;
        }

        if (! file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);

        $modelFunctionNameVariable = Str::snake(Str::plural($modelName));
        $permissionBase = Str::of($modelName)
            ->afterLast('\\')
            ->snake()
            ->replace('_', '::');

        $methodTemplates = [
            'import_data'            => "return \$user->can('import_data_{$permissionBase}');",
            'download_template_file' => "return \$user->can('download_template_file_{$permissionBase}');",
            'viewAny'                => "return \$user->can('view_any_{$permissionBase}');",
            'view'                   => "return \$user->can('view_{$permissionBase}');",
            'create'                 => "return \$user->can('create_{$permissionBase}');",
            'update'                 => "return \$user->can('update_{$permissionBase}');",
            'delete'                 => "return \$user->can('delete_{$permissionBase}');",
            'deleteAny'              => "return \$user->can('delete_any_{$permissionBase}');",
            'restore'                => "return \$user->can('restore_{$permissionBase}');",
            'restoreAny'             => "return \$user->can('restore_any_{$permissionBase}');",
            'forceDelete'            => "return \$user->can('force_delete_{$permissionBase}');",
            'forceDeleteAny'         => "return \$user->can('force_delete_any_{$permissionBase}');",
            'replicate'              => "return \$user->can('replicate_{$permissionBase}');",
            'reorder'                => "return \$user->can('reorder_{$permissionBase}');",
        ];

        $noModelMethods = [
            'viewAny', 'create', 'deleteAny', 'restoreAny',
            'forceDeleteAny', 'reorder', 'import_data', 'download_template_file',
        ];

        $newMethods = '';

        foreach ($methodTemplates as $method => $returnStatement) {
            $hasModel       = ! in_array($method, $noModelMethods);
            $modelParam     = $hasModel ? ", {$modelName} \${$modelFunctionNameVariable}" : '';
            $methodSignature = "public function {$method}(User \$user{$modelParam}): bool";
            $methodBody     = "    {\n        {$returnStatement}\n    }";
            $fullMethod     = "\n\n    {$methodSignature}\n{$methodBody}";

            if (! str_contains($content, "public function {$method}(")) {
                $newMethods .= $fullMethod;
            } else {
                $pattern     = "/public function {$method}\([^\)]*\): bool\n\s*{\n.*?\n\s*}/s";
                $replacement = "{$methodSignature}\n{$methodBody}";
                $content     = preg_replace($pattern, $replacement, $content);
            }
        }

        $content = preg_replace('/}(\s*)$/', $newMethods . "\n}", $content);
        file_put_contents($filePath, $content);
    }

    // ─── API SERVICE (filament-api-service) ────────────────────────────────────

    protected static function generateApiService(array $data, string $resourceName): void
    {
        $resourcePath = $data['Resource'] ?? null;

        if (! $resourcePath) {
            Notification::make()
                ->danger()
                ->title('Missing Resource Input')
                ->body('The Resource input is required to generate the API service.')
                ->send();

            return;
        }

        $resourceClass = str_replace(['/', '\\'], '\\', $resourcePath);
        $resourceClass = preg_replace('/^app\\\\/i', 'App\\', $resourceClass);
        $resourceClassName = class_basename($resourceClass);
        $apiServiceName = str_replace('Resource', '', $resourceClassName);

        if (! class_exists($resourceClass)) {
            Notification::make()
                ->danger()
                ->title('Resource Class Not Found')
                ->body("The class `{$resourceClass}` does not exist. Please generate the resource first.")
                ->send();

            return;
        }

        try {
            $defaultPanelId = \Filament\Facades\Filament::getDefaultPanel()->getId();

            Artisan::call('make:filament-api-service', [
                'resource'         => $apiServiceName,
                '--panel'          => $defaultPanelId,
                '--no-interaction' => true,
            ]);
            $output = Artisan::output();

            if (str_contains($output, 'created') || str_contains($output, 'generated')) {
                Notification::make()
                    ->success()
                    ->persistent()
                    ->title('API Service Created Successfully!')
                    ->body(new \Illuminate\Support\HtmlString("
                        API service generated for: <b>{$resourceClassName}</b><br>
                        Location: <b>app/Filament/Resources/{$resourceClassName}/Api</b>
                    "))
                    ->icon('heroicon-o-code-bracket')
                    ->send();
            } else {
                Notification::make()
                    ->warning()
                    ->persistent()
                    ->title('API Service Generation Issue')
                    ->body(new \Illuminate\Support\HtmlString("<small><pre>" . e($output) . "</pre></small>"))
                    ->icon('heroicon-o-exclamation-triangle')
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('API Service Generation Failed')
                ->body('Unexpected error: ' . $e->getMessage())
                ->send();
        }
    }
}
