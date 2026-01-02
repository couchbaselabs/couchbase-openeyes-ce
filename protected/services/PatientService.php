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

namespace services;

use OE\Reports\CouchbasePatientSearch;

class PatientService extends DatabaseAgnosticService
{
    protected static $operations = array(self::OP_READ, self::OP_UPDATE, self::OP_CREATE, self::OP_SEARCH);

    protected static $search_params = array(
        'id' => self::TYPE_TOKEN,
        'identifier' => self::TYPE_TOKEN,
        'family' => self::TYPE_STRING,
        'given' => self::TYPE_STRING,
    );

    protected static $primary_model = 'Patient';
    protected $collection = 'patient';

    /**
     * Read patient by ID (Couchbase-enabled)
     * @param string $id Patient ID
     * @return array|null Patient data or null
     */
    public function readPatient($id)
    {
        if ($this->enforceCouchbaseOnly()) {
            $result = $this->readFromCouchbase($id);
            if ($result === null) {
                \Yii::log('Couchbase-only patient read requested but document not found; skipping MariaDB fallback', \CLogger::LEVEL_WARNING, 'application.services');
            }
            return $result;
        }

        // Prefer Couchbase, fall back to MariaDB if missing or on error
        try {
            $result = $this->readFromCouchbase($id);
            if ($result !== null) {
                return $result;
            }
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase operation failed, falling back to MariaDB: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.services'
            );
        }

        return $this->readFromMariaDB($id);
    }

    /**
     * Read patient from Couchbase
     */
    private function readFromCouchbase($id)
    {
        if (!$this->canUsePatientDocument()) {
            return null;
        }
        $doc = \PatientDocument::findByPk($id);
        return $doc ? $doc->getAttributes() : null;
    }

    /**
     * Read patient from MariaDB
     */
    private function readFromMariaDB($id)
    {
        $patient = \Patient::model()->findByPk($id);
        return $this->normalizeResult($patient);
    }

    /**
     * Find patient by hospital number
     * @param string $hosNum Hospital number
     * @return array|null Patient data or null
     */
    public function findByHosNum($hosNum)
    {
        $couchbaseOp = function() use ($hosNum) {
            if (!$this->canUsePatientDocument()) {
                return null;
            }
            $doc = \PatientDocument::findByHosNum($hosNum);
            return $doc ? $doc->getAttributes() : null;
        };

        if ($this->enforceCouchbaseOnly()) {
            $result = $couchbaseOp();
            if ($result === null) {
                \Yii::log('Couchbase-only patient lookup by hos_num and document not found; skipping MariaDB fallback', \CLogger::LEVEL_WARNING, 'application.services');
            }
            return $result;
        }

        try {
            $result = $couchbaseOp();
            if ($result !== null) {
                return $result;
            }
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase operation failed, falling back to MariaDB: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.services'
            );
        }

        $patient = \Patient::model()->findByAttributes(['hos_num' => $hosNum]);
        return $this->normalizeResult($patient);
    }

    /**
     * Find patient by NHS number
     * @param string $nhsNum NHS number
     * @return array|null Patient data or null
     */
    public function findByNhsNum($nhsNum)
    {
        $couchbaseOp = function() use ($nhsNum) {
            if (!$this->canUsePatientDocument()) {
                return null;
            }
            $doc = \PatientDocument::findByNhsNum($nhsNum);
            return $doc ? $doc->getAttributes() : null;
        };

        if ($this->enforceCouchbaseOnly()) {
            $result = $couchbaseOp();
            if ($result === null) {
                \Yii::log('Couchbase-only patient lookup by nhs_num and document not found; skipping MariaDB fallback', \CLogger::LEVEL_WARNING, 'application.services');
            }
            return $result;
        }

        try {
            $result = $couchbaseOp();
            if ($result !== null) {
                return $result;
            }
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase operation failed, falling back to MariaDB: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.services'
            );
        }

        $patient = \Patient::model()->findByAttributes(['nhs_num' => $nhsNum]);
        return $this->normalizeResult($patient);
    }

    /**
     * Whether to enforce Couchbase-only patient reads (no MariaDB fallback).
     * @return bool
     */
    private function enforceCouchbaseOnly(): bool
    {
        $require = \Yii::app()->params['require_couchbase_patient_reads'] ?? false;
        return $require && $this->useCouchbase && $this->canUsePatientDocument();
    }

    /**
     * Ensure the Couchbase PatientDocument class is available without triggering fatal autoload errors.
     * @return bool
     */
    private function canUsePatientDocument(): bool
    {
        if (class_exists('PatientDocument', false)) {
            return true;
        }
        try {
            \Yii::import('application.models.couchbase.PatientDocument');
        } catch (\Exception $e) {
            return false;
        }
        return class_exists('PatientDocument', false);
    }

    /**
     * Get patient's episodes
     * @param string $patientId Patient ID
     * @return array Episodes
     */
    public function getEpisodes($patientId)
    {
        return $this->executeWithFallback(
            function() use ($patientId) {
                if (!class_exists('EpisodeDocument')) {
                    return [];
                }
                $episodes = \EpisodeDocument::findByPatientId($patientId);
                return array_map(function($ep) { return $ep->getAttributes(); }, $episodes);
            },
            function() use ($patientId) {
                $episodes = \Episode::model()->findAllByAttributes(
                    ['patient_id' => $patientId],
                    ['order' => 'start_date DESC']
                );
                return $this->normalizeResults($episodes);
            }
        );
    }

    /**
     * Get patient's events
     * @param string $patientId Patient ID
     * @param int $limit Maximum events to return
     * @return array Events
     */
    public function getEvents($patientId, $limit = 50)
    {
        return $this->executeWithFallback(
            function() use ($patientId, $limit) {
                if (!class_exists('EventDocument')) {
                    return [];
                }
                $events = \EventDocument::findByPatientId($patientId, $limit);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($patientId, $limit) {
                $criteria = new \CDbCriteria();
                $criteria->with = ['episode'];
                $criteria->addCondition('episode.patient_id = :patientId');
                $criteria->params[':patientId'] = $patientId;
                $criteria->order = 't.event_date DESC';
                $criteria->limit = $limit;
                
                $events = \Event::model()->findAll($criteria);
                return $this->normalizeResults($events);
            }
        );
    }

    public function search(array $params)
    {
        $model = $this->getSearchModel();
        if (isset($params['id'])) {
            $model->id = $params['id'];
        }

        if (isset($params['identifier'])) {
            if (strpos($params['identifier'], '|') !== false) {
                list($namespace, $identifier) = explode('|', $params['identifier'], 2);

                switch ($namespace) {
                    case \Yii::app()->params['fhir_system_uris']['hos_num']:
                        $model->hos_num = sprintf('%07s', $identifier);
                        break;
                    case \Yii::app()->params['fhir_system_uris']['nhs_num']:
                        $model->nhs_num = $identifier;
                        break;
                    default:
                        return array();
                }
            } else {
                $model->hos_num = sprintf('%07s', $params['identifier']);
                $model->nhs_num = $params['identifier'];
            }
        }

        $searchParams = array(
            'pageSize' => 5,
            'first_name' => null,
            'last_name' => null,
            'dob' => null,
            'sortBy' => 'last_name',
        );
        if (isset($params['family'])) {
            $searchParams['last_name'] = $params['family'];
        }
        if (isset($params['given'])) {
            $searchParams['first_name'] = $params['given'];
        }

        return $this->getResourcesFromDataProvider($model->search($searchParams));
    }

    public function modelToResource($patient)
    {
        $res = parent::modelToResource($patient);

        $hasAttr = method_exists($patient, 'hasAttribute');
        $res->nhs_num = ($hasAttr && $patient->hasAttribute('nhs_num')) ? $patient->nhs_num : null;
        $res->hos_num = ($hasAttr && $patient->hasAttribute('hos_num')) ? $patient->hos_num : null;
        $res->gender = ($hasAttr && $patient->hasAttribute('gender')) ? $patient->gender : null;
        $res->birth_date = ($hasAttr && $patient->hasAttribute('dob')) ? $patient->dob : null;
        $res->date_of_death = ($hasAttr && $patient->hasAttribute('date_of_death')) ? $patient->date_of_death : null;

        $contact = $patient->contact ?? null;
        $res->title = $contact->title ?? null;
        $res->family_name = $contact->last_name ?? null;
        $res->given_name = $contact->first_name ?? null;
        $res->primary_phone = $contact->primary_phone ?? null;
        $res->addresses = $contact && isset($contact->addresses)
            ? array_map(array('services\PatientAddress', 'fromModel'), $contact->addresses)
            : [];

        if ($patient->gp_id) {
            $res->gp_ref = new InternalReference('Gp', $patient->gp_id);
        }
        if ($patient->practice_id) {
            $res->prac_ref = new InternalReference('Practice', $patient->practice_id);
        }
        foreach ($patient->commissioningbodies as $cb) {
            $res->cb_refs[] = new InternalReference('CommissioningBody', $cb->id);
        }
        $res->care_providers = array_merge(array_filter(array($res->gp_ref, $res->prac_ref)), $res->cb_refs);

        return $res;
    }

    public function resourceToModel($res, $patient)
    {
        if (method_exists($patient, 'hasAttribute')) {
            if ($patient->hasAttribute('nhs_num')) {
                $patient->nhs_num = $res->nhs_num;
            }
            if ($patient->hasAttribute('hos_num')) {
                $patient->hos_num = $res->hos_num;
            }
            if ($patient->hasAttribute('gender')) {
                $patient->gender = $res->gender;
            }
            if ($patient->hasAttribute('dob')) {
                $patient->dob = $res->birth_date;
            }
            if ($patient->hasAttribute('date_of_death')) {
                $patient->date_of_death = $res->date_of_death;
            }
        }
        $patient->gp_id = $res->gp_ref ? $res->gp_ref->getId() : null;
        $patient->practice_id = $res->prac_ref ? $res->prac_ref->getId() : null;
        $this->saveModel($patient);

        $contact = $patient->contact;
        if ($contact) {
            if (property_exists($contact, 'title')) {
                $contact->title = $res->title;
            }
            if (property_exists($contact, 'last_name')) {
                $contact->last_name = $res->family_name;
            }
            if (property_exists($contact, 'first_name')) {
                $contact->first_name = $res->given_name;
            }
            if (property_exists($contact, 'primary_phone')) {
                $contact->primary_phone = $res->primary_phone;
            }
            $this->saveModel($contact);
        }

        $cur_addrs = array();
        if ($contact && isset($contact->addresses)) {
            foreach ($contact->addresses as $addr) {
                $cur_addrs[$addr->id] = PatientAddress::fromModel($addr);
            }
        }

        $add_addrs = array();
        $matched_ids = array();

        foreach ($res->addresses as $new_addr) {
            $found = false;
            foreach ($cur_addrs as $id => $cur_addr) {
                if ($cur_addr->isEqual($new_addr)) {
                    $matched_ids[] = $id;
                    $found = true;
                    unset($cur_addrs[$id]);
                    break;
                }
            }
            if (!$found) {
                $add_addrs[] = $new_addr;
            }
        }

        if ($contact) {
            $crit = new \CDbCriteria();
            $crit->compare('contact_id', $contact->id)->addNotInCondition('id', $matched_ids);
            \Address::model()->deleteAll($crit);

            foreach ($add_addrs as $add_addr) {
                $addr = new \Address();
                $addr->contact_id = $contact->id;
                $add_addr->toModel($addr);
                $this->saveModel($addr);
            }
        }

        $cur_cb_ids = array();
        foreach ($patient->commissioningbodies as $cb) {
            $cur_cb_ids[] = $cb->id;
        }

        $new_cb_ids = array();
        foreach ($res->cb_refs as $cb_ref) {
            $new_cb_ids[] = $cb_ref->getId();
        };

        $add_cb_ids = array_diff($new_cb_ids, $cur_cb_ids);
        $del_cb_ids = array_diff($cur_cb_ids, $new_cb_ids);

        foreach ($add_cb_ids as $cb_id) {
            $cba = new \CommissioningBodyPatientAssignment();
            $cba->commissioning_body_id = $cb_id;
            $cba->patient_id = $patient->id;
            $this->saveModel($cba);
        }

        if ($del_cb_ids) {
            $crit = new \CDbCriteria();
            $crit->compare('patient_id', $patient->id)->addInCondition('commissioning_body_id', $del_cb_ids);
            \CommissioningBodyPatientAssignment::model()->deleteAll($crit);
        }
    }
}
