<?php
namespace Zaknoel\ForBitrix;
class ZakLoader
{
    private string $table = "zak_loader_profiles";
    private $profile = 'new';
    private int $step = 1;
    private array $fcache = [];
    private array $errors = [];
    private array $titles = [
        '1' => 'Выбор файла данных и highload-блока для импорта',
        '2' => 'Настройка параметров загрузки файла',
        '3' => 'Установка соответствия полей в файле данных',
        '4' => 'Импорт данных',
    ];
    private float $startTime;
    public int $limit=20;
    public array $cache=[];
    function __construct($profile = '', $step = 1)
    {

        $this->handleAjaxActions();
        \CModule::IncludeModule('highloadblock');
        if (!$profile) $profile = "";
        if (!$step) $step = 1;
        if (!$profile) $step = 1;
        if ($step > 4) $step = 1;
        $this->profile = $profile;
        $this->step = $step;
        $this->checkDb();

        $this->getStyles();
        $this->prepare();
    }

    function handleAjaxActions()
    {
        $action = $_REQUEST['action'];
        global $APPLICATION;
        switch ($action) {
            case "loadFields":
                $APPLICATION->RestartBuffer();
                foreach ($this->getHlFieldList($_REQUEST['hl']) as $k => $v):
                    $this->createCheckbox('settings[unique_fields][]', $v['TITLE'] ?: $v['FIELD_NAME'], $v['FIELD_NAME'], false);
                endforeach;
                die();
            case "save":
                $isNew = $_REQUEST['profile'] == "new";
                if ($isNew || $_FILES["FILE"]['name']) {
                    $file_id = \CFile::SaveFile($_FILES["FILE"], "zloader");
                    if (!$file_id) {
                        $this->errors[] = "Не удалось сохранить файл";
                    }
                }
                $settings = $_REQUEST['settings'];
                if (!$settings['highload']) $this->errors[] = "Не указана Highload-блок";
                if (!$settings['unique_fields']) $this->errors[] = "Не указана поля для идентификации элемента";
                if (!$this->errors) {
                    global $DB;
                    $arFields = [
                        "NAME" => $_REQUEST['NAME'],
                        "FILE" => $file_id,
                        "SETTINGS" => base64_encode(serialize($settings))
                    ];
                    if ($isNew) {
                        $sql = "INSERT INTO " . $this->table . " (ID, NAME, FILE, SETTINGS)
            VALUES ('', '" . $arFields['NAME'] . "', '" . $arFields['FILE'] . "', '" . $arFields['SETTINGS'] . "')";
                        if (!$DB->Query($sql)) {
                            $this->errors[] = "Не удалось сохранить профиль";
                        }
                        $profile['ID'] = $DB->LastID();
                    } else {
                        $profile = $this->getProfile($_REQUEST['profile']);
                        $settings = array_merge($profile['settings'], $settings);
                        $settings = base64_encode(serialize($settings));
                        $sql = "UPDATE " . $this->table . " SET SETTINGS='" . $settings . "'";
                        if ($file_id) {
                            $sql .= ", FILE=" . $file_id;
                        }
                        $sql .= " WHERE ID=" . $profile['ID'];
                        if (!$DB->Query($sql)) {
                            $this->errors[] = "Не удалось сохранить профиль";
                        }
                    }
                    if (!$this->errors) {
                        LocalRedirect($APPLICATION->GetCurPage() . "?step=2&profile=" . $profile['ID']);
                    }

                }


                break;

            case "sheet":

                $profile = $this->getProfile($_REQUEST['profile']);
                $profile['settings']["sheet"] = $_REQUEST['sheet'];
                $profile['settings']['header_col'] = $_REQUEST['header_col'];
                $settings = $profile['settings'];
                $settings = base64_encode(serialize($settings));
                $sql = "UPDATE " . $this->table . " SET SETTINGS='" . $settings . "'";
                $sql .= " WHERE ID=" . $profile['ID'];
                global $DB;
                if (!$DB->Query($sql)) {
                    $this->errors[] = "Не удалось сохранить профиль";
                }
                if (!$this->errors) {
                    LocalRedirect($APPLICATION->GetCurPage() . "?step=3&profile=" . $profile['ID']);
                }
                break;
            case "fields":
                $match = $_REQUEST['match'];
                $filter = $_REQUEST['filter'];
                $profile = $this->getProfile($_REQUEST['profile']);
                $fields = $this->getHlFieldList($profile['settings']['highload']);
                foreach ($profile['settings']['unique_fields'] as $k => $v) {
                    if (!in_array($v, $match)) {
                        $this->errors[] = 'Не указано поле для идентификации элемента (лист 1, поле ' . ($fields[$v]['TITLE'] ?: $v) . ')';
                    }
                }
                if (!$this->errors) {

                    $profile['settings']["match"] = $match;
                    $profile['settings']['filter'] = $filter;
                    $settings = $profile['settings'];
                    $settings = base64_encode(serialize($settings));
                    $sql = "UPDATE " . $this->table . " SET SETTINGS='" . $settings . "'";
                    $sql .= " WHERE ID=" . $profile['ID'];
                    global $DB;
                    if (!$DB->Query($sql)) {
                        $this->errors[] = "Не удалось сохранить профиль";
                    }
                    if (!$this->errors) {
                        LocalRedirect($APPLICATION->GetCurPage() . "?step=4&profile=" . $profile['ID']);
                    }
                }


                break;
            case "import":
                $APPLICATION->RestartBuffer();
                ////do work
                $pid=$_REQUEST['profile'];
                $profile=$_SESSION['import']["p".$pid]?:$_SESSION['import']["p".$pid]=$this->getProfile($pid);
                $fields=$_SESSION['import']["f".$pid]?:$_SESSION['import']["f".$pid]=$this->getHlFieldList($profile['settings']['highload']);
                $cfile = $_SERVER['DOCUMENT_ROOT'] . '/upload/loader_' . $profile["ID"] . '.txt';
                $this->startTime=microtime(true);
                $dd=unserialize(htmlspecialcharsback(file_get_contents($cfile)));
                if($dd):
                    $not_update=$profile['settings']['not_update'];
                    $not_add=$profile['settings']['not_add'];
                    $unique=$profile['settings']['unique_fields'];
                    $hl=$profile['settings']['highload'];
                    $delimetr=$profile['settings']['multi_delimeter'];
                    foreach ($dd as $line_number=>$item){
                        $r=microtime(true)-$this->startTime;
                        if($r>$this->limit){
                            break;
                        }
                        ////
                        $arFields=[];
                        foreach ($item as $field_code=>$value){
                            $field=$fields[$field_code];
                            if($field_code=="ID"){
                                $arFields["ID"]=$value;
                                continue;
                            }
                            $multy=$field['MULTIPLE']=="Y";
                            if($multy)
                                $value=explode($delimetr, $value);
                            else
                                $value=[$value];
                            foreach ($value as $v1){
                                switch($field['USER_TYPE_ID']){
                                    case "boolean":
                                        $arFields[$field_code][]=$v1?1:0;
                                        break;
                                    case "date":
                                        $arFields[$field_code][]=$v1?date('d.m.Y', strtotime($v1)):"";
                                        break;
                                    case "datetime":
                                        $arFields[$field_code][]=$v1?date('d.m.Y H:i:s', strtotime($v1)):"";
                                        break;
                                    case "enumeration":
                                        $arFields[$field_code][]=$this->getEnum($field, $v1);
                                        break;
                                    case "hlblock":
                                        $arFields[$field_code][]=$this->getHl($field, $v1);
                                        break;

                                    case "file":
                                        $r=false;
                                        if($v1){
                                            if(strpos($v1, "http")===0){
                                                $r=\CFile::MakeFileArray($v1);
                                            }elseif(strpos($v1, "/")===0){
                                                $r=\CFile::MakeFileArray($_SERVER['DOCUMENT_ROOT'].$v1);
                                            }
                                            if(!$r['size']) $r=false;
                                        }
                                        $arFields[$field_code][]=$r;
                                        break;
                                    case "integer":
                                        $arFields[$field_code][]=intval($v1);
                                        break;
                                    case "double":
                                        $arFields[$field_code][]=doubleval($v1);
                                        break;
                                    default:
                                        $arFields[$field_code][]=$v1;

                                }
                            }

                            if(!$multy){
                                $arFields[$field_code]=implode($delimetr, $arFields[$field_code]);
                            }

                        }
                        ///check unique
                        $err=false;
                        $filter=[];
                        foreach ($unique as $uf){
                            if(!$arFields[$uf]){
                                $err=true;
                                $_SESSION['import']['err'][]="Строка ".$line_number.": Поля ".($fields[$uf]['TITLE']?:$uf)." не заполнена!";
                            }else{
                                $filter[$uf]=$arFields[$uf];
                            }
                        }
                        if($err){
                            $_SESSION['import']['error']++;
                            $_SESSION['import']['done']++;
                        }else{
                            $oldOne=Record::init($hl)->GetList([], $filter, ['ID']);
                            if($oldOne){
                                if( !$not_update){
                                    foreach ($oldOne as $old){
                                        if($updated=Record::init($hl)->Update($old['ID'], $arFields)){
                                            $_SESSION['import']['updated']++;
                                            $_SESSION['import']['done']++;
                                            $_SESSION['import']['correct']++;
                                        }else{
                                            $_SESSION['import']['err'][]="Строка ".$line_number.": ".Record::init($hl)->LAST_ERROR;
                                            $_SESSION['import']['error']++;
                                            $_SESSION['import']['done']++;
                                        }
                                    }
                                }else{
                                    $_SESSION['import']['done']++;
                                    $_SESSION['import']['correct']++;
                                }
                            }elseif (!$not_add){
                                if($newID=Record::init($hl)->Add($arFields)){
                                    $_SESSION['import']['add']++;
                                    $_SESSION['import']['done']++;
                                    $_SESSION['import']['correct']++;
                                }else{
                                    $_SESSION['import']['err'][]="Строка ".$line_number.": ".Record::init($hl)->LAST_ERROR;
                                    $_SESSION['import']['error']++;
                                    $_SESSION['import']['done']++;
                                }
                            }else{
                                $_SESSION['import']['done']++;
                                $_SESSION['import']['correct']++;
                            }
                        }
                        unset($dd[$line_number]);
                    }
                    file_put_contents($cfile, serialize($dd));
                ?>
                <?$this->ShowImport(100, count($dd));?>
                    <script>
                        StartImport();
                    </script>
                <?
                else:
                    unlink($cfile);
                   ?>
                    <div class="alert alert-success">
                        Файл успешно обработан!
                    </div>
                    <style>
                        .btn_list{
                            display: none;
                        }
                        .btn_list2{
                            display: block!important;
                        }
                    </style>
                    <?
                    $this->ShowImport(100, 0);
                endif;
                die();
                break;

        }
    }

    function prepare()
    {
        global $APPLICATION;
        $APPLICATION->SetTitle("Загрузка highload-блока: шаг " . $this->step);
    }

    function init()
    {
        global $APPLICATION;
        ?>
        <div class="card">
            <div class="card-header">
                <h5 id="title"><?= $this->titles[$this->step] ?></h5>
            </div>
            <div class="card-body" id="body">
                <form method="post" class="main_form" enctype="multipart/form-data">
                    <? if (IS_AJAX) $APPLICATION->RestartBuffer(); ?>
                    <? $this->showErrors() ?>
                    <? $this->loadStep() ?>
                    <? if (IS_AJAX) die(); ?>
                </form>

            </div>
        </div>
        <?
        $this->getScript();
    }

    function loadStep()
    {
        switch ($this->step) {
            case 1:
                $this->Step1();
                break;
            case 2:
                $this->Step2();
                break;
            case 3:
                $this->Step3();
                break;
            case 4:
                $this->Step4();
                break;

        }

    }

    function Step4()
    {
        ///prepareData
        $_SESSION['import'] = [];
        $profile = $this->getProfile($this->profile);
        if (!$profile) die('Profile not found!');
        $file = $_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath($profile['FILE']);
        if (!file_exists($file)) {
            die("File not found!");
        }
        $xlsx = \SimpleXLSX::parse($file);
        if (!$xlsx) die('Can not read file');
        $cfile = $_SERVER['DOCUMENT_ROOT'] . '/upload/loader_' . $profile["ID"] . '.txt';
        $rows = $xlsx->rows($profile['settings']['sheet']);
        $col = $profile['settings']['header_col'] - 1;
        $header = $rows[$col];
        for ($i = 0; $i <= $col; $i++) {
            unset($rows[$i]);
        }
        $rdata = [];
        $udata = $profile['settings'];
        foreach ($rows as $k => $v) {
            $stop = false;
            foreach ($v as $k2 => $v1) {
                if ($udata['filter'][$k2]['type'] && $udata['filter'][$k2]['type'] != "no") {
                    $type = $udata['filter'][$k2]['type'];
                    $value = explode($udata['multi_delimeter'], $udata['filter'][$k2]['value']);
                    switch ($type) {
                        case '=':
                            if (!in_array($v1, $value)) {
                                $stop = true;
                            }
                            break;
                        case '!=':
                            if (in_array($v1, $value)) {
                                $stop = true;
                            }
                            break;
                        case '>':
                            foreach ($value as $vone) {
                                if (!($v1 > $vone)) {
                                    $stop = true;
                                }
                            }
                            break;
                        case '<':
                            foreach ($value as $vone) {
                                if (!($v1 < $vone)) {
                                    $stop = true;
                                }
                            }
                            break;
                        case 'contain':
                            foreach ($value as $vone) {
                                if (strpos($v1, $vone) === FALSE) {
                                    $stop = true;
                                }
                            }
                            break;
                        case 'not_contain':
                            foreach ($value as $vone) {
                                if (strpos($v1, $vone) !== FALSE) {
                                    $stop = true;
                                }
                            }
                            break;
                    }
                }//endif
            }//endforeach
            if ($stop) continue;


            foreach ($v as $k2 => $v1) {
                if ($udata['match'][$k2]) {
                    $rdata[$k][$udata['match'][$k2]] = $v1;
                }
            }
        }
        file_put_contents($cfile, serialize($rdata));
        $_SESSION['import']['total_rows'] = count($rdata);
        $_SESSION['import']['deleted'] = 0;
        $this->ShowImport($_SESSION['import']['total_rows'], $_SESSION['import']['total_rows'], true);
    }

    function ShowImport($all, $left, $buttons=false)
    {
        if($buttons):
        ?>
        <div class="inner_card" style="position: relative">
        <?
        endif;
        $pp = ceil(($all - $left) * 100 / $all);

        $this->showProgress($pp,
            "Кол-во строк: " . btf($_SESSION['import']['total_rows']) . "<br>".
            "Всего обработано строк: ".btf($_SESSION['import']['done'])."<br>".
            "Из них полностью корректных: ".btf($_SESSION['import']['correct'])."<br>".
            "С ошибками: ".btf($_SESSION['import']['error'])."<br>".
            "Добавлено записей: ".btf($_SESSION['import']['add'])."<br>".
            "Обновлено записей: ".btf($_SESSION['import']['updated'])."<br>"
        );
        if($buttons):
        ?>
        </div>
<?  endif;
        if($buttons):
        ?>
        <div class="text-center mt-4 btn_list">
            <button type="button" class="btn btn-success btn-lg" id="startButton" style="margin-right: 25px; ">Start</button>
            <button type="button" class="btn btn-danger btn-lg" id="stopButton" style="margin-right: 25px; display: none " disabled>Stop</button>
        </div>
        <div class="text-center mt-4 btn_list2" style="display: none">
            <button type="button" onclick="location.href=location.pathname+'?step=1&profile=<?= $this->profile ?>'"
                    class="btn btn-default"> << Вернуться на первый шаг
            </button>

        </div>
            <?
        endif;

    }

    function showProgress($pp = false, $text = "")
    {
        if ($pp === false) {
            $pp = $_SESSION["last_progress"];
        } else {
            $_SESSION["last_progress"] = $pp;
        }
        ?>

            <div class="row">
                <div class="col-md-4">

                <? if ($text): ?>
                    <div class="alert alert-info background-info">
                        <?= $text ?>
                    </div>
                <? endif; ?>

                </div>
                <div class="col-md-8">
                    <?if($_SESSION['import']['err']):?>
                        <div class="alert alert-danger background-danger">
                            <?= implode("<br>", $_SESSION['import']['err']) ?>
                        </div>
                    <?endif;?>
                </div>
            </div>

            <div class="text-center"><?= $pp ?>%</div>
            <div class="progress">

                <div class="progress-bar" role="progressbar" style="width: <?= $pp ?>%;"
                     aria-valuenow="<?= $pp ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>


        <?
    }

    function Step3()
    {
        $profile = $this->getProfile($this->profile);
        if (!$profile) die('Profile not found!');
        $file = $_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath($profile['FILE']);
        if (!file_exists($file)) {
            die("File not found!");
        }
        $xlsx = \SimpleXLSX::parse($file);
        if (!$xlsx) die('Can not read file');
        $rows = $xlsx->rows($profile['settings']['sheet']);
        $col = $profile['settings']['header_col'] - 1;
        $header = $rows[$col];
        $fields = $this->getHlFieldList($profile['settings']['highload']);
        if ($this->errors) {
            $profile['settings']['match'] = $_REQUEST['match'];
            $profile['settings']['filter'] = $_REQUEST['filter'];
        }
        ?>
        <div class="row">
            <div class="col-md-6">
                <? foreach ($header as $k => $v):?>
                    <div class="form-group">
                        <label><?= $v ?></label>
                        <select class="form-control" name="match[<?= $k ?>]">
                            <option value="">Выберите...</option>
                            <? foreach ($fields as $fid => $fname):?>
                                <option value="<?= $fid ?>"
                                    <?= $profile['settings']['match'][$k] == $fid ? 'selected' : '' ?>><?= $fname['TITLE'] ?: $fname['FIELD_NAME'] ?></option>
                            <?endforeach; ?>
                        </select>
                    </div>
                <?endforeach; ?>
            </div>
            <div class="col-md-6">
                <h5 class="mb-5">Фильтр данных</h5>
                <? foreach ($header as $k => $v):?>
                    <div class="row mb-3">
                        <div class="col">
                            <?= $v ?>
                        </div>
                        <div class="col">
                            <select name="filter[<?= $k ?>][type]" class="form-control">
                                <option value="no">Не фильтровать</option>
                                <option value="=">равно</option>
                                <option value="!=">не равно</option>
                                <option value=">">больше</option>
                                <option value="<">меньше</option>
                                <option value="contain">содержить</option>
                                <option value="not_contain">не содержить</option>
                            </select>
                        </div>
                        <div class="col">
                            <input type="text" name="filter[<?= $k ?>][value]" class="form-control">
                        </div>
                    </div>
                <?endforeach; ?>
            </div>
            <div class="col-md-12 text-center">
                <input type="hidden" name="action" value="fields">
                <button type="button" onclick="location.href=location.pathname+'?step=2&profile=<?= $this->profile ?>'"
                        class="btn btn-default">Назад
                </button>
                <input type="submit" value="Далее" class="btn btn-twitter btn waves-effect">
            </div>
        </div>


        <?


    }

    function Step2()
    {
        $profile = $this->getProfile($this->profile);
        if (!$profile) die('Profile not found!');
        $file = $_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath($profile['FILE']);
        if (!file_exists($file)) {
            die("File not found!");
        }
        $xlsx = \SimpleXLSX::parse($file);
        if (!$xlsx) die('Can not read file');
        $sheets = $xlsx->sheetNames();
        $curSheet = $_REQUEST["sheet"] ?: $profile['settings']['sheet'];
        $headerNumber = $_REQUEST["header_col"] ?: $profile['settings']['header_col'];
        ?>
        <input type="hidden" name="profile" value="<?= $profile['ID'] ?>">
        <input type="hidden" name="step" value="<?= $this->step ?>">
        <input type="hidden" name="action" value="sheet">
        <div class="form-group row">
            <label class="col-3 text-right" style="line-height: 35px;">Выберите лист</label>
            <div class="col">
                <select class="form-control" name="sheet" required>
                    <? foreach ($sheets as $k => $sheet): ?>
                        <option value="<?= $k ?>"
                            <?= $k == $curSheet ? 'selected' : '' ?>>
                            Лист <?= $k + 1 ?> "<?= $sheet ?>"
                        </option>
                    <? endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label class="col-3 text-right" style="line-height: 35px;">Номер строка с заголовками</label>
            <div class="col">
                <input type="number" name="header_col" value="<?= $headerNumber ?: 1 ?>" class="form-control">
            </div>
        </div>
        <div class="form-group row">
            <div class="col-12 text-center">
                <button type="button" onclick="location.href=location.pathname+'?step=1&profile=<?= $this->profile ?>'"
                        class="btn btn-default">Назад
                </button>
                <button type="submit" value="Y" name="save" class="btn btn-info">Далее</button>
            </div>
        </div>
        <?


    }

    function Step1()
    {
        $profiles = $this->getProfileList();
        ?>
        <div class="form-group row">
            <label class="col-3 text-right" style="line-height: 35px;">Выберите профиль</label>
            <div class="col">
                <select class="form-control" name="profile" required id="profile"
                        onchange="zLoader.ajaxSubmit(getProfile)">
                    <option value="">- не выбран -</option>
                    <option value="new" <?= $this->profile == "new" ? 'selected' : '' ?>>Создать новый</option>
                    <? foreach ($profiles as $profile): ?>
                        <option value="<?= $profile['ID'] ?>" <?= $this->profile == $profile['ID'] ? 'selected' : '' ?>><?= $profile['NAME'] ?></option>
                    <? endforeach; ?>
                </select>
            </div>

        </div>
        <?
        if ($this->profile) {
            if ($this->profile != "new") {
                $profile = $this->getProfile($this->profile);
            } else {
                $profile = $_REQUEST;
                ?>
                <div class="form-group row">
                    <label class="col-3 text-right" style="line-height: 35px;">Название профиля</label>
                    <div class="col">
                        <input type="text" required value="<?= $profile["NAME"] ?>" name="NAME" class="form-control">
                    </div>

                </div>
                <?
            }
            ?>
            <div class="form-group row">
                <label class="col-3 text-right" style="line-height: 35px;">Файл для загрузки</label>
                <div class="col">
                    <input type="file" value="" name="FILE"
                           accept="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel"
                        <?= !$profile['FILE'] ? "required" : '' ?> class="form-control">
                    <? if ($profile['FILE']):
                        $file = \CFile::GetByID($profile['FILE'])->GetNext();
                        if ($file):
                            ?>
                            <a href="<?= \CFile::GetPath($file['ID']) ?>" class="text-c-blue f-12" download=""
                               title="<?= $file['ORIGINAL_NAME'] ?>">
                                <i class="fa fa-file-excel"></i> <?= $file['ORIGINAL_NAME'] ?>
                            </a>
                        <?endif; ?>
                    <?endif; ?>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-3 text-right" style="line-height: 35px;">Highload-блок</label>
                <div class="col">
                    <select class="form-control" name="settings[highload]" required
                            onchange="zLoader.loadFields(this.value)">
                        <option value="">(выберите highload-блок)</option>
                        <? foreach ($this->getHlList() as $k => $v):?>
                            <option value="<?= $v['ID'] ?>" <?= $profile['settings']['highload'] == $v['ID'] ? 'selected' : '' ?>><?= $v['NAME'] ?></option>
                        <?endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-3 text-right" style="line-height: 35px;">
                    Поле (поля) для идентификации элемента
                    <p class="text-muted f-10 font-italic" style="line-height: normal">
                        По данному полю (полям) будет производиться поиск элемента.
                        Если элемент будет найден по данному полю (полям), то он будет обновлен, иначе будет создан
                        новый элемент.
                        Для выбора нескольких полей нажмите клавишу CTRL.
                        При идентификации по нескольким полям все они должны быть заданы в файле импорта
                    </p>
                </label>
                <div class="col">
                    <div class="row" id="unique_fields">
                        <? foreach ($this->getHlFieldList($profile['settings']['highload']) as $k => $v):
                            $this->createCheckbox(
                                'settings[unique_fields][]',
                                $v['TITLE'] ?: $v['FIELD_NAME'],
                                $v['FIELD_NAME'],
                                in_array($v['FIELD_NAME'], $profile['settings']["unique_fields"]));
                        endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-3 text-right" style="line-height: 35px;">
                    Не создавать новые элементы
                    <p class="text-muted f-10 font-italic" style="line-height: normal">
                        Режим обновления. Будут обновлены только существующие элементы, новые не будут созданы.
                    </p>
                </label>
                <div class="col">
                    <div class="checkbox-color checkbox-info">
                        <input type="hidden" value="0" name="settings[not_add]">
                        <input id="checkbox_not_add" type="checkbox" value="1"
                               name="settings[not_add]" <?= $profile['settings']['not_add'] ? 'checked' : '' ?>>
                        <label for="checkbox_not_add">

                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-3 text-right" style="">
                    Не обновлять существующие элементы
                    <p class="text-muted f-10 font-italic" style="line-height: normal">
                        Режим создания. Будут созданы только новые элементы, существующие останутся без изменений.
                    </p>
                </label>
                <div class="col">
                    <div class="checkbox-color checkbox-info">
                        <input type="hidden" value="0" name="settings[not_update]">
                        <input id="checkbox_not_update" type="checkbox" value="1"
                               name="settings[not_update]" <?= $profile['settings']['not_update'] ? 'checked' : '' ?>>
                        <label for="checkbox_not_update">

                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-3 text-right" style="line-height: 35px;">
                    Разделитель для множественных полей
                </label>
                <div class="col">
                    <input type="text" class="form-control" name="settings[multi_delimeter]"
                           value="<?= $profile['settings']['multi_delimeter'] ?: ";" ?>">
                </div>
            </div>
            <div class="form-group row">
                <div class="col-12 text-center">
                    <input type="hidden" name="action" value="save">
                    <button type="submit" value="Y" name="save" class="btn btn-info">Далее</button>
                </div>
            </div>
            <?
        }

    }

    function getHlFieldList($hl)
    {
        if (!$hl) return [];
        if ($this->fcache[$hl]) return $this->fcache[$hl];
        global $DB;
        $sql = ' select a.*, b.EDIT_FORM_LABEL as TITLE from b_user_field as a, b_user_field_lang as b where a.ENTITY_ID="HLBLOCK_' . $hl . '" 
AND  b.USER_FIELD_ID=a.ID AND b.LANGUAGE_ID="ru" order by SORT asc';
        $a = $DB->Query($sql);
        $fields = [
            "ID" => ["FIELD_NAME" => "ID", "TITLE" => "Ид"]
        ];
        while ($b = $a->GetNext()) {
            $b['SETTINGS'] = unserialize($b['~SETTINGS']);
            $fields[$b['FIELD_NAME']] = $b;
        }
        return $this->fcache[$hl] = $fields;

    }

    function getHlList()
    {
        global $DB;
        $query = $DB->Query("select * from b_hlblock_entity_lang where LID='ru'");
        $lang = [];
        while ($b = $query->GetNext()) {
            $lang[$b['ID']] = $b['NAME'];
        }

        $a = \Bitrix\Highloadblock\HighloadBlockTable::getList(['order' => ['NAME' => "asc"]]);
        $res = [];
        while ($b = $a->Fetch()) {
            if ($lang[$b['ID']]) $b['NAME'] = $lang[$b['ID']];
            $res[] = $b;
        }
        return $res;
    }

    function checkDB()
    {
        global $DB;
        $query = "SELECT * 
            FROM information_schema.tables
            WHERE table_schema = '" . $GLOBALS["DBName"] . "' 
                AND table_name = '" . $this->table . "'
            LIMIT 1";
        $r = $DB->Query($query);
        if ($r->SelectedRowsCount()) {
            return true;
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS " . $this->table . " (
                ID INT(16) NOT NULL AUTO_INCREMENT,
                NAME VARCHAR(255) NOT NULL,
                FILE INT(30),
                SETTINGS longtext,
                PRIMARY KEY (ID))
                ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
                ";
            if ($DB->Query($sql)) {
                return true;
            } else {
                die("ProbLem On Creating Table");
            }
        }
    }

    function getProfileList(): array
    {
        global $DB;
        $sql = $DB->Query("select * from " . $this->table);
        $list = [];
        while ($b = $sql->GetNext()) {
            // $b['settings'] =  unserialize(base64_decode($b['SETTINGS']));
            $list[$b['ID']] = $b;
        }
        return $list;
    }

    function getProfile($id)
    {
        global $DB;
        $sql = $DB->Query("select * from " . $this->table . ' WHERE ID=' . $id);
        $b = $sql->GetNext();
        if ($b)
            $b['settings'] = unserialize(base64_decode($b['SETTINGS']));
        return $b;
    }

    function getScript()
    {
        ?>
        <script>
            const zLoader = {
                form: $(".main_form"),
                ajaxSubmit(formDataCallback = false, successCallback = false, errorCallback = false) {
                    const container = $(".main_form");
                    const self = this;
                    let fd = new FormData($('.main_form')[0]);
                    if (formDataCallback) {
                        fd = formDataCallback.call(self, fd);
                    }
                    console.log(fd);
                    container.fadeTo("slow", 0.3);
                    $.ajax({
                        processData: false,
                        contentType: false,
                        type: 'POST',
                        url: location.pathname + '?z-ajax=Y',
                        data: fd,
                        success: function (data) {
                            container.html(data).fadeTo(200, 1);
                            if (successCallback) {
                                successCallback.call(self, data);
                            }
                        },
                        error: function (data) {
                            container.prepend(`<div class="alert alert-danger">${data.responseText}</div>`).fadeTo(200, 1);
                            if (errorCallback) {
                                errorCallback.call(self, data);
                            }
                        }
                    });
                },

                loadFields(value) {
                    const fieldSelect = $('#unique_fields');
                    if (!value) fieldSelect.html("");
                    else {
                        $.post(location.pathname, {action: 'loadFields', hl: value}, function (data) {
                            fieldSelect.html(data);
                        })
                    }
                },
                submitForm() {
                    $(".main_form").attr('onsubmit', '').submit();
                }
            }

            function getProfile(fd) {
                const data = new FormData();
                data.append("profile", fd.get('profile'));
                return data;
            }

            const ZakImport = {
                startBtn: $("#startButton"),
                stopButton: $("#stopButton"),
                stop: false,
                loading:false,
                init() {
                    const self = this;
                    this.startBtn.click(function () {
                        if ($(this).is(":disabled")) return false;
                        self.Start();
                    });
                    this.stopButton.click(function () {
                        if ($(this).is(":disabled")) return false;
                        self.Stop();
                    })
                },
                Start(){
                    this.startBtn.attr('disabled', true).hide();
                    this.stopButton.attr('disabled', false).show();
                    this.stop = false;
                    this.Import();
                },
                Stop(){
                    this.startBtn.attr('disabled', false).show();
                    this.stopButton.attr('disabled', true).hide();
                    this.stop = true;
                },
                Import(){
                    const  self=this;
                    if(self.stop) {
                        return false;
                    }
                    if(self.loading) {
                        return false;
                    }
                    const container=$(".main_form");
                    const loadContent=$('.inner_card');
                    setLoader(loadContent, true);
                    self.loading=true;
                    $.ajax({
                        type: 'POST',
                        url: location.pathname + '?z-ajax=Y',
                        data: {profile: "<?=$_REQUEST['profile']?>", action:'import'},
                        success: function (data) {
                            self.loading=false;
                            loadContent.html(data);
                            removeLoader(loadContent, true);
                        },
                        error: function (data) {
                            loadContent.html(`<div class="alert alert-danger">${data.responseText}</div>`);
                            removeLoader(loadContent, true);
                            self.loading=false;
                            self.Stop();
                        }
                    });
                }


            }
            ZakImport.init();
            function StartImport(){
                setTimeout(function () {
                    ZakImport.Import();
                }, 500);
            }
        </script>
        <?
    }

    function getStyles()
    {
        ?>
        <style>
            button:disabled{
                cursor: not-allowed;
                opacity: 0.5;
            }
        </style>
        <?
    }

    private function createCheckbox($name, $label, $value, $checked)
    {
        ?>
        <div class="col-3">
            <label><input type="checkbox" name="<?= $name ?>"
                          value="<?= $value ?>" <?= $checked ? 'checked' : '' ?>> <?= $label ?></label>
        </div>
        <?
    }

    private function showErrors()
    {
        if ($this->errors) {
            ?>
            <div class="alert alert-danger">
                <?= implode("<br>", $this->errors) ?>
            </div>
            <?
        }
    }

    private function getEnum($field, $value)
    {
        if(!$value) return  false;
        if(!$list=$this->cache['enums'][$field['ID']]){
            $fenum = new \CUserFieldEnum();
            $dbRes = $fenum->GetList(array("SORT"=>"ASC", "VALUE"=>"ASC"), array('USER_FIELD_ID'=>$field['ID']));
            while(($arr = $dbRes->Fetch()))
            {
                $list[$arr['ID']] = trim(toLower($arr['VALUE']));
            }
            $this->cache['enums'][$field['ID']]=$list;
        }

        $find=array_search(trim(toLower($value)), $list);
        if($find) return $find;
        $obEnum = new \CUserFieldEnum;
        $obEnum->SetEnumValues($field['ID'], array(
            "n0" => array(
                "VALUE" => trim($value),
            ),
        ));
        $arEnum = $obEnum->GetList(array(), array(
            "VALUE" =>trim($value),
        ))->GetNext();
        $list[$arEnum['ID']]=trim($value);
        $this->cache['enums'][$field['ID']]=$list;
        return $arEnum['ID'];
    }

    private function getHl($field, $value)
    {
        $value=trim($value);
        if(!$value) return  false;
        $hl=$field['SETTINGS']['HLBLOCK_ID'];
        if(!$hl) return  false;
        $v=Record::init($hl)->GetList([], ["=UF_NAME"=>($value)], ["ID", "UF_NAME"], true, 1);
        if($v) return $v['ID'];
        return  Record::init($hl)->Add(["UF_NAME"=>$value]);
    }

}

?>