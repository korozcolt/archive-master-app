<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class GenerateApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-docs {--output=storage/app/api-docs.json : Output file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OpenAPI documentation from API controllers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating API documentation...');

        $apiConfig = config('api.documentation');
        
        $openApiSpec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $apiConfig['title'],
                'description' => $apiConfig['description'],
                'version' => $apiConfig['version'],
                'contact' => $apiConfig['contact'],
                'license' => $apiConfig['license'],
            ],
            'servers' => $apiConfig['servers'],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Laravel Sanctum token authentication'
                    ]
                ],
                'schemas' => $this->generateSchemas(),
            ],
            'security' => [
                ['sanctum' => []]
            ],
        ];

        $controllers = $this->getApiControllers();
        
        foreach ($controllers as $controller) {
            $this->info("Processing controller: {$controller}");
            $paths = $this->extractPathsFromController($controller);
            $openApiSpec['paths'] = array_merge($openApiSpec['paths'], $paths);
        }

        $outputPath = $this->option('output');
        $fullPath = storage_path('app/' . basename($outputPath));
        
        File::put($fullPath, json_encode($openApiSpec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $this->info("API documentation generated successfully at: {$fullPath}");
        $this->info("Total endpoints documented: " . count($openApiSpec['paths']));
        
        return Command::SUCCESS;
    }

    /**
     * Get all API controllers.
     *
     * @return array
     */
    private function getApiControllers(): array
    {
        return [
            'App\\Http\\Controllers\\Api\\AuthController',
            'App\\Http\\Controllers\\Api\\DocumentController',
            'App\\Http\\Controllers\\Api\\UserController',
            'App\\Http\\Controllers\\Api\\CompanyController',
            'App\\Http\\Controllers\\Api\\CategoryController',
            'App\\Http\\Controllers\\Api\\StatusController',
            'App\\Http\\Controllers\\Api\\TagController',
        ];
    }

    /**
     * Extract paths from a controller.
     *
     * @param string $controllerClass
     * @return array
     */
    private function extractPathsFromController(string $controllerClass): array
    {
        $paths = [];
        
        if (!class_exists($controllerClass)) {
            $this->warn("Controller {$controllerClass} not found");
            return $paths;
        }

        $reflection = new ReflectionClass($controllerClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $resourceName = $this->getResourceNameFromController($controllerClass);
        
        foreach ($methods as $method) {
            if ($method->class !== $controllerClass) {
                continue;
            }
            
            $methodName = $method->getName();
            $pathInfo = $this->getPathInfoForMethod($methodName, $resourceName);
            
            if ($pathInfo) {
                $paths[$pathInfo['path']][$pathInfo['method']] = [
                    'tags' => [$resourceName],
                    'summary' => $this->generateSummary($methodName, $resourceName),
                    'description' => $this->generateDescription($methodName, $resourceName),
                    'parameters' => $pathInfo['parameters'] ?? [],
                    'responses' => $this->generateResponses($methodName),
                ];
                
                if (in_array($methodName, ['store', 'update'])) {
                    $paths[$pathInfo['path']][$pathInfo['method']]['requestBody'] = [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => "#/components/schemas/{$resourceName}Request"
                                ]
                            ]
                        ]
                    ];
                }
                
                if (!in_array($methodName, ['login'])) {
                    $paths[$pathInfo['path']][$pathInfo['method']]['security'] = [['sanctum' => []]];
                }
            }
        }
        
        return $paths;
    }

    /**
     * Get resource name from controller class.
     *
     * @param string $controllerClass
     * @return string
     */
    private function getResourceNameFromController(string $controllerClass): string
    {
        $className = class_basename($controllerClass);
        return str_replace('Controller', '', $className);
    }

    /**
     * Get path information for a method.
     *
     * @param string $methodName
     * @param string $resourceName
     * @return array|null
     */
    private function getPathInfoForMethod(string $methodName, string $resourceName): ?array
    {
        $resourcePath = '/' . strtolower($resourceName === 'Auth' ? 'auth' : Str::plural(strtolower($resourceName)));
        
        switch ($methodName) {
            case 'index':
                return ['path' => $resourcePath, 'method' => 'get'];
            case 'store':
                return ['path' => $resourcePath, 'method' => 'post'];
            case 'show':
                return [
                    'path' => $resourcePath . '/{id}',
                    'method' => 'get',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ]
                ];
            case 'update':
                return [
                    'path' => $resourcePath . '/{id}',
                    'method' => 'put',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ]
                ];
            case 'destroy':
                return [
                    'path' => $resourcePath . '/{id}',
                    'method' => 'delete',
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ]
                ];
            case 'login':
                return ['path' => '/auth/login', 'method' => 'post'];
            case 'logout':
                return ['path' => '/auth/logout', 'method' => 'post'];
            case 'me':
                return ['path' => '/auth/me', 'method' => 'get'];
            default:
                return null;
        }
    }

    /**
     * Generate summary for a method.
     *
     * @param string $methodName
     * @param string $resourceName
     * @return string
     */
    private function generateSummary(string $methodName, string $resourceName): string
    {
        $actions = [
            'index' => 'List all',
            'store' => 'Create a new',
            'show' => 'Get a specific',
            'update' => 'Update a specific',
            'destroy' => 'Delete a specific',
            'login' => 'User login',
            'logout' => 'User logout',
            'me' => 'Get current user',
        ];
        
        $action = $actions[$methodName] ?? ucfirst($methodName);
        
        if (in_array($methodName, ['login', 'logout', 'me'])) {
            return $action;
        }
        
        return $action . ' ' . strtolower($resourceName);
    }

    /**
     * Generate description for a method.
     *
     * @param string $methodName
     * @param string $resourceName
     * @return string
     */
    private function generateDescription(string $methodName, string $resourceName): string
    {
        return $this->generateSummary($methodName, $resourceName) . ' with proper authorization and validation.';
    }

    /**
     * Generate responses for a method.
     *
     * @param string $methodName
     * @return array
     */
    private function generateResponses(string $methodName): array
    {
        $responses = [
            '401' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
            '403' => [
                'description' => 'Forbidden',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
        ];
        
        switch ($methodName) {
            case 'index':
                $responses['200'] = [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => ['type' => 'array'],
                                    'meta' => ['type' => 'object']
                                ]
                            ]
                        ]
                    ]
                ];
                break;
            case 'store':
                $responses['201'] = [
                    'description' => 'Resource created successfully',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => ['type' => 'object'],
                                    'message' => ['type' => 'string']
                                ]
                            ]
                        ]
                    ]
                ];
                $responses['422'] = [
                    'description' => 'Validation error',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'message' => ['type' => 'string'],
                                    'errors' => ['type' => 'object']
                                ]
                            ]
                        ]
                    ]
                ];
                break;
            case 'show':
            case 'update':
                $responses['200'] = [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'data' => ['type' => 'object']
                                ]
                            ]
                        ]
                    ]
                ];
                $responses['404'] = [
                    'description' => 'Resource not found'
                ];
                break;
            case 'destroy':
                $responses['204'] = [
                    'description' => 'Resource deleted successfully'
                ];
                $responses['404'] = [
                    'description' => 'Resource not found'
                ];
                break;
            default:
                $responses['200'] = [
                    'description' => 'Successful response'
                ];
        }
        
        return $responses;
    }

    /**
     * Generate component schemas.
     *
     * @return array
     */
    private function generateSchemas(): array
    {
        return [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'company_id' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ]
            ],
            'Category' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'color' => ['type' => 'string', 'nullable' => true],
                    'company_id' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ]
            ],
            'CategoryRequest' => [
                'type' => 'object',
                'required' => ['name'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'description' => ['type' => 'string', 'maxLength' => 1000, 'nullable' => true],
                    'color' => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$', 'nullable' => true],
                    'parent_id' => ['type' => 'integer', 'nullable' => true],
                    'is_active' => ['type' => 'boolean', 'nullable' => true],
                ]
            ],
        ];
    }
}