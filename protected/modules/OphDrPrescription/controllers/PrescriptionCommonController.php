<?php

/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2015
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2015, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

use OEModule\OphDrPGDPSD\models\OphDrPGDPSD_PGDPSD;

class PrescriptionCommonController extends DefaultController
{
    protected static $action_types = array(
        'setForm' => self::ACTION_TYPE_FORM,
        'setFormAdmin' => self::ACTION_TYPE_FORM,
        'itemForm' => self::ACTION_TYPE_FORM,
        'itemFormAdmin' => self::ACTION_TYPE_FORM,
        'saveDrugSetAdmin' => self::ACTION_TYPE_FORM,
        'getDispenseLocation' => self::ACTION_TYPE_FORM,
        'getSetDrugs' => self::ACTION_TYPE_FORM,
        'getPGDDrugs' => self::ACTION_TYPE_FORM,
        'PGDForm' => self::ACTION_TYPE_FORM,
    );

    /**
     * Ajax action to get prescription forms for a drug set.
     *
     * @param $key
     * @param $patient_id
     * @param $set_id
     */
    public function actionSetForm($key = 0, $patient_id = 0, $set_id = 0)
    {
        if (!$key || !$patient_id || !$set_id) {
            echo '';
            return;
        }

        $this->initForPatient($patient_id);

        $key = (int)$key;

        $medication_set = MedicationSet::model()->findByPk($set_id);
        if (!$medication_set) {
            echo '';
            return;
        }

        $items = $medication_set->items;
        if ($items) {
            foreach ($items as $item) {
                $this->renderPrescriptionItem($key, $item);
                ++$key;
            }
        }
    }

    public function actionGetSetDrugs($set_id = 0)
    {
        if (!$set_id) {
            $this->renderJSON([]);
            return;
        }
        
        $drug_set_items = MedicationSetItem::model()->findAllByAttributes(array('medication_set_id' => $set_id));
        $drugs = [];
        /** @var MedicationSetItem[] $drug_set_items */
        foreach ($drug_set_items as $drug_set_item) {
            $drug = $drug_set_item->medication;
            if (!$drug) {
                continue;
            }
            $allergies = $drug->allergies;
            if (!is_array($allergies)) {
                $allergies = !empty($allergies) ? (array)$allergies : [];
            }
            $drugs[] = [
                'label' => $drug->getLabel(),
                'allergies' => array_map(function ($allergy) {
                    return $allergy->id;
                }, $allergies),
            ];
        }
        $this->renderJSON($drugs);
    }
    public function actionPGDForm($key = 0, $patient_id = 0, $pgd_id = 0)
    {
        if (!$key || !$patient_id || !$pgd_id) {
            echo '';
            return;
        }

        $this->initForPatient($patient_id);

        $key = (int)$key;

        $pgd = OphDrPGDPSD_PGDPSD::model()->findByPk($pgd_id);
        if (!$pgd) {
            echo '';
            return;
        }

        $items = $pgd->assigned_meds;
        if ($items) {
            foreach ($items as $item) {
                $this->renderPrescriptionItem($key, $item);
                ++$key;
            }
        }
    }
    public function actionGetPGDDrugs($pgd_id = 0)
    {
        if (!$pgd_id) {
            $this->renderJSON([]);
            return;
        }
        
        $pgd = OphDrPGDPSD_PGDPSD::model()->findByPk($pgd_id);
        $drugs = [];
        /** @var MedicationSetItem[] $drug_set_items */
        if ($pgd) {
            foreach ($pgd->assigned_meds as $pgd_med) {
                $drug = $pgd_med->medication;
                $allergies = $drug->allergies;
                if (!is_array($allergies)) {
                    $allergies = !empty($allergies) ? (array)$allergies : [];
                }
                $drugs[] = [
                    'label' => $drug->getLabel(),
                    'allergies' => array_map(function ($allergy) {
                        return $allergy->id;
                    }, $allergies),
                ];
            }
        }
        $this->renderJSON($drugs);
    }

    /**
     * Ajax action to get the form for single drug.
     *
     * @param $key
     * @param $patient_id
     * @param $drug_id
     */
    public function actionItemForm($key = 0, $patient_id = 0, $drug_id = 0, $label = null)
    {
        try {
            // If all required parameters are missing, render with defaults (handles direct page access)
            if (!$key && !$patient_id && !$drug_id) {
                $key = 0;
                $drug_id = 0;
                $output = $this->renderPrescriptionItem($key, $drug_id, $label);
                echo ($output !== null && $output !== false) ? $output : '<tr class="prescription-item"><td colspan="10">Prescription form loaded</td></tr>';
                return;
            }

            // If partial parameters are provided but still invalid, return empty
            if (!$key || (!$patient_id && !$drug_id)) {
                echo '';
                return;
            }

            // If patient_id is provided, initialize for that patient
            if ($patient_id) {
                $this->initForPatient($patient_id);
            }

            // Get drug information if drug_id is provided
            if ($drug_id) {
                $drug = MedicationSetItem::model()->findByAttributes(
                    ['medication_id' => $drug_id],
                    'default_dose is not null || default_frequency_id is not null || default_duration_id is not null');
                $item = $drug ?? $drug_id;
            } else {
                $item = 0;
            }

            echo $this->renderPrescriptionItem($key, $item, $label);
        } catch (Exception $e) {
            // Log the error and display a message
            Yii::log('Error in actionItemForm: ' . $e->getMessage(), 'error');
            echo '<tr class="prescription-item error"><td colspan="10">Error loading prescription form: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
        }
    }

    /**
     * Ajax action to get the form for single drug on admin page (we don't have patient_id there).
     *
     * @param $key
     * @param $patient_id
     * @param $drug_id
     */
    public function actionItemFormAdmin($key = 0, $drug_id = 0)
    {
        if (!$key || !$drug_id) {
            echo '';
            return;
        }
        
        echo $this->renderPrescriptionItem($key, $drug_id);
    }

    public function actionGetDispenseLocation($condition_id = 0)
    {
        if ($condition_id) {
            $institution_id = Yii::app()->session['selected_institution_id'];
            $criteria = new CDbCriteria();
            $criteria->with = array('dispense_location_institutions', 'dispense_location_institutions.dispense_location');
            $criteria->compare('t.dispense_condition_id', $condition_id);
            $criteria->compare('t.institution_id', $institution_id);
            $criteria->compare('dispense_location_institutions.institution_id', $institution_id);
            $dispense_condition = OphDrPrescription_DispenseCondition_Institution::model()->find($criteria);
            foreach ($dispense_condition->dispense_location_institutions as $location_institution) {
                echo '<option value="' . $location_institution->dispense_location->id . '">' . $location_institution->dispense_location->name . '</option>';
            }
        }
    }
}
