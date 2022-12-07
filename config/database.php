<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
     */

    'default'     => env('DB_CONNECTION', 'aws'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
     */

    'connections' => [

        'aws'     => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST_AWS'),
            'port'        => env('DB_PORT_AWS'),
            'database'    => env('DB_DATABASE_AWS'),
            'username'    => env('DB_USERNAME_AWS'),
            'password'    => env('DB_PASSWORD_AWS'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => false,
            'engine'      => null,
        ],
        'crm'     => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST_CRM'),
            'port'        => env('DB_PORT_CRM'),
            'database'    => env('DB_DATABASE_CRM'),
            'username'    => env('DB_USERNAME_CRM'),
            'password'    => env('DB_PASSWORD_CRM'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => false,
            'engine'      => null,
        ],
        'crmUtf8' => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST_CRM'),
            'port'        => env('DB_PORT_CRM'),
            'database'    => env('DB_DATABASE_CRM'),
            'username'    => env('DB_USERNAME_CRM'),
            'password'    => env('DB_PASSWORD_CRM'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'latin1',
            'collation'   => 'latin1_swedish_ci',
            'prefix'      => '',
            'strict'      => false,
            'engine'      => null,
        ],
        'app'     => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST_APP'),
            'port'        => env('DB_PORT_APP'),
            'database'    => env('DB_DATABASE_APP'),
            'username'    => env('DB_USERNAME_APP'),
            'password'    => env('DB_PASSWORD_APP'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => false,
            'engine'      => null,
        ],
        'crm2'    => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST_CRM2'),
            'port'        => env('DB_PORT_CRM2'),
            'database'    => env('DB_DATABASE_CRM2'),
            'username'    => env('DB_USERNAME_CRM2'),
            'password'    => env('DB_PASSWORD_CRM2'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => false,
            'engine'      => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
     */

    'migrations'  => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
     */

    'redis'       => [

        'client'  => 'predis',

        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

    ],

];
