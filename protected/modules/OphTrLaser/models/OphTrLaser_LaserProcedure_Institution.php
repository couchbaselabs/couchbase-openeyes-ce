<?php

use OE\Models\Traits\CouchbaseModelBridge;

class OphTrLaser_LaserProcedure_Institution extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public static function model($class_name = __CLASS__)
    {
        return parent::model($class_name);
    }

    public function tableName()
    {
        return 'ophtrlaser_laserprocedure_institution';
    }

    public function rules()
    {
        return [
            ['id, laserprocedure_id, institution_id', 'safe', 'on' => 'search'],
        ];
    }

    public function relations()
    {
        return [
            'laserprocedure' => [self::BELONGS_TO, 'OphTrLaser_LaserProcedure', 'laserprocedure_id'],
            'institution' => [self::BELONGS_TO, 'Institution', 'institution_id'],
        ];
    }

    public function couchbaseScope()
    {
        return 'laser';
    }

    public function couchbaseCollection()
    {
        return 'ophtrlaser_laserprocedure_institution';
    }
}
