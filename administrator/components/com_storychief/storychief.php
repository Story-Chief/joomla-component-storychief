<?php
/**
 * @package    storychief
 *
 * @author     StoryChief <support@storychief.io>
 * @copyright  A copyright
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://storychief.io/integrations/joomla
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Version;

defined('_JEXEC') or die;

if (version_compare(PHP_VERSION, '7.2.0', 'lt')) {
    die('Your PHP version is too old for this component (min 7.2).');
}

if (version_compare(Version::MAJOR_VERSION.'.'.Version::MINOR_VERSION.'.'.Version::PATCH_VERSION, '4.1', 'lt')) {
    die('Your Joomla! version is too old for this component (min 4.1).');
}

// Access check.
if (!Factory::getUser()->authorise('core.manage', 'com_storychief')) {
    throw new InvalidArgumentException(Text::_('JERROR_ALERTNOAUTHOR'), 404);
}

// Execute the task
$controller = BaseController::getInstance('storychief');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
