<#1>
<?php
if (!$ilDB->tableExists("rep_robj_xoce_data")) {
    $fields = [
        'id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
        ],
        'is_online' => [
            'type' => 'integer',
            'length' => 1,
            'notnull' => false,
        ],
        'event_id' => [
            'type' => 'text',
            'length' => 64,
            'fixed' => false,
            'notnull' => false,
        ],
        'new_tab' => [
            'type' => 'integer',
            'length' => 1,
            'notnull' => false,
        ],
        'maximize' => [
            'type' => 'integer',
            'length' => 1,
            'notnull' => false,
        ],
        'width' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => false,
        ],
        'height' => [
            'type' => 'integer',
            'length' => 4,
            'notnull' => false,
        ],
    ];
    $ilDB->createTable("rep_robj_xoce_data", $fields);
    $ilDB->addPrimaryKey("rep_robj_xoce_data", ["id"]);
}
?>
<#2>
<?php
// introducing configuration table.
if (!$ilDB->tableExists("rep_robj_xoce_config")) {
    $fields = [
        'name' => [
            'type' => 'text',
            'length' => 250,
            'notnull' => true,
        ],
        'value' => [
            'type' => 'text',
            'length' => 4000,
            'notnull' => false,
        ],
    ];
    $ilDB->createTable("rep_robj_xoce_config", $fields);
    $ilDB->addPrimaryKey("rep_robj_xoce_config", ["name"]);
}
?>
