<?
if (!defined('B_PROLOG_INCLUDED') || constant('B_PROLOG_INCLUDED') !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;

use Welpodron\Core\Helper;

$moduleId = 'welpodron.specialbasket';

$arTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => 'Настройки внешнего вида ответа',
        'TITLE' => 'Настройки внешнего вида ответа',
        'GROUPS' => [
            [
                'TITLE' => 'Настройки внешнего вида ответа',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_SUCCESS_CONTENT',
                        'LABEL' => 'Использовать успешное сообщение',
                        'VALUE' => Option::get($moduleId, 'USE_SUCCESS_CONTENT'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'SUCCESS_FILE',
                        'LABEL' => 'PHP файл-шаблон успешного ответа',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_FILE'),
                        'TYPE' => 'file',
                        'DESCRIPTION' => 'Если PHP файл-шаблон успешного ответа не задан, то будет использоваться содержимое успешного ответа по умолчанию',
                        'REL'  => 'USE_SUCCESS_CONTENT',
                    ],
                    [
                        'NAME' => 'SUCCESS_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое успешного ответа по умолчанию',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                        'REL'  => 'USE_SUCCESS_CONTENT',
                    ],
                    [
                        'NAME' => 'USE_ERROR_CONTENT',
                        'LABEL' => 'Использовать сообщение об ошибке',
                        'VALUE' => Option::get($moduleId, 'USE_ERROR_CONTENT'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'ERROR_FILE',
                        'LABEL' => 'PHP файл-шаблон ответа с ошибкой',
                        'VALUE' => Option::get($moduleId, 'ERROR_FILE'),
                        'DESCRIPTION' => 'Если PHP файл-шаблон ответа с ошибкой не задан, то будет использоваться содержимое ответа с ошибкой по умолчанию',
                        'TYPE' => 'file',
                        'REL'  => 'USE_ERROR_CONTENT',
                    ],
                    [
                        'NAME' => 'ERROR_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое ответа с ошибкой по умолчанию',
                        'VALUE' => Option::get($moduleId, 'ERROR_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                        'REL'  => 'USE_ERROR_CONTENT',
                    ],
                ],
            ]
        ]
    ],
];

if (Loader::includeModule('welpodron.core')) {
    Helper::buildOptions($moduleId, $arTabs);
} else {
    echo 'Модуль welpodron.core не установлен';
}
