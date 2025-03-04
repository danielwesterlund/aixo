<?php
namespace Aixo;
use xPDO\xPDO;

class modAixoTokenUsage extends \xPDO\Om\xPDOSimpleObject {
    public static $metaMap = [
        'package'   => 'aixo',
        'version'   => '1.0',
        'table'     => 'aixo_token_usage',
        'extends'   => 'xPDO\Om\xPDOSimpleObject',
        'fields'    => [
            'provider'  => '',
            'model'     => '',
            'tokens'    => 0,
            'timestamp' => NULL,
            'metadata'  => NULL,
        ],
        'fieldMeta' => [
            'provider'  => [
                'dbtype'    => 'varchar',
                'precision' => '100',
                'phptype'   => 'string',
                'null'      => false,
            ],
            'model'     => [
                'dbtype'    => 'varchar',
                'precision' => '100',
                'phptype'   => 'string',
                'null'      => false,
            ],
            'tokens'    => [
                'dbtype'    => 'int',
                'precision' => '10',
                'phptype'   => 'integer',
                'null'      => false,
                'default'   => 0,
            ],
            'timestamp' => [
                'dbtype'    => 'datetime',
                'phptype'   => 'datetime',
                'null'      => false,
            ],
            'metadata'  => [
                'dbtype'    => 'text',
                'phptype'   => 'string',
                'null'      => true,
            ],
        ],
        'indexes' => [
            'provider' => [
                'alias' => 'provider',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'provider' => [
                        'length' => 0,
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'model' => [
                'alias' => 'model',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'model' => [
                        'length' => 0,
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
            'timestamp' => [
                'alias' => 'timestamp',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => [
                    'timestamp' => [
                        'length' => 0,
                        'collation' => 'A',
                        'null' => false,
                    ],
                ],
            ],
        ],
    ];
}
