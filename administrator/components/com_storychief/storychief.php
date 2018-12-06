<?php
/**
 * @package    storychief
 *
 * @author     Greg <your@email.com>
 * @copyright  A copyright
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @link       http://your.url.com
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

defined('_JEXEC') or die;


if (version_compare(PHP_VERSION, '7.0.0', 'lt')) {
    die('Your PHP version is too old for this component (min 7.0).');
}

if (version_compare(JVERSION::RELEASE, '3.9', 'lt')) {
    die('Your Joomla! version is too old for this component (min 3.9).');
}

// Access check.
if (!Factory::getUser()->authorise('core.manage', 'com_storychief')) {
    throw new InvalidArgumentException(Text::_('JERROR_ALERTNOAUTHOR'), 404);
}

// Execute the task
$controller = BaseController::getInstance('storychief');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
