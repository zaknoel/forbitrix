<?
namespace Zaknoel\ForBitrix;
class ZakHelper
{
    public static function show404($message=''){
        \CModule::IncludeModule("iblock");
        define("ERROR_404", "Y");
        \Bitrix\Iblock\Component\Tools::process404(
             $message?:GetMessage("CATALOG_SECTION_NOT_FOUND")
            ,true
            ,true
            ,true
            ,"/404.php"
        );
        die();
    }
    public static function show403($message='', $defineConstant = true, $setStatus = true, $showPage = true, $pageFile = "/403.php"){
        \CModule::IncludeModule("iblock");
        define("ERROR_403", "Y");
        /** @global \CMain $APPLICATION */
        global $APPLICATION;

        if($message <> "")
        {
            $APPLICATION->includeComponent(
                "bitrix:system.show_message",
                ".default",
                array(
                    "MESSAGE"=> $message,
                    "STYLE" => "errortext",
                ),
                null,
                array(
                    "HIDE_ICONS" => "Y",
                )
            );
        }

        if ($defineConstant && !defined("ERROR_403"))
        {
            // define("ERROR_404", "Y");
        }

        if ($setStatus)
        {
            \CHTTP::setStatus("403 Forbidden");
        }

        if ($showPage)
        {
            if ($APPLICATION->RestartWorkarea())
            {
                if ($pageFile)
                    require(\Bitrix\Main\Application::getDocumentRoot().rel2abs("/", "/".$pageFile));
                else
                    require(\Bitrix\Main\Application::getDocumentRoot()."/403.php");
                die();
            }
        }
        die();
    }
    public static function SendErrorMessage($mess){
        if (defined('BOT_TOKEN') && !empty(BOT_TOKEN)
        && defined('BOT_CHAT_ID') && !empty(BOT_CHAT_ID)) {
            /**@var \TelegramBot\Api\BotApi $bbot;*/
            $bbot= new \TelegramBot\Api\Client(BOT_TOKEN, false, true);
            $text=$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI'].PHP_EOL;
            $text.=strip_tags($mess).print_r($GLOBALS['message_data'], 1)
                .print_r($GLOBALS['for_log'], 1).'. date'.print_r($GLOBALS['mess_delete_date'], 1);
            $bbot->sendMessage(BOT_CHAT_ID, $text, 'HTML');
        }
    }
    public static function ShowJsonResult($res)
    {
        $GLOBALS["APPLICATION"]->RestartBuffer();
        echo json_encode($res);
        die();
    }
    public static function ShowDate($date, $format)
    {
        if(!$date) return '';
        return \CIBlockFormatProperties::DateFormat($format, MakeTimeStamp($date, \CSite::GetDateFormat()));
    }
    public static function translit($str, $sym="-")
    {
        $params = Array(
            "max_len" => "100",
            "change_case" => "L",
            "replace_space" => $sym,
            "replace_other" => $sym,
            "delete_repeat_replace" => "true",
            "use_google" => "false",
        );
        return \CUtil::translit($str, "ru", $params);
    }
    public static function iResize($id, $width, $height, $type=0, $ifno=false)
    {
        //if(!$id && $ifno=="ava") return ZakHelper::GetNoPhotoAva($width, $height);
        if(!$id) return ZakHelper::GetNoPhoto($width, $height, $ifno);
        if($type==0)
            $img=\CFile::ResizeImageget($id, array('width'=>$width, "height"=>$height), BX_RESIZE_IMAGE_EXACT);
        elseif($type==1)
            $img=\CFile::ResizeImageget($id, array('width'=>$width, "height"=>$height), BX_RESIZE_IMAGE_PROPORTIONAL);
        else
            $img=\CFile::ResizeImageget($id, array('width'=>$width, "height"=>$height), BX_RESIZE_IMAGE_PROPORTIONAL_ALT);
        return $img["src"];
    }
    public static function GetNoPhoto($width, $height, $ifno = false)
    {

        if ($ifno == false){
            $n_file=$_SERVER["DOCUMENT_ROOT"]."/upload/no_photo/".$width."_".$height."/no_photo_last.png";
            $main = $_SERVER["DOCUMENT_ROOT"]."/upload/no_photo_last.png";
        }  elseif($ifno=="company"){
            $n_file=$_SERVER["DOCUMENT_ROOT"]."/upload/no_photo/".$width."_".$height."/no_photo_last.png";
            $main = $_SERVER["DOCUMENT_ROOT"]."/upload/no_photo_last.png";
        }elseif($ifno=="product"){
            $n_file=$_SERVER["DOCUMENT_ROOT"]."/upload/no_photo/".$width."_".$height."/no_photo_last.png";
            $main = $_SERVER["DOCUMENT_ROOT"]."/upload/no_photo_last.png";
        }
        if(!file_exists($n_file)){
            CheckDirPath($_SERVER["DOCUMENT_ROOT"]."/upload/no_photo/".$width."_".$height."/", true);
            $wmMini = CFile::ResizeImageFile(
                $sourceFile = $main,
                $destinationFile =  $n_file,
                $arSize = array('width'=>$width, 'height'=>$height),
                $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                $arWaterMark = array(),
                $jpgQuality=false,
                $arFilters =false
            );
        }
        return str_replace($_SERVER["DOCUMENT_ROOT"], "", $n_file);
    }
    public static function GetNoPhotoAva($width, $height)
    {
        $n_file=$_SERVER["DOCUMENT_ROOT"]."/upload/avatar/".$width."_".$height."/avatar.png";
        if(!file_exists($n_file)){
            CheckDirPath($_SERVER["DOCUMENT_ROOT"]."/upload/avatar/".$width."_".$height."/", true);
            $wmMini = \CFile::ResizeImageFile(
                $sourceFile = $_SERVER["DOCUMENT_ROOT"]."/img/avatar.jpg",
                $destinationFile =  $n_file,
                $arSize = array('width'=>$width, 'height'=>$height),
                $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
                $arWaterMark = array(),
                $jpgQuality=false,
                $arFilters =false
            );
        }
        return str_replace($_SERVER["DOCUMENT_ROOT"], "", $n_file);
    }
    public static function GetEnding($n, $forms=array("товар", "товара", "товаров")) {
        return $n%10==1&&$n%100!=11?$forms[0]:($n%10>=2&&$n%10<=4&&($n%100<10||$n%100>=20)?$forms[1]:$forms[2]);
    }
    public static function Put($a, $b)
    {
        return ($a)?($a):($b);
    }
    public static function isLast($array, $key)
    {
        end($array);
        $k = key($array);
        if($key==$k) return true;
        return false;
    }
    public function GenerateSqlWhere($array, $name)
    {
        $html="";
        foreach($array as $i=>$k){
            if($i) $html.=" OR ";

            $html.=$name."='".$k."'";
        }
        return $html;
    }
    public static function GetElement($id, $field, $ib=false)
    {
        if(!$id) return "";
        if($GLOBALS["ib"][$id][$field]) return $GLOBALS["ib"][$id][$field];
        \CModule::IncludeModule("iblock");
        $arF=array("ID"=>$id);
        if($ib) $arF['IBLOCK_ID']=$ib;
        $a=\CIBlockElement::GetList(array(), $arF, false, false, array("ID", toUpper($field)))->GetNext();
        $f=(strpos(toUpper($field), "PROPERTY_")!==FALSE)?(toUpper($field)."_VALUE"):($field);
        $GLOBALS["ib"][$id][$field]=$a[toUpper($f)];
        return $a[toUpper($f)];
    }
    public static function GetSection($id, $field)
    {
        if(!$id) return "";
        \CModule::IncludeModule("iblock");
        $a=\CIBlockSection::GetList(array(), array("ID"=>$id), false, array("ID", toUpper($field)))->GetNext();
        return $a[toUpper($field)];
    }
    public static function GetElements($arOrder=[], $arFilter=[], $arGroup=false, $arNav=false, $arSelect=[])
    {
        \CModule::IncludeModule("iblock");
        $a=\CIBlockElement::GetList($arOrder, $arFilter, $arGroup, $arNav, $arSelect);
        $res=array();
        while($b=$a->GetNext())
        {
            $res[$b["ID"]]=$b;
        }
        return $res;
    }
    public static function GetSections($arOrder=[], $arFilter=[], $arGroup=false,  $arSelect=[], $arNav=false)
    {
        \CModule::IncludeModule("iblock");
        $a=\CIBlockSection::GetList($arOrder, $arFilter, $arGroup,  $arSelect, $arNav);
        $res=array();
        while($b=$a->GetNext())
        {
            $res[$b["ID"]]=$b;
        }
        return $res;
    }
    public static function ShowRangeDate($from_date, $to_date)
    {
        if(!$to_date) $to_date=$from_date;
        $f_date=date_parse($from_date);
        $e_date=date_parse($to_date);
        if($f_date["month"]==$e_date["month"])
        {
            if($f_date["day"]==$e_date["day"])
            {
                return ZakHelper::ShowDate($from_date, "d F");
            }else{
                return $f_date["day"]." - ".$e_date["day"]." ".ZakHelper::ShowDate($from_date, "F");
            }
        }else{

            return ZakHelper::ShowDate($from_date, "d F")." - ". ZakHelper::ShowDate($to_date, "d F");
        }

    }
    public static function GetStatus($date, $end_date=false, $finish_date=false)
    {
        $status=[
            "<div class='e_status f1'><i style='color: #F1C70F' class='fa fa-edit'></i>&nbsp;Идет регистрация</div>",
            "<div class='e_status f2'><i style='color:#6ad404' class='fa fa-check'></i>&nbsp;Начался</div>",
            "<div class='e_status f3'><i style='color:#F1C70F' class='fa fa-edit'></i>&nbsp;Проверка работ</div>",
            "<div class='e_status f4'><i style='color:#ff4136;' class='fa fa-star'></i>&nbsp;Итоги подведены</div>"
        ];
        if(!$end_date)
        {
            if(strtotime($date)>strtotime(date("d.m.Y")))
            {
                return $status[0];
            }else{
                return $status[1];
            }
        }else{
            $today=strtotime(date("d.m.Y"));
            if(strtotime($date)>$today)
            {
                return $status[0];
            }elseif(strtotime($end_date)>=$today){
                return $status[1];
            }elseif(strtotime($finish_date)>$today)
            {
                return $status[2];
            }else{
                return $status[3];
            }

        }

    }
    public static function ParseCatalogUrl()
    {
        global $filt;
        $url=$GLOBALS["APPLICATION"]->GetCurPage(false);
        $u=array_values(array_filter(explode("filter", $url)));
        $s=array_values(array_filter(explode("/", $u[0])));
        if($s[1]){$filt["section"]=$s[1];}
        $f=array_filter(explode("/", $u[1]));
        foreach($f as $k=>$v)
        {
            $b=array_values(array_filter(explode("-", $v)));
            $bdv=$b; unset($bdv[0]);
            $filt[$b[0]]=implode("-", $bdv);
        }
        return $filt;
    }
    public static function GetEventClass($arResult)
    {
        $c=array_unique($arResult["PROPERTIES"]["CLASS"]["VALUE"]);
        if($c)
        {
            if(count($c)==1) return ZakHelper::GetElement($c[0], "NAME");
            $tr=true;
            $last=false;
            $k=array();
            foreach($c as $cl)
            {
                $num=ZakHelper::GetElement($cl, "CODE");
                if(!intval($num)) continue;
                $k[]=$num;
            }
            sort($k);
            foreach($k as $num)
            {
                if(!$last)
                    $last=$num;
                elseif($last==($num-1))
                {
                    $last=$num;
                    ///tr
                }else{
                    $tr=false;

                }
            }
           // echo "<pre>"; print_r($k);echo "</pre>";
            if($tr)
            {
                sort($k);
                return $k[0]."-".$k[(count($k)-1)]." классы";
            }else{
                sort($k);
                $bdv=implode(",", $k);
                return $bdv." классы";
            }

        }
    }
    public static function GetIcon($val)
    {
        $icon=$GLOBALS["icon"]=(is_object($GLOBALS["icon"]))?($GLOBALS["icon"]):(new Record(1));
        return \CFile::GetPath($icon->GetBy("UF_NAME", $val, "UF_ICON_SVG"));
    }
    public static function AddLog($text)
    {
        global $APPLICATION;
        $APPLICATION->ThrowException($text);
        ZakHelper::AddMessage($text, 404);
        $file=$_SERVER["DOCUMENT_ROOT"]."/site_log.txt";
        $html="###########################\n";
        $html.=date("d.m.Y H:i:s")."\n";
        $html.="Text: ".$text;
        $html.="\n\n";
        file_put_contents($file, $html, FILE_APPEND | LOCK_EX);
    }
    public static function GetRequest2Log()
    {
        $file=$_SERVER["DOCUMENT_ROOT"]."/site_log_req.txt";
        $html="###########################\n";
        $html.=date("d.m.Y H:i:s")."--".$GLOBALS["APPLICATION"]->GetCurPage() ."\n";
        $html.="POST: ".print_r($_POST, 1);
        $html.="\nGET: ".print_r($_GET, 1);
        $html.="\n\n";
        file_put_contents($file, $html, FILE_APPEND | LOCK_EX);
    }
    public static function GetCityName($id)
    {
        if($_SESSION["cities"][$id]) return $_SESSION["cities"][$id];
        global $DB;
        $sql="select NAME from b_sale_loc_name where LOCATION_ID='".$id."' AND LANGUAGE_ID='ru'";
        $r=$DB->Query($sql)->GetNext();
        if($r["NAME"])
        {
            $_SESSION["cities"][$id]=$r["NAME"];
            return $_SESSION["cities"][$id];
        }
    }
    public static function SetOption($var, $val="")
    {
        \COption::SetOptionString('main', $var, $val);
    }
    public static function GetOption($var)
    {
        return \COption::GetOptionString('main', $var, "N");
    }
    public static function ChangeArraySort($orginal, $sort)
    {
        $r=[];
        foreach ($sort as $s)
        {
            $res[$s]=$orginal[$s];
        }
        foreach ($orginal as $k=>$o)
        {
            if($res[$k]) continue;
            $res[$k]=$o;
        }
        return $res;
    }
    public static function InitParamArrays($arOrder, $orderID = 0, $psParams = "", $relatedData = array(), $payment = array())
    {
        if(!is_array($relatedData))
            $relatedData = array();

        $GLOBALS["SALE_INPUT_PARAMS"] = array();
        $GLOBALS["SALE_CORRESPONDENCE"] = array();

        if (!is_array($arOrder) || count($arOrder) <= 0 || !array_key_exists("ID", $arOrder))
        {
            $arOrder = array();

            $orderID = IntVal($orderID);
            if ($orderID > 0)
                $arOrderTmp = \CSaleOrder::GetByID($orderID);
            if (!empty($arOrderTmp))
            {
                foreach($arOrderTmp as $k => $v)
                {
                    $arOrder["~".$k] = $v;
                    $arOrder[$k] = htmlspecialcharsbx($v);
                }
            }
        }
        else if ($orderID == 0 && $arOrder['ID'] > 0)
        {
            $orderID = $arOrder['ID'];
        }

        if (empty($payment) && $orderID > 0)
        {
            $payment = \Bitrix\Sale\Internals\PaymentTable::getRow(
                array(
                    'select' => array('*'),
                    'filter' => array('ORDER_ID' => $orderID, '!PAY_SYSTEM_ID' => \Bitrix\Sale\PaySystem\Manager::getInnerPaySystemId())
                )
            );

        }

        if (count($arOrder) > 0)
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"] = $arOrder;

        if (!empty($payment))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["PAYMENT_ID"] = $payment['ID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["~PAYMENT_ID"] = $payment['ID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["SHOULD_PAY"] = $payment['SUM'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["~SHOULD_PAY"] = $payment['SUM'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["PAYED"] = $payment['PAID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["~PAYED"] = $payment['PAID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["PAY_SYSTEM_ID"] = $payment['PAY_SYSTEM_ID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["~PAY_SYSTEM_ID"] = $payment['PAY_SYSTEM_ID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ORDER_PAYMENT_ID"] = $payment['ID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["~ORDER_PAYMENT_ID"] = $payment['ID'];

            $GLOBALS["SALE_INPUT_PARAMS"]["PAYMENT"] = $payment;
        }
        else
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["SHOULD_PAY"] = DoubleVal($GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["PRICE"]) - DoubleVal($GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["SUM_PAID"]);
        }

        $arDateInsert = explode(" ", $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["DATE_INSERT"]);
        if (is_array($arDateInsert) && count($arDateInsert) > 0)
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["DATE_INSERT_DATE"] = $arDateInsert[0];
        else
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["DATE_INSERT_DATE"] = $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["DATE_INSERT"];

        if (!empty($payment))
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["DATE_BILL_DATE"] = ConvertTimeStamp(MakeTimeStamp($payment["DATE_BILL"]), 'SHORT');

        $userID = IntVal($GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["USER_ID"]);
        if ($userID > 0)
        {
            $dbUser = \CUser::GetByID($userID);
            if ($arUser = $dbUser->GetNext())
                $GLOBALS["SALE_INPUT_PARAMS"]["USER"] = $arUser;
        }

        $arCurOrderProps = array();
        if (isset($relatedData["PROPERTIES"]) && is_array($relatedData["PROPERTIES"]))
        {
            $properties = $relatedData["PROPERTIES"];
            foreach ($properties as $key => $value)
            {
                $arCurOrderProps["~".$key] = $value;
                $arCurOrderProps[$key] = htmlspecialcharsEx($value);
            }
        }
        else
        {
            $dbOrderPropVals = \CSaleOrderPropsValue::GetList(
                array(),
                array("ORDER_ID" => $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["ID"]),
                false,
                false,
                array("ID", "CODE", "VALUE", "ORDER_PROPS_ID", "PROP_TYPE")
            );

            while ($arOrderPropVals = $dbOrderPropVals->Fetch())
            {
                $arCurOrderPropsTmp = \CSaleOrderProps::GetRealValue(
                    $arOrderPropVals["ORDER_PROPS_ID"],
                    $arOrderPropVals["CODE"],
                    $arOrderPropVals["PROP_TYPE"],
                    $arOrderPropVals["VALUE"],
                    LANGUAGE_ID
                );

                foreach ($arCurOrderPropsTmp as $key => $value)
                {
                    $arCurOrderProps["~".$key] = $value;
                    $arCurOrderProps[$key] = htmlspecialcharsEx($value);
                }
            }
        }

        if (count($arCurOrderProps) > 0)
            $GLOBALS["SALE_INPUT_PARAMS"]["PROPERTY"] = $arCurOrderProps;

        $shipment = \Bitrix\Sale\Internals\ShipmentTable::getRow(
            array(
                'select' => array('DELIVERY_ID'),
                'filter' => array('ORDER_ID' => $orderID, 'SYSTEM' => 'N')
            )
        );

        if ($shipment)
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["DELIVERY_ID"] = $shipment['DELIVERY_ID'];
            $GLOBALS["SALE_INPUT_PARAMS"]["ORDER"]["~DELIVERY_ID"] = $shipment['DELIVERY_ID'];
        }

        $paySystemId = '';
        if ($payment && $payment['PAY_SYSTEM_ID'] > 0)
        {
            $paySystemId = $payment['PAY_SYSTEM_ID'];
        }
        elseif (isset($arOrder['PAY_SYSTEM_ID']) && $arOrder['PAY_SYSTEM_ID'] > 0)
        {
            $paySystemId = $arOrder['PAY_SYSTEM_ID'];
        }
        else
        {
            $psParams = unserialize($psParams);
            if (isset($psParams['BX_PAY_SYSTEM_ID']))
                $paySystemId = $psParams['BX_PAY_SYSTEM_ID']['VALUE'];
        }
        $paySystemId=$GLOBALS["current_paysystem"];
        if ($paySystemId !== '')
        {
            if (!isset($arOrder['PERSON_TYPE_ID']) || $arOrder['PERSON_TYPE_ID'] <= 0)
            {
                // for crm quote compatibility
                $personTypes = \CSalePaySystem::getPaySystemPersonTypeIds($paySystemId);
                $personTypeId = array_shift($personTypes);
            }
            else
            {
                $personTypeId = $arOrder['PERSON_TYPE_ID'];
            }
    //echo "<pre>"; print_r('PAYSYSTEM_'.$paySystemId);echo "</pre>";
            $params = \CSalePaySystemAction::getParamsByConsumer('PAYSYSTEM_'.$paySystemId, $personTypeId);
            foreach ($params as $key => $value)
                $params[$key]['~VALUE'] = htmlspecialcharsbx($value['VALUE']);

            $GLOBALS["SALE_CORRESPONDENCE"] = $params;
        }

        if ($payment['COMPANY_ID'] > 0)
        {
            if (!array_key_exists('COMPANY', $GLOBALS["SALE_INPUT_PARAMS"]))
                $GLOBALS["SALE_INPUT_PARAMS"]["COMPANY"] = array();

            global $USER_FIELD_MANAGER;
            $userFieldsList = $USER_FIELD_MANAGER->GetUserFields(\Bitrix\Sale\Internals\CompanyTable::getUfId(), null, LANGUAGE_ID);
            foreach ($userFieldsList as $key => $userField)
            {
                $value = $USER_FIELD_MANAGER->GetUserFieldValue(\Bitrix\Sale\Internals\CompanyTable::getUfId(), $key, $payment['COMPANY_ID']);
                $GLOBALS["SALE_INPUT_PARAMS"]["COMPANY"][$key] = $value;
                $GLOBALS["SALE_INPUT_PARAMS"]["COMPANY"]["~".$key] = $value;
            }

            $companyFieldList = \Bitrix\Sale\Internals\CompanyTable::getRowById($payment['COMPANY_ID']);
            foreach ($companyFieldList as $key => $value)
            {
                $GLOBALS["SALE_INPUT_PARAMS"]["COMPANY"][$key] = $value;
                $GLOBALS["SALE_INPUT_PARAMS"]["COMPANY"]["~".$key] = $value;
            }
        }
        // fields with no interface

        $GLOBALS["SALE_CORRESPONDENCE"]['PAYER_STREET']["TYPE"] = 'PROPERTY';
        $GLOBALS["SALE_CORRESPONDENCE"]['PAYER_STREET']["VALUE"] = 'LOCATION_STREET';
        $GLOBALS["SALE_CORRESPONDENCE"]['PAYER_STREET']["~VALUE"] = 'LOCATION_STREET';

        $GLOBALS["SALE_CORRESPONDENCE"]['PAYER_VILLAGE']["TYPE"] = 'PROPERTY';
        $GLOBALS["SALE_CORRESPONDENCE"]['PAYER_VILLAGE']["VALUE"] = 'LOCATION_VILLAGE';
        $GLOBALS["SALE_CORRESPONDENCE"]['PAYER_VILLAGE']["~VALUE"] = 'LOCATION_VILLAGE';

        $GLOBALS["SALE_CORRESPONDENCE"]['ORDER_PAYMENT_ID']["TYPE"] = 'ORDER';
        $GLOBALS["SALE_CORRESPONDENCE"]['ORDER_PAYMENT_ID']["VALUE"] = 'PAYMENT_ID';
        $GLOBALS["SALE_CORRESPONDENCE"]['ORDER_PAYMENT_ID']["~VALUE"] = 'PAYMENT_ID';

        $GLOBALS["SALE_CORRESPONDENCE"]['PAYED']["TYPE"] = 'ORDER';
        $GLOBALS["SALE_CORRESPONDENCE"]['PAYED']["VALUE"] = 'PAYED';
        $GLOBALS["SALE_CORRESPONDENCE"]['PAYED']["~VALUE"] = 'PAYED';

        if (isset($relatedData["BASKET_ITEMS"]) && is_array($relatedData["BASKET_ITEMS"]))
            $GLOBALS["SALE_INPUT_PARAMS"]["BASKET_ITEMS"] = $relatedData["BASKET_ITEMS"];

        if (isset($relatedData["TAX_LIST"]) && is_array($relatedData["TAX_LIST"]))
            $GLOBALS["SALE_INPUT_PARAMS"]["TAX_LIST"] = $relatedData["TAX_LIST"];

        if(isset($relatedData["REQUISITE"]) && is_array($relatedData["REQUISITE"]))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["REQUISITE"] = $relatedData["REQUISITE"];

            self::$relatedData['REQUISITE'] = array(
                'GET_INSTANCE_VALUE' => function ($providerInstance, $providerValue, $personTypeId)
                {
                    return $GLOBALS['SALE_INPUT_PARAMS']['REQUISITE'][$providerValue];
                }
            );
        }
        if(isset($relatedData["BANK_DETAIL"]) && is_array($relatedData["BANK_DETAIL"]))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["BANK_DETAIL"] = $relatedData["BANK_DETAIL"];

            self::$relatedData['BANK_DETAIL'] = array(
                'GET_INSTANCE_VALUE' => function ($providerInstance, $providerValue, $personTypeId)
                {
                    return $GLOBALS['SALE_INPUT_PARAMS']['BANK_DETAIL'][$providerValue];
                }
            );
        }
        if(isset($relatedData["CRM_COMPANY"]) && is_array($relatedData["CRM_COMPANY"]))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["CRM_COMPANY"] = $relatedData["CRM_COMPANY"];

            self::$relatedData['CRM_COMPANY'] = array(
                'GET_INSTANCE_VALUE' => function ($providerInstance, $providerValue, $personTypeId)
                {
                    return $GLOBALS['SALE_INPUT_PARAMS']['CRM_COMPANY'][$providerValue];
                }
            );
        }
        if(isset($relatedData["CRM_CONTACT"]) && is_array($relatedData["CRM_CONTACT"]))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["CRM_CONTACT"] = $relatedData["CRM_CONTACT"];

            self::$relatedData['CRM_CONTACT'] = array(
                'GET_INSTANCE_VALUE' => function ($providerInstance, $providerValue, $personTypeId)
                {
                    return $GLOBALS['SALE_INPUT_PARAMS']['CRM_CONTACT'][$providerValue];
                }
            );
        }
        if(isset($relatedData["MC_REQUISITE"]) && is_array($relatedData["MC_REQUISITE"]))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["MC_REQUISITE"] = $relatedData["MC_REQUISITE"];

            self::$relatedData['MC_REQUISITE'] = array(
                'GET_INSTANCE_VALUE' => function ($providerInstance, $providerValue, $personTypeId)
                {
                    return $GLOBALS['SALE_INPUT_PARAMS']['MC_REQUISITE'][$providerValue];
                }
            );
        }
        if(isset($relatedData["MC_BANK_DETAIL"]) && is_array($relatedData["MC_BANK_DETAIL"]))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["MC_BANK_DETAIL"] = $relatedData["MC_BANK_DETAIL"];

            self::$relatedData['MC_BANK_DETAIL'] = array(
                'GET_INSTANCE_VALUE' => function ($providerInstance, $providerValue, $personTypeId)
                {
                    return $GLOBALS['SALE_INPUT_PARAMS']['MC_BANK_DETAIL'][$providerValue];
                }
            );
        }
        if(isset($relatedData["CRM_MYCOMPANY"]) && is_array($relatedData["CRM_MYCOMPANY"]))
        {
            $GLOBALS["SALE_INPUT_PARAMS"]["CRM_MYCOMPANY"] = $relatedData["CRM_MYCOMPANY"];

            self::$relatedData['CRM_MYCOMPANY'] = array(
                'GET_INSTANCE_VALUE' => function ($providerInstance, $providerValue, $personTypeId)
                {
                    return $GLOBALS['SALE_INPUT_PARAMS']['CRM_MYCOMPANY'][$providerValue];
                }
            );
        }

        if ($relatedData)
        {
            $eventManager = \Bitrix\Main\EventManager::getInstance();
            $eventManager->addEventHandler('sale', 'OnGetBusinessValueProviders', array('\CSalePaySystemAction', 'getProviders'));
        }
    }
    function AddMessage($m, $code=200)
    {
        if($code==200){
            $GLOBALS["zMess"][]=$m;
        }else{
            $GLOBALS["zError"][]=$m;
        }
    }
    function ShowExeption()
    {
        $html="";
        if($GLOBALS["zError"])
        {
            foreach ($GLOBALS["zError"] as $m)
            {
                $html.="<span class='zErr'>".$m."</span>";
            }
        }
        if($GLOBALS["zMess"])
        {
            foreach ($GLOBALS["zMess"] as $m)
            {
                $html.="<span class='zMess'>".$m."</span>";
            }
        }
        echo $html;
    }
    public static function GetGramota($event)
    {
        $allSheets=[];
        $allMax=0;
        $dbOffer=\CIBlockElement::GetList(
            array("PROPERTY_NUMBER"=>"asc"),
            array("IBLOCK_ID"=>30, "ACTIVE"=>"Y", "PROPERTY_CML2_LINK"=>$event),
            false,
            false,
            array("ID", "IBLOCK_ID", "IBLOCK_SECTION_ID", "PROPERTY_QUANTITY"));
        $stepCount=$dbOffer->SelectedRowsCount();
        $teachers=[];
        while($arOffer=$dbOffer->GetNext())
        {
            $allMax=$allMax+intval($arOffer["PROPERTY_QUANTITY_VALUE"]);
        }
        $sheets=ZakHelper::GetElements(
            array(),
            array("IBLOCK_ID"=>ANSWER_IB, "PROPERTY_EVENT"=>$event),
            false,
            false,
            array("ID", "IBLOCK_ID", "PROPERTY_CORRECT",  "PROPERTY_WRONG",  "PROPERTY_STUDENT"));
        foreach ($sheets as $sheet)
        {
            $allSheets[$sheet["PROPERTY_STUDENT_VALUE"]][]=$sheet;
        }
        //gramota
        $allG=[];
        foreach ($allSheets as $user=>$sheets)
        {

            $r=new Reward();
            $r->setStudent($user);
            $true=0;
            $false=0;
            $max=0;
            foreach ($sheets as $sheet)
            {
                $true=($true+intval($sheet["PROPERTY_CORRECT_VALUE"]));
                $false=($false+intval($sheet["PROPERTY_WRONG_VALUE"]));
            }
            $allG[]=[
                "STUDENT"=>$user,
                "CORRECT"=>$true,
                "WRONG"=>$false,
                "STEP_COUNT"=>$stepCount,
                "PART_COUNT"=>count($sheets),
                "MAX"=>$allMax,
                "REWARD"=>Record::init(9)->GetList([], ["UF_GRAMOTA"=>"Y", "UF_STUDENT"=>$user, "UF_EVENT"=>$event])
            ];
        }
        return $allG;
    }
    public static function GetElementByID($id, $fields=array())
    {
        \CModule::IncludeModule("iblock");
        return \CIBlockElement::GetList(array(), array("ID"=>$id), false, false, $fields)->GetNext();
    }
    public static function GetSectionByID($id, $fields=array(), $ib=false)
    {
        \CModule::IncludeModule("iblock");
        $f=array("ID"=>$id);
        if($ib) $f["IBLOCK_ID"]=$ib;
        return \CIBlockSection::GetList(array(), $f, false, $fields)->GetNext();
    }
}
?>