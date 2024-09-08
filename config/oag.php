<?php

return [
    'title' => 'Docs',
    'path' => realpath(
        resource_path('oag.yaml')
    ),
    'route' => 'oag.json',
    'view' => 'oag::docs',
];
