<?php

namespace app\controllers\admin;

use app\controllers\AbstractController;
use app\libraries\FileUtils;
use app\libraries\ForumUtils;
use app\libraries\response\JsonResponse;
use app\libraries\routers\AccessControl;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ConfigurationController
 * @package app\controllers\admin
 * @AccessControl(role="INSTRUCTOR")
 */
class ConfigurationController extends AbstractController {

    /**
     * @deprecated
     */
    public function run() {
        return null;
    }

    /**
     * @Route("/{_semester}/{_course}/config")
     * @return Response
     */
    public function viewConfiguration()
    {
        $fields = array(
            'course_name'                    => $this->core->getConfig()->getCourseName(),
            'course_home_url'                => $this->core->getConfig()->getCourseHomeUrl(),
            'default_hw_late_days'           => $this->core->getConfig()->getDefaultHwLateDays(),
            'default_student_late_days'      => $this->core->getConfig()->getDefaultStudentLateDays(),
            'zero_rubric_grades'             => $this->core->getConfig()->shouldZeroRubricGrades(),
            'upload_message'                 => $this->core->getConfig()->getUploadMessage(),
            'keep_previous_files'            => $this->core->getConfig()->keepPreviousFiles(),
            'display_rainbow_grades_summary' => $this->core->getConfig()->displayRainbowGradesSummary(),
            'display_custom_message'         => $this->core->getConfig()->displayCustomMessage(),
            'course_email'                   => $this->core->getConfig()->getCourseEmail(),
            'vcs_base_url'                   => $this->core->getConfig()->getVcsBaseUrl(),
            'vcs_type'                       => $this->core->getConfig()->getVcsType(),
            'forum_enabled'                  => $this->core->getConfig()->isForumEnabled(),
            'regrade_enabled'                => $this->core->getConfig()->isRegradeEnabled(),
            'regrade_message'                => $this->core->getConfig()->getRegradeMessage(),
            'private_repository'             => $this->core->getConfig()->getPrivateRepository(),
            'room_seating_gradeable_id'      => $this->core->getConfig()->getRoomSeatingGradeableId(),
            'seating_only_for_instructor'    => $this->core->getConfig()->isSeatingOnlyForInstructor(),
            'auto_rainbow_grades'            => $this->core->getConfig()->getAutoRainbowGrades()
        );

        return Response::WebOnlyResponse(
            new WebResponse(
                ['admin', 'Configuration'],
                'viewConfig',
                $fields,
                $this->getGradeableSeatingOptions()
            )
        );
    }

    /**
     * @Route("/{_semester}/{_course}/config/update", methods={"POST"})
     * @return Response
     */
    public function updateConfiguration() {
        if(!isset($_POST['name'])) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Name of config value not provided')
            );
        }
        $name = $_POST['name'];

        if(!isset($_POST['entry'])) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Name of config entry not provided')
            );
        }
        $entry = $_POST['entry'];

        if($name === "room_seating_gradeable_id") {
            $gradeable_seating_options = $this->getGradeableSeatingOptions();
            $gradeable_ids = array();
            foreach($gradeable_seating_options as $option) {
                $gradeable_ids[] = $option['g_id'];
            }
            if(!in_array($entry, $gradeable_ids)) {
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Invalid gradeable chosen for seating')
                );
            }
        }
        else if(in_array($name, array('default_hw_late_days', 'default_student_late_days'))) {
            if(!ctype_digit($entry)) {
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse('Must enter a number for this field')
                );
            }
            $entry = intval($entry);
        }
        else if(in_array($name, array('zero_rubric_grades', 'keep_previous_files', 'display_rainbow_grades_summary',
                                      'display_custom_message', 'forum_enabled', 'regrade_enabled', 'seating_only_for_instructor'))) {
            $entry = $entry === "true" ? true : false;
        }
        else if($name === 'upload_message') {
            $entry = nl2br($entry);
        }
        else if($name == "course_home_url") {
            if(!filter_var($entry, FILTER_VALIDATE_URL) && !empty($entry)){
                return Response::JsonOnlyResponse(
                    JsonResponse::getFailResponse($entry . ' is not a valid URL')
                );
            }
        }

        if($name === 'forum_enabled') {
            if($entry == 1){
                if($this->core->getAccess()->canI("forum.modify_category")) {
                    $categories = ["General Questions", "Homework Help", "Quizzes" , "Tests"];
                    $rows = $this->core->getQueries()->getCategories();

                    foreach ($categories as $category) {
                        if (ForumUtils::isValidCategories($rows, -1, array($category))) {
                            $this->core->getQueries()->addNewCategory($category);
                        }
                    }
                }
            }
        }

        $config_ini = $this->core->getConfig()->getCourseJson();
        if(!isset($config_ini['course_details'][$name])) {
            return Response::JsonOnlyResponse(
                JsonResponse::getFailResponse('Not a valid config name')
            );
        }
        $config_ini['course_details'][$name] = $entry;
        $this->core->getConfig()->saveCourseJson(['course_details' => $config_ini['course_details']]);

        return Response::JsonOnlyResponse(
            JsonResponse::getSuccessResponse(null)
        );
    }

    private function getGradeableSeatingOptions() {
        $gradeable_seating_options = $this->core->getQueries()->getAllGradeablesIdsAndTitles();

        $seating_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), 'reports', 'seating');

        $gradeable_seating_options = array_filter($gradeable_seating_options, function($seating_option) use($seating_dir) {
            return is_dir(FileUtils::joinPaths($seating_dir, $seating_option['g_id']));
        });

        $empty_option = [[
            'g_id' => "",
            'g_title' => "--None--"
        ]];

        return $empty_option + $gradeable_seating_options;
    }
}
