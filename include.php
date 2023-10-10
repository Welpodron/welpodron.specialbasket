<?

use Bitrix\Main\Loader;

CJSCore::RegisterExt('welpodron.specialbasket', [
    'js' => '/bitrix/js/welpodron.specialbasket/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.core.templater'],
]);

CJSCore::RegisterExt('welpodron.forms.specialbasket.add', [
    'js' => '/bitrix/js/welpodron.specialbasket/forms/add/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.specialbasket', 'welpodron.core.templater'],
]);

CJSCore::RegisterExt('welpodron.forms.specialbasket.calculator', [
    'js' => '/bitrix/js/welpodron.specialbasket/forms/calculator/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.specialbasket', 'welpodron.core.templater'],
]);

// //! ОБЯЗАТЕЛЬНО

Loader::registerAutoLoadClasses(
    'welpodron.specialbasket',
    [
        'Welpodron\SpecialBasket\Utils' => 'lib/utils/utils.php',
    ]
);
