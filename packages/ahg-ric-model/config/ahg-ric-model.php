<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Fuseki — RiC-O ontology dataset
    |--------------------------------------------------------------------------
    | Separate from the instance-data dataset. Loaded with RiC-O OWL v1.1
    | from ICA-EGAD (see resources/data/ric-o/loaded-versions.md).
    */
    'fuseki' => [
        'url'      => env('FUSEKI_URL', 'http://localhost:3030'),
        'dataset'  => env('FUSEKI_DATASET_MODEL', 'openric-model'),
        'user'     => env('FUSEKI_USER'),
        'password' => env('FUSEKI_PASSWORD'),
        'timeout'  => (int) env('FUSEKI_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | RiC-O OWL namespace
    |--------------------------------------------------------------------------
    */
    'rico_namespace' => 'https://www.ica.org/standards/RiC/ontology#',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Reference data is near-immutable between package releases; long TTL.
    | Keys are versioned — bumping the package (or reloading the ontology)
    | should call `artisan ric-model:rebuild-cache`.
    */
    'cache' => [
        'store' => env('RIC_MODEL_CACHE_STORE'),  // null = default cache store
        'ttl'   => (int) env('RIC_MODEL_CACHE_TTL', 86400),  // 24h
        'prefix' => 'ric_model',
    ],

    /*
    |--------------------------------------------------------------------------
    | Versions
    |--------------------------------------------------------------------------
    | Available RiC-CM model versions that this package ships data for, and
    | which one the unversioned browse URLs resolve to.
    */
    'versions' => [
        'available' => ['1.0'],
        'latest'    => '1.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribution (CC BY 4.0 compliance)
    |--------------------------------------------------------------------------
    | Rendered in UI chrome and JSON responses so downstream consumers see
    | the required credit.
    */
    'attribution' => [
        'ontology_credit' => 'Records in Contexts-Ontology (RiC-O) v1.1 is published by the International Council on Archives, Expert Group on Archival Description (ICA EGAD), under CC BY 4.0.',
        'ontology_url'    => 'https://github.com/ICA-EGAD/RiC-O',
        'license_url'     => 'https://creativecommons.org/licenses/by/4.0/',
    ],

];
