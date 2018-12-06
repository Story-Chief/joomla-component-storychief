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
use Joomla\CMS\MVC\Controller\BaseController;

defined('_JEXEC') or die;

JLoader::register('StoryHelper',JPATH_COMPONENT.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'story.php');
JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');
JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

$controller = BaseController::getInstance('storychief');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
