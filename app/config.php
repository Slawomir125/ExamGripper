<?php

return [
    'title' => 'Examgripper',
    'default_template' => 'default',

    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=examgripper;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
    ],
    'db_scheme_check' => 2,
    'menu_items' => [
        ['label' => 'Start', 'url' => ''],
        ['label' => 'Kategorie', 'url' => 'kategorie/'],
        ['label' => 'Zadania', 'url' => 'zadania/'],
        ['label' => 'Quiz', 'url' => 'quiz/'],
        ['label' => 'Postępy', 'url' => 'postepy/'],
    ],
    'debug' => true,
    'save_errors' => true,
    'php_log_file' => 'php.log',
    'sql_log_file' => 'sql.log',
    'db_scheme_log_file' => 'dbScheme.log',
];