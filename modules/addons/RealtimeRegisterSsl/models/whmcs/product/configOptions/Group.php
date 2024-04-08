<?php

namespace MGModule\RealtimeRegisterSsl\models\whmcs\product\configOptions;
use MGModule\RealtimeRegisterSsl as main;

/**
 * Description of group
 * @Table(name=tblproductconfiggroups,preventUpdate,prefixed=false)
 * @author Michal Czech <michael@modulesgarden.com>
 */
class Group extends main\mgLibs\models\Orm{
    private $_relatedPID = array();

    private $_configOptions = array();
    
    /**
     * @Column()
     * @var int
     */
    public $id;
    
    /**
     *
     * @Column()
     * @var string
     */
    public $name;
    
    /**
     *
     * @Column()
     * @var string
     */
    public $description;
    
    function addPID($pid)
    {
        $this->_relatedPID[] = $pid;
    }
    
    function getRelatedPIDs(){
        if(empty($this->_relatedPID))
        {
            $result = main\mgLibs\MySQL\Query::select(array(
               'pid'
            )
            , 'tblproductconfiglinks'
            ,array(
                'gid'   => $this->id
            ));
            
            while($row = $result->fetch())
            {
                $this->_relatedPID[] = $row['pid'];
            }
        }
        
        return $this->_relatedPID;
    }
    
    function save() {
        parent::save();
               
        if($this->_relatedPID)
        {
            $result = main\mgLibs\MySQL\Query::select(array(
               'pid'
            )
            , 'tblproductconfiglinks'
            ,array(
                'gid'   => $this->id
            ));

            $exists = array();
            while($row = $result->fetch())
            {
                $exists[$row['pid']] = $row['pid'];
            }
            
            foreach($this->_relatedPID as $pid)
            {
                if(!isset($exists[$pid]))
                {
                    main\mgLibs\MySQL\Query::insert('tblproductconfiglinks', array(
                       'pid'    => $pid
                       ,'gid'   => $this->id
                    ));
                }
            }
        }
    }
    
    function getConfigOptions(){
        if(empty($this->_configOptions))
        {
            $this->_configOptions = array();
            $result = main\mgLibs\MySQL\Query::select(
                configOption::fieldDeclaration()
                , configOption::tableName()
                ,array(
                    'gid'   => $this->id
            ));

            while($row = $result->fetch())
            {
                $this->_configOptions[] = new configOption($row['id'],$row);
            }
        }
        
        return $this->_configOptions;
    }
    
}
