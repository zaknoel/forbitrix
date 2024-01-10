<?

use Sprint\Migration\VersionManager;

class Migrate{
    private bool $active=true;
    private $sprint;
    function __construct()
    {
        if(!CModule::IncludeModule('sprint.migration')){
            $this->active=false;
        }
        $this->sprint = new Sprint\Migration\VersionConfig();
    }
    function MakeMigration($post){
        $versionManager = new VersionManager($this->sprint );
        $builderName = !empty($post['builder_name']) ? trim($post['builder_name']) : '';

        $builder = $versionManager->createBuilder($builderName, $post);
        $builder->buildExecute();
        $builder->buildAfter();
        $builder->renderHtml();
    }
    function isActive(): bool
    {
        if(strpos($_SERVER['HTTP_HOST'], '.loc')===FALSE) return false;
        return $this->active;
    }

    public static function OnTableAdd(\Bitrix\Main\Entity\Event $event){
        $event=new Event($event);
        $ID=$event->getID();
      $post=[
          'builder_name'=>'HlblockBuilder',
          'prefix'=>'Version',
          'description'=>'Auto generated',
          'hlblock_id'=>$ID,
          'step_code'=>'migration_create',
          'filter'=>'migration_view_all'
      ];
        (new Migrate())->MakeMigration($post);
    }
    public static function OnUFieldsAdd($arFields){
        $post=[
            'builder_name'=>'UserTypeEntitiesBuilder',
            'prefix'=>'Version',
            'description'=>'Auto generated',
            'type_codes'=>[
                $arFields['ID']
            ],
            'step_code'=>'migration_create',
            'filter'=>'migration_view_all'
        ];

        (new Migrate())->MakeMigration($post);
    }
    public static function OnUFieldsUpdate($arFields, $ID){
        $post=[
            'builder_name'=>'UserTypeEntitiesBuilder',
            'prefix'=>'Version',
            'description'=>'Auto generated',
            'type_codes'=>[
                $ID
            ],
            'step_code'=>'migration_create',
            'filter'=>'migration_view_all'
        ];
        (new Migrate())->MakeMigration($post);
    }
    public static function OnUFieldsDelete($arFields){
        return false;
        (new Migrate())->MakeMigration($arFields);
    }
    public static function Events(){
        $t=new Migrate();
        if($t->isActive()){
            AddEventHandler('main', 'OnAfterUserTypeAdd', ['Migrate', 'OnUFieldsAdd']);
            AddEventHandler('main', 'OnAfterUserTypeUpdate', ['Migrate', 'OnUFieldsUpdate']);
           // AddEventHandler('main', 'OnBeforeUserTypeDelete', ['Migrate', 'OnUFieldsDelete']);
            $eventManager = \Bitrix\Main\EventManager::getInstance();
            $eventManager->addEventHandler('highloadblock', 'HighloadBlockOnAfterAdd', ['Migrate', 'OnTableAdd']);
        }
    }
}

?>