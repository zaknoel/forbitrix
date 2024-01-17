<?
namespace Zaknoel\ForBitrix;
class GridList
{
    var $id, $params;

    function __construct($id)
    {
        $this->id = $id;
    }

    function show($arParams)
    {
        $default_params = [
            "show_add" => "Y"
        ];

        $arParams = $this->params = array_merge($default_params, $arParams);
        $this->modal();
        $this->actions();

        $item = new ZakList($this->id, '', $arParams['code']);
        $item->code = $arParams['code'];
        $item->presets=$arParams['preset'];
        $item->actions = [
            [
                'text' => 'Посмотреть',
                'default' => true,
                'onclick' => "item.show(#ID#)"
            ],
            canEdit() ? [
                'text' => 'Редактировать',
                'default' => false,
                'onclick' => "item.edit(#ID#)"
            ] : [],
            canEdit() ? [
                'text' => 'Удалить',
                'default' => false,
                'onclick' => "ModalConfirm('Вы действительно хотите удалить?', 'Это действие необратимо.', 'ItemDelete', {'action':'delete', id:#ID#});"
            ] : []
        ];

        $item->actions = array_filter($item->actions);
        $item->asf = $arParams['ASF'];
        $chdata = [
            "fields" => $arParams["fields"],
            'filter' => $arParams["filter"],
            'change' => $arParams['change'],
            'onFilter' => $arParams['onFilter'],
            'action' => $arParams['action']
        ];
        foreach ($chdata as $k => $v) {
            if (!$v) unset($chdata[$k]);
        }
        $item->changerFilter = $chdata;
        $item->show();
        if ($arParams['show_add'] == 'Y' && canEdit()):
            global $APPLICATION;

            ob_start(); ?>
            <div class="text-right">
                <button onclick="item.add(this)" class="btn btn-info btn-sm waves-effect"><i class="fa fa-plus"></i>
                    Добавить
                </button>
            </div>
            <?
            $html = @ob_get_clean();
            $APPLICATION->AddViewContent('buttons', $html);
        endif;
        $this->html($arParams);


    }

    function html($arParams)
    {
        global $APPLICATION;
        $page = $arParams['page'] ?: $APPLICATION->GetCurPage();
        ?>

        <? include $_SERVER['DOCUMENT_ROOT'] . '/app/modal.php' ?>

        <script>
            const item = {
                modal: $('#main_modal'),
                content: $('.modal-dialog'),

                show: function (id) {
                    location.href = '<?=$page?>' + id + "/show/";
                },
                edit: function (id) {
                    setLoader(item.content, true);
                    item.modal.modal('show');
                    $.get('<?=$page?>', {action: 'modal', 'id': id}, function (data) {
                        item.content.html(data);
                        if (window.hasOwnProperty('OnModalOpen')) {
                            OnModalOpen();
                        }
                        initSelect();
                        removeLoader(item.content, true);
                    })
                },
                add: function (_this, fields={}) {
                    setLoader(item.content, true);
                    item.modal.modal('show');
                    fields["action"]='modal';
                    $.get('<?=$page?>', fields, function (data) {
                        item.content.html(data);
                        if (window.hasOwnProperty('OnModalOpen')) {
                            OnModalOpen();
                        }
                        initSelect();
                        removeLoader(item.content, true);
                    })

                },
                save: function (_this) {
                    setLoader(item.content, true);
                    var file = $(_this).find('input[type="file"]');
                    if (file.length) {
                        $(_this).submit();
                    } else {
                        reloadGrid(<?=$this->id?>, $(_this).serializeObject(), function () {
                            item.modal.modal('hide');
                            initSelect();
                            removeLoader(item.content, true);
                        }, '<?=$page?>');
                    }


                }

            };

            function ItemDelete(confirm, params) {
                if (confirm) {
                    reloadGrid(<?=$this->id?>, params, function () {
                        swal.close();
                    }, '<?=$page?>');

                } else {
                    ModalError("Отменено!", "");
                }
            }
        </script>
        <?
    }

    function modal()
    {
        if (!canEdit()) return false;
        if ($_REQUEST['action'] == 'modal') {
            global $APPLICATION;
            $APPLICATION->RestartBuffer();
            $data = $_REQUEST['id'] ? Record::init($this->id)->GetByID($_REQUEST['id']) : [];
            $fields = getHlFields($this->id);


            ?>
            <style>.modal-dialog {
                    max-width: 70%
                }</style>
            <form method="post" action="" class="" enctype="multipart/form-data"
                  onsubmit="item.save(this); return false">
                <input type="hidden" name="action" value="add">
                <? if ($data):?>
                    <input type="hidden" value="<?= $data['ID'] ?>" name="ID">
                <?endif; ?>
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><?= $data ? ' Редактировать' : 'Добавить' ?></h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <? foreach ($fields as $k => $field):
                            if (in_array($k, $this->params['not_add'])) continue;
                            if (!$this->params['fields'][$k] && !$field['TITLE']) continue;
                            $cur_value = $_REQUEST['v'][$k]?:$data[$k];
                            $req = $field['MANDATORY'] == 'Y' || $k == 'UF_NAME' ? 'required' : '';
                            $multy = $field["MULTIPLE"] == 'Y' ? 'multiple' : '';
                            ?>
                            <div class="form-group row">
                                <label class="col-sm-2 col-form-label"><?= $this->params['fields'][$k] ?: $field['TITLE'] ?>
                                    <?= $req ? '<sup style="color: red">*</sup>' : '' ?>
                                </label>
                                <div class="col-sm-10">
                                    <? if ($k == 'UF_LOCATION'): ?>
                                    <input type="text" class="form-control form-control-sm" name="v[UF_LOCATION]" id="UF_LOCATION"
                                           value="<?= $cur_value ?>">
                                        <div id="sel_map" style="width: 100%; height: 400px"></div>
                                        <script>
                                            $(function () {
                                                ymaps.ready(init);
                                            })

                                            function init() {
                                                var myPlacemark,
                                                    myMap = new ymaps.Map('sel_map', {
                                                        center: [41.31620950467525, 69.27942399984737],
                                                        zoom: 13,
                                                        behaviors: ['default', 'scrollZoom']
                                                    }, {
                                                        searchControlProvider: 'yandex#search'
                                                    });
                                                if ($('#UF_LOCATION').val()) {

                                                    let c = $('#UF_LOCATION').val().split(",");
                                                    c = [parseFloat(c[0]), parseFloat(c[1])];
                                                    myPlacemark = createPlacemark(c);
                                                    myMap.geoObjects.add(myPlacemark);
                                                    // Слушаем событие окончания перетаскивания на метке.
                                                    myPlacemark.events.add('dragend', function () {
                                                        $('#UF_LOCATION').val(myPlacemark.geometry.getCoordinates().join(","));
                                                        getAddress(myPlacemark.geometry.getCoordinates());
                                                    });
                                                    getAddress(c)
                                                    myMap.setCenter([parseFloat(c[0]), parseFloat(c[1])], 17, {
                                                        checkZoomRange: true
                                                    })
                                                }
                                                // Слушаем клик на карте.
                                                myMap.events.add('click', function (e) {
                                                    var coords = e.get('coords');

                                                    // Если метка уже создана – просто передвигаем ее.
                                                    if (myPlacemark) {
                                                        myPlacemark.geometry.setCoordinates(coords);
                                                    }
                                                    // Если нет – создаем.
                                                    else {
                                                        myPlacemark = createPlacemark(coords);
                                                        myMap.geoObjects.add(myPlacemark);
                                                        // Слушаем событие окончания перетаскивания на метке.
                                                        myPlacemark.events.add('dragend', function () {
                                                            $('#UF_LOCATION').val(myPlacemark.geometry.getCoordinates().join(","))
                                                            getAddress(myPlacemark.geometry.getCoordinates());
                                                        });
                                                    }
                                                    $('#UF_LOCATION').val(coords.join(","))
                                                    getAddress(coords);
                                                });

                                                // Создание метки.
                                                function createPlacemark(coords) {
                                                    return new ymaps.Placemark(coords, {
                                                        iconCaption: 'поиск...'
                                                    }, {
                                                        preset: 'islands#violetDotIconWithCaption',
                                                        draggable: true
                                                    });
                                                }

                                                // Определяем адрес по координатам (обратное геокодирование).
                                                function getAddress(coords) {
                                                    myPlacemark.properties.set('iconCaption', 'поиск...');
                                                    ymaps.geocode(coords).then(function (res) {
                                                        var firstGeoObject = res.geoObjects.get(0);

                                                        myPlacemark.properties
                                                            .set({
                                                                // Формируем строку с данными об объекте.
                                                                iconCaption: [
                                                                    // Название населенного пункта или вышестоящее административно-территориальное образование.
                                                                    firstGeoObject.getLocalities().length ? firstGeoObject.getLocalities() : firstGeoObject.getAdministrativeAreas(),
                                                                    // Получаем путь до топонима, если метод вернул null, запрашиваем наименование здания.
                                                                    firstGeoObject.getThoroughfare() || firstGeoObject.getPremise()
                                                                ].filter(Boolean).join(', '),
                                                                // В качестве контента балуна задаем строку с адресом объекта.
                                                                balloonContent: firstGeoObject.getAddressLine()
                                                            });
                                                    });
                                                }
                                            }

                                        </script>
                                    <? elseif ($multy && in_array($field['USER_TYPE_ID'], ['string', 'integer', 'double', 'date', 'datetime'])): ?>
                                        <div class="client_phone">
                                            <? if (!$data[$k]) $data[$k] = ['']; ?>
                                            <? foreach ($data[$k] as $phone): ?>
                                                <div class="input-group input-group-sm each_phone">
                                                    <? switch ($field['USER_TYPE_ID']):
                                                        case 'string':
                                                            ?>
                                                            <? if ($field['SETTINGS']['ROWS'] > 1):?>
                                                            <textarea class="form-control form-control-sm" <?= $req ?>  name="v[<?= $k ?>][]"
                                                                      rows="<?= $field['SETTINGS']['ROWS'] ?>"><?= $phone ?></textarea>
                                                        <? else:?>
                                                            <input class="form-control form-control-sm"
                                                                   type="text" <?= $req ?> name="v[<?= $k ?>][]"
                                                                   value='<?= quotclear($phone) ?>'>
                                                        <?endif; ?>
                                                            <? break;
                                                        case 'integer':
                                                            ?>
                                                            <input class="form-control form-control-sm"
                                                                   type="number" <?= $req ?> name="v[<?= $k ?>][]"
                                                                   value='<?= quotclear($phone) ?>'>
                                                            <? break;
                                                        case 'double':
                                                            ?>
                                                            <input class="form-control form-control-sm" type="number"
                                                                   step="0.1" <?= $req ?> name="v[<?= $k ?>][]"
                                                                   value='<?= quotclear($phone) ?>'>
                                                            <? break;
                                                        case 'date':
                                                            ?>
                                                            <input class="form-control form-control-sm"
                                                                   type="date" <?= $req ?> name="v[<?= $k ?>][]"
                                                                   value="<?= $phone ? date('Y-m-d', strtotime($phone->toString())) : '' ?>">
                                                            <? break;
                                                        case 'datetime':
                                                            ?>
                                                            <input class="form-control form-control-sm"
                                                                   type="datetime-local" <?= $req ?>
                                                                   name="v[<?= $k ?>][]"
                                                                   value="<?= $phone ? date('Y-m-d H:i', strtotime($phone->toString())) : '' ?>">
                                                            <? break;
                                                    endswitch; ?>
                                                    <div class="input-group-append">
                                                        <button type="button"
                                                                onclick="ZakHelper.clone(this, '.each_phone')"
                                                                class="btn btn-mini btn-success add_btn"><i
                                                                    class="fa fa-plus"></i></button>
                                                        <button type="button"
                                                                onclick="ZakHelper.delete(this, '.each_phone')"
                                                                class="btn btn-mini btn-danger remove_btn"><i
                                                                    class="fa fa-trash"></i></button>
                                                    </div>
                                                </div>
                                            <?endforeach; ?>
                                        </div>
                                    <? else: ?>
                                    <? switch ($field['USER_TYPE_ID']):
                                    case 'address':
                                    case 'string':
                                    ?>
                                    <? if ($field['SETTINGS']['ROWS'] > 1): ?>
                                        <textarea class="form-control form-control-sm" <?= $req ?>  name="v[<?= $k ?>]"
                                                  rows="<?= $field['SETTINGS']['ROWS'] ?>"><?= $cur_value ?></textarea>
                                    <? else:?>
                                    <input class="form-control form-control-sm" type="text" <?= $req ?>
                                           name="v[<?= $k ?>]" value='<?= quotclear($cur_value) ?>'>
                                    <?endif;
                                        ?>
                                        <? break;
                                    case 'integer':
                                        ?>
                                    <input class="form-control form-control-sm" type="number" <?= $req ?>
                                           name="v[<?= $k ?>]" value='<?= quotclear($cur_value) ?>'>
                                        <? break;
                                    case 'double':
                                        ?>
                                    <input class="form-control form-control-sm" type="number" step="0.1" <?= $req ?>
                                           name="v[<?= $k ?>]" value='<?= quotclear($cur_value) ?>'>
                                        <? break;
                                    case 'date':
                                        ?>
                                    <input class="form-control form-control-sm" type="date" <?= $req ?>
                                           name="v[<?= $k ?>]"
                                           value="<?= $cur_value ? date('Y-m-d', strtotime($cur_value->toString())) : '' ?>">
                                        <? break;
                                    case 'datetime':
                                        ?>
                                    <input class="form-control form-control-sm" type="datetime-local" <?= $req ?>
                                           name="v[<?= $k ?>]"
                                           value="<?= $cur_value ? date('Y-m-d H:i', strtotime($cur_value->toString())) : '' ?>">
                                        <? break;
                                    case 'boolean':
                                        ?>
                                        <div class="checkbox-color checkbox-primary">
                                            <input type="hidden" name="v[<?= $k ?>]" value="0">
                                            <input id="checkbox_<?= $k ?>" type="checkbox" name="v[<?= $k ?>]"
                                                   value="1" <?= $cur_value ? 'checked' : '' ?>>
                                            <label for="checkbox_<?= $k ?>">
                                                Да
                                            </label>
                                        </div>
                                        <? break;
                                    case "hlblock":
                                        $hl = $field['SETTINGS']['HLBLOCK_ID'];
                                    if ($field['SETTINGS']['DISPLAY'] == 'CHECKBOX') {
                                        $list = Record::init($hl)->GetList();
                                        ?>

                                        <div class="<?= !$multy ? 'form-radio' : '' ?>">
                                            <? if ($hl == 10):?>
                                                <a href="javascript:void(0)" onclick="checkAll(this)"
                                                   class="f-14 d-block mb-3 border-bottom-success text-c-blue">Выбрать
                                                    все</a>
                                            <?endif; ?>
                                            <? foreach ($list as $lk => $lv) {
                                               ?>
                                                <? if (!$multy):?>
                                                    <div class="radio">
                                                        <label>
                                                            <input type="radio"
                                                                   name="v[<?= $k ?>]<?= ($multy ? "[]" : "") ?>"
                                                                   class="checkbox_<?= $k ?>"
                                                                   value="<?= $lk ?>" <?= $cur_value == $lk ? 'checked' : '' ?>>
                                                            <i class="helper"></i> <?= $lv['UF_NAME'] ?>
                                                        </label>
                                                    </div>
                                                <? else:?>
                                                    <div class="checkbox-color checkbox-primary d-block">
                                                        <input id="checkbox_<?= $k ?>_<?= $lk ?>" type="checkbox"
                                                               class="checkbox_<?= $k ?>"
                                                               name="v[<?= $k ?>]<?= ($multy ? "[]" : "") ?>"
                                                               value="<?= $lk ?>"
                                                            <?= in_array($lk, $cur_value??[]) ? 'checked' : '' ?>
                                                        >
                                                        <label for="checkbox_<?= $k ?>_<?= $lk ?>">
                                                            <?= $lv['UF_NAME'] ?>
                                                        </label>
                                                    </div>
                                                <?endif; ?>

                                            <?
                                            } ?>
                                        </div>
                                        <?

                                    }
                                    else//if LIST
                                    {

                                        ?>
                                        <select id="f_<?= $k ?>" class="form-control form-control-sm ajax_sel "
                                                name="v[<?= $k ?>]<?= ($multy ? "[]" : "") ?>" <?= $req ?> <?= $multy ?>
                                                data-placeholder='Выберите . . .' data-type="hl"
                                                data-ib="<?= $hl ?>"
                                        >
                                            <option></option>
                                            <? if ($cur_value):
                                                foreach (Record::init($hl)->GetList([], ["ID" => $cur_value], ['*']) as $k1 => $v1):
                                                    ?>
                                                    <option value="<?= $v1['ID'] ?>"
                                                            selected><?= $v1['UF_NAME'] ?></option>
                                                <?endforeach; ?>
                                            <?endif; ?>
                                        </select>
                                        <?
                                    }//endif;
                                        ?>
                                        <? break;
                                    case "enumeration":
                                        $a = CUserFieldEnum::GetList([], ['USER_FIELD_ID' => $field['ID']]);
                                        $items = [];
                                        while ($b = $a->GetNext()) {
                                            $items[$b['ID']] = $b['VALUE'];
                                        }
                                        $cur_value = is_array($cur_value) ?: [$cur_value];
                                        ?>
                                        <select class="form-control form-control-sm simple_select "
                                                name="v[<?= $k ?>]<?= ($multy ? "[]" : "") ?>" <?= $req ?> <?= $multy ?>
                                                data-placeholder='Выберите . . .'
                                        >
                                            <option></option>
                                            <? foreach ($items as $k1 => $v1):?>
                                                <option value="<?= $k1 ?>" <?= in_array($k1, $cur_value) ? 'selected' : '' ?> ><?= $v1 ?></option>
                                            <?endforeach; ?>
                                        </select>

                                        <? break;
                                    case 'file':
                                    if ($cur_value):
                                        $file = CFile::GetByID($cur_value)->GetNext();
                                        ?>
                                        <div class="mb-3 d-block">
                                            <a class="text-c-blue " target="_blank"
                                               href="<?= CFile::GetPath($cur_value) ?>"><?= $file['ORIGINAL_NAME'] ?></a>
                                            |
                                            <label><input type="checkbox" style="vertical-align: middle"
                                                          name="del[<?= $k ?>]" value="<?= $cur_value ?>">
                                                Удалить</label>
                                        </div>
                                    <?endif;
                                        ?>
                                    <input type="file" name="<?= $k ?>" <?= $req ?> class="form-control-file">

                                        <?
                                        break;
                                        default:
                                            echo "<pre>";
                                            print_r($field);
                                            echo "</pre>";;
                                    endswitch; ?>
                                    <?endif; ?>
                                </div>
                            </div>
                        <?endforeach; ?>
                    </div>

                    <div class="modal-footer">
                        <button type="reset" class="btn btn-sm btn-default waves-effect " data-dismiss="modal">
                            <i class="feather icon-x"></i> Отмена
                        </button>
                        <button type="submit" class="btn btn-sm btn-info waves-effect waves-light ">
                            <i class="feather icon-save"></i>
                            Сохранить
                        </button>
                    </div>

                </div>
            </form>
            <?
            die();
        }

    }

    function actions()
    {

        if (!canEdit()) return false;
        if ($_SERVER["REQUEST_METHOD"] == 'POST') {

            $action = $_REQUEST['action'];
            switch ($action):
                case "add":
                    $fields = getHlFields($this->id);
                    $arUpdate = [];
                    foreach ($_REQUEST['v'] as $k => $v) {
                        $multy = $fields[$k]['MULTIPLE'] == "Y";
                        if ($multy && !is_array($v)) $v = [$v];
                        switch ($fields[$k]['USER_TYPE_ID']) {
                            case "date":
                                if ($multy) {
                                    foreach ($v as $kk => $vv) $v[$kk] = date('d.m.Y', strtotime($vv));
                                } else {
                                    $v = date('d.m.Y', strtotime($v));
                                }

                                break;
                            case 'datetime':
                                if ($multy) {
                                    foreach ($v as $kk => $vv) $v[$kk] = date('d.m.Y H:i:s', strtotime($vv));
                                } else {
                                    $v = date('d.m.Y H:i:s', strtotime($v));
                                }
                                break;

                        }
                        if(in_array($fields[$k]['USER_TYPE_ID'], ['iblock_section', 'hlblock', 'iblock_element'])){
                            $arUpdate[$k] = $multy ? array_filter(array_unique(toWrite($v))) : toWrite($v);
                        }else{
                            $arUpdate[$k] = toWrite($v);
                        }

                    }
                    foreach ($_FILES as $k => $v) {
                        if ($v['tmp_name'])
                            $arUpdate[$k] = $v;
                    }
                    foreach ($_REQUEST['del'] as $k => $v) {
                        $arUpdate[$k] = ['del' => 'Y', 'old_id' => $v];
                    }
                    if ($this->params['onAdd']) {
                        try {
                            $reflectionAction = new ReflectionFunction($this->params['onAdd']);
                            $arUpdate = $reflectionAction->invoke($arUpdate);
                        } catch (ReflectionException $e) {
                        }

                    }
                    if ($arUpdate) {

                        if ($_REQUEST['ID']) {//edit
                            if (Record::init($this->id)->Update($_REQUEST['ID'], $arUpdate)) {
                                setJsSuccess("Успешно сохранена!");
                            } else {
                                //die(Record::init($this->id)->LAST_ERROR);
                                setJsError(Record::init($this->id)->LAST_ERROR);
                                setError(Record::init($this->id)->LAST_ERROR);
                            }

                        } else {//add

                            $CID = Record::init($this->id)->Add($arUpdate);
                            if ($CID) {
                                if ($this->params['after_add']) {
                                    LocalRedirect(str_replace("#ID#", $CID, $this->params['after_add']));
                                }
                                setJsSuccess("Успешно добавлена!");
                            } else {
                                setJsError(Record::init($this->id)->LAST_ERROR);
                                setError(Record::init($this->id)->LAST_ERROR);
                                //die(Record::init($this->id)->LAST_ERROR);
                            }

                        }

                    }
                    if ($_REQUEST['backurl']) LocalRedirect($_REQUEST['backurl']);
                    if (!$_REQUEST['bxajaxid']) {
                        global $APPLICATION;
                        LocalRedirect($APPLICATION->GetCurPage());
                    }
                    break;

                case 'delete':
                    Record::init($this->id)->Delete($_REQUEST['id']);
                    setJsSuccess('Удалена!');

                    break;
            endswitch;
        }
    }
}

?>