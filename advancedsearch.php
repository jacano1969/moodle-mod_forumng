<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Search that lets you do full-text and/or author and date conditions.
 * @package mod
 * @subpackage forumng
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once('mod_forumng.php');
require_once('advancedsearchlib.php');

class advancedsearch_form extends moodleform {
    public function definition() {
        global $CFG;
        $mform =& $this->_form;

        $mform->addElement('header', 'heading', get_string('advancedsearch', 'forumng'));

        $mform->addElement('hidden', 'course', $this->_customdata['course']);
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        if (isset($this->_customdata['cloneid'])) {
            $mform->addElement('hidden', 'clone', $this->_customdata['cloneid']);
        }

        //words to be searched
        $mform->addElement('text', 'query', get_string('words', 'forumng'), 'size="40"');

        //author name or OUCU to be filtered
        $mform->addElement('text', 'author', get_string('authorname', 'forumng'), 'size="40"');

        // Date range_from to be filtered
        $mform->addElement('date_time_selector', 'datefrom',
                get_string('daterangefrom', 'forumng'),
                array('optional'=>true, 'step'=>1));

        // Date range_to to be filtered
        $mform->addElement('date_time_selector', 'dateto',
                get_string('daterangeto', 'forumng'),
                array('optional'=>true, 'step'=>1));

        // Add help buttons
        $mform->addHelpButton('query', 'words', 'forumng');
        $mform->addHelpButton('author', 'authorname', 'forumng');
        $mform->addHelpButton('datefrom', 'daterangefrom', 'forumng');

        //Set default hour and minute for "Date ranfe from" and "date range to"
        $mform->addElement('static', 'sethourandminute', '',
        '<script type="text/javascript">
//<![CDATA[
        //check whether "Date range from" and/or "Date range to" are disabled
        var datefromenabled = false;
        var datetoenabled = false;
        var inputs = document.getElementsByTagName("input");
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].type == "checkbox") {
                if (inputs[i].checked == true) {
                    if (inputs[i].name == "datefrom[off]") {
                        datefromenabled = true;
                    }
                    if (inputs[i].name == "dateto[off]") {
                        datetoenabled = true;
                    }
                }
            }
        }
        //Set hour and minute of "Date range from" and "Date range to"
        var sel = document.getElementsByTagName("select");
        for (var i = 0; i < sel.length; i++) {
            if (datefromenabled == true) {
                if (sel[i].name == "datefrom[hour]") {
                    sel[i].options[0].selected = true;
                }
                if (sel[i].name == "datefrom[minute]") {
                    sel[i].options[0].selected = true;
                }
            }
            if (datetoenabled == true) {
                if (sel[i].name == "dateto[hour]") {
                    sel[i].options[23].selected = true;
                }
                if (sel[i].name == "dateto[minute]") {
                    sel[i].options[59].selected = true;
                }
            }
        }
//]]>
        </script>');

        // Add "Search all forums"/"Search this forum" and "Cancel" buttons
        if ($this->_customdata['course']) {
            $this->add_action_buttons(true, get_string('searchallforums', 'forumng'));
        } else {
            $this->add_action_buttons(true, get_string('searchthisforum', 'forumng'));
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['datefrom'] > time()) {
            $errors['datefrom'] = get_string('inappropriatedateortime', 'forumng');
        }
        if (($data['datefrom'] > $data['dateto']) && $data['dateto'] != 0) {
            $errors['dateto'] = get_string('daterangemismatch', 'forumng');
        }
        if (($data['query'] === '') && ($data['author'] === '') &&
            !$data['datefrom'] && !$data['dateto']) {
            $errors['sethourandminute'] = get_string('nosearchcriteria', 'forumng');
        }
        return $errors;
    }
}
///////////////////////////////////////////////////////////////////////////////

$url = new Moodle_url('/mod/forumng/advancedsearch.php');
$courseid = optional_param('course', 0,  PARAM_INT);
if ($courseid) {
    $url->param('course', $courseid);
    $cmid = 0;
} else {
    $cmid = required_param('id', PARAM_INT);
    $url->param('id', $cmid);
}
$query = trim(optional_param('query', '', PARAM_RAW));
if ($query !== '') {
    $url->param('query', rawurlencode($query));
}
$author = trim(optional_param('author', '', PARAM_RAW));
if ($author !== '') {
    $url->param('author', rawurlencode($author));
}
$cloneid = optional_param('clone', 0, PARAM_INT);
if ($cloneid) {
    // clone is used to mark shared forums
    $url->param('clone', $cloneid);
}
$datefrom = optional_param_array('datefrom', 0, PARAM_INT);
if (!empty($datefrom)) {
    foreach ($datefrom as $key => $value) {
        $url->param('datefrom[' . $key . ']', $value);
    }
}
$dateto = optional_param_array('dateto', 0, PARAM_INT);
if (!empty($dateto)) {
    foreach ($dateto as $key => $value) {
        $url->param('dateto[' . $key . ']', $value);
    }
}

// Search in a single forum
if ($cmid) {
    $forum = mod_forumng::get_from_cmid($cmid, $cloneid);
    $cm = $forum->get_course_module();
    $course = $forum->get_course();
    $forum->require_view(mod_forumng::NO_GROUPS, 0, true);
    mod_forumng::search_installed();
    $allforums = false;
}
if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    require_login($course);
    $coursecontext = context_course::instance($courseid);
    mod_forumng::search_installed();
    $allforums = true;
}

// Set up page
$PAGE->set_url($url);
$PAGE->set_heading($course->fullname);
if ($allforums) {
    $PAGE->set_context($coursecontext);
    $PAGE->set_title($course->shortname . ': ' . get_string('searchallforums', 'forumng'));
} else {
    $PAGE->set_context($forum->get_context());
    $PAGE->set_cm($cm, $course);
    $PAGE->set_title($course->shortname . ': ' . format_string($forum->get_name()));
}
$PAGE->set_pagelayout('base');
$PAGE->navbar->add(get_string('advancedsearch', 'forumng'));

//display the form
if ($allforums) {
    $editform = new advancedsearch_form('advancedsearch.php',
                array('course'=> $courseid, 'id'=> $cmid), 'get');
} else {
    $editform = new advancedsearch_form('advancedsearch.php',
        array('course'=> $courseid, 'id'=> $cmid, 'cloneid' => $cloneid), 'get');
}
$inputdata = new stdClass;
$inputdata->query = $query;
$inputdata->author = $author;
$inputdata->datefrom = $datefrom;
$inputdata->dateto = $dateto;
$editform->set_data($inputdata);

$data = $editform->get_data();

if ($editform->is_cancelled()) {
    if (isset($forum) ) {
        $returnurl = $forum->get_url(mod_forumng::PARAM_PLAIN);
    } else {
        $returnurl = $CFG->wwwroot . '/course/view.php?id=' . $course->id;
    }
    redirect($returnurl, '', 0);
}

$datefromint = isset($data->datefrom) ? $data->datefrom : 0;
$datetoint = isset($data->dateto) ? $data->dateto : 0;
$action = $query !== '' || $author !== '' || $datefromint || $datetoint ||
        !empty($datefrom) || !empty($dateto);

// Display header
$out = mod_forumng_utils::get_renderer();
print $out->header();

$searchtitle = forumng_get_search_results_title($query, $author, $datefromint, $datetoint);

if (!$allforums) {
    // Display group selector if required
    groups_print_activity_menu($cm, $url);
    $groupid = mod_forumng::get_activity_group($cm, true);
    $forum->require_view($groupid, 0, true);
    print '<br/><br/>';
}
$editform->display();

// Searching for free text with or without filtering author and date range
if ($query) {
    $result = new local_ousearch_search($query);
    // Search all forums
    if ($allforums) {
        $result->set_plugin('mod/forumng');
        $result->set_course_id($courseid);
        $result->set_visible_modules_in_course($COURSE);

        // Restrict them to the groups they belong to
        if (!isset($USER->groupmember[$courseid])) {
            $result->set_group_ids(array());
        } else {
            $result->set_group_ids($USER->groupmember[$courseid]);
        }
        // Add exceptions where they can see other groups
        $result->set_group_exceptions(local_ousearch_search::get_group_exceptions($courseid));

        $result->set_user_id($USER->id);
    } else {// Search this forum
        $result->set_coursemodule($forum->get_course_module(true));
        if ($groupid && $groupid!=mod_forumng::NO_GROUPS) {
            $result->set_group_id($groupid);
        }
    }
    $result->set_filter('forumng_exclude_words_filter');
    print $result->display_results($url, $searchtitle);

// Searching without free text using author and/or date range
} else if ($action) {
    $page = optional_param('page', 0, PARAM_INT);
    $prevpage = $page-FORUMNG_SEARCH_RESULTSPERPAGE;
    $prevrange = ($page-FORUMNG_SEARCH_RESULTSPERPAGE+1) . ' - ' . $page;

    //Get result from db
    if ($allforums) {
        $results = forumng_get_results_for_all_forums($course, $author,
                $datefromint, $datetoint, $page);
    } else {
        $results = forumng_get_results_for_this_forum($forum, $groupid, $author,
                $datefromint, $datetoint, $page);
    }
    $nextpage = $page + FORUMNG_SEARCH_RESULTSPERPAGE;

    $linknext = null;
    $linkprev = null;

    if ($results->success) {
        if (($page-FORUMNG_SEARCH_RESULTSPERPAGE+1)>0) {
            $url->param('page', $prevpage);
            // we cannot pass the moodle_url straight through to format_results yet because
            // these links will be re-encoded by the call to s(). If SC, ouwiki and local/ousearch
            // is converted to use moodle_url then we can get rid of the str_replace here.
            $linkprev = str_replace('&amp;', '&', $url->out());
        }
        if ($results->numberofentries == FORUMNG_SEARCH_RESULTSPERPAGE) {
            $url->param('page', $nextpage);
            $linknext = str_replace('&amp;', '&', $url->out());
        }
    }
    if ($results->done ===1) {
        if (($page-FORUMNG_SEARCH_RESULTSPERPAGE+1)>0) {
            $url->param('page', $prevpage);
            $linkprev = str_replace('&amp;', '&', $url->out());
        }
    }
    print local_ousearch_search::format_results($results, $searchtitle, $page+1, $linkprev,
                    $prevrange, $linknext, $results->searchtime);
}

print $out->footer();
