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
use Joomla\CMS\MVC\Controller\BaseController;

defined('_JEXEC') or die;

JLoader::register('StoryHelper',JPATH_COMPONENT.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'story.php');
JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');
JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

$controller = BaseController::getInstance('storychief');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
