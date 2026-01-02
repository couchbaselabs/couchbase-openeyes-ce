<?php

/**
 * File-based session with OpenEyes helpers.
 *
 * Provides selected firm/site/institution/user helpers without requiring DB-backed sessions.
 */
class OEFileSession extends CHttpSession
{
    protected $selected_firm;
    protected $selected_site;
    protected $selected_institution;
    protected $selected_user;

    public const SITE_ID_KEY = 'selected_site_id';

    public function getSelectedFirm()
    {
        if (!$this->selected_firm) {
            $firm_id = $this->get('selected_firm_id');
            if (!$firm_id) {
                return null;
            }

            $this->selected_firm = Firm::model()
                ->with('serviceSubspecialtyAssignment.subspecialty.specialty')
                ->findByPk($firm_id);

            if (!$this->selected_firm) {
                throw new Exception("Firm with id '$firm_id' not found");
            }
        }

        return $this->selected_firm;
    }

    public function getSelectedSite()
    {
        if (!$this->selected_site) {
            $site_id = $this->get(self::SITE_ID_KEY);
            if (!$site_id) {
                return null;
            }

            $this->selected_site = Site::model()->findByPk($site_id);
            if (!$this->selected_site) {
                throw new Exception("Site with id '$site_id' not found");
            }
        }

        return $this->selected_site;
    }

    public function getSelectedInstitution()
    {
        if (!$this->selected_institution) {
            $institution_id = $this->get('selected_institution_id');

            if (!$institution_id) {
                return null;
            }

            $this->selected_institution = Institution::model()->findByPk($institution_id);
            if (!$this->selected_institution) {
                throw new Exception("Institution with id '$institution_id' not found");
            }
        }

        return $this->selected_institution;
    }

    public function getSelectedUser()
    {
        if (!$this->selected_user) {
            $user_id = $this->get('user')->id ?? Yii::app()->user->id;

            if (empty($user_id)) {
                return null;
            }

            $this->selected_user = User::model()->findByPk($user_id);
            if (!$this->selected_user) {
                throw new Exception("User with id '$user_id' not found");
            }
        }

        return $this->selected_user;
    }
}
