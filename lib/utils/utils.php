<?

namespace Welpodron\SpecialBasket;

use Bitrix\Main\Context;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Loader;
use Bitrix\Main\FileTable;
use Bitrix\Highloadblock\HighloadBlockTable;

class Utils
{
    const DEFAULT_COOKIE_CODE = "BASKET";

    static public function getBasketItems($cookieCode = self::DEFAULT_COOKIE_CODE)
    {
        $basket = Context::getCurrent()->getRequest()->getCookie($cookieCode);

        if (isset($basket) && !empty($basket)) {
            try {
                $basket = Json::decode($basket);

                if (is_array($basket)) {
                    return $basket;
                }
            } catch (\Throwable $th) {
            }
        }

        return [];
    }

    static public function getProductsInfo($arProductsRawIds = [])
    {
        $arElements = [];

        if (!Loader::includeModule('iblock')) {
            return $arElements;
        }

        if (!Loader::includeModule('catalog')) {
            return $arElements;
        }

        if (!Loader::includeModule('sale')) {
            return $arElements;
        }

        if (!$arProductsRawIds || !isset($arProductsRawIds) || empty($arProductsRawIds) || !is_array($arProductsRawIds)) {
            return $arElements;
        }

        $arProductsIds = [];

        foreach ($arProductsRawIds as $productId) {
            if (is_numeric($productId)) {
                $arProductsIds[] = $productId;
            }
        }

        if (!$arProductsIds || empty($arProductsIds)) {
            return $arElements;
        }

        $arFilter = [
            'SITE_ID' => Context::getCurrent()->getSite(),
            'CHECK_PERMISSIONS' => 'N',
            'ACTIVE' => 'Y',
            'ID' => $arProductsIds
        ];
        $arOrder = [];
        $arGroup = false;
        $arSelect = [
            'ID', 'NAME', 'IBLOCK_ID', 'DETAIL_PAGE_URL', 'AVAILABLE', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'TYPE'
        ];


        $dbElements = \CIBlockElement::GetList($arOrder, $arFilter, $arGroup, false, $arSelect);

        while ($dbElement = $dbElements->GetNextElement(false, false)) {
            $arFields = $dbElement->GetFields();

            $arSku = \CCatalogSku::GetProductInfo($arFields["ID"]);

            if (is_array($arSku)) {
                $dbParentItem = \CIBlockElement::GetList([], [
                    'SITE_ID' => Context::getCurrent()->getSite(), 'CHECK_PERMISSIONS' => 'N', 'IBLOCK_ID' => $arSku['IBLOCK_ID'], 'ID' => $arSku['ID']
                ], false, false, ['PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch();

                if ($dbParentItem) {
                    $arFields['PREVIEW_PICTURE'] = $dbParentItem['PREVIEW_PICTURE'];
                    $arFields['DETAIL_PICTURE'] = $dbParentItem['DETAIL_PICTURE'];
                }
            }

            if ($arFields['PREVIEW_PICTURE']) {
                $id = $arFields['PREVIEW_PICTURE'];

                $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                    '=ID' => $id
                ], 'limit' => 1])->fetch();
                $arFields['PREVIEW_PICTURE'] = $file;
                $arFields['PREVIEW_PICTURE']['SRC'] = \CFile::GetPath($id);
            }

            if ($arFields['DETAIL_PICTURE']) {
                $id = $arFields['DETAIL_PICTURE'];

                $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                    '=ID' => $id
                ], 'limit' => 1])->fetch();
                $arFields['DETAIL_PICTURE'] = $file;
                $arFields['DETAIL_PICTURE']['SRC'] = \CFile::GetPath($id);
            }

            $arProps = [];

            if (is_array($arSku)) {
                $arOffersProps = [];

                $dbOffersProps = \CIBlockProperty::GetList([], ["ACTIVE" => "Y", "IBLOCK_ID" => $arFields["IBLOCK_ID"]]);

                while ($arFetchRes = $dbOffersProps->Fetch()) {
                    $arOffersProps[] = $arFetchRes["ID"];
                };

                if (!empty($arOffersProps)) {
                    $dbProps = \CIBlockElement::GetProperty($arFields["IBLOCK_ID"], $arFields["ID"], [], [
                        "ID" => $arOffersProps
                    ]);

                    while ($dbProp = $dbProps->Fetch()) {
                        if ($dbProp['PROPERTY_TYPE'] == "E" && $dbProp['USER_TYPE'] == "SKU") {
                            continue;
                        }

                        if ($dbProp['USER_TYPE'] == 'directory') {
                            if (Loader::includeModule('highloadblock')) {
                                $entityRaw = HighloadBlockTable::getList([
                                    'filter' => [
                                        '=TABLE_NAME' => $dbProp['USER_TYPE_SETTINGS']['TABLE_NAME']
                                    ]
                                ])->fetch();

                                $entity = HighloadBlockTable::compileEntity($entityRaw);
                                $entityClass = $entity->getDataClass();

                                if ($entityClass) {
                                    $dbComplexProps = $entityClass::getList(array(
                                        'order' => array('UF_NAME' => 'ASC'),
                                        'select' => array('UF_NAME', 'UF_XML_ID', 'UF_FILE', 'ID'),
                                        'filter' => array('!UF_NAME' => false, 'UF_XML_ID' => $dbProp['VALUE'])
                                    ));

                                    $arProp = [
                                        'NAME' => $dbProp['NAME'],
                                        'CODE' => $dbProp['CODE'],
                                        'PROPERTY_TYPE' => $dbProp['PROPERTY_TYPE'],
                                        'USER_TYPE' => $dbProp['USER_TYPE'],
                                        'VALUE' => $dbProp['VALUE'],
                                        'VALUE_ENUM' => [],
                                    ];

                                    while ($dbComplexProp = $dbComplexProps->fetch()) {
                                        $arPropValue = [
                                            'NAME' => $dbComplexProp['UF_NAME'],
                                            'XML_ID' => $dbComplexProp['UF_XML_ID'],
                                            'FILE' => [
                                                'VALUE' => $dbComplexProp['UF_FILE']
                                            ],
                                            'ID' => $dbComplexProp['ID'],
                                        ];

                                        if ($arPropValue['FILE']['VALUE']) {
                                            $id = $arPropValue['FILE']['VALUE'];

                                            $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                                                '=ID' => $arPropValue['FILE']
                                            ], 'limit' => 1])->fetch();

                                            if ($file) {
                                                $arPropValue['FILE'] = $file;
                                                $arPropValue['FILE']['SRC'] = \CFile::GetPath($id);
                                            }
                                        }


                                        $arProp['VALUE_ENUM'][] = $arPropValue;
                                    }

                                    $arProps[] = $arProp;
                                }
                            }
                        } else {
                            if ($dbProp['PROPERTY_TYPE'] === 'L') {
                                $arProps[] = [
                                    'NAME' => $dbProp['NAME'],
                                    'CODE' => $dbProp['CODE'],
                                    'VALUE' => $dbProp['VALUE'],
                                    'VALUE_ENUM' => $dbProp['VALUE_ENUM'],
                                    'PROPERTY_TYPE' => $dbProp['PROPERTY_TYPE'],
                                    'USER_TYPE' => $dbProp['USER_TYPE'],
                                ];
                            } else {
                                $arProps[] = [
                                    'NAME' => $dbProp['NAME'],
                                    'CODE' => $dbProp['CODE'],
                                    'VALUE' => $dbProp['VALUE'],
                                    'VALUE_ENUM' => $dbProp['VALUE_ENUM'],
                                    'PROPERTY_TYPE' => $dbProp['PROPERTY_TYPE'],
                                    'USER_TYPE' => $dbProp['USER_TYPE'],
                                ];
                            }
                        }
                    }
                }
            }

            $arFields['PRICE'] = \CCatalogProduct::GetOptimalPrice($arFields["ID"])['RESULT_PRICE']['DISCOUNT_PRICE'];

            $arElement = ['FIELDS' => $arFields, 'PROPS' => $arProps];

            $arElements[] = $arElement;
        }

        return $arElements;
    }
}
