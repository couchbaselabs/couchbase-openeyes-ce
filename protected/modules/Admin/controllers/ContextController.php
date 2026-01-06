<?php

class ContextController extends BaseAdminController
{
    // public $defaultAction = 'firms';

    public function actionIndex()
    {
        Audit::add('admin-Firm', 'list');
        $criteria = new \CDbCriteria();
        $search = [];
        $search['query'] = \Yii::app()->request->getQuery('query');
        $search['active'] = \Yii::app()->request->getQuery('active');
        $search['institution_id'] = \Yii::app()->request->getQuery('institution_id');
        if (isset($search['query'])) {
            if (is_numeric($search['query'])) {
                $criteria->addCondition('id = :id');
                $criteria->params[':id'] = $search['query'];
            } else {
                $criteria->addSearchCondition('pas_code', $search['query'], true, 'OR');
                $criteria->addSearchCondition('cost_code', $search['query'], true, 'OR');
                $criteria->addSearchCondition('name', $search['query'], true, 'OR');
            }
        }
        if (isset($search['active'])) {
            if ((int)$search['active'] === 1) {
                $criteria->addCondition('active = 1');
            } elseif ($search['active'] !== '') {
                $criteria->addCondition('active != 1');
            }
        } else {
            $search['active'] = 1;
            $criteria->addCondition('active = 1');
        }

        $current_institution = Yii::app()->session->getSelectedInstitution();

        if (!$this->checkAccess('admin')) {
            $search['institution_id'] = isset($current_institution) ? $current_institution->id : null;
        }

        $reference_level = $search['institution_id'] ? ReferenceData::LEVEL_INSTITUTION : ReferenceData::LEVEL_INSTALLATION;

        // eliminate uncessary DB query if search institution is the same as current institution
        if (isset($current_institution) && $current_institution->id == $search['institution_id']) {
            $institution = $current_institution;
        } else {
            if (isset($search['institution_id'])) {
                $institution = Institution::model()->findByPk($search['institution_id']);
            } else {
                $institution = null;
            }
        }

        $pagination_criteria = Firm::model()->getCriteriaForLevels($reference_level, $criteria, $institution);
        $pagination = $this->initPagination(
            Firm::model(),
            $pagination_criteria
        );

        $this->render('index', array(
            'pagination' => $pagination,
            'firms' => Firm::model()->findAllAtLevels($reference_level, $pagination_criteria, $institution),
            'search' => $search,
            'pagination_criteria' => $pagination_criteria
        ));
    }

    /**
     * @throws Exception
     */
    public function actionAdd()
    {
        $firm = new Firm();

        if (!empty($_POST)) {
            $firm->attributes = $_POST['Firm'];

            if (!$this->checkAccess('admin')) {
                $firm->institution_id = Yii::app()->session['selected_institution_id'];
            }

            if (!$firm->validate()) {
                $errors = $firm->getErrors();
            } else {
                // Ensure ServiceSubspecialtyAssignment exists if subspecialty is selected
                if ($firm->subspecialty_id && !$firm->service_subspecialty_assignment_id) {
                    $ssa = ServiceSubspecialtyAssignment::model()->find('subspecialty_id=?', array($firm->subspecialty_id));
                    if (!$ssa) {
                        // Create a default service if none exist
                        $service = Service::model()->find();
                        if (!$service) {
                            $service = new Service();
                            $service->name = 'Default Service';
                            // Use reflection to disable Couchbase sync
                            try {
                                $reflection = new ReflectionClass($service);
                                $property = $reflection->getProperty('_couchbaseSyncDisabled');
                                $property->setAccessible(true);
                                $property->setValue($service, true);
                            } catch (Exception $e) {
                                // Ignore reflection errors, just proceed
                            }
                            if (!$service->save()) {
                                throw new Exception('Unable to create default service: ' . print_r($service->getErrors(), true));
                            }
                        }
                        
                        // Check if the assignment already exists for this service+subspecialty
                        $existing = ServiceSubspecialtyAssignment::model()->find('service_id=? AND subspecialty_id=?', array($service->id, $firm->subspecialty_id));
                        if (!$existing) {
                            // Create the ServiceSubspecialtyAssignment
                            $ssa = new ServiceSubspecialtyAssignment();
                            $ssa->service_id = $service->id;
                            $ssa->subspecialty_id = $firm->subspecialty_id;
                            // Use reflection to disable Couchbase sync
                            try {
                                $reflection = new ReflectionClass($ssa);
                                $property = $reflection->getProperty('_couchbaseSyncDisabled');
                                $property->setAccessible(true);
                                $property->setValue($ssa, true);
                            } catch (Exception $e) {
                                // Ignore reflection errors, just proceed
                            }
                            if (!$ssa->save()) {
                                throw new Exception('Unable to create ServiceSubspecialtyAssignment: ' . print_r($ssa->getErrors(), true));
                            }
                        } else {
                            $ssa = $existing;
                        }
                    }
                    $firm->service_subspecialty_assignment_id = $ssa->id;
                }
                
                if (!$firm->save()) {
                    throw new Exception('Unable to save firm: ' . print_r($firm->getErrors(), true));
                }
                Audit::add('admin-Firm', 'add', $firm->id);
                $this->redirect('/Admin/context/' . ceil($firm->id / $this->items_per_page));
            }
        }

        $this->render('edit', array(
            'firm' => $firm,
            'errors' => @$errors,
            'subspecialties_list_data' => CHtml::listData(Subspecialty::model()->findAll(['order' => 'name']), 'id', 'name'),
            'consultant_list_data' => CHtml::listData(User::model()->findAll(['order' => 'first_name,last_name']), 'id', 'fullName'),
        ));
    }

    /**
     * @throws Exception
     */
    public function actionEdit($id)
    {
        $firm = Firm::model()->findByPk($id);
        if (!$firm) {
            throw new Exception("Firm not found: $id");
        }

        $firm->subspecialty_id = $firm->getSubspecialtyID();

        if (!empty($_POST)) {
            $firm->attributes = $_POST['Firm'];
            if (!$firm->validate()) {
                $errors = $firm->getErrors();
            } else {
                if (!$firm->save()) {
                    throw new Exception('Unable to save firm: ' . print_r($firm->getErrors(), true));
                }
                Audit::add('admin-Firm', 'edit', $firm->id);
                $this->redirect('/Admin/context/' . ceil($firm->id / $this->items_per_page));
            }
        } else {
            Audit::add('admin-Firm', 'view', $id);
        }

        $siteSecretaries = array();
        if (isset(Yii::app()->modules['OphCoCorrespondence'])) {
            $firmSiteSecretaries = new FirmSiteSecretary();
            $site_secretaries = $firmSiteSecretaries->findSiteSecretaryForFirm($id);
            $firmSiteSecretaries->firm_id = $id;
            $siteSecretaries[] = $firmSiteSecretaries;
        }

        $this->render('edit', array(
            'firm' => $firm,
            'errors' => @$errors,
            'siteSecretaries' => $site_secretaries,
            'subspecialties_list_data' => CHtml::listData(Subspecialty::model()->findAll(['order' => 'name']), 'id', 'name'),
            'consultant_list_data' => CHtml::listData(User::model()->findAll(['order' => 'first_name,last_name']), 'id', 'fullName'),
            'newSiteSecretary' => new FirmSiteSecretary(),
        ));
    }
}
