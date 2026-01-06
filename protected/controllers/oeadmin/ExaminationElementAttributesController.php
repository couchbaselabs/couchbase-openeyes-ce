<?php

use OEModule\OphCiExamination\models\OphCiExamination_Attribute;
use OEModule\OphCiExamination\models\OphCiExamination_AttributeElement;
use OEModule\OphCiExamination\models\OphCiExamination_AttributeOption;

/**
 * Created by PhpStorm.
 * User: himanshu
 * Date: 12/05/15
 * Time: 15:02.
 */
class ExaminationElementAttributesController extends BaseAdminController
{
    /**
     * @var string
     */
    public $layout = 'admin';

    /**
     * @var int
     */
    public $itemsPerPage = 100;

    public $group = 'Examination';


    public function accessRules()
    {
        return array(array('allow', 'roles' => array('admin', 'OprnInstitutionAdmin')));
    }

    /**
     * @throws CHttpException
     */
    public function actionList()
    {
        $admin = new Admin(OphCiExamination_Attribute::model(), $this);

        $admin->setListFields(array(
            'display_order',
            'name',
            'label',
            'attribute_elements.id',
            'attribute_element_types.name',
            'is_multiselect',
        ));

        $institution_id = Yii::app()->request->getQuery('institution_id', '');

        if ($institution_id) {
            $institution = Institution::model()->findByPk($institution_id);
        } else {
            $institution = null;
        }

        $criteria = new CDbCriteria();
        $criteria->order = 't.display_order asc';

        $admin->getSearch()->setCriteria(
            OphCiExamination_Attribute::model()->getCriteriaForLevels(
                $institution ? ReferenceData::LEVEL_INSTITUTION : ReferenceData::LEVEL_INSTALLATION,
                $criteria,
                $institution,
            )
        );
        $admin->setModelDisplayName('Element Attributes');
        $admin->div_wrapper_class = 'cols-8';
        $admin->has_global_institution_option  = true;
        $admin->getSearch()->setItemsPerPage($this->itemsPerPage);

        $admin->setListTemplate('//admin/generic/listInstitution');
        $admin->listModel();
    }
    /**
     * Edits or adds a Procedure.
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEdit($id = false)
    {
        $admin = new Admin(OphCiExamination_Attribute::model(), $this);
        $admin->setCustomSaveURL('/oeadmin/ExaminationElementAttributes/update');
        if ($id) {
            $admin->setModelId($id);
        } else {
            $institution_id = Yii::app()->request->getQuery('institution_id', null);
            $model = $admin->getModel();

            $model->institution_id = $institution_id;

            $admin->setModel($model);
        }
        $admin->setModelDisplayName('Element Attributes');

        $admin->setEditFields(array(

            'name' => 'text',
            'label' => 'text',
            'attribute_elements' => array(
                'widget' => 'DropDownList',
                'options' => CHtml::listData(ElementType::model()->findAll(), 'id', 'name'),
                'htmlOptions' => ['class' => 'cols-8'],
                'hidden' => false,
                'layoutColumns' => null,
            ),
            'institution' => array(
                'widget' => 'DropDownList',
                'options' => Institution::model()->getTenantedList(false, true),
                'htmlOptions' => ['class' => 'cols-8', 'empty' => 'None'],
                'hidden' => false,
                'layoutColumns' => null,
            ),
            'is_multiselect' => 'checkbox',
        ));

        $admin->editModel();
    }

    /**
     * @throws Exception
     */
    public function actionUpdate()
    {
        // Handle GET requests by redirecting to edit page
        if (!Yii::app()->request->isPostRequest) {
            $id = Yii::app()->request->getParam('id');
            if ($id) {
                $this->redirect(array('edit', 'id' => $id));
            } else {
                $this->redirect(array('list'));
            }
            return;
        }

        $newOCEA = new OphCiExamination_Attribute();
        $newOCEAE = new OphCiExamination_AttributeElement();

        $post = Yii::app()->request->getPost('OEModule_OphCiExamination_models_OphCiExamination_Attribute');
        
        // Check if POST data is null
        if (!$post) {
                        Yii::app()->end();
        }

        $attributeId = isset($post['id']) ? $post['id'] : null;
        $attributeName = isset($post['name']) ? $post['name'] : null;
        $attributeLabel = isset($post['label']) ? $post['label'] : null;
        $attributeElements = isset($post['attribute_elements']) ? $post['attribute_elements'] : null;
        $attributeInstitution = isset($post['institution']) && $post['institution'] !== '' ? $post['institution'] : null;
        $attributeIsMultiSelect = isset($post['is_multiselect']) ? $post['is_multiselect'] : 0;

        if (!isset($attributeElements)) {
            $newOCEA->name = $attributeName;
            $newOCEA->label = $attributeLabel;
            $newOCEA->institution_id = $attributeInstitution;
            $newOCEA->is_multiselect = $attributeIsMultiSelect;

            if ($newOCEA->save()) {
                $this->redirect(array('list'));
                Yii::app()->end();
            } else {
                                print_r($newOCEA->getErrors(), true);
            }
        } else {
            //Add New
            if ($attributeId ==  '') {
                $newOCEA->name = $attributeName;
                $newOCEA->label = $attributeLabel;
                $newOCEA->institution_id = $attributeInstitution;
                $newOCEA->is_multiselect = $attributeIsMultiSelect;

                if ($newOCEA->save()) {
                    $newAttributeId = Yii::app()->cbdb->getLastInsertID();

                    $newOCEAE->attribute_id = $newAttributeId;
                    $newOCEAE->element_type_id = $attributeElements;

                    if ($newOCEAE->save()) {
                        $this->redirect(array('list'));
                        Yii::app()->end();
                    } else {
                                                print_r($newOCEA->getErrors(), true);
                    }
                } else {
                                        print_r($newOCEA->getErrors(), true);
                }
            } else {
                //Edit
                $attribute = OphCiExamination_Attribute::model()->findByPk($attributeId);
                $attribute->name = $attributeName;
                $attribute->label = $attributeLabel;
                $attribute->institution_id = $attributeInstitution;
                $attribute->is_multiselect = $attributeIsMultiSelect;


                if ($attribute->save()) {
                    $element = OphCiExamination_AttributeElement::model()->findByAttributes(array('attribute_id' => $attributeId));
                    if (is_object($element)) {
                        $element->element_type_id = $attributeElements;
                        $element->save();
                    }

                    $this->redirect(array('list'));
                    Yii::app()->end();
                } else {
                                        print_r($newOCEA->getErrors(), true);
                }
            }
        }
    }

    protected function isAttributeElementDeletable(OphCiExamination_AttributeElement $element)
    {
        return !OphCiExamination_AttributeOption::model()->exists(
            'attribute_element_id = :id',
            [':id' => $element->id]
        );
    }

    protected function isAttributeDeletable(OphCiExamination_Attribute $attribute)
    {
        return !OphCiExamination_AttributeElement::model()->exists(
            'attribute_id = :id',
            [':id' => $attribute->id]
        );
    }

    /**
     * Deletes rows for the model.
     */
    public function actionDelete()
    {
        $post = Yii::app()->request->getPost(OphCiExamination_Attribute::class);

        // Check if post data exists and has 'id' key
        if (!$post || !array_key_exists('id', $post) || !is_array($post['id'])) {
            echo 0;
            return;
        }

        $attributeIdsArray = $post['id'];
        $response = 1;

        foreach ($attributeIdsArray as $key => $attributeId) {
            $element = OphCiExamination_AttributeElement::model()->findByAttributes(array('attribute_id' => $attributeId));

            if (Yii::app()->request->getPost('DELETE_SUBS_ALSO')) {
                $this->deleteAttributeElements($element);
            }

            if ($element && $this->isAttributeElementDeletable($element)) {
                if (!$element->delete()) {
                    $response = 0;
                }
            } else {
                // Cannot delete; Attribute Element is in use
                $response = 0;
            }

            $attribute = OphCiExamination_Attribute::model()->findByAttributes(array('id' => $attributeId));
            if ($attribute && $this->isAttributeDeletable($attribute)) {
                if (!$attribute->delete()) {
                    $response = 0;
                }
            } else {
                // Cannot delete; Attribute is in use
                $response = 0;
            }
        }

        echo $response;
    }

    public function actionSearch()
    {
        if (Yii::app()->request->isAjaxRequest) {
            $criteria = new CDbCriteria();
            $params = array();
            if (isset($_GET['term']) && strlen($_GET['term']) > 0) {
                $criteria->addCondition(
                    array('LOWER(name) LIKE :term'),
                    'OR'
                );
                $params[':term'] = '%' . strtolower(strtr($_GET['term'], array('%' => '\%'))) . '%';
            }

            $criteria->order = 'name';
            $criteria->addCondition('active = 1');
            $criteria->select = 'id, name';
            $criteria->params = $params;

            $results = OphCiExamination_Attribute::model()->findAllAtLevels(ReferenceData::LEVEL_ALL, $criteria);

            $return = array();
            foreach ($results as $resultRow) {
                $return[] = array(
                    'label' => $resultRow->name,
                    'value' => $resultRow->name,
                    'id' => $resultRow->id,
                );
            }
            $this->renderJSON($return);
        } else {
            // Handle non-AJAX requests - display list like actionList()
            $admin = new Admin(OphCiExamination_Attribute::model(), $this);

            $admin->setListFields(array(
                'display_order',
                'name',
                'label',
                'attribute_elements.id',
                'attribute_element_types.name',
                'is_multiselect',
            ));

            $institution_id = Yii::app()->request->getQuery('institution_id', '');

            if ($institution_id) {
                $institution = Institution::model()->findByPk($institution_id);
            } else {
                $institution = null;
            }

            $criteria = new CDbCriteria();
            $criteria->order = 't.display_order asc';

            $admin->getSearch()->setCriteria(
                OphCiExamination_Attribute::model()->getCriteriaForLevels(
                    $institution ? ReferenceData::LEVEL_INSTITUTION : ReferenceData::LEVEL_INSTALLATION,
                    $criteria,
                    $institution,
                )
            );
            $admin->setModelDisplayName('Element Attributes');
            $admin->div_wrapper_class = 'cols-8';
            $admin->has_global_institution_option  = true;
            $admin->getSearch()->setItemsPerPage($this->itemsPerPage);

            $admin->setListTemplate('//admin/generic/listInstitution');
            $admin->listModel();
        }
    }

    /**
     * Save ordering of the objects.
     */
    public function actionSort()
    {
        $admin = new Admin(OphCiExamination_Attribute::model(), $this);

        $admin->setListFields(array(
            'display_order',
            'name',
            'label',
            'attribute_elements.id',
            'attribute_element_types.name',
            'is_multiselect',
        ));

        $institution_id = Yii::app()->request->getQuery('institution_id', '');

        if ($institution_id) {
            $institution = Institution::model()->findByPk($institution_id);
        } else {
            $institution = null;
        }

        $criteria = new CDbCriteria();
        $criteria->order = 't.display_order asc';

        $admin->getSearch()->setCriteria(
            OphCiExamination_Attribute::model()->getCriteriaForLevels(
                $institution ? ReferenceData::LEVEL_INSTITUTION : ReferenceData::LEVEL_INSTALLATION,
                $criteria,
                $institution,
            )
        );
        $admin->setModelDisplayName('Element Attributes');
        $admin->div_wrapper_class = 'cols-8';
        $admin->has_global_institution_option  = true;
        $admin->getSearch()->setItemsPerPage($this->itemsPerPage);

        $admin->setListTemplate('//admin/generic/listInstitution');
        
        if (Yii::app()->request->isPostRequest) {
            $admin->sortModel();
        } else {
            $admin->listModel();
        }
    }

    public function deleteAttributeElements(OphCiExamination_AttributeElement $element)
    {
        OphCiExamination_AttributeOption::model()->deleteAll('attribute_element_id = :id', [':id' => $element->id]);
    }
}
