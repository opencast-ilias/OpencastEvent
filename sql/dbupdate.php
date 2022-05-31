<#1>
<?php
if (!$ilDB->tableExists("rep_robj_xoce_data")) {
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true
        ),
        'is_online' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => false
        ),
        'event_id' => array(
            'type' => 'text',
            'length' => 64,
            'fixed' => false,
            'notnull' => false
        ),
        'new_tab' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => false
        ),
        'maximize' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => false
        ),
        'width' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => false
        ),
        'height' => array(
            'type' => 'integer',
            'length' => 4,
            'notnull' => false
        )
    );
    $ilDB->createTable("rep_robj_xoce_data", $fields);
    $ilDB->addPrimaryKey("rep_robj_xoce_data", array("id"));
}
?>