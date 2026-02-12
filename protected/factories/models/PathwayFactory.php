<?php
/**
 * (C) Apperta Foundation, 2024
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2024, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

namespace OE\factories\models;

use OE\factories\ModelFactory;
use Pathway;
use WorklistPatient;

class PathwayFactory extends ModelFactory
{
    /**
     * @return array
     */
    public function definition(): array
    {
        return [
            'worklist_patient_id' => WorklistPatient::factory(),
            'status' => Pathway::STATUS_ACTIVE,
            'start_time' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param WorklistPatient|WorklistPatientFactory|string|int $worklistPatient
     * @return PathwayFactory
     */
    public function forWorklistPatient($worklistPatient): self
    {
        return $this->state([
            'worklist_patient_id' => $worklistPatient
        ]);
    }

    /**
     * @param int $status
     * @return PathwayFactory
     */
    public function withStatus(int $status): self
    {
        return $this->state([
            'status' => $status
        ]);
    }

    /**
     * Active/Arrived status
     * @return PathwayFactory
     */
    public function active(): self
    {
        return $this->withStatus(Pathway::STATUS_ACTIVE);
    }

    /**
     * Checked-out/Discharged status
     * @return PathwayFactory
     */
    public function discharged(): self
    {
        return $this->withStatus(Pathway::STATUS_DISCHARGED);
    }

    /**
     * Completed/Done status
     * @return PathwayFactory
     */
    public function completed(): self
    {
        return $this->withStatus(Pathway::STATUS_DONE);
    }
}
