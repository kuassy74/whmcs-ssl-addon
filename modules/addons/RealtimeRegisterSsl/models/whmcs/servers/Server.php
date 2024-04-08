<?php

namespace MGModule\RealtimeRegisterSsl\models\whmcs\servers;
use MGModule\RealtimeRegisterSsl as main;


/**
 * Server Model
 * @Table(name=tblservers,preventUpdate,prefixed=false)
 * @author Michal Czech <michael@modulesgarden.com>
 */
class Server extends main\mgLibs\models\Orm {    
    /**
     * @Column()
     * @var int 
     */
    public $id;
    
    /**
     * @Column()
     * @var string 
     */
    public $hostname;
    
    /**
     * @Column(name=ipaddress)
     * @var string 
     */
    public $ip;
    
    /**
     * 
     * @Column()
     * @var string 
     */
    public $username;
    
    /**
     * 
     * @Column(as=passwordEncrypted)
     * @var string 
     */
    public $password;
    
    /**
     * 
     * @Column()
     * @var string 
     */
    public $accesshash;
    
    /**
     * @Column()
     * @var string 
     */
    public $secure;
    
    /**
     * @Column(notRequired)
     * @var string 
     */
    public $disabled;
    
    /** 
     * Load Server Data
     * 
     * @author Michal Czech <michael@modulesgarden.com>
     * @param int $id
     * @param array $data
     */
    function __construct($id = false,$data = array()) {        
        if($id !== false && empty($data))
        {            
            $data = main\mgLibs\MySQL\Query::select(
                    self::fieldDeclaration()
                    , self::tableName()
                    ,array(
                        'id'    => $id
                    )
            )->fetch();
            
            if(empty($data))
            {
                throw new main\mgLibs\exceptions\System('Unable to find Item with ID:'.$id);
            }
        }

        if(isset($data['passwordEncrypted']))
        {
            $data['password'] = decrypt($data['passwordEncrypted']);
        }
        
        if(!empty($data))
        {
            $this->fillProperties($data);
        }
    }
    
    
    function save(){
        parent::save(array(
            'password'  => encrypt($this->password)
        ));
    }
}
