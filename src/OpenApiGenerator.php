<?php

declare(strict_types=1);

namespace MoonShine\OAG;

use JsonException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final class OpenApiGenerator
{
    private array $data;

    private readonly array $security;

    private bool $json = false;

    private bool $yaml = true;

    private bool $debug = false;

    public function __construct()
    {
        $this->security = [
            ['jwtAuth' => []],
        ];

        $this->data = [
            'openapi' => '3.0.1',
            'info' => [
                'title' => '',
                'description' => '',
                'version' => '1.0.0',
            ],
            'servers' => [],
            'paths' => [

            ],
            'components' => [
                'securitySchemes' => [
                    'auth:api' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                ],
                'schemas' => [

                ],
                'responses' => [
                    'Unauthorized' => [
                        'description' => 'Unauthorized',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'Success' => [
                        'description' => 'Successful',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'ValidationException' => [
                        'description' => 'Validation errors',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string'],
                                        'errors' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'array',
                                                'items' => ['type' => 'string']
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'tags' => [],
        ];
    }

    public function server(string $url, string $description = ''): self
    {
        $this->data['servers'][] = [
            'url' => $url,
            'description' => $description,
        ];

        return $this;
    }

    public function info(string $title, string $description = '', string $version = '1.0.0'): self
    {
        $this->data['info'] = [
            'title' => $title,
            'description' => $description,
            'version' => $version,
        ];

        return $this;
    }

    public function tag(string $tag): self
    {
        $this->data['tags'][$tag] = [
            'name' => $tag,
        ];

        return $this;
    }

    public function component(string $component, array $data, string $type = 'schemas'): self
    {
        $this->data['components'][$type][$component] = $data;

        return $this;
    }

    public function path(string $path, string $method, array $data, bool $authorize = true): self
    {
        if($authorize) {
            $data['security'] = $this->security;
        }

        $this->data['paths'][$path][$method] = $data;

        return $this;
    }

    public function authorizationPaths(string $login): self
    {
        return $this->tag('Authentication')->path($login, 'post', [
            'tags' => ['Authentication'],
            'summary' => 'User login',
            'operationId' => 'authenticate',
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'username' => ['type' => 'string', 'example' => 'example@example.com'],
                                'password' => ['type' => 'string', 'example' => '***'],
                            ],
                        ],
                    ],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Successful',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'token' => [
                                        'type' => 'string',
                                        'example' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '401' => [
                    '$ref' => "#/components/responses/Unauthorized",
                ],
                '422' => [
                    '$ref' => "#/components/responses/ValidationException",
                ],
            ],
        ], authorize: false);
    }

    public function jsonFormat(bool $enable = true): self
    {
        $this->json = $enable;

        return $this;
    }

    public function yamlFormat(bool $enable = true): self
    {
        $this->yaml = $enable;

        return $this;
    }

    public function debug(bool $enable = true): self
    {
        $this->debug = $enable;

        return $this;
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function build(): bool
    {
        $this->data['tags'] = array_values($this->data['tags']);

        try {
            $result = Yaml::dump($this->data, 4, 2, flags: YAML::DUMP_MULTI_LINE_LITERAL_BLOCK);

            $result = str_replace('jwtAuth: {  }', 'jwtAuth: []', $result);

            if($this->yaml) {
                file_put_contents(resource_path('oag.yaml'), $result);
            }

            if($this->json) {
                file_put_contents(resource_path('oag.json'), json_encode($this->data, JSON_THROW_ON_ERROR));
            }

            return true;
        } catch (Throwable $e) {
            if($this->debug) {
                throw new $e;
            }

            return false;
        }
    }
}
