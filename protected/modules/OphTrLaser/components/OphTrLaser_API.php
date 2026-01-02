<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
class OphTrLaser_API extends BaseAPI
{
    /**
     * get laterality of event by looking at the treatment element eye side
     *
     * @param $event_id
     * @return mixed
     * @throws Exception
     */
    public function getLaterality($event_id)
    {
        // Couchbase-first lookup
        $useCouchbase = Yii::app()->params['enable_couchbase_read'] ?? false;
        if ($useCouchbase) {
            try {
                $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(\OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE);
                // Treatment data typically sits in clinical scope keyed by event_id
                $rows = $adapter->query(
                    "SELECT d.eye FROM `" . Yii::app()->couchbase->config['bucket'] . "`.`clinical`.`examination` d WHERE d.event_id = $event_id LIMIT 1",
                    ['event_id' => (int)$event_id]
                );
                if (!empty($rows[0]['eye'])) {
                    return $rows[0]['eye'];
                }
            } catch (\Exception $e) {
                Yii::log('Couchbase laser laterality lookup failed: ' . $e->getMessage(), \CLogger::LEVEL_WARNING, 'application.laser');
            }
        }

        // MariaDB fallback
        $laser_treatment = Element_OphTrLaser_Treatment::model()->find('event_id=?', array($event_id));
        if (!$laser_treatment) {
            return null;
        }
        return $laser_treatment->eye;
    }
}
