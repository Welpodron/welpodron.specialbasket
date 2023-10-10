<?

namespace Welpodron\SpecialBasket\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Error;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\Cookie;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Config\Option;
use Bitrix\Catalog\Product\Basket as _Basket;
use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Welpodron\SpecialBasket\Utils;

class Receiver extends Controller
{
    const DEFAULT_MODULE_ID = 'welpodron.specialbasket';
    const DEFAULT_COOKIE_CODE = "BASKET";
    const DEFAULT_ERROR_CONTENT = "При обработке Вашего запроса произошла ошибка, повторите попытку позже или свяжитесь с администрацией сайта";

    public function configureActions()
    {
        return [
            'add' => [
                'prefilters' => [],
            ],
            'set' => [
                'prefilters' => [],
            ]
        ];
    }

    protected function addCookie($code = self::DEFAULT_COOKIE_CODE, $value)
    {
        $cookie = new Cookie($code, Json::encode($value), time() + 60 * 60 * 24 * 7);

        $response = Context::getCurrent()->getResponse();
        $response->addCookie($cookie);
    }

    public function addAction()
    {
        global $APPLICATION;

        try {
            if (!Loader::includeModule(self::DEFAULT_MODULE_ID)) {
                throw new \Exception('Модуль ' . self::DEFAULT_MODULE_ID . ' не удалось подключить');
            }

            if (!Loader::includeModule("catalog")) {
                throw new \Exception('Модуль catalog не удалось подключить');
            }

            if (!Loader::includeModule("sale")) {
                throw new \Exception('Модуль sale не удалось подключить');
            }

            if (!_Basket::isNotCrawler()) {
                throw new \Exception('Поисковые роботы не могут добавлять товары в корзину');
            }

            $request = $this->getRequest();
            $arDataRaw = $request->getPostList()->toArray();

            if ($arDataRaw['sessid'] !== bitrix_sessid()) {
                throw new \Exception('Неверный идентификатор сессии');
            }

            $arRequestProducts = $arDataRaw['products'];

            if (!$arRequestProducts || !is_array($arRequestProducts)) {
                throw new \Exception('Неверный формат данных');
            }

            $arAddedProducts = [];

            //! Данные для добавления в корзину представлены в виде: [p_id_1 => quantity_1, p_id_2 => quantity_2, ...] 
            foreach ($arRequestProducts as $productId => $productQuantity) {
                if (intval($productId) > 0 && intval($productQuantity) > 0) {
                    //! TODO: Добавить поддержку ограничения количества сверху 
                    $arAddedProducts[$productId] = $productQuantity;
                }
            }

            $arCurrentProducts = Utils::getBasketItems(self::DEFAULT_COOKIE_CODE);

            //! Удалить все товары у которых количество равно 0
            $arCurrentProducts = array_filter($arCurrentProducts, function ($quantity) {
                return intval($quantity) > 0;
            });

            foreach ($arAddedProducts as $productId => $productQuantity) {
                if (array_key_exists($productId, $arCurrentProducts)) {
                    $arCurrentProducts[$productId] += $productQuantity;
                } else {
                    $arCurrentProducts[$productId] = $productQuantity;
                }
            }

            $arResult = [
                'CURRENT_PRODUCTS' => $arCurrentProducts,
                'ADDED_PRODUCTS' => $arAddedProducts,
            ];

            $useSuccessContent = Option::get(self::DEFAULT_MODULE_ID, 'USE_SUCCESS_CONTENT');

            if ($useSuccessContent == 'Y') {
                $templateIncludeResult =  Option::get(self::DEFAULT_MODULE_ID, 'SUCCESS_CONTENT_DEFAULT');

                $successFile = Option::get(self::DEFAULT_MODULE_ID, 'SUCCESS_FILE');

                if ($successFile) {
                    ob_start();
                    $APPLICATION->IncludeFile($successFile, [
                        'arMutation' => [
                            'PATH' => $successFile,
                            'PARAMS' => $arResult,
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                }

                $arResult['HTML'] = $templateIncludeResult;
            }

            $this->addCookie(self::DEFAULT_COOKIE_CODE, $arCurrentProducts);

            return $arResult;
        } catch (\Throwable $th) {
            if (CurrentUser::get()->isAdmin()) {
                $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTraceAsString()));
                return false;
            }

            try {
                $useErrorContent = Option::get(self::DEFAULT_MODULE_ID, 'USE_ERROR_CONTENT');

                if ($useErrorContent == 'Y') {
                    $errorFile = Option::get(self::DEFAULT_MODULE_ID, 'ERROR_FILE');

                    if (!$errorFile) {
                        $this->addError(new Error(Option::get(self::DEFAULT_MODULE_ID, 'ERROR_CONTENT_DEFAULT')));
                        return;
                    }

                    ob_start();
                    $APPLICATION->IncludeFile($errorFile, [
                        'arMutation' => [
                            'PATH' => $errorFile,
                            'PARAMS' => [],
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                    $this->addError(new Error($templateIncludeResult));
                    return;
                }

                $this->addError(new Error(self::DEFAULT_ERROR_CONTENT));
                return;
            } catch (\Throwable $th) {
                if (CurrentUser::get()->isAdmin()) {
                    $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTraceAsString()));
                    return;
                } else {
                    $this->addError(new Error(self::DEFAULT_ERROR_CONTENT));
                    return;
                }
            }
        }
    }

    public function setAction()
    {
        global $APPLICATION;

        try {
            if (!Loader::includeModule(self::DEFAULT_MODULE_ID)) {
                throw new \Exception('Модуль ' . self::DEFAULT_MODULE_ID . ' не удалось подключить');
            }

            if (!Loader::includeModule("catalog")) {
                throw new \Exception('Модуль catalog не удалось подключить');
            }

            if (!Loader::includeModule("sale")) {
                throw new \Exception('Модуль sale не удалось подключить');
            }

            if (!_Basket::isNotCrawler()) {
                throw new \Exception('Поисковые роботы не могут добавлять товары в корзину');
            }

            $request = $this->getRequest();
            $arDataRaw = $request->getPostList()->toArray();

            $arRequestProducts = $arDataRaw['products'];

            if (!$arRequestProducts || !is_array($arRequestProducts)) {
                $arRequestProducts = [];
            }

            $arAddedProducts = [];

            //! Данные для добавления в корзину представлены в виде: [p_id_1 => quantity_1, p_id_2 => quantity_2, ...] 
            foreach ($arRequestProducts as $productId => $productQuantity) {
                if (intval($productId) > 0 && intval($productQuantity) > 0) {
                    //! TODO: Добавить поддержку ограничения количества сверху 
                    $arAddedProducts[$productId] = $productQuantity;
                }
            }

            $arResult = [
                'CURRENT_PRODUCTS' => $arAddedProducts,
                'ADDED_PRODUCTS' => $arAddedProducts,
            ];

            $this->addCookie(self::DEFAULT_COOKIE_CODE, $arAddedProducts);

            if ($arDataRaw['start'] && $arDataRaw['end']) {

                try {
                    $currentDate = new DateTime(null, 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
                    $currentDate->setTime(0, 0);

                    $startDate = new DateTime($arDataRaw['start'], 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
                    $startDate->setTime(0, 0);

                    if ($startDate < $currentDate) {
                        // throw new \Exception('Начало временного периода не может быть меньше текущей даты');
                        return $arResult;
                    }

                    $endDate = new DateTime($arDataRaw['end'], 'Y-m-d', new \DateTimeZone('Europe/Moscow'));
                    $endDate->setTime(0, 0);

                    if ($endDate < $currentDate) {
                        // throw new \Exception('Конец временного периода не может быть меньше текущей даты');
                        return $arResult;
                    }

                    $totalDays = $startDate->getDiff($endDate)->days;

                    if ($totalDays === false) {
                        // throw new \Exception('Не удалось посчитать разницу в днях между начальной и конечной датой');
                        return $arResult;
                    }

                    //! TODO: Добавить в настройки модуля свойство для выбора цены начиная со 2 дня 

                    /*
            Цена за позицию в корзине считается по принципу:
    
            (БАЗОВАЯ_ЗА_1_ДЕНЬ + РАЗНИЦА_В_ДНЯХ * НАЧИНАЯ_СО_2_ДНЯ) * КОЛИЧЕСТВО_ТОВАРА_В_КОРЗИНЕ
    
            где: 
            
            БАЗОВАЯ_ЗА_1_ДЕНЬ - берется базовая цена из битрикса
            РАЗНИЦА_В_ДНЯХ - разница в днях между начальной и конечной датой
            НАЧИНАЯ_СО_2_ДНЯ - значение из настройки модуля свойство для выбора цены начиная со 2 дня 
            КОЛИЧЕСТВО_ТОВАРА_В_КОРЗИНЕ - текущее количество товара в корзине
            */

                    $totalPrice = 0;

                    $query = ProductTable::query();
                    $query->setSelect(['ID']);
                    $query->where('AVAILABLE', 'Y');
                    $query->whereIn('ID', array_keys($arAddedProducts));
                    $queryResult = $query->exec();

                    while ($arProduct = $queryResult->fetch()) {
                        $firstDayPrice = \CCatalogProduct::GetOptimalPrice($arProduct['ID']);

                        $quantity = $arAddedProducts[$arProduct['ID']];

                        if (!$firstDayPrice || !$quantity) {
                            continue;
                        }

                        $firstDayPrice = $firstDayPrice['RESULT_PRICE']['DISCOUNT_PRICE'];

                        $propsQuery = ElementPropertyTable::query();
                        $propsQuery->setSelect(['VALUE']);
                        $propsQuery->registerRuntimeField(new Reference(
                            'p_t',
                            PropertyTable::class,
                            Join::on('this.IBLOCK_PROPERTY_ID', 'ref.ID')
                        ));
                        $propsQuery->where('IBLOCK_ELEMENT_ID', $arProduct['ID']);
                        $propsQuery->where('p_t.CODE', 'SECOND_DAY_PRICE');

                        $secondDayPrice = $propsQuery->exec()->fetch();

                        if (!$secondDayPrice || !$secondDayPrice['VALUE']) {
                            $secondDayPrice = $firstDayPrice;
                        } else {
                            $secondDayPrice = $secondDayPrice['VALUE'];
                        }

                        $productPrice = ($firstDayPrice + $totalDays * $secondDayPrice) * $quantity;

                        $totalPrice += $productPrice;
                    }

                    $arResult['TOTAL_PRICE'] = $totalPrice;
                } catch (\Throwable $th) {
                }
            }

            return $arResult;
        } catch (\Throwable $th) {
            $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTraceAsString()));
            return;
        }
    }
}
