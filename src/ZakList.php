<?
namespace Zaknoel\ForBitrix;
use \Bitrix\Main\Grid\Options as GridOptions;
use \Bitrix\Main\UI\PageNavigation;
use \Bitrix\Tasks\Grid\Row\Content\UserField;

class ZakList{

var $hl, $id, $fields, $filterData, $list, $arHeader, $nav, $changerFilter, $hide_filter=false;
var $actions=[];
var $entity;
var $presets=[];
var $access_page;private int $total;
function __construct($hl, $access_page='')
{
    global $APPLICATION;
    $this->hl=$hl;
    $this->access_page=$access_page;
    $this->id=\CSite::InDir('/events/')?md5($APPLICATION->GetCurPage()).'hl_block_'.$this->hl:'hl_block_'.$this->hl;
    $this->entity=Record::init($hl);
    $this->postActions();

    $this->actions=[
        [
            'text'    => 'Просмотр',
            'default' => true,
            'onclick' => 'document.location.href="#cur_path#show/#ID#/"'
        ],
        [
            'text'    => 'Редактировать',
            'default' => false,
            'onclick' => 'document.location.href="#cur_path#edit/#ID#/"'
        ],
        [
            'text'    => 'Удалить',
            'default' => false,
            'onclick' => 'if(confirm("Точно?")){document.location.href="?op=delete&id=#ID#"}'
        ]
    ];


}
function postActions(){
    global $APPLICATION;
    if($_REQUEST['op']=='delete'){
            $this->entity->Delete($_REQUEST['id']);



        if(!$_REQUEST['bxajaxid'])
        LocalRedirect($APPLICATION->GetCurPageParam('', ['op', 'id']));
    }
}
function getFilterData(){
    $this->fields=getHlFields($this->hl);
    if($this->changerFilter['fields']){
        $f=[];
        foreach ($this->changerFilter['fields'] as $ff=>$title){
            if($ff=='ID') continue;
            if($title) $this->fields[$ff]['TITLE']=$title;
            $f[$ff]=$this->fields[$ff];
        }
        $this->fields=$f;
    }

    $filterFields = array_filter(array(
        $this->fields['UF_NAME']?array(
            "id" => "UF_NAME",
            "name" => 'Название',
            "filterable" => "?",
            "quickSearch" => "?",
            "default" => true
        ):[],
        array(
            "id" => "ID",
            "name" => "ID (начальный и конечный)",
            "type" => "number",
            "filterable" => ""
        ),

    ));
    $types=[
        "boolean"=>'list',
        "checkbox"=>'list',
        "date"=>'date',
        "datetime"=>'date',
        "iblock_section"=>'list',
        "hlblock"=>'list',
        "iblock_element"=>'list',
        "enumeration"=>'list',
        "string"=>'string',
        "integer"=>'number',
        "double"=>'number',

    ];
    foreach ($this->fields as $f){
        if($f['USER_TYPE_ID']=='file') continue;
        if($f['FIELD_NAME']=="UF_NAME") continue;
        switch ($f['SHOW_FILTER']){
            case "I":
                $fItem=
                    array(
                        "id" => $f["FIELD_NAME"],
                        "name" => $f['TITLE']?:$f['FIELD_NAME'],
                        "type" => $types[$f['USER_TYPE_ID']],
                        "filterable" => ""
                    );
                break;
            default:
                $fItem=
                    array(
                        "id" => $f["FIELD_NAME"],
                        "name" => $f['TITLE']?:$f['FIELD_NAME'],
                        "type" => $types[$f['USER_TYPE_ID']],
                        "filterable" => "?"
                    );
        }
        if($types[$f['USER_TYPE_ID']]=='list'){

            if(!in_array($f['USER_TYPE_ID'], ['boolean', 'checkbox'])){
                $fItem['params']['multiple']="Y";
            }
            $fItem['filterable']="";
            $fItem['items']=[];
            switch($f['USER_TYPE_ID']){
                case 'boolean':
                case 'checkbox':
                    $fItem['items']=[
                        1=>'Да',
                        0=>'Нет'
                    ];
                    break;
                case 'enumeration':
                    $a=\CUserFieldEnum::GetList([], ['USER_FIELD_ID'=>$f['ID']]);
                    while ($b=$a->GetNext()){
                        $fItem['items'][$b['ID']]=$b['VALUE'];
                    }
                    break;
                case 'iblock_section';
                    $ib=$f['SETTINGS']['IBLOCK_ID'];
                    if($ib){
                        \CModule::IncludeModule('iblock');
                        $_a=\CIBlockSection::GetList(['SORT'=>"ASC"], ['IBLOCK_ID'=>$ib, 'ACTIVE'=>"Y"], false, ['ID', 'NAME'], ['nTopCount'=>500]);
                        while($_b=$_a->GetNext()){
                            $fItem['items'][$_b['ID']]=$_b['NAME'];
                        }
                    }
                    break;
                case 'iblock_element';
                    $ib=$f['SETTINGS']['IBLOCK_ID'];
                    if($ib){
                        \CModule::IncludeModule('iblock');
                        $_a=\CIBlockElement::GetList(['SORT'=>"ASC"], ['IBLOCK_ID'=>$ib, 'ACTIVE'=>"Y"], false, ['nTopCount'=>500], ['ID', 'NAME']);
                        while($_b=$_a->GetNext()){
                            $fItem['items'][$_b['ID']]=$_b['NAME'];
                        }
                    }
                    break;
                case 'hlblock';
                    $hl=$f['SETTINGS']['HLBLOCK_ID'];
                    if($hl){
                        $filt=[];

                        foreach (Record::init($hl)->GetList(['ID'=>"ASC"], $filt, ['ID', 'UF_NAME'], false, 500) as $_b){
                            $fItem['items'][$_b['ID']]=$_b['UF_NAME'];
                        }

                    }
                    break;
            }
        }
        if($fItem){
            if($this->changerFilter['filter'][$f['FIELD_NAME']]){
                try {
                    $reflectionAction = new \ReflectionFunction($this->changerFilter['filter'][$f['FIELD_NAME']]);
                    $fItem=$reflectionAction->invoke($fItem);
                } catch (\ReflectionException $e) {

                }

            }
            $filterFields[]=$fItem;
        }

    }
    $this->filterData=$filterFields;
    return $this->filterData;
}
function prepare(){
    $this->getFilterData();
    ////// list
    $grid_options = new GridOptions($this->id);
    $sort = $grid_options->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);

    $nav_params = $grid_options->GetNavParams();

    $nav = new PageNavigation($this->id);
    $nav->allowAllRecords(true)
        ->setPageSize($nav_params['nPageSize'])
        ->initFromUri();
    if ($nav->allRecordsShown()) {
        $nav_params = false;
    } else {
        $nav_params['iNumPage'] = $nav->getCurrentPage();
    }
    $this->nav=$nav;

    $filterData = [];
    $this->AddFilter($this->filterData, $filterData);

    if($this->changerFilter['onFilter']){
        $filterData=array_merge($this->changerFilter['onFilter'], $filterData);
       /* try {
            $reflectionAction = new ReflectionFunction($this->changerFilter['onFilter']);
            $filterData=$reflectionAction->invoke($filterData);
        } catch (ReflectionException $e) {

        }*/
    }

    $types=[
        "boolean"=>'list',
        "date"=>'date',
        "datetime"=>'date',
        "iblock_section"=>'list',
        "hlblock"=>'list',
        "iblock_element"=>'list',
        "enumeration"=>'list',
        "string"=>'string',
        "integer"=>'number',
        "double"=>'number',

    ];
    $this->arHeader[] = array(
        "id" => "ID",
        "content" => "ID",
        "title" => "",
        "sort" => "ID",
        "align" => "left",
        'default'=>1,
        "column_sort" => 1,
    );
    $pageConfig['USE_NEW_CARD']=true;
    $_sort=100;
    foreach ($this->fields as $f=>$item){
        $showMorePhoto = false;
        $editable = true;
        $preventDefault = true;

        if ($item["USER_TYPE_ID"] === "F" && $item["MULTIPLE"] === "Y")
        {
            $editable = false;
            $preventDefault = false;
            $showMorePhoto = $pageConfig['USE_NEW_CARD'] && $item["USER_TYPE_ID"] === "F";
        }
        $this->arHeader[] = [
            "id" => $item['FIELD_NAME']?:$f,
            "content" => $item['TITLE']?:$item['FIELD_NAME'],
            "title" => $item['TITLE']?:$item['FIELD_NAME'],
            "align" => ($types[$item['USER_TYPE_ID']]=='number'? "center": "left"),
            "sort" => $item['FIELD_NAME'],
            "default" => true,
            "editable" => $editable,
            "prevent_default" => $preventDefault,
            "column_sort" => $_sort,
        ];
        $_sort++;
    }
    \Bitrix\Main\Type\Collection::sortByColumn($this->arHeader, ['column_sort' => SORT_ASC], '', PHP_INT_MAX);
    foreach ($this->arHeader as $k=>$h){
        if(!$h['name']) $this->arHeader[$k]['name']=$h['content'];
    }
    /**@var \CDBResult $res*/
    $GLOBALS['hlFilter']=$filterData;
    foreach ($sort['sort'] as $k=>$v){
        if(!array_key_exists($k, $this->fields) && $k!="ID"){
            unset($sort['sort'][$k]);
            $sort['sort']["ID"]="asc";
        }
    }
    $res=$this->entity->GetList(
            $sort['sort'],
            $filterData,
            ["*"],
            false,
            ($_REQUEST['export']=='Y'?false:$nav_params['nPageSize']),
            ($_REQUEST['export']=='Y'?false:$nav_params['iNumPage']),
            1);
    $this->total=count($this->entity->GetList( [], $filterData, ['ID']));
    $nav->setRecordCount( $this->total);
    $ids=[];
    while($row=$res->GetNext()) {
        $ids[]=$row['ID'];
        $list=[];
        $list['data']['ID']=$row['ID'];
        foreach ($this->fields as $k=>$field){
            $v=$row[$k];

        //foreach ($row as $k=>$v){
            /////change data
            $type=$this->fields[$k]['USER_TYPE_ID'];
            //$field=$this->fields[$k];
         /*   if(!$field) {

                continue;
            }*/
            switch($type){
                case "boolean":
                    $v=$v?'Да':'Нет';
                    break;
                case "file":
                case "video":
                    if($v){
                        $file=\CFile::GetByID($v)->GetNext();
                        if(strpos($file['CONTENT_TYPE'], 'image/')!==FALSE){
                            if($this->fields[$k]['FIELD_NAME']=='UF_PHOTO'){
                                $v='<img src="'.\CFile::GetPath($v).'" alt="user image" class="img-radius img-40 align-top m-r-15">';
                            }else{
                                $v='<img src="'.\CFile::GetPath($v).'" alt="user image" class="img-square img-100 align-top m-r-15">';
                            }

                        }else{
                            $v='<a href="'.\CFile::GetPath($v).'" download class="col-text-blue">'.$file['ORIGINAL_NAME'].'</a>';
                        }

                    }else{
                        if($this->fields[$k]['FIELD_NAME']=='UF_PHOTO'){
                            $v='<img src="/local/templates/main/assets/img/user-default.png" alt="user image" class="img-radius img-40 align-top m-r-15">';
                        }else{
                            $v= "-";
                        }
                    }
                    break;
                case "datetime":
                case "date":
                    $v=$v?$v->toString():'';
                    if($v){
                        if($type=='datetime'){
                            $v='<div><i class="fa fa-calendar"></i> '.ZakHelper::ShowDate($v, 'd M, Y').'</div><div><i class="fa fa-clock-o"></i> '.ZakHelper::ShowDate($v, 'H:i').'</div>';
                        }else{
                            $v='<div><i class="fa fa-calendar"></i> '.ZakHelper::ShowDate($v, 'd M, Y').'</div>';
                        }
                    }
                    break;
                case "enumeration":
                    if($v){
                        $a=\CUserFieldEnum::GetList([], ['USER_FIELD_ID'=>$field['ID'], 'ID'=>$v])->GetNext();
                        $v=$a['VALUE'];
                    }else{
                        $v= "-";
                    }
                    break;
                case "iblock_section":
                case "hlblock":
                case "iblock_element":
                    if($v){
                        $v= GetRelatedName($v, $field['USER_TYPE_ID'], $field['SETTINGS']['HLBLOCK_ID']?:$field['SETTINGS']["IBLOCK_ID"]);
                    }else{
                        $v="-";
                    }
                    break;
            }
            if($k === "UF_ACTIVE"){
                $v=$v === 'Да'?'<span class="label label-success">Да</span>':'<span class="label label-danger">Нет</span>';
            }
            if($k === 'UF_USER' && $v){
                $u=\CUser::GetByID($v)->GetNext();
                $v=$u['NAME'].' ('.$u['LAST_NAME'].')';
            }
            if($this->changerFilter['change'][$k]){
                try {
                    $reflectionAction = new \ReflectionFunction($this->changerFilter['change'][$k]);
                    $v=$reflectionAction->invoke($row, $v);

                } catch (\ReflectionException $e) {
                }

            }

            $list['data'][$k]=is_array($v)?implode(", ", $v):$v;
        }
        if($this->changerFilter['action']){
            try {
                $reflectionAction = new \ReflectionFunction($this->changerFilter['action']);
                $list['actions']=$reflectionAction->invoke($row);
            } catch (\ReflectionException $e) {

            }

        }else{
            $act=$this->actions;
            foreach ($act as $k=>$v){
                $v['onclick']=$this->replacer($row, $v['onclick']);
                $list['actions'][]=$v;
            }
        }

        $lists[$row['ID']]=$list;
    }



    $this->list=$lists;
}
function replacer($row, $onc){
    $r=[
        "#cur_path#"=>"/".trim($GLOBALS['APPLICATION']->GetCurPage(), "/").'/',
    ];
    foreach ($row as $k=>$v){
        $r["#".$k."#"]=is_array($v)?implode(", ", $v):$v;
    }
    return str_replace(array_keys($r), array_values($r), $onc);

}
public function AddFilter(array $filterFields, array &$arFilter)
{
    $filterOption = new \Bitrix\Main\UI\Filter\Options($this->id);
    if($this->presets){
        $filterOption->setupDefaultFilter($this->presets['tmp_filter']['fields'], $filterOption->getUsedFields());
        global  $APPLICATION;
        LocalRedirect($APPLICATION->GetCurPage());
    }

    $filterData = $filterOption->getFilter($filterFields);

    $filterable = array();
    $quickSearchKey = "";
    foreach ($filterFields as $filterField)
    {
        if (isset($filterField["quickSearch"]))
        {
            $quickSearchKey = $filterField["quickSearch"].$filterField["id"];
        }
        $filterable[$filterField["id"]] = $filterField["filterable"];
    }

    foreach ($filterData as $fieldId => $fieldValue)
    {
        if ((is_array($fieldValue) && empty($fieldValue)) || (is_string($fieldValue) && $fieldValue == ''))
        {
            continue;
        }

        if (mb_substr($fieldId, -5) == "_from")
        {
            $realFieldId = mb_substr($fieldId, 0, mb_strlen($fieldId) - 5);
            if (!array_key_exists($realFieldId, $filterable))
            {
                continue;
            }
            if (mb_substr($realFieldId, -2) == "_1")
            {
                $arFilter[$realFieldId] = $fieldValue;
            }
            else
            {
                if (!empty($filterData[$realFieldId."_numsel"]) && $filterData[$realFieldId."_numsel"] == "more")
                    $filterPrefix = ">";
                else
                    $filterPrefix = ">=";
                $arFilter[$filterPrefix.$realFieldId] = trim($fieldValue);
            }
        }
        elseif (mb_substr($fieldId, -3) == "_to")
        {
            $realFieldId = mb_substr($fieldId, 0, mb_strlen($fieldId) - 3);
            if (!array_key_exists($realFieldId, $filterable))
            {
                continue;
            }
            if (mb_substr($realFieldId, -2) == "_1")
            {
                $realFieldId = mb_substr($realFieldId, 0, mb_strlen($realFieldId) - 2);
                $arFilter[$realFieldId."_2"] = $fieldValue;
            }
            else
            {
                if (!empty($filterData[$realFieldId."_numsel"]) && $filterData[$realFieldId."_numsel"] == "less")
                    $filterPrefix = "<";
                else
                    $filterPrefix = "<=";
                $arFilter[$filterPrefix.$realFieldId] = trim($fieldValue);
            }
        }
        else
        {
            if (array_key_exists($fieldId, $filterable))
            {
                $filterPrefix = $filterable[$fieldId];
                $arFilter[$filterPrefix.$fieldId] = $fieldValue;
            }
            if ($fieldId == "FIND" && trim($fieldValue) && $quickSearchKey)
            {
                $arFilter[$quickSearchKey] = $fieldValue;
            }
        }
    }
}
function show(){

$this->prepare();
global $APPLICATION;
/*
        ob_start();*/?><!--
            <div class="text-right">
                <a href="add/" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Добавить новый
                </a>
            </div>
        --><?/*
        $html=@ob_get_clean();
        $APPLICATION->AddViewContent('buttons', $html);*/

if(!$this->hide_filter):
?>

<div style="clear: both;"></div>
<div style="width: 100%; display: flex" class="grid_filter">
    <?

    $APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
        'FILTER_ID' => $this->id,
        'GRID_ID' => $this->id,
        'FILTER' => $this->filterData,
        'ENABLE_LIVE_SEARCH' => true,
        'ENABLE_LABEL' => true,
    ]);?>
</div>
<div style="clear: both;"></div>
<?
endif;
$messages=$_REQUEST['bxajaxid']?($_SESSION['error']?:$_SESSION['success']):false;
showErrors()?>
<?
if($_REQUEST['export']=='Y'){
    $APPLICATION->RestartBuffer();
    $name="Список";
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
    header("Content-Disposition: attachment; filename=".$name.".xls");
    ?>
    <table border="1" cellspacing="2" cellpadding="2">
        <thead>
        <tr>
            <?foreach ($this->arHeader as $h):?>
                <th><?=$h['content']?></th>
            <?endforeach;?>
        </tr>
        </thead>
        <tbody>
        <?foreach ($this->list as $k=>$v):?>
            <tr>
                <?foreach ($this->arHeader as $h):?>
                    <td><?=strip_tags($v['data'][$h["id"]])?></td>
                <?endforeach;?>
            </tr>
        <?endforeach;?>
        </tbody>
    </table>


    <?

    die();
}
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => $this->id,
    'COLUMNS' => $this->arHeader,
    'ROWS' => $this->list,
    'SHOW_ROW_CHECKBOXES' => false,
    'NAV_OBJECT' => $this->nav,
    'AJAX_MODE' => 'Y',
    'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
    'PAGE_SIZES' =>  [
        ['NAME' => '20', 'VALUE' => '20'],
        ['NAME' => '50', 'VALUE' => '50'],
        ['NAME' => '100', 'VALUE' => '100']
    ],
    'TOTAL_ROWS_COUNT'=>$this->total,
    'AJAX_OPTION_JUMP'          => 'N',
    'SHOW_CHECK_ALL_CHECKBOXES' => false,
    'SHOW_ROW_ACTIONS_MENU'     => true,
    'SHOW_GRID_SETTINGS_MENU'   => true,
    'SHOW_NAVIGATION_PANEL'     => true,
    'SHOW_PAGINATION'           => true,
    'SHOW_SELECTED_COUNTER'     => true,
    'SHOW_TOTAL_COUNTER'        => true,
    'SHOW_PAGESIZE'             => true,
    'SHOW_ACTION_PANEL'         => true,
    'ALLOW_COLUMNS_SORT'        => true,
    'ALLOW_COLUMNS_RESIZE'      => true,
    'ALLOW_HORIZONTAL_SCROLL'   => true,
    'ALLOW_SORT'                => true,
    'ALLOW_PIN_HEADER'          => true,
    'AJAX_OPTION_HISTORY'       => true,
    'L_MESS'=>$messages,
    'MESSAGES'=>$messages?[$messages]:false,
]);
}


}

?>
