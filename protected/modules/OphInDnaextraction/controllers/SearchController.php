<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
class SearchController extends BaseController
{
    public $layout = '//layouts/advanced_search';
    public $items_per_page = 30;

    public function accessRules()
    {
        return array(
            array('allow',
                'actions' => array('dnaExtractions'),
                'roles' => array('OprnSearchPedigree'),
            ), );
    }

    public function getUri($elements)
    {
        $uri = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);

        $request = $_REQUEST;

        if (isset($elements['sortby']) && $elements['sortby'] == @$request['sortby']) {
            $request['order'] = (@$request['order'] == 'desc') ? 'asc' : 'desc';
        } elseif (isset($request['sortby']) && isset($elements['sortby']) && $request['sortby'] != $elements['sortby']) {
            $request['order'] = 'asc';
        }

        $first = true;
        foreach (array_merge($request, $elements) as $key => $value) {
            $uri .= $first ? '?' : '&';
            $first = false;
            $uri .= "$key=$value";
        }

        return $uri;
    }

    private function initPagination($model, $criteria = null)
    {
        $criteria = is_null($criteria) ? new CDbCriteria() : $criteria;
        $itemsCount = $model->count($criteria);
        $pagination = new CPagination($itemsCount);
        $pagination->pageSize = $this->items_per_page;
        $pagination->applyLimit($criteria);

        return $pagination;
    }

    public function actionDnaExtractions()
    {
        if (empty($_GET)) {
            if (($data = YiiSession::get('genetics_dnaextraction_searchoptions'))) {
                $_GET = $data;
            }
            Audit::add('Genetics dnaextraction list', 'view');
        } else {
            Audit::add('Genetics dnaextraction list', 'search');
            YiiSession::set('genetics_dnaextraction_searchoptions', $_GET);
        }

        $pages = 1;
        $page = 1;
        $results = array();
        $total_items = 0;

        if (@$_GET['search']) {
            $page = 1;

            $count_command = $this->buildSearchCommand('count(DISTINCT(et_ophindnaextraction_dnaextraction.id)) as count');
            $total_items = $count_command->queryScalar();
            $pages = ceil($total_items / $this->items_per_page);

            if (@$_GET['page'] && $_GET['page'] >= 1 and $_GET['page'] <= $pages) {
                $page = $_GET['page'];
            }

            $search_command = $this->buildSearchCommand('DISTINCT(et_ophindnaextraction_dnaextraction.id) AS extraction_id,patient.id,patient.hos_num,event.id,contact.first_name,contact.last_name,contact.title,patient.gender,patient.dob,et_ophindnaextraction_dnaextraction.extracted_date,et_ophindnaextraction_dnaextraction.volume,et_ophindnaextraction_dnaextraction.comments', $page);

            $dir = @$_GET['order'] == 'desc' ? 'desc' : 'asc';

            switch (@$_GET['sortby']) {
                case 'extraction_id':
                    $order = "et_ophindnaextraction_dnaextraction.id $dir";
                    break;
                case 'hos_num':
                    $order = "hos_num $dir";
                    break;
                case 'patient_name':
                    $order = "last_name $dir, first_name $dir";
                    break;
                case 'extracted_date':
                    $order = "extracted_date $dir";
                    break;
                case 'volume':
                    $order = "volume $dir";
                    break;
                case 'comment':
                    $order = "comments $dir";
                    break;
                default:
                    $order = "last_name $dir, first_name $dir";
            }

            $search_command->order($order)
                ->offset(($page - 1) * $this->items_per_page)
                ->limit($this->items_per_page);

            $results = $search_command->queryAll();
        }

        $pagination = new CPagination($total_items);
        $pagination->setPageSize($this->items_per_page);

        $this->render('dnaExtractions', array(
            'results' => $results,
            'pagination' => $pagination,
            'page' => $page,
            'pages' => $pages,
        ));
    }

    private function buildSearchCommand($select, $page = null)
    {
        $extraction_id = @$_GET['extraction_id'];
        $date_from = @$_GET['date-from'];
        $date_to = @$_GET['date-to'];
        $volume = @$_GET['volume'];
        $comment = @$_GET['comment'];
        $first_name = @$_GET['first_name'];
        $last_name = @$_GET['last_name'];
        $hos_num = @$_GET['hos_num'];

        $command = Yii::app()->cbdb->createCommand()
            ->select($select)
            ->from('et_ophindnaextraction_dnaextraction')
            ->leftJoin('event', 'et_ophindnaextraction_dnaextraction.event_id = event.id')
            ->leftJoin('episode', 'event.episode_id = episode.id')
            ->leftJoin('patient', 'episode.patient_id = patient.id')
            ->leftJoin('contact', 'patient.contact_id = contact.id');

        if ($extraction_id) {
            $command->andWhere('et_ophindnaextraction_dnaextraction.id = :extraction_id', array(':extraction_id' => $extraction_id));
        }

        if ($date_from) {
            $command->andWhere('extracted_date >= :date_from', array(':date_from' => Helper::convertNHS2MySQL($date_from)));
        }

        if ($date_to) {
            $command->andWhere('extracted_date <= :date_to', array(':date_to' => Helper::convertNHS2MySQL($date_to)));
        }

        if ($volume) {
            $command->andWhere('volume = :volume', array(':volume' => $volume));
        }

        if ($comment) {
            $command->andWhere((array('like', 'et_ophindnaextraction_dnaextraction.comments', '%' . $comment . '%')));
        }

        if ($first_name) {
            $command->andWhere((array('like', 'LOWER(first_name)', '%' . strtolower($first_name) . '%')));
        }

        if ($last_name) {
            $command->andWhere((array('like', 'LOWER(last_name)', '%' . strtolower($last_name) . '%')));
        }

        if ($hos_num) {
            $command->andWhere('hos_num = :hos_num', array(':hos_num' => $hos_num));
        }

        return $command;
    }
}
