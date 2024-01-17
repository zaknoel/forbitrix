<?
namespace Zaknoel\ForBitrix;
class Record
{
    public static $instance;
    var $ib;
    var $entity;
    var $LAST_ERROR;
    function __construct($hblock)
    {
        CModule::IncludeModule("highloadblock");
        $this->ib=$hblock;
        if (is_numeric($hblock)) {

            $rsData = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter' => array('ID' => $hblock)));

        }else{
            $rsData = \Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter' => array('TABLE_NAME' => $hblock)));
        }
        if ( !($arData = $rsData->fetch()) ){
            throw new Error('Инфоблок не найден');
        }
        $this->entity =\Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arData);
        return $this;

    }
    public static function init($hblock): Record
    {
        if(self::$instance[$hblock] instanceof self){
            return self::$instance[$hblock];
        }
        return self::$instance[$hblock]=new Record($hblock);
    }
    function Add($arFields=[])
    {
        $DataClass = $this->entity->getDataClass();
        $result = $DataClass::add($arFields);
        if(!$result->isSuccess()){
            $this->LAST_ERROR=implode(', ', $result->getErrorMessages()); //выведем ошибки
            return false;
        }
        return $result->getId();//Id нового элемента
    }
    function Update($id, $arFields=[], $err=false): bool
    {
        if(!$id){
            throw new Error("Update primary key value is empty" .print_r($arFields, 1).":".$id);
        }
        $DataClass = $this->entity->getDataClass();
        $result = $DataClass::update($id, $arFields);
        if(!$result->isSuccess()){ //произошла ошибка

            $this->LAST_ERROR= implode(', ', $result->getErrorMessages()); //выведем ошибки
            return false;
        }
        return true;
    }
    function Delete($id): bool
    {
        $DataClass = $this->entity->getDataClass();
        $result = $DataClass::delete($id);
        if(!$result->isSuccess()){ //произошла ошибка
            $this->LAST_ERROR= implode(', ', $result->getErrorMessages()); //выведем ошибки
            return false;
        }
        return true;
    }
    function GetList($arOrder=[], $arFilter=[], $arSelect=["*"], $doArray=false, $limit=false, $page=1, $onlyResult=false)
    {
        $arFields=array(

        );
        if($arFilter){
            $arFields['filter']=$arFilter;
        }
        if($arOrder){
            $arFields['order']=$arOrder;
        }
        if($arOrder['RAND']){
            $arFields['runtime']= array('RAND'=>array('data_type' => 'float', 'expression' => array('RAND()')));
        }
        if($arSelect){
            $arFields['select']=$arSelect;
        }
        if($limit):
            $nav = new \Bitrix\Main\UI\PageNavigation("page");
            $nav->allowAllRecords(false)
                ->setPageSize($limit)
                ->setCurrentPage($page);
            $arFields['limit']=$nav->getLimit();
            $arFields['offset']=$nav->getOffset();
        endif;

        $result = $this->entity->getDataClass()::getList($arFields);
        $result = new CDBResult($result);
        if($onlyResult) return $result;
        $arLang = array();
        while ($row = $result->Fetch()){

            if(defined('HL_CONTRA') && $this->ib==HL_CONTRA){
                $row['UF_NAME']=quotclear($row['UF_NAME']);
            }
            $arLang[$row['ID']] = $row;

        }
        if(count($arLang)==1 && $doArray)
        {
            $arLang=array_values($arLang)[0];
        }
        return $arLang;

/*
        //Создадим объект - запрос
        $Query = new \Bitrix\Main\Entity\Query($this->entity);

        //Зададим параметры запроса, любой параметр можно опустить
        $Query->setSelect($arSelect);
        $Query->setFilter($arFilter);
        if($arOrder['RAND']){
            $Query->registerRuntimeField('', array('RAND'=>array('data_type' => 'float', 'expression' => array('RAND()'))));
        }else{
            $Query->setOrder($arOrder);
        }
        if($limit):
            $nav = new \Bitrix\Main\UI\PageNavigation("page");
            $nav->allowAllRecords(false)
                ->setPageSize($limit)
                ->setCurrentPage($page);
        $Query->setLimit($nav->getLimit());
        $Query->setOffset($nav->getOffset());
        endif;
        //Выполним запрос
        $result = $Query->exec();

        //Получаем результат по привычной схеме
        $result = new CDBResult($result);
        if($onlyResult) return $result;
        $arLang = array();
        while ($row = $result->Fetch()){

            if($this->ib==HL_CONTRA){
                $row['UF_NAME']=quotclear($row['UF_NAME']);
            }
            $arLang[$row['ID']] = $row;

        }
        if(count($arLang)==1 && $doArray)
        {
            $arLang=array_values($arLang)[0];
        }
        return $arLang;*/
    }
    function GetBy($by, $val, $field)
    {
        if($GLOBALS["_hl"][$this->ib][$by][$val][$field]) return $GLOBALS["_hl"][$this->ib][$by][$val][$field];
        $arF=array($by=>$val);
        $arSelect=array("ID", $field);
        $res=array_values($this->GetList(array(), $arF, $arSelect));
        $GLOBALS["_hl"][$this->ib][$by][$val][$field]=$res[0][$field];
        return $GLOBALS["_hl"][$this->ib][$by][$val][$field];
    }
    function GetByID($id){
        if(!$id) return [];
        return $this->GetList([], ['ID'=>$id], ['*'], true, 1);
    }
}
?>