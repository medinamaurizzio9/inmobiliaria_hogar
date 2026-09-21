<?php

return [
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'directory' => storage_path('app/backups'),
];
