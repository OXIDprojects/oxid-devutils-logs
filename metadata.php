<?php

$sMetadataVersion = '2.1';
$aModule = [
    'id'          => 'devutils-logs',
    'title'       => '[devutils] logs',
    'description' => 'Logs parser for oxid eshop.<br/>add this code to your config.inc.php to save webserver errors into log/error.log<br><textarea cols="80" rows="3">ini_set(\'error_reporting\',24567); 
ini_set(\'log_errors\',1);
ini_set(\'error_log\',dirname(__FILE__).\'/log/error.log\');</textarea><hr/>access token for chrome extension: ' . md5($_SERVER["DOCUMENT_ROOT"]),
    'version'     => '1.0.0',
    'author'      => 'OXID Community',
    'email'       => '',
    'url'         => 'https://github.com/OXIDprojects/oxid-devutils-logs',
    'extend'      => [
        \OxidEsales\Eshop\Application\Controller\StartController::class => OxidCommunity\DevutilsLogs\Controller\StartController::class
    ],
    'controllers' => [
        'dev_logs' => OxidCommunity\DevutilsLogs\Controller\Admin\Logs::class
    ],
    'templates'   => [
        'dev_logs.tpl' => 'oxid-community/devutils-logs/views/admin/dev_logs.tpl'
    ],
    'settings'    => [
        [
            'group'    => 'Dev',
            'name'     => 's_Dev_serverLogPath',
            'type'     => 'str',
            'position' => 0,
            'value'    => 'log/error.log'
        ]
    ]
];
