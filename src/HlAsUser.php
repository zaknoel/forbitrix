<?
class HlAsUser{
    private  string $tableName;
    private int $group;
    private string $prop;
    private int $hl;
    private string $name;
    function __construct(
        $tableName,
        $group,
        $hl,
        $prop="UF_COMPANY",
        $name='Торговые представители'
    )
    {
        $this->group=$group;
        $this->tableName=$tableName;
        $this->prop=$prop;
        $this->hl=$hl;
        $this->name=$name;
        $this->setEvents();
    }
    function setEvents(){
        $eventManager = \Bitrix\Main\EventManager::getInstance();


        //on Before
        $eventManager->addEventHandler('', $this->tableName.'OnBeforeAdd', function (Bitrix\Main\Entity\Event $event){
            return $this->OnBeforeAdd($event);
        });
        $eventManager->addEventHandler('', $this->tableName.'OnBeforeUpdate', function (Bitrix\Main\Entity\Event $event){
            return $this->OnBeforeAdd($event);
        });
        //on After
        $eventManager->addEventHandler('', $this->tableName.'OnAfterAdd', function (Bitrix\Main\Entity\Event $event){
            return $this->OnAfterAdd($event);
        });
        $eventManager->addEventHandler('', $this->tableName.'OnAfterUpdate', function (Bitrix\Main\Entity\Event $event){
            return $this->OnAfterAdd($event);
        });
        ///on Delete
        $eventManager->addEventHandler('', $this->tableName.'OnAfterDelete', function (Bitrix\Main\Entity\Event $event){
            $id = $event->getParameter("id");
            $id = $id["ID"];

            $by = 'id';
            $order = "asc";
            $user = CUser::GetList($by, $order, [$this->prop => $id])->GetNext();
            if($user){
                $GLOBALS['onHDelete']=1;
                CUser::Delete($user['ID']);
                $GLOBALS['onHDelete']=0;
            }
        });


        AddEventHandler('main', 'OnAfterUserUpdate', function (&$arFields) {

            if ($arFields['ID'] && $arFields['CONFIRM_PASSWORD']) {
                $user = getUsers(['ID' => $arFields['ID']], ['FIELDS' => ['ID'], 'SELECT' => [$this->prop]], true);
                if ($user[$this->prop]) {
                    $GLOBALS['NO_UPDATE_PASSWORD'] = true;
                    Record::init($this->tableName)->Update($user[$this->prop], ['UF_PASSWORD' => $arFields['CONFIRM_PASSWORD']]);
                    $GLOBALS['NO_UPDATE_PASSWORD'] = false;
                }
            }

        });
        AddEventHandler('main', 'OnBeforeUserDelete',  function ($userID){
            $user = getUsers(['ID' => $userID], ['FIELDS' => ['ID'], 'SELECT' => [$this->prop]], true);
            if($user[$this->prop] && !$GLOBALS['onHDelete']){
                $hl=$this->hl;
                $obj=Record::init($hl)->GetByID($user[$this->prop]);
                if($obj){
                    global $APPLICATION;
                    $entity=$this->name;
                    $APPLICATION->throwException("Нельзя удалить этого пользователя так как он связан с сущности ".$entity);
                    return false;
                }

            }
        });

    }
    private function OnBeforeAdd(Bitrix\Main\Entity\Event $event){
        $event = new Event($event);
        $all = $event->getAll();
        $ID = $event->getID();
        //validate user info
        $arUser = [
            "LOGIN" => trim($all['UF_LOGIN']),
            "PASSWORD" => trim($all['UF_PASSWORD']),
        ];
        $err="";
        if(strlen($arUser['LOGIN'])<3){
            $err="Логин должен быть не менее 3 символов.";
        }else{
            $user=CUser::GetByLogin($arUser['LOGIN'])->GetNext();
            if($user){
                if($ID){
                    if($user[$this->prop]!=$ID){
                        $err="Пользователь с логином \"".$arUser['LOGIN']."\" уже существует.";
                    }
                }else{
                    $err="Пользователь с логином \"".$arUser['LOGIN']."\" уже существует.";
                }
            }

        }
        if(strlen($arUser['PASSWORD'])<6 && !$err){
            $err="Пароль должен быть не менее 6 символов длиной";
        }
        if($err){
            $result = new Bitrix\Main\Entity\EventResult();
            $entity = $event->event->getEntity();
            $arErrors = array();
            $arErrors[] = new \Bitrix\Main\Entity\FieldError($entity->getField("UF_LOGIN"),
                $err);
            $result->setErrors($arErrors);
            return $result;
        }
    }



    private function OnAfterAdd(\Bitrix\Main\Entity\Event $event)
    {
        if ($GLOBALS['NO_UPDATE_PASSWORD']) return false;
        $event = new Event($event);
        $old = $event->getActual();
        $all = $event->getAll();
        $current = $event->getCurrent();
        $ID = $event->getID();
        $by = 'id';
        $order = "asc";
        $user = CUser::GetList($by, $order, [$this->prop => $ID])->GetNext();
        if (!$user) {
            $arUser = [
                "NAME" => $all['UF_NAME'],
                "LOGIN" => trim($all['UF_LOGIN']),
                "PASSWORD" => trim($all['UF_PASSWORD']),
                "CONFIRM_PASSWORD" => trim($all['UF_PASSWORD']),
                "GROUP_ID" => [$this->group],
                $this->prop => $ID
            ];
            global $USER;
            if (!$USER->Add($arUser)) {
                $error = $USER->LAST_ERROR;
                setJsError($error);
            } else {
                setJsSuccess("Успешно сохранено!");
                //LocalRedirect("/info/contragents/".$_REQUEST['ID'].'/show/');
            }
        } elseif ($old['UF_LOGIN'] != $current['UF_LOGIN'] || $old['UF_PASSWORD'] != $current['UF_PASSWORD']) {
            $arUser = [
                "NAME" => $all['UF_NAME'],
                "LOGIN" => trim($all['UF_LOGIN']),
                "PASSWORD" => trim($all['UF_PASSWORD']),
                "CONFIRM_PASSWORD" => trim($all['UF_PASSWORD']),
            ];
            global $USER;
            if (!$USER->Update($user['ID'], $arUser)) {
                $error = $USER->LAST_ERROR;
                setJsError($error);
            }
        }
        return $event;
    }




}
?>