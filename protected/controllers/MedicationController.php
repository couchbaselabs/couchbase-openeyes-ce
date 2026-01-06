<?php

/**
 * (C) OpenEyes Foundation, 2014
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2014, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
class MedicationController extends BaseController
{
    public function accessRules()
    {
        return array(
            array('allow', 'roles' => array('OprnEditMedication')),
        );
    }

    /**
     * Lists medications.
     * Patient medications are displayed within the patient context,
     * so this action is typically not called directly.
     */
    public function actionList()
    {
        // This action exists to handle the /medication/list route.
        // However, patient medications are typically displayed within a patient record context.
        // If this is called directly, we render an empty or informational view.
        $this->render('list', array('patient' => null, 'current' => true));
    }

    /**
     * @param int $patientId
     * @param int $medicationId
     */
    public function actionForm($patientId, $medicationId = null, $prescriptionItemId = null)
    {
        if ($medicationId == 'adherence') {
            $this->renderPartial(
                'adherence_form',
                array(
                    'patient' => $this->fetchModel('Patient', $patientId),
                ),
                false,
                true
            );
        } else {
            if ($medicationId) {
                $medication = $this->fetchModel('ArchiveMedication', $medicationId);
            } elseif ($prescriptionItemId) {
                if ($api = Yii::app()->moduleAPI->get('OphDrPrescription')) {
                    $medication = $api->getMedicationForPrescriptionItem($patientId, $prescriptionItemId);
                    if (!$medication) {
                        throw new CHttpException(404, 'Could not get medication for prescription item.');
                    }
                } else {
                    throw new CHttpException(400, 'Missing prescription item or module');
                }
            } else {
                $medication = new ArchiveMedication();
            }

            $this->renderPartial(
                'form',
                array(
                    'patient' => $this->fetchModel('Patient', $patientId),
                    'medication' => $medication,
                    'firm' => Firm::model()->findByPk($this->selectedFirmId),
                ),
                false,
                true
            );
        }
    }

    /**
     * Searches across Medication models for the given term. If the term only matches
     * on an alternative term from medication_search_index, the alternative term will be included 
     * in the returned label for that entry.
     *
     * Uses both preferred_term/short_term and alternative_term (via medication_search_index) for search.
     */
    public function actionFindDrug()
    {
        $return = array();

        if (isset($_GET['term']) && $term = strtolower($_GET['term'])) {
            // Search for medications by preferred_term or short_term
            $criteria = new CDbCriteria();
            $criteria->compare('LOWER(t.preferred_term)', $term, true, 'OR');
            $criteria->compare('LOWER(t.short_term)', $term, true, 'OR');
            
            $medications = Medication::model()->findAll($criteria);
            $found_ids = array();
            
            foreach ($medications as $med) {
                $found_ids[] = $med->id;
                $label = $med->preferred_term;
                // Check if this is a match on short_term
                if (strpos(strtolower($med->preferred_term), $term) === false && !empty($med->short_term)) {
                    $label .= ' (' . $med->short_term . ')';
                }
                $return[] = array(
                    'name' => $med->preferred_term,
                    'label' => $label,
                    'value' => $label,
                    'id' => $med->id,
                    'type' => 'medication',
                );
            }
            
            // Also search in medication_search_index for alternative terms
            $search_criteria = new CDbCriteria();
            $search_criteria->compare('LOWER(alternative_term)', $term);
            $search_results = MedicationSearchIndex::model()->findAll($search_criteria);
            
            foreach ($search_results as $search_result) {
                // Only add if we haven't already added this medication
                if (!in_array($search_result->medication_id, $found_ids)) {
                    $med = $search_result->medication;
                    if ($med && !$med->deleted_date) {
                        $found_ids[] = $med->id;
                        $label = $med->preferred_term . ' (' . $search_result->alternative_term . ')';
                        $return[] = array(
                            'name' => $med->preferred_term,
                            'label' => $label,
                            'value' => $label,
                            'id' => $med->id,
                            'type' => 'medication',
                        );
                    }
                }
            }
        }

        $this->renderJSON($return);
    }

    public function actionDrugDefaults($drug_id = null)
    {
        if ($drug_id && strpos($drug_id, '@@M') === false) {
            $this->renderJSON($this->fetchModel('Drug', $drug_id)->getDefaults());
        } else {
            $this->renderJSON([]);
        }
    }

    public function actionDrugRouteOptions($id = null)
    {
        if (!$id) {
            throw new CHttpException(400, 'Route ID is required');
        }
        $this->renderPartial(
            'route_option',
            array(
                'medication' => new ArchiveMedication(),
                'route' => $this->fetchModel('MedicationRoute', $id),
            )
        );
    }

    public function actionRetrieveDrugRouteOptions($id = null)
    {
        if (!$id) {
            $this->renderJSON([]);
            return;
        }
        $route = MedicationRoute::model()->findByPk($id);
        if ($route && $route->has_laterality) {
            // Fetch laterality options from database rather than hardcoding
            $lateralities = MedicationLaterality::model()->findAll(
                ['condition' => 'deleted_date IS NULL', 'order' => 'id']
            );
            $options = [];
            foreach ($lateralities as $laterality) {
                $options[] = ['id' => $laterality->id, 'name' => $laterality->name];
            }
            $this->renderJSON($options);
        } else {
            $this->renderJSON([]);
        }
    }

    public function actionSave()
    {
        if (!isset($_POST['patient_id']) || empty($_POST['patient_id'])) {
            throw new CHttpException(400, 'patient_id parameter is required');
        }

        if (@$_POST['MedicationAdherence']) {
            $patient = $this->fetchModel('Patient', @$_POST['patient_id']);

            $medication_adherence = MedicationAdherence::model()->find(
                'patient_id=:patient_id',
                array(':patient_id' => $patient->id)
            );
            if (!$medication_adherence) {
                $medication_adherence = new MedicationAdherence();
                $medication_adherence->patient_id = $patient->id;
            }
            $medication_adherence->medication_adherence_level_id = $_POST['MedicationAdherence']['level'];
            $medication_adherence->comments = $_POST['MedicationAdherence']['comments'];

            if ($medication_adherence->save()) {
                $this->renderPartial('lists', array('patient' => $patient));
            } else {
                header('HTTP/1.1 422');
                $this->renderJSON($medication_adherence->errors);
            }
        } else {
            $patient = $this->fetchModel('Patient', @$_POST['patient_id']);
            $medication = $this->fetchModel('ArchiveMedication', @$_POST['medication_id']);

            $medication->patient_id = $patient->id;

            if (!@$_POST['dose']) {
                $_POST['dose'] = null;
            }
            if (!@$_POST['end_date']) {
                $_POST['end_date'] = null;
            }

            $post_data = $_POST;

            if (!empty($post_data['drug_id']) && strpos($post_data['drug_id'], '@@M') !== false) {
                $post_data['drug_id'] = null;
                $medication_data = explode('@@M', $_POST['drug_id']);
                $post_data['medication_drug_id'] = $medication_data[0];
            }

            $medication->attributes = $post_data;

            if ($medication->save()) {
                $this->renderPartial('lists', array('patient' => $patient));
            } else {
                header('HTTP/1.1 422');
                $this->renderJSON($medication->errors);
            }
        }
    }

    public function actionStop()
    {
        if (!isset($_POST['patient_id']) || empty($_POST['patient_id'])) {
            throw new CHttpException(400, 'patient_id parameter is required');
        }
        if (!isset($_POST['medication_id']) || empty($_POST['medication_id'])) {
            throw new CHttpException(400, 'medication_id parameter is required');
        }

        $patient = $this->fetchModel('Patient', @$_POST['patient_id']);
        $medication = $this->fetchModel('ArchiveMedication', @$_POST['medication_id']);

        if ($patient->id != $medication->patient_id) {
            throw new CHttpException(400, 'Patient ID mismatch');
        }

        $medication->end_date = @$_POST['end_date'];
        $medication->stop_reason_id = @$_POST['stop_reason_id'] ?: null;
        $medication->save();

        $this->renderPartial('lists', array('patient' => $patient));
    }

    public function actionDelete()
    {
        // Try to get medication_id from GET or POST
        $medicationId = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['medication_id']) ? $_POST['medication_id'] : null);
        $patientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : (isset($_POST['patient_id']) ? $_POST['patient_id'] : null);

        if (!$medicationId) {
            throw new CHttpException(400, 'medication_id parameter is required');
        }

        if (!$patientId) {
            throw new CHttpException(400, 'patient_id parameter is required');
        }

        $patient = $this->fetchModel('Patient', $patientId);
        $medication = $this->fetchModel('ArchiveMedication', $medicationId);

        if ($patient->id != $medication->patient_id) {
            throw new CHttpException(400, 'Patient ID mismatch');
        }

        $medication->delete();

        // If it's an AJAX request, render partial; otherwise render full page
        if (Yii::app()->request->isAjaxRequest) {
            $this->renderPartial('lists', array('patient' => $patient));
        } else {
            // Redirect to medication list or render confirmation
            $this->render('list', array('patient' => $patient));
        }
    }
}
