<?php

namespace MGModule\RealtimeRegisterSsl\models\testGroup\simpleItem;
use MGModule\RealtimeRegisterSsl as main;

/**
 * Example Item Class
 * 
 * @Table(name=simple_item)
 * @author Michal Czech <michael@modulesgarden.com>
 */
class SimpleItem extends main\mgLibs\models\Orm{
    /**
     * ID field
     * 
     * @Column(id)
     * @var int 
     */
    public $id;
    
    /**
     *
     * @Column(varchar)
     * @var string
     */
    public $name;
    
    /**
     *
     * @var main\models\testGroup\testItem\TestItem
     */
    private $_testItem;
    
    /**
     *
     * @Column(int,refrence=models\testGroup\testItem\TestItem::id)
     * @var int 
     */
    public $testItemID;
    
    public function gettestItem(){
        if(empty($this->_testItem))
        {
            $this->_testItem = new main\models\testGroup\testItem\TestItem($this->testItemID);
        }
        
        return $this->_testItem;
    }
    
    public function settestItem(main\models\testGroup\testItem\TestItem $item)
    {
        $this->_testItem = $item;
        $this->testItemID = $item->id;
    }
}
