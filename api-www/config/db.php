<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=db;dbname=gt_music_app_db',
    'username' => 'root',
    'password' => 'secretroot',
    'charset' => 'utf8',
	'tablePrefix' => 'gt_'
    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
