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
 * Message output socialgw testcase.
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../message_output_socialgw.php');

/**
 * Class message_output_socialgw_testcase
 *
 * @package    message_socialgw
 * @copyright  2024 Developer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_output_socialgw_testcase extends advanced_testcase {

    public function test_process_form() {
        $this->resetAfterTest();

        $processor = new message_output_socialgw();

        $form = new stdClass();
        $form->telegramchatid = '123456789';
        $form->whatsappnumber = '+19876543210';

        $preferences = [];

        $processor->process_form($form, $preferences);

        $this->assertEquals('123456789', $preferences['message_processor_socialgw_telegramchatid']);
        $this->assertEquals('+19876543210', $preferences['message_processor_socialgw_whatsappnumber']);
    }

    public function test_load_data() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        set_user_preference('message_processor_socialgw_telegramchatid', '55555', $user);
        set_user_preference('message_processor_socialgw_whatsappnumber', '+445555', $user);

        $processor = new message_output_socialgw();
        $preferences = new stdClass();

        $processor->load_data($preferences, $user->id);

        $this->assertEquals('55555', $preferences->telegramchatid);
        $this->assertEquals('+445555', $preferences->whatsappnumber);
    }
}
