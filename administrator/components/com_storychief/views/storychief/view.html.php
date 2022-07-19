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
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

defined('_JEXEC') or die;

class StorychiefViewStorychief extends HtmlView {

    /**
     * The sidebar to show
     *
     * @var    string
     * @since  1.0
     */
    protected $sidebar = '';

    /**
     * Execute and display a template script.
     *
     * @param   string $tpl The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, otherwise a JError object.
     *
     * @see     fetch()
     * @since   1.0
     */
    public function display($tpl = null) {
        // Show the toolbar
        $this->toolbar();

        $this->sidebar = Sidebar::render();

        parent::display($tpl);
    }

    /**
     * Displays a toolbar for a specific page.
     *
     * @return  void.
     *
     * @since   1.0
     */
    private function toolbar()
    {
        ToolbarHelper::title(Text::_('COM_STORYCHIEF'), '');

        // Options button.
        if (Factory::getUser()->authorise('core.admin', 'com_storychief'))
        {
            ToolbarHelper::preferences('com_storychief');
        }
    }
}
