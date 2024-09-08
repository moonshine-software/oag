<?php

declare(strict_types=1);

namespace MoonShine\OAG\Console\Commands;

use Illuminate\Console\Command;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Contracts\HasDefaultValueContract;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Password;
use Symfony\Component\Console\Attribute\AsCommand;
use MoonShine\OAG\OpenApiGenerator;

#[AsCommand(name: 'oag:generate')]
final class GenerateCommand extends Command
{
    public function __construct(private readonly CoreContract $core)
    {
        parent::__construct();
    }

    private function endpoint(string $url): string
    {
        $url = preg_replace('/(!)(.*?)(!)/', '{$2}', $url);

        return str_replace(
            $this->core->getRouter()->getEndpoints()->home(), '',
            $url
        );
    }

    private function fieldToType(FieldContract $field): array
    {
        $type = 'string';
        $format = null;
        $extra = [];

        if ($field instanceof Number) {
            $type = 'integer';
        }

        if ($field instanceof BelongsTo) {
            $type = 'integer';
        }

        if ($field instanceof Password) {
            $type = 'string';
            $format = 'password';
        }

        if ($field instanceof HasDefaultValueContract) {
            $extra['default'] = $field->getDefault();
        }

        return array_filter(['type' => $type, 'format' => $format, ...$extra]);
    }

    public function handle(): int
    {
        $builder = new OpenApiGenerator();
        $builder
            ->info($this->core->getConfig()->getTitle())
            ->server(
                $this->core->getRouter()->getEndpoints()->home(),
                'Production API server'
            );

        $builder->authorizationPaths(
            $this->endpoint($this->core->getRouter()->to('authenticate'))
        );

        /** @var ModelResource $resource */
        foreach ($this->core->getResources() as $resource) {
            $component = class_basename($resource->getDataInstance());

            $properties = [];

            foreach ($resource->getIndexFields() as $field) {
                $properties[$field->getColumn()] = $this->fieldToType($field);
            }

            $builder->component($component, [
                'type' => 'object',
                'properties' => $properties,
            ]);

            $builder->component("{$component}Collection", [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer', 'default' => 1],
                    'first_page_url' => ['type' => 'string'],
                    'from' => ['type' => 'integer', 'default' => 1],
                    'next_page_url' => ['type' => 'string', 'nullable' => true],
                    'prev_page_url' => ['type' => 'string', 'nullable' => true],
                    'to' => ['type' => 'integer'],
                    'path' => ['type' => 'string'],
                    'per_page' => ['type' => 'integer'],
                    'data' => [
                        'type' => 'array',
                        'items' => [
                            '$ref' => "#/components/schemas/$component",
                        ],
                    ],
                ]
            ]);

            $parameters = [];

            foreach ($resource->getFilters()->onlyFields() as $filter) {
                if ($filter->isGroup()) {
                    $parameters[] = [
                        'name' => $filter->getNameAttribute(),
                        'in' => 'query',
                        'required' => false,
                        'style' => 'deepObject',
                        'explode' => true,
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'integer',
                            ],
                        ],
                    ];
                } else {
                    $parameters[] = [
                        'name' => $filter->getNameAttribute(),
                        'in' => 'query',
                        'required' => false,
                        'schema' => $this->fieldToType($filter),
                    ];
                }
            }

            $sorts = [];
            foreach (
                $resource->getIndexFields()->filter(fn (FieldContract $field) => $field->isSortable()) as $sortField
            ) {
                $sorts[] = $sortField->getColumn();
                $sorts[] = '-' . $sortField->getColumn();
            }

            if ($sorts !== []) {
                $parameters[] = [
                    'name' => 'sort',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string', 'enum' => $sorts],
                ];
            }

            $parameters[] = [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'default' => 1],
                'example' => 1,
            ];

            $alias = str($resource->getUriKey())->camel();

            $builder
                ->tag((string) $alias)
                ->path(
                    path: $this->endpoint($resource->getRouter()->to('crud.index')),
                    method: 'get',
                    data: [
                        'tags' => [(string) $alias],
                        'summary' => "{$resource->getTitle()} - Listing",
                        'operationId' => (string) $alias->append('Index'),
                        'parameters' => $parameters,
                        'responses' => [
                            '200' => [
                                'description' => 'Successful',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => "#/components/schemas/{$component}Collection",
                                        ],
                                    ],
                                ],
                            ],
                            '401' => [
                                '$ref' => "#/components/responses/Unauthorized",
                            ],
                        ],
                    ],
                );

            $properties = [];

            foreach ($resource->getFormFields()->onlyFields() as $field) {
                $properties[$field->getColumn()] = $this->fieldToType($field);
            }

            $defaultResponses = [
                '200' => [
                    '$ref' => "#/components/responses/Success",
                ],
                '201' => [
                    '$ref' => "#/components/responses/Success",
                ],
                '401' => [
                    '$ref' => "#/components/responses/Unauthorized",
                ],
                '422' => [
                    '$ref' => "#/components/responses/ValidationException",
                ],
            ];

            $resourceItemParameter = [
                'name' => 'resourceItem',
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'oneOf' => [
                        ['type' => 'integer'],
                        ['type' => 'string'],
                    ],
                ],
            ];

            $builder->path(
                path: $this->endpoint(
                    $resource->getRouter()->to('crud.update',
                    ['resourceItem' => '!resourceItem!'])
                ),
                method: 'put',
                data: [
                    'tags' => [(string) $alias],
                    'summary' => "{$resource->getTitle()} - Update",
                    'operationId' => (string) $alias->append('Update'),
                    'parameters' => [$resourceItemParameter],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => $properties,
                                ],
                            ],
                        ],
                    ],
                    'responses' => array_filter($defaultResponses, static fn($k) => $k !== 201, ARRAY_FILTER_USE_KEY),
                ]
            );

            $builder->path(
                path: $this->endpoint(
                    $resource->getRouter()->to('crud.store')
                ),
                method: 'post',
                data: [
                    'tags' => [(string) $alias],
                    'summary' => "{$resource->getTitle()} - Create",
                    'operationId' => (string) $alias->append('Create'),
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => array_filter($properties, static fn($k) => $k !== $resource->getDataInstance()->getKeyName(), ARRAY_FILTER_USE_KEY),
                                ],
                            ],
                        ],
                    ],
                    'responses' => array_filter($defaultResponses, static fn($k) => $k !== 200, ARRAY_FILTER_USE_KEY),
                ]
            );

            $builder->path(
                path: $this->endpoint(
                    $resource->getRouter()->to('crud.show', ['resourceItem' => '!resourceItem!'])
                ),
                method: 'get',
                data: [
                    'tags' => [(string) $alias],
                    'summary' => "{$resource->getTitle()} - Show",
                    'operationId' => (string) $alias->append('Show'),
                    'parameters' => [$resourceItemParameter],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => "#/components/schemas/$component",
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            '$ref' => "#/components/responses/Unauthorized",
                        ],
                    ],
                ]
            );

            $builder->path(
                path: $this->endpoint(
                    $resource->getRouter()->to('crud.destroy', ['resourceItem' => '!resourceItem!'])
                ),
                method: 'delete',
                data: [
                    'tags' => [(string) $alias],
                    'summary' => "{$resource->getTitle()} - Delete",
                    'operationId' => (string) $alias->append('Delete'),
                    'parameters' => [$resourceItemParameter],
                    'responses' => $defaultResponses,
                ]
            );

            $builder->path(
                path: $this->endpoint(
                    $resource->getRouter()->to('crud.massDelete')
                ),
                method: 'delete',
                data: [
                    'tags' => [(string) $alias],
                    'summary' => "{$resource->getTitle()} - Mass delete",
                    'operationId' => (string) $alias->append('MassDelete'),
                    'parameters' => [
                        [
                            'name' => 'ids',
                            'in' => 'query',
                            'required' => true,
                            'schema' => [
                                ['type' => 'array'],
                            ],
                        ]
                    ],
                    'responses' => $defaultResponses,
                ]
            );
        }


        $builder
            ->yamlFormat()
            ->jsonFormat()
            ->debug()
            ->build();

        return self::SUCCESS;
    }
}
