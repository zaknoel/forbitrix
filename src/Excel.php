<?
namespace Zaknoel\ForBitrix;
class Excel{
    public static $instance;
    public static function init(){
        if(!self::$instance instanceof self){
            self::$instance=new self();
        }
        return self::$instance;
    }
    var $excel=false;
    function __construct()
    {
        if($_REQUEST['excel']=='Y'){
            global $APPLICATION;
            $this->excel=true;
            $name="Отчет (".$APPLICATION->GetTitle().')';
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header("Content-Disposition: attachment; filename=".$name.".xls");
        }
    }
    function Button(){
            global $APPLICATION;
            ?>
            <a href="<?=$APPLICATION->GetCurPageParam('excel=Y', ['excel', 'z-ajax'])?>"
               style="float: right; " class="ml-2 btn waves-effect waves-light btn-instagram btn-sm mb-2"> <i class="fa fa-file-excel-o"></i> Скачать Excel</a>
            <?php
    }
    function Start(){
        if($this->excel){
            global $APPLICATION;
            $APPLICATION->RestartBuffer();
            ob_start();
        }
    }
    function End(){
        if($this->excel){
            $html=@ob_get_clean();
            print chr(255) . chr(254) . mb_convert_encoding($html, 'UTF-16LE', 'UTF-8');
            die();
        }
    }


}

?>