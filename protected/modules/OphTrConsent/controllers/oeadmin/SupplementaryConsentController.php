<?php

class SupplementaryConsentController extends BaseAdminController
{

    public $group = 'Consent form';

    /**
     * Lists Ophtrconsent_SupplementaryConsentQuestions.
     *
     * @throws CHttpException
     */
    public function actionList()
    {
        $search = \Yii::app()->request->getPost('search', ['query' => '', 'active' => '']);
        $query_lowercase = strtolower($search['query']);

        // Get ALL questions first
        $suppleConsent = Ophtrconsent_SupplementaryConsentQuestion::model();
        $allQuestions = $suppleConsent->findAll();

        // Apply filtering
        $filteredQuestions = [];
        foreach ($allQuestions as $question) {
            $include = true;
            
            // If there's a search query
            if (!empty($query_lowercase)) {
                $foundInQuestion = false;
                
                // Search in question name/description
                if (stripos($question->name, $query_lowercase) !== false ||
                    stripos($question->description, $query_lowercase) !== false) {
                    $foundInQuestion = true;
                }
                
                // Search in question assignments and answers
                if (!$foundInQuestion) {
                    foreach ($question->question_assignment as $qa) {
                        if (stripos($qa->question_text, $query_lowercase) !== false ||
                            stripos($qa->question_info, $query_lowercase) !== false ||
                            stripos($qa->question_output, $query_lowercase) !== false) {
                            $foundInQuestion = true;
                            break;
                        }
                        // Check answers
                        foreach ($qa->answers as $answer) {
                            if (stripos($answer->name, $query_lowercase) !== false ||
                                stripos($answer->display, $query_lowercase) !== false ||
                                stripos($answer->answer_output, $query_lowercase) !== false) {
                                $foundInQuestion = true;
                                break 2;
                            }
                        }
                    }
                }
                
                $include = $foundInQuestion;
            }
            
            // Filter by active status
            if ($search['active'] !== '' && $include) {
                $hasActiveAssignment = false;
                foreach ($question->question_assignment as $qa) {
                    if ((int)$qa->active === (int)$search['active']) {
                        $hasActiveAssignment = true;
                        break;
                    }
                }
                $include = $hasActiveAssignment;
            }
            
            if ($include) {
                $filteredQuestions[] = $question;
            }
        }

        $this->render('/oeadmin/supplementaryconsent/index', [
            'pagination' => new CPagination(count($filteredQuestions)),
            'suppleConsent' => $filteredQuestions,
            'search' => $search,
        ]);
    }

    /**
     * Edits or adds a SupplementaryConsent.
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEditAnswer($id = false)
    {
        $errors = [];

        $q_assign = Ophtrconsent_SupplementaryConsentQuestionAnswer::model()->findByPk($id);


        if (!$q_assign) {
            $q_assign = new Ophtrconsent_SupplementaryConsentQuestionAnswer();

            $q_assign->question_assignment_id = \Yii::app()->request->getParam('question_assignment_id');

            $existing = Ophtrconsent_SupplementaryConsentQuestionAnswer::model()->findAll('question_assignment_id=?', array($q_assign->question_assignment_id));

            $max_order = array_reduce($existing, static function($order, $answer) {
                return max($order, $answer->display_order);
            }, 0);

            $q_assign->display_order = $max_order + 1;
        }

        if (Yii::app()->request->isPostRequest) {
            $assignment_data = \Yii::app()->request->getPost('Ophtrconsent_SupplementaryConsentQuestionAnswer');

            $q_assign->attributes = $assignment_data;

            // try saving the data
            if (!$q_assign->save()) {
                $errors = $q_assign->getErrors();
            } else {
                $this->redirect('/OphTrConsent/oeadmin/SupplementaryConsent/editAssignment?id=' . $q_assign->question_assignment_id);
            }
        }

        $this->render('/oeadmin/supplementaryconsent/editAnswer', array(
            'q_assign' => $q_assign,
            'errors' => $errors,
        ));
    }

    /**
     * Edits or adds a Ophtrconsent_SupplementaryConsentQuestionAssignment
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEditAssignment($id = false)
    {
        $errors = [];

        $q_assign = Ophtrconsent_SupplementaryConsentQuestionAssignment::model()->findByPk($id);

        if (!$q_assign) {
            $q_assign = new Ophtrconsent_SupplementaryConsentQuestionAssignment();

            $q_assign->question_id = \Yii::app()->request->getParam('question_id');
        }

        if (Yii::app()->request->isPostRequest) {
            $assignment_data = \Yii::app()->request->getPost('Ophtrconsent_SupplementaryConsentQuestionAssignment');

            $q_assign->attributes = $assignment_data;

            // try saving the data
            if (!$q_assign->save()) {
                $errors = $q_assign->getErrors();
            } else {
                $this->redirect('/OphTrConsent/oeadmin/SupplementaryConsent/edit?id=' . $q_assign->question_id);
            }
        }

        $this->render('/oeadmin/supplementaryconsent/editAssignment', array(
            'q_assign' => $q_assign,
            'errors' => $errors,
        ));
    }
    /**
     * Edits or adds a Ophtrconsent_SupplementaryConsentQuestion.
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEdit($id = false)
    {
        $errors = [];

        $suppleconsent = Ophtrconsent_SupplementaryConsentQuestion::model()->findByPk($id);


        if (!$suppleconsent) {
            $suppleconsent = new Ophtrconsent_SupplementaryConsentQuestion();
        }
        if (Yii::app()->request->isPostRequest) {
            $user_data = \Yii::app()->request->getPost('Ophtrconsent_SupplementaryConsentQuestion');

            $suppleconsent->attributes = $user_data;

            $suppleconsent->question_type_id = $user_data['question_type_id'];

            // try saving the data
            if (!$suppleconsent->save()) {
                $errors = $suppleconsent->getErrors();
            } else {
                $this->redirect('/OphTrConsent/oeadmin/SupplementaryConsent/list/');
            }
        }
        $this->render('/oeadmin/supplementaryconsent/edit', array(
            'suppleconsent' => $suppleconsent,
            'errors' => $errors,
        ));
    }

    public function getNameFromID($id, $modelName)
    {

        return $modelName::model()->findByAttributes(
            array(
                'id' => $id,
            )
        )->name;
    }
}
