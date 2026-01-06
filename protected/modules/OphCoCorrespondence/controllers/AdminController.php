<?php

/**
 * (C) Copyright Apperta Foundation 2022
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2022, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

class AdminController extends \ModuleAdminController
{
    public $group = 'Correspondence';

    public $defaultAction = 'letterMacros';

    public function actions()
    {
        return [
            'sortLetterMacros' => [
                'class' => 'SaveDisplayOrderAction',
                'model' => LetterMacro::model(),
                ],
        ];
    }

    public function actionLetterMacros()
    {
        $macros = $this->getMacros();

        Audit::add('admin', 'list', null, null, array('module' => 'OphCoCorrespondence', 'model' => 'LetterMacro'));

        $unique_names = CHtml::listData($macros, 'name', 'name');
        asort($unique_names);


        $assetManager = Yii::app()->getAssetManager();
        $assetManager->registerScriptFile('/js/oeadmin/OpenEyes.admin.js');
        $assetManager->registerScriptFile('/js/oeadmin/list.js');

        $institution_id = $this->request->getParam('institution_id')
                            ? $this->request->getParam('institution_id')
                            : Institution::model()->getCurrent()->id;

        $this->render('letter_macros', array(
            'macros' => $macros,
            'unique_names' => $unique_names,
            'episode_statuses' => $this->getUniqueEpisodeStatuses($macros),
            'default_institution_id' => $institution_id
        ));
    }


    public function actionLetterSettings()
    {
        $this->render('/admin/letter_settings', array(
            'settings' => OphCoCorrespondenceLetterSettings::model()->findAll(),
        ));
    }

    public function actionSenderEmailAddresses()
    {
        $criteria = new CDbCriteria();
        $criteria->addCondition('institution_id = :institution_id');
        $criteria->params[':institution_id'] = Institution::model()->getCurrent()->id;
        $this->render('/admin/sender_email_addresses', array(
            'addresses' => SenderEmailAddresses::model()->findAll($criteria),
        ));
    }

    public function actionEmailTemplates()
    {
        $criteria = new CDbCriteria();
        $criteria->addCondition('institution_id = :institution_id');
        $criteria->params[':institution_id'] = Institution::model()->getCurrent()->id;
        $criteria->order = 'id DESC';
        
        try {
            $templates = EmailTemplate::model()->findAll($criteria);
            Yii::log('actionEmailTemplates: institution_id = ' . $criteria->params[':institution_id'] . ', found ' . count($templates) . ' templates', CLogger::LEVEL_INFO);
        } catch (Exception $e) {
            Yii::log('Error in actionEmailTemplates: ' . $e->getMessage() . ' ' . $e->getTraceAsString(), CLogger::LEVEL_ERROR);
            $templates = array();
        }
        
        $this->render('/admin/email_templates', array(
            'templates' => $templates,
        ));
    }

    public function actionEditSetting()
    {
        if (!$metadata = OphCoCorrespondenceLetterSettings::model()->find('`key`=?', array(@$_GET['key']))) {
            $this->redirect(array('/OphCoCorrespondence/admin/letterSettings/settings'));
        }

        $institution_id = Institution::model()->getCurrent()->id;
        $errors = array();

        if (Yii::app()->request->isPostRequest) {
            foreach (OphCoCorrespondenceLetterSettings::model()->findAll() as $metadata) {
                if (@$_POST['hidden_' . $metadata->key] || @$_POST[$metadata->key]) {
                    if (!$setting = $metadata->getSetting($metadata->key, null, true)) {
                        $setting = new OphCoCorrespondenceLetterSettingValue();
                        $setting->key = $metadata->key;
                    }
                    $setting->value = @$_POST[$metadata->key];
                    if (!$setting->save()) {
                        $errors = $setting->errors;
                    } else {
                        $this->redirect(array('/OphCoCorrespondence/admin/letterSettings/settings'));
                    }
                }
            }
        }

        $this->render(
            '/admin/edit_setting',
            [
                'metadata' => $metadata,
                'errors' => $errors,
                'cancel_uri' => '/OphCoCorrespondence/admin/letterSettings/settings',
                'institution_id' => $institution_id,
            ]
        );
    }


    public function getUniqueEpisodeStatuses($macros)
    {
        $statuses = array();

        foreach ($macros as $macro) {
            if ($macro->episode_status_id && !isset($statuses[$macro->episode_status_id])) {
                try {
                    $episode_status = $macro->episode_status;
                    if ($episode_status && isset($episode_status->name)) {
                        $statuses[$macro->episode_status_id] = $episode_status->name;
                    }
                } catch (Exception $e) {
                    // Log the error but continue processing
                    \Yii::log("Error loading episode status for macro {$macro->id}: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
                }
            }
        }

        ksort($statuses);

        return $statuses;
    }

    public function actionFilterMacros()
    {
        // If not an AJAX request, render the full letterMacros page
        if (!Yii::app()->request->isAjaxRequest) {
            return $this->actionLetterMacros();
        }
        
        // For AJAX requests, return just the table rows
        $this->renderPartial('_macros', array('macros' => $this->getMacros()));
    }

    public function actionFilterMacroNames()
    {
        $macros = $this->getMacros(false);

        $unique_names = CHtml::listData($macros, 'name', 'name');
        asort($unique_names);

        $this->renderPartial('_macro_names', array('names' => $unique_names));
    }

    public function actionFilterEpisodeStatuses()
    {
        try {
            $macros = $this->getMacros(false);
            \Yii::log("actionFilterEpisodeStatuses: Found " . count($macros) . " macros", \CLogger::LEVEL_INFO);
            $statuses = $this->getUniqueEpisodeStatuses($macros);
            \Yii::log("actionFilterEpisodeStatuses: Found " . count($statuses) . " statuses", \CLogger::LEVEL_INFO);
            $this->renderPartial('_episode_statuses', array('statuses' => $statuses));
        } catch (Exception $e) {
            \Yii::log("Error in actionFilterEpisodeStatuses: " . $e->getMessage() . "\n" . $e->getTraceAsString(), \CLogger::LEVEL_ERROR);
            // Render with empty statuses on error
            $this->renderPartial('_episode_statuses', array('statuses' => array()));
        }
    }

    /**
     * @param bool $filter_name_and_episode_status
     * @return BaseActiveRecord[]|CActiveRecord|LetterMacro[]|null
     * @throws Exception
     */
    public function getMacros($filter_name_and_episode_status = true)
    {
        // For Couchbase compatibility, use direct N1QL queries instead of Yii AR with JOINs
        $couchbase = \Yii::app()->couchbase;
        if ($couchbase) {
            try {
                // Query all macros from reference scope
                $n1ql = "SELECT META(m).id AS _doc_key, m.* FROM `openeyes`.`reference`.`ophcocorrespondence_letter_macro` m ORDER BY m.display_order ASC, m.name ASC";
                
                $queryResult = $couchbase->query($n1ql, []);
                $rows = [];
                if ($queryResult instanceof \Couchbase\QueryResult) {
                    foreach ($queryResult->rows() as $row) {
                        $rows[] = (array)$row;
                    }
                } elseif (is_array($queryResult)) {
                    $rows = $queryResult;
                }
                
                \Yii::log("getMacros Couchbase query returned " . count($rows) . " rows", \CLogger::LEVEL_INFO);
                
                // If Couchbase returns results, use them
                if (!empty($rows)) {
                    // Hydrate models
                    $macros = [];
                    foreach ($rows as $row) {
                        $macro = new LetterMacro();
                        $macro->setScenario('search');
                        
                        // Set id from _mysql_id if available, otherwise use id
                        if (isset($row['_mysql_id']) && !empty($row['_mysql_id'])) {
                            $macro->id = $row['_mysql_id'];
                        } elseif (isset($row['id'])) {
                            $macro->id = $row['id'];
                        }
                        
                        // Set all safe attributes
                        $safeAttributes = ['name', 'recipient_id', 'use_nickname', 'body', 'cc_patient', 'cc_doctor', 
                                           'display_order', 'cc_optometrist', 'cc_drss', 'episode_status_id', 
                                           'letter_type_id', 'short_code', 'created_date', 'created_user_id',
                                           'last_modified_date', 'last_modified_user_id'];
                        foreach ($safeAttributes as $attr) {
                            if (isset($row[$attr])) {
                                $macro->$attr = $row[$attr];
                            }
                        }
                        
                        $macros[] = $macro;
                    }
                    
                    // Apply filters
                    if ($filter_name_and_episode_status) {
                        if (@$_GET['name']) {
                            $macros = array_filter($macros, function($m) {
                                return $m->name === $_GET['name'];
                            });
                        }
                        if (@$_GET['episode_status_id']) {
                            $macros = array_filter($macros, function($m) {
                                return $m->episode_status_id == $_GET['episode_status_id'];
                            });
                        }
                    }
                    
                    return array_values($macros);
                }
                // If Couchbase returns no results, fall through to standard approach
            } catch (\Exception $e) {
                \Yii::log("getMacros N1QL error: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
                // Fall through to standard approach
            }
        }
        
        // Fallback to standard criteria (may not work for Couchbase-only mode)
        $selected_institution = $_GET['institution_id'] ?? Institution::model()->getCurrent()->id;
        $macroIds = $this->getMacroIdsByInstitution($selected_institution);
        
        if (empty($macroIds)) {
            return [];
        }
        
        $criteria = new CDbCriteria();
        $criteria->addInCondition('t.id', $macroIds);

        // Apply additional filters on the macro attributes directly
        if ($filter_name_and_episode_status) {
            if (@$_GET['name']) {
                $criteria->addCondition('t.name = :name');
                $criteria->params[':name'] = $_GET['name'];
            }

            if (@$_GET['episode_status_id']) {
                $criteria->addCondition('t.episode_status_id = :esi');
                $criteria->params[':esi'] = $_GET['episode_status_id'];
            }
        }

        $criteria->order = 'display_order asc, t.name asc';

        $macros = LetterMacro::model()->findAll($criteria);
        
        // Post-filter by site, subspecialty, firm if needed
        // (these are less common filters, so post-filtering is acceptable)
        if (@$_GET['site_id'] || @$_GET['subspecialty_id'] || @$_GET['firm_id']) {
            $macros = array_filter($macros, function($macro) {
                if (@$_GET['site_id']) {
                    $siteIds = array_map(function($s) { return $s->id; }, $macro->sites);
                    if (!in_array($_GET['site_id'], $siteIds)) {
                        return false;
                    }
                }
                if (@$_GET['subspecialty_id']) {
                    $subspecialtyIds = array_map(function($s) { return $s->id; }, $macro->subspecialties);
                    if (!in_array($_GET['subspecialty_id'], $subspecialtyIds)) {
                        return false;
                    }
                }
                if (@$_GET['firm_id']) {
                    $firmIds = array_map(function($f) { return $f->id; }, $macro->firms);
                    if (!in_array($_GET['firm_id'], $firmIds)) {
                        return false;
                    }
                }
                return true;
            });
        }
        
        return array_values($macros);
    }
    
    /**
     * Get macro IDs that are associated with a specific institution
     * Uses direct N1QL query for Couchbase compatibility
     * @param int $institutionId
     * @return array
     */
    protected function getMacroIdsByInstitution($institutionId)
    {
        try {
            // Try Couchbase query first
            $couchbase = \Yii::app()->couchbase;
            if ($couchbase) {
                $scope = 'reference';
                
                // Debug: Log institution ID
                \Yii::log("getMacroIdsByInstitution: institutionId = " . var_export($institutionId, true), \CLogger::LEVEL_INFO);
                
                // Query junction table - try both string and numeric match
                $n1ql = "SELECT RAW letter_macro_id FROM `openeyes`.`{$scope}`.`ophcocorrespondence_letter_macro_institution` WHERE TOSTRING(institution_id) = TOSTRING(\$institutionId)";
                $queryResult = $couchbase->query($n1ql, ['institutionId' => (string)$institutionId]);
                
                // Convert QueryResult to array
                $results = [];
                if ($queryResult instanceof \Couchbase\QueryResult) {
                    foreach ($queryResult->rows() as $row) {
                        $results[] = $row;
                    }
                } elseif (is_array($queryResult)) {
                    $results = $queryResult;
                }
                
                if (!empty($results)) {
                    return $results;
                }
                
                // DEBUG: Force return all macro IDs for testing
                \Yii::log("getMacroIdsByInstitution: No junction results, returning all macros", \CLogger::LEVEL_INFO);
                
                // If no results from junction table, return all macro IDs (for backward compatibility)
                $n1ql = "SELECT RAW TOSTRING(IFMISSING(m._mysql_id, m.id)) FROM `openeyes`.`{$scope}`.`ophcocorrespondence_letter_macro` m";
                $queryResult = $couchbase->query($n1ql, []);
                
                $results = [];
                if ($queryResult instanceof \Couchbase\QueryResult) {
                    foreach ($queryResult->rows() as $row) {
                        $results[] = $row;
                    }
                } elseif (is_array($queryResult)) {
                    $results = $queryResult;
                }
                
                return $results ?: [];
            }
        } catch (\Exception $e) {
            \Yii::log("getMacroIdsByInstitution Couchbase error: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
        }
        
        // Fallback: return all macro IDs (let the model handle filtering)
        try {
            $macros = LetterMacro::model()->findAll();
            return array_map(function($m) { return $m->id; }, $macros);
        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * @throws Exception
     */
    public function actionAddMacro()
    {
        // if no institution id parameter passed, or an invalid one, default to current institution
        $institution = Institution::model()->findByAttributes(['id' => $this->request->getParam('institution_id')])
                                    ?? Institution::model()->getCurrent();

        $macro = new LetterMacro();
        $errors = $this->processPOST('create', $macro);

        $init_method = new OphcorrespondenceInitMethod();

        // Get institution ID properly - handle both object and array
        $institution_id = is_array($institution) ? $institution['id'] : $institution->id;

        $this->render('_macro', [
                                'macro' => $macro,
                                'init_method' => $init_method,
                                'associated_content' => array(),
                                'errors' => $errors,
                                'institution' => is_array($institution) ? $institution : ['id' => $institution->id, 'name' => $institution->name],
                                'site_options' => Site::model()->getListForInstitutionById($institution_id),
                                'default_sites' => null,
                                'firm_options' => Firm::model()->getListWithSpecialties($institution_id, true),
                                'default_firms' => null
                            ]);
    }

    public function actionEditMacro($id)
    {
        if (!$macro = LetterMacro::model()->findByPk($id)) {
            throw new Exception("LetterMacro not found: $id");
        }

        $init_method = new OphcorrespondenceInitMethod();

        $criteria = new \CDbCriteria();
        $criteria->addCondition('macro_id = ' . $id);
        $criteria->order = 'display_order asc';
        $associated_content_saved = MacroInitAssociatedContent::model()->findAll($criteria);

        $errors = $this->processPOST('update', $macro);

        // Get institutions
        // Only one institution is allowed so if there is more than one then select just the first.
        $institution = count($macro->institutions) > 0 ? $macro->institutions[0] : [Institution::model()->getCurrent()];

        // Get sites
        $siteOptions = [];
        foreach ($macro->sites as $siteOption) {
            $siteOptions[$siteOption['id']] = $siteOption['name'];
        }
        $siteOptions = $siteOptions + Site::model()->getListForInstitutionById($institution['id']);

        // Get firms
        $firmOptions = [];
        foreach ($macro->firms as $firmOption) {
            $firmOptions[$firmOption['id']] = $firmOption['name'];
        }
        $firmOptions = $firmOptions + Firm::model()->getListWithSpecialties($institution['id'], true);

        $this->render('_macro', [
                                'macro' => $macro,
                                'init_method' => $init_method,
                                'associated_content' => $associated_content_saved,
                                'errors' => $errors,
                                'institution' => $institution,
                                'site_options' => $siteOptions,
                                'default_sites' => null,
                                'firm_options' => $firmOptions,
                                'default_firms' => null
                            ]);
    }


    private function processPOST($mode, LetterMacro $macro)
    {
        $errors = array();
        if (!empty($_POST)) {
            $post = $_POST['LetterMacro'];
            $macro->attributes = $post;

            if (!$macro->validate()) {
                foreach ($macro->levels as $level => $referenceAttribute) {
                    if ($referenceAttribute && !is_array($referenceAttribute)) {
                        $referenceAttribute = [$referenceAttribute];
                    }
                    $macro->$level = $referenceAttribute;
                }
                $errors = $macro->errors;
            } else {
                if (!$macro->save()) {
                    throw new Exception('Unable to save macro: ' . print_r($macro->errors, true));
                }

                Audit::add('admin', $mode, $macro->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'LetterMacro'));

                $this->redirect('/OphCoCorrespondence/admin/letterMacros');
            }
        } else {
            Audit::add('admin', 'view', $macro->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'LetterMacro'));
        }
        return $errors;
    }

    public function actionDeleteLetterMacros()
    {
        if (!isset($_POST['id'])) {
            return null;
        }

        $transaction = Yii::app()->cbdb->beginTransaction();
        $result = true;

        try {
            //Make all the macro ids null that is equal to the macro id
            // that is being deleted in the document instance data table
            DocumentInstanceData::model()->updateAll(['macro_id' => null], 'macro_id IN (' . implode($_POST['id']) . ')');

            $criteria = new CDbCriteria();
            $criteria->addInCondition('id', $_POST['id']);

            $instances = LetterMacro::model()->findAll($criteria);

            foreach ($instances as $instance) {
                // Remove mappings for each letter macro to ensure its deletion
                $result = $result && $instance->deleteMappings(ReferenceData::LEVEL_INSTITUTION);
                $result = $result && $instance->deleteMappings(ReferenceData::LEVEL_SITE);
                $result = $result && $instance->deleteMappings(ReferenceData::LEVEL_SUBSPECIALTY);
                $result = $result && $instance->deleteMappings(ReferenceData::LEVEL_FIRM);

                $result = $result && $instance->delete();
            }
        } catch (Exception $e) {
            $result = false;
        }

        if ($result) {
            $transaction->commit();
        } else {
            $transaction->rollback();
        }

        echo $result ? '1' : '0';
    }

    /**
     * @throws Exception
     */
    public function actionDeleteEmailTemplates()
    {
        if (!isset($_POST['id'])) {
            return;
        }

        $criteria = new CDbCriteria();
        $criteria->addInCondition('id', @$_POST['id']);
        if (EmailTemplate::model()->deleteAll($criteria)) {
            echo '1';
        } else {
            echo '0';
        }
    }

    public function actionDeleteEmailAddresses()
    {
        if (!isset($_POST['id'])) {
            return;
        }

        $criteria = new CDbCriteria();
        $criteria->addInCondition('id', @$_POST['id']);
        if (SenderEmailAddresses::model()->deleteAll($criteria)) {
            echo '1';
        } else {
            echo '0';
        }
    }

    public function actionAddSiteSecretary($id = null)
    {
        $firmId = $id;
        $siteSecretaries = array();
        $errors = array();
        if ($firmId === null && isset(Yii::app()->session['selected_firm_id'])) {
            $firmId = Yii::app()->session['selected_firm_id'];
        }
        $errorList = array();
        if (Yii::app()->request->isPostRequest) {
            // Validate that a firm ID is available before processing
            if ($firmId === null || $firmId === '' || $firmId === 0) {
                $errorList[] = array('A firm must be selected to add site secretaries');
            } else {
                foreach ($_POST['FirmSiteSecretary'] as $i => $siteSecretaryPost) {
                    if (empty($siteSecretaryPost['site_id']) && empty($siteSecretaryPost['direct_line']) &&  empty($siteSecretaryPost['fax'])) {
                        //The entire row is empty, ignore it
                        $errorList[] = array('You must supply at least a Site and Direct Line');
                        continue;
                    }

                    //Are we updating an existing object
                    if ($siteSecretaryPost['id'] !== '') {
                        $siteSecretary = FirmSiteSecretary::model()->findByPk($siteSecretaryPost['id']);
                    } else {
                        $siteSecretary = new FirmSiteSecretary();
                    }
                    //Set to have posted attributes
                    $siteSecretary->attributes = $siteSecretaryPost;

                    if (!$siteSecretary->firm_id) {
                        $siteSecretary->firm_id = (int) $firmId;
                    }
                    if (!$siteSecretary->validate()) {
                        $errorList[] = $siteSecretary->getErrors();
                    } else {
                        if (!$siteSecretary->save()) {
                            throw new CHttpException(500, 'Unable to save Site Secretary: ' . $siteSecretary->site->name);
                        }
                    }
                    //Add to array so updated version can be rendered
                    $siteSecretaries[] = $siteSecretary;
                }
            }
        } else {
            //Find all of the contacts for the current firm
            $siteSecretary = new FirmSiteSecretary();
            $siteSecretaries = $siteSecretary->findSiteSecretaryForFirm($firmId);
        }
        //Add a blank one to the end of the form for adding
        $newSiteSecretary = new FirmSiteSecretary();
        $siteSecretaries[] = $newSiteSecretary;
        if (count($errorList)) {
            $errors = call_user_func_array('array_merge', $errorList);
        }

        $outputArray = array(
            'siteSecretaries' => $siteSecretaries,
            'newSiteSecretary' => $newSiteSecretary,
            'firmId' => $firmId,
            'errors' => $errors,
            'success' => (count($errors) === 0),
        );

        if (Yii::app()->request->isAjaxRequest) {
            if (!$outputArray['success']) {
                $outputArray['errors'] = iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator($outputArray['errors'])), false);
            }
            $this->renderJSON($outputArray);
        } else {
            $this->render('/admin/secretary/edit', $outputArray);
        }
    }

    /**
     * Deletes a site secretary.
     *
     * @throws CHttpException
     */
    public function actionDeleteSiteSecretary()
    {
        if (Yii::app()->request->isPostRequest) {
            if (!isset($_POST['id'])) {
                throw new CHttpException(400, 'Unable to delete Site Secretary: no ID provided');
            }
            $siteSecretary = FirmSiteSecretary::model()->findByPk($_POST['id']);
            if (!$siteSecretary) {
                throw new CHttpException(404, 'Unable to delete Site Secretary: Can not find Site Secretary');
            }
            $firmId = $siteSecretary->firm_id;
            $siteSecretary->delete();
            $this->redirect('/OphCoCorrespondence/admin/addSiteSecretary/' . $firmId);
        }
        throw new CHttpException(400, 'Invalid method for delete');
    }

    /*
     * Get init method's data by id
     */
    public function actionGetInitMethodDataById()
    {

        if (!Yii::app()->request->isAjaxRequest) {
            throw new CHttpException(400, 'This action only accepts AJAX requests');
        }

        if (!isset($_POST['id'])) {
            throw new CHttpException(400, 'No ID provided');
        }

        if (!$method = OphcorrespondenceInitMethod::model()->findByPk($_POST['id'])) {
            throw new Exception("Method not found: " . $_POST['id']);
        }

        $result = array(
            'success'       => 1,
            'description'   => $method->description,
            'short_code'    => $method->short_code
        );

        $this->renderJSON($result);
    }

    public function actionAddEmailAddress()
    {
        $senderEmailAddresses = new SenderEmailAddresses();

        $errors = array();

        if (!empty($_POST)) {
            $senderEmailAddresses->attributes = $_POST['SenderEmailAddresses'];
            $senderEmailAddresses->institution_id = Institution::model()->getCurrent()->id;

            if (!$senderEmailAddresses->validate()) {
                $errors = $senderEmailAddresses->errors;
            } else {
                if (isset($senderEmailAddresses->password) && !empty($senderEmailAddresses->password)) {
                    $encryptionDecryptionHelper = new EncryptionDecryptionHelper();
                    try {
                        $senderEmailAddresses->password = $encryptionDecryptionHelper->encryptData($senderEmailAddresses->password);
                    } catch (Exception $e) {
                        // If encryption fails, log the error but continue saving without encryption
                        Yii::log('Failed to encrypt sender email password: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
                    }
                }

                if (!$senderEmailAddresses->save()) {
                    throw new Exception('Unable to save Sender Email Address: ' . print_r($senderEmailAddresses->errors, true));
                }

                Audit::add('admin', 'create', $senderEmailAddresses->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'SenderEmailAddresses'));

                $this->redirect('/OphCoCorrespondence/admin/senderEmailAddresses');
            }
        } else {
            Audit::add('admin', 'view', $senderEmailAddresses->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'SenderEmailAddresses'));
        }

        $this->render('_email_addresses', array(
            'title' => 'Add',
            'senderEmailAddresses' => $senderEmailAddresses,
            'errors' => $errors,
        ));
    }

    public function actionEditEmailAddress($id)
    {
        $senderEmailAddresses = SenderEmailAddresses::model()->findByPk($id);

        $errors = array();
        // Store the original encrypted password
        $originalPassword = $senderEmailAddresses->password;

        if (!empty($_POST)) {
            $senderEmailAddresses->attributes = $_POST['SenderEmailAddresses'];

            if (!$senderEmailAddresses->validate()) {
                $errors = $senderEmailAddresses->errors;
            } else {
                // Only encrypt password if it has been changed (is different from the original encrypted value)
                // and is not empty
                if (!empty($senderEmailAddresses->password) && $senderEmailAddresses->password !== $originalPassword) {
                    $encryptionDecryptionHelper = new EncryptionDecryptionHelper();
                    try {
                        $senderEmailAddresses->password = $encryptionDecryptionHelper->encryptData($senderEmailAddresses->password);
                    } catch (Exception $e) {
                        // If encryption fails, log the error but continue saving without encryption
                        Yii::log('Failed to encrypt sender email password: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
                    }
                } else {
                    // If password was not changed, keep the original encrypted value
                    $senderEmailAddresses->password = $originalPassword;
                }

                if (!$senderEmailAddresses->save()) {
                    throw new Exception('Unable to save Sender Email Address: ' . print_r($senderEmailAddresses->errors, true));
                }

                Audit::add('admin', 'create', $senderEmailAddresses->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'SenderEmailAddresses'));

                $this->redirect('/OphCoCorrespondence/admin/senderEmailAddresses');
            }
        } else {
            Audit::add('admin', 'view', $senderEmailAddresses->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'SenderEmailAddresses'));
        }

        $this->render('_email_addresses', array(
            'title' => 'Edit',
            'senderEmailAddresses' => $senderEmailAddresses,
            'errors' => $errors,
        ));
    }

    public function actionAddEmailTemplate()
    {
        $template = new EmailTemplate();

        $errors = array();

        if (!empty($_POST)) {
            $template->attributes = $_POST['EmailTemplate'];
            $template->institution_id = Institution::model()->getCurrent()->id;

            if (!$template->validate()) {
                $errors = $template->errors;
            } else {
                if (!$template->save()) {
                    throw new Exception('Unable to save Email template: ' . print_r($template->errors, true));
                }

                Audit::add('admin', 'create', $template->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'EmailTemplate'));

                $this->redirect('/OphCoCorrespondence/admin/emailTemplates');
            }
        } else {
            Audit::add('admin', 'view', $template->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'EmailTemplate'));
        }

        $this->render('_email_template', array(
            'title' => 'Add',
            'template' => $template,
            'errors' => $errors,
        ));
    }

    public function actionEditEmailTemplate($id)
    {
        $template = EmailTemplate::model()->findByPk($id);

        $errors = array();

        if (!empty($_POST) && $template) {
            $template->attributes = $_POST['EmailTemplate'];

            if (!$template->validate()) {
                $errors = $template->errors;
            } else {
                if (!$template->save()) {
                    throw new Exception('Unable to save Email template: ' . print_r($template->errors, true));
                }

                Audit::add('admin', 'create', $template->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'EmailTemplate'));

                $this->redirect('/OphCoCorrespondence/admin/emailTemplates');
            }
        } else {
            Audit::add('admin', 'view', $template->id, null, array('module' => 'OphCoCorrespondence', 'model' => 'EmailTemplate'));
        }

        $this->render('_email_template', array(
            'title' => 'Edit',
            'template' => $template,
            'errors' => $errors,
        ));
    }

    public function actionGetEmailBody($recipient_type = '')
    {
        if ($recipient_type != '') {
            $email_body = \Yii::app()->cbdb->createCommand()
                ->select('email_body')
                ->from('ophcocorrespondence_default_recipient_email_templates')
                ->where('recipient_type=:recipient_type', array(':recipient_type' => $recipient_type))
                ->queryScalar();

            echo $email_body;
        }
    }
}
