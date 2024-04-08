<?php

namespace MGModule\RealtimeRegisterSsl\mgLibs\whmcsAPI;
use MGModule\RealtimeRegisterSsl as main;

class WhmcsAPI{
    
    static function getAdmin(){
          static $username;
          
          if(empty($username))
          {
                $data = main\mgLibs\MySQL\Query::select(
                                array('username')
                                , 'tbladmins'
                                , array()
                                , array()
                                , 1
                        )->fetch();
                $username = $data['username'];
          }
          
          return $username;
    }


    static function request($command,$config){
        $result = localAPI($command,$config,self::getAdmin());
        
        if($result['result'] == 'error')
        {
            throw new main\mgLibs\exceptions\WhmcsAPI($result['message']);
        }
        
        return $result;
    }
    
    static function getAdminDetails($adminId){
        
        $data = main\mgLibs\MySQL\Query::select(
                                array('username')
                                , 'tbladmins'
                                , array("id" =>$adminId )
                                , array()
                                , 1
                        )->fetch();
        $username = $data['username'];
        
        $result = localAPI("getadmindetails",array(),$username);
        if($result['result'] == 'error')
            throw new main\mgLibs\exceptions\WhmcsAPI($result['message']);
            
        $result['allowedpermissions'] = explode(",", $result['allowedpermissions']);
        return  $result;
    }
}
