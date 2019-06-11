<?php
namespace Exceedone\Exment\Services\Installer;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\InitializeStatus;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\CustomTable;

/**
 * 
 */
class InstallService
{
    public static function index(){
        $status = static::getStatus();
        
        if(($response = static::redirect($status)) instanceof \Illuminate\Http\RedirectResponse){
            return $response;
        }

        $form = static::getForm($status);

        return $form->index();
    }

    public static function post(){
        $status = static::getStatus();

        if(($response = static::redirect($status)) instanceof \Illuminate\Http\RedirectResponse){
            return $response;
        }

        $form = static::getForm($status);

        return $form->post();
    }

    public static function getStatus(){
        if(is_null(env('APP_LOCALE'))){
            return InitializeStatus::LANG;
        }

        if(!\DB::canConnection()){
            return InitializeStatus::DATABASE;
        }

        if(is_null($envInitialize = env(Define::ENV_EXMENT_INITIALIZE))){
            return InitializeStatus::DATABASE;
        }
        
        if(!\Schema::hasTable(SystemTableName::SYSTEM) || CustomTable::count() == 0){
            return InitializeStatus::INSTALLING;
        }

        return InitializeStatus::INITIALIZE;
    }

    public static function redirect($status){
        $isInstallPath = collect(explode('/', request()->getRequestUri()))->last() == 'install';
        switch($status){
            case InitializeStatus::LANG;
                if(!$isInstallPath){
                    return redirect(admin_url('install'));
                }
                return new LangForm;
            case InitializeStatus::DATABASE;
                if(!$isInstallPath){
                    return redirect(admin_url('install'));
                }
                return new DatabaseForm;
            case InitializeStatus::INSTALLING;
                if(!$isInstallPath){
                    return redirect(admin_url('install'));
                }
                return new InstallingForm;
            case InitializeStatus::INITIALIZE;
                if($isInstallPath){
                    return redirect(admin_url('initialize'));
                }
                return new InitializeForm;
        }
    }

    public static function getForm($status){
        switch($status){
            case InitializeStatus::LANG;
                return new LangForm;
            case InitializeStatus::DATABASE;
                return new DatabaseForm;
            case InitializeStatus::INSTALLING;
                return new InstallingForm;
            case InitializeStatus::INITIALIZE;
                return new InitializeForm;
        }

        return new InitializeForm;
    }
}
